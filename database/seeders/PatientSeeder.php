<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

/**
 * The shared fixture from docs/API_CONTRACT.md §9. Telemed's QaTestUsersSeeder
 * uses the same clsu_ids, so keep the two in step.
 *
 * Idempotent: re-running updates the records in place instead of duplicating.
 */
class PatientSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->patients() as $patient) {
            Patient::updateOrCreate(['clsu_id' => $patient['clsu_id']], $patient);
        }
    }

    private function patients(): array
    {
        return [
            // Student ID format (YY-NNNN), full medical profile.
            [
                'clsu_id' => '23-0001',
                'first_name' => 'QA',
                'middle_name' => null,
                'last_name' => 'Patient',
                'sex' => 'male',
                'birth_date' => '2004-05-14',
                'patient_type' => 'student',
                'college' => 'College of Engineering',
                'blood_type' => 'O+',
                'medical_profile' => [
                    'allergies' => [
                        ['substance' => 'Penicillin', 'reaction' => 'Hives', 'severity' => 'moderate'],
                        ['substance' => 'Shrimp', 'reaction' => 'Lip swelling', 'severity' => 'severe'],
                    ],
                    'chronic_conditions' => [
                        ['name' => 'Asthma', 'diagnosed_on' => '2012-03-01', 'status' => 'controlled'],
                    ],
                    'current_medications' => [
                        ['name' => 'Salbutamol inhaler', 'dose' => '100 mcg', 'frequency' => 'as needed'],
                    ],
                    'immunizations' => [
                        ['vaccine' => 'Hepatitis B', 'dose' => '3rd dose', 'date_administered' => '2021-08-10'],
                        ['vaccine' => 'Tetanus-diphtheria (Td)', 'dose' => 'booster', 'date_administered' => '2023-07-18'],
                        ['vaccine' => 'COVID-19 (Pfizer-BioNTech)', 'dose' => '2nd dose', 'date_administered' => '2022-01-12'],
                    ],
                    'family_history' => [
                        ['relation' => 'father', 'condition' => 'Hypertension'],
                        ['relation' => 'mother', 'condition' => 'Type 2 diabetes mellitus'],
                    ],
                ],
                'record_updated_at' => '2026-09-30 10:00:00',
            ],
            // Student ID format, nothing on record: every list must come back [].
            [
                'clsu_id' => '23-0002',
                'first_name' => 'QA',
                'middle_name' => null,
                'last_name' => 'Patient',
                'sex' => 'female',
                'birth_date' => '2005-02-21',
                'patient_type' => 'student',
                'college' => 'College of Arts and Social Sciences',
                'blood_type' => null,
                'medical_profile' => [
                    'allergies' => [],
                    'chronic_conditions' => [],
                    'current_medications' => [],
                    'immunizations' => [],
                    'family_history' => [],
                ],
                'record_updated_at' => '2026-08-15 09:00:00',
            ],
            // Staff ID format (YY-NNNNNN). Deliberately named differently from any
            // Telemed QA account: Telemed must refuse to display it (identity mismatch).
            [
                'clsu_id' => '26-000001',
                'first_name' => 'Maria',
                'middle_name' => 'Lopez',
                'last_name' => 'Santos',
                'sex' => 'female',
                'birth_date' => '1990-11-02',
                'patient_type' => 'staff',
                'college' => 'Office of Student Affairs',
                'blood_type' => 'A+',
                'medical_profile' => [
                    'allergies' => [
                        ['substance' => 'Aspirin', 'reaction' => 'Wheezing', 'severity' => 'severe'],
                    ],
                    'chronic_conditions' => [],
                    'current_medications' => [],
                    'immunizations' => [
                        ['vaccine' => 'Influenza', 'dose' => 'annual', 'date_administered' => '2026-04-05'],
                    ],
                    'family_history' => [],
                ],
                'record_updated_at' => '2026-10-01 14:30:00',
            ],
            // Long-tenured staff, older ID scheme (YYYY-NNNN).
            [
                'clsu_id' => '1996-0042',
                'first_name' => 'Roberto',
                'middle_name' => 'Cruz',
                'last_name' => 'Villanueva',
                'sex' => 'male',
                'birth_date' => '1968-07-09',
                'patient_type' => 'staff',
                'college' => 'College of Agriculture',
                'blood_type' => 'B+',
                'medical_profile' => [
                    'allergies' => [],
                    'chronic_conditions' => [
                        ['name' => 'Hypertension', 'diagnosed_on' => '2010-06-15', 'status' => 'controlled'],
                        ['name' => 'Type 2 diabetes mellitus', 'diagnosed_on' => '2016-02-20', 'status' => 'active'],
                    ],
                    'current_medications' => [
                        ['name' => 'Amlodipine', 'dose' => '5 mg', 'frequency' => 'once daily'],
                        ['name' => 'Metformin', 'dose' => '500 mg', 'frequency' => 'twice daily'],
                    ],
                    'immunizations' => [
                        ['vaccine' => 'Pneumococcal (PPSV23)', 'dose' => 'single dose', 'date_administered' => '2025-09-12'],
                    ],
                    'family_history' => [
                        ['relation' => 'father', 'condition' => 'Coronary artery disease'],
                    ],
                ],
                'record_updated_at' => '2026-10-10 08:15:00',
            ],
        ];
    }
}
