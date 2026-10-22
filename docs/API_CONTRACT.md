# CHIS Simulation — API Contract v1

This is the interface between the **CLSU Telemedicine System** ("Telemed") and the
**Comprehensive Health Information System** ("CHIS"). No real CHIS specification
exists yet, so this repository implements a simulated CHIS against this contract.
When the real CHIS publishes its API, Telemed's `ChisClient` is the only place
that has to change.

Telemed is always the **client**. CHIS never calls Telemed.

| Direction | Purpose | Endpoint |
|---|---|---|
| Telemed → CHIS | Look up patient identity and medical profile (on demand) | `GET /api/v1/patients/{clsu_id}` |
| Telemed → CHIS | Send a completed consultation summary | `POST /api/v1/encounters` |
| Anyone → CHIS | Liveness check | `GET /api/v1/health` |
| Postman / admin → CHIS | Inspect received summaries | `GET /api/v1/encounters`, `GET /api/v1/encounters/{chis_encounter_id}` |

---

## 1. Conventions

- **Base URL:** `{CHIS_BASE_URL}/api/v1` — locally `http://localhost:8001/api/v1`.
- **Versioning:** the major version is in the path (`/v1`). The encounter payload
  also carries `schema_version` (`"1.0"`). Adding optional fields is a minor
  change; removing or renaming a field requires `/v2`.
- **Format:** JSON request and response bodies, UTF-8. Clients send
  `Accept: application/json` and, with a body, `Content-Type: application/json`.
- **Timestamps:** ISO 8601 with offset, e.g. `2026-10-22T14:05:00+08:00`.
  Dates without a time are `YYYY-MM-DD`.
- **Field naming:** `snake_case`.
- **Absent values** are sent as `null`, never omitted, so both sides can tell
  "unknown" from "field forgotten".
- **Successful responses** wrap the resource in `data`; lists add `meta`.

## 2. Authentication

Every endpoint except `/health` requires a static bearer token:

```
Authorization: Bearer {CHIS_API_TOKEN}
```

The token lives in `.env` on both sides (`CHIS_API_TOKEN` here, `CHIS_TOKEN` in
Telemed). It is never logged, never put in a URL, and never committed with a real
value. A missing or wrong token returns `401`.

## 3. Errors

Every error uses the same shape:

```json
{
  "message": "Human-readable explanation.",
  "code": "MACHINE_READABLE_CODE"
}
```

Validation errors (`422`) add an `errors` object keyed by field path:

```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": { "clinical.diagnosis": ["The clinical.diagnosis field is required."] }
}
```

| Status | `code` | Meaning | Client should retry? |
|---|---|---|---|
| 400 | `MALFORMED_JSON` | Body is not valid JSON, or is not a JSON object | No |
| 401 | `UNAUTHENTICATED` | Missing or wrong bearer token | No — fix configuration |
| 404 | `PATIENT_NOT_FOUND` | No patient with that `clsu_id` | No |
| 404 | `ENCOUNTER_NOT_FOUND` | No encounter with that id | No |
| 404 | `NOT_FOUND` | Unknown route | No |
| 405 | `METHOD_NOT_ALLOWED` | HTTP method not supported on that endpoint | No |
| 409 | `ENCOUNTER_CONFLICT` | Same `telemed_encounter_id` sent with different content | No — investigate |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | `POST` body sent without `Content-Type: application/json` | No |
| 422 | `VALIDATION_FAILED` | Body failed validation | No |
| 429 | `RATE_LIMITED` | More than 60 requests/minute per token; see `Retry-After` header | Yes, after `Retry-After` |
| 5xx | `SERVER_ERROR` | CHIS failure. The message is always generic; details go to the CHIS log only | Yes, with backoff |

**Retry rule for Telemed:** retry on timeouts, connection errors, `429` and `5xx`.
Every other `4xx` is a permanent failure: record it and stop retrying.

---

## 4. `GET /api/v1/health`

No authentication. Used by Telemed to show whether CHIS is reachable.

**200 OK**
```json
{ "status": "ok", "service": "CHIS Simulation", "api_version": "v1", "time": "2026-10-22T14:05:00+08:00" }
```

---

## 5. `GET /api/v1/patients/{clsu_id}`

Returns one patient's identity and medical profile.

- `clsu_id` is the CLSU ID number, max 50 characters, URL-encoded by the client.
  Matching is an exact **string** comparison after trimming whitespace — it is
  never parsed as a number, so leading zeros and the dash are significant
  (`23-0001` ≠ `23-1`).
- Known CLSU ID formats:

  | Holder | Format | Example |
  |---|---|---|
  | Student | `YY-NNNN` | `23-0001` |
  | Staff | `YY-NNNNNN` | `26-000001` |
  | Long-tenured staff (older ID scheme) | `YYYY-NNNN` | `1996-0042` |

  CHIS does **not** reject an ID for having an unrecognised format; it answers
  `404 PATIENT_NOT_FOUND` if the ID is not on record. Format validation belongs
  at the point of entry (Telemed registration), not here, so an ID scheme this
  list does not know about (e.g. faculty) is never locked out by CHIS.
- **Who may look a patient up is Telemed's responsibility** (only the physician
  assigned to the consultation, with an identity check and an audit entry). CHIS
  cannot see who is asking, so it does not enforce this itself.

**200 OK**
```json
{
  "data": {
    "clsu_id": "23-0001",
    "first_name": "Juan",
    "middle_name": "Reyes",
    "last_name": "Dela Cruz",
    "sex": "male",
    "birth_date": "2003-05-14",
    "patient_type": "student",
    "college": "College of Engineering",
    "blood_type": "O+",
    "medical_profile": {
      "allergies": [
        { "substance": "Penicillin", "reaction": "Hives", "severity": "moderate" }
      ],
      "chronic_conditions": [
        { "name": "Asthma", "diagnosed_on": "2015-03-01", "status": "controlled" }
      ],
      "current_medications": [
        { "name": "Salbutamol inhaler", "dose": "100 mcg", "frequency": "as needed" }
      ],
      "immunizations": [
        { "vaccine": "Hepatitis B", "dose": "3rd dose", "date_administered": "2021-08-10" }
      ],
      "family_history": [
        { "relation": "father", "condition": "Hypertension" }
      ]
    },
    "record_updated_at": "2026-09-30T10:00:00+08:00"
  }
}
```

| Field | Type | Notes |
|---|---|---|
| `sex` | `male` \| `female` \| `null` | |
| `patient_type` | `student` \| `faculty` \| `staff` | Mirrors Telemed's `users.user_type` |
| `allergies[].severity` | `mild` \| `moderate` \| `severe` | |
| `chronic_conditions[].status` | `active` \| `controlled` \| `resolved` | |
| Every `medical_profile` list | array | Empty array `[]` when none, never `null` |

**404** `PATIENT_NOT_FOUND`.

### Identity verification (Telemed's responsibility)

`clsu_id` is self-declared at Telemed registration, so a matching ID alone does
not prove the CHIS record belongs to the Telemed patient. Before showing any of
this data to a physician, Telemed must compare names:

- Normalise both sides: lowercase, strip accents, collapse whitespace, remove
  `.`, `,` and `-`.
- **Match** only if the normalised `first_name` **and** `last_name` are equal.
- On a mismatch Telemed shows *"Identity mismatch — CHIS record not displayed"*
  and none of the medical profile.

Telemed does not store birth dates, so names are the only check available in v1.
Adding a birth-date check is listed under future work.

---

## 6. `POST /api/v1/encounters`

Receives the summary of one **completed** Telemed consultation. Telemed sends it
once, after the physician completes the consultation. A completed consultation
can no longer be edited in Telemed, so a summary never changes after it is sent.

### Request body

```json
{
  "schema_version": "1.0",
  "telemed_encounter_id": 123,
  "telemed_request_id": 456,
  "lawful_basis": "medical_treatment",
  "consent": null,
  "patient": {
    "clsu_id": "23-0001",
    "first_name": "Juan",
    "last_name": "Dela Cruz"
  },
  "physician": {
    "telemed_user_id": 7,
    "name": "Dr. Ana Santos",
    "specialization": "Internal Medicine"
  },
  "encounter": {
    "type": "general",
    "parent_telemed_encounter_id": null,
    "mode": "teleconsultation",
    "started_at": "2026-10-22T13:30:00+08:00",
    "completed_at": "2026-10-22T14:05:00+08:00"
  },
  "presenting_complaint": {
    "concern_category": "Respiratory",
    "symptoms": [
      { "name": "Cough", "severity": 2, "severity_label": "mild", "onset_date": "2026-10-19", "onset_time": null }
    ],
    "additional_information": "Worse at night."
  },
  "clinical": {
    "diagnosis": "Acute upper respiratory tract infection",
    "assessment": "Afebrile, no respiratory distress reported.",
    "plan": "Supportive care, increase fluid intake.",
    "recommendations": "Return if fever exceeds 38.5 °C or breathing worsens."
  },
  "follow_up": {
    "required": null,
    "date": null
  },
  "prescription": {
    "attached": true,
    "file_name": "prescription-123.pdf",
    "mime_type": "application/pdf"
  }
}
```

### Field rules

| Field | Type | Required | Notes |
|---|---|---|---|
| `schema_version` | string | yes | Must be `"1.0"` |
| `telemed_encounter_id` | integer ≥ 1 | yes | Telemed `consultations.id`. **Idempotency key.** |
| `telemed_request_id` | integer ≥ 1 | yes | Telemed `consultation_requests.request_id` |
| `lawful_basis` | `consent` \| `medical_treatment` | no | Why Telemed may share this summary — see *Lawful basis* below. Omitted = `consent` (the original v1.0 rule). |
| `consent` | object \| null | when the basis is `consent` | May be `null` (or omitted) when `lawful_basis` is `medical_treatment` |
| `consent.data_sharing_consented_at` | timestamp | when `consent` is an object | When the patient consented. Not in the future. |
| `patient.clsu_id` | string ≤ 50 | yes | Must exist in CHIS, else `404 PATIENT_NOT_FOUND` |
| `patient.first_name`, `patient.last_name` | string ≤ 255 | yes | Recorded as sent, for audit |
| `physician.telemed_user_id` | integer ≥ 1 | yes | |
| `physician.name` | string ≤ 255 | yes | |
| `physician.specialization` | string ≤ 100 \| null | yes | |
| `encounter.type` | `general` \| `follow_up` | yes | |
| `encounter.parent_telemed_encounter_id` | integer \| null | yes | Required (non-null) when `type` is `follow_up`, null otherwise |
| `encounter.mode` | `teleconsultation` | yes | Only value in v1 |
| `encounter.started_at` | timestamp \| null | yes | Null if Telemed has no start time recorded |
| `encounter.completed_at` | timestamp | yes | Not before `started_at` |
| `presenting_complaint.concern_category` | string ≤ 100 | yes | |
| `presenting_complaint.symptoms` | array, ≥ 1 item | yes | |
| `symptoms[].name` | string ≤ 255 | yes | |
| `symptoms[].severity` | integer 1–4 | yes | Telemed's 1–4 scale |
| `symptoms[].severity_label` | `very_mild` \| `mild` \| `moderate` \| `severe` | yes | Must match `severity`: 1 = very_mild, 2 = mild, 3 = moderate, 4 = severe (Telemed's labels) |
| `symptoms[].onset_date` | date \| null | yes | |
| `symptoms[].onset_time` | `HH:MM` \| null | yes | |
| `presenting_complaint.additional_information` | string ≤ 1000 \| null | yes | |
| `clinical.diagnosis` | string ≤ 10000 | yes | Telemed refuses to complete without it |
| `clinical.assessment` | string ≤ 10000 | yes | |
| `clinical.plan` | string ≤ 10000 | yes | |
| `clinical.recommendations` | string ≤ 10000 | yes | |
| `follow_up.required` | boolean \| null | yes | `null` = **not recorded** by the sender. Never treat it as "no follow-up needed". Telemed v1 does not record follow-up intent at completion, so it always sends `null` |
| `follow_up.date` | date \| null | yes | Null when not recorded |
| `prescription.attached` | boolean | yes | |
| `prescription.file_name` | string ≤ 255 \| null | yes | Null when `attached` is false |
| `prescription.mime_type` | string ≤ 100 \| null | yes | Null when `attached` is false |

**Length limits** are at least as large as Telemed's own input limits
(assessment/plan/recommendations accept up to 10,000 characters there; profile
names up to 255; custom symptom names are free text), so a summary is never
refused, or clinically truncated, because of a limit this contract invented.

### Lawful basis

Health data is sensitive personal information under the Data Privacy Act
(RA 10173). Section 13 allows processing it without consent when it is
*necessary for medical treatment, carried out by a medical practitioner or
medical institution, with adequate protection*. Telemed and CHIS both belong to
CLSU, and the summary is kept for the patient's continuing care, so Telemed sends
every completed consultation under **`"lawful_basis": "medical_treatment"`** and
shows patients a privacy notice instead of asking for consent. (A consent that
the patient could not actually refuse — because CLSU requires every completed
consultation to reach CHIS — would not be valid consent.)

`"lawful_basis": "consent"`, or omitting the field, keeps the original rule: a
`consent` object with its timestamp is required. CHIS records whichever basis the
sender declares; it cannot verify it. This is a design decision for the capstone,
pending confirmation by the University's Data Protection Officer.

### What is deliberately **not** sent (data minimisation)

- The prescription **file** or any URL to it. A storage URL is effectively a
  link to the patient's record; CHIS gets metadata only.
- Patient-uploaded images, chat messages, video session data.
- Triage priority, nurse identity, rejection/cancellation history.
- Patient email, phone number, Telemed user id.

### Responses

**201 Created** — first time this `telemed_encounter_id` is received.
**200 OK** — the same `telemed_encounter_id` was already received **with identical
content** (a safe retry). The existing record is returned unchanged.

Both return:

```json
{
  "data": {
    "chis_encounter_id": "ENC-2026-000001",
    "telemed_encounter_id": 123,
    "patient_clsu_id": "23-0001",
    "received_at": "2026-10-22T14:05:03+08:00",
    "status": "recorded"
  }
}
```

- `chis_encounter_id` format: `ENC-{YEAR}-{6-digit sequence}`. Telemed stores it as
  its CHIS reference.
- "Identical content" is compared on a hash of the canonical JSON body (keys
  sorted recursively).

**409** `ENCOUNTER_CONFLICT` — same `telemed_encounter_id` with **different**
content. Summaries are immutable, so this means a bug on the sending side; CHIS
keeps the original and rejects the new one.

**404** `PATIENT_NOT_FOUND`, **422** `VALIDATION_FAILED`.

**Order of checks:** body format (`400`/`415`) → validation (`422`) → existing
`telemed_encounter_id` (`200` or `409`) → patient exists (`404`) → record (`201`).
So a resend whose patient was since removed still gets its original receipt.

Only the JSON body is validated; query-string parameters are ignored, so they
can never stand in for a missing body field.

---

## 7. `GET /api/v1/encounters`

Lists received summaries, newest first. Used to prove in Postman that Telemed's
data arrived.

| Query parameter | Notes |
|---|---|
| `clsu_id` | Optional filter |
| `page` | Default `1` |
| `per_page` | Default `15`. Values outside 1–100 are clamped, not rejected |

**200 OK**
```json
{
  "data": [
    {
      "chis_encounter_id": "ENC-2026-000001",
      "telemed_encounter_id": 123,
      "patient_clsu_id": "23-0001",
      "encounter_type": "general",
      "diagnosis": "Acute upper respiratory tract infection",
      "completed_at": "2026-10-22T14:05:00+08:00",
      "received_at": "2026-10-22T14:05:03+08:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 }
}
```

## 8. `GET /api/v1/encounters/{chis_encounter_id}`

Returns one received summary: the fields from the list above plus `payload`, the
full request body exactly as Telemed sent it.

**404** `ENCOUNTER_NOT_FOUND`.

---

## 9. Seed data (shared fixture)

Both repositories seed these identities so the demo works end to end. Telemed's
`QaTestUsersSeeder` must use the same `clsu_id`s.

| `clsu_id` | CHIS name | Telemed account | Expected Telemed result |
|---|---|---|---|
| `23-0001` | QA Patient (student) | `patient.test1@clsu.edu.ph` (QA Patient) | Profile shown — full medical profile |
| `23-0002` | QA Patient (student) | `patient.test2@clsu.edu.ph` (QA Patient) | Profile shown — empty profile (all lists `[]`) |
| `26-000001` | Maria Santos (staff) | Any Telemed account with a different name | **Identity mismatch** — not displayed |
| `1996-0042` | Roberto Villanueva (long-tenured staff) | `patient.test3@clsu.edu.ph` (Roberto Villanueva), added to Telemed's seeder in phase 2 | Profile shown — exercises the `YYYY-NNNN` format |
| *(any other)* | — | Any | `PATIENT_NOT_FOUND` — "No CHIS record" |

Postman collection: `postman/CHIS-Simulation.postman_collection.json`, with the
local environment in `postman/CHIS-Simulation-Local.postman_environment.json`.

## 10. Future work (not in v1)

- Birth-date check in identity verification (Telemed does not collect it yet).
- Per-client tokens with scopes instead of one shared token.
- HL7 FHIR R4 mapping (`Patient`, `Encounter`, `Condition`, `AllergyIntolerance`).
  v1 is plain JSON loosely modelled on those resources; it does not claim FHIR
  conformance.
- CHIS pushing patient-record changes to Telemed (v1 is lookup-only).
