<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncRepositoryJob;
use App\Models\GithubInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallationSyncController extends Controller
{
    /**
     * Queue a fresh sync of the installation's repository.
     */
    public function store(Request $request, GithubInstallation $installation): JsonResponse
    {
        SyncRepositoryJob::dispatch($installation);

        return response()->json([
            'id' => $installation->id,
            'status' => 'syncing',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
