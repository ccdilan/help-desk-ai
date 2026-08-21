<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Project;
use App\Models\Ticket;
use App\Services\TicketChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketChatController extends Controller
{
    public function __construct(private TicketChatService $ticketChat) {}

    /**
     * List the discovery tickets for a project.
     */
    public function index(Project $project): AnonymousResourceCollection
    {
        return TicketResource::collection(
            $project->tickets()->latest()->get(),
        );
    }

    /**
     * Create a ticket from an initial prompt and open the discovery conversation.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'initial_prompt' => ['required', 'string'],
        ]);

        $ticket = $this->ticketChat->startTicket(
            $project,
            $validated['title'],
            $validated['initial_prompt'],
        );

        return (new TicketResource($ticket))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Show a ticket with its full message history.
     */
    public function show(Ticket $ticket): TicketResource
    {
        return new TicketResource($ticket->load('messages'));
    }

    /**
     * Append a user message to the ticket's conversation.
     */
    public function addMessage(Request $request, Ticket $ticket): TicketResource
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $this->ticketChat->appendMessage($ticket, 'user', $validated['content']);

        return new TicketResource($ticket->load('messages'));
    }
}
