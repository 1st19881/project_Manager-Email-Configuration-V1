<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MailTemplateController extends Controller
{
    /**
     * Expose the wording of every notification mail so administrators can read
     * on screen exactly what the system sends, without opening a mailbox.
     */
    public function index(): JsonResponse
    {
        /** @var array<string, string> $events */
        $events = config('notification.events', []);

        /** @var array<string, mixed> $body */
        $body = config('notification.body', []);

        /** @var array<string, string> $headlines */
        $headlines = $body['headlines'] ?? [];

        /** @var array<string, string> $fields */
        $fields = $body['fields'] ?? [];

        $templates = collect($events)
            ->map(fn (string $subject, string $eventCode): array => [
                'event_code' => $eventCode,
                'subject' => $subject,
                'headline' => $headlines[$eventCode] ?? $eventCode,
            ])
            ->values();

        return response()->json([
            'data' => [
                'templates' => $templates,
                'layout' => [
                    'greeting' => $body['greeting'] ?? '',
                    'greeting_fallback' => $body['greeting_fallback'] ?? '',
                    'fields' => collect($fields)
                        ->map(fn (string $label, string $key): array => [
                            'key' => $key,
                            'label' => $label,
                        ])
                        ->values(),
                    'empty_value' => $body['empty_value'] ?? '-',
                    'closing' => $body['closing'] ?? '',
                    'cta' => $body['cta'] ?? '',
                    'cta_url' => config('notification.approval_url'),
                    'footer' => $body['footer'] ?? '',
                ],
                'placeholders' => [
                    ':reference' => 'เลขที่เอกสารการจอง (เช่น DOC-00000071)',
                    ':name' => 'ชื่อผู้อนุมัติที่รับเมล',
                ],
            ],
        ]);
    }
}
