<?php

namespace Tests\Feature;

use App\Models\Encounter;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class EncounterSubmissionTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function postRaw(string $content, string $contentType = 'application/json'): TestResponse
    {
        return $this->call('POST', '/api/v1/encounters', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-token',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => $contentType,
        ], $content);
    }

    #[Test]
    public function a_first_submission_is_recorded_with_a_chis_reference(): void
    {
        Carbon::setTestNow('2026-10-22 14:05:03');

        $this->api('POST', 'encounters', $this->encounterPayload())
            ->assertCreated()
            ->assertExactJson(['data' => [
                'chis_encounter_id' => 'ENC-2026-000001',
                'telemed_encounter_id' => 123,
                'patient_clsu_id' => '23-0001',
                'received_at' => '2026-10-22T14:05:03+08:00',
                'status' => 'recorded',
            ]]);

        $encounter = Encounter::sole();
        $this->assertSame('general', $encounter->encounter_type);
        $this->assertSame('Acute upper respiratory tract infection', $encounter->diagnosis);
        $this->assertSame($this->encounterPayload(), $encounter->payload);
    }

    #[Test]
    public function an_identical_resend_returns_the_original_record_without_a_duplicate(): void
    {
        $first = $this->api('POST', 'encounters', $this->encounterPayload())->assertCreated()->json('data');

        $this->api('POST', 'encounters', $this->encounterPayload())
            ->assertOk()
            ->assertExactJson(['data' => $first]);

        $this->assertSame(1, Encounter::count());
    }

    #[Test]
    public function key_order_does_not_make_a_resend_count_as_different(): void
    {
        $payload = $this->encounterPayload();
        $this->api('POST', 'encounters', $payload)->assertCreated();

        $reordered = array_reverse($payload, true);
        $reordered['clinical'] = array_reverse($payload['clinical'], true);

        $this->api('POST', 'encounters', $reordered)->assertOk();
    }

    #[Test]
    public function a_changed_resend_is_a_conflict_and_the_original_is_kept(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload())->assertCreated();

        $this->api('POST', 'encounters', $this->encounterPayload(['clinical.diagnosis' => 'Allergic rhinitis']))
            ->assertStatus(409)
            ->assertJsonPath('code', 'ENCOUNTER_CONFLICT');

        $this->assertSame('Acute upper respiratory tract infection', Encounter::sole()->diagnosis);
    }

    #[Test]
    public function an_unknown_patient_is_a_404(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload(['patient.clsu_id' => '99-9999']))
            ->assertNotFound()
            ->assertJsonPath('code', 'PATIENT_NOT_FOUND');

        $this->assertSame(0, Encounter::count());
    }

    #[Test]
    public function sequence_numbers_increase_per_submission(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload())->assertCreated();

        $this->api('POST', 'encounters', $this->encounterPayload(['telemed_encounter_id' => 124]))
            ->assertCreated()
            ->assertJsonPath('data.chis_encounter_id', sprintf('ENC-%d-000002', now()->year));
    }

    #[Test]
    public function completed_at_in_another_offset_is_stored_as_the_same_instant(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'encounter.started_at' => '2026-10-22T05:30:00+00:00',
            'encounter.completed_at' => '2026-10-22T06:05:00+00:00',
        ]))->assertCreated();

        $this->api('GET', 'encounters')->assertJsonPath('data.0.completed_at', '2026-10-22T14:05:00+08:00');
    }

    #[Test]
    public function a_valid_follow_up_is_accepted(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'encounter.type' => 'follow_up',
            'encounter.parent_telemed_encounter_id' => 100,
        ]))->assertCreated();
    }

    #[Test]
    public function no_prescription_is_accepted_when_the_file_fields_are_null(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'prescription' => ['attached' => false, 'file_name' => null, 'mime_type' => null],
        ]))->assertCreated();
    }

    #[Test]
    public function every_known_clsu_id_format_can_receive_encounters(): void
    {
        foreach (['23-0001', '26-000001', '1996-0042'] as $i => $clsuId) {
            $this->api('POST', 'encounters', $this->encounterPayload([
                'telemed_encounter_id' => 500 + $i,
                'patient.clsu_id' => $clsuId,
            ]))->assertCreated()->assertJsonPath('data.patient_clsu_id', $clsuId);
        }
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function invalid_payloads_are_rejected_field_by_field(array $overrides, string $errorKey): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonValidationErrors([$errorKey]);

        $this->assertSame(0, Encounter::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'wrong schema version' => [['schema_version' => '2.0'], 'schema_version'],
            'missing diagnosis' => [['clinical.diagnosis' => null], 'clinical.diagnosis'],
            'blank assessment' => [['clinical.assessment' => ''], 'clinical.assessment'],
            'no symptoms' => [['presenting_complaint.symptoms' => []], 'presenting_complaint.symptoms'],
            'unknown encounter type' => [['encounter.type' => 'walk_in'], 'encounter.type'],
            'unknown mode' => [['encounter.mode' => 'in_person'], 'encounter.mode'],
            'follow-up without parent' => [['encounter.type' => 'follow_up'], 'encounter.parent_telemed_encounter_id'],
            'general with a parent' => [['encounter.parent_telemed_encounter_id' => 99], 'encounter.parent_telemed_encounter_id'],
            'completed before started' => [['encounter.completed_at' => '2026-10-22T13:00:00+08:00'], 'encounter.completed_at'],
            'consent in the future' => [['consent.data_sharing_consented_at' => '2099-01-01T00:00:00+08:00'], 'consent.data_sharing_consented_at'],
            'missing consent' => [['consent' => null], 'consent'],
            'severity out of range' => [['presenting_complaint.symptoms.0.severity' => 5], 'presenting_complaint.symptoms.0.severity'],
            'severity label mismatch' => [['presenting_complaint.symptoms.0.severity_label' => 'severe'], 'presenting_complaint.symptoms.0.severity_label'],
            'bad onset time' => [['presenting_complaint.symptoms.0.onset_time' => '7pm'], 'presenting_complaint.symptoms.0.onset_time'],
            'attached without file name' => [['prescription.file_name' => null], 'prescription.file_name'],
            'not attached but has file name' => [['prescription.attached' => false, 'prescription.mime_type' => null], 'prescription.file_name'],
            'clsu id too long' => [['patient.clsu_id' => str_repeat('1', 51)], 'patient.clsu_id'],
            'non-integer encounter id' => [['telemed_encounter_id' => 'abc'], 'telemed_encounter_id'],
        ];
    }

    #[Test]
    public function limits_match_telemeds_own_input_limits(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'clinical.assessment' => str_repeat('a', 10000),
            'patient.last_name' => str_repeat('b', 255),
            'physician.name' => str_repeat('c', 255),
            'presenting_complaint.symptoms.0.name' => str_repeat('d', 255),
        ]))->assertCreated();

        $this->api('POST', 'encounters', $this->encounterPayload([
            'telemed_encounter_id' => 124,
            'clinical.plan' => str_repeat('a', 10001),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['clinical.plan']);
    }

    #[Test]
    public function a_nullable_field_that_is_omitted_rather_than_null_is_rejected(): void
    {
        $payload = $this->encounterPayload();
        unset($payload['physician']['specialization']);

        $this->api('POST', 'encounters', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['physician.specialization']);
    }

    #[Test]
    public function query_string_parameters_cannot_stand_in_for_body_fields(): void
    {
        $payload = $this->encounterPayload();
        unset($payload['schema_version']);

        $this->json('POST', '/api/v1/encounters?schema_version=1.0', $payload, $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['schema_version']);
    }

    #[Test]
    public function malformed_json_is_a_400(): void
    {
        $this->postRaw('{"schema_version": "1.0",')
            ->assertStatus(400)
            ->assertExactJson(['message' => 'The request body is not valid JSON.', 'code' => 'MALFORMED_JSON']);
    }

    #[Test]
    public function a_json_list_or_scalar_is_a_400(): void
    {
        $this->postRaw('[1, 2]')->assertStatus(400)->assertJsonPath('code', 'MALFORMED_JSON');
        $this->postRaw('"text"')->assertStatus(400)->assertJsonPath('code', 'MALFORMED_JSON');
    }

    #[Test]
    public function a_non_json_content_type_is_a_415(): void
    {
        $this->postRaw(json_encode($this->encounterPayload()), 'text/plain')
            ->assertStatus(415)
            ->assertJsonPath('code', 'UNSUPPORTED_MEDIA_TYPE');
    }

    #[Test]
    public function submitting_requires_the_token(): void
    {
        $this->postJson('/api/v1/encounters', $this->encounterPayload())->assertUnauthorized();

        $this->assertSame(0, Encounter::count());
    }
}
