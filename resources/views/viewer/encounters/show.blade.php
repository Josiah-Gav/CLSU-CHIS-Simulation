@extends('viewer.layout')

@section('title', $encounter->chis_encounter_id)

@section('content')
    @php
        $p = $encounter->payload;
        $severityLabels = ['very_mild' => 'Very mild', 'mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe'];
        // Payload timestamps are ISO 8601 strings; show them readably.
        $when = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.timezone'))->format('M j, Y g:i A') : '—';
    @endphp

    <p><a href="{{ route('viewer.encounters.index') }}">&larr; All received encounters</a></p>

    <div class="card">
        <h2>{{ $encounter->chis_encounter_id }}</h2>
        <div class="muted">
            Received {{ $encounter->received_at->format('M j, Y g:i:s A') }} from Telemed (encounter #{{ $encounter->telemed_encounter_id }}, request #{{ data_get($p, 'telemed_request_id') }})
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Patient</h3>
            <dl>
                <dt>Name</dt><dd>{{ data_get($p, 'patient.first_name') }} {{ data_get($p, 'patient.last_name') }}</dd>
                <dt>CLSU ID</dt><dd>{{ data_get($p, 'patient.clsu_id') }}</dd>
                <dt>Shared under</dt>
                <dd>
                    @if (data_get($p, 'lawful_basis') === 'medical_treatment')
                        Medical treatment (CLSU record-keeping; no consent required)
                    @else
                        Patient consent given {{ $when(data_get($p, 'consent.data_sharing_consented_at')) }}
                    @endif
                </dd>
            </dl>
        </div>
        <div class="card">
            <h3>Encounter</h3>
            <dl>
                <dt>Type</dt><dd>{{ data_get($p, 'encounter.type') === 'follow_up' ? 'Follow-up of Telemed #'.data_get($p, 'encounter.parent_telemed_encounter_id') : 'General' }}</dd>
                <dt>Mode</dt><dd>{{ data_get($p, 'encounter.mode') }}</dd>
                <dt>Physician</dt><dd>{{ data_get($p, 'physician.name') }}@if (data_get($p, 'physician.specialization')) · {{ data_get($p, 'physician.specialization') }}@endif</dd>
                <dt>Started</dt><dd>{{ $when(data_get($p, 'encounter.started_at')) }}</dd>
                <dt>Completed</dt><dd>{{ $when(data_get($p, 'encounter.completed_at')) }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <h3>Presenting complaint — {{ data_get($p, 'presenting_complaint.concern_category') }}</h3>
        <table>
            <thead><tr><th>Symptom</th><th>Severity</th><th>Onset</th></tr></thead>
            <tbody>
                @foreach (data_get($p, 'presenting_complaint.symptoms', []) as $symptom)
                    <tr>
                        <td>{{ $symptom['name'] ?? '' }}</td>
                        <td>{{ $symptom['severity'] ?? '' }} — {{ $severityLabels[$symptom['severity_label'] ?? ''] ?? ($symptom['severity_label'] ?? '') }}</td>
                        <td>{{ trim(($symptom['onset_date'] ?? '').' '.($symptom['onset_time'] ?? '')) ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (data_get($p, 'presenting_complaint.additional_information'))
            <p class="muted" style="margin-bottom:0">Additional information: {{ data_get($p, 'presenting_complaint.additional_information') }}</p>
        @endif
    </div>

    <div class="card">
        <h3>Clinical summary</h3>
        <dl>
            <dt>Diagnosis</dt><dd>{{ data_get($p, 'clinical.diagnosis') }}</dd>
            <dt>Assessment</dt><dd style="white-space:pre-line">{{ data_get($p, 'clinical.assessment') }}</dd>
            <dt>Plan</dt><dd style="white-space:pre-line">{{ data_get($p, 'clinical.plan') }}</dd>
            <dt>Recommendations</dt><dd style="white-space:pre-line">{{ data_get($p, 'clinical.recommendations') }}</dd>
            <dt>Follow-up</dt><dd>{{ data_get($p, 'follow_up.required') === null ? 'Not recorded by Telemed' : (data_get($p, 'follow_up.required') ? 'Required'.(data_get($p, 'follow_up.date') ? ' on '.data_get($p, 'follow_up.date') : '') : 'Not required') }}</dd>
            <dt>Prescription</dt><dd>{{ data_get($p, 'prescription.attached') ? data_get($p, 'prescription.file_name').' ('.data_get($p, 'prescription.mime_type').') — file itself is not sent' : 'None' }}</dd>
        </dl>
    </div>

    <div class="card">
        <details>
            <summary><strong>Raw JSON exactly as Telemed sent it</strong></summary>
            <pre>{{ json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
@endsection
