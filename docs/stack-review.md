# Tech stack review

**Status:** stack changed to PHP + MySQL on 26 Nov 2026; hosting (Laravel Cloud), freelancer sign-in and secrets manager (Dashlane) decided 27 Nov 2026.

**Summary:** HTML, CSS, Bootstrap and JavaScript for the front end, PHP (Laravel framework) for the back end, MySQL for the database. MySQL has no built-in row-level security, so permissions are enforced in three layers instead (see §3). The brief's security section has been updated to say this plainly.

---

## 1. Stack

| Layer | Choice | Why |
|---|---|---|
| Front end | HTML + CSS + **Bootstrap 5** + plain JavaScript, rendered by Laravel's Blade templates | As requested. The pages are built on the server, so no permission logic runs in the browser. Bootstrap is themed in Dark Blue #002F55 / Orange #F57634 with Lato. |
| Back end | **PHP 8.3 + Laravel 12** | Laravel is the most widely used PHP framework, and any PHP developer can maintain it. CSRF protection, input validation, encryption, queues and permission "policies" come built in. |
| Database | **MySQL 8**, managed, in **Frankfurt** (e.g. AWS RDS eu-central-1) | Managed service: encrypted at rest (AES-256), daily backups, point-in-time recovery. Meets RPO ≤ 24 h. |
| Hosting | **Laravel Cloud, EU region** (decided) | Fully managed and made for Laravel. Managed MySQL and Redis are provisioned in the same EU region where available; otherwise AWS RDS Frankfurt. |
| Background jobs | Laravel **scheduler + queues** (Redis, same region) | Daily email sync and invoice matching runs. No extra supplier. |
| Sign-in | Google sign-in (**Laravel Socialite**), **Laravel Fortify** for app MFA (authenticator app), **WebAuthn passkeys** for Owner and Super Admin | See §2. |
| Tests | **Pest** (PHP tests, including the permission matrix), **Playwright** (end-to-end in a real browser) | |
| CI | GitHub Actions: PHPStan (level max) + Larastan, **Semgrep** (security scanning; GitHub's CodeQL doesn't support PHP), `composer audit`, `npm audit`, Dependabot, gitleaks + GitHub secret scanning | Runs on every pull request. |
| Monitoring | Sentry (EU data region, personal data scrubbed) + uptime check | |

## 2. Sign-in and MFA

- **Google sign-in only:** no passwords stored by us. Only FinEvents Workspace accounts are allowed. The Google OAuth app is set to **Internal**, so Google itself refuses anyone outside the domain.
- **Two layers of MFA:**
  1. In the Google Workspace admin console, enforce 2-Step Verification for everyone, with security keys only for leadership.
  2. In the app, an extra second factor at every new session: an authenticator app for everyone, passkeys or hardware keys required for the Owner and Super Admin.
- Sessions are stored on the server and last at most 12 hours. Finance screens time out after 30 minutes idle, enforced on the server. A fresh MFA check is required before exports, role changes and finance syncs.

### Freelancers (decided 27 Nov 2026)
Freelancers already have FinEvents Google Workspace accounts, so the app stays **Internal** and needs no Google review or paid security assessment. The ERP marks them as freelancers with an access expiry date and project-only access. Offboarding suspends them in the ERP; suspending the Google account in the Workspace admin is the second step.

## 3. Permissions without row-level security

PostgreSQL could enforce "who sees which row" inside the database. MySQL can't, so we use three layers. If one layer has a bug, the others still block the access.

1. **Route and action checks (Laravel Policies):** every page and every action asks "is this role, in this entity, allowed to do this?" The default answer is no. A test fails the build if any route has no permission check.
2. **Automatic data filtering (global query scopes):** every core table's model filters rows by the user's entity, team and project membership *automatically*. Developers can't forget it, and switching it off is only allowed in a short, reviewed list of places, such as the Owner's export.
3. **Database-level limits (MySQL users and triggers):**
   - The app connects as a database user that **cannot update or delete the audit log**. MySQL triggers also reject any such attempt.
   - **Sensitive fields** (bank details, day rates, salaries, deal values) live in **separate tables**, read through a separate, more restricted database connection, and only after the user's sensitive-field permission is checked.
   - Migrations (changes to the database structure) run under a different, privileged user that the running app never holds.

The **permission test matrix** (every role × every entity × assigned / unassigned) proves layers 1 and 2. Database tests prove layer 3.

## 4. Data protection

- **Field-level encryption:** Laravel's encrypted fields with **AES-256-GCM** and a dedicated encryption key, kept separate from the general app key, for bank details, OAuth tokens, rates and salaries.
- **Audit log:** append-only, with each row hash-chained to the previous one so tampering shows. A nightly copy goes to write-once storage (S3 Object Lock, EU) so that **no one, Owner included,** can alter history.
- TLS 1.2+ and HSTS. Strict Content Security Policy. Bootstrap and the JavaScript are served from our own domain, not a CDN, which keeps the CSP tight.

## 5. Secrets (decided 27 Nov 2026: Dashlane)
- **Dashlane** is the company's source of truth for every key and credential, in a shared collection limited to Max and named Super Admins. The offline break-glass credentials are kept there too, plus a sealed paper copy.
- **At runtime**, secrets are copied into **Laravel Cloud's encrypted environment variables** (production and staging separately). CI secrets go into **GitHub Actions secrets**.
- Dashlane doesn't push secrets into hosting automatically, so updating them is a manual, two-person step. Every rotation is recorded in the decisions log, with dates only and never values.
- Nothing secret goes in the repo; `.env.example` holds placeholders only; gitleaks blocks accidents.

## 6. Sub-processors (initial list for the security pack)
Laravel Cloud (EU hosting, database, Redis), AWS S3 EU (audit archive), Dashlane, Google Workspace, Sentry (EU), GitHub, email provider (Postmark or Resend, chosen in Phase 1). Later phases add Pennylane, Xero and an upload virus scanner.
