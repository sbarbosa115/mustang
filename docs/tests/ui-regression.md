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

Every seed account has two-step sign-in on: the TOTP secret `MUSTANGDEVTOTPSECRET234567` (add it to an
authenticator app, or compute the code with `otplib`) and the recovery codes `MUSTANG-RECOVERY-1` to
`MUSTANG-RECOVERY-8` (dev only). A code is accepted once per account: a second sign-in within the same 30 seconds
waits for the next code.

**AUTH-04 · The first sign-in sets up two-step sign-in**
By hand (it replaces the account's seed secret). As admin, reset sales2's MFA (`DELETE /api/v1/users/{id}/mfa`, or
the Users screen once item "users-admin" lands), then sign in as sales2@mustang.test on a phone.
**Expected:** after the password, "Set up two-step sign-in" shows a QR code that an authenticator app (Google
Authenticator, 1Password…) scans, and the key in groups of four for typing. A wrong code says "That code is not
right"; the right one shows eight recovery codes once, with "Copy codes"; "I saved my codes" lands on Today. Signing
out and in again asks for the code (AUTH-05), never for enrollment. Reloading the enrollment screen shows a new QR
code, and only the newest one works. Reseed (`mustang:seed-demo`) afterwards.

**AUTH-05 · Sign-in asks for the code from the app**
Smoke: `e2e/mfa.spec.ts`.
Sign in with an enrolled account. **Expected:** after the password, "Enter your code"; a wrong code says "That code is
not right. Check the app and try again."; the code the app shows lands on Today. The same code a second time (sign
out and in within 30 seconds) says it was already used. Six wrong codes in five minutes say to wait a few minutes.
"Sign in as someone else" goes back to the password.

**AUTH-06 · A recovery code signs in once**
Smoke: `e2e/mfa.spec.ts`.
At the code step, "Use a recovery code instead", type `MUSTANG-RECOVERY-1` (case and dashes do not matter).
**Expected:** lands on Today; Settings › Security says two-step sign-in is On with 7 recovery codes left. The same
code again is refused. With two or fewer left, the Security tab warns and says how to get new ones.

**AUTH-07 · A password alone reaches nothing**
Smoke: `e2e/mfa.spec.ts`.
Sign in through the API with the password only. **Expected:** every route but `/api/v1/auth/*` answers 403
`mfa_required` (`/api/v1/customers`, `/api/v1/me`…); `GET /api/v1/auth/mfa` answers `verified: false`. Reloading the
browser on the code step keeps the code step; opening `/today` instead goes back to sign-in.

**AUTH-08 · Only an admin of the dealership resets MFA**
Smoke: `e2e/mfa.spec.ts` (the refusals); by hand (the reset itself, with AUTH-04).
**Expected:** `DELETE /api/v1/users/{id}/mfa` answers 403 for a manager or a rep and 404 for the admin of Other
Motors; for Mustang's admin it answers 204 and that user sets up two-step sign-in again at the next sign-in (their
open sessions stay signed in until they sign out).

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
