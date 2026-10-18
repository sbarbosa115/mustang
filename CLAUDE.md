# Mustang: house rules

Read `README.md` (how to run it), `DESIGN.md` (the design system) and, when building part of the MVP,
`docs/pdr/prd-mvp.md` (the plan, the split and the decisions). The process is the `symfony-react-app` skill; these
rules are the project's and win where they differ.

## Running things

- Nothing runs on the host: `docker compose exec php …` for PHP, `docker compose run --rm --no-deps node …` for npm.
  The `node` service watches and rebuilds the UI; read `docker compose logs --tail=20 node` instead of building.
- Never run Prettier over Symfony config (YAML, `config/`, `migrations/`); `.prettierignore` already excludes it.
- After changing a controller's attributes or an Output/Input DTO, regenerate `openapi.json` and `api.d.ts`
  (README, "Checks"). Never edit either by hand.

## Backend

- One folder per bounded context; the layer and context rules are in `deptrac.yaml` and `deptrac.contexts.yaml`.
  A context reaches another only through its Application layer, its domain events, or a port in
  `Shared/Application/Port` (`UserDirectory`, `CustomerDirectory`, `ConsentStates`, `TenantContext`, `Clock`).
- Command handlers implement `Shared\Application\Bus\CommandHandler`, event handlers `EventHandler`; the
  Application layer names no framework class. Handlers never flush. Publish events with `EventBus::publish()`.
- A rule's "no" is a `Shared\Domain\Error\*` with a stable snake_case code; the UI translates the code.
- Every repository extends `TenantScopedRepository`; prove "another dealership gets 404 and sees no rows" for every
  new endpoint (`ApiTestCase::assertNotFoundForOtherDealership`).
- Seed data: add a `DemoSeeder` for your area; never edit another area's seeder. Seeded customers are read-only
  for smoke specs.
- Controllers document responses with `#[OA\Response(..., content: new Model(type: XOutput::class))]` and read input
  with `#[MapRequestPayload]` / `#[MapQueryString]`.

## Frontend

- Feature-Sliced Design, enforced by ESLint: import downwards only, and a slice from outside only through its
  `index.ts`. Item 0 owns `app/`, `shared/` and every `entities/*`; items own `features/`, `widgets/`, `pages/`.
- API calls only through `entities/<x>/api` (typed from the schema); errors are `ApiError` with `.code` and
  `.fieldError(field)`.
- Every visible string through i18next, in your area's namespace file (`shared/i18n/locales/en/<ns>.json`); no
  concatenation, use interpolation and `_one`/`_other` plurals.
- Lists use `DataTable` (loading, error, empty, filtered-to-nothing, phone cards) and `Pagination`.

## DESIGN.md in practice

- Colours only through tokens (`bg-primary`, `text-muted-foreground`, `bg-stage-engaged-surface`…); never hex or
  Tailwind palette colours.
- One primary button per screen. `hot` (orange) only for "contact now" and unanswered customers.
- Never colour alone: stages, consent and channels always carry a word or an icon (`StageBadge`,
  `ConsentIndicator`, `ChannelIcon`).
- 44 px touch targets on phones (shadcn `Button` and `Input` already are); sentence case everywhere.
- Relative times with the full timestamp in a tooltip (`RelativeTime`); skeletons, never spinners; destructive
  actions confirm in an `AlertDialog` that names the record.
- Consent is shown before anything is sent; a channel without consent is disabled with its reason.

## Tests

- Test first. PHP: `tests/Unit/<Context>/`, `tests/Functional/<Context>/` on `ApiTestCase`. UI: `*.test.tsx` next to
  the code, rendered with `renderWithProviders` (strict i18n: a missing key fails the test).
- Smoke specs: `backend/e2e/<area>.spec.ts`, tests named by their case ID from `docs/tests/ui-regression.md`,
  importing from `./support/test` and signing in with `signedInAs('sales' | 'bdc' | 'manager' | 'admin')`.
