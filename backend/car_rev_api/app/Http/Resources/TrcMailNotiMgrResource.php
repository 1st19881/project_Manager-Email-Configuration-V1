<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrcMailNotiMgrResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'noti_id' => $this->noti_id,
            'employee_code' => $this->employee_code,
            'plant' => $this->plant,
            'position_name' => $this->position_name,
            'department_name' => $this->department_name,
            'name_th' => $this->name_th,
            'name_en' => $this->name_en,
            'email' => $this->email,
            'active_flag' => $this->active_flag,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_by' => $this->updated_by,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
