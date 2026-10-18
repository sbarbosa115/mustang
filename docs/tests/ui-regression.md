# UI regression suite

What a real user does with the app, case by case, written as steps and what must happen. It is run in two parts:
the **smoke suite** (Playwright, `e2e/`) runs the simple cases against the Docker stack, and when it is green a
person runs the rest in a browser, **in order**: later cases use the data earlier ones create.

A case with `Smoke:` under its title is run by the script named there and skipped by the manual run; with
`Smoke (part):` the script covers what the line says and the rest ("by hand: …") is run manually; with neither, the
whole case is manual.

Every new feature adds its cases here, in the section of the screen it lives on, in the same format. A case is
only worth adding if it would catch the feature breaking: name the button, the data and what you should see.
Case IDs are stable: a new case takes the next number in its section, and a removed case leaves a gap.

Each run is recorded as a new file in [`runs/`](runs/): the smoke suite's attempts and the result of every manual
case (`python3 ~/.claude/skills/symfony-react-app/scripts/new-run.py` creates it, `smoke.py` runs the smoke suite
and records it).

## Before you start

**Start from known data, not from whatever the last run left.** Everything runs in Docker; `backend/e2e/prepare.sh`
does all of this:

```bash
docker compose exec php php bin/console doctrine:database:drop --force
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console mustang:seed-demo
```

- `docker compose ps`: every service is up, and `docker compose logs node` says the last build finished.
- Open Mailpit (http://localhost:8025) next to the app: cases that send email end with "an email arrives".
- Use one browser profile for the run and nothing else on the app's origin in it: tabs share one session.
- Check each screen at phone width (390 px) and at desktop width, in the light and the dark theme.
- Texts never reach a real carrier: the stack uses the fake SMS transport. A customer's reply is simulated with
  `docker compose exec php php bin/console mustang:messaging:simulate-inbound <phone or email> "<text>"`.

**Accounts.** Dev only, never reuse this password: every account signs in with `Mustang-dev-1` (and, once MFA lands,
the TOTP secret `MUSTANGDEVTOTPSECRET234567` in any authenticator app).

| Role | Sign in with | Dealership | Lands on |
|---|---|---|---|
| Admin | admin@mustang.test | Mustang Motors Tampa | /today |
| Manager | manager@mustang.test | Mustang Motors Tampa | /today |
| BDC | bdc@mustang.test | Mustang Motors Tampa | /today |
| Sales | sales@mustang.test, sales2@mustang.test | Mustang Motors Tampa | /today |
| Admin of another dealership | other@mustang.test | Other Motors | /today |

The seed has 30 customers at Mustang Motors (Sarah Johnson is +1 813 555 1001, Michael Williams +1 813 555 1002,
and so on to +1 813 555 1030) and 5 at Other Motors.

**On every screen, whatever the case says**, also check:

- The browser console has no errors, and nothing is a blank page.
- No text shows a raw translation key.
- The screen follows DESIGN.md: one primary button, stages and consent in words as well as colour, 44 px touch
  targets on phones, relative times with the full timestamp on hover.
- A success or error message appears after every save, and it belongs to *that* save.

---

## 1. Public pages (no login)

**PUB-01 · The sign-in page loads for a visitor**
Smoke: `e2e/public.spec.ts`.
Open `/`. **Expected:** it goes to `/login`, shows "Sign in to Mustang", with no console errors.

## 2. Authentication

**AUTH-01 · Sign in and out as each role**
Smoke: `e2e/auth.spec.ts`.
Sign in with each account above. **Expected:** each lands on Today. "Sign out" (desktop: the account menu; phone:
More) returns to the sign-in page; reopening a signed-in URL or pressing back never shows the signed-in page again.

**AUTH-02 · A rep cannot reach the manager screens**
Smoke: `e2e/auth.spec.ts`.
Signed in as sales@mustang.test, open `/dashboard`. **Expected:** back to Today. The API answers 403 for
`/api/v1/reports/kpis`.

**AUTH-03 · Wrong credentials are refused without saying which one is wrong**
Smoke (part): `e2e/auth.spec.ts` covers the message; by hand: after five wrong attempts in a minute, the sixth says
to wait.
**Expected:** one generic message for a wrong password and for an unknown email.

<!-- AUTH-04 – 08: item "mfa" (TOTP enrollment, the code step, recovery codes, admin reset). -->

## 3. App shell and theme

**SHELL-01 · A rep moves between the phone tabs**
Smoke: `e2e/shell.spec.ts`.
At 390 px, signed in as sales, tap Today, Inbox, Customers, Inventory, then More. **Expected:** each opens its
screen; More lists Leads, Deals, Appointments, Settings and Sign out, but not Dashboard or Team.

**SHELL-02 · A manager sees the dashboard and team in the sidebar**
Smoke: `e2e/shell.spec.ts`.
At desktop width, signed in as manager. **Expected:** the dark sidebar lists Dashboard and Team, and both open.

**SHELL-03 · Quick search finds a customer by phone**
Smoke: `e2e/shell.spec.ts`.
Press Ctrl K (⌘K on a Mac), type `813-555-1001`. **Expected:** Sarah Johnson appears; choosing her opens her record.

**SHELL-04 · The theme follows the user**
Smoke (part): `e2e/shell.spec.ts` covers saving and reloading; by hand: sign in on a second browser and see the
same theme, and check System follows the operating system.
In Settings, choose Dark. **Expected:** the app turns dark at once, "Theme saved." appears, and a reload keeps it.

**SHELL-05 · Every route opens**
Smoke: `e2e/shell.spec.ts`.
Signed in as admin, open every screen of the navigation and a URL that does not exist. **Expected:** each shows its
screen (or "This page does not exist." with a way back), never a blank page.

**SHELL-06 · The layout holds at every width, in both themes**
By hand only. At 390 px, 800 px and 1440 px, in light and dark. **Expected:** phone: bottom tabs and no sidebar;
tablet: the menu button opens the sidebar; desktop: the dark sidebar and the top bar. Nothing overflows sideways.

## 4. Today: contact now

<!-- TODAY-01 – 05: item "worklist". -->

## 5. Inbox

<!-- MSG-01 – 04: item "messaging-api" (console command and dev endpoint). INB-01 – 08: item "inbox-ui". -->

**MSG-01 · A customer's text reply lands in their conversation**
Smoke (part): `e2e/messaging.spec.ts` covers the dev endpoint; by hand: the console command.
Run `docker compose exec php php bin/console mustang:messaging:simulate-inbound "(813) 555-1005" "Is the Civic still
there?"`. **Expected:** it says "Reply from Ashley Garcia (+18135551005) added to conversation …". In the inbox,
Ashley Garcia's conversation is at the top, unread (1) and in the "Unreplied" folder; the folder counts went up by one.
Run it again with `nobody@example.com "Hi" --channel=email`: it fails with "No customer has email nobody@example.com"
and nothing changes.

**MSG-02 · A send without consent is refused, and the refusal is kept**
Smoke: `e2e/messaging.spec.ts`.
For a customer with no text consent, send a text to their conversation. **Expected:** 409 `consent_missing` (with a
revoked consent or a STOP: 409 `opted_out`); nothing reaches a transport, the conversation's last message and
"unreplied" flag do not change, and the message's provenance says `blocked` with the consent state it was checked
against.

**MSG-03 · A text to a consenting customer is sent by the fake transport, with its provenance**
Smoke: `e2e/messaging.spec.ts`.
Record text consent for a customer, then send them a text. **Expected:** 201 with status `sent`; the provenance names
the rep who sent it, the transport `fake_sms` with a `fake-…` id, and the consent snapshot `granted` with the phone
it went to. The conversation leaves the "Unreplied" folder.

**MSG-04 · An email is queued, sent by the worker and lands in Mailpit**
Smoke: `e2e/messaging.spec.ts`.
Record email consent for a customer, then send them an email with a subject. **Expected:** 201 with status `queued`;
within a few seconds the email arrives in Mailpit (from no-reply@mustang.test, with that subject) and the message's
provenance turns `sent` with the transport `mailer`. By hand: with the worker stopped (`docker compose stop worker`)
the message stays `queued`; starting it again sends it.

## 6. Customers

<!-- CUS-01 – 08: item "customers". TL-01 – 04: item "timeline". CONS-01 – 06: item "compliance". -->

## 7. Leads

<!-- LEAD-01 – 06: item "leads". -->

## 8. Deals

<!-- DEAL-01 – 07: item "deals". -->

## 9. Appointments

<!-- APP-01 – 06: item "appointments". -->

## 10. Inventory

<!-- INV-01 – 06: item "inventory". -->

## 11. Manager: team and dashboard

<!-- MGR-01 – 05: item "worklist". KPI-01 – 04: item "dashboard". -->

## 12. Settings

<!-- USR-01 – 05: item "users-admin". TPL-01 – 04: item "templates". IMP-01 – 05: item "data-import".
     AUD-01 – 04: item "audit-log". -->

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, "Mustang" as the sender, and every
link opens a real screen.
