<?php

use App\Http\Controllers\Viewer\EncounterViewerController;
use App\Http\Controllers\Viewer\PatientViewerController;
use App\Http\Middleware\EnsureViewerEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return config('chis.viewer_enabled') ? redirect()->route('viewer.encounters.index') : view('welcome');
});

// Presentation viewer (EnsureViewerEnabled: off by default; password outside local).
Route::prefix('viewer')->name('viewer.')->middleware(EnsureViewerEnabled::class)->group(function () {
    Route::get('/', fn () => redirect()->route('viewer.encounters.index'));

    Route::get('encounters', [EncounterViewerController::class, 'index'])->name('encounters.index');
    Route::get('encounters/{chisEncounterId}', [EncounterViewerController::class, 'show'])->name('encounters.show');

    Route::get('patients', [PatientViewerController::class, 'index'])->name('patients.index');
    Route::get('patients/create', [PatientViewerController::class, 'create'])->name('patients.create');
    Route::post('patients', [PatientViewerController::class, 'store'])->name('patients.store');
    Route::get('patients/{clsuId}/edit', [PatientViewerController::class, 'edit'])->name('patients.edit');
    Route::put('patients/{clsuId}', [PatientViewerController::class, 'update'])->name('patients.update');
});
