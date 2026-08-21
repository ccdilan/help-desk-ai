<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TicketMessage $resource
 */
class TicketMessageResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'role' => $this->resource->role,
            'content' => $this->resource->content,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
