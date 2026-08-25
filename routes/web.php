<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Two-factor authentication is opt-in for every role: users enrol from
// Settings → Security when they choose to, and no role is held at the door.
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Form-kit playground. Local only — it is a development reference for the
// shared field components, not part of the application surface.
if (app()->environment('local')) {
    Route::inertia('design/form-kit', 'design/FormKit')->name('design.form-kit');
}

require __DIR__.'/settings.php';
