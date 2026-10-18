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

## 6. Customers

<!-- CUS-01 – 08: item "customers". TL-01 – 04: item "timeline". CONS-01 – 06: item "compliance". -->

**CUS-01 · Create a customer from the list**
Smoke (part): `e2e/customers.spec.ts` covers the create and the new record; by hand: at 390 px the form and its
buttons are comfortable to use, and the dialog scrolls when the keyboard is open.
Signed in as sales@mustang.test, open Customers, tap "Add customer", enter Dana as first name, Test as last name and the phone
`(813) 555-0201`, and add. **Expected:** the new customer's record opens, shows the phone as `(813) 555-0201`, and
lists the creator as Sales 1; "Dana Test was added." appears; the customer is on top of the list.

**CUS-02 · The top bar opens the form, which explains what is missing**
Smoke: `e2e/customers.spec.ts`.
At desktop width, signed in as bdc@mustang.test, click "Add customer" in the top bar. **Expected:** `/customers?new=1`
opens the form. "Add customer" stays disabled with only a name; clearing the phone shows "Enter a phone number or an
email."; `not-an-email` shows "Enter a valid email address."; with a valid email it saves and opens the record.

**CUS-03 · A contact already on file is refused and the existing customer is offered**
Smoke: `e2e/customers.spec.ts`.
Add a customer with the phone `813-555-1001` (Sarah Johnson's number written another way). **Expected:** nothing is
created; the form says a customer with this phone number already exists and offers "Open that customer", which opens
Sarah Johnson. The same holds for an email already on another record, in the form and when adding a contact.

**CUS-04 · Search the list and clear a search that finds nothing**
Smoke: `e2e/customers.spec.ts`.
Search `813-555-1002`, then `zzz-nobody`, then "Show all". **Expected:** Michael Williams only; then "Nothing matches
these filters." with a "Show all" button that empties the search and brings the list back. By hand: "My customers"
lists only customers where the signed-in user is on the team; "Archived" lists archived ones; the filters survive a
reload and Back; the page numbers work with more than 25 customers (import some or add them).

**CUS-05 · Edit a customer, add and remove an email**
Smoke: `e2e/customers.spec.ts`.
On a customer you created: Customer actions, "Edit details", change the first name and save. Then "Manage contacts",
add an email typed as `Cus05@Example.com`, then remove it (the confirmation names it). **Expected:** the heading
shows the new name; the email is stored in lowercase and marked Primary; after removal it is gone. By hand: making a
second phone primary demotes the first; removing the primary promotes the other; editing a contact to a number
another customer has says "Another customer already has this contact."

**CUS-06 · Assign the team and record a vehicle the customer owns**
Smoke: `e2e/customers.spec.ts`.
As manager, on a customer you created: "Change team", choose Jordan Reyes as Sales 2 and Brianna Diaz as BDC; then on
Overview add a vehicle (2018 Toyota Camry) and remove it. **Expected:** the panel shows both names; the vehicle is
listed and removing it asks first. By hand: the Sales 1 and Sales 2 lists offer only sales people (and managers), the
BDC list only BDC agents, and a deactivated user is not offered.

**CUS-07 · Notes are listed newest first with their author**
Smoke: `e2e/customers.spec.ts`.
On a customer you created, "Add note" is disabled until you type; add two notes. **Expected:** both show, the newest
first, with the author's name and a relative time (full timestamp on hover); the box empties after each add.

**CUS-08 · Archive a customer; another dealership cannot open the record**
Smoke (part): `e2e/customers.spec.ts` covers the archive and the other dealership; by hand: the tabs of the record.
As manager, on a customer you created: Customer actions, "Archive customer", confirm (the dialog names the
customer). **Expected:** the record shows an "Archived" badge and the menu no longer offers archiving; the customer
is gone from the default list and present under "Archived". Signed in as other@mustang.test, opening Sarah Johnson's
URL says "This customer does not exist or is not in your dealership." By hand: Overview, Timeline, Deals and
Appointments tabs open and keep the choice in the URL (`?tab=deals`); Deals and Appointments list the customer's own
records, or say there are none; the Timeline tab says what will appear until the timeline ships.

## 7. Leads

<!-- LEAD-01 – 06: item "leads". -->

**LEAD-01 · Capture a walk-in for a new customer**
Smoke: `e2e/leads.spec.ts`.
As BDC, Leads, "Capture lead": source Walk-in, phone `813-555-0301`, first name `LEAD-01`, last name `Test`, vehicle
`2025 RAV4 Hybrid`, Rep left on "Next in the round robin", then "Capture lead". **Expected:** a toast says the lead was
captured and routed to a rep; the lead's page is titled "Lead from LEAD-01 Test" with status Assigned, source Walk-in,
the vehicle and the rep's name (Jordan Reyes or Sam Patel, never "Unassigned"). "LEAD-01 Test" opens a new customer
with the phone `(813) 555-0301`. By hand: the deal opened for the lead appears on the customer's Deals tab once
"deals" lands.

**LEAD-02 · A lead for a customer already on file is added to that customer**
Smoke: `e2e/leads.spec.ts`.
"Capture lead" with source Phone call and the phone `813.555.1001` (Sarah Johnson's number written with dots), no
name. **Expected:** under the phone the form says "Adds the lead to Sarah Johnson, who is already a customer."; after
capturing, the page says "Lead from Sarah Johnson" and searching customers for `813-555-1001` still finds one Sarah
Johnson. By hand: the same with her email `Sarah.Johnson@example.com` typed in capitals and no phone.

**LEAD-03 · The form explains what is missing**
Smoke (part): `e2e/leads.spec.ts` covers the disabled button and the new customer without a name; by hand: the phone
layout and the source list.
Open "Capture lead". **Expected:** "Capture lead" stays disabled until there is a source and a phone or an email;
with only an email nobody has (`lead03@example.com`) and no name, capturing says "This is a new customer: enter a
first and a last name." and nothing is created. By hand: at 390 px the dialog scrolls and every field is reachable;
the source list offers Phone call, Walk-in, Website, Entered by hand and Other (not Import); the Rep list offers the
round robin, then active sales, BDC and manager users, never an admin or a deactivated user.

**LEAD-04 · Round-robin order in Settings, Lead routing**
Smoke: `e2e/leads.spec.ts`.
As manager, Settings, "Lead routing". **Expected:** the active salespeople are listed (Jordan Reyes and Sam Patel)
with the line "Next lead goes to …". Move Sam Patel up and "Save order": "Routing saved." and "Next lead goes to Sam
Patel."; a lead captured with the round robin goes to Sam Patel and the next one to Jordan Reyes. Unticking everyone
and saving says "Nobody is in the rotation: new leads wait unassigned." By hand: signed in as sales or BDC there is no
"Lead routing" tab, and opening `/settings/routing` lands on Today; a deactivated salesperson disappears from the
list and gets no leads.

**LEAD-05 · Hand a lead to another rep, then close it**
Smoke: `e2e/leads.spec.ts`.
On a lead you captured: "Hand to" Brianna Diaz, "Assign"; then "Close lead" and confirm (the dialog names the
customer). **Expected:** the Rep shows Brianna Diaz and a toast says the lead was handed to her; after closing the
status is Closed, "Close lead" is gone and the rep picker is disabled. By hand: "Assign" is disabled while the picker
shows the current rep.

**LEAD-06 · Search and filter the list; another dealership cannot open a lead**
Smoke (part): `e2e/leads.spec.ts` covers the search, the empty filter and the other dealership; by hand: the
filters in the URL and the phone cards.
On Leads, search `LEAD-0`, then choose status Closed with the search `zzz-nobody`, then "Show all". **Expected:**
the leads captured above are listed newest first with their source, rep, status and a relative time (full timestamp
on hover); the second search says "Nothing matches these filters." and "Show all" clears the search and both selects.
Signed in as other@mustang.test, a lead's URL says "This lead does not exist or is not in your dealership." By hand:
"My leads" lists only the signed-in rep's leads; status and source survive a reload; at 390 px each lead is a card.

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
