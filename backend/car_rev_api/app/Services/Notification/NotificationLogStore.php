<?php

namespace App\Services\Notification;

use App\Models\TrcMailNotiLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Owns every read and write against TRC_MAIL_NOTI_LOG so that the notification
 * service and its queue job hold no persistence details of their own.
 */
class NotificationLogStore
{
    /**
     * @param  array{event_code: string, reference_id: string, mgr_select_id: ?string, email_to: ?string, email_cc: ?string, email_subject: string, status: string, error_message?: ?string}  $attributes
     */
    public function create(array $attributes): TrcMailNotiLog
    {
        return TrcMailNotiLog::query()->create([
            'log_id' => (string) Str::uuid(),
            'attempt_count' => 0,
            'error_message' => null,
            ...$attributes,
        ]);
    }

    public function find(string $logId): ?TrcMailNotiLog
    {
        return TrcMailNotiLog::query()->find($logId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(TrcMailNotiLog $log, array $attributes): void
    {
        $log->forceFill($attributes)->save();
    }

    /**
     * Number of logs per report-clock day, event and status, created inside the
     * half-open UTC range [$from, $until).
     *
     * @return list<array{day: string, event_code: string, status: string, total: int}>
     */
    public function countByDay(?CarbonInterface $from, ?CarbonInterface $until): array
    {
        // Inlined rather than bound: Oracle only accepts the GROUP BY when it is
        // textually identical to the select expression, and two binds are not.
        $offsetMinutes = CarbonImmutable::now((string) config('notification.report_timezone'))->utcOffset();
        $day = "TO_CHAR(created_at + {$offsetMinutes} / 1440, 'YYYY-MM-DD')";

        return TrcMailNotiLog::query()
            ->toBase()
            ->selectRaw("{$day} as day, event_code, status, COUNT(*) as total")
            ->when($from, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($until, fn ($query, $until) => $query->where('created_at', '<', $until))
            ->groupByRaw("{$day}, event_code, status")
            ->get()
            ->map(fn (object $row): array => [
                'day' => (string) $row->day,
                'event_code' => (string) $row->event_code,
                'status' => (string) $row->status,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * Newest logs first.
     *
     * @param  array{from?: ?CarbonInterface, until?: ?CarbonInterface, status?: ?string, event_code?: ?string}  $filters
     * @return LengthAwarePaginator<int, TrcMailNotiLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return TrcMailNotiLog::query()
            ->when($filters['from'] ?? null, fn (Builder $query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['until'] ?? null, fn (Builder $query, $until) => $query->where('created_at', '<', $until))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['event_code'] ?? null, fn (Builder $query, $eventCode) => $query->where('event_code', $eventCode))
            ->orderByDesc('created_at')
            ->orderBy('log_id')
            ->paginate($perPage);
    }
}
