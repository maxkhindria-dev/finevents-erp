# Phase 1 plan: Foundation

**Status:** proposed 26 Nov 2026, waiting for Max's approval. No code is written until it is approved.

**Goal:** a staging site where FinEvents staff sign in with Google + MFA and each see only what their role and entity allow. Admins can manage users and see the audit log. The core records (Entity, Person, Client, Project, Document) exist for every later module to build on.

---

## A. Database tables

Every table has `id`, `created_at`, `updated_at`, `created_by` and a `classification` column (public / internal / confidential / restricted). Row-level security is **on, with no access by default**. Each table then gets explicit rules.

| Table | Purpose | Key columns |
|---|---|---|
| `entities` | FinEvents France / UK / USA | name, country, currency, accounting_system (pennylane/xero) |
| `teams` | Teams within an entity | entity_id, name, lead_person_id |
| `people` | One row per user | auth_user_id, full_name, email, **role** (owner/super_admin/admin/user), primary_entity_id, team_id, kind (employee/freelancer), access_expires_at, status (active/suspended/offboarded), last_login_at |
| `person_entity_access` | Extra entities a person may see (scope) | person_id, entity_id |
| `sensitive_grants` | Explicit permission for sensitive fields | person_id, grant (bank_details / day_rates / salaries / deal_values), scope (entity or team), granted_by, expires_at |
| `clients` | Client companies | name, sector, owner_person_id, entity_id, notes |
| `projects` | One per event | code (unique, e.g. `FR-2026-014`), client_id, entity_id, name, start/end dates, venue, status, drive_folder_id, budget (*restricted, encrypted*) |
| `project_members` | Who is assigned | project_id, person_id, role_on_project |
| `client_members` | Who may see an unassigned client | client_id, person_id |
| `documents` | Drive file metadata only | drive_file_id, project_id or client_id, type, visibility |
| `audit_events` | Append-only, hash-chained | actor_person_id, action, target_table, target_id, before, after, ip, user_agent, country, occurred_at, prev_hash, hash |
| `access_reviews` | Quarterly sign-off | period, reviewed_by, signed_at, snapshot (json) |
| `security_alerts` | Alerts raised for the Owner | type, person_id, details, emailed_at |
| `feature_flags` | e.g. `team_reminder_emails` (off) | key, enabled |

Deal, Contact, Task and the other records arrive in their own phases. `projects.deal_id` is added in Phase 2.

### How the permission rules work in the database
Small database functions answer "who is asking?": `current_person()`, `has_role_at_least('admin')`, `can_see_entity(e)`, `is_project_member(p)`, `has_sensitive_grant('day_rates', scope)`. Every table's rules use them. For example, a User sees a project only if they are a member of it, an Admin sees projects in their entities, and Super Admins and the Owner see everything. Expired freelancers and offboarded people match nothing.

Audit protection: `UPDATE`, `DELETE` and `TRUNCATE` on `audit_events` are revoked from every database role and also blocked by a trigger. A nightly job checks the hash chain and copies new events to write-once storage (S3 Object Lock, EU).

## B. Screens

1. **Sign in:** "Continue with Google", restricted to the FinEvents domain (plus invited freelancers, depending on the decision in `stack-review.md` §2).
2. **MFA setup / challenge:** required on first login and on every new session. Re-checked before sensitive actions.
3. **Home:** empty shell with FinEvents navigation (CRM, Time, Projects, Finance, Time off, Wiki, plus a greyed-out "Brand tools" placeholder). Each item appears only if the role allows it.
4. **Users (Admin+):** list with role, entity, team, kind, expiry and last login, plus filters.
5. **User detail:** change role (only within your powers; re-auth needed), entity scope, team, sensitive grants, freelancer expiry.
6. **Invite user / freelancer:** new accounts start as User with no project access.
7. **Offboard (one click):** suspends the account, ends all sessions, revokes stored Google tokens and writes an audit event. It also reminds the admin to suspend the Google Workspace account.
8. **Audit log (Owner + Super Admin):** search and filter, read-only, export (re-auth, logged, Owner emailed).
9. **Access review (quarterly):** snapshot of every user, role and last login, with a Super Admin sign-off button.
10. **Entities, clients, projects (basic):** simple list and edit pages so the core records can be created and tested. The rich versions come in Phases 2 to 4.
11. **Security settings (Owner):** alert email address and feature flags.

## C. Security built in this phase

- Session length 12 h; idle-timeout groundwork for finance screens.
- Security headers: strict CSP with nonces, HSTS, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy.
- CSRF protection on all changes (Next.js server actions plus origin checks).
- Rate limiting on sign-in and sensitive endpoints.
- Input validation with Zod on every server action.
- Owner email alerts for: role change, sensitive grant, export, sign-in from a new country, repeated permission denials, break-glass use.
- Field-encryption helper (AES-256-GCM, key from the secrets manager).
- Sentry with personal-data scrubbing.

## D. Tests

- **Permission matrix (pgTAP, database level):** for each of the four roles × three entities × (assigned / unassigned project), check that each one can only read and write what it should. Expected outcomes include: a User in France cannot read a UK project, an Admin cannot see day rates without a grant, nobody can update or delete audit events, an expired freelancer sees nothing, and an Admin cannot promote anyone to Super Admin.
- **Server checks (Vitest):** every server action rejects the wrong role, even if the database rules were missing.
- **End-to-end (Playwright):** sign-in → MFA → user management → offboarding, run against generated test data.
- **CI on every pull request:** lint, typecheck, all tests, CodeQL, dependency audit, gitleaks secret scan.

## E. Delivery as small pull requests

1. Repo skeleton: Next.js, Tailwind theme (Lato, #002F55, #F57634), CI, security headers, `.env.example`
2. Database: core tables, permission functions, row-level security, permission test matrix
3. Audit log: append-only table, hash chain, triggers, nightly archive
4. Sign-in: Google SSO, domain restriction, MFA, session rules
5. User admin: list, detail, invite, role change with re-auth, offboarding
6. Audit log viewer, access review, Owner alerts
7. Basic entities, clients and projects screens with generated test data

Each pull request gets a plain-English summary and "how to test on staging" steps.

## F. What Max needs to set up first (Claude will guide each step)

1. **A private GitHub repository** (e.g. `finevents/finevents-erp`), connected to this Claude Code environment, with branch protection turned on.
2. **Supabase:** an organisation with two projects, `finevents-staging` and `finevents-prod`, both in Frankfurt. Pro plan for prod.
3. **Vercel:** a team account linked to the GitHub repo.
4. **Google Cloud project** under the FinEvents Workspace, with an OAuth client (Internal). Claude will give exact steps.
5. **Google Workspace admin:** enforce 2-Step Verification, with security keys for the leadership group.
6. Decisions on the open questions in `CLAUDE.md`.

**Phase 1 sign-off:** you sign in on staging, the permission tests pass in CI, and you try the test accounts for each role and confirm each sees the right things.
