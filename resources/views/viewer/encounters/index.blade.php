@extends('viewer.layout')

@section('title', 'Received encounters')

@push('head')
    {{-- Refreshes itself so a summary sent by Telemed appears without clicking. --}}
    <meta http-equiv="refresh" content="5">
@endpush

@section('content')
    <div class="card">
        <div class="row">
            <div>
                <h2>Encounters received from Telemed</h2>
                <div class="muted">Newest first · this page refreshes every 5 seconds · received in the last 2 minutes are marked <span class="badge new">NEW</span></div>
            </div>
        </div>

        @if ($encounters->isEmpty())
            <p class="muted">No encounters received yet. Complete a consultation in Telemed for a patient who has consented.</p>
        @else
            <table>
                <thead>
                    <tr><th>Received</th><th>CHIS reference</th><th>Patient</th><th>Type</th><th>Diagnosis</th><th>Telemed #</th></tr>
                </thead>
                <tbody>
                    @foreach ($encounters as $encounter)
                        @php $isNew = $encounter->received_at->gt(now()->subMinutes(2)); @endphp
                        <tr class="{{ $isNew ? 'new' : '' }}">
                            <td>{{ $encounter->received_at->format('M j, Y g:i:s A') }} @if ($isNew)<span class="badge new">NEW</span>@endif</td>
                            <td><a href="{{ route('viewer.encounters.show', $encounter->chis_encounter_id) }}">{{ $encounter->chis_encounter_id }}</a></td>
                            <td>{{ $encounter->patient->first_name }} {{ $encounter->patient->last_name }}<div class="muted">{{ $encounter->patient->clsu_id }}</div></td>
                            <td>{{ $encounter->encounter_type === 'follow_up' ? 'Follow-up' : 'General' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($encounter->diagnosis, 80) }}</td>
                            <td>{{ $encounter->telemed_encounter_id }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px">{{ $encounters->links('pagination::simple-default') }}</div>
        @endif
    </div>
@endsection
