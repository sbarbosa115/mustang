# Security policies (NFR-05)

Drafts for the FTC Safeguards Rule vendor review a dealership will run before a pilot. **To be confirmed by the
owner before the first dealer signs**: every line marked _To confirm_ is a decision, not a fact.

## Data Mustang holds

Customer names, phone numbers, email addresses, vehicles owned, deals, appointments, messages and consent records,
per dealership. No payment card data, no Social Security numbers, no credit applications (F&I is out of scope).

## Access control

- Every user signs in with email, password and a TOTP code (NFR-02); sessions are cookies (HttpOnly, SameSite=Lax,
  Secure over HTTPS).
- Roles per dealership: sales, BDC, manager, admin (NFR-03). A user sees only their own dealership's records; another
  dealership's id answers "not found".
- Sign-in is limited to 5 attempts per minute per IP and email.

## Encryption

- In transit: HTTPS only in production. _To confirm: the host terminates TLS 1.2+._
- At rest: the database disk is encrypted by the host (_To confirm: the hosting provider and its setting_); MFA
  secrets are also encrypted in the application with libsodium (`MUSTANG_ENCRYPTION_KEY`).

## Audit log (NFR-04)

Every create, update and delete of a business record, and every opening of a customer record, is logged with who,
when, from which IP and the changed fields (secrets masked). _To confirm: retention. Draft: kept for the life of the
dealer's contract plus 2 years._

## Incident response

_To confirm. Draft:_ a suspected breach is reported to the owner at once; affected dealerships are told within 72
hours with what was exposed; sessions are revoked and secrets rotated; the FTC is notified when 500 or more
consumers are affected, as the Safeguards Rule requires.

## Subprocessors

_To confirm. Today: none._ Hosting, SMS carrier and email delivery providers are added here when chosen (integrations
are deferred, see the requirements baseline).

## Data retention and deletion

_To confirm. Draft:_ a dealership's data is exported on request and deleted within 30 days of the end of its contract;
backups age out within 35 days.
