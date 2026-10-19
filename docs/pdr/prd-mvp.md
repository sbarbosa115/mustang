# Mustang MVP

<!--
    A PRD for a feature built in parallel items (steps/02b-split.md). Saved as docs/pdr/prd-mvp.md: the feature is
    `mvp`, its base branch feature/mvp. `split.py plan` reads the table under "## Split".
    Requirements: docs/pdr/requirements-baseline.md (the "MVP" table). Design: DESIGN.md and backend/assets/styles/tokens.css.
-->

## What it is for

A franchise dealership (one rooftop per tenant) runs its sales floor on Mustang as its system of record. Salespeople
and BDC agents (`/today`, `/inbox`, `/customers`, `/inventory`, `/leads`, `/deals`, `/appointments`, phone first)
capture leads, work deals and appointments, text and email customers from one inbox, and start each day from a
"contact now" list that says who to reach and why. Managers (`/dashboard`, `/team`, desktop first) see the deals
that need them today and the KPIs that prove the pilot. Admins (`/settings/*`) manage users, templates, round-robin
routing, the spreadsheet import and the audit log. Every send is blocked unless consent is recorded, and STOP revokes
it at once.

## Plan

- **Owning contexts (backend):** `Shared` (kernel), `Access` (dealership, users, roles, session, MFA), `Customer`
  (customers, contacts, owned vehicles, notes, assignments), `Timeline` (the per-customer activity stream, read side
  fed by events), `Lead` (intake, de-duplication, round-robin routing), `Deal` (stages, vehicle of interest,
  trade-in), `Appointment`, `Inventory` (vehicles, CSV upload), `Messaging` (conversations, messages, transports,
  templates), `Compliance` (consent ledger, suppressions, STOP), `Worklist` (contact-now list and intervention
  queue, read side behind a `Prioritizer` port), `Reporting` (KPIs), `DataImport` (customers and open leads from
  CSV), `Audit` (changes and access log).
- **FSD slices (frontend):** `app/` (providers, router, shell), `shared/` (shadcn/ui kit, Mustang components, api
  client, i18n, lib), `entities/` {dealership, user, customer, activity, lead, deal, appointment, vehicle,
  conversation, message, template, consent, worklist-item, intervention, kpi, import-job, audit-entry}, `features/`
  and `widgets/` and `pages/` as listed per item below.
- **Nearest existing feature to copy the shape of:** none; this is a greenfield project. The reference is the
  `symfony-react-app` skill: `steps/01-stack.md`, `steps/04-build.md` for the folder shapes and rules, `templates/`
  for the configs, CI and the smoke-suite starter files. Every project-specific name in those starter files is
  replaced by Mustang's (project, compose project, database, DB user and namespace prefix: `mustang`; seed command
  `mustang:seed-demo`; accounts `*@mustang.test`; health check `GET /api/v1/health`; smoke fixtures
  `backend/fixtures/e2e/`; stand-in switch `MESSAGING_SMS_TRANSPORT=fake`).
- **Left out on purpose:** see "Left out on purpose" at the end (goes to the README's "Known gaps").

## Contract (item 0)

Built by the coordinator on `feature/mvp` in `../mustang-mvp`, alone, before any item starts. It stands up the whole
baseline the skill expects (there is no app yet) and fixes every shared thing the items build against.

### Stack (Decisions hold the versions)

`docker-compose.yml` with `php`, `nginx` (`${HTTP_PORT:-8080}`), `database` (`${DB_PORT:-3306}`), `worker`,
`node`, `mailpit` (`${MAILPIT_PORT:-8025}`), `e2e` (profile); `docker/php/Dockerfile`, `docker/nginx/default.conf`,
`docker/mariadb/init/10-test-database.sql`, `docker/node/start.sh`; root `.env.example`; `backend/` holds the Symfony
app and the React app (Vite builds `assets/react` into `public/build`; `node` runs `vite build --watch`). Tool
configs from the skill's templates: `.php-cs-fixer.dist.php`, `phpstan.dist.neon` (level 6), `deptrac.yaml` +
`deptrac.contexts.yaml`, `eslint.config.mjs` (Google rules + FSD boundaries, prettier last), `.prettierrc.json`,
`.prettierignore`, `.editorconfig`, `tsconfig.json` (strict), `vitest.config.mts`, `playwright.config.ts`,
`.github/workflows/ci.yml`. `README.md`, `CLAUDE.md`, `docs/security/README.md`, `docs/security/policies.md`.

### Tenancy, roles, auth

- Every table but `dealerships`, `suppressions` and `messenger_messages` has `dealership_id` (indexed first).
  Repositories load through the current dealership; a record of another dealership is `NotFound` (HTTP 404).
- Roles: `sales`, `bdc`, `manager`, `admin` (`role_hierarchy`: admin ⊃ manager ⊃ sales, bdc). Access rules in
  `security.yaml`: `/api/v1/reports/**`, `/api/v1/worklist/interventions`, `/api/v1/settings/routing` need
  `ROLE_MANAGER`; `/api/v1/users` writes, `/api/v1/audit`, `/api/v1/imports` writes need `ROLE_ADMIN` (imports also
  manager); `/api/v1/templates` writes need `ROLE_MANAGER`. Everything else: any signed-in user of the dealership.
  Reps see every customer of their rooftop; assignment drives lists and queues, not access.
- Session cookie (`lax`, `httponly`), `json_login` at `POST /api/v1/auth/login` with a 5/min rate limit per IP and
  email, same-origin check on every state-changing `/api` request, generic error on wrong credentials.
- Seed accounts (`mustang:seed-demo`, dev only, password `Mustang-dev-1`, TOTP secret `MUSTANGDEVTOTPSECRET234567ABCDEF`
  for every seed user): dealership "Mustang Motors Tampa" with `admin@mustang.test`, `manager@mustang.test`,
  `bdc@mustang.test`, `sales@mustang.test`, `sales2@mustang.test`; dealership "Other Motors" with
  `other@mustang.test` (admin). `tests/Support/ApiTestCase::signInAs(role, dealership)` uses `loginUser()`.

### Entities and the migration (`migrations/Version<date>Mvp.php`, dev and test)

| Context | Table | Columns beyond `id` (UUID v7), `dealership_id`, `created_at`, `updated_at` |
|---|---|---|
| Access | `dealerships` | name, timezone, average_gross_cents |
| Access | `users` | email (unique per dealership), password_hash, first_name, last_name, role, active, theme, last_login_at |
| Access | `mfa_credentials` | user_id (unique), secret_encrypted, confirmed_at, recovery_codes_json |
| Customer | `customers` | first_name, last_name, preferred_channel, sales1_user_id, sales2_user_id, bdc_user_id, source, imported_at, archived_at |
| Customer | `customer_contacts` | customer_id, kind (phone, email), value (E.164 or lowercase email), label, is_primary; index (dealership_id, kind, value) |
| Customer | `customer_owned_vehicles` | customer_id, year, make, model, trim, vin, purchased_at |
| Customer | `customer_notes` | customer_id, author_user_id, body |
| Timeline | `activities` | customer_id, kind (lead, message_in, message_out, stage, appointment, note, consent, import), occurred_at, summary, ref_type, ref_id, actor_user_id; index (dealership_id, customer_id, occurred_at) |
| Lead | `leads` | customer_id, source (website, phone, walk_in, manual, import, other), channel_note, status (new, assigned, contacted, closed), assigned_user_id, vehicle_interest_text, received_at, first_contacted_at |
| Lead | `routing_rotations` | user_ids_json (ordered), next_index (one row per dealership) |
| Deal | `deals` | customer_id, lead_id, assigned_user_id, stage (new, engaged, appointment, visit, proposal, sold, delivered, lost), stage_entered_at, vehicle_id, vehicle_text, trade_in_json {year, make, model, mileage, payoff_cents}, expected_gross_cents, lost_reason, engaged_at, appointment_at, visited_at, proposal_at, sold_at, delivered_at, lost_at, last_activity_at |
| Appointment | `appointments` | customer_id, deal_id, user_id, scheduled_at (UTC), duration_minutes, kind (visit, test_drive, delivery), status (scheduled, confirmed, shown, no_show, cancelled), confirmed_at, shown_at, note, reminder_sent_at |
| Inventory | `vehicles` | stock_number (unique per dealership), vin, year, make, model, trim, body, drivetrain, fuel, exterior_color, interior_color, mileage, condition (new, used, cpo), price_cents, status (available, hold, sold, in_transit), photo_url, description; indexes on (dealership_id, status), (dealership_id, make, model), (dealership_id, price_cents) |
| Inventory | `vehicle_imports` | uploaded_by_user_id, file_name, status, rows_total, rows_ok, rows_failed, errors_json |
| Messaging | `conversations` | customer_id (unique per dealership), assigned_user_id, last_message_at, last_inbound_at, last_outbound_at, unread_count |
| Messaging | `messages` | conversation_id, direction (in, out), channel (text, email), to_value, from_value, subject, body, status (queued, sent, failed, received), sent_by_user_id, template_id, consent_id, consent_state_snapshot, transport, transport_message_id, failed_reason, sent_at, received_at |
| Messaging | `message_templates` | name, channel, subject, body, active, created_by_user_id |
| Compliance | `consents` | customer_id, contact_value, channel (text, email, call), state (granted, revoked), source, captured_by_user_id, captured_where, captured_at, wording, campaign, revoked_at, revoked_reason |
| Compliance | `suppressions` | (no dealership_id) contact_value, channel, reason, created_at; unique (contact_value, channel) |
| DataImport | `import_jobs` | type (customers, leads), uploaded_by_user_id, file_path, status (pending, processing, done, failed), rows_total, rows_created, rows_updated, rows_skipped, rows_failed, errors_json, finished_at |
| Audit | `audit_entries` | actor_user_id, action (create, update, delete, read), entity_type, entity_id, customer_id, changes_json, request_id, ip, occurred_at; index (dealership_id, occurred_at), (dealership_id, entity_type, entity_id) |

Plus `messenger_messages` (Doctrine transport). Money in cents (int). Dates in UTC, shown in the dealership timezone.

### Ports, queries and events item 0 declares

- Repositories (`<Context>/Domain/Repository/<Noun>Repository`, Doctrine adapters in `Infrastructure/Persistence`):
  one per table above except `activities`, `suppressions`, `audit_entries` (write-only appenders named
  `ActivityLog`, `SuppressionList`, `AuditLog`).
- Queries (`<Context>/Application/Query/<Noun>Queries`): `CustomerQueries` (page, byId, `findByContact(phone|email)`),
  `UserQueries`, `LeadQueries`, `DealQueries`, `AppointmentQueries`, `VehicleQueries`, `ConversationQueries`,
  `TemplateQueries`, `ConsentQueries` (`stateFor(contactValue, channel)` and `isSuppressed`), `ActivityQueries`,
  `WorklistQueries`, `InterventionQueries`, `KpiQueries`, `ImportJobQueries`, `AuditQueries`. Item 0 implements
  `CustomerQueries` and `UserQueries`; every other query returns a fixed empty page until its item lands.
- Ports: `Messaging/Application/Port/MessageTransport` (`send(OutboundMessage): TransportReceipt`; adapters
  `MailerEmailTransport`, `FakeSmsTransport` keyed by `MESSAGING_SMS_TRANSPORT`), `Messaging/Application/Port/
  ConsentCheck` (adapter over `ConsentQueries`), `Lead/Application/Port/CustomerLookup` (adapter over
  `CustomerQueries`), `Lead/Application/Port/Router` (`RoundRobinRouter`), `Worklist/Application/Port/Prioritizer`
  (`RuleBasedPrioritizer`), `Shared/Application/Port/{Clock, Encrypter}`.
- Domain events (`<Context>/Domain/Event`): `LeadCaptured`, `LeadAssigned`, `DealOpened`, `DealStageChanged`,
  `DealAssigned`, `AppointmentBooked`, `AppointmentConfirmed`, `AppointmentShown`, `AppointmentNoShow`,
  `AppointmentCancelled`, `MessageSent`, `MessageFailed`, `InboundMessageReceived` (carries contact value, channel,
  body), `ConsentRecorded`, `ConsentRevoked`, `NoteAdded`, `CustomerViewed`, `ImportFinished`. The event bus runs
  with `allow_no_handlers: true`, so no no-op handlers are needed; the owning items add the handlers.
- Seeding: `mustang:seed-demo` runs every service tagged `mustang.seeder` (priority order); item 0 ships
  `DealershipSeeder`, `UserSeeder`, `CustomerSeeder`.

### Endpoints (all under `/api/v1`, JSON `snake_case`, Output DTO named; 501 until the owning item lands)

| Area | Endpoints | Output DTOs | Owner |
|---|---|---|---|
| Health | GET /health | HealthOutput | 0 |
| Auth | POST /auth/login, POST /auth/logout, GET /me, PATCH /me/preferences | LoginOutput {next: ok, mfa, enroll; user}, MeOutput {user, dealership, roles} | 0 |
| MFA | POST /auth/mfa/enroll, POST /auth/mfa/confirm, POST /auth/mfa/verify, DELETE /users/{id}/mfa | MfaEnrollmentOutput {secret, otpauth_url, qr_svg}, MfaStatusOutput | 1 |
| Users | GET /users, GET /users/{id} (0); POST /users, PATCH /users/{id}, POST /users/{id}/deactivate, POST /users/{id}/activate | UserOutput | 0, 11 |
| Customers | GET /customers, GET /customers/{id}, GET /customers/lookup?phone&email (0); POST /customers, PATCH /customers/{id}, POST /customers/{id}/contacts, PATCH and DELETE /customers/{id}/contacts/{cid}, POST and DELETE /customers/{id}/vehicles-owned/{vid}, PATCH /customers/{id}/assignments, POST /customers/{id}/notes, POST /customers/{id}/archive | CustomerOutput {contacts, owned_vehicles, assignments, open_deal, consent_summary}, CustomerListItemOutput, NoteOutput | 0, 2 |
| Timeline | GET /customers/{id}/timeline | ActivityOutput | 13 |
| Leads | GET /leads, POST /leads, GET /leads/{id}, PATCH /leads/{id}/assignee, POST /leads/{id}/close, GET and PUT /settings/routing | LeadOutput, RoutingOutput | 5 |
| Deals | GET /deals, POST /deals, GET /deals/{id}, POST /deals/{id}/stage, PATCH /deals/{id}/vehicle, PATCH /deals/{id}/trade-in, PATCH /deals/{id}/assignee | DealOutput {stage, days_in_stage, vehicle, trade_in, customer, assignee} | 6 |
| Appointments | GET /appointments?from&to&user_id, POST /appointments, GET and PATCH /appointments/{id}, POST /appointments/{id}/confirm, /show, /no-show, /cancel | AppointmentOutput | 9 |
| Inventory | GET /vehicles (filters: q, status, condition, make, model, year_min, year_max, price_min, price_max, mileage_max, color, body, drivetrain, fuel, sort), POST /vehicles, GET and PATCH /vehicles/{id}, POST /vehicles/{id}/status, POST /vehicles/import (CSV), GET /vehicles/import/{id}, GET /vehicles/facets | VehicleOutput, VehicleImportOutput, VehicleFacetsOutput | 4 |
| Messaging | GET /conversations?folder=all,unreplied,fumbled&user_id&q, GET /conversations/counts, GET /conversations/{id}, POST /conversations/{id}/messages, POST /conversations/{id}/read, GET /customers/{id}/conversation (find-or-create), GET /messages/{id}/provenance, POST /dev/inbound (dev and test only) | ConversationOutput {customer, last_message, unreplied, fumbled, assignee, consent: {text, email}}, ConversationCountsOutput, MessageOutput, ProvenanceOutput | 3 |
| Templates | GET /templates, POST /templates, GET, PATCH, DELETE /templates/{id} | TemplateOutput | 8 |
| Compliance | GET /customers/{id}/consents, POST /customers/{id}/consents, POST /consents/{id}/revoke, GET /compliance/suppressions?q | ConsentOutput, SuppressionOutput | 7 |
| Worklist | GET /worklist/contact-now?user_id, GET /worklist/interventions | WorklistItemOutput {customer, reason_code, reason_params, score, suggested_channel, deal, appointment, channel_availability}, InterventionOutput {deal, reason_code, reason_params, assignee, at_risk_cents}, InterventionSummaryOutput {at_risk_cents, reps_with_unanswered} | 14 |
| Reports | GET /reports/kpis?from&to | KpiReportOutput {period, previous, kpis: [{code, value, previous_value, unit}], funnel} | 15 |
| Imports | POST /imports (type, file), GET /imports, GET /imports/{id}, GET /imports/template/{type}.csv | ImportJobOutput | 12 |
| Audit | GET /audit?entity_type&entity_id&user_id&from&to | AuditEntryOutput | 16 |

After the stubs: `nelmio:apidoc:dump --format=json > assets/types/openapi.json`, `npm run api:types`; both committed.

### Frontend contract

- `app/`: providers (query, i18n, theme with `.dark` on `<html>` from `me.theme`, auth), lazy router, shell (desktop
  dark sidebar rail + top bar with ⌘K search and "Add customer"; tablet collapsible; phone bottom tabs Today, Inbox,
  Customers, Inventory, More), `pages/login`, `pages/settings` (profile, theme), `pages/not-found`, and a placeholder
  page per route (an `EmptyState` naming the item) so the shell is navigable from day one.
- `shared/ui/` = shadcn/ui components installed from DESIGN.md's list, themed only by `backend/assets/styles/tokens.css`;
  `shared/ui/mustang/` = `StageBadge`, `ChannelActions`, `ConsentIndicator`, `MessageBubble`, `PriorityItem`,
  `InterventionItem`, `KpiTile`, `VehicleCard`, `EmptyState`, `RelativeTime`, `DataTable`; `shared/api/client.ts`,
  `shared/api/schema.ts` (`Schema<'XOutput'>`), `shared/i18n`, `shared/lib/{dates,phone,money,cn}`,
  `shared/test/setup.ts`.
- `entities/<x>/` for every Output DTO: `api/` (typed functions per endpoint, TanStack Query keys), `model/`
  (mappers, formatters, enums with labels), `ui/` (small presentational pieces: `CustomerPanel`, `DealSummary`,
  `AppointmentChip`, `TemplatePicker` list, `ConsentIndicator` wiring). `entities/template/lib/render.ts` fills
  `{first_name}`, `{last_name}`, `{rep_name}`, `{dealership}`, `{vehicle}`, `{appointment_time}`.
- Items own only `features/`, `widgets/`, `pages/` slices named in their row, their i18n namespace files, their
  `e2e/<area>.spec.ts`, their README API rows and their seeder. A change an item needs in `entities/`, `shared/`,
  `app/`, the schema or the migration goes to the coordinator as a contract item `0b`.

### i18n namespaces (`assets/react/shared/i18n/locales/en/<ns>.json`, English only)

`common`, `shell`, `auth`, `settings` (0); `auth` MFA keys under `auth.mfa.*` (1); `customers` (2); `inventory` (4);
`leads` (5); `deals` (6); `consent` (7); `templates` (8); `appointments` (9); `inbox` (10); `users` (11);
`imports` (12); `timeline` (13); `today` and `team` (14); `reports` (15); `audit` (16). Item 3 has no UI strings
beyond `inbox.transport.*` placeholders it leaves for item 10.

### Regression suite sections (`docs/tests/ui-regression.md`) and smoke specs

| Section | Prefix and owner |
|---|---|
| 1. Public | PUB-01 (0) |
| 2. Authentication | AUTH-01 – 03 (0), AUTH-04 – 08 (1) |
| 3. App shell and theme | SHELL-01 – 06 (0) |
| 4. Today: contact now | TODAY-01 – 05 (14) |
| 5. Inbox | MSG-01 – 04 (3, console and dev endpoint), INB-01 – 08 (10) |
| 6. Customers | CUS-01 – 08 (2), TL-01 – 04 (13), CONS-01 – 06 (7) |
| 7. Leads | LEAD-01 – 06 (5) |
| 8. Deals | DEAL-01 – 07 (6) |
| 9. Appointments | APP-01 – 06 (9) |
| 10. Inventory | INV-01 – 06 (4) |
| 11. Manager: team and dashboard | MGR-01 – 05 (14), KPI-01 – 04 (15) |
| 12. Settings | USR-01 – 05 (11), TPL-01 – 04 (8), IMP-01 – 05 (12), AUD-01 – 04 (16) |
| Emails | MAIL-01 (0) |

Specs: `e2e/public.spec.ts`, `auth.spec.ts`, `shell.spec.ts` (0) and one `e2e/<area>.spec.ts` per item. The suite
runs on the `phone` project (390×844); manager specs set a 1280×800 viewport with `test.use`.

## Split

| # | Slug | Item | Owns (context / slice, files) | Tests first | Browser cases | Depends on | Model |
|---|---|---|---|---|---|---|---|
| 0 | contract | Stack, skeletons, design system, shell, auth and tenancy, all entities and the migration, ports, DTOs, stubs, types, i18n namespaces, suite sections | docker-compose.yml, docker/, backend/ skeleton and tool configs, Shared, Access (login, session, roles, voters, users read), Customer reads, every Domain/Model and Domain/Event, migrations/Version*Mvp.php, every Repository port and Doctrine adapter, every Query interface, every Input and Output DTO and 501 controller, openapi.json and api.d.ts, app/, shared/, entities/*, pages/login, pages/settings, placeholder pages, locales skeleton, docs/tests/ui-regression.md sections, e2e/support, README, CLAUDE.md, docs/security, ci.yml | PhoneNumberTest, EmailTest, MoneyTest, LoginApiTest, MeApiTest, UnauthenticatedRoutesTest, CustomerReadApiTest (other dealership 404, q escaping, pagination), UserReadApiTest, StubShapesTest, mustang components render tests, shell tabs per role, theme switch | PUB-01, AUTH-01 – 03, SHELL-01 – 06, MAIL-01 | — | — |
| 1 | mfa | TOTP enrollment and verification on login, recovery codes, admin reset | Access: Application/Mfa (EnrollMfa, ConfirmMfa, VerifyMfa, ResetMfa), Infrastructure/Totp, UI/Http/Controller/MfaController, security step (MfaRequiredSubscriber: a session without a verified factor reaches only /auth/*); pages/login step 2 and enroll screen, features/mfa-enroll, features/mfa-verify, pages/settings Security tab, e2e/support/test.ts TOTP step, locales auth.mfa.* | MfaEnrollmentTest (secret encrypted at rest, confirm with a valid code, wrong code 422, replay refused), MfaLoginTest (login answers enroll then mfa, protected route 403 until verified, recovery code once), MfaResetApiTest (admin only, other dealership 404), MfaVerifyForm.test.tsx | AUTH-04 – 08 | 0 | opus |
| 2 | customers | Customer records: list, create, edit, contacts, owned vehicles, assignments, notes, archive | Customer: Application/Command (CreateCustomer, UpdateCustomer, AddContact, UpdateContact, RemoveContact, AddOwnedVehicle, RemoveOwnedVehicle, AssignTeam, AddNote, ArchiveCustomer), UI/Http/Controller/CustomerWriteController; features/create-customer, features/edit-customer, features/manage-contacts, features/assign-team, features/add-note, widgets/customer-list, pages/customers, pages/customer-detail (tabs: Overview, Timeline slot filled by item 13, Deals, Appointments), locales customers, e2e/customers.spec.ts, CustomerSeeder additions | CustomerWriteApiTest (create with phone normalised, duplicate contact 409, update, contacts CRUD, assignments by role, note, archive, other dealership 404, 422 messages), CustomerForm.test.tsx, CustomerList.test.tsx (loading, empty, filtered to nothing) | CUS-01 – 08 | 0 | sonnet |
| 3 | messaging-api | Conversations and the send pipeline: consent check, transports, provenance, inbound simulation, unreplied and fumbled flags | Messaging: Application/Command (SendMessage, ReceiveInboundMessage, MarkConversationRead), Application/Query/DoctrineConversationQueries, Application/EventHandler (none), Infrastructure/Transport (MailerEmailTransport queued on the mail bus, FakeSmsTransport), Infrastructure/Persistence, UI/Http/Controller/ConversationController, MessageController, DevInboundController (dev and test only), UI/Cli/SimulateInboundCommand (mustang:messaging:simulate-inbound), MessagingSeeder, README rows | SendMessageTest (text sent through the fake transport with provenance, email queued then sent and lands in Mailpit, blocked 409 consent_missing and 409 opted_out with the consent snapshot, template_id recorded), InboundMessageTest (finds or creates the conversation by contact, raises InboundMessageReceived, unread count), ConversationQueriesTest (folders, per-rep counts, fumbled after 30 min, other dealership sees nothing), ProvenanceApiTest | MSG-01 – 04 | 0 | opus |
| 4 | inventory | Vehicles: manual entry, CSV upload, filters and facets, status | Inventory: Application/Command (AddVehicle, UpdateVehicle, ChangeVehicleStatus, ImportVehiclesCsv on the async queue), Application/Query/DoctrineVehicleQueries, Infrastructure/Csv/VehicleCsvReader, UI/Http/Controller/VehicleController, VehicleImportController; features/add-vehicle, features/edit-vehicle, features/upload-vehicles-csv, features/filter-inventory, widgets/inventory-grid, pages/inventory, pages/vehicle-detail, locales inventory, e2e/inventory.spec.ts, VehicleSeeder, README rows | VehicleApiTest (create, stock number unique 409, filters and sort, facets, pagination, other dealership 404), VehicleCsvImportTest (sample file, bad rows reported, job status), InventoryFilters.test.tsx, VehicleForm.test.tsx | INV-01 – 06 | 0 | sonnet |
| 5 | leads | Lead capture (manual, phone, walk-in), de-duplication, round-robin routing, routing settings | Lead: Application/Command (CaptureLead, AssignLead, CloseLead, UpdateRouting), Application/Query/DoctrineLeadQueries, Infrastructure/Routing/RoundRobinRouter, Infrastructure/Adapter/CustomerLookupAdapter, UI/Http/Controller/LeadController, RoutingController; features/capture-lead (also from the top bar), features/assign-lead, widgets/lead-list, pages/leads, pages/lead-detail, pages/settings/routing, locales leads, e2e/leads.spec.ts, LeadSeeder, README rows | LeadCaptureTest (new customer created, existing matched by phone or email, LeadCaptured raised, source required 422), RoundRobinRouterTest (order, skips inactive, wraps, explicit assignee bypasses), RoutingSettingsTest (manager only), LeadApiTest (other dealership 404), CaptureLeadForm.test.tsx | LEAD-01 – 06 | 2 | opus |
| 6 | deals | Deals: stage machine, vehicle of interest, trade-in, days in stage, open on lead, auto-advance on events | Deal: Domain/Model/Deal transitions, Application/Command (OpenDeal, ChangeStage, AttachVehicle, SetTradeIn, AssignDeal), Application/EventHandler (OpenDealWhenLeadCaptured, EngageWhenMessageExchanged, AdvanceWhenAppointmentBooked, AdvanceWhenAppointmentShown), Application/Query/DoctrineDealQueries, UI/Http/Controller/DealController; features/change-deal-stage, features/attach-vehicle, features/set-trade-in, features/assign-deal, features/create-deal, widgets/deal-board, pages/deals, pages/deal-detail, locales deals, e2e/deals.spec.ts, DealSeeder, README rows | DealStageTest (every allowed transition, lost needs a reason, reopen, timestamps per stage, days in stage), DealEventHandlersTest, DealApiTest (create for a customer, 422 and 409 refusals, assignee, other dealership 404), DealBoard.test.tsx, StageDialog.test.tsx | DEAL-01 – 07 | 2, 4 | opus |
| 7 | compliance | Consent ledger per contact and channel, revocation, STOP and START keywords, global suppression, send check | Compliance: Application/Command (RecordConsent, RevokeConsent, ApplyKeyword), Application/EventHandler/RevokeOnStopKeyword (on InboundMessageReceived), Application/Query/DoctrineConsentQueries, Infrastructure/Persistence, UI/Http/Controller/ConsentController, SuppressionController; features/record-consent, features/revoke-consent, pages/customer-consent (/customers/:id/consent), pages/settings/suppressions, locales consent, e2e/consent.spec.ts, ConsentSeeder, README rows | ConsentLedgerTest (record with source, who, where, when, wording, campaign; revoke; state per channel), StopKeywordTest (STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT revoke and suppress across dealerships; START lifts the suppression and records consent with source sms_keyword), ConsentCheckAdapterTest (missing, granted, revoked, suppressed), ConsentApiTest (other dealership 404), ConsentForm.test.tsx | CONS-01 – 06 | 3 | opus |
| 8 | templates | Text and email templates with placeholders | Messaging: Application/Command (CreateTemplate, UpdateTemplate, DeleteTemplate), Application/Query/DoctrineTemplateQueries, UI/Http/Controller/TemplateController; features/manage-template, widgets/template-list, pages/settings/templates, locales templates, e2e/templates.spec.ts, TemplateSeeder (appointment confirmation, first reply, vehicle match), README rows | TemplateApiTest (CRUD, manager only for writes, unknown placeholder 422, other dealership 404), TemplateForm.test.tsx (placeholder helper, preview with render()) | TPL-01 – 04 | 3 | sonnet |
| 9 | appointments | Appointments: book, confirm, reschedule, cancel, shown and no-show, calendar, reminder email to the rep | Appointment: Application/Command (BookAppointment, ConfirmAppointment, RescheduleAppointment, CancelAppointment, MarkShown, MarkNoShow), Application/EventHandler/ScheduleReminder (delayed Messenger message, email to the rep 60 min before), Application/Query/DoctrineAppointmentQueries, UI/Http/Controller/AppointmentController; features/book-appointment, features/confirm-appointment, features/mark-shown, features/reschedule-appointment, widgets/appointment-calendar (day and week), pages/appointments, locales appointments, e2e/appointments.spec.ts, AppointmentSeeder, README rows | AppointmentApiTest (book in the dealership timezone, past date 422, overlap for the same rep 409, confirm, show, no-show, cancel, other dealership 404), ReminderTest (email in Mailpit, not sent after cancel), Calendar.test.tsx, BookAppointmentForm.test.tsx | APP-01 – 06 | 6 | sonnet |
| 10 | inbox-ui | The inbox: folders with counts, per-rep filter, thread, composer with channel, consent check, template picker, vehicle card, dev inbound simulator | widgets/conversation-list, widgets/conversation-thread, features/send-message (Composer), features/pick-template, features/send-vehicle, features/filter-conversations, features/simulate-inbound (dev only, shown when the API exposes /dev/inbound), pages/inbox, pages/inbox-thread (phone full screen), locales inbox, e2e/inbox.spec.ts | ConversationList.test.tsx (folders, counts, loading, empty), Thread.test.tsx (bubbles by direction and channel, day groups), Composer.test.tsx (send disabled with the reason when consent is missing or revoked, channel switch, template fills placeholders, error by code shown inline), SimulateInbound.test.tsx | INB-01 – 08 | 3, 7, 8 | opus |
| 11 | users-admin | Users and roles: create, edit, role, activate and deactivate, MFA reset button | Access: Application/Command (CreateUser, UpdateUser, ChangeRole, DeactivateUser, ActivateUser), UI/Http/Controller/UserWriteController; features/create-user, features/edit-user, features/toggle-user-active, widgets/user-table, pages/settings/users, locales users, e2e/users.spec.ts, README rows | UserWriteApiTest (admin only, email unique per dealership 409, a deactivated user cannot sign in, the last admin cannot be deactivated, other dealership 404), UserForm.test.tsx | USR-01 – 05 | 1 | sonnet |
| 12 | data-import | Customers and open leads from CSV: upload, queued processing, de-duplication, consent columns, row errors, sample files | DataImport: Domain/Model/ImportJob, Application/Command (StartImport, ProcessImport on the async queue), Infrastructure/Csv/{CustomerCsvReader, LeadCsvReader}, Infrastructure/Adapter (calls Customer, Lead and Compliance commands through the command bus), Application/Query/DoctrineImportJobQueries, UI/Http/Controller/ImportController; features/upload-import, widgets/import-status, pages/settings/import, locales imports, e2e/imports.spec.ts, README rows | ImportCustomersTest (sample file, phone normalised, duplicates updated not duplicated, bad rows in errors_json, consent recorded with source import when sms_consent is yes), ImportLeadsTest (lead and deal opened per row, rep by email, missing rep goes to round robin), ImportApiTest (admin or manager only, 10 MB limit 413, other dealership 404), ImportStatus.test.tsx | IMP-01 – 05 | 5, 7 | opus |
| 13 | timeline | One timeline per customer from every event, grouped by day | Timeline: Application/EventHandler (one per event: LeadCaptured, MessageSent, InboundMessageReceived, DealStageChanged, AppointmentBooked, AppointmentShown, AppointmentNoShow, AppointmentCancelled, NoteAdded, ConsentRecorded, ConsentRevoked, ImportFinished), Application/Query/DoctrineActivityQueries, Infrastructure/Persistence/DoctrineActivityLog, UI/Http/Controller/TimelineController; widgets/customer-timeline, the Timeline tab of pages/customer-detail, locales timeline, e2e/timeline.spec.ts, README rows | TimelineProjectionTest (each event becomes one activity with the right kind, summary and actor; idempotent on redelivery), TimelineApiTest (ordered, paginated, other dealership 404), Timeline.test.tsx (day groups, channel icons, empty) | TL-01 – 04 | 3, 5, 9 | sonnet |
| 14 | worklist | The "contact now" list for reps and the intervention queue for managers, rule-based with a reason per row | Worklist: Domain/Rule (UnansweredInbound, NewLeadUncontacted, AppointmentTodayUnconfirmed, NoShowWithoutFollowUp, DealIdle, UnansweredQuestion), Application/Port/Prioritizer, Infrastructure/RuleBasedPrioritizer, Application/Query/{DoctrineWorklistQueries, DoctrineInterventionQueries}, UI/Http/Controller/WorklistController; widgets/contact-now-list (PriorityItem rows, progress), widgets/intervention-queue (InterventionItem rows, summary tiles), features/assign-to-me, pages/today, pages/team, locales today and team, e2e/today.spec.ts, e2e/team.spec.ts, README rows | Each rule's unit test (fires, does not fire, score, reason code and params), PrioritizerTest (one reason per customer, the highest wins, channel suggestion by consent), WorklistApiTest (my rows only, manager can pick a rep, other dealership sees nothing), InterventionApiTest (manager only, at_risk_cents from average gross, reps with unanswered), ContactNowList.test.tsx, InterventionQueue.test.tsx | TODAY-01 – 05, MGR-01 – 05 | 3, 5, 9 | opus |
| 15 | dashboard | KPI dashboard for managers: period versus previous period and the funnel | Reporting: Application/Query/DoctrineKpiQueries (first response time, leads followed, lead to contact, contact to appointment, appointment to show, show to sale, units per salesperson), UI/Http/Controller/ReportController; widgets/kpi-dashboard (KpiTile grid and one chart-1 funnel), features/pick-period, pages/dashboard, locales reports, e2e/dashboard.spec.ts, README rows | KpiQueriesTest (each KPI on a known seed, empty period, previous period delta, timezone day boundaries), ReportApiTest (manager only, other dealership sees its own numbers), KpiDashboard.test.tsx | KPI-01 – 04 | 3, 5, 9 | sonnet |
| 16 | audit-log | Audit log of changes and of customer record access, with an admin screen | Audit: Infrastructure/Doctrine/ChangeRecorder (onFlush: create, update, delete of every Domain/Model entity, field diffs without password_hash and secret_encrypted, actor, dealership, request id), Application/EventHandler/RecordCustomerViewed, Application/Query/DoctrineAuditQueries, UI/Http/Controller/AuditController; widgets/audit-table, features/filter-audit, pages/settings/audit, locales audit, e2e/audit.spec.ts, README rows | ChangeRecorderTest (one entry per changed entity with diffs, secrets masked, no entry for AuditEntry itself), CustomerViewedTest (a GET of a customer records a read), AuditApiTest (admin only, filters, other dealership sees nothing), AuditTable.test.tsx | AUD-01 – 04 | 3, 5, 9 | opus |

## Decisions

**Versions (pinned by the user).** Database MariaDB 10.11.19 (`mariadb:10.11.19`), Doctrine on the MariaDB platform
(`serverVersion=10.11.19-MariaDB` in `DATABASE_URL`), a separate `mustang_test` database (`dbname_suffix: _test`
under `when@test`; the init SQL grants `mustang\_test%` to the `mustang` user). Backend Symfony 8.1.7 on PHP 8.4.26
(`php:8.4.26-fpm-alpine`; `symfony/*` pinned to 8.1.7 in `composer.json`). Frontend React 19.2.0 (`react` and
`react-dom` pinned exactly). Everything else as in the coordinator's brief: Messenger with a `worker` container,
Mailpit, Vite, OpenAPI → openapi-typescript, Tailwind CSS v4 + shadcn/ui on `backend/assets/styles/tokens.css`. If a third-party
package has no release for Symfony 8.1 at install time, the coordinator records the substitute here.

**Names.** Project, compose project, database, DB user, namespace prefix in console commands: `mustang`. Base branch
`feature/mvp`, worktree `../mustang-mvp`, items `feature/mvp-<slug>` in `../mustang-mvp-<slug>`. API under
`/api/v1`, JSON in `snake_case`; PHP stays camelCase.

**Tenancy and access.** One dealership per user. Every repository `get()` goes through the current dealership and
throws `NotFound` (404) for another dealership's id; every list is filtered by it. Roles `sales`, `bdc`, `manager`,
`admin` with the hierarchy above. Reps see every customer of their rooftop. `suppressions` is the one table without
`dealership_id` (FR-12: suppression across every rooftop).

**Auth and MFA.** Email + password, session cookie, 5 login attempts per minute per IP and email, generic message.
MFA is TOTP and mandatory: `POST /auth/login` answers `next: enroll` for a user without a confirmed credential and
`next: mfa` otherwise; until `POST /auth/mfa/verify` (or a recovery code) succeeds, every other `/api` route answers
403 `mfa_required` and the SPA shows the step. Secrets are encrypted at rest with `SodiumEncrypter`
(`MUSTANG_ENCRYPTION_KEY`). Eight recovery codes, each usable once. An admin can reset a user's MFA
(`DELETE /users/{id}/mfa`), which forces enrollment at the next login. Functional tests sign in with `loginUser()`
and mark the session verified; the smoke suite signs in through the API and computes the code with `otplib` from
the seed secret.

**Messaging without providers.** `MessageTransport` port; `FakeSmsTransport` (default in dev and test,
`MESSAGING_SMS_TRANSPORT=fake`) marks a text `sent` at once with `transport_message_id = fake-<uuid>`; emails go on
the `mail` queue and `MailerEmailTransport` sends them to Mailpit (`queued` → `sent` or `failed`). Inbound is
simulated by `POST /api/v1/dev/inbound` (route condition: `APP_ENV` is `dev` or `test`) and
`mustang:messaging:simulate-inbound <phone or email> "<body>" [--channel=text]`; both dispatch `ReceiveInboundMessage`,
which finds the customer by contact value (or creates one with source `other` and a new lead), appends the message and
raises `InboundMessageReceived`. A conversation is one per customer per dealership and is created on the first
message. `unreplied` = last message inbound; `fumbled` = unreplied for more than 30 minutes (configurable
`WORKLIST_FUMBLED_MINUTES`). Templates are rendered in the UI (`entities/template/lib/render.ts`); the server stores
the body as sent and the `template_id`. Composer attachments, emoji, GIFs and video are out.

**Consent (FR-11 – FR-13, NFR-07).** Consent is per contact value and channel (`text`, `email`, `call`), states
`granted` and `revoked`; no row means `missing`. A send is refused (409 `consent_missing` or 409 `opted_out`) when the
channel has no granted consent, the latest row is revoked, or the value is suppressed; the refusal and the consent
snapshot are stored on the message for provenance. Inbound text keywords STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT
(case-insensitive, whole message) revoke every channel for that phone in the dealership and add a global suppression;
START or UNSTOP removes the suppression and records consent with source `sms_keyword`. The UI's `ConsentIndicator`
shows the state before anything is sent (DESIGN.md principle 5) and links to `/customers/:id/consent`.

**Leads, deals, appointments.** Capturing a lead creates or matches a customer (phone first, then email), assigns a rep
(explicit assignee, else round robin over the dealership's active `sales` users in `routing_rotations`), raises
`LeadCaptured`; the Deal context opens a deal in stage `new`. Stage order new → engaged → appointment → visit →
proposal → sold → delivered; `lost` from any stage with a reason; reopen goes to `engaged`. Automatic moves: `engaged`
when a message is sent or received while `new`; `appointment` on `AppointmentBooked`; `visit` on `AppointmentShown`.
Manual moves forward or back are allowed to every role; `delivered` sets `delivered_at`. `days_in_stage` is computed
from `stage_entered_at`. Trade-in is a value object on the deal. Appointments are stored in UTC and entered in the
dealership timezone (default `America/New_York`); a rep gets an email reminder 60 minutes before (queued with a delay
stamp, skipped if cancelled); confirmation is a status the rep sets (a "Send confirmation" link opens the inbox
composer with the confirmation template). Overlapping appointments for the same rep are refused (409).

**Contact now (FR-03, FR-18).** Rule-based, one reason per row, highest score wins, computed on request from the
merged data (no table). Rules and base scores: unanswered inbound message 100 + minutes waiting (reason "replied N min
ago, nobody answered"; `hot` beyond 30 min); new lead not contacted 90 + minutes; appointment today not confirmed 80;
no-show in the last 24 h without a follow-up 70; deal idle beyond its stage threshold 50 + days (engaged 3 d,
appointment 2 d, visit 2 d, proposal 1 d); an unanswered inbound containing "?" adds 20 and the suffix "question
unanswered". A row belongs to the customer's assigned rep (sales1, else the open deal's assignee, else BDC); managers
can view any rep's list. Suggested channel: the last inbound channel, else text if consented, else email if consented,
else call. Reasons travel as `reason_code` + `reason_params` and are worded in the UI (i18n). The `Prioritizer` port
is where an AI scorer plugs in later.

**Intervention queue (FR-08).** Deals where: the customer is unanswered for more than 60 minutes; the deal is idle
beyond twice its stage threshold; a no-show has had no follow-up for 24 h; a lead is unassigned or uncontacted for 2 h.
`at_risk_cents` = each deal's `expected_gross_cents`, else the dealership's `average_gross_cents` (default $3,000).
The summary also lists reps with unanswered customers and their counts. "Assign to me" calls
`PATCH /deals/{id}/assignee`.

**KPIs (FR-02, basic).** For a period and the previous period of the same length, in the dealership timezone:
first-response time (median minutes from lead creation or inbound message to the next outbound), leads followed
(share of leads with an outbound within 24 h), lead to contact (leads whose conversation has an inbound after an
outbound), contact to appointment, appointment to show, show to sale (deals reaching sold or delivered), units per
salesperson (delivered deals per assignee). Before-versus-after pilot reporting is this screen with two periods.

**Import (CSV).** `customers.csv`: first_name, last_name, phone, email, address, sales_rep_email, sms_consent (yes/no),
email_consent, consent_source, notes. `leads.csv`: first_name, last_name, phone, email, source, vehicle_interest,
received_at, sales_rep_email, notes. UTF-8, header row, 10 MB max, processed by the worker; duplicates (by phone,
then email) are updated, never duplicated; `yes` consent columns record consent with source `import` and the
wording "Imported from the dealer's previous CRM"; a lead row also opens a deal. Vehicles use Inventory's own
`vehicles.csv` (stock_number, vin, year, make, model, trim, body, drivetrain, fuel, exterior_color, interior_color,
mileage, condition, price, status, photo_url). Sample files are served by the API.

**Audit (NFR-04).** A Doctrine `onFlush` listener records create, update and delete of every `Domain/Model` entity
with field diffs (password hashes, MFA secrets and recovery codes masked), the actor, the dealership, the request id
and IP; a customer detail read records an access entry through `CustomerViewed`. Retention: kept forever in the MVP
(policy to be written in `docs/security/policies.md`).

**Frontend.** Theme per user (`light`, `dark`, `system`) stored through `PATCH /me/preferences`, applied as `.dark`
on `<html>`. Lists use `DataTable` with loading, empty, error and filtered-to-nothing states; phones get cards from
the same component. Relative times under 24 h, dates after, full timestamp in a tooltip. One primary button per
screen; `hot` only on contact-now rows and unanswered customers. Every string through i18next; namespaces as listed;
English only, no concatenation, so Spanish can be added by adding files.

**Models and their risk.** `customers`, `inventory`, `templates`, `users-admin` are CRUD on aggregates item 0 fixed
(sonnet). `appointments` is sonnet with the risk that timezone and overlap rules need care; `timeline` is sonnet with
the risk that idempotent projection over many events is more than one moving part; `dashboard` is sonnet with the risk
that the KPI SQL and day boundaries are subtle. If any of these fails the gate twice on the same problem or reports it
is stuck, relaunch it on opus on the same worktree (§2b.4). No item is mechanical enough for haiku: the mechanical work
(configs, kit, strings skeleton, README rows) is all in item 0.

**Waves.** 1: mfa, customers, messaging-api, inventory. 2: leads, deals, compliance, templates. 3: appointments,
inbox-ui, users-admin, data-import. 4: timeline, worklist, dashboard, audit-log. Four stacks at a time on the
16-core, 62 GB host; each service has a CPU ceiling.

**Process notes.** `.env.dev.local` is written by `e2e/prepare.sh` only. Items add their own seeder (tagged
`mustang.seeder`), never edit another's. `openapi.json` and `api.d.ts` are regenerated, never merged by hand. The
coordinator writes `docs/security/policies.md` content at finish and the user confirms it before the PR.

**Contract as built (item 0).** Where the build differs from the text above, this wins:

- *Versions:* `symfony/framework-bundle` is pinned to 8.1.7; the other `symfony/*` components take the latest 8.1
  patch (not every component has an 8.1.7 release). UI tooling: Vite 8, ESLint 9, TypeScript 5.9 and zod 3, the newest
  versions whose peer dependencies agree.
- *Paths:* design tokens are `backend/assets/styles/tokens.css` (the UI build cannot read outside `backend/`); the test
  database init is `docker/mariadb/init/10-test-database.sql`; the UI is built by `vite build --watch` in `node`.
- *Sign-in:* email is unique across Mustang (not per dealership), so sign-in needs no dealership picker.
- *API errors:* `{error, message, detail}`; validation is 422 `{error: "validation", message, violations: [{field,
  code, message}]}`. OpenAPI comes from Nelmio's own attributes (`#[OA\Response(..., new Model(...))]`) on each
  controller; there is no custom `ApiResponse` attribute.
- *Cross-context reads:* `Shared/Application/Port/{UserDirectory, CustomerDirectory, ConsentStates}` give any context
  user names, customer names and the consent state per channel without depending on Access, Customer or Compliance.
  `ConsentStates` is implemented now (`Compliance/Infrastructure/Persistence/DbalConsentStates`) with the rules under
  "Consent"; item 7 keeps it in step with the ledger.
- *Events:* published with `EventBus::publish()`, delivered after the command's transaction commits. A message that
  implements `Shared\Application\Bus\AsyncMessage` runs on the worker. Handlers implement `CommandHandler` /
  `EventHandler` (no framework attribute in Application). `GET /customers/{id}` already publishes `CustomerViewed`.
- *No database foreign keys between aggregates:* the Doctrine mapping owns the schema, and `doctrine:migrations:diff`
  would drop keys it does not map. Integrity is the repositories' job (load through the dealership, 404 otherwise).
- *Frontend:* page slices are flat (`pages/settings-users`, `pages/settings-routing`, `pages/inbox-thread`,
  `pages/customer-consent`, …); the settings tabs are `widgets/settings-nav`; the session (`AuthProvider`, `useAuth`)
  lives in `entities/user`. Each namespace file carries an `_owner` key naming its item; items add keys to their own
  file only. Every route already renders a placeholder page that names its item: an item replaces its page's
  component and keeps the export name.
- *Regression suite:* item 0 owns SHELL-01 – 06 (06 is the by-hand layout and theme check).
- *Smoke suite:* the Symfony debug toolbar is off in dev (it covered the phone tab bar); `consoleErrors()` ignores the
  expected 401 of `/api/v1/me` for a visitor.

- *Learned while building (wave 1):*
  - `vehicles.condition` is a reserved word in MariaDB; its mapping quotes the column name (no schema change).
  - Event and worker handlers run without a signed-in user (console commands, the worker): they read the dealership
    from the event and load records through repository methods that take the dealership explicitly
    (`getInDealership`), never through `TenantContext`.
  - A message queued for the worker arrives on the event bus, which has no transaction: its handler dispatches a
    command, which runs in one. Restart the worker after changing code it runs (`e2e/prepare.sh` does).
  - An inbound message from a contact no customer of the dealership has is a first contact: `ReceiveInboundMessage`
    dispatches the Lead context's `CaptureLead` (source `other`, the phone or email, first name "Unknown" and the
    contact as last name, channel note "First contact by text" or "by email") through Messaging's `LeadIntake` port,
    which creates the customer through the Customer context and routes the lead round robin; Deals opens the deal on
    `LeadCaptured`; the message then goes to the new customer's conversation and raises `InboundMessageReceived`.
    Another dealership's customers are never matched. The console command has nobody signed in, so it runs as the
    dealership's first active admin (Messaging's `ActingUser` port, the previous token restored afterwards) and needs
    `--dealership` for an unknown contact.
  - Messaging's send refusal is 409 `consent_missing` or `opted_out` with `detail {message_id, consent}`; the blocked
    message is kept for provenance. Email sends need a subject; the fake SMS number is +18135550100.
  - Customers: create needs a phone or an email at the API; the `CreateCustomer` command does not, for lead capture
    and import. A duplicate contact is 409 `duplicate_contact` with `detail.customer_id`. There is no unarchive yet.

  - Compliance: a customer's state per channel follows the primary contact of that kind (the one a send goes to);
    `ConsentStates::forContact()` returns the state and the consent row id, so provenance carries `consent_id`.
    The opt-out screen lists only suppressions of the current dealership's customers (a STOP still blocks every
    dealership): listing every rooftop's numbers would leak other dealers' customers. Recording consent on a suppressed
    contact is allowed but the channel stays opted out until the customer sends START.
## Left out on purpose (README "Known gaps")

- Everything in the baseline's backlog: built-in calling and call recording with AI notes (FR-05), AI suggested
  replies (FR-06), follow-up cadences (CRM-06), the pre-switch CRM audit (FR-01), mining and service-to-sales (FR-14,
  FR-20), conversation coaching (FR-10), showroom visit log and In Store list (CRM-07, FR-19), Top 6 report (FR-23),
  appointment and show probability (FR-09), gamification (FR-22), video messages (CRM-09 video), dealer-group roll-up
  (FR-15, NFR-12 group part), the independent-dealer edition (FR-16).
- All integrations: SMS carrier and A2P 10DLC registration (NFR-09), DNC list checks (NFR-08), real inbound email,
  lead providers and OEM feeds, inventory feeds, DMS, service scheduler, website signals, and migration from other
  CRMs (CRM-10). Texts are "sent" by a fake transport and inbound replies are simulated locally.
- On the customer record (CRM-01): household linking, purchase and service history beyond the deals Mustang itself
  records (both need DMS data), Service BDC assignment.
- Routing beyond round robin: rules by source, language, availability and BDC-first (CRM-03 rules).
- Spanish strings (NFR-13): the i18n layer is ready, no translations yet.
- A native app, installable PWA or push notifications (FR-07 is responsive web).
- Composer attachments, emoji, GIFs; bulk actions and exports on lists; calendar sync.
- Security beyond the baseline: SOC 2, penetration test, cyber insurance (NFR-06); encryption at rest is the
  hosting's disk encryption plus field-level encryption of MFA secrets (NFR-01); the retention, incident-response and
  subprocessor documents (NFR-05) are drafted for the user to confirm; audit entries are never pruned.
- Pilot KPI targets (Success metrics) are not set; the dashboard shows values and deltas only.

## Timeline

Recorded with `timeline.py`, from 2026-10-18 15:59 to 2026-10-18 20:32. Active time leaves out the pauses.

| Step | Started | Active | Wall clock |
|---|---|---|---|
| Plan | 2026-10-18 15:59 | 7m | 7m |
| Item 0 (contract) | 2026-10-18 16:05 | 1h 30m | 1h 30m |
| Build test-first | 2026-10-18 17:37 | 18m | 18m |
| Merging the items | 2026-10-18 17:54 | 1h 21m | 1h 21m |
| Barrier (waiting for the items) | 2026-10-18 19:16 | 9m | 9m |
| Gate | 2026-10-18 17:36 | 3m | 3m |
| Full PHPUnit + Vitest | 2026-10-18 19:26 | 3m | 3m |
| Security audit | 2026-10-18 19:28 | 1m | 1m |
| Regression run (smoke, then manual) | 2026-10-18 19:30 | 58m | 58m |
| Definition of done | 2026-10-18 20:28 | 4m | 4m |
| Pull request | 2026-10-18 20:32 | 1m | 1m |
| **Total** | | **4h 33m** | **4h 33m** |

The items of the split (from git: branch created → merged into the base branch):

| Item | Started | Merged | Took |
|---|---|---|---|
| appointments | 2026-10-18 18:28 | 2026-10-18 18:53 | 25m |
| audit-log | 2026-10-18 18:54 | 2026-10-18 19:14 | 20m |
| compliance | 2026-10-18 17:58 | 2026-10-18 18:20 | 23m |
| customers | 2026-10-18 17:37 | 2026-10-18 17:54 | 18m |
| dashboard | 2026-10-18 18:54 | 2026-10-18 19:10 | 15m |
| data-import | 2026-10-18 18:21 | 2026-10-18 18:44 | 23m |
| deals | 2026-10-18 17:58 | 2026-10-18 18:27 | 30m |
| inbox-ui | 2026-10-18 18:30 | 2026-10-18 18:54 | 24m |
| inventory | 2026-10-18 17:37 | 2026-10-18 17:56 | 20m |
| leads | 2026-10-18 17:55 | 2026-10-18 18:16 | 21m |
| messaging-api | 2026-10-18 17:37 | 2026-10-18 17:57 | 20m |
| mfa | 2026-10-18 17:37 | 2026-10-18 18:01 | 24m |
| templates | 2026-10-18 18:17 | 2026-10-18 18:29 | 13m |
| timeline | 2026-10-18 18:54 | 2026-10-18 19:07 | 13m |
| users-admin | 2026-10-18 18:17 | 2026-10-18 18:39 | 22m |
| worklist | 2026-10-18 18:54 | 2026-10-18 19:23 | 29m |
