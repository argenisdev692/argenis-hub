<?php

declare(strict_types=1);

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Two-factor authentication is opt-in for every role: users enrol from
// Settings → Security when they choose to, and no role is held at the door.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Design playgrounds. Local only — these are development references for the
// shared component kits, not part of the application surface.
if (app()->environment('local')) {
    Route::inertia('design/form-kit', 'design/FormKit')->name('design.form-kit');
    Route::inertia('design/data-table', 'design/DataTable')->name('design.data-table');
}

require __DIR__.'/settings.php';
