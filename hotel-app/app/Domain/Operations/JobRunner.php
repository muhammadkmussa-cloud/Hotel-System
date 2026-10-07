<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Domain\Ids;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P15 — database-backed jobs for shared hosting (cron: `php artisan
 * hotel:run-jobs` every minute). Jobs are unique by key, leased with an
 * expiry and retried with backoff; periodic sweeps keep state honest.
 */
final class JobRunner
{
    public function dispatch(string $type, string $uniqueKey, array $payload, int $delaySeconds = 0): void
    {
        try {
            DB::table('background_jobs')->insert([
                'id' => Ids::new(), 'type' => $type, 'unique_key' => mb_substr($uniqueKey, 0, 160), 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'state' => 'pending', 'attempts' => 0, 'max_attempts' => 8, 'available_at' => now('UTC')->addSeconds($delaySeconds),
                'created_at' => now('UTC'), 'updated_at' => now('UTC'),
            ]);
        } catch (QueryException $e) {
            if (! self::isDuplicate($e)) {
                throw $e;
            }
        }
    }

    public static function isDuplicate(QueryException $e): bool
    {
        $info = $e->errorInfo ?? [];

        return ($info[0] ?? null) === '23000' || ($info[1] ?? null) === 1062 || ($info[1] ?? null) === 19 || ($info[1] ?? null) === 2067
            || str_contains(strtolower($e->getMessage()), 'unique constraint');
    }

    /** Run periodic sweeps and due jobs. Returns a short summary. */
    public function tick(int $limit = 20): array
    {
        $summary = ['jobs' => 0, 'failed' => 0, 'mpesa' => 0, 'mpesa_callbacks' => 0, 'kiosk_expired' => 0, 'print_unknown' => 0];
        $summary['print_unknown'] = app(PrintService::class)->expireLeases();
        $summary['kiosk_expired'] = app(KioskService::class)->expireDue();
        foreach (DB::table('mpesa_callback_inbox')->where('state', 'pending')->orderBy('created_at')->limit(10)->pluck('id') as $id) {
            try {
                if (app(\App\Domain\Payments\PaymentService::class)->processCallbackInbox($id)) {
                    $summary['mpesa_callbacks']++;
                }
            } catch (Throwable $e) {
                report($e);
                DB::table('mpesa_callback_inbox')->where('id', $id)->update([
                    'last_error' => mb_substr('Callback processing failed', 0, 200),
                    'updated_at' => now('UTC'),
                ]);
            }
        }
        foreach (DB::table('payment_attempts')->where('state', 'pending')->where('next_query_at', '<=', now('UTC'))->whereNotNull('checkout_request_id')->limit(10)->pluck('id') as $id) {
            try {
                app(\App\Domain\Payments\PaymentService::class)->refresh($id);
                $summary['mpesa']++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        DB::table('cart_lines')->where('expires_at', '<', now('UTC'))->delete();
        $owner = gethostname().':'.getmypid();
        for ($i = 0; $i < $limit; $i++) {
            $job = DB::transaction(function () use ($owner) {
                $job = DB::table('background_jobs')->where(fn ($q) => $q->where('state', 'pending')->orWhere(fn ($r) => $r->where('state', 'leased')->where('lease_expires_at', '<', now('UTC'))))
                    ->where('available_at', '<=', now('UTC'))->orderBy('available_at')->lockForUpdate()->first();
                if ($job !== null) {
                    DB::table('background_jobs')->where('id', $job->id)->update(['state' => 'leased', 'lease_owner' => $owner, 'lease_expires_at' => now('UTC')->addMinutes(2), 'attempts' => (int) $job->attempts + 1, 'updated_at' => now('UTC')]);
                }

                return $job;
            });
            if ($job === null) {
                break;
            }
            $summary['jobs']++;
            try {
                $done = $this->handle($job->type, json_decode($job->payload, true));
                if ($done) {
                    DB::table('background_jobs')->where('id', $job->id)->update(['state' => 'done', 'completed_at' => now('UTC'), 'lease_owner' => null, 'updated_at' => now('UTC')]);
                } else {
                    $this->retryLater($job, 'Handler reported not finished');
                }
            } catch (Throwable $e) {
                report($e);
                $summary['failed']++;
                $this->retryLater($job, $e->getMessage());
            }
        }
        DB::table('system_heartbeats')->upsert([['name' => 'job_runner', 'last_seen_at' => now('UTC'), 'detail' => json_encode($summary)]], ['name'], ['last_seen_at', 'detail']);

        return $summary;
    }

    /** Opportunistic tick from busy polling endpoints when cron is late. */
    public function tickIfStale(int $seconds = 10): void
    {
        try {
            if (Cache::add('hotel.jobs.tick', 1, $seconds)) {
                $this->tick(5);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function retryLater(object $job, string $error): void
    {
        $attempts = (int) $job->attempts + 1;
        DB::table('background_jobs')->where('id', $job->id)->update([
            'state' => $attempts >= (int) $job->max_attempts ? 'failed' : 'pending', 'last_error' => mb_substr($error, 0, 300), 'lease_owner' => null,
            'available_at' => now('UTC')->addSeconds(min(3600, 15 * (2 ** min(8, $attempts)))), 'updated_at' => now('UTC'),
        ]);
    }

    private function handle(string $type, array $payload): bool
    {
        return match ($type) {
            'fiscal_submit' => app(FiscalService::class)->submit((string) $payload['document_id']),
            default => true,
        };
    }

    /** @return list<object> */
    public function recent(): array
    {
        return DB::table('background_jobs')->orderByDesc('created_at')->limit(50)->get()->all();
    }
}
