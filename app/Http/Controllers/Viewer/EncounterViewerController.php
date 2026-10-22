<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Encounter;
use Illuminate\View\View;

/** Read-only: encounters only ever arrive from Telemed through the API. */
class EncounterViewerController extends Controller
{
    public function index(): View
    {
        $encounters = Encounter::query()
            ->with('patient')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('viewer.encounters.index', compact('encounters'));
    }

    public function show(string $chisEncounterId): View
    {
        $encounter = Encounter::query()->with('patient')->where('chis_encounter_id', $chisEncounterId)->firstOrFail();

        return view('viewer.encounters.show', compact('encounter'));
    }
}
