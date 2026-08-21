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

        foreach (Project::query()->orderBy('name')->get(['id', 'name']) as $project) {
            $projects[] = [
                'id' => $project->id,
                'name' => $project->name,
            ];
        }

        return Inertia::render('Tickets/Create', [
            'projects' => $projects,
        ]);
    }
}
