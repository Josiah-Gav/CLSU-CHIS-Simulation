<?php

use App\Http\Controllers\EncounterController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PatientController;
use App\Http\Middleware\EnsureChisToken;
use App\Http\Middleware\RequireJsonObjectBody;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 in bootstrap/app.php. Contract: docs/API_CONTRACT.md.
// Throttling runs before authentication so token guessing is rate limited too.
Route::middleware('throttle:chis-api')->group(function () {
    Route::get('health', HealthController::class);

    Route::middleware(EnsureChisToken::class)->group(function () {
        Route::get('patients/{clsuId}', [PatientController::class, 'show']);

        Route::get('encounters', [EncounterController::class, 'index']);
        Route::post('encounters', [EncounterController::class, 'store'])->middleware(RequireJsonObjectBody::class);
        Route::get('encounters/{chisEncounterId}', [EncounterController::class, 'show']);
    });
});
