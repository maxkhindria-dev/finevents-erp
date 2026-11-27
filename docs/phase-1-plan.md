# Phase 1 plan: Foundation (PHP / Laravel / MySQL)

**Status:** approved by Max on 27 Nov 2026.

**Goal:** a staging site where FinEvents staff sign in with Google + MFA and each see only what their role and entity allow. Admins can manage users and see the audit log. The core records (Entity, Person, Client, Project, Document) exist for every later module to build on.

---

## A. Database tables (MySQL 8)

Every table has `id`, `created_at`, `updated_at`, `created_by` and a `classification` column (public / internal / confidential / restricted).

| Table | Purpose | Key columns |
|---|---|---|
| `entities` | FinEvents France / UK / USA | name, country, currency, accounting_system (pennylane/xero) |
| `teams` | Teams within an entity | entity_id, name, lead_person_id |
| `people` | One row per user | google_id, full_name, email, **role** (owner/super_admin/admin/user), primary_entity_id, team_id, kind (employee/freelancer), access_expires_at, status (active/suspended/offboarded), last_login_at |
| `person_entity_access` | Extra entities a person may see | person_id, entity_id |
| `sensitive_grants` | Explicit permission for sensitive fields | person_id, grant (bank_details / day_rates / salaries / deal_values), scope (entity or team), granted_by, expires_at |
| `clients` | Client companies | name, sector, owner_person_id, entity_id, notes |
| `projects` | One per event | code (unique, e.g. `FR-2026-014`), client_id, entity_id, name, start/end dates, venue, status, drive_folder_id |
| `project_financials` | **Sensitive, separate table**, encrypted | project_id, budget |
| `project_members` | Who is assigned | project_id, person_id, role_on_project |
| `client_members` | Who may see an otherwise unassigned client | client_id, person_id |
| `documents` | Drive file metadata only | drive_file_id, project_id or client_id, type, visibility |
| `mfa_credentials` | Authenticator secrets and passkeys (encrypted) | person_id, type, data, last_used_at |
| `sessions` | Server-side sessions | person_id, ip, last_activity, mfa_verified_at |
| `audit_events` | Append-only, hash-chained | actor_person_id, action, target_table, target_id, before, after, ip, user_agent, country, occurred_at, prev_hash, hash |
| `access_reviews` | Quarterly sign-off | period, reviewed_by, signed_at, snapshot (json) |
| `security_alerts` | Alerts raised for the Owner | type, person_id, details, emailed_at |
| `feature_flags` | e.g. `team_reminder_emails` (off by default) | key, enabled |

Deal, Contact, Task and the other records arrive in their own phases.

### How permissions are enforced (three layers; see `stack-review.md` §3)
1. **Policies** on every route and action. Deny by default.
2. **Automatic scopes** on every model: a User sees only projects they're a member of, an Admin sees their entities, Super Admin and Owner see everything. Expired freelancers and offboarded people see nothing.
3. **MySQL limits:**
   - the app's database user has no `UPDATE`/`DELETE` on `audit_events`, and triggers block it as well;
   - sensitive tables are read through a separate restricted connection;
   - migrations run under a separate admin user.

## B. Screens (Blade + Bootstrap 5, FinEvents theme)

1. **Sign in:** "Continue with Google", FinEvents Workspace accounts only (staff and freelancers). Unknown FinEvents accounts are refused until an Admin invites them.
2. **MFA setup / challenge:** authenticator app for everyone, passkey for Owner and Super Admin. Required on every new session and again before sensitive actions.
3. **Home:** navigation shell (CRM, Time, Projects, Finance, Time off, Wiki, plus a greyed-out "Brand tools" placeholder). Each item shows only if the role allows it.
4. **Users (Admin+):** list with role, entity, team, kind, expiry and last login, plus filters.
5. **User detail:** change role (only within your own powers, MFA re-check), entity scope, team, sensitive grants, freelancer expiry.
6. **Invite user / freelancer:** new accounts start as User with no project access.
7. **Offboard (one click):** suspends the account, deletes all sessions, revokes stored Google tokens and writes an audit event. It also reminds the admin to suspend the Google Workspace account.
8. **Audit log (Owner + Super Admin):** search and filter, read-only, export (MFA re-check, logged, Owner emailed).
9. **Access review (quarterly):** every user, role and last login, with a Super Admin sign-off.
10. **Entities, clients, projects (basic):** simple list and edit pages. The rich versions come in Phases 2 to 4.
11. **Security settings (Owner):** alert email address and feature flags.

All pages are responsive (Bootstrap grid), so they work on phones.

## C. Security built in this phase

- 12-hour sessions stored on the server; idle-timeout middleware ready for finance screens.
- Security headers: strict CSP, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy.
- CSRF tokens on every form (Laravel's default) and `SameSite=Lax` secure cookies.
- Rate limiting on sign-in, MFA and sensitive actions.
- Every input validated with Laravel Form Requests. Blade escapes all output by default.
- Owner email alerts for: role change, sensitive grant, export, sign-in from a new country, repeated permission denials, break-glass use.
- Field encryption (AES-256-GCM, dedicated key from the secrets manager).
- Sentry with personal-data scrubbing.

## D. Tests

- **Permission matrix (Pest):** 4 roles × 3 entities × assigned / unassigned. Expected outcomes include:
  - a France User cannot read a UK project;
  - an Admin cannot see budgets or day rates without a grant;
  - an expired freelancer sees nothing;
  - an Admin cannot promote anyone to Super Admin;
  - nobody can edit or delete audit events.
- **Route coverage test:** fails the build if any route has no permission check.
- **Database tests:** confirm the app's MySQL user really can't modify the audit log or read the sensitive tables directly.
- **End-to-end (Playwright):** sign-in → MFA → user management → offboarding, run against generated test data.
- **CI on every pull request:** PHPStan, Semgrep, `composer audit`, gitleaks, all tests.

## E. Delivery as small pull requests

1. Laravel skeleton, Bootstrap theme (Lato, #002F55, #F57634), CI pipeline, security headers, `.env.example`
2. Core tables, models with automatic scopes, policies, permission test matrix
3. Audit log: append-only table, triggers, hash chain, nightly archive
4. Google sign-in, domain restriction, MFA and passkeys, session rules
5. User admin: list, detail, invite, role change, offboarding
6. Audit log viewer, access review, Owner alerts
7. Basic entities, clients and projects screens with generated test data

Each pull request gets a plain-English summary and "how to test on staging" steps.

## F. What Max needs to set up first (Claude will guide each step)

1. **A private GitHub repository** (e.g. `finevents-erp`), connected to this Claude Code environment, with branch protection turned on.
2. **Laravel Cloud** account (EU region), with staging and production environments.
3. **Managed MySQL 8** in the EU (via Laravel Cloud, or AWS RDS Frankfurt), with backups and point-in-time recovery turned on.
4. **Google Cloud project** under the FinEvents Workspace, with an OAuth client (Internal).
5. **Google Workspace admin:** enforce 2-Step Verification, with security keys for the leadership group.
6. A Dashlane shared collection for ERP secrets.

**First accounts seeded:** Max (Owner), Solene (Super Admin), Sarah and Elodie (Admins).

**Phase 1 sign-off:** you sign in on staging, all permission tests pass in CI, and you try the test account for each role and confirm each sees the right things.

---

## Progress

| Step | Status | Notes |
|---|---|---|
| 1. Skeleton, theme, CI | ✅ Merged ([#1](https://github.com/maxkhindria-dev/finevents-erp/pull/1)) | Actions pinned to commit SHAs; Dependabot waits 14 days. |
| 2. Core tables, scopes, policies, permission matrix | ✅ Merged ([#10](https://github.com/maxkhindria-dev/finevents-erp/pull/10)) | See notes below. |
| 3. Audit log + permission layer 3 (MySQL logins) | In review | See step 3 notes below. |

### Step 2 notes (decisions made while building)
- **People are the login identity.** There is no separate "users" table and no passwords. Role and status can't be set by mass assignment; they change only through permission-checked actions.
- **Only one Owner** is enforced by MySQL itself, using a unique "owner slot" column. The break-glass account is designed in step 4 (sign-in).
- **Project budgets** live in `project_financials`, encrypted with AES-256-GCM using a dedicated `FIELD_ENCRYPTION_KEY`, and are visible only with the **deal values** permission for that entity. Owner and Super Admins see all sensitive fields.
- **Admins** see projects, clients and people in their own entities. **Everyone** also sees projects and clients they are explicitly assigned to. **Freelancers** see only documents marked "freelancers allowed".
- **Deny by default:**
  - when nobody is signed in, every query returns nothing;
  - trusted system work (seeders, scheduled jobs) must say so explicitly with `runAsSystem()`;
  - a model that hasn't declared its visibility rules is fully hidden.
- **Laravel's built-in `/storage` file serving and upload routes are switched off.** Files live in Google Drive.
- **Layer 3 (restricted MySQL users, separate connection for sensitive tables) moves to step 3**, together with the audit-log triggers, because both need the same database-user setup.

### Step 3 notes
- **Audit log:**
  - every create, edit and delete of a core record is logged with before and after values, who did it and from where;
  - encrypted and hidden fields are never copied in; the log only notes that they changed;
  - each entry is sealed to the previous one with an HMAC whose key (`AUDIT_CHAIN_KEY`) lives outside the database.
- **Append-only, three ways:**
  - database triggers refuse edits and deletions for every login;
  - the app login has only SELECT and INSERT on the log;
  - the model refuses changes in code.
- **Tampering is detected** by `finevents:audit:verify`, run daily: an altered, missing or re-sealed entry is reported along with the first broken link. `finevents:audit:archive` then copies new entries to write-once storage.
- **Layer 3:** the app runs as a MySQL login with no access to sensitive tables. Budgets are read through a separate login, and migrations use a third. `finevents:db:privileges` prints or applies the exact permissions, and tests connect as each real login to prove MySQL refuses what it should.
- **Still to come:** project and client membership changes and entity-scope grants (join tables) are logged explicitly when the screens that change them are built (step 5). "Viewed a restricted record" and sign-in events come with the screens (steps 4–7). Owner email alerts come in step 6.
- **Hosting requirements found:** see `docs/deployment-checklist.md`. The most important is `log_bin_trust_function_creators = 1` on managed MySQL.
