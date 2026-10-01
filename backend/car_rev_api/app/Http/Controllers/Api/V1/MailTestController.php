<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendTestMailRequest;
use App\Services\Notification\NotificationException;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;

class MailTestController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Send one notification mail to an address of the administrator's choosing
     * so the SMTP settings and the template can be checked from the browser.
     */
    public function store(SendTestMailRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $log = $this->notifications->sendTestMail(
                $validated['email'],
                $validated['event'],
                $validated['reference_id'] ?? null,
            );
        } catch (NotificationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'log_id' => $exception->log?->log_id,
                'status' => $exception->log?->status,
            ], $exception->statusCode);
        }

        return response()->json([
            'message' => "ส่งอีเมลทดสอบไปที่ {$validated['email']} เรียบร้อยแล้ว",
            'log_id' => $log->log_id,
            'status' => $log->status,
            'email_subject' => $log->email_subject,
        ]);
    }
}
