<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationPortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $projects = Project::query()
            ->with('installations')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                'installations' => $project->installations
                    ->sortBy('repository_full_name')
                    ->values()
                    ->map(fn ($installation): array => [
                        'id' => $installation->id,
                        'repositoryFullName' => $installation->repository_full_name,
                        'status' => $installation->status,
                        'lastSyncedAt' => $installation->last_synced_at?->toIso8601String(),
                    ])
                    ->all(),
            ])
            ->all();

        return Inertia::render('Integrations', [
            'projects' => $projects,
        ]);
    }
}
