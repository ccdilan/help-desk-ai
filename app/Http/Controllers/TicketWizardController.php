<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketWizardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $projects = [];

        foreach (Project::query()->with('installations')->orderBy('name')->get() as $project) {
            $installations = [];

            foreach ($project->installations->sortBy('repository_full_name') as $installation) {
                $installations[] = [
                    'id' => $installation->id,
                    'repositoryFullName' => $installation->repository_full_name,
                ];
            }

            $projects[] = [
                'id' => $project->id,
                'name' => $project->name,
                'installations' => $installations,
            ];
        }

        return Inertia::render('Tickets/Create', [
            'projects' => $projects,
        ]);
    }
}
