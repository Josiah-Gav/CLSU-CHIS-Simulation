<?php

namespace Tests\Feature;

use App\Models\Patient;
use Database\Seeders\PatientSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PatientLookupTest extends ApiTestCase
{
    #[Test]
    public function it_returns_the_contract_shape(): void
    {
        $this->api('GET', 'patients/23-0001')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'clsu_id', 'first_name', 'middle_name', 'last_name', 'sex', 'birth_date',
                'patient_type', 'college', 'blood_type', 'record_updated_at',
                'medical_profile' => ['allergies', 'chronic_conditions', 'current_medications', 'immunizations', 'family_history'],
            ]])
            ->assertJsonPath('data.clsu_id', '23-0001')
            ->assertJsonPath('data.birth_date', '2004-05-14')
            ->assertJsonPath('data.record_updated_at', '2026-09-30T10:00:00+08:00')
            ->assertJsonPath('data.medical_profile.allergies.0.substance', 'Penicillin');
    }

    #[Test]
    public function an_empty_profile_has_empty_lists_never_null(): void
    {
        $profile = $this->api('GET', 'patients/23-0002')->assertOk()->json('data.medical_profile');

        foreach (Patient::PROFILE_LISTS as $list) {
            $this->assertSame([], $profile[$list], "{$list} should be []");
        }
    }

    #[Test]
    public function a_profile_list_missing_from_storage_still_comes_back_as_an_empty_list(): void
    {
        Patient::where('clsu_id', '23-0002')->update(['medical_profile' => json_encode(['allergies' => []])]);

        $this->api('GET', 'patients/23-0002')
            ->assertOk()
            ->assertJsonPath('data.medical_profile.immunizations', []);
    }

    #[Test]
    #[DataProvider('clsuIdFormats')]
    public function every_known_clsu_id_format_resolves(string $clsuId, string $lastName): void
    {
        $this->api('GET', "patients/{$clsuId}")
            ->assertOk()
            ->assertJsonPath('data.clsu_id', $clsuId)
            ->assertJsonPath('data.last_name', $lastName);
    }

    public static function clsuIdFormats(): array
    {
        return [
            'student YY-NNNN' => ['23-0001', 'Patient'],
            'staff YY-NNNNNN' => ['26-000001', 'Santos'],
            'long-tenured staff YYYY-NNNN' => ['1996-0042', 'Villanueva'],
        ];
    }

    #[Test]
    public function ids_are_compared_as_strings_so_leading_zeros_matter(): void
    {
        $this->api('GET', 'patients/23-1')->assertNotFound()->assertJsonPath('code', 'PATIENT_NOT_FOUND');
        $this->api('GET', 'patients/230001')->assertNotFound();
    }

    #[Test]
    public function surrounding_whitespace_is_trimmed(): void
    {
        $this->api('GET', 'patients/%2023-0001%20')->assertOk()->assertJsonPath('data.clsu_id', '23-0001');
    }

    #[Test]
    public function an_unknown_id_of_any_format_is_a_404_not_a_validation_error(): void
    {
        $this->api('GET', 'patients/99-9999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'No patient is on record with that CLSU ID.', 'code' => 'PATIENT_NOT_FOUND']);

        $this->api('GET', 'patients/FAC-2001-77')->assertNotFound()->assertJsonPath('code', 'PATIENT_NOT_FOUND');
    }

    #[Test]
    public function the_seeder_is_idempotent(): void
    {
        $this->seed(PatientSeeder::class);

        $this->assertSame(4, Patient::count());
    }
}
