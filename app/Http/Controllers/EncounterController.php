<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncounterRequest;
use App\Models\Encounter;
use App\Models\Patient;
use App\Support\ApiError;
use App\Support\CanonicalJson;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EncounterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $page = Encounter::query()
            ->with('patient')
            ->when(
                filled($request->query('clsu_id')),
                fn ($query) => $query->whereRelation('patient', 'clsu_id', trim((string) $request->query('clsu_id')))
            )
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map->toSummaryArray()->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function show(string $chisEncounterId): JsonResponse
    {
        $encounter = Encounter::query()->with('patient')->where('chis_encounter_id', $chisEncounterId)->first();

        if (! $encounter) {
            return ApiError::response(404, 'ENCOUNTER_NOT_FOUND', 'No encounter is on record with that CHIS encounter ID.');
        }

        return response()->json(['data' => $encounter->toDetailArray()]);
    }

    /**
     * Records a completed-consultation summary. telemed_encounter_id is the
     * idempotency key: an identical resend returns the original record (200),
     * a changed one is refused (409), and the unique index settles the case of
     * two identical sends arriving at the same moment.
     */
    public function store(StoreEncounterRequest $request): JsonResponse
    {
        $payload = $request->json()->all();
        $hash = CanonicalJson::hash($payload);
        $telemedEncounterId = (int) $payload['telemed_encounter_id'];

        if ($existing = $this->findByTelemedId($telemedEncounterId)) {
            return $this->replay($existing, $hash);
        }

        $patient = Patient::findByClsuId($payload['patient']['clsu_id']);

        if (! $patient) {
            return ApiError::response(404, 'PATIENT_NOT_FOUND', 'No patient is on record with that CLSU ID.');
        }

        try {
            $encounter = DB::transaction(function () use ($payload, $hash, $patient, $telemedEncounterId) {
                $receivedAt = now();

                $encounter = Encounter::create([
                    'telemed_encounter_id' => $telemedEncounterId,
                    'patient_id' => $patient->id,
                    'encounter_type' => $payload['encounter']['type'],
                    'diagnosis' => $payload['clinical']['diagnosis'],
                    // Eloquent stores a Carbon's wall-clock time without converting
                    // it, so normalise to the app timezone or a +00:00 timestamp
                    // would be read back eight hours off.
                    'completed_at' => Carbon::parse($payload['encounter']['completed_at'])->setTimezone(config('app.timezone')),
                    'payload' => $payload,
                    'payload_hash' => $hash,
                    'received_at' => $receivedAt,
                ]);

                $encounter->forceFill([
                    'chis_encounter_id' => Encounter::formatChisId($encounter->id, $receivedAt->year),
                ])->save();

                return $encounter;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent identical submission won the race; answer as a resend.
            return $this->replay($this->findByTelemedId($telemedEncounterId), $hash);
        }

        return response()->json(['data' => $encounter->load('patient')->toReceiptArray()], 201);
    }

    private function findByTelemedId(int $telemedEncounterId): ?Encounter
    {
        return Encounter::query()->with('patient')->where('telemed_encounter_id', $telemedEncounterId)->first();
    }

    private function replay(Encounter $existing, string $hash): JsonResponse
    {
        if (! hash_equals($existing->payload_hash, $hash)) {
            return ApiError::response(
                409,
                'ENCOUNTER_CONFLICT',
                "Encounter {$existing->telemed_encounter_id} was already recorded with different content. Summaries are immutable; the original is kept."
            );
        }

        return response()->json(['data' => $existing->toReceiptArray()]);
    }
}
