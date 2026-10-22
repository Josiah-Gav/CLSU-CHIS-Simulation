<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates a completed-consultation summary against docs/API_CONTRACT.md §6.
 *
 * Length limits are set to Telemed's own input limits, so nothing Telemed
 * accepts from a user can ever be refused here for length.
 *
 * "present" (rather than "required") is used for fields the contract allows to
 * be null: the contract says absent values are sent as null, never omitted, so
 * a missing key is an error even where null is fine.
 */
class StoreEncounterRequest extends FormRequest
{
    /** Telemed's 1–4 symptom severity scale and its labels. */
    public const SEVERITY_LABELS = [
        1 => 'very_mild',
        2 => 'mild',
        3 => 'moderate',
        4 => 'severe',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** Validate the JSON body only, never query-string parameters. */
    public function validationData(): array
    {
        return $this->json()->all();
    }

    public function rules(): array
    {
        $completedAt = ['required', 'date'];

        if (filled(data_get($this->validationData(), 'encounter.started_at'))) {
            $completedAt[] = 'after_or_equal:encounter.started_at';
        }

        return [
            'schema_version' => ['required', 'string', 'in:1.0'],
            'telemed_encounter_id' => ['required', 'integer', 'min:1'],
            'telemed_request_id' => ['required', 'integer', 'min:1'],

            'consent' => ['required', 'array'],
            'consent.data_sharing_consented_at' => ['required', 'date', 'before_or_equal:now'],

            'patient' => ['required', 'array'],
            'patient.clsu_id' => ['required', 'string', 'max:50'],
            'patient.first_name' => ['required', 'string', 'max:255'],
            'patient.last_name' => ['required', 'string', 'max:255'],

            'physician' => ['required', 'array'],
            'physician.telemed_user_id' => ['required', 'integer', 'min:1'],
            'physician.name' => ['required', 'string', 'max:255'],
            'physician.specialization' => ['present', 'nullable', 'string', 'max:100'],

            'encounter' => ['required', 'array'],
            'encounter.type' => ['required', 'in:general,follow_up'],
            'encounter.parent_telemed_encounter_id' => [
                'present', 'nullable', 'integer', 'min:1',
                'required_if:encounter.type,follow_up',
                'prohibited_unless:encounter.type,follow_up',
            ],
            'encounter.mode' => ['required', 'in:teleconsultation'],
            'encounter.started_at' => ['present', 'nullable', 'date'],
            'encounter.completed_at' => $completedAt,

            'presenting_complaint' => ['required', 'array'],
            'presenting_complaint.concern_category' => ['required', 'string', 'max:100'],
            'presenting_complaint.symptoms' => ['required', 'array', 'list', 'min:1'],
            'presenting_complaint.symptoms.*' => ['required', 'array'],
            'presenting_complaint.symptoms.*.name' => ['required', 'string', 'max:255'],
            'presenting_complaint.symptoms.*.severity' => ['required', 'integer', 'between:1,4'],
            'presenting_complaint.symptoms.*.severity_label' => ['required', 'in:'.implode(',', self::SEVERITY_LABELS)],
            'presenting_complaint.symptoms.*.onset_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'presenting_complaint.symptoms.*.onset_time' => ['present', 'nullable', 'date_format:H:i'],
            'presenting_complaint.additional_information' => ['present', 'nullable', 'string', 'max:1000'],

            'clinical' => ['required', 'array'],
            'clinical.diagnosis' => ['required', 'string', 'max:10000'],
            'clinical.assessment' => ['required', 'string', 'max:10000'],
            'clinical.plan' => ['required', 'string', 'max:10000'],
            'clinical.recommendations' => ['required', 'string', 'max:10000'],

            'follow_up' => ['required', 'array'],
            'follow_up.required' => ['required', 'boolean'],
            'follow_up.date' => ['present', 'nullable', 'date_format:Y-m-d'],

            'prescription' => ['required', 'array'],
            'prescription.attached' => ['required', 'boolean'],
            'prescription.file_name' => [
                'present', 'nullable', 'string', 'max:255',
                'required_if_accepted:prescription.attached',
                'prohibited_if_declined:prescription.attached',
            ],
            'prescription.mime_type' => [
                'present', 'nullable', 'string', 'max:100',
                'required_if_accepted:prescription.attached',
                'prohibited_if_declined:prescription.attached',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $symptoms = data_get($this->validationData(), 'presenting_complaint.symptoms');

                if (! is_array($symptoms)) {
                    return;
                }

                foreach ($symptoms as $index => $symptom) {
                    $severity = is_array($symptom) ? ($symptom['severity'] ?? null) : null;
                    $label = is_array($symptom) ? ($symptom['severity_label'] ?? null) : null;
                    $expected = is_int($severity) ? (self::SEVERITY_LABELS[$severity] ?? null) : null;

                    if ($expected !== null && is_string($label) && $label !== $expected) {
                        $validator->errors()->add(
                            "presenting_complaint.symptoms.{$index}.severity_label",
                            "Severity {$severity} must be labelled \"{$expected}\"."
                        );
                    }
                }
            },
        ];
    }
}
