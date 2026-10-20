<?php

declare(strict_types=1);

namespace YouWillLikeIT\Cleaning\Middleware;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Configurable;

/**
 * Per-IP rate limiting for portal API endpoints.
 *
 * Configuration (config.yml):
 *
 *   YouWillLikeIT\Cleaning\Middleware\RateLimitMiddleware:
 *     rules:
 *       - prefix: portal/photo
 *         limit: 30
 *         window: 60
 *       - prefix: portal/
 *         limit: 120
 *         window: 60
 *       - prefix: revir-qr/
 *         limit: 30
 *         window: 3600
 *
 * `limit`  = max requests per IP in the window
 * `window` = sliding window in seconds
 *
 * Attempts are stored in the PHP session (keyed by IP + prefix + bucket).
 * Does NOT require Redis or a persistent cache — suitable for shared hosting.
 *
 * If session is unavailable, the middleware passes the request through
 * rather than blocking (fail-open is deliberate — availability > protection
 * for a cleaning SaaS context; adjust if needed).
 */
class RateLimitMiddleware implements HTTPMiddleware
{
    use Configurable;

    /**
     * Rate limit rules. Each rule matches request URL prefixes.
     * First matching rule wins (most-specific first).
     *
     * @var list<array{prefix: string, limit: int, window: int}>
     */
    private static array $rules = [
        // Photo upload: stricter (large payloads)
        ['prefix' => 'portal/photo', 'limit' => 30,  'window' => 60],
        // General portal: generous
        ['prefix' => 'portal/',      'limit' => 120, 'window' => 60],
        // QR scan: per-hour
        ['prefix' => 'revir-qr/',    'limit' => 30,  'window' => 3600],
        // Manager portal API
        ['prefix' => 'manager/api/', 'limit' => 60,  'window' => 60],
    ];

    // ── HTTPMiddleware ────────────────────────────────────────────────────

    public function process(HTTPRequest $request, callable $delegate): HTTPResponse
    {
        $rule = $this->matchRule($request);

        if ($rule !== null && $this->isLimitExceeded($request, $rule)) {
            return $this->throttledResponse($rule);
        }

        return $delegate($request);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * @return array{prefix: string, limit: int, window: int}|null
     */
    private function matchRule(HTTPRequest $request): ?array
    {
        $url   = ltrim($request->getURL(), '/');
        $rules = (array) static::config()->get('rules');

        foreach ($rules as $rule) {
            if (str_starts_with($url, (string) ($rule['prefix'] ?? ''))) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param array{prefix: string, limit: int, window: int} $rule
     */
    private function isLimitExceeded(HTTPRequest $request, array $rule): bool
    {
        try {
            $session = $request->getSession();
        } catch (\Throwable) {
            // Session unavailable — pass through (fail-open)
            return false;
        }

        $ip     = $request->getIP();
        $window = (int) ($rule['window'] ?? 60);
        $limit  = (int) ($rule['limit']  ?? 60);
        $prefix = (string) ($rule['prefix'] ?? '');

        // Bucket by hour or minute depending on window size
        $bucket = (int) floor(time() / $window);
        $key    = 'rl_' . md5($ip . $prefix) . '_' . $bucket;

        $attempts = (int) ($session->get($key) ?? 0);

        if ($attempts >= $limit) {
            return true;
        }

        $session->set($key, $attempts + 1);

        // Clean up the previous bucket key to avoid session bloat
        $prevKey = 'rl_' . md5($ip . $prefix) . '_' . ($bucket - 1);
        $session->clear($prevKey);

        return false;
    }

    /**
     * @param array{prefix: string, limit: int, window: int} $rule
     */
    private function throttledResponse(array $rule): HTTPResponse
    {
        $window  = (int) ($rule['window'] ?? 60);
        $retryAt = (int) (ceil(time() / $window) * $window);

        return HTTPResponse::create()
            ->setStatusCode(429)
            ->addHeader('Content-Type', 'application/json')
            ->addHeader('Retry-After', (string) ($retryAt - time()))
            ->addHeader('X-RateLimit-Limit', (string) ($rule['limit'] ?? 60))
            ->setBody((string) json_encode([
                'error'      => 'Too many requests',
                'retry_after' => $retryAt - time(),
            ]));
    }
}
