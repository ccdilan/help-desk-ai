<?php

use App\Http\Controllers\Api\TicketChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('projects/{project}/tickets', [TicketChatController::class, 'index'])->name('api.tickets.index');
    Route::post('projects/{project}/tickets', [TicketChatController::class, 'store'])->name('api.tickets.store');
    Route::get('tickets/{ticket}', [TicketChatController::class, 'show'])->name('api.tickets.show');
    Route::post('tickets/{ticket}/messages', [TicketChatController::class, 'addMessage'])->name('api.tickets.messages.store');
});
