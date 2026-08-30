<?php

namespace App\Services;

use App\Exceptions\FinanceIntegrationException;
use App\Support\CanonicalJson;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FinanceIntegrationClient
{
    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public function send(array $envelope): array
    {
        $baseUrl = rtrim((string) config('services.finance.base_url'), '/');
        $token = (string) config('services.finance.service_token');

        if ($baseUrl === '' || $token === '') {
            throw new FinanceIntegrationException(
                'Finance Service belum dikonfigurasi.',
                0,
                'CONFIG',
                retryable: true,
            );
        }

        $body = CanonicalJson::encode($envelope);
        $timestamp = (string) time();
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $hmacSecret = (string) config('services.finance.hmac_secret');
        if ($hmacSecret !== '') {
            $headers['X-Integration-Timestamp'] = $timestamp;
            $headers['X-Integration-Signature'] = hash_hmac('sha256', $timestamp.'.'.$body, $hmacSecret);
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders($headers)
                ->timeout((int) config('services.finance.timeout', 15))
                ->withBody($body, 'application/json')
                ->post('/api/internal/plantation/events');
        } catch (ConnectionException $exception) {
            throw new FinanceIntegrationException(
                'Finance Service sedang tidak dapat dihubungi.',
                0,
                'NETWORK',
                retryable: true,
                previous: $exception,
            );
        }

        $json = $response->json();
        $data = is_array($json) ? $json : [];

        if ($response->successful()) {
            return $data;
        }

        $code = is_string($data['code'] ?? null) ? $data['code'] : null;
        $message = $this->safeMessage($data, $response->status());

        if ($response->status() === 409 && $code === 'DEPENDENCY_NOT_READY') {
            throw new FinanceIntegrationException($message, 409, $code, retryable: true);
        }

        if ($response->status() === 409 && ($data['already_processed'] ?? false) === true) {
            return array_merge($data, ['already_processed' => true, 'ok' => true]);
        }

        if ($response->status() === 409) {
            throw new FinanceIntegrationException($message, 409, $code ?? 'CONFLICT', retryable: false);
        }

        if (in_array($response->status(), [401, 403, 422], true)) {
            throw new FinanceIntegrationException($message, $response->status(), $code, retryable: false);
        }

        if ($response->serverError() || $response->status() === 0) {
            Log::warning('finance.integration_http_failed', [
                'status' => $response->status(),
                'event_type' => $envelope['event_type'] ?? null,
            ]);

            throw new FinanceIntegrationException($message, $response->status(), 'SERVER', retryable: true);
        }

        throw new FinanceIntegrationException($message, $response->status(), $code, retryable: false);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function safeMessage(array $data, int $status): string
    {
        $message = $data['message'] ?? null;

        if (is_string($message) && $message !== '') {
            return mb_substr($message, 0, 180);
        }

        return 'Finance Service menolak event (HTTP '.$status.').';
    }
}
