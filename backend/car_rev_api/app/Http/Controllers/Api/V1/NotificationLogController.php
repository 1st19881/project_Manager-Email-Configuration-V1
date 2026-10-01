<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationLogFilterRequest;
use App\Http\Resources\TrcMailNotiLogResource;
use App\Models\TrcMailNotiLog;
use App\Services\Notification\NotificationLogStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationLogController extends Controller
{
    private const STATUSES = [
        TrcMailNotiLog::STATUS_PENDING,
        TrcMailNotiLog::STATUS_PROCESSING,
        TrcMailNotiLog::STATUS_SENT,
        TrcMailNotiLog::STATUS_FAILED,
    ];

    public function __construct(private readonly NotificationLogStore $logs) {}

    /**
     * How many notification mails were logged, broken down by status, event
     * and day, so administrators can see delivery health at a glance.
     */
    public function summary(NotificationLogFilterRequest $request): JsonResponse
    {
        [$from, $until] = $request->utcRange();
        $rows = collect($this->logs->countByDay($from, $until));

        /** @var array<string, string> $headlines */
        $headlines = config('notification.body.headlines', []);
        $emptyStatusCounts = array_fill_keys(self::STATUSES, 0);

        $countStatuses = fn ($rows): array => [
            ...$emptyStatusCounts,
            ...$rows->groupBy('status')->map->sum('total')->all(),
        ];

        $byEvent = collect(array_keys((array) config('notification.events', [])))
            ->merge($rows->pluck('event_code'))
            ->unique()
            ->map(function (string $eventCode) use ($rows, $headlines, $countStatuses): array {
                $eventRows = $rows->where('event_code', $eventCode);

                return [
                    'event_code' => $eventCode,
                    'label' => $headlines[$eventCode] ?? $eventCode,
                    'total' => $eventRows->sum('total'),
                    'by_status' => $countStatuses($eventRows),
                ];
            })
            ->values();

        $daily = $rows->groupBy('day')
            ->map(fn ($dayRows, string $day): array => [
                'date' => $day,
                'total' => $dayRows->sum('total'),
                'by_status' => $countStatuses($dayRows),
            ])
            ->sortKeys()
            ->values();

        return response()->json([
            'data' => [
                'date_from' => $request->validated('date_from'),
                'date_to' => $request->validated('date_to'),
                'timezone' => config('notification.report_timezone'),
                'total' => $rows->sum('total'),
                'by_status' => $countStatuses($rows),
                'by_event' => $byEvent,
                'daily' => $daily,
            ],
        ]);
    }

    public function index(NotificationLogFilterRequest $request): AnonymousResourceCollection
    {
        [$from, $until] = $request->utcRange();

        $logs = $this->logs->paginate([
            'from' => $from,
            'until' => $until,
            'status' => $request->validated('status'),
            'event_code' => $request->validated('event_code'),
        ], (int) ($request->validated('per_page') ?? 15));

        return TrcMailNotiLogResource::collection($logs->withQueryString());
    }
}
