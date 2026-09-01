<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MpesaService
{
    public function initiate(Payment $payment): array
    {
        $environment = config('services.mpesa.environment');
        $baseUrl = $environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        $key = config('services.mpesa.consumer_key');
        $secret = config('services.mpesa.consumer_secret');
        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $callbackUrl = config('services.mpesa.callback_url');

        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException('M-Pesa environment must be sandbox or production.');
        }

        if (! $key || ! $secret || ! $shortcode || ! $passkey || ! $callbackUrl) {
            throw new RuntimeException('M-Pesa is not configured.');
        }

        $this->validateCallbackUrl($callbackUrl);

        $accessToken = Http::asForm()->acceptJson()->timeout(15)->connectTimeout(5)
            ->withBasicAuth($key, $secret)
            ->get($baseUrl.'/oauth/v1/generate?grant_type=client_credentials')
            ->throw()->json('access_token');
        if (! is_string($accessToken) || blank($accessToken)) {
            throw new RuntimeException('M-Pesa did not return an access token.');
        }
        $timestamp = now()->format('YmdHis');

        $response = Http::acceptJson()->timeout(15)->connectTimeout(5)
            ->withToken($accessToken)->post($baseUrl.'/mpesa/stkpush/v1/processrequest', [
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

        if (($response['ResponseCode'] ?? null) !== '0') {
            throw new RuntimeException($response['errorMessage'] ?? $response['ResponseDescription'] ?? 'M-Pesa rejected the STK push.');
        }

        return $response;
    }

    private function validateCallbackUrl(string $callbackUrl): void
    {
        $parts = parse_url($callbackUrl);
        $host = $parts['host'] ?? '';

        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPublicIpAddress = filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        if (($parts['scheme'] ?? null) !== 'https' || ! $host ||
            in_array($host, ['localhost', 'example.test'], true) ||
            ($isIpAddress && ! $isPublicIpAddress)) {
            throw new RuntimeException('M-Pesa callback URL must be a publicly reachable HTTPS URL.');
        }
    }
}
