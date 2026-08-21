<?php

namespace App\Http\Controllers\GitHub;

use App\Http\Controllers\Controller;
use App\Jobs\HandleGitHubWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    /**
     * Receive, verify and queue GitHub webhook events.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $secret = (string) config('services.github.webhook_secret');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, $signature)) {
            abort(401);
        }

        HandleGitHubWebhook::dispatch(
            (string) $request->header('X-GitHub-Event', 'ping'),
            (array) $request->json()->all(),
        );

        return response()->json(['status' => 'queued']);
    }
}
