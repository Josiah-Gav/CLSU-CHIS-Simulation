<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Support\ProfileLines;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Simulates clinic staff entering a patient's record into CHIS. Telemed only
 * ever reads these records through the API; it never writes them.
 */
class PatientViewerController extends Controller
{
    public function index(): View
    {
        $patients = Patient::query()->withCount('encounters')->orderBy('last_name')->orderBy('first_name')->get();

        return view('viewer.patients.index', compact('patients'));
    }

    public function create(): View
    {
        return view('viewer.patients.form', ['patient' => new Patient(['medical_profile' => []])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $patient = Patient::create($data);

        return redirect()->route('viewer.patients.index')->with('status', "CHIS record created for {$patient->clsu_id}.");
    }

    public function edit(string $clsuId): View
    {
        return view('viewer.patients.form', ['patient' => $this->find($clsuId)]);
    }

    public function update(Request $request, string $clsuId): RedirectResponse
    {
        $patient = $this->find($clsuId);

        $patient->update($this->validated($request, $patient));

        return redirect()->route('viewer.patients.index')->with('status', "CHIS record updated for {$patient->clsu_id}.");
    }

    private function find(string $clsuId): Patient
    {
        return Patient::findByClsuId($clsuId) ?? abort(404);
    }

    /**
     * The CLSU ID is the identifier Telemed looks records up by, so it is set
     * once at creation and never changed here.
     */
    private function validated(Request $request, ?Patient $patient): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'sex' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'patient_type' => ['required', Rule::in(['student', 'faculty', 'staff'])],
            'college' => ['nullable', 'string', 'max:150'],
            'blood_type' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
        ];

        foreach (array_keys(ProfileLines::FIELDS) as $list) {
            $rules[$list] = ['nullable', 'string', 'max:5000'];
        }

        if (! $patient) {
            $rules['clsu_id'] = ['required', 'string', 'max:50', Rule::unique('patients', 'clsu_id')];
        }

        $data = $request->validate($rules);

        $profile = [];
        $errors = [];

        foreach (array_keys(ProfileLines::FIELDS) as $list) {
            [$items, $listErrors] = ProfileLines::parse($list, $data[$list] ?? null);
            $profile[$list] = $items;

            if ($listErrors) {
                $errors[$list] = $listErrors;
            }

            unset($data[$list]);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            ...$data,
            'medical_profile' => $profile,
            'record_updated_at' => now(),
        ];
    }
}
