@extends('viewer.layout')

@section('title', 'Patient records')

@section('content')
    <div class="card">
        <div class="row">
            <div>
                <h2>Patient records in CHIS</h2>
                <div class="muted">Telemed reads these by CLSU ID when the assigned physician opens a consultation's Patient tab. It never writes them.</div>
            </div>
            <a class="btn" href="{{ route('viewer.patients.create') }}">+ Add patient record</a>
        </div>

        <table>
            <thead>
                <tr><th>CLSU ID</th><th>Name</th><th>Type</th><th>Allergies</th><th>Encounters received</th><th>Last updated</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($patients as $patient)
                    @php $allergies = $patient->medical_profile['allergies'] ?? []; @endphp
                    <tr>
                        <td>{{ $patient->clsu_id }}</td>
                        <td>{{ $patient->first_name }} {{ $patient->last_name }}</td>
                        <td>{{ ucfirst($patient->patient_type) }}</td>
                        <td>
                            @forelse ($allergies as $allergy)
                                <span class="badge {{ ($allergy['severity'] ?? '') === 'severe' ? 'severe' : '' }}">{{ $allergy['substance'] ?? '' }}@if (($allergy['severity'] ?? '') === 'severe') · severe @endif</span>
                            @empty
                                <span class="muted">None</span>
                            @endforelse
                        </td>
                        <td>{{ $patient->encounters_count }}</td>
                        <td class="muted">{{ $patient->record_updated_at?->format('M j, Y g:i A') ?? '—' }}</td>
                        <td><a href="{{ route('viewer.patients.edit', $patient->clsu_id) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
