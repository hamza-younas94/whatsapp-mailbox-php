<?php

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

class JobQueue
{
    public static function enqueue(string $type, int $referenceId, array $payload, int $userId, ?string $availableAt = null): void
    {
        $availableAt = $availableAt ?: date('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');

        // Avoid duplicate pending jobs for same ref
        $exists = Capsule::table('job_queue')
            ->where('type', $type)
            ->where('reference_id', $referenceId)
            ->whereIn('status', ['pending', 'reserved'])
            ->exists();

        if ($exists) {
            return;
        }

        Capsule::table('job_queue')->insert([
            'user_id' => $userId,
            'type' => $type,
            'reference_id' => $referenceId,
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $availableAt,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    public static function reserveBatch(int $limit = 50): array
    {
        $now = date('Y-m-d H:i:s');
        $jobs = Capsule::table('job_queue')
            ->where('status', 'pending')
            ->where('available_at', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            return [];
        }

        $ids = $jobs->pluck('id')->all();
        Capsule::table('job_queue')
            ->whereIn('id', $ids)
            ->update(['status' => 'reserved', 'reserved_at' => $now, 'updated_at' => $now]);

        return $jobs->all();
    }

    public static function markSucceeded(int $id): void
    {
        Capsule::table('job_queue')->where('id', $id)->update([
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public static function markFailed(int $id, string $error, int $maxAttempts = 3, int $backoffSeconds = 300): void
    {
        $job = Capsule::table('job_queue')->where('id', $id)->first();
        if (!$job) {
            return;
        }

        $attempts = (int) $job->attempts + 1;
        $now = date('Y-m-d H:i:s');

        if ($attempts >= $maxAttempts) {
            Capsule::table('job_queue')->where('id', $id)->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => $error,
                'updated_at' => $now
            ]);
            return;
        }

        Capsule::table('job_queue')->where('id', $id)->update([
            'status' => 'pending',
            'attempts' => $attempts,
            'last_error' => $error,
            'available_at' => date('Y-m-d H:i:s', time() + $backoffSeconds),
            'updated_at' => $now
        ]);
    }
}
