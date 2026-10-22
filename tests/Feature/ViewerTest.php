<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

class ViewerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['chis.viewer_enabled' => true, 'chis.viewer_password' => null]);
        // The viewer only runs locally; outside 'testing' Laravel enforces CSRF,
        // which is covered on its own below.
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function recordForm(array $overrides = []): array
    {
        return array_merge([
            'clsu_id' => '23-0005',
            'first_name' => 'Ana',
            'middle_name' => '',
            'last_name' => 'Reyes',
            'sex' => 'female',
            'birth_date' => '2004-02-03',
            'patient_type' => 'student',
            'college' => 'College of Science',
            'blood_type' => 'B+',
            'allergies' => "Peanuts | Hives | severe\nDust | Sneezing | mild",
            'chronic_conditions' => 'Asthma | 2015-06-01 | controlled',
            'current_medications' => 'Cetirizine | 10 mg | once daily',
            'immunizations' => 'Hepatitis B | 3rd dose | 2021-08-10',
            'family_history' => 'mother | Hypertension',
        ], $overrides);
    }

    // --- access ----------------------------------------------------------------

    #[Test]
    public function it_is_hidden_when_disabled(): void
    {
        config(['chis.viewer_enabled' => false]);

        $this->get('/viewer/encounters')->assertNotFound();
        $this->get('/viewer/patients')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Received encounters');
    }

    #[Test]
    public function outside_local_it_stays_hidden_without_a_password(): void
    {
        $this->app['env'] = 'production';

        $this->get('/viewer/encounters')->assertNotFound();
    }

    #[Test]
    public function with_a_password_it_requires_basic_auth(): void
    {
        $this->app['env'] = 'production';
        config(['chis.viewer_password' => 'secret-pass']);

        $this->get('/viewer/encounters')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->get('/viewer/encounters', ['PHP_AUTH_USER' => 'any', 'PHP_AUTH_PW' => 'wrong'])->assertUnauthorized();
        $this->get('/viewer/encounters', ['PHP_AUTH_USER' => 'any', 'PHP_AUTH_PW' => 'secret-pass'])->assertOk();
    }

    #[Test]
    public function the_home_page_redirects_to_the_viewer_when_enabled(): void
    {
        $this->get('/')->assertRedirect(route('viewer.encounters.index'));
    }

    #[Test]
    public function the_api_is_unaffected_by_the_viewer_settings(): void
    {
        config(['chis.viewer_enabled' => false]);

        $this->api('GET', 'patients/23-0001')->assertOk();
    }

    // --- encounters -------------------------------------------------------------

    #[Test]
    public function received_encounters_are_listed_newest_first_with_the_simulator_banner(): void
    {
        Carbon::setTestNow('2026-10-22 09:00:00');
        $this->api('POST', 'encounters', $this->encounterPayload(['telemed_encounter_id' => 1]))->assertCreated();
        Carbon::setTestNow('2026-10-22 10:00:00');
        $this->api('POST', 'encounters', $this->encounterPayload(['telemed_encounter_id' => 2, 'clinical.diagnosis' => 'Migraine']))->assertCreated();

        $this->get('/viewer/encounters')
            ->assertOk()
            ->assertSee('not CLSU', false)
            ->assertSee('http-equiv="refresh"', false)
            ->assertSeeInOrder(['ENC-2026-000002', 'Migraine', 'ENC-2026-000001', 'Acute upper respiratory tract infection'])
            ->assertSee('NEW');
    }

    #[Test]
    public function an_empty_list_explains_how_to_get_data(): void
    {
        $this->get('/viewer/encounters')->assertOk()->assertSee('No encounters received yet');
    }

    #[Test]
    public function one_encounter_shows_the_summary_and_raw_json(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'follow_up' => ['required' => null, 'date' => null],
        ]))->assertCreated();

        $this->get('/viewer/encounters/ENC-'.now()->year.'-000001')
            ->assertOk()
            ->assertSee('Acute upper respiratory tract infection')
            ->assertSee('Cough')
            ->assertSee('Oct 22, 2026 2:05 PM')
            ->assertSee('Patient consent given')
            ->assertSee('Not recorded by Telemed')
            ->assertSee('file itself is not sent')
            ->assertSee('Raw JSON exactly as Telemed sent it');
    }

    #[Test]
    public function the_viewer_shows_a_medical_treatment_basis(): void
    {
        $this->api('POST', 'encounters', $this->encounterPayload([
            'lawful_basis' => 'medical_treatment', 'consent' => null,
        ]))->assertCreated();

        $this->get('/viewer/encounters/ENC-'.now()->year.'-000001')
            ->assertOk()
            ->assertSee('Medical treatment (CLSU record-keeping; no consent required)');
    }

    #[Test]
    public function an_unknown_encounter_is_a_404(): void
    {
        $this->get('/viewer/encounters/ENC-1999-000000')->assertNotFound();
    }

    #[Test]
    public function the_viewer_cannot_create_or_change_encounters(): void
    {
        $this->post('/viewer/encounters', [])->assertStatus(405);
    }

    #[Test]
    public function the_form_is_csrf_protected(): void
    {
        $this->withMiddleware(ValidateCsrfToken::class);

        $this->post('/viewer/patients', $this->recordForm())->assertStatus(419);
        $this->assertNull(Patient::findByClsuId('23-0005'));
    }

    // --- patient records -----------------------------------------------------------

    #[Test]
    public function patients_are_listed_with_severe_allergies_marked(): void
    {
        $this->get('/viewer/patients')
            ->assertOk()
            ->assertSee('23-0001')
            ->assertSee('1996-0042')
            ->assertSee('Shrimp')
            ->assertSee('severe');
    }

    #[Test]
    public function a_new_record_is_created_and_immediately_served_by_the_api(): void
    {
        $this->get('/viewer/patients/create')->assertOk()->assertSee('same first and last name')->assertSee('e.g. Penicillin | Hives | severe');

        $this->post('/viewer/patients', $this->recordForm())
            ->assertRedirect(route('viewer.patients.index'))
            ->assertSessionHas('status');

        $data = $this->api('GET', 'patients/23-0005')->assertOk()->json('data');

        $this->assertSame('Ana', $data['first_name']);
        $this->assertNull($data['middle_name']);
        $this->assertSame('B+', $data['blood_type']);
        $this->assertSame([
            ['substance' => 'Peanuts', 'reaction' => 'Hives', 'severity' => 'severe'],
            ['substance' => 'Dust', 'reaction' => 'Sneezing', 'severity' => 'mild'],
        ], $data['medical_profile']['allergies']);
        $this->assertSame([['name' => 'Asthma', 'diagnosed_on' => '2015-06-01', 'status' => 'controlled']], $data['medical_profile']['chronic_conditions']);
        $this->assertSame([['relation' => 'mother', 'condition' => 'Hypertension']], $data['medical_profile']['family_history']);
        $this->assertNotNull($data['record_updated_at']);
    }

    #[Test]
    public function empty_boxes_become_empty_lists(): void
    {
        $this->post('/viewer/patients', $this->recordForm([
            'allergies' => '', 'chronic_conditions' => "\n\n", 'current_medications' => '',
            'immunizations' => '', 'family_history' => '',
        ]))->assertSessionHasNoErrors();

        $profile = $this->api('GET', 'patients/23-0005')->json('data.medical_profile');

        foreach (Patient::PROFILE_LISTS as $list) {
            $this->assertSame([], $profile[$list], $list);
        }
    }

    #[Test]
    public function optional_fields_may_be_blank(): void
    {
        $this->post('/viewer/patients', $this->recordForm([
            'chronic_conditions' => 'Asthma |  | active',
            'current_medications' => 'Paracetamol',
            'immunizations' => 'Influenza | | ',
        ]))->assertSessionHasNoErrors();

        $profile = $this->api('GET', 'patients/23-0005')->json('data.medical_profile');

        $this->assertNull($profile['chronic_conditions'][0]['diagnosed_on']);
        $this->assertSame(['name' => 'Paracetamol', 'dose' => null, 'frequency' => null], $profile['current_medications'][0]);
        $this->assertNull($profile['immunizations'][0]['date_administered']);
    }

    #[Test]
    public function bad_lines_are_rejected_with_line_numbers(): void
    {
        $this->from('/viewer/patients/create')->post('/viewer/patients', $this->recordForm([
            'allergies' => "Peanuts | Hives | extreme\n | Rash | mild",
            'chronic_conditions' => 'Asthma | last year | controlled',
            'family_history' => 'mother | Hypertension | extra',
        ]))
            ->assertRedirect('/viewer/patients/create')
            ->assertSessionHasErrors(['allergies', 'chronic_conditions', 'family_history']);

        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('Line 1: severity must be one of mild, moderate, severe.', implode(' ', $errors->get('allergies')));
        $this->assertStringContainsString('Line 2: substance is required.', implode(' ', $errors->get('allergies')));
        $this->assertSame(4, Patient::count());
    }

    #[Test]
    public function identity_fields_are_validated(): void
    {
        $this->post('/viewer/patients', $this->recordForm([
            'clsu_id' => '23-0001', 'first_name' => '', 'patient_type' => 'alien', 'blood_type' => 'Z', 'birth_date' => '2999-01-01',
        ]))->assertSessionHasErrors(['clsu_id', 'first_name', 'patient_type', 'blood_type', 'birth_date']);
    }

    #[Test]
    public function editing_updates_the_record_but_never_its_clsu_id(): void
    {
        $this->get('/viewer/patients/23-0002/edit')->assertOk()->assertSee("can't be changed", false);

        $this->put('/viewer/patients/23-0002', $this->recordForm([
            'clsu_id' => '99-9999', 'first_name' => 'QA', 'last_name' => 'Patient',
            'allergies' => 'Latex | Itching | moderate',
        ]))->assertRedirect(route('viewer.patients.index'));

        $this->api('GET', 'patients/99-9999')->assertNotFound();
        $this->api('GET', 'patients/23-0002')
            ->assertOk()
            ->assertJsonPath('data.medical_profile.allergies.0.substance', 'Latex');
    }

    #[Test]
    public function editing_an_unknown_record_is_a_404(): void
    {
        $this->get('/viewer/patients/99-9999/edit')->assertNotFound();
        $this->put('/viewer/patients/99-9999', $this->recordForm())->assertNotFound();
    }

    #[Test]
    public function the_form_round_trips_existing_lists(): void
    {
        $this->get('/viewer/patients/23-0001/edit')
            ->assertOk()
            ->assertSee('Penicillin | Hives | moderate')
            ->assertSee('Asthma | 2012-03-01 | controlled');
    }
}
