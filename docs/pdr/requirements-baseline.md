# Mustang Requirements Baseline

2026-10-18 · Sergio

## Status and sources

Draft v0, built from two inputs shared 2026-10-18: Sergio's market and strategy analysis of the U.S. automotive CRM market, and seven DriveCentric screenshots used as inspiration for the product flow. Market figures are as cited in the analysis and have not been re-verified here.

**Decision 2026-10-18:** Mustang is a full CRM that dealers switch to, not an overlay on their existing CRM. Sergio chose this after seeing the analysis recommend an overlay entry.

**Decision 2026-10-18:** v1 is a bare-minimum MVP with the highest-value requirements only; later scope grows from customer feedback.

**Decision 2026-10-18:** the technology stack and all integrations, including migration from other CRMs, are out of scope for now and will be decided along the way.

More examples may follow. Each one will be added as a source, and the requirements it confirms or changes will be marked. Priorities below are proposals until confirmed.

## Product vision and positioning

Mustang is a full dealership CRM, the system of record for leads, customers, conversations and deals, built so salespeople sell instead of operating software. Its AI tells each person what to do next, and DriveCentric's workspace is the reference for the flow.

- **Thesis:** "The CRM that makes salespeople sell instead of making salespeople operate a CRM." It removes work rather than adding software.
- **What it watches:** leads, calls, SMS, email, inventory, customer and service history, appointments and salesperson activity.
- **What it decides:** who is likely to buy, who is being mishandled, what the salesperson should do next, where a manager must step in, and where revenue is leaking.
- **Pitch:** "Switch to Mustang and your reps work from one prioritized list instead of 80 overdue tasks." A free audit of the current CRM's data (FR-01) opens the conversation.
- **Why not "CRM + AI":** basic CRM features and AI are already table stakes at VinSolutions, CDK, DealerSocket, DriveCentric, Tekion and Reynolds.

The analysis recommended entering as an overlay because asking a GM to replace the CRM is a large barrier (migration, retraining, lost leads, broken phones and texts). Going full CRM means switching must eventually be safe. For now the MVP starts from a spreadsheet import, and migration tooling waits in the backlog.

## Target customers and users

The first customer is a single-point franchise dealer or a 2 to 10 rooftop group in Florida. These can afford $2K to $5K a month, and the owner or GM can decide without a long procurement cycle.

| Segment | Florida rooftops | Role in plan |
|---|---|---|
| Single-point franchise and 2 to 10 rooftop groups | part of 953 franchise | Launch customer |
| Larger groups (AutoNation size) | part of 953 franchise | Later; long enterprise cycles |
| Active independents (50+ cars a year) | about 4,240 | Separate, lighter edition later |

Launch territories, in order: Tampa Bay, Orlando, South Florida, Jacksonville.

| User | What they need from Mustang |
|---|---|
| Salesperson | Who to contact now and why, one timeline per customer, a suggested reply, no manual note entry |
| Sales manager / GSM | Which deals need intervention today, pipeline at risk, coaching points per rep |
| Internet / BDC director | Lead response and follow-up quality across the team |
| GM / dealer principal | Proof of incremental units and gross versus cost |
| Group CIO / CTO | Security and compliance answers before signing |
| Service department | Inferred: service customers who are good upgrade candidates |

## MVP scope and backlog

The MVP is the bare minimum full CRM a franchise rooftop can run its sales floor on, plus the one thing that sets Mustang apart: a daily list that tells each rep who to contact now and why. Everything else waits in a backlog and ships in the order customer feedback asks for it.

**MVP**

| Capability | Requirements | Why it can't wait |
|---|---|---|
| Customers and timeline | CRM-01, FR-04 | The system of record; one history per customer |
| Lead capture and routing | CRM-02 (manual, phone and walk-in entry), CRM-03 (round robin only) | No leads, no CRM |
| Deals and appointments | CRM-04, CRM-05 | Tracks every sale from lead to delivery |
| Two-way text and email inbox | FR-17, CRM-09 (templates) | Where reps talk to customers every day |
| Consent and opt-out | FR-11, FR-12, FR-13 | Legally required once Mustang sends messages |
| "Contact now" daily list | FR-03, FR-18 | The differentiator; replaces the overdue-task list |
| Manager intervention queue | FR-08 | Managers see neglected and at-risk deals the same day |
| Inventory search | FR-21 (vehicles entered or uploaded, with filters) | Reps attach a vehicle to a deal and a conversation |
| KPI dashboard | FR-02 (basic) | Proves value: response time, lead to appointment to show to sale |
| Mobile | FR-07 | Reps live on their phones |
| Users and roles | CRM-08 | One rooftop, several roles |
| Data import | Customers and open leads from a spreadsheet | A dealer can start with its existing customer list |
| Baseline security | NFR-01 to NFR-05 | Required by the FTC Safeguards Rule |

**Backlog, ordered by customer feedback**

Not a fixed roadmap. After launch, each item is pulled forward when pilot dealers ask for it or the data shows they need it. The likely-next column is only a starting guess.

| Item | Requirements | Likely next? |
|---|---|---|
| Built-in calling with recording and AI notes | FR-05 | Yes |
| AI suggested replies | FR-06 | Yes |
| Follow-up cadences | CRM-06 | Yes |
| Pre-switch CRM audit | FR-01 | Yes, as a sales tool |
| Integrations: lead providers, inventory feeds, DMS | CRM-02 (feeds), FR-21 (feeds), Integrations section | Decided along the way |
| Migration from other CRMs | CRM-10 | Decided along the way |
| Mining and service-to-sales | FR-14, FR-20 (need DMS data) | Maybe |
| Conversation coaching | FR-10 | Maybe |
| Showroom visit log and In Store list | CRM-07, FR-19 | Maybe |
| Top 6 salesperson report | FR-23 | Maybe |
| Appointment and show probability | FR-09 | Later |
| Gamification | FR-22 | Later |
| Video messages | CRM-09 (video) | Later |
| Dealer-group roll-up | FR-15 | Later |
| Independent-dealer edition | FR-16 | Later |
| SOC 2, pen test, advanced routing | NFR-06, CRM-03 (rules) | Before scaling past pilots |

**Not planned**

- Desking, F&I, DMS, websites and digital retailing
- OEM certifications
- National rollout and large enterprise groups
- Independent-dealer edition

## Functional requirements

The Must rows are the smallest set that lets a pilot dealer see missed opportunities, act on them, and measure the result. Priorities are proposals.

| ID | Area | Requirement | Priority |
|---|---|---|---|
| FR-01 | Revenue-leak audit | Analyze the dealer's last 30 to 90 days of CRM data and report leads where the customer replied and nobody followed up within 30 minutes, with estimated lost units at the store's close rate | Must |
| FR-02 | Pilot ROI | Produce a before-versus-after report for a 60 or 90 day pilot on the dealer KPIs in Success metrics | Must |
| FR-03 | Salesperson | Show a prioritized "contact now" list where each item states why (e.g. "asked about a Camry 47 minutes ago, trade question unanswered"), replacing the overdue-task list | Must |
| FR-04 | Salesperson | Show one timeline per customer combining texts, calls, emails, appointments, videos, leads and tasks | Must |
| FR-05 | Salesperson | Write activity notes automatically from calls and messages so the rep does not type them | Must |
| FR-06 | Salesperson | Suggest a reply using the conversation and matching in-stock inventory | Should |
| FR-07 | Salesperson | Work fully on a phone; mobile is the primary interface | Must |
| FR-08 | Manager | Show a daily intervention queue: deals needing a manager, estimated pipeline at risk in dollars, and reps with unanswered customer questions | Must |
| FR-09 | Manager | Score appointment and show probability per opportunity | Should |
| FR-10 | Manager | Analyze conversations per rep for missed objections, unanswered questions, lead neglect and follow-up quality, and surface coaching points | Must |
| FR-11 | Compliance | Record consent per contact and channel: source, who captured it, where, when, the wording shown and the campaign | Must |
| FR-12 | Compliance | Honor STOP and other revocations at once with suppression across every rooftop and channel, and block sends to suppressed contacts | Must |
| FR-13 | Compliance | Keep a provenance trail for every outbound message | Must |
| FR-14 | Service-to-sales | Flag customers in service today who meet upgrade criteria such as positive equity and purchase age (e.g. 39 months) | Must |
| FR-15 | Dealer group | Roll up dashboards and queues across rooftops in a group | Could |
| FR-16 | Independents | Offer a reduced-function edition at a lower price | Could |

As a full CRM, Mustang sends texts and makes calls itself, so every compliance requirement is Must.

### Core CRM requirements

These are the system-of-record capabilities a dealer needs before it can drop its current CRM. MVP scope and backlog says which ship first.

| ID | Requirement |
|---|---|
| CRM-01 | Customer records with contacts, household, vehicles owned, purchase and service history, and assigned Sales 1, Sales 2, BDC and Service BDC |
| CRM-02 | Lead intake from website, third-party, OEM, phone, walk-in and manual entry, with source tracking and de-duplication |
| CRM-03 | Lead routing rules by store, source, language and availability (round robin, BDC first) |
| CRM-04 | Deals with stages (e.g. Engaged, Appointment, Visit, Proposal, Sold, Delivered, Lost), vehicle of interest, trade-in and days in stage |
| CRM-05 | Appointments with calendar, confirmations, reminders and show tracking |
| CRM-06 | Follow-up cadences by lead status (e.g. day 1, 2, 6, 13 touchpoints) that generate the workplan |
| CRM-07 | Showroom visit logging (up system) that feeds the In Store list |
| CRM-08 | Users, roles and permissions per rooftop and group |
| CRM-09 | Templates for texts and emails, and video messages |
| CRM-10 | Migration from VinSolutions, DealerSocket, CDK/eLead and DriveCentric: customers, leads, deals, notes, tasks and message history, with a weekend cutover and a validation report |

## Reference flow: DriveCentric screens

Sergio shared seven DriveCentric screens (captured 2026-10-01) as inspiration for Mustang's flow. They show a full salesperson and manager workspace, not just an overlay; the scope question this raises is in Open questions. Customer names and contact details visible in the screenshots are not copied here.

**Navigation.** A top bar of modules with live badge counts (conversations, tasks, appointments, inventory, mining, lists, leaderboard), global quick search, add customer, recently viewed records, and a left rail with notifications, unread conversations and who is in store.

| Screen | What it does | Mustang requirement | Take, improve or skip |
|---|---|---|---|
| Conversations | Inbox per store with All, Unreplied and Chat folders and counts; tags (Negative, Ignored, Acknowledged, "BH Fumbled"); filter by salesperson with counts; thread view with text composer, emoji, GIF, attachments, templates and an AI button; customer panel with contact info, AI "Genius Summary", wish list, best contact method, assigned Sales 1, Sales 2, BDC and Service BDC, open deal stage with days in stage, vehicle of interest, trade-in and lead source | FR-17: unified inbox per rooftop with unreplied and fumbled filters, per-rep counts, and a customer side panel with an AI summary, team assignments, deal stage and vehicles | Take; it is FR-04's timeline in practice. Improve: auto-flag fumbled and unanswered questions instead of manual tags |
| My Workplan | Daily task list for one rep: progress bar (0 of 16 done), counters by channel (call, text, email, video, done), rows with store, customer, one-tap call/text/email/video, cadence step (e.g. "Day 6, Engaged, Appt Request"), vehicle, due date and time; overdue rows in red; list, grid and board views | FR-18: daily work queue with one-tap actions and progress, ordered by likelihood to buy with a reason per row | Improve; this is the "82 overdue tasks" list the analysis wants replaced by FR-03 |
| In Store | Live list of customers on the lot with assigned salesperson, vehicle and time since arrival | FR-19: live showroom list visible to managers, linked to each customer's record | Take |
| Mining | Per-store opportunity tiles refreshed nightly: open deals, orphan customers, yesterday's visits and proposals, leases ending within 3 months, high interest rate (9.9%+), deliveries this year, contracts ending within 18 months, birthdays this month, service leads; Mine Deals and Mine Customers actions | FR-20: equity and lifecycle mining that turns each segment into a call list, including service-to-sales (FR-14) | Take; improve by ranking within each tile and saying why |
| Inventory | Search across stores with filters (store, status, price, make, model, year, color, mileage, trim, fuel, engine, body, drivetrain), AI "Genius" search, sort, grid or list; cards show condition, stock number, store, colors, drivetrain, engine, mileage and mpg | FR-21: inventory search with natural-language mode, usable from a conversation to send a matching vehicle | Take; FR-06 depends on it |
| DriveScore | Gamification: level and points, leaderboard rank, deliveries, badges, recent activity, active challenges, top performers monthly or yearly | FR-22: points, badges and challenges tied to the behaviors Mustang recommends | Could; useful for adoption |
| Top 6 Report | Per-store, per-period ranking of salespeople on new, used and total deliveries, front-end gross, back-end gross and overall, with tier color bands | FR-23: salesperson ranking report by units and gross, filtered by store and period | Take |

## Integrations

Deferred. Integrations will be chosen along the way, driven by customer feedback. The table below is kept as reference for later and is not part of the MVP.

| System | Examples | Why Mustang needs it | Phase |
|---|---|---|---|
| CRM | VinSolutions, DealerSocket, CDK/eLead, DriveCentric | One-time migration of customers, leads, deals, notes and message history (CRM-10) | v1 |
| DMS | CDK (Fortellis APIs), Reynolds, Dealertrack, Tekion | Sales history, service visits, equity and contract data for mining and service-to-sales | v1 read |
| Phone and SMS | Dealer phone system, SMS carrier with A2P registration | Call and text content for timelines, notes and coaching | v1 |
| Email | Dealer mailboxes | Timeline and follow-up tracking | v1 |
| Inventory | Dealer inventory feed | Matching vehicles in suggested replies and search | v1 |
| Lead providers and OEM leads | Third-party and manufacturer lead feeds | Lead source for consent ledger and response timing | Later |
| Service scheduler | Service appointment systems | Know who is in service today | Later |
| Website and digital retailing | Dealer site, online deal tools | Shopper intent signals | Later |

The Phase column above reflects the earlier draft; all rows are now backlog.

## Compliance, security and non-functional requirements

Dealers that finance or lease fall under the FTC Safeguards Rule, so Mustang must pass a vendor security review before any pilot. Treat compliance as a selling point, not only a cost.

**Security (FTC Safeguards vendor oversight)**

- NFR-01: Encrypt customer data in transit and at rest
- NFR-02: Enforce MFA for every user
- NFR-03: Role-based access per rooftop and per role (salesperson, manager, BDC, admin)
- NFR-04: Audit log of data access and changes
- NFR-05: Documented incident response, data retention policy, subprocessor list and hosting location
- NFR-06: Annual penetration test; SOC 2 Type I before scaling, Type II later; cyber insurance in place

**Messaging compliance (TCPA, DNC, A2P)**

- NFR-07: No outbound text or call without a recorded consent basis (FR-11)
- NFR-08: DNC list checks before calls
- NFR-09: A2P 10DLC registration for all SMS

**Product qualities**

- NFR-10: Mobile-first; every salesperson flow works on a phone
- NFR-11: A new salesperson is productive on day one with no training session, because turnover is high
- NFR-12: Multi-tenant by rooftop with group roll-up
- NFR-13: Inferred, to confirm: English and Spanish for Florida dealers and their customers

## Success metrics

The product succeeds when a GM can say "this made me $38,000 last month and costs me $2,500." Pilots are measured on dealer KPIs, before versus after 60 to 90 days. Target values are not set yet.

| KPI | Definition | Pilot target |
|---|---|---|
| First-response time | Minutes from lead or customer reply to first rep response | To set |
| Leads followed | Share of leads with follow-up per the store's process | To set |
| Lead to contact | Leads that reach a two-way conversation | To set |
| Contact to appointment | Conversations that book an appointment | To set |
| Appointment to show | Booked appointments that arrive | To set |
| Show to sale | Visits that become a delivery | To set |
| Lost-lead recovery | Previously neglected leads that re-engage or buy | To set |
| Units per salesperson | Deliveries per rep per month | To set |
| Service-to-sales conversion | Service customers flagged who buy | To set |
| Salesperson adoption | Reps active in Mustang on a given day | To set |
| Manager coaching time | Hours per week managers spend on coaching | To set |

Company milestones: 3 dealerships with undeniable numbers, then 10 recognizable Florida dealerships, then one 5 to 20 rooftop group.

## Commercial assumptions

Price per rooftop, not per salesperson, so invoices don't swing with staff turnover. Don't compete as the cheap option. These are test prices from the analysis, not validated.

| Package | Monthly price per rooftop |
|---|---|
| Independent | $499 to $999 |
| Franchise single rooftop | $1,500 to $2,500 |
| AI / advanced | $2,500 to $4,000+ |
| Dealer group | Negotiated multi-rooftop |

High-volume voice, SMS and AI use may carry usage charges. The ROI case: 5 extra cars a month at about $3,000 gross each is about $15,000 of monthly value against a $2,000 to $3,000 fee.

Sales motion the product must support: outreach, 20-minute discovery, CRM audit (FR-01), tailored demo, 60-day rooftop pilot, ROI report (FR-02), annual contract, then neighboring stores.

## Risks and open questions

**Risks**

- Integration access: CRM and DMS vendors may limit or charge for API access, which blocks the overlay model.
- Security review: a missing SOC 2 or weak answers can kill deals before a pilot.
- Adoption: high salesperson turnover and "vendor number 14 this week" fatigue.
- Differentiation: every incumbent now markets AI, and DriveCentric already ships most of the screens shared.

Added with the full-CRM decision:

- Switching barrier: GMs fear migration, retraining 40 people and lost leads; the analysis rates this the biggest sales obstacle.
- Head-on competition: Mustang now replaces VinSolutions, DealerSocket, CDK and DriveCentric directly, rather than complementing them.
- Without migration in the MVP, early dealers may lose history when they switch, which narrows who will pilot.

**Open questions for Sergio**

- [ ] How do pilots work? Options: a full switch at one rooftop, or a free audit of the dealer's current data as the first step.
- [ ] Is Spanish support required at launch?
- [ ] Pilot KPI targets: what improvement makes a pilot a win?
- [ ] How will feedback be collected from pilot dealers: regular calls, in-app requests, usage data, or all three?

