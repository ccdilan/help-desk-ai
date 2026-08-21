<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GitHub\GitHubConnectionController;
use App\Http\Controllers\GitHub\GitHubWebhookController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::post('webhooks/github', GitHubWebhookController::class)->name('github.webhook');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('github/connect', [GitHubConnectionController::class, 'connect'])->name('github.connect');
    Route::get('github/callback', [GitHubConnectionController::class, 'callback'])->name('github.callback');
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
