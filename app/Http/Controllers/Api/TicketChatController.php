<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Project;
use App\Models\Ticket;
use App\Services\DiscoveryAgentService;
use App\Services\PrdGeneratorService;
use App\Services\TicketChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketChatController extends Controller
{
    public function __construct(
        private TicketChatService $ticketChat,
        private DiscoveryAgentService $discoveryAgent,
        private PrdGeneratorService $prdGenerator,
    ) {}

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

        $ticket = $this->discoveryAgent->reply(
            $this->ticketChat->startTicket(
                $project,
                $validated['title'],
                $validated['initial_prompt'],
            ),
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
     * Append a user message and return the assistant's clarifying reply.
     */
    public function addMessage(Request $request, Ticket $ticket): TicketResource
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        return new TicketResource(
            $this->discoveryAgent->respond($ticket, $validated['content']),
        );
    }

    /**
     * Summarize the discovery conversation into a reviewable SRS/PRD.
     */
    public function generatePrd(Ticket $ticket): TicketResource
    {
        return new TicketResource(
            $this->prdGenerator->generate($ticket),
        );
    }

    /**
     * Approve the generated SRS and close the discovery workflow.
     */
    public function signOff(Ticket $ticket): TicketResource
    {
        $ticket->forceFill(['status' => TicketStatus::SignedOff])->save();

        return new TicketResource($ticket);
    }
}
