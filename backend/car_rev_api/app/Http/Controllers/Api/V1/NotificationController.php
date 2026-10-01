<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Services\Notification\NotificationException;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Accept a post-commit event from a booking system and queue its email.
     */
    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $log = $this->notifications->notifyBookingEvent(
                $validated['event'],
                $validated['reference_id'],
                $validated['mgr_select_id'] ?? null,
            );
        } catch (NotificationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'log_id' => $exception->log?->log_id,
                'status' => $exception->log?->status,
            ], $exception->statusCode);
        }

        return response()->json([
            'message' => 'Notification queued.',
            'log_id' => $log->log_id,
            'status' => $log->status,
        ], Response::HTTP_ACCEPTED);
    }
}
