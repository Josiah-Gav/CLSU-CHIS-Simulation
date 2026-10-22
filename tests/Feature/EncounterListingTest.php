<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

class EncounterListingTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function submit(int $telemedId, string $clsuId, string $at): string
    {
        Carbon::setTestNow($at);

        return $this->api('POST', 'encounters', $this->encounterPayload([
            'telemed_encounter_id' => $telemedId,
            'patient.clsu_id' => $clsuId,
        ]))->assertCreated()->json('data.chis_encounter_id');
    }

    #[Test]
    public function the_list_is_newest_first_with_meta(): void
    {
        $older = $this->submit(1, '23-0001', '2026-10-22 09:00:00');
        $newer = $this->submit(2, '1996-0042', '2026-10-22 10:00:00');

        $this->api('GET', 'encounters')
            ->assertOk()
            ->assertJsonPath('data.0.chis_encounter_id', $newer)
            ->assertJsonPath('data.1.chis_encounter_id', $older)
            ->assertJsonPath('data.0.patient_clsu_id', '1996-0042')
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 15, 'total' => 2, 'last_page' => 1])
            ->assertJsonMissingPath('data.0.payload');
    }

    #[Test]
    public function it_filters_by_clsu_id(): void
    {
        $this->submit(1, '23-0001', '2026-10-22 09:00:00');
        $this->submit(2, '1996-0042', '2026-10-22 10:00:00');

        $this->api('GET', 'encounters?clsu_id=23-0001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_clsu_id', '23-0001');
    }

    #[Test]
    public function per_page_is_clamped_to_the_contract_maximum(): void
    {
        $this->api('GET', 'encounters?per_page=500')->assertJsonPath('meta.per_page', 100);
        $this->api('GET', 'encounters?per_page=0')->assertJsonPath('meta.per_page', 1);
    }

    #[Test]
    public function one_encounter_includes_the_payload_as_sent(): void
    {
        $id = $this->submit(1, '23-0001', '2026-10-22 09:00:00');

        $this->api('GET', "encounters/{$id}")
            ->assertOk()
            ->assertJsonPath('data.chis_encounter_id', $id)
            ->assertJsonPath('data.payload', $this->encounterPayload(['telemed_encounter_id' => 1]));
    }

    #[Test]
    public function an_unknown_encounter_is_a_404(): void
    {
        $this->api('GET', 'encounters/ENC-1999-000000')
            ->assertNotFound()
            ->assertJsonPath('code', 'ENCOUNTER_NOT_FOUND');
    }

    #[Test]
    public function listing_requires_the_token(): void
    {
        $this->getJson('/api/v1/encounters')->assertUnauthorized();
    }
}
