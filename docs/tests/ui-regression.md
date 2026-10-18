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

## 8. Deals

<!-- DEAL-01 – 07: item "deals". -->

## 9. Appointments

<!-- APP-01 – 06: item "appointments". -->

## 10. Inventory

**INV-01 · A rep browses and searches the inventory**
Smoke: `e2e/inventory.spec.ts`.
Signed in as sales, open Inventory. **Expected:** the first 25 of the lot with "1–25 of N" and Next page; on a phone
each vehicle is a card (photo or car icon, condition, stock number, mileage, price), on a desktop a table. Search for
`MM-1004`: one vehicle, shown as "Call for price" (it has no price). Search `ford mustang`: only Ford Mustangs (every
word has to match). A stock number, a VIN, a make, a model, a year and a trim all find vehicles.

**INV-02 · Filters narrow the list and clearing them brings it back**
Smoke (part): `e2e/inventory.spec.ts` covers condition, clearing and the "nothing matches" state; by hand: every other
filter and the sorts.
Open Filters and set Condition to New. **Expected:** the URL gains `condition=new`, the Filters button says "1 filter
applied", and the 14 new vehicles show. Make shows each make with its count ("Ford (12)"); the counts add up. Try
status, body, drivetrain, fuel, color, a year range, a price range (whole dollars) and a mileage limit, alone and
together. Sort by price low to high and high to low: vehicles without a price always come last. Reload and use the
back button: the filters stay. Search for gibberish: "Nothing matches these filters." with "Show all", which restores
the list. The price and year boxes apply on Enter or when you leave them, not on every digit.

**INV-03 · A rep adds a vehicle, and a stock number cannot be reused**
Smoke: `e2e/inventory.spec.ts`.
In Inventory choose Add vehicle. **Expected:** the form opens with Add vehicle disabled until stock number, year
(1950 to 2100), make and model are filled. Saving opens the new vehicle's page with its title and the price typed in
dollars (`38500` shows `$38,500`). Adding the same stock number again keeps the form open and says "This stock number
is already in the inventory." under the stock number. A price like `cheap` or a photo address that is not http(s) is
refused next to its field. Leaving the price empty shows "Call for price".

**INV-04 · A rep edits a vehicle and changes its status**
Smoke: `e2e/inventory.spec.ts`.
Open a vehicle and choose Edit. **Expected:** the stock number is fixed; changing the price and the trim saves and the
page shows them with a confirmation. Status (Available, On hold, Sold, In transit) saves the moment it is picked and
survives a reload. In the list, a vehicle that is not available says so in words, not only colour. Clearing the
description and saving removes it.

**INV-05 · A CSV upload imports the good rows and lists the bad ones**
Smoke: `e2e/inventory.spec.ts`.
Choose Upload CSV, download the sample file, and upload it as is. **Expected:** the dialog shows it is processing, then
"4 of 4 rows imported, 0 skipped", and the four SAMPLE vehicles are in the list. Upload a file with a bad year, an
empty stock number and an unknown condition: the other rows still import, and each bad row is listed as "Row N:" with
what is wrong. Upload the same file again: nothing is duplicated, prices and statuses are updated. A file that is not
a .csv, an empty file, and a file without the year column each fail with a reason; the vehicles page is unchanged.

**INV-06 · Another dealership's vehicle is not found, and quick search finds ours**
Smoke: `e2e/inventory.spec.ts`.
Signed in as sales, open the address of a vehicle that belongs to the other dealership. **Expected:** "This vehicle is
not in your inventory." and a way back; the API answers 404, never 403. Press Ctrl K and type `MM-1002`: the vehicle
is offered, and choosing it opens its page.

## 11. Manager: team and dashboard

<!-- MGR-01 – 05: item "worklist". KPI-01 – 04: item "dashboard". -->

## 12. Settings

<!-- USR-01 – 05: item "users-admin". TPL-01 – 04: item "templates". IMP-01 – 05: item "data-import".
     AUD-01 – 04: item "audit-log". -->

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, "Mustang" as the sender, and every
link opens a real screen.
