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
the TOTP secret `MUSTANGDEVTOTPSECRET234567ABCDEF` in any authenticator app).

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

Every seed account has two-step sign-in on: the TOTP secret `MUSTANGDEVTOTPSECRET234567ABCDEF` (add it to an
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

**TODAY-01 · A customer's unanswered question is on the rep's Contact now list, worded with its reason**
Smoke: `e2e/today.spec.ts`.
Signed in as sales@mustang.test, add a customer TODAY-01 Test (`(813) 555-9801`) with Sales 1 = you and text consent,
then the customer texts "Is the Civic still there?" (Simulate a reply). Open Today. **Expected:** a row "TODAY-01 Test"
reads "Replied less than a minute ago, nobody answered; question unanswered", with a relative time (full timestamp on
hover) and the call, text and email buttons; email is disabled and its tooltip says "No consent recorded for Email".
The row is not marked "Contact now" yet. By hand: 31 minutes later (or after
`UPDATE conversations SET last_inbound_at = last_inbound_at - INTERVAL 31 MINUTE` for that customer) the row is at the
top with the orange "Contact now" pill and its word, and its time reads "31 min ago".

**TODAY-02 · Tapping text opens the customer's thread, and an answered customer leaves the list as done**
Smoke: `e2e/today.spec.ts`.
With a customer TODAY-02 Test (`(813) 555-9802`) waiting on Today as in TODAY-01, tap the text button on their row.
**Expected:** the inbox opens on TODAY-02 Test's thread (`/inbox/…`). Reply "Yes, it is" from the composer and go back to
Today: TODAY-02 Test is gone, and the progress line says "1 of N done today" (at least one done) with its bar.

**TODAY-03 · A new lead nobody has contacted is on the list with its vehicle**
Smoke: `e2e/today.spec.ts`.
Capture a lead (Leads, "Capture lead") for TODAY-03 Test, `(813) 555-9803`, source Website, vehicle of interest
"2024 Camry", assigned to you. Open Today. **Expected:** a row "TODAY-03 Test" reads "New lead for a 2024 Camry less
than a minute ago, not contacted yet", with the "New" stage badge; text and email are disabled (no consent yet) and the
call button is the suggestion only once call consent exists. Texting the customer from the inbox (after recording text
consent) removes the row.

**TODAY-04 · Today's unconfirmed appointment, a missed one, and an idle deal**
By hand only (it depends on the time of day). Book an appointment for a customer of yours later today (Appointments,
"Book appointment", a test drive). **Expected:** Today shows their row "Today at 4:30 PM: test drive not confirmed" with
the appointment chip (time and "Scheduled"); "Confirm" on the appointment removes the row. Mark a past appointment of
today "No-show": the row reads "Missed the test drive at …, no follow-up yet"; booking a new appointment or sending a
message removes it. A seeded deal of yours in Proposal with no activity for more than a day reads "No activity for N
days in proposal", with the stage badge and its days.

**TODAY-05 · Only a manager can open another rep's list**
Smoke: `e2e/today.spec.ts`.
As sales@mustang.test, request `/api/v1/worklist/contact-now?user_id=<sales2's id>`. **Expected:** 403
`worklist_not_yours`. As manager@mustang.test, open Today: a "Whose list" menu shows "My list" and each rep; choosing
a rep shows "<name>'s list" under the title and their rows, and the address carries `?user_id=`. By hand: the
other dealership's admin asking for a Mustang Motors rep's list gets 404.

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

**INB-01 · Folders with counts, and Unreplied narrows the list**
Smoke: `e2e/inbox.spec.ts`.
Signed in as sales@mustang.test, a customer INB-01 Test (`(813) 555-9401`) texts "Is the Civic still there?" (Simulate a
reply, or the console command). Open Inbox. **Expected:** the folders read All, Unreplied and Fumbled, each with its
count; "Unreplied" is pressed after tapping it and lists INB-01 Test with the words "Unreplied" and "1 unread" (never
colour alone), the last message and a relative time with the full timestamp on hover. A conversation whose customer
waited more than 30 minutes says "Fumbled" and is in the Fumbled folder.

**INB-02 · Filter by rep and search; filtered to nothing offers Show all**
Smoke: `e2e/inbox.spec.ts`.
In Inbox, search `zzzz-nothing`. **Expected:** "Nothing matches these filters." with "Show all"; Show all empties the
search box, goes back to All and lists conversations again. The "Assigned rep" menu lists Everyone and each sales,
BDC and manager user with their unreplied count ("Jordan Reyes · 2 unreplied"), never the admin; choosing one keeps
the folder and narrows the list to that rep's customers. The filters are in the URL: a reload keeps them.

**INB-03 · On a phone a conversation opens full screen, by day, and is marked read**
Smoke: `e2e/inbox.spec.ts`.
At 390 px, open INB-01 Test's conversation from the Unreplied folder. **Expected:** the thread fills the screen with
the customer's name, phone and the back arrow; messages are grouped under "Today", "Yesterday" or the date; the
customer's message is on the left in grey with "Text" and its time below; the unread count is gone from the list.
The back arrow returns to the list with the Unreplied folder still chosen.

**INB-04 · The composer shows consent before anything is sent, and blocks a channel without it**
Smoke: `e2e/inbox.spec.ts`.
Open the conversation of a customer with no consent (INB-04 Test, `(813) 555-9404`). **Expected:** above the message
box "Text: Missing" and "Email: Missing", the reasons "No consent recorded for Text" and "No email address on file",
the Text and Email buttons and Send disabled, and "Record consent" opens /customers/<id>/consent. By hand: after
recording text consent there, coming back enables Text at once. A customer whose consent was revoked reads "Consent
for Text was revoked"; one who replied STOP reads "This contact opted out of Text".

**INB-05 · Send a text to a consenting customer**
Smoke: `e2e/inbox.spec.ts`.
For INB-05 Test with text consent and an inbound message, type "Your car is ready." and Send (or Ctrl+Enter).
**Expected:** the box empties, the message appears on the right in blue with "Text", the time and "Sam Patel"; the
conversation leaves the Unreplied folder and its count goes down by one. By hand: if consent is revoked in another tab
before Send, the composer says "Not sent: the customer opted out of this channel. The attempt is kept in the thread.",
the attempt shows in the thread with "Not sent: the customer opted out", the draft is kept and Text turns disabled.

**INB-06 · A template fills its placeholders; an email needs a subject**
Smoke (part): `e2e/inbox.spec.ts` covers the text template and the email; by hand: the email lands in Mailpit.
For INB-06 Test (text and email consent), "Templates" lists only the active text templates; "First reply" fills "Hi
INB-06, this is Sam Patel at Mustang Motors Tampa. Thanks for reaching out. …". "Appointment confirmation" leaves
`{appointment_time}` and says "Fill in {appointment_time} before sending." Switch to Email: a Subject field appears and
"Vehicle match" fills the subject and body. **Expected:** without a subject Send stays disabled with "An email needs a
subject."; sent, the email shows "Sending", then the worker sends it and it arrives in Mailpit from
no-reply@mustang.test with that subject. An inactive template is never offered.

**INB-07 · Send a vehicle from inventory**
Smoke: `e2e/inbox.spec.ts`.
In INB-07 Test's conversation (text consent), "Vehicle", search `MM-1001` and choose it. **Expected:** the vehicle card
shows its photo or placeholder, year, make and model, trim, stock number, mileage and price; "Add to message" puts its
title, "Trim: …", "Stock #MM-1001", mileage and price into the message box, one per line; Send sends it as a text.
On Email the subject becomes "The <title> you asked about". Nothing is attached.

**INB-08 · Simulate a customer reply (dev and test only); STOP blocks text at once**
Smoke (part): `e2e/inbox.spec.ts` covers the simulator and STOP; by hand: the desktop layout and production.
In INB-08 Test's conversation (text consent), "Simulate a reply" opens a dialog with From set to the customer's
phone; send "STOP". **Expected:** the reply appears on the left, and within a moment the composer reads "Text: Opted
out" and "This contact opted out of Text", with Text and Send disabled. By hand: at 1280 px and wider, folders and
list, the thread and the customer panel (contacts, consent, team, open deal) sit side by side, and picking another
conversation swaps the thread without leaving the page; at 1024 px the panel folds away behind the customer button.
Check both themes. With `APP_ENV=prod` the "Simulate a reply" button is not shown.

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

**CONS-01 · Record text consent from the consent page**
Smoke (part): `e2e/consent.spec.ts` covers the record and the summary; by hand: at 390 px the history is a list of
cards and the dialog scrolls with the keyboard open; "When" is typed in the dealership's timezone.
Signed in as sales@mustang.test, on a customer you created with a phone: Customer actions, "Consent". The page says
"No consent recorded yet." and every channel is "Missing". "Record consent": the phone is preselected (primary
first), channel Text, "How they agreed" Verbal; fill Where and "What they agreed to" and record. **Expected:** "Text
consent recorded for (813) 555-…" appears; the summary shows "Text: Granted" (with an icon, not colour alone); the
history row says Verbal, "by Sam Patel", a relative time with the full timestamp on hover, and the wording. By hand:
choosing Email offers the customer's emails, and a customer without an email says to add one first; a "When" in the
future says "Consent cannot be captured in the future."

**CONS-02 · Revoke consent; the send is refused**
Smoke: `e2e/consent.spec.ts`.
On a customer with text consent, "Revoke" on the history row. **Expected:** the confirmation names the channel and
the phone; with a reason and "Revoke consent", the summary turns "Text: Revoked", the row keeps its history with
"Revoked: <reason>", and no Revoke button is left on it. A text to that customer is refused with `opted_out` (the
composer shows the reason once the inbox ships).

**CONS-03 · A STOP reply opts the phone out at every dealership**
Smoke (part): `e2e/consent.spec.ts` covers the opt-out on the consent page; by hand: the other dealership.
On a customer with text consent, simulate the reply `STOP` (`mustang:messaging:simulate-inbound <phone> STOP`, or
STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT in any case). **Expected:** the consent page shows "Text: Opted out" and
"Text is blocked: this contact replied STOP…"; the text record is revoked with "Replied STOP" and call consent for
that phone is revoked too; email is untouched. A sentence such as "Can you stop by?" changes nothing. By hand: give a
customer of Other Motors the same phone (signed in as other@mustang.test); after the STOP at Mustang Motors that
customer is "Opted out" for text as well, and the number is on Other Motors' opt-outs list.

**CONS-04 · START lifts the opt-out**
Smoke: `e2e/consent.spec.ts`.
After a STOP, simulate the reply `START` (or UNSTOP). **Expected:** the summary is back to "Text: Granted"; the newest
history row is Granted with source "Text keyword", "by the customer", and the customer's words as the wording; the
phone is gone from the opt-outs list.

**CONS-05 · The opt-outs screen lists and searches**
Smoke: `e2e/consent.spec.ts`.
At desktop width, as manager, Settings, "Opt-outs" tab (every role sees it). **Expected:** contacts of this
dealership's customers that opted out, newest first, with the channel (icon and word), the reason and since when;
searching `813-555-…` (any format) finds the phone; a search that finds nothing says "Nothing matches these filters."
and "Show all" empties the search. By hand: with nobody opted out it says "Nobody has opted out."; Other Motors never
sees Mustang Motors' contacts here unless one of its own customers has the same contact.

**CONS-06 · Another dealership cannot see or change a customer's consent**
Smoke (part): `e2e/consent.spec.ts` covers the page and the API; by hand: the layout in the dark theme.
Signed in as other@mustang.test, open `/customers/<a Mustang Motors customer id>/consent`. **Expected:** "This
customer does not exist or is not in your dealership."; reading, recording or revoking through the API answers 404.
By hand: in the dark theme the summary chips and the Granted and Revoked badges stay readable.

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

**DEAL-01 · A rep sees the open deals by stage**
Smoke: `e2e/deals.spec.ts`.
Signed in as sales, open Deals. **Expected:** a column per open stage (New, Engaged, Appointment, Visit, Proposal,
Sold), each headed by its name and count; the seed puts Chris Miller in Sold and Ashley Garcia in Proposal. Each card
shows the customer, the vehicle of interest (or "No vehicle chosen"), "N days in stage" and the rep (or
"Unassigned"). Delivered and lost deals are not on the board: "Lost" shows James Rodriguez with "Lost: Bought
elsewhere", "Delivered" shows Amanda Davis. "My deals" keeps only Sam Patel's. Searching `zzz` says "No deals match
these filters." and "Show all" brings every deal back. On a phone the stages stack one under the other; on a desktop
they are columns side by side.

**DEAL-02 · Open a deal for a customer**
Smoke: `e2e/deals.spec.ts`.
Create a customer `DEAL-02 Test` (`(813) 555-9602`). Deals › New deal › search `DEAL-02` › choose it › Vehicle of
interest `Blue Mustang` › Open deal. **Expected:** "Deal opened for DEAL-02 Test." and the deal page: title
"DEAL-02 Test", stage New · 0 days, "Blue Mustang", assigned to Sam Patel (whoever opened it). The customer's Deals
tab lists it. New deal again for the same customer: "This customer already has an open deal." with "Open the
existing deal", which opens it.

**DEAL-03 · Move a deal forward, back, to lost and reopen it**
Smoke: `e2e/deals.spec.ts`.
On a deal in New: Move stage › Proposal › Move. **Expected:** "Moved to Proposal.", the badge says Proposal · 0
days and Progress shows when Proposal was reached. Move stage › Lost › Move without a reason: "Say why the deal was
lost." and nothing is sent; type `Price` › Move: the badge says Lost, "Why it was lost: Price". The primary button is
now Reopen: Reopen to engaged › the deal is Engaged again, the reason is gone and Proposal is "Not yet".

**DEAL-04 · Set the vehicle of interest and the trade-in**
Smoke: `e2e/deals.spec.ts`.
On a deal: Change vehicle › search `MM-1005` › choose "2026 Toyota RAV4" › Save. **Expected:** "Vehicle of interest
saved." and the card links "2026 Toyota RAV4" (MM-1005) to the vehicle. Change trade-in › Year `2017`, Make `Honda`,
Model `Civic`, Mileage `82000`, Payoff `4500` › Save: "2017 Honda Civic", "82,000 mi", "Payoff $4,500.00". Year
`1800` says "Enter a year between 1950 and 2100." Remove trade-in: "No trade-in".

**DEAL-05 · A manager takes a deal ("Assign to me")**
Smoke: `e2e/deals.spec.ts`.
Signed in as manager, open a deal assigned to Sam Patel › Assigned to › Change › Assign to me. **Expected:**
"Assigned to Maria Gonzalez." and the board's "My deals" now lists it for the manager.

**DEAL-06 · Deals move by themselves: a lead, a message, an appointment**
Smoke (part): `e2e/deals.spec.ts` covers a simulated text reply engaging a deal in New; by hand: capture a lead for a
new customer (Leads › New lead): a deal opens in New, assigned to the lead's rep, with the vehicle interest as its
description; capture a second lead for the same customer: still one deal. Book an appointment for a customer whose
deal is Engaged: the deal goes to Appointment; mark it shown: Visit. A deal in Proposal stays in Proposal when an
appointment is booked; a lost deal stays lost. Run `bin/console mustang:messaging:simulate-inbound "(813) 555-1010"
"Hi"` (Robert Hernandez, New) and reload his deal: Engaged, with the worker and no browser involved.

**DEAL-07 · Another dealership's deal does not exist**
Smoke: `e2e/deals.spec.ts`.
Signed in as other@mustang.test (Other Motors), open `/deals/<id of a Mustang Motors deal>`. **Expected:** "This deal
does not exist or is not in your dealership." with a way back to Deals, and the board of Other Motors shows only its
own deal (Other1 Customer).

## 9. Appointments

<!-- APP-01 – 06: item "appointments". -->

**APP-01 · The calendar shows a week or a day at the dealership time**
Smoke: `e2e/appointments.spec.ts`.
Book an appointment for a customer (API or APP-02). Open Appointments with `?date=` on that day. **Expected:** seven
days, Monday to Sunday, each headed by its date and its count ("Today" on today's), the appointment under its day with
the time at the dealership (Tampa), its status, the kind, "with <rep>" and the note. "Day" shows only that day; the
arrows step by a day or a week and "Today" returns; "Mine" keeps the signed-in rep's, "Everyone" brings the rest back.
The view, date and scope live in the URL and survive a reload. A day with nothing says "Nothing booked"; a period with
nothing says "No appointments in this period" (with "Mine": "None of your appointments are in this period" and "Show
all"). Cancelled appointments are not on the calendar; the customer's Appointments tab lists them. By hand: a booking
late in the evening (11:30 PM) stays on its own day, not the next; at 390 px the days stack, the cards are full width and
every button is 44 px tall.

**APP-02 · Book an appointment, and the refusals**
Smoke: `e2e/appointments.spec.ts`.
Create customers `APP-02 Test` and `APP-02b Test`. Appointments › Book appointment › search `APP-02` › choose
`APP-02 Test`. A time in the past says "That time has already passed." and Book stays disabled. Pick a time well ahead,
a note, Book. **Expected:** "Booked APP-02 Test for <date and time>." and the card on that day. Book `APP-02b Test`
for the same time and rep: "That rep already has an appointment at that time." under the time, the dialog stays. By
hand: choose another rep, length and kind; book half an hour into another appointment of the same rep (refused) and
back to back (accepted); a customer with an open deal in New: the deal moves to Appointment.

**APP-03 · Confirm, then mark the customer as shown**
Smoke: `e2e/appointments.spec.ts`.
For a customer with an open deal, book an appointment. On its card: Confirm. **Expected:** "Marked APP-03 Test's
appointment as confirmed.", the status says Confirmed and Confirm is gone. Showed: "APP-03 Test showed. Nice.", the
status says Showed, the card has no buttons, and the deal moved to Visit. "Send confirmation" (on a scheduled
appointment) opens the customer's conversation in the inbox with the confirmation template.

**APP-04 · A no-show frees the rep for that time**
Smoke: `e2e/appointments.spec.ts`.
On a card: No-show. **Expected:** "Marked APP-04 Test as a no-show.", the status says No-show and the card has no
buttons. The rep can be booked at that time again. The deal stays where it was.

**APP-05 · Reschedule, then cancel**
Smoke: `e2e/appointments.spec.ts`.
Confirm an appointment, then ⋯ › Reschedule › a time in the past: "That time has already passed."; a later day › Reschedule.
**Expected:** "Moved APP-05 Test to <date and time>.", it leaves this day and, on the new day, is Scheduled again (the
customer has to confirm the new time). ⋯ › Cancel appointment asks "Cancel the appointment with APP-05 Test?"; "Keep
it" leaves it; "Cancel appointment" says "Cancelled the appointment with APP-05 Test." and it leaves the calendar; the
customer's Appointments tab shows it Cancelled. By hand: hand it to another rep in the same dialog.

**APP-06 · The rep is reminded an hour before; another dealership sees nothing**
Smoke (part): `e2e/appointments.spec.ts` covers the other dealership; by hand: the reminder email and the worker.
Signed in as sales, book an appointment 62 minutes from now for any customer, with the worker running. **Expected:** one
to two minutes later Mailpit has an email to sales@mustang.test "Appointment at <time> with <customer>" saying it starts
in about an hour, with the time at the dealership. Cancel an appointment booked 63 minutes ahead before the reminder
is due: no email arrives. Move one to a later time: one email arrives, for the new time. An appointment booked less than
an hour ahead sends none. Signed in as other@mustang.test, the other dealership's calendar never shows these, and
their URLs and actions answer 404.

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

**MGR-01 · The team page sums up the deals at risk**
Smoke: `e2e/team.spec.ts`.
As manager@mustang.test at desktop width, open Team. **Expected:** three tiles: "Deals that need you" (a number),
"Gross at risk" (dollars) and "Reps with unanswered customers"; under "Deals that need you" one row per deal with its
reason in words, "Assigned to …" or "Unassigned", "$… at risk" and "Assign to me" (never on a deal already yours).
With nothing at risk the list says "No deal needs you right now."

**MGR-02 · A lead left uncontacted for two hours is in the queue, and "Assign to me" takes it**
Smoke: `e2e/team.spec.ts`.
Capture a lead for MGR-02 Test (`(813) 555-9812`) received three hours ago (`received_at`) and assigned to
sales@mustang.test. As manager, open Team. **Expected:** the row "MGR-02 Test" reads "Lead waiting 3 hours, not
contacted yet", "Assigned to Sam Patel" and "$3,000 at risk" (the dealership's average gross, the deal has none of its own).
"Assign to me": a toast says "MGR-02 Test's deal is assigned to you.", and the row now says "Assigned to" the manager
(Maria Gonzalez) without the button. The deal page shows her as its rep.

**MGR-03 · A rep's unanswered customers are counted, and the count opens their list**
Smoke: `e2e/team.spec.ts`.
A customer MGR-03 Test (`(813) 555-9813`) with Sales 1 = sales2@mustang.test texts "Hello". As manager, open Team.
**Expected:** "Reps with unanswered customers" lists sales2's name with "N waiting" (the word, not only the colour).
Clicking the name opens Today with "<name>'s list", showing MGR-03 Test.

**MGR-04 · Reps are kept out of the team queue**
Smoke: `e2e/team.spec.ts`.
As sales@mustang.test, open `/team`. **Expected:** the app goes to Today; `/api/v1/worklist/interventions` answers 403.
The Team entry is not in the menu.

**MGR-05 · Deals waiting on the customer, idle deals and missed visits, with the gross at stake**
By hand only (it needs time to pass). As manager, after the seed: a customer whose text has waited more than an hour
reads "Customer waiting N hours for an answer"; a deal idle more than twice its stage's days (engaged 3, appointment 2,
visit 2, proposal 1) reads "No activity for N days in <stage>, more than twice the usual"; a no-show nobody followed
up for a day reads "No-show 1 day ago, nobody followed up". A deal with an expected gross shows that amount at risk,
and "Gross at risk" is the sum of the rows. Signed in as other@mustang.test, Team lists none of Mustang Motors'
customers or reps.

## 12. Settings

<!-- USR-01 – 05: item "users-admin". TPL-01 – 04: item "templates". IMP-01 – 05: item "data-import".
     AUD-01 – 04: item "audit-log". -->

**USR-01 · An admin adds a user, who sets up two-step sign-in at first sign-in**
Smoke: `e2e/users.spec.ts`.
As admin, Settings, Users, "Add user": first name `USR01`, last name `Test`, a new email, role BDC, a temporary password of
12 characters or more, then "Add user". **Expected:** a toast says the user was added; searching the last name finds the
row with role BDC, status Active and two-step sign-in "Not set up". Signing in as that user with the password asks to set
up two-step sign-in (the enroll screen), not for a code. By hand: scan the QR code, confirm, and sign in again, which now
asks for a code.

**USR-02 · An admin edits a name and a role**
Smoke: `e2e/users.spec.ts`.
On a user you added, row menu "Edit": "Save changes" stays disabled until something changes; change the first name and the
role to Manager. **Expected:** a toast says the user was updated and the row shows the new name and role. By hand: while
that user is signed in elsewhere, their next request ends the session (a role change signs them out); signing in again
shows the screens of the new role.

**USR-03 · The form explains what is missing, and a taken email is refused**
Smoke: `e2e/users.spec.ts`.
"Add user" with nothing filled: the button is disabled. Type `not-an-email` ("Enter a valid email address.") and a
password of 5 characters ("Use at least 12 characters."). Then `Sales@mustang.test` with a valid password and submit.
**Expected:** "Another account already uses this email." under the email and the dialog stays open. By hand: an email of
the other dealership (`other@mustang.test`) is refused the same way, and the same email in capitals is too.

**USR-04 · Deactivating asks first, ends their session and blocks sign-in; activating restores it**
Smoke: `e2e/users.spec.ts`.
On a user who is signed in (with two-step sign-in set up), row menu "Deactivate". **Expected:** a dialog titled
"Deactivate <name>?" names the user; Cancel changes nothing; the confirm button "Deactivate <name>" shows a toast and the
row's status becomes Deactivated. The user's open session answers 401 on its next request, signing in with their password
is refused, and row menu "Activate" lets them sign in again. By hand: the signed-in user sees the sign-in screen on their
next click, with no data left on screen.

**USR-05 · Own row, last admin and the two-step reset**
Smoke: `e2e/users.spec.ts`.
As the only admin (Alex Admin), the row menu's "Deactivate" on your own row is disabled; "Edit" with role Manager shows
"A dealership needs at least one active admin…" and the row stays Admin. On another user with two-step sign-in set up, row
menu "Reset two-step sign-in" opens a dialog that names the user; confirming shows a toast and the row says "Not set up".
**Expected:** that user's next sign-in goes to the enroll screen. By hand: "Reset two-step sign-in" is disabled for a user
who has none; on a phone (390 px) the users appear as cards with the same menu.

**TPL-01 · A manager writes a text template with the placeholder helper**
Smoke: `e2e/templates.spec.ts`.
As manager, Settings, Templates, "New template". **Expected:** "Save template" is disabled until there is a name and a
message. Typing `Hi `, choosing `{first_name}`, typing `, ` and choosing `{rep_name}` gives the message
`Hi {first_name}, {rep_name}` and the Preview reads "Hi Maria, Sam". Saving shows "TPL-01 Greeting was saved." and the
list row says Text, Active and the message. By hand: the buttons add the tag at the caret, in the middle of the text
too; at 390 px the dialog scrolls and the list shows cards.

**TPL-02 · An email needs a subject and an unknown placeholder is refused**
Smoke: `e2e/templates.spec.ts`.
In "New template" choose Email. **Expected:** the Subject field appears and Save stays disabled until it is filled.
Writing `Hello {pet_name}` says "Unknown placeholder: {pet_name}. Use only the ones listed above." and keeps Save
disabled; the API answers 422 `unknown_placeholder` for the same text. With `{first_name}` it saves and the list says
Email. By hand: `{pet_name}` in the subject is refused the same way; the Preview shows the subject above the message.

**TPL-03 · Edit, deactivate and delete a template**
Smoke: `e2e/templates.spec.ts`.
Create a template, then Edit it. **Expected:** the Channel is shown but cannot be changed; renaming and switching
Active off saves and the row says Inactive. Delete opens a dialog titled "Delete <name>?"; Cancel keeps the row;
"Delete template" removes it with "<name> was deleted." By hand: an inactive template is not offered in the inbox
(item "inbox-ui"), and a message already sent from a deleted template still shows in its conversation.

**TPL-04 · Reps only read templates; another dealership sees none**
Smoke: `e2e/templates.spec.ts`.
Signed in as sales, Settings. **Expected:** there is no Templates tab; the API lists the templates (First reply is
there) but creating one answers 403. Signed in as other@mustang.test, the list does not have First reply and its id
answers 404, never 403. By hand: opening `/settings/templates` as sales or BDC lands on Today.

**IMP-01 · A manager imports a customers file and the customers are there**
Smoke: `e2e/imports.spec.ts`.
Signed in as manager, open Settings, Import. **Expected:** "Customers" is chosen, its columns are explained and
"Download the sample customers.csv" downloads the sample (three customers). Upload the sample as is: a toast says it is
queued, Recent imports shows it as Queued or Importing, then Done with "3 created, 0 updated, 0 unchanged, 0 failed"
without a reload. Elena Vargas is in Customers with her phone as (813) 555-7101, Sam Patel as Sales 1, a note with
her notes and address, and on her consent page text and email granted with source import, "Imported from the dealer's
previous CRM" and "Signed showroom form" as where. James Whitfield has no consent at all.

**IMP-02 · The same file again updates and never duplicates**
Smoke: `e2e/imports.spec.ts`.
Upload the sample customers file a second time. **Expected:** "0 created, 0 updated, 3 unchanged, 0 failed"; still one
Elena Vargas, one note, one consent per channel. Change her last name and add a new email in the file and upload it:
"1 updated"; the customer has the new name and both emails. A row with the phone of a seeded customer updates that
customer instead of adding one.

**IMP-03 · Bad rows are listed by row and column, and the rest lands**
Smoke: `e2e/imports.spec.ts`.
Upload a customers file with a phone `12345`, a row without phone or email, `maybe` as sms_consent, a new customer
without a last name, and one good row. **Expected:** Done with "1 created … 4 failed"; "Show 4 problems" lists each as
"Row N:" (the header is row 1) with what is wrong and its column; nothing of a bad row is in Customers. A file without
the last_name column, an empty file and a .png each fail with a reason; a file over 10 MB is refused before upload
finishes with "The file is too large (10 MB at most)."

**IMP-04 · A leads file opens a lead and its deal per row**
Smoke (part): `e2e/imports.spec.ts` covers one lead; by hand: the deal, round robin and the timezone.
Choose "Open leads" and upload the sample leads.csv. **Expected:** "3 created"; Leads lists Daniel Ortega (website,
Sam Patel, received Jan 15 at 10:30 in the dealership's time), Priya Shah (phone) and Tom Becker (walk-in); Priya and
Tom, who name no rep, went to the next reps in Lead routing's round robin. Each has a deal in New on Deals. Upload it
again: "0 created", no second lead or deal. A received_at in the future or like `31/31/2025` fails its row.

**IMP-05 · Only managers and admins import**
Smoke: `e2e/imports.spec.ts`.
Signed in as sales (and as bdc): Settings has no Import tab, `/settings/import` lands on Today, and the API answers 403
to `GET /api/v1/imports`, to an upload and to the sample files. As admin the screen works as for a manager. Signed in
as other@mustang.test, the address of a Mustang Motors import (`/api/v1/imports/{id}`) is 404 and their list does not
show it.

## Emails

**MAIL-01 · Every email is readable and links to a real screen**
After the run, open each email in Mailpit. **Expected:** no translation keys, "Mustang" as the sender, and every
link opens a real screen.
