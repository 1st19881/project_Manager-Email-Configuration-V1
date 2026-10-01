<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrcMailNotiLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'log_id' => $this->log_id,
            'event_code' => $this->event_code,
            'reference_id' => $this->reference_id,
            'mgr_select_id' => $this->mgr_select_id,
            'email_to' => $this->email_to,
            'email_cc' => $this->email_cc,
            'email_subject' => $this->email_subject,
            'status' => $this->status,
            'attempt_count' => $this->attempt_count,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
