<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Ticket $resource
 */
class TicketResource extends JsonResource
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
            'project_id' => $this->resource->project_id,
            'title' => $this->resource->title,
            'initial_prompt' => $this->resource->initial_prompt,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'generated_prd' => $this->resource->generated_prd,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
