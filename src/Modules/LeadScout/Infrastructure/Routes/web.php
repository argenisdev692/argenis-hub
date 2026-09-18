<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LeadScout\Infrastructure\Http\Controllers\AiSettingsController;
use Modules\LeadScout\Infrastructure\Http\Controllers\BudgetController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ChannelController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ContactController;
use Modules\LeadScout\Infrastructure\Http\Controllers\DecisionRuleController;
use Modules\LeadScout\Infrastructure\Http\Controllers\DraftController;
use Modules\LeadScout\Infrastructure\Http\Controllers\LeadController;
use Modules\LeadScout\Infrastructure\Http\Controllers\LeadExportController;
use Modules\LeadScout\Infrastructure\Http\Controllers\LeadPageController;
use Modules\LeadScout\Infrastructure\Http\Controllers\LeadScoutStatusController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ManualLeadController;
use Modules\LeadScout\Infrastructure\Http\Controllers\MetricsController;
use Modules\LeadScout\Infrastructure\Http\Controllers\OpportunityController;
use Modules\LeadScout\Infrastructure\Http\Controllers\OutreachController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ProfileController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ReplyController;
use Modules\LeadScout\Infrastructure\Http\Controllers\ScoreController;
use Modules\LeadScout\Infrastructure\Http\Controllers\SourceController;
use Modules\LeadScout\Infrastructure\Http\Controllers\SuppressionController;

// Session-authenticated JSON surface (plan §5). No api.php in the MVP.
// Controllers land per phase; routes are added here as they do.
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('lead-scout')->name('lead-scout.')->group(function (): void {
    Route::get('/', [LeadPageController::class, 'index'])->middleware('permission:VIEW_ANY_LEAD_SCOUT')->name('index');
});

Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('data/admin/lead-scout')->name('lead-scout.')->group(function (): void {
    Route::get('/status', LeadScoutStatusController::class)
        ->middleware('permission:VIEW_ANY_LEAD_SCOUT')
        ->name('status');

    Route::get('/leads', [LeadController::class, 'index'])->middleware('permission:VIEW_ANY_LEAD_SCOUT')->name('leads.index');

    Route::get('/profile', [ProfileController::class, 'show'])->middleware('permission:VIEW_LEAD_SCOUT')->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('profile.update');
    Route::get('/profile/cvs', [ProfileController::class, 'cvs'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('profile.cvs');
    Route::post('/profile/import-cv', [ProfileController::class, 'importCv'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('profile.import-cv');

    Route::get('/sources', [SourceController::class, 'index'])->middleware('permission:VIEW_LEAD_SCOUT')->name('sources.index');
    Route::patch('/sources/{uuid}', [SourceController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('sources.update');
    Route::post('/sources/{uuid}/run', [SourceController::class, 'run'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('sources.run');

    Route::post('/leads', [ManualLeadController::class, 'store'])->middleware('permission:CREATE_LEAD_SCOUT')->name('leads.store');
    Route::post('/suppressions', [SuppressionController::class, 'store'])->middleware('permission:DELETE_LEAD_SCOUT')->name('suppressions.store');

    Route::post('/leads/{uuid}/rescore', [ScoreController::class, 'rescore'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('leads.rescore');
    Route::post('/leads/{uuid}/drafts', [DraftController::class, 'store'])->middleware(['permission:UPDATE_LEAD_SCOUT', 'throttle:lead-scout-llm'])->whereUuid('uuid')->name('leads.drafts');

    Route::get('/leads/export', LeadExportController::class)->middleware(['permission:EXPORT_LEAD_SCOUT', 'throttle:lead-scout-export'])->name('leads.export');
    Route::get('/leads/{uuid}', [LeadController::class, 'show'])->middleware('permission:VIEW_LEAD_SCOUT')->whereUuid('uuid')->name('leads.show');

    Route::get('/budgets', [BudgetController::class, 'show'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('budgets.show');
    Route::put('/budgets', [BudgetController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('budgets.update');

    Route::get('/ai-settings', [AiSettingsController::class, 'show'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('ai-settings.show');
    Route::put('/ai-settings', [AiSettingsController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('ai-settings.update');

    Route::post('/leads/{uuid}/contacts', [ContactController::class, 'store'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('contacts.store');
    Route::patch('/contacts/{uuid}', [ContactController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('contacts.update');
    Route::post('/contacts/{uuid}/objection', [ContactController::class, 'objection'])->middleware('permission:DELETE_LEAD_SCOUT')->whereUuid('uuid')->name('contacts.objection');

    Route::patch('/channels/{uuid}', [ChannelController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('channels.update');

    Route::post('/outreaches/{uuid}/reply', [ReplyController::class, 'store'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('outreaches.reply');
    Route::patch('/outreaches/{uuid}', [OutreachController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('outreaches.update');

    Route::post('/outreaches/{uuid}/opportunities', [OpportunityController::class, 'store'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('opportunities.store');
    Route::patch('/opportunities/{uuid}', [OpportunityController::class, 'update'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('opportunities.update');

    Route::get('/metrics', [MetricsController::class, 'index'])->middleware('permission:VIEW_ANY_LEAD_SCOUT')->name('metrics.index');

    Route::post('/decision-rules', [DecisionRuleController::class, 'store'])->middleware('permission:UPDATE_LEAD_SCOUT')->name('decision-rules.store');
    Route::post('/decision-rules/{uuid}/lock', [DecisionRuleController::class, 'lock'])->middleware('permission:UPDATE_LEAD_SCOUT')->whereUuid('uuid')->name('decision-rules.lock');
});
