<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreMpesaPaymentRequest;
use App\Models\Farm;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Payments\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MpesaPaymentController extends Controller
{
    public function store(StoreMpesaPaymentRequest $request, Farm $farm, MpesaService $mpesa): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->farms()->whereKey($farm->id)->exists(), 403, 'You do not belong to this farm.');
        abort_if(blank($user->phone), 422, 'A phone number is required for M-Pesa payment.');

        $count = (int) ($farm->mother_pig_count ?? 0);
        $plan = SubscriptionPlan::query()->where('code', $request->string('plan'))->where('active', true)->firstOrFail();
        abort_if($count < 1, 422, 'Enter the number of mother pigs before paying.');
        abort_if($plan->pig_limit !== null && $count > $plan->pig_limit, 422, 'This plan does not cover the farm herd size.');
        abort_if(strtoupper($plan->currency) !== 'KES', 422, 'M-Pesa payments require a KES subscription plan.');

        $phone = $this->normalizeKenyanPhone($user->phone);
        abort_unless($phone, 422, 'Use a valid Kenyan M-Pesa phone number.');

        $payment = Payment::create([
            'farm_id' => $farm->id,
            'user_id' => $user->id,
            'plan_code' => $plan->code,
            'mother_pig_count' => $count,
            'amount' => $plan->amount,
            'currency' => $plan->currency,
            'phone' => $phone,
            'status' => 'pending',
        ]);

        try {
            $response = $mpesa->initiate($payment);
            $payment->update([
                'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                'result_description' => $response['CustomerMessage'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $payment->update(['status' => 'failed', 'result_description' => 'Unable to start M-Pesa payment.']);
            throw $exception;
        }

        return response()->json(['payment' => $payment->fresh()], 201);
    }

    public function callback(Request $request): JsonResponse
    {
        $callback = $request->input('Body.stkCallback', []);
        $checkoutId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = (int) ($callback['ResultCode'] ?? 1);
        $payment = $checkoutId ? Payment::where('checkout_request_id', $checkoutId)->first() : null;

        if (! $payment) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        DB::transaction(function () use ($payment, $callback, $resultCode): void {
            if ($payment->status === 'paid') {
                return;
            }

            if ($resultCode !== 0) {
                $payment->update([
                    'status' => 'failed',
                    'result_description' => $callback['ResultDesc'] ?? 'Payment failed.',
                ]);

                return;
            }

            $items = collect($callback['CallbackMetadata']['Item'] ?? [])->keyBy('Name');
            $payment->update([
                'status' => 'paid',
                'mpesa_receipt' => $items->get('MpesaReceiptNumber')['Value'] ?? null,
                'result_description' => $callback['ResultDesc'] ?? 'Payment received.',
                'paid_at' => now(),
            ]);
            $payment->farm()->update(['subscription_plan' => $payment->plan_code]);
        });

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function normalizeKenyanPhone(string $phone): ?string
    {
        $normalized = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($normalized, '0')) {
            $normalized = '254'.substr($normalized, 1);
        }

        return preg_match('/^254[17]\d{8}$/', $normalized) ? $normalized : null;
    }
}
