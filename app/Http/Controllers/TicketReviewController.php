<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketReviewController extends Controller
{
    public function __invoke(Request $request, string $currentTeam, Ticket $ticket): Response
    {
        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'initial_prompt' => $ticket->initial_prompt,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'generated_prd' => $ticket->generated_prd,
            ],
        ]);
    }
}
