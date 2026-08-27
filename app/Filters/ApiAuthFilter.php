<?php

namespace App\Filters;

use App\Models\ApiClientModel;
use App\Models\ApiKeyModel;
use App\Services\RateLimitService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Api;
use Config\Services;
use Throwable;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $context = Services::apiClientContext();
        $context->clear();

        $apiKey = $this->extractApiKey($request);

        if ($apiKey === '') {
            return $this->error('UNAUTHORIZED', 'A valid API key is required.', 401);
        }

        try {
            $client = (new ApiKeyModel())->findClientByPlainTextKey($apiKey);
        } catch (Throwable $exception) {
            log_message('error', 'API authentication storage failure: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->error('AUTHENTICATION_UNAVAILABLE', 'Authentication service is unavailable.', 500);
        }

        if ($client === null) {
            return $this->error('UNAUTHORIZED', 'A valid API key is required.', 401);
        }

        $apiConfig = config(Api::class);
        $clientId = (int) $client['id'];
        $clientModel = new ApiClientModel();
        $clientModel->resetMonthlyUsageIfNeeded($clientId);

        $freshClient = $clientModel->find($clientId) ?? $client;
        $used = (int) ($freshClient['requests_used'] ?? 0);
        $quota = (int) ($freshClient['monthly_quota'] ?? $apiConfig->defaultMonthlyQuota);

        if ($quota > 0 && $used >= $quota) {
            return $this->error('QUOTA_EXCEEDED', 'Monthly API quota has been exceeded.', 403);
        }

        $rateLimit = (int) ($freshClient['rate_limit_per_minute'] ?? $apiConfig->defaultRateLimit);
        $rate = (new RateLimitService())->hit($clientId, $rateLimit);

        if (! $rate['allowed']) {
            return $this->error('RATE_LIMIT_EXCEEDED', 'Too many requests. Try again later.', 429)
                ->setHeader('Retry-After', (string) $rate['retry_after'])
                ->setHeader('X-RateLimit-Limit', (string) $rate['limit'])
                ->setHeader('X-RateLimit-Remaining', '0');
        }

        $requiresAdmin = is_array($arguments) && in_array('admin', $arguments, true);

        if ($requiresAdmin && ! (bool) ($freshClient['is_admin'] ?? false)) {
            return $this->error('FORBIDDEN', 'Admin privileges are required.', 403);
        }

        (new ApiKeyModel())->markUsed((int) $client['api_key_id']);
        $clientModel->incrementMonthlyUsage($clientId);

        $freshClient['api_key_id'] = $client['api_key_id'];
        $freshClient['key_prefix'] = $client['key_prefix'];
        $context->setClient($freshClient);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    private function extractApiKey(RequestInterface $request): string
    {
        $headerKey = trim($request->getHeaderLine('X-API-Key'));

        if ($headerKey !== '') {
            return $headerKey;
        }

        $authorization = trim($request->getHeaderLine('Authorization'));

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    private function error(string $code, string $message, int $status): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'error'   => [
                    'code'    => $code,
                    'message' => $message,
                ],
            ]);
    }
}
