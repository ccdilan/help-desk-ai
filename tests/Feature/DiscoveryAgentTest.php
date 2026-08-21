<?php

use App\Contracts\ChatClient;
use App\Models\CodeChunk;
use App\Models\GithubInstallation;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Repositories\Contracts\CodeChunkRepository as CodeChunkRepositoryContract;
use Illuminate\Support\Collection;

/**
 * @property list<list<array{role: string, content: string}>> $calls
 */
class RecordingChatSpy implements ChatClient
{
    public array $calls = [];

    /**
     * Record the conversation and reply with a clarifying question.
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(array $messages): string
    {
        $this->calls[] = $messages;

        return 'Could you clarify who the primary users are?';
    }
}

class FakeChunkRepository implements CodeChunkRepositoryContract
{
    public array $searches = [];

    public function __construct(private readonly array $chunks) {}

    public function searchSimilar(int $projectId, array $embedding, int $limit = 8, ?int $installationId = null): Collection
    {
        $this->searches[] = ['project_id' => $projectId, 'installation_id' => $installationId];

        return collect($this->chunks)
            ->map(fn (CodeChunk $chunk): array => ['chunk' => $chunk, 'distance' => 0.1])
            ->slice(0, $limit)
            ->values();
    }
}

test('the discovery agent references relevant files without exposing raw code', function () {
    $spy = new RecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $project = Project::factory()->create();
    $installation = GithubInstallation::factory()->for($project)->create();
    $chunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Services/SearchService.php',
        'code_content' => 'CLASSIFIED_SOURCE_CODE_BODY',
    ]);
    $this->app->instance(CodeChunkRepositoryContract::class, new FakeChunkRepository([$chunk]));

    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($project)->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'How does search filtering work today?'])
        ->assertOk()
        ->assertJsonPath('messages.1.role', 'assistant')
        ->assertJsonPath('messages.1.content', 'Could you clarify who the primary users are?');

    expect($spy->calls)->toHaveCount(1);

    $systemPrompt = $spy->calls[0][0]['content'];

    expect($spy->calls[0][0]['role'])->toBe('system')
        ->and($systemPrompt)->toContain('app/Services/SearchService.php')
        ->not->toContain('CLASSIFIED_SOURCE_CODE_BODY');
});

test('a ticket scoped to a repository only searches that repository\'s chunks', function () {
    $spy = new RecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $chunks = new FakeChunkRepository([]);
    $this->app->instance(CodeChunkRepositoryContract::class, $chunks);

    $user = User::factory()->create();
    $project = Project::factory()->create();
    $installation = GithubInstallation::factory()->for($project)->create();
    $otherInstallation = GithubInstallation::factory()->for($project)->create();
    $ticket = Ticket::factory()->for($project)->for($installation, 'installation')->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'How does search filtering work today?'])
        ->assertOk();

    expect($chunks->searches)->toHaveCount(1)
        ->and($chunks->searches[0]['installation_id'])->toBe($installation->id)
        ->and($chunks->searches[0]['installation_id'])->not->toBe($otherInstallation->id);
});

test('an unscoped ticket searches all repositories of the project', function () {
    $spy = new RecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $chunks = new FakeChunkRepository([]);
    $this->app->instance(CodeChunkRepositoryContract::class, $chunks);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->hasMessages(['role' => 'user', 'content' => 'I need bulk export.'])->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'Also CSV and PDF formats.'])
        ->assertOk();

    expect($chunks->searches)->toHaveCount(1)
        ->and($chunks->searches[0]['installation_id'])->toBeNull();
});

test('rag retrieval is logged with the repository scope and selected context', function () {
    $logger = fakeLogChannel('rag');
    $this->app->instance(ChatClient::class, new RecordingChatSpy);

    $project = Project::factory()->create();
    $installation = GithubInstallation::factory()->for($project)->create();
    $chunk = CodeChunk::factory()->for($installation, 'installation')->create([
        'file_path' => 'app/Services/SearchService.php',
    ]);
    $this->app->instance(CodeChunkRepositoryContract::class, new FakeChunkRepository([$chunk]));

    $user = User::factory()->create();
    $ticket = Ticket::factory()->for($project)->for($installation, 'installation')->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'How does search filtering work today?'])
        ->assertOk();

    expect($logger->entries())->toHaveCount(1);

    $entry = $logger->entries()[0];

    expect($entry['message'])->toBe('Discovery context selected')
        ->and($entry['context']['ticket_id'])->toBe($ticket->id)
        ->and($entry['context']['installation_id'])->toBe($installation->id)
        ->and($entry['context']['message'])->toBe('How does search filtering work today?')
        ->and($entry['context']['context_paths'])->toBe(['app/Services/SearchService.php']);
});

test('the discovery agent passes the full conversation history to the model', function () {
    $spy = new RecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);
    $this->app->instance(CodeChunkRepositoryContract::class, new FakeChunkRepository([]));

    $user = User::factory()->create();
    $ticket = Ticket::factory()->hasMessages(['role' => 'user', 'content' => 'I need bulk export.'])->create();

    $this->actingAs($user)
        ->postJson("/api/tickets/{$ticket->id}/messages", ['content' => 'Also CSV and PDF formats.'])
        ->assertOk();

    $conversation = $spy->calls[0];

    expect(count($conversation))->toBe(3)
        ->and($conversation[1])->toBe(['role' => 'user', 'content' => 'I need bulk export.'])
        ->and($conversation[2])->toBe(['role' => 'user', 'content' => 'Also CSV and PDF formats.']);
});

test('the discovery agent degrades gracefully when vector search is unavailable', function () {
    $spy = new RecordingChatSpy;
    $this->app->instance(ChatClient::class, $spy);

    $user = User::factory()->create();
    $project = Project::factory()->create();

    $response = $this->actingAs($user)
        ->postJson("/api/projects/{$project->id}/tickets", [
            'title' => 'Bulk export',
            'initial_prompt' => 'We need to export tickets.',
        ]);

    $response->assertCreated()
        ->assertJsonPath('messages.0.role', 'user')
        ->assertJsonPath('messages.1.role', 'assistant');

    expect($spy->calls[0][0]['content'])->toContain('No indexed repository context is available yet');
});
