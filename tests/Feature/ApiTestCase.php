<?php

namespace Tests\Feature;

use Database\Seeders\PatientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PatientSeeder::class);
    }

    protected function authHeaders(string $token = 'test-token'): array
    {
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    protected function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, "/api/v1/{$uri}", $data, $this->authHeaders());
    }

    /** A valid summary matching docs/API_CONTRACT.md §6. */
    protected function encounterPayload(array $overrides = []): array
    {
        $payload = [
            'schema_version' => '1.0',
            'telemed_encounter_id' => 123,
            'telemed_request_id' => 456,
            'consent' => ['data_sharing_consented_at' => '2026-10-01T09:12:00+08:00'],
            'patient' => ['clsu_id' => '23-0001', 'first_name' => 'QA', 'last_name' => 'Patient'],
            'physician' => ['telemed_user_id' => 7, 'name' => 'Dr. QA Physician', 'specialization' => 'Internal Medicine'],
            'encounter' => [
                'type' => 'general',
                'parent_telemed_encounter_id' => null,
                'mode' => 'teleconsultation',
                'started_at' => '2026-10-22T13:30:00+08:00',
                'completed_at' => '2026-10-22T14:05:00+08:00',
            ],
            'presenting_complaint' => [
                'concern_category' => 'Respiratory',
                'symptoms' => [
                    ['name' => 'Cough', 'severity' => 2, 'severity_label' => 'mild', 'onset_date' => '2026-10-19', 'onset_time' => null],
                ],
                'additional_information' => 'Worse at night.',
            ],
            'clinical' => [
                'diagnosis' => 'Acute upper respiratory tract infection',
                'assessment' => 'Afebrile, no respiratory distress reported.',
                'plan' => 'Supportive care, increase fluid intake.',
                'recommendations' => 'Return if fever exceeds 38.5 C or breathing worsens.',
            ],
            'follow_up' => ['required' => false, 'date' => null],
            'prescription' => ['attached' => true, 'file_name' => 'prescription-123.pdf', 'mime_type' => 'application/pdf'],
        ];

        foreach ($overrides as $path => $value) {
            data_set($payload, $path, $value);
        }

        return $payload;
    }
}
