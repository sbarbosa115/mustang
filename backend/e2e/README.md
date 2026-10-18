# The smoke suite (Playwright)

The simple cases of [`docs/tests/ui-regression.md`](../../docs/tests/ui-regression.md), run by a script against this
checkout's Docker stack. It runs **before** anything is checked by hand in the browser: a failure here is recorded,
fixed and the suite run again until it is green; only then does the manual run of the complex cases start.

```bash
backend/e2e/smoke.sh                       # prepare the stack (reset to the demo seed) and run everything
backend/e2e/smoke.sh customers             # one spec file
SMOKE_KEEP_DATA=1 backend/e2e/smoke.sh -g "CUS-03"   # one case, without resetting the stack
```

`prepare.sh` drops and recreates the dev database, runs `mustang:seed-demo`, switches the stack to the fake SMS
transport (`backend/.env.dev.local`), and empties the mail catcher and the sign-in rate limits. Reports:
`e2e/.results/` (gitignored).

## What is a smoke case, and what stays manual

| Smoke (here)                                                            | Manual (the browser run)                                                   |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| A view opens and shows the right things, with no console error          | Anything judged by eye: layout at 390 px, both themes, colours, motion     |
| A straightforward create / edit / remove with a clear result            | Timing: the worker's emails, a reminder 60 minutes before an appointment   |
| Access rules: signed out, another role, another dealership's record     | Long stories with several actors: a lead through every stage to delivered  |
| A form's validation messages                                            | What only the database, a log or a console command can prove               |
| Sending to a customer with and without consent; an email in Mailpit      |                                                                            |

A case that mixes both is split: the action is tested here, and the case stays in the manual run for the rest.

## Conventions

- **One spec per area** (`e2e/<area>.spec.ts`, owned by the item that builds the area), each test named by its case:
  `test('CUS-01 · create a customer from the list', …)`.
- **In the suite**, a covered case gets a line right under its title: ``Smoke: `e2e/customers.spec.ts`.`` or
  ``Smoke (part): `e2e/…` covers …; by hand: ….``. A case's ID and text are never removed.
- **Import from `./support/test`**, not from `@playwright/test`: every test is its own visitor for the rate limit,
  and `signedInAs('sales' | 'bdc' | 'manager' | 'admin' | 'sales2' | 'other')` gives a signed-in page (with the TOTP
  step once MFA lands).
- **The seed is read-only for specs.** A spec that changes data creates its own records first, named after its case
  (`CUS-02 Test Customer`), so the next spec still finds the seed as it was.
- **Phone first:** the project runs at 390×844. A manager screen sets `test.use({viewport: {width: 1280, height: 800}})`.
