<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\ApiError;
use Illuminate\Http\JsonResponse;

class PatientController extends Controller
{
    public function show(string $clsuId): JsonResponse
    {
        $patient = Patient::findByClsuId($clsuId);

        if (! $patient) {
            return ApiError::response(404, 'PATIENT_NOT_FOUND', 'No patient is on record with that CLSU ID.');
        }

        return response()->json(['data' => $patient->toApiArray()]);
    }
}
