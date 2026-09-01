<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MpesaPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_start_a_herd_based_mpesa_payment(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'access-token']),
            'sandbox.safaricom.co.ke/mpesa/*' => Http::response([
                'ResponseCode' => '0',
                'MerchantRequestID' => 'merchant-1',
                'CheckoutRequestID' => 'checkout-1',
                'CustomerMessage' => 'Success. Request accepted for processing.',
            ]),
        ]);
        $user = User::factory()->create(['role' => 'farmOwner', 'phone' => '254712345678']);
        $farm = Farm::create(['name' => 'Herd Farm', 'mother_pig_count' => 40]);
        $user->farms()->attach($farm);
        SubscriptionPlan::create([
            'code' => 'starter', 'name' => 'Starter', 'amount' => 500,
            'currency' => 'KES', 'pig_limit' => 50, 'active' => true,
        ]);
        config(['services.mpesa' => [
            'environment' => 'sandbox',
            'consumer_key' => 'key', 'consumer_secret' => 'secret',
            'shortcode' => '174379', 'passkey' => 'passkey',
            'callback_url' => 'https://payments.example.com/api/v1/payments/mpesa/callback',
        ]]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/subscription/payment", ['plan' => 'starter'])
            ->assertCreated()
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('payment.mother_pig_count', 40)
            ->assertJsonPath('payment.amount', '500.00');

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            && $request['Amount'] === 500
            && $request['PhoneNumber'] === '254712345678');
    }

    public function test_payment_normalizes_a_kenyan_phone_number_before_sending_the_stk_push(): void
    {
        Http::fake([
            'sandbox.safaricom.co.ke/oauth/*' => Http::response(['access_token' => 'access-token']),
            'sandbox.safaricom.co.ke/mpesa/*' => Http::response([
                'ResponseCode' => '0', 'MerchantRequestID' => 'merchant-2',
                'CheckoutRequestID' => 'checkout-2',
            ]),
        ]);
        $user = User::factory()->create(['role' => 'farmOwner', 'phone' => '0712 345 678']);
        $farm = Farm::create(['name' => 'Herd Farm', 'mother_pig_count' => 5]);
        $user->farms()->attach($farm);
        SubscriptionPlan::create([
            'code' => 'starter', 'name' => 'Starter', 'amount' => 500,
            'currency' => 'KES', 'pig_limit' => 50, 'active' => true,
        ]);
        config(['services.mpesa' => [
            'environment' => 'sandbox', 'consumer_key' => 'key', 'consumer_secret' => 'secret',
            'shortcode' => '174379', 'passkey' => 'passkey',
            'callback_url' => 'https://payments.example.com/api/v1/payments/mpesa/callback',
        ]]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/farms/{$farm->id}/subscription/payment", ['plan' => 'starter'])
            ->assertCreated();

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            && $request['PhoneNumber'] === '254712345678');
    }

    public function test_successful_callback_activates_the_plan_and_is_idempotent(): void
    {
        $user = User::factory()->create(['role' => 'farmOwner', 'phone' => '254712345678']);
        $farm = Farm::create(['name' => 'Paid Farm', 'mother_pig_count' => 10]);
        $payment = Payment::create([
            'farm_id' => $farm->id, 'user_id' => $user->id, 'plan_code' => 'starter',
            'mother_pig_count' => 10, 'amount' => 100, 'currency' => 'KES',
            'phone' => $user->phone, 'status' => 'pending',
            'checkout_request_id' => 'checkout-2',
        ]);

        $payload = ['Body' => ['stkCallback' => [
            'CheckoutRequestID' => 'checkout-2', 'ResultCode' => 0,
            'ResultDesc' => 'The service request is processed successfully.',
            'CallbackMetadata' => ['Item' => [
                ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
            ]],
        ]]];

        $this->postJson('/api/v1/payments/mpesa/callback', $payload)->assertOk();
        $this->postJson('/api/v1/payments/mpesa/callback', $payload)->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'mpesa_receipt' => 'ABC123']);
        $this->assertDatabaseHas('farms', ['id' => $farm->id, 'subscription_plan' => 'starter']);
    }
}
