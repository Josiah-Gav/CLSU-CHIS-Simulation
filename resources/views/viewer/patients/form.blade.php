@extends('viewer.layout')

@php
    $editing = $patient->exists;
    $listLabels = [
        'allergies' => 'Allergies',
        'chronic_conditions' => 'Chronic conditions',
        'current_medications' => 'Current medications',
        'immunizations' => 'Immunizations',
        'family_history' => 'Family history',
    ];
@endphp

@section('title', $editing ? 'Edit '.$patient->clsu_id : 'Add patient record')

@section('content')
    <p><a href="{{ route('viewer.patients.index') }}">&larr; All patient records</a></p>

    <form method="post" action="{{ $editing ? route('viewer.patients.update', $patient->clsu_id) : route('viewer.patients.store') }}">
        @csrf
        @if ($editing) @method('put') @endif

        @if ($errors->any())
            <div class="errors" role="alert">
                <strong>Please fix the following:</strong>
                <ul class="plain">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <h2>{{ $editing ? 'Edit CHIS record for '.$patient->clsu_id : 'Add a patient record to CHIS' }}</h2>
            <p class="muted">To link to a Telemed patient, use the <strong>same CLSU ID</strong> and the <strong>same first and last name</strong> as their Telemed account — Telemed refuses to show a record whose name does not match.</p>

            <div class="grid">
                <div class="field">
                    <label for="clsu_id">CLSU ID</label>
                    @if ($editing)
                        <input id="clsu_id" value="{{ $patient->clsu_id }}" disabled>
                        <div class="hint">The identifier Telemed looks records up by; it can't be changed.</div>
                    @else
                        <input id="clsu_id" name="clsu_id" value="{{ old('clsu_id') }}" required maxlength="50" placeholder="e.g. 23-0005">
                        <div class="hint">Student 23-0001 · staff/faculty 26-000001 · long-time staff 1996-0042</div>
                    @endif
                </div>
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name', $patient->first_name) }}" required maxlength="100">
                </div>
                <div class="field">
                    <label for="middle_name">Middle name</label>
                    <input id="middle_name" name="middle_name" value="{{ old('middle_name', $patient->middle_name) }}" maxlength="100">
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name', $patient->last_name) }}" required maxlength="100">
                </div>
                <div class="field">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex">
                        <option value="">—</option>
                        @foreach (['male' => 'Male', 'female' => 'Female'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('sex', $patient->sex) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="birth_date">Birth date</label>
                    <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', $patient->birth_date?->toDateString()) }}">
                </div>
                <div class="field">
                    <label for="patient_type">Patient type</label>
                    <select id="patient_type" name="patient_type" required>
                        @foreach (['student' => 'Student', 'faculty' => 'Faculty', 'staff' => 'Staff'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('patient_type', $patient->patient_type ?? 'student') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="college">College / office</label>
                    <input id="college" name="college" value="{{ old('college', $patient->college) }}" maxlength="150">
                </div>
                <div class="field">
                    <label for="blood_type">Blood type</label>
                    <select id="blood_type" name="blood_type">
                        <option value="">—</option>
                        @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type)
                            <option value="{{ $type }}" @selected(old('blood_type', $patient->blood_type) === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Medical profile</h2>
            <p class="muted">One item per line, fields separated by <code>|</code>. Leave a box empty if there is nothing to record.</p>
            @foreach ($listLabels as $list => $label)
                <div class="field">
                    <label for="{{ $list }}">{{ $label }}</label>
                    <textarea id="{{ $list }}" name="{{ $list }}" placeholder="e.g. {{ \App\Support\ProfileLines::EXAMPLES[$list] }}">{{ old($list, \App\Support\ProfileLines::toText($patient, $list)) }}</textarea>
                    <div class="hint">{{ \App\Support\ProfileLines::hint($list) }}</div>
                </div>
            @endforeach
        </div>

        <button class="btn" type="submit">{{ $editing ? 'Save changes' : 'Create record' }}</button>
        <a class="btn secondary" href="{{ route('viewer.patients.index') }}">Cancel</a>
    </form>
@endsection
