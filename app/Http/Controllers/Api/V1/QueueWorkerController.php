<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Throwable;

class QueueWorkerController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $configuredSecret = (string) config('queue.http_worker.secret');
        $providedSecret = (string) $request->header('X-Queue-Secret', '');

        if ($configuredSecret === '') {
            return response()->json([
                'message' => 'HTTP queue processing is not configured.',
            ], 503);
        }

        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json([
                'message' => 'Invalid queue worker credentials.',
            ], 401);
        }

        $lock = Cache::lock(
            'http-queue-worker',
            (int) config('queue.http_worker.lock_seconds', 75),
        );

        if (! $lock->get()) {
            return response()->json([
                'message' => 'A queue worker is already running.',
            ], 409);
        }

        try {
            $connection = (string) config('queue.default');
            $queue = (string) config('queue.http_worker.queue', 'default');
            $pendingBefore = Queue::connection($connection)->size($queue);

            $exitCode = Artisan::call('queue:work', [
                'connection' => $connection,
                '--queue' => $queue,
                '--stop-when-empty' => true,
                '--tries' => (int) config('queue.http_worker.tries', 3),
                '--timeout' => (int) config('queue.http_worker.timeout', 40),
                '--max-time' => (int) config('queue.http_worker.max_time', 50),
                '--max-jobs' => (int) config('queue.http_worker.max_jobs', 100),
                '--no-interaction' => true,
            ]);

            $pendingAfter = Queue::connection($connection)->size($queue);

            return response()->json([
                'message' => $exitCode === 0
                    ? 'Available queue jobs were processed.'
                    : 'The queue worker exited with an error.',
                'connection' => $connection,
                'queue' => $queue,
                'pending_before' => $pendingBefore,
                'pending_after' => $pendingAfter,
                'exit_code' => $exitCode,
                'output' => trim(Artisan::output()),
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The queue worker could not be started.',
                'error' => app()->hasDebugModeEnabled()
                    ? $exception->getMessage()
                    : 'Check the server log for details.',
            ], 500);
        } finally {
            $lock->release();
        }
    }
}
