<?php

namespace App\Services\Payments;

use App\Exceptions\MpesaGatewayException;
use App\Models\Payment;
use Illuminate\Http\Client\Response;
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

        $tokenResponse = Http::asForm()->acceptJson()->timeout(30)->connectTimeout(15)
            ->withBasicAuth($key, $secret)
            ->get($baseUrl.'/oauth/v1/generate?grant_type=client_credentials');
        $this->throwIfProviderFailed($tokenResponse, 'OAuth');
        $accessToken = $tokenResponse->json('access_token');
        if (! is_string($accessToken) || blank($accessToken)) {
            throw new RuntimeException('M-Pesa did not return an access token.');
        }
        $timestamp = now()->format('YmdHis');

        $stkResponse = Http::acceptJson()->timeout(30)->connectTimeout(15)
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
            ]);

        $this->throwIfProviderFailed($stkResponse, 'STK push');
        $response = $stkResponse->json();
        if (! is_array($response)) {
            throw new RuntimeException('M-Pesa returned an invalid STK response.');
        }

        if (($response['ResponseCode'] ?? null) !== '0') {
            throw new MpesaGatewayException(
                'STK push',
                $stkResponse->status(),
                $this->scalar($response['errorCode'] ?? $response['ResponseCode'] ?? null),
                $this->scalar($response['requestId'] ?? $response['RequestId'] ?? $response['MerchantRequestID'] ?? null),
                $this->scalar($response['errorMessage'] ?? $response['ResponseDescription'] ?? null)
                    ?? 'Safaricom rejected the STK request.',
            );
        }

        return $response;
    }

    private function throwIfProviderFailed(Response $response, string $stage): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];

        throw new MpesaGatewayException(
            $stage,
            $response->status(),
            $this->scalar($body['errorCode'] ?? $body['ResponseCode'] ?? null),
            $this->scalar($body['requestId'] ?? $body['RequestId'] ?? $body['MerchantRequestID'] ?? null),
            $this->scalar($body['errorMessage'] ?? $body['ResponseDescription'] ?? $body['message'] ?? null)
                ?? 'Safaricom returned an unsuccessful response.',
        );
    }

    private function scalar(mixed $value): ?string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : null;
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
