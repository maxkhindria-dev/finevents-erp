# FinEvents ERP — Claude Code Brief

Brief dated 26 Nov 2026 · Owner: Max. Keep this file updated as decisions change (see **Decisions log** at the bottom).

## How Claude Code works on this project

- Start each phase in plan mode: propose the database tables, screens and tests, and wait for Max's approval before coding.
- Write automated tests for every permission rule (each role, each entity) alongside the feature.
- Never commit secrets; use `.env.example` with placeholder names only.
- Never touch production data or deploy to production without Max's explicit approval.
- Keep changes small: one feature per branch and pull request, with a plain-English summary of what changed and how to test it.
- Explain choices in plain English: Max is not a developer.
- Rebuild the *behaviour* of the Claude artifact prototypes, not their code.

## 1. Purpose and goals

One secure, web-based ERP for FinEvents replacing the separate tools prototyped as Claude artifacts: one login, one database, one permission model.

FinEvents is a specialist financial events agency with entities in France, the UK and the USA. Clients are banks, asset managers and other regulated financial firms, so the platform must meet their vendor due-diligence security expectations from day one.

**Goals**
- Replace Toggl, Who's off and scattered spreadsheets with one system the team actually uses.
- Give the CEO and head of events a live view of pipeline, project health, time spent and margins.
- Cut manual finance work: match bank lines to supplier invoices and sync to Pennylane and Xero.
- Make company knowledge findable for staff and freelancers.
- Pass client security reviews (ISO 27001 / SOC 2-style questionnaires, DORA third-party checks) without rework.

**Non-goals for v1:** WhatsApp integration, client-facing portal, payroll, native mobile apps (the web app must be responsive instead).

## 2. Users and access roles

Four roles, enforced on the server **and** at the data layer (automatic query scopes plus restricted MySQL users; MySQL has no native row-level security, see `docs/stack-review.md` §3), never only by hiding UI. Every user has exactly one role. Freelancers are Users with an expiry date and project-scoped access.

**First accounts (decided 27 Nov 2026):** Owner: Max. Super Admin: Solene (COO). Admins: Sarah (Head of Events), Elodie (Operations Manager). Everyone else starts as User.

| Role | Who | Can do | Cannot do |
|---|---|---|---|
| Owner | Max only (one account + sealed break-glass account) | Everything: billing/hosting, delete workspace, create/demote Super Admins, all audit logs, export all data, approve finance syncs above a threshold | Bypass MFA or the audit log |
| Super Admin | Leadership (e.g. head of events, finance lead) | Manage Admins and Users, all modules across all entities, company dashboards, margins and rates, finance review and sync, integration settings | Change Owner, delete workspace, edit/delete audit logs |
| Admin | Team leads, finance assistant | Manage Users in their team/entity, all projects and clients in scope, approve time and leave for their team, edit wiki sections they own | See salaries/day rates outside their team, change security settings, manage integrations |
| User | Staff and freelancers | Own time and leave, assigned projects and clients, read wiki (freelancers: only sections tagged for them) | See finance, others' time detail, pipeline value, unassigned clients |

**Rules for every role**
- Least privilege by default: new accounts start as User with no project access.
- Scope by entity (France, UK, USA) and by project as well as by role.
- Sensitive fields (bank details, day rates, salaries, deal values) are a separate, explicitly granted permission.
- Role changes, exports and permission grants are logged and emailed to the Owner.
- Quarterly access review screen (every user, role, last login), signed off by a Super Admin.
- One-click offboarding revokes sessions, API tokens and Google connections immediately.

## 3. Modules

Six modules share one client, project and people record.

### 3.1 CRM
- Client companies, contacts, deals, account records (work per client, notes, Google Drive links).
- Sales pipeline with stages; metrics by stage, event and revenue.
- Log outreach emails from every team member's Gmail, not only the owner's.
- Automations: follow-up alerts, weekly digest, calendar reminders, client emails via Gmail (drafts need a human click to send).

### 3.2 Time tracking (replaces Toggl)
- Time per person by client, project and task, to measure efficiency and justify fees.
- Each person connects their own Gmail, Calendar and Drive; system suggests entries from meetings, emails and documents, approved with one click.
- Manual start/stop timer and per-task hour entry.
- Rolls up to project, client and entity reports.

### 3.3 Projects (event delivery)
- One project per event, linked to a CRM client and deal, with a project code used across invoices and Drive.
- Tasks: status (to do, ongoing, done), assignees, due dates.
- Daily auto-sync of key client emails as a track record.
- Client documents filed to the project's Drive folder: execution plan, agenda, minutes, call recaps, client budget, detailed running order.
- Management dashboard: on track / at risk / late.
- During testing: no reminder emails to the team (feature flag, **off by default**).

### 3.4 Finance: invoice matching
- Import bank statements (CSV or PDF) per entity.
- Match each bank line to a supplier invoice found in Gmail using amount, date, supplier and project code.
- File matched invoices to the project folder and the finance folder in Drive.
- Sync matches to Pennylane (France) and Xero (UK, USA).
- Scheduled runs, with a review screen where finance approves/corrects before anything is pushed.

### 3.5 Time off (replaces Who's off)
- Holiday and TOIL requests, approval by the person's Admin, balances per person and entity.
- Team calendar visible to everyone; reasons visible only to approvers.
- TOIL can be linked to logged event time.

### 3.6 Wiki
- Searchable knowledge base for staff and freelancers.
- Pages with owners, last-reviewed dates, visibility tags (internal only, freelancers allowed).
- Search also indexes Drive documents the user is allowed to see.

### 3.7 Later (not v1): brand and content tools
Leave room in navigation and data model for: brand style checker, deck generator (standard copy, 38 services, four deck structures), venue finder (London pilot), branded slide creation.

**Brand (also the ERP UI theme):** Dark Blue `#002F55`, Orange `#F57634` only; Lato Bold and Lato Regular.

## 4. Data model and integrations

Core records first: **Entity, Person, Client, Project, Document**. Every module reads and writes them.

| Record | Key fields | Used by |
|---|---|---|
| Entity | France / UK / USA, currency, accounting system | All, finance scoping |
| Person | Name, role, entity, team, employee/freelancer, access expiry | All |
| Client | Company, contacts, owner, sector, notes | CRM, projects, time, finance |
| Deal | Client, stage, value, expected date, event | CRM |
| Project | Code, client, deal, entity, dates, venue, budget, status, Drive folder | Projects, time, finance |
| Task | Project, assignee, status, due date | Projects |
| Time entry | Person, client, project, task, minutes, source (timer/manual/suggested), approved | Time, dashboards |
| Leave request | Person, type (holiday/TOIL), dates, status, approver | Time off |
| Bank line / Invoice / Match | Entity, amount, date, supplier, project code, status, sync ID | Finance |
| Document | Drive file ID, project or client, type, visibility | Projects, CRM, wiki |
| Wiki page | Title, owner, visibility, last reviewed | Wiki |
| Audit event | Who, what, when, IP, before/after | Security |

**Integrations**

| System | Purpose | Notes |
|---|---|---|
| Google Workspace (Gmail, Calendar, Drive) | Sign-in, email/doc sync, time suggestions, filing | Per-user OAuth, narrowest scopes, read-only where possible |
| Pennylane | Accounting sync (France) | API key in secrets manager, never in code |
| Xero | Accounting sync (UK, USA) | OAuth app, one connection per entity |
| Bank statements | CSV/PDF upload | No direct bank login in v1 |
| Transactional email | Notifications | FinEvents domain with SPF, DKIM, DMARC |

File contents live in Google Drive; the ERP stores IDs and metadata only.

## 5. Security requirements (all mandatory, from the first commit)

### 5.1 Identity and sign-in
- Google Workspace SSO only; no separate passwords.
- MFA enforced for every account; hardware keys or passkeys for Owner and Super Admin.
- Sessions expire after 12 h, and after 30 min idle on finance screens; re-auth before exports, role changes and finance syncs.
- Only FinEvents Google Workspace accounts can sign in. Freelancers also have FinEvents accounts; the ERP marks them as freelancers with an access expiry date. (Decided 27 Nov 2026.)
- Owner break-glass account: separate credentials stored offline; every use alerts by email.

### 5.2 Authorisation
- Server-side role and scope checks on every request (Laravel Policies), plus automatic query scopes on every model, plus restricted MySQL users and triggers (audit log write-once, sensitive tables on a separate connection).
- Deny by default; automated tests prove each role cannot reach a higher role's or another entity's data.
- No shared accounts, no shared API keys.

### 5.3 Data protection
- TLS 1.2+ with HSTS; AES-256 at rest including backups.
- Field-level encryption for bank details, OAuth tokens, rates, salaries.
- EU hosting (Frankfurt or Paris).
- Classification on every record: public, internal, confidential, restricted.
- Retention: delete/anonymise leavers' personal data and old email snippets on schedule; keep finance records for the legal period per country.
- GDPR: records of processing, DSAR and deletion from an admin screen, DPAs with every provider.

### 5.4 Secrets and integrations
- All keys/tokens in a secrets manager; none in code, repo or logs.
- Google, Xero, Pennylane connections use least-privilege scopes, revocable per user from admin.
- Outbound actions (sending emails, pushing to accounting) need human approval in v1.

### 5.5 Logging and monitoring
- Tamper-evident audit log: logins, views of restricted records, edits, exports, permission changes, syncs; kept ≥ 12 months; editable by no one, Owner included.
- Owner alerts: mass exports, sign-in from new country, repeated failed access, role escalation.
- Error monitoring strips personal and client data.

### 5.6 Secure development
- Private GitHub repo, branch protection, mandatory review, secret scanning.
- Dependency scanning and static analysis on every PR.
- Separate dev / staging / production; production data never copied to dev (generated test data).
- Input validation, output encoding, CSRF protection, rate limiting, security headers (CSP); OWASP Top 10 and ASVS L2.
- File uploads scanned, size-limited, type-checked.

### 5.7 Resilience
- Daily encrypted backups with point-in-time recovery; quarterly restore test.
- RPO ≤ 24 h, RTO ≤ 8 h.
- Incident response plan: who is called, client notification within 24–72 h per contract/GDPR, written post-mortem.

### 5.8 Evidence for client due diligence
- Independent penetration test before go-live and annually.
- Security pack: architecture, sub-processors, encryption, access control, backup and incident policies.
- Plan toward ISO 27001 or SOC 2 if needed; keep DORA evidence current.

## 6. Tech stack and hosting

**Decided 26 Nov 2026 (Max): PHP + MySQL.** Details and reasoning in `docs/stack-review.md`.

| Layer | Choice |
|---|---|
| Front end | HTML, CSS, Bootstrap 5, plain JavaScript (Blade templates, server-rendered) |
| Back end | PHP 8.3 + Laravel 12 |
| Database | MySQL 8, managed, Frankfurt (e.g. AWS RDS), PITR on |
| Hosting | Laravel Cloud, EU region (decided 27 Nov 2026) |
| Secrets | Dashlane (company source of truth) → Laravel Cloud encrypted environment variables and GitHub Actions secrets |
| Background jobs | Laravel scheduler + queues (Redis) |
| Sign-in | Google (Socialite) + app MFA (Fortify TOTP) + passkeys for Owner/Super Admin |
| Tests | Pest + Playwright |
| Code and CI | Private GitHub repo, GitHub Actions (PHPStan, Semgrep, composer audit, gitleaks) |
| Monitoring | Sentry EU (scrubbed) + uptime checks |

Environments: development (local), staging (test data; Max + pilot group), production (real data, restricted deploys).

## 7. Build phases

Each phase ends with a tested release on staging that Max signs off before the next starts.

1. **Foundation:** repo, environments, Google sign-in with MFA, four roles, three-layer permissions, audit log, core records, user/role admin screens. → plan in `docs/phase-1-plan.md`
2. **CRM:** clients, contacts, deals, pipeline/metrics, Gmail logging, follow-up alerts, weekly digest.
3. **Time tracking:** timer, manual entries, suggestions from Gmail/Calendar/Drive, approvals, reports. Retire Toggl.
4. **Projects:** tasks, email/doc sync, Drive filing, health dashboard.
5. **Finance:** statement import, matching, review screen, Pennylane/Xero sync.
6. **Time off:** requests, approvals, balances, calendar. Retire Who's off.
7. **Wiki:** pages, permissions, search across wiki and allowed Drive files.

**Before go-live:** pen test, restore test, client security pack, migration from artifact prototypes, Toggl and Who's off.

## Open questions for Max

- [x] Who are the first Super Admins and Admins? → Solene Super Admin; Sarah and Elodie Admins.
- [ ] Default active period for freelancer accounts?
- [ ] Should finance syncs above an amount need Owner approval, and what amount? (needed by Phase 5)
- [ ] Do any current clients require ISO 27001 or SOC 2 from suppliers?
- [x] Freelancer sign-in → freelancers have FinEvents Google accounts; the app stays Internal to the Workspace.
- [x] Secrets manager → Dashlane.
- [x] Hosting → Laravel Cloud (EU).
- [ ] *(added by Claude)* Which team and entity do Sarah and Elodie manage as Admins? (needed when accounts are created)

## Decisions log

| Date | Decision | By |
|---|---|---|
| 2026-11-26 | Brief saved as CLAUDE.md; stack review and Phase 1 plan proposed, awaiting approval | Claude Code |
| 2026-11-26 | Stack changed to HTML/CSS/Bootstrap/JS + PHP (Laravel) + MySQL. No native row-level security, so permissions use 3 layers: policies, automatic query scopes, restricted MySQL users/triggers | Max |
| 2026-11-27 | Phase 1 plan approved. Hosting: Laravel Cloud (EU). Freelancers use FinEvents Google accounts (app stays Internal, domain-only sign-in). Secrets: Dashlane. Roles: Solene Super Admin; Sarah and Elodie Admins | Max |
