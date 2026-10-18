# Mustang

A CRM for franchise car dealerships: the system of record for customers, leads, deals, appointments and two-way text
and email, with a daily "contact now" list that tells each rep who to reach and why. Requirements:
[`docs/pdr/requirements-baseline.md`](docs/pdr/requirements-baseline.md); the MVP plan and its split:
[`docs/pdr/prd-mvp.md`](docs/pdr/prd-mvp.md); design: [`DESIGN.md`](DESIGN.md).

## Running it

Everything runs in Docker; nothing is installed on the host.

```bash
cp .env.example .env                       # optional: host ports and your UID/GID
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console mustang:seed-demo
```

- App: http://localhost:8080 (sign in as `admin@mustang.test`, `manager@mustang.test`, `bdc@mustang.test`,
  `sales@mustang.test` or `sales2@mustang.test`; password `Mustang-dev-1`, dev only).
- Mailpit: http://localhost:8025. Texts never leave the stack (`MESSAGING_SMS_TRANSPORT=fake`).
- The `node` service rebuilds the UI on every save (`docker compose logs node`); `UI_WATCH=0` builds once.

| Check | Command |
|---|---|
| PHP tests | `docker compose exec php php bin/phpunit` (migrates and seeds `mustang_test` itself) |
| UI tests | `docker compose run --rm --no-deps node npm test` |
| Smoke suite | `backend/e2e/smoke.sh` (resets the dev database to the seed) |
| The whole gate | `~/.claude/skills/symfony-react-app/scripts/gate.sh` |
| API types | `docker compose exec php php bin/console nelmio:apidoc:dump --format=json > backend/assets/types/openapi.json && docker compose run --rm --no-deps node npm run -s api:types` |

## Stack

PHP 8.4.26, Symfony 8.1 (framework 8.1.7), Doctrine ORM 3, MariaDB 10.11.19, Messenger with a worker, Mailpit;
React 19.2.0, TypeScript, Vite 8, Tailwind CSS v4, shadcn/ui, TanStack Query and Table, react-i18next. Tests: PHPUnit
13, Vitest, Playwright.

## Architecture

- **Backend** (`backend/src/<Context>/{Domain,Application,Infrastructure,UI}`): DDD and hexagonal layers, enforced by
  Deptrac (`deptrac.yaml` for layers, `deptrac.contexts.yaml` for which context may call which). Contexts: Shared,
  Access, Customer, Timeline, Lead, Deal, Appointment, Inventory, Messaging, Compliance, Worklist, Reporting,
  DataImport, Audit. Writes go through the command bus (one transaction, handlers never flush); domain events are
  delivered after the commit.
- **Tenancy:** every record belongs to a dealership (rooftop). Repositories load through the signed-in user's
  dealership, so another dealership's id answers 404 and its rows never appear.
- **Frontend** (`backend/assets/react`): Feature-Sliced Design (app, pages, widgets, features, entities, shared),
  enforced by ESLint. Types come from `assets/types/api.d.ts`, generated from the API schema.

## API reference

JSON under `/api/v1`, `snake_case`. Errors are `{error, message, detail}`; validation errors are 422
`{error: "validation", violations: [{field, code, message}]}`. Every route needs a session except health and sign-in.
"501 until" marks a route fixed by the contract whose behaviour lands with that item of the MVP split.

| Route | What it does | Responses | Status |
|---|---|---|---|
| `POST /api/v1/auth/login` | Sign in with email and password; sets the session cookie. Handled by the firewall (json_login) | 200, 401, 429 | Built |
| `POST /api/v1/auth/logout` | Sign out; ends the session. Handled by the firewall's logout | 204 | Built |
| `GET /api/v1/me` | The signed-in user, their dealership and their roles | 200 | Built |
| `PATCH /api/v1/me/preferences` | Change the signed-in user's preferences (the colour theme) | 200, 422 | Built |
| `POST /api/v1/auth/mfa/enroll` | Start TOTP enrollment: a new secret and its QR code | 200, 409 | 501 until "mfa" |
| `POST /api/v1/auth/mfa/confirm` | Confirm enrollment with a code from the app; returns the recovery codes once | 200, 422 | 501 until "mfa" |
| `POST /api/v1/auth/mfa/verify` | Finish sign-in with a TOTP code or a recovery code | 200, 422, 429 | 501 until "mfa" |
| `DELETE /api/v1/users/{id}/mfa` | Reset a user's MFA so they enroll again at next sign-in (admin) | 204, 404 | 501 until "mfa" |
| `GET /api/v1/users` | The dealership's users, by name. Every signed-in user may read it (to pick an assignee) | 200 | Built |
| `POST /api/v1/users` | Create a user (admin) | 201, 409, 422 | 501 until "users-admin" |
| `GET /api/v1/users/{id}` | One user | 200, 404 | Built |
| `PATCH /api/v1/users/{id}` | Change a user's name or role (admin) | 200, 404, 409, 422 | 501 until "users-admin" |
| `POST /api/v1/users/{id}/deactivate` | Deactivate a user; they can no longer sign in (admin) | 200, 404, 409 | 501 until "users-admin" |
| `POST /api/v1/users/{id}/activate` | Reactivate a user (admin) | 200, 404 | 501 until "users-admin" |
| `GET /api/v1/appointments` | Appointments in a period | 200 | 501 until "appointments" |
| `POST /api/v1/appointments` | Book an appointment | 201, 404, 409, 422 | 501 until "appointments" |
| `GET /api/v1/appointments/{id}` | One appointment | 200, 404 | 501 until "appointments" |
| `PATCH /api/v1/appointments/{id}` | Reschedule or reassign an appointment | 200, 404, 409, 422 | 501 until "appointments" |
| `POST /api/v1/appointments/{id}/confirm` | Mark an appointment confirmed | 200, 404, 409 | 501 until "appointments" |
| `POST /api/v1/appointments/{id}/show` | Mark that the customer showed | 200, 404, 409 | 501 until "appointments" |
| `POST /api/v1/appointments/{id}/no-show` | Mark a no-show | 200, 404, 409 | 501 until "appointments" |
| `POST /api/v1/appointments/{id}/cancel` | Cancel an appointment | 200, 404, 409 | 501 until "appointments" |
| `GET /api/v1/audit` | Changes and customer record access (admin) | 200 | 501 until "audit-log" |
| `GET /api/v1/customers/{id}/consents` | A customer's consent records and the state per channel | 200, 404 | 501 until "compliance" |
| `POST /api/v1/customers/{id}/consents` | Record consent | 201, 404, 422 | 501 until "compliance" |
| `POST /api/v1/consents/{id}/revoke` | Revoke consent | 200, 404, 409 | 501 until "compliance" |
| `GET /api/v1/compliance/suppressions` | Contacts that opted out, across every dealership | 200 | 501 until "compliance" |
| `GET /api/v1/customers` | The dealership's customers, most recently updated first; q matches a name, phone or email | 200 | Built |
| `POST /api/v1/customers` | Create a customer | 201, 409, 422 | 501 until "customers" |
| `GET /api/v1/customers/lookup` | Customers matching a phone or email, so a form can offer the existing record instead of a duplicate | 200 | Built |
| `GET /api/v1/customers/{id}` | One customer with contacts, owned vehicles, team, open deal and consent per channel | 200, 404 | Built |
| `PATCH /api/v1/customers/{id}` | Change a customer's name or preferred channel | 200, 404, 422 | 501 until "customers" |
| `POST /api/v1/customers/{id}/contacts` | Add a phone number or email | 201, 404, 409, 422 | 501 until "customers" |
| `DELETE /api/v1/customers/{id}/contacts/{contactId}` | Remove a phone number or email | 200, 404 | 501 until "customers" |
| `PATCH /api/v1/customers/{id}/contacts/{contactId}` | Change a phone number or email | 200, 404, 409, 422 | 501 until "customers" |
| `POST /api/v1/customers/{id}/vehicles-owned` | Add a vehicle the customer owns | 201, 404, 422 | 501 until "customers" |
| `DELETE /api/v1/customers/{id}/vehicles-owned/{vehicleId}` | Remove a vehicle the customer owns | 200, 404 | 501 until "customers" |
| `PATCH /api/v1/customers/{id}/assignments` | Assign Sales 1, Sales 2 and BDC | 200, 404, 422 | 501 until "customers" |
| `GET /api/v1/customers/{id}/notes` | The notes on a customer, newest first | 200, 404 | 501 until "customers" |
| `POST /api/v1/customers/{id}/notes` | Add a note | 201, 404, 422 | 501 until "customers" |
| `POST /api/v1/customers/{id}/archive` | Archive a customer | 200, 404 | 501 until "customers" |
| `GET /api/v1/imports` | Spreadsheet imports, newest first | 200 | 501 until "data-import" |
| `POST /api/v1/imports` | Upload customers or leads from a CSV (manager) | 201, 413, 422 | 501 until "data-import" |
| `GET /api/v1/imports/{id}` | Progress and errors of an import | 200, 404 | 501 until "data-import" |
| `GET /api/v1/imports/template/{type}.csv` | A sample CSV with the expected columns | 200, 404 | 501 until "data-import" |
| `GET /api/v1/deals` | Deals | 200 | 501 until "deals" |
| `POST /api/v1/deals` | Open a deal for a customer | 201, 404, 409, 422 | 501 until "deals" |
| `GET /api/v1/deals/{id}` | One deal | 200, 404 | 501 until "deals" |
| `POST /api/v1/deals/{id}/stage` | Move a deal to another stage | 200, 404, 409, 422 | 501 until "deals" |
| `PATCH /api/v1/deals/{id}/vehicle` | Set the vehicle of interest | 200, 404, 422 | 501 until "deals" |
| `PATCH /api/v1/deals/{id}/trade-in` | Set the trade-in | 200, 404, 422 | 501 until "deals" |
| `PATCH /api/v1/deals/{id}/assignee` | Assign a deal to a rep ("Assign to me") | 200, 404, 422 | 501 until "deals" |
| `GET /api/v1/vehicles` | Search inventory | 200 | 501 until "inventory" |
| `POST /api/v1/vehicles` | Add a vehicle | 201, 409, 422 | 501 until "inventory" |
| `GET /api/v1/vehicles/facets` | Filter values with counts | 200 | 501 until "inventory" |
| `GET /api/v1/vehicles/{id}` | One vehicle | 200, 404 | 501 until "inventory" |
| `PATCH /api/v1/vehicles/{id}` | Change a vehicle | 200, 404, 422 | 501 until "inventory" |
| `POST /api/v1/vehicles/{id}/status` | Change a vehicle's status | 200, 404, 422 | 501 until "inventory" |
| `POST /api/v1/vehicles/import` | Upload vehicles from a CSV (processed by the worker) | 201, 413, 422 | 501 until "inventory" |
| `GET /api/v1/vehicles/import/{id}` | Progress and errors of a vehicles upload | 200, 404 | 501 until "inventory" |
| `GET /api/v1/leads` | Leads, newest first | 200 | 501 until "leads" |
| `POST /api/v1/leads` | Capture a lead: match or create the customer, route it to a rep | 201, 422 | 501 until "leads" |
| `GET /api/v1/leads/{id}` | One lead | 200, 404 | 501 until "leads" |
| `PATCH /api/v1/leads/{id}/assignee` | Assign a lead to a rep | 200, 404, 422 | 501 until "leads" |
| `POST /api/v1/leads/{id}/close` | Close a lead | 200, 404, 409 | 501 until "leads" |
| `GET /api/v1/settings/routing` | The round-robin order for new leads (manager) | 200 | 501 until "leads" |
| `PUT /api/v1/settings/routing` | Set the round-robin order (manager) | 200, 422 | 501 until "leads" |
| `GET /api/v1/conversations` | Conversations, most recent first; `folder` all, unreplied (the customer wrote last) or fumbled (unreplied for more than `WORKLIST_FUMBLED_MINUTES`, 30), `user_id` the assigned rep, `q` a name, phone or email | 200, 422 | Built |
| `GET /api/v1/conversations/counts` | Counts per folder, and unreplied conversations per assigned rep | 200 | Built |
| `GET /api/v1/conversations/{id}` | A conversation, its messages oldest first (refused attempts included) and the customer's phone and email | 200, 404 | Built |
| `POST /api/v1/conversations/{id}/read` | Mark a conversation read | 200, 404 | Built |
| `GET /api/v1/customers/{id}/conversation` | The customer's conversation, created if there is none | 200, 404 | Built |
| `POST /api/v1/dev/inbound` | Simulate a customer reply from a customer's phone or email (dev and test only; also `bin/console mustang:messaging:simulate-inbound <phone or email> "<text>" [--channel=email]`) | 201, 422 | Built |
| `POST /api/v1/conversations/{id}/messages` | Send a text (fake transport, sent at once) or an email (queued, then sent by the worker); refused with 409 `consent_missing` or `opted_out`, the attempt kept with its consent snapshot | 201, 404, 409, 422 | Built |
| `GET /api/v1/messages/{id}/provenance` | Who sent a message, under which consent and through which transport | 200, 404 | Built |
| `GET /api/v1/templates` | Message templates | 200 | 501 until "templates" |
| `POST /api/v1/templates` | Create a template (manager) | 201, 422 | 501 until "templates" |
| `GET /api/v1/templates/{id}` | One template | 200, 404 | 501 until "templates" |
| `DELETE /api/v1/templates/{id}` | Delete a template (manager) | 204, 404 | 501 until "templates" |
| `PATCH /api/v1/templates/{id}` | Change a template (manager) | 200, 404, 422 | 501 until "templates" |
| `GET /api/v1/reports/kpis` | KPIs for a period and the one before (manager) | 200, 422 | 501 until "dashboard" |
| `GET /api/v1/health` | Is the app up and its database reachable. The smoke suite also reads which SMS transport is active | 200 | Built |
| `GET /api/v1/customers/{id}/timeline` | Everything that happened with a customer, newest first | 200, 404 | 501 until "timeline" |
| `GET /api/v1/worklist/contact-now` | Who to contact now and why | 200, 403 | 501 until "worklist" |
| `GET /api/v1/worklist/interventions` | Deals that need a manager today (manager) | 200 | 501 until "worklist" |

## Data model decisions

- Ids are UUID v7 strings. Money is integer cents (USD). Times are stored in UTC and shown in the dealership's timezone.
- A lead is captured as a `leads` row and opens a deal in stage `new`; stages are new, engaged, appointment, visit,
  proposal, sold, delivered, lost.
- Email addresses are unique across Mustang, so sign-in needs no dealership picker.
- Records of different aggregates reference each other by id without database foreign keys: the Doctrine mapping owns
  the schema, and a foreign key it does not know about would be dropped by the next generated migration.
- Suppressions (opt-outs) have no dealership: a STOP at one dealership blocks every dealership (FR-12).

## Known gaps

See "Left out on purpose" in [`docs/pdr/prd-mvp.md`](docs/pdr/prd-mvp.md): everything in the baseline's backlog, all
integrations (no SMS carrier, DNC check, lead feeds, DMS or CRM migration), Spanish strings, and security work beyond
the baseline (SOC 2, penetration test). Security policies are drafts to confirm: [`docs/security/policies.md`](docs/security/policies.md).
