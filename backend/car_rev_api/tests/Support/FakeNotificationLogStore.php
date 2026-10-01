<?php

namespace Tests\Support;

use App\Models\TrcMailNotiLog;
use App\Services\Notification\NotificationLogStore;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps notification logs in memory so tests never reach the Oracle database.
 */
class FakeNotificationLogStore extends NotificationLogStore
{
    /**
     * @var array<string, TrcMailNotiLog>
     */
    public array $logs = [];

    public function create(array $attributes): TrcMailNotiLog
    {
        $log = new TrcMailNotiLog([
            'log_id' => (string) Str::uuid(),
            'attempt_count' => 0,
            'error_message' => null,
            ...$attributes,
        ]);
        $log->created_at = $attributes['created_at'] ?? now();

        $this->logs[$log->log_id] = $log;

        return $log;
    }

    public function find(string $logId): ?TrcMailNotiLog
    {
        return $this->logs[$logId] ?? null;
    }

    public function update(TrcMailNotiLog $log, array $attributes): void
    {
        $log->forceFill($attributes);

        $this->logs[$log->log_id] = $log;
    }

    public function countByDay(?CarbonInterface $from, ?CarbonInterface $until): array
    {
        $timezone = (string) config('notification.report_timezone');

        return $this->filtered(['from' => $from, 'until' => $until])
            ->groupBy(fn (TrcMailNotiLog $log): string => implode('|', [
                $log->created_at->copy()->setTimezone($timezone)->format('Y-m-d'),
                $log->event_code,
                $log->status,
            ]))
            ->map(function (Collection $logs, string $key): array {
                [$day, $eventCode, $status] = explode('|', $key);

                return ['day' => $day, 'event_code' => $eventCode, 'status' => $status, 'total' => $logs->count()];
            })
            ->values()
            ->all();
    }

    public function paginate(array $filters, int $perPage): LengthAwarePaginatorContract
    {
        $logs = $this->filtered($filters)->sortByDesc(fn (TrcMailNotiLog $log) => $log->created_at)->values();
        $page = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $logs->forPage($page, $perPage)->values(),
            $logs->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array{from?: ?CarbonInterface, until?: ?CarbonInterface, status?: ?string, event_code?: ?string}  $filters
     * @return Collection<int, TrcMailNotiLog>
     */
    private function filtered(array $filters): Collection
    {
        return collect($this->logs)
            ->filter(fn (TrcMailNotiLog $log): bool => ! isset($filters['from']) || $log->created_at->gte($filters['from']))
            ->filter(fn (TrcMailNotiLog $log): bool => ! isset($filters['until']) || $log->created_at->lt($filters['until']))
            ->filter(fn (TrcMailNotiLog $log): bool => ! isset($filters['status']) || $log->status === $filters['status'])
            ->filter(fn (TrcMailNotiLog $log): bool => ! isset($filters['event_code']) || $log->event_code === $filters['event_code'])
            ->values();
    }
}
