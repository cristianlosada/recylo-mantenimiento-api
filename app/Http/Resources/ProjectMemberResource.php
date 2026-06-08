<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectMemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'user_id'    => $this->user_id,
            'user'       => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn() => [
                    'id'        => $this->user->id,
                    'full_name' => $this->user->full_name,
                    'name'      => $this->user->full_name,
                    'email'     => $this->user->email,
                ]
            ),
            'role' => $this->when(
                $this->relationLoaded('role') && $this->role,
                fn() => [
                    'id'   => $this->role->id,
                    'code' => $this->role->code,
                    'name' => $this->role->name,
                ]
            ),
            'hourly_rate' => $this->hourly_rate,
            'assigned_at' => $this->assigned_at?->toDateString(),
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at,
        ];
    }
}
