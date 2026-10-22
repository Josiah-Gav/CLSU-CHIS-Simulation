# CLSU CHIS Simulation

A stand-in for CLSU's **Comprehensive Health Information System (CHIS)**, so the
CLSU Telemedicine System can be built and demonstrated against a real HTTP API
before the actual CHIS exposes one. It is a separate application with its own
database: Telemed reaches it only over HTTP, exactly as it would the real CHIS.

- **Contract:** [`docs/API_CONTRACT.md`](docs/API_CONTRACT.md) — the source of truth
  for every endpoint, payload and error code.
- **Postman:** [`postman/`](postman/) — a collection that exercises and asserts the
  whole contract, plus a local environment.

## Endpoints (`/api/v1`)

| Method | Path | Auth | Purpose |
|---|---|---|---|
| `GET` | `/health` | none | Liveness check |
| `GET` | `/patients/{clsu_id}` | bearer | Patient identity and medical profile |
| `POST` | `/encounters` | bearer | Receive a completed consultation summary (idempotent) |
| `GET` | `/encounters` | bearer | List received summaries, newest first |
| `GET` | `/encounters/{chis_encounter_id}` | bearer | One summary, with the payload as sent |

## Running it locally

Requires PHP 8.2+ with `pdo_sqlite`, and Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan serve --port=8001
```

Check it is up: <http://localhost:8001/api/v1/health>, and open the viewer at <http://localhost:8001/viewer>.

Port **8001** keeps it clear of Telemed on 8000. The bearer token is
`CHIS_API_TOKEN` in `.env` (`chis-local-dev-token` by default — a local-development
value only; set a long random one anywhere else, and the same value as
`CHIS_TOKEN` in Telemed's `.env`).

Reset to a clean fixture at any time with `php artisan migrate:fresh --seed`.

## Viewer and patient records (for presenting)

Two read-only-from-Telemed pages for the demo, at <http://localhost:8001/viewer>:

- **Received encounters** — every summary Telemed has sent, newest first. The page
  refreshes itself every 5 seconds and marks anything from the last 2 minutes
  **NEW**, so a summary appears on screen moments after a consultation is completed
  in Telemed. Click a reference for the full summary and the raw JSON.
- **Patient records** — the CHIS records Telemed reads. **+ Add patient record** /
  **Edit** simulate clinic staff entering data into CHIS. To link a record to a
  Telemed patient, use the same CLSU ID **and** the same first and last name as
  their Telemed account; Telemed refuses to show a record whose name doesn't match.
  The CLSU ID can't be changed after creation. Encounters can't be created or edited
  here — they only ever arrive from Telemed through the API.

These pages show medical data, so they're **off by default**:

| Setting | Effect |
|---|---|
| `CHIS_VIEWER_ENABLED=false` (default) | Pages return 404 |
| `CHIS_VIEWER_ENABLED=true`, `APP_ENV=local` | Pages open (no login) |
| `CHIS_VIEWER_ENABLED=true`, not local, no `CHIS_VIEWER_PASSWORD` | Still 404 |
| `CHIS_VIEWER_ENABLED=true` + `CHIS_VIEWER_PASSWORD=…` | HTTP Basic login (any username) |

`.env.example` enables it for local use. Never enable it on a shared server
without a password. The API under `/api/v1` is unaffected by these settings.

## Seed patients

| `clsu_id` | Name | ID format | Profile |
|---|---|---|---|
| `23-0001` | QA Patient | Student `YY-NNNN` | Full |
| `23-0002` | QA Patient | Student `YY-NNNN` | Empty (every list `[]`) |
| `26-000001` | Maria Santos | Staff `YY-NNNNNN` | Identity-mismatch fixture |
| `1996-0042` | Roberto Villanueva | Long-tenured staff `YYYY-NNNN` | Chronic conditions |

## Demonstrating with Postman

1. Import both files from `postman/`, select the **CHIS Simulation - Local** environment.
2. Run the collection with the **Collection Runner**. All 17 requests carry tests;
   a clean run is the contract passing end to end.
3. To show Telemed's data arriving, run **List encounters** after completing a
   consultation in Telemed.

The same collection runs from the command line:
`npx newman run postman/CHIS-Simulation.postman_collection.json -e postman/CHIS-Simulation-Local.postman_environment.json`

## Tests

```bash
composer test
```

Feature tests use an in-memory SQLite database (`phpunit.xml`) and never touch
`database/database.sqlite`.
