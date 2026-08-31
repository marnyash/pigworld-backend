<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MpesaService
{
    public function initiate(Payment $payment): array
    {
        $baseUrl = config('services.mpesa.environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        $key = config('services.mpesa.consumer_key');
        $secret = config('services.mpesa.consumer_secret');
        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $callbackUrl = config('services.mpesa.callback_url');

        if (! $key || ! $secret || ! $shortcode || ! $passkey || ! $callbackUrl) {
            throw new RuntimeException('M-Pesa is not configured.');
        }

        $accessToken = Http::asForm()->acceptJson()
            ->withBasicAuth($key, $secret)
            ->get($baseUrl.'/oauth/v1/generate?grant_type=client_credentials')
            ->throw()->json('access_token');
        $timestamp = now()->format('YmdHis');

        return Http::acceptJson()->withToken($accessToken)->post($baseUrl.'/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$passkey.$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) ceil((float) $payment->amount),
            'PartyA' => $payment->phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $payment->phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => 'Farm-'.$payment->farm_id,
            'TransactionDesc' => 'Pig World subscription',
        ])->throw()->json();
    }
}