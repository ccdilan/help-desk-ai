<?php

namespace App\Http\Controllers\GitHub;

use App\Contracts\GitHubClient;
use App\Http\Controllers\Controller;
use App\Models\GithubInstallation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

class GitHubConnectionController extends Controller
{
    /**
     * Redirect the user to the GitHub App installation page.
     */
    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $state = Str::random(32);

        Session::put('github.oauth.state', $state);
        Session::put('github.oauth.project_id', (int) $validated['project']);

        return redirect()->away(sprintf(
            'https://github.com/apps/%s/installations/new?state=%s',
            (string) config('services.github.app_slug'),
            $state,
        ));
    }

    /**
     * Handle the OAuth callback from the GitHub App installation.
     */
    public function callback(Request $request, GitHubClient $github): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'installation_id' => ['required', 'integer'],
            'state' => ['required', 'string'],
        ]);

        abort_unless(
            hash_equals((string) Session::pull('github.oauth.state'), $validated['state']),
            419,
        );

        $projectId = (int) Session::pull('github.oauth.project_id');

        try {
            $token = $github->exchangeUserCode($validated['code']);
            $repositories = $github->installationRepositories(
                (int) $validated['installation_id'],
                $token,
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('dashboard')
                ->withErrors(['github' => 'Unable to complete the GitHub connection. Please try again.']);
        }

        foreach ($repositories as $repository) {
            GithubInstallation::query()->updateOrCreate(
                [
                    'installation_id' => (string) $validated['installation_id'],
                    'repository_id' => $repository['id'],
                ],
                [
                    'project_id' => $projectId,
                    'repository_full_name' => $repository['full_name'],
                    'status' => 'pending',
                ],
            );
        }

        return redirect()->route('dashboard');
    }
}
