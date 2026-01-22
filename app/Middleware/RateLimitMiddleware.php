<?php

namespace App\Middleware;

use Illuminate\Database\Capsule\Manager as Capsule;

class RateLimitMiddleware
{
    /**
     * Throttle requests per user + action within a time window.
     */
    public static function throttle(string $action, int $limit, int $windowSeconds = 60, ?int $userId = null): void
    {
        $userId = $userId ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Align window start to the nearest window boundary
        $windowStart = date('Y-m-d H:i:s', floor(time() / $windowSeconds) * $windowSeconds);

        // Remove very old buckets to prevent unbounded growth
        try {
            Capsule::table('rate_limits')
                ->where('window_start', '<', date('Y-m-d H:i:s', strtotime('-2 days')))
                ->limit(500)
                ->delete();
        } catch (\Exception $e) {
            // best-effort cleanup; do not block the request
        }

        try {
            $existing = Capsule::table('rate_limits')
                ->where('action', $action)
                ->where('window_start', $windowStart)
                ->when($userId !== null, fn($q) => $q->where('user_id', $userId))
                ->when($userId === null, fn($q) => $q->whereNull('user_id'))
                ->first();

            if (!$existing) {
                Capsule::table('rate_limits')->insert([
                    'user_id' => $userId,
                    'action' => $action,
                    'ip_address' => $ip,
                    'window_start' => $windowStart,
                    'count' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                return;
            }

            if ($existing->count >= $limit) {
                self::reject($action, $limit, $windowSeconds);
            }

            Capsule::table('rate_limits')
                ->where('id', $existing->id)
                ->update([
                    'count' => $existing->count + 1,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'ip_address' => $ip,
                ]);
        } catch (\Exception $e) {
            // If rate limit storage fails, fail closed to avoid abuse in production
            if (env('APP_ENV', 'production') === 'production') {
                self::reject($action, $limit, $windowSeconds);
            }
            // In non-production, allow but log
            error_log('RateLimitMiddleware error: ' . $e->getMessage());
        }
    }

    private static function reject(string $action, int $limit, int $windowSeconds): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Too Many Requests',
            'action' => $action,
            'limit' => $limit,
            'window_seconds' => $windowSeconds,
        ]);
        exit;
    }
}
