# Tech stack review

**Status:** proposed 26 Nov 2026, waiting for Max's approval.

**Summary:** Keep the proposed stack (Next.js + TypeScript, Supabase in the EU, Vercel, Tailwind, GitHub Actions, Sentry). It is mainstream and well documented, and it fits the security requirements. Six points need a decision or a small change. Each is set out below in plain English.

---

## 1. Confirmed as proposed

| Layer | Choice | Notes |
|---|---|---|
| App | Next.js (App Router) + TypeScript (strict) | Server-side checks run in server actions and route handlers, never in the browser. |
| Database | Supabase Postgres, **Frankfurt (eu-central-1)** | Row-level security is native. Point-in-time recovery needs the Pro plan plus the PITR add-on, which is required to meet RPO ≤ 24 h. |
| Hosting | Vercel, functions pinned to **fra1** | Same region as the database, so requests are fast and data stays in the EU. Vercel is a US company, so it goes on the sub-processor list with its DPA (EU Standard Contractual Clauses). |
| UI | Tailwind + shadcn/ui (Radix) | Accessible components that we own in the repo and can theme in Dark Blue / Orange with Lato. |
| CI | GitHub Actions + Dependabot + CodeQL + secret scanning + gitleaks | Runs on every pull request. |
| Monitoring | Sentry (EU data region, `sendDefaultPii: false`, extra scrubbing) + uptime check | |
| Tests | Vitest (logic), **pgTAP** (database permission tests), Playwright (end-to-end) | pgTAP tests check the database rules directly, so a UI bug cannot hide a permission hole. |

## 2. Google sign-in and freelancers ⚠ decision needed

Google treats an app as one of two types:

- **Internal app:** only FinEvents Google Workspace accounts can use it. Google does not review it, even when it reads Gmail and Drive.
- **External app:** anyone can sign in. Reading Gmail or Drive then counts as a "restricted scope", which needs Google's verification and a **paid annual third-party security assessment (CASA)**. That takes weeks and costs money every year.

The brief lets freelancers sign in with outside accounts, which would make the app External.

**Recommendation: give freelancers a FinEvents Google Workspace account** (roughly €7–14 per month each). This also:
- lets us enforce MFA on them through Google, which we cannot do for a personal Gmail account;
- makes offboarding one action, because suspending the Google account cuts off everything;
- keeps client emails and files off personal accounts, which bank due-diligence questionnaires ask about.

*Fallback if you prefer outside accounts:* use two Google OAuth apps. An External one handles sign-in only, with basic scopes and light review. An Internal one handles Gmail, Calendar and Drive connections for staff only. Freelancers would then need app-level MFA (see §3).

## 3. MFA enforcement

Supabase cannot see whether Google already checked a second factor, so we use two layers:

1. **Google Workspace (admin console):** enforce 2-Step Verification for everyone. Require **security keys / passkeys only** for the Owner and Super Admin accounts (you can set this per group).
2. **In the app:** Supabase MFA (TOTP authenticator app) is required before any page loads, so the database itself rejects sessions without the second factor (`aal2`). This also covers the break-glass account and any outside-account freelancers.

Re-authentication before exports, role changes and finance syncs is a fresh MFA challenge, checked on the server.

## 4. Background jobs: Supabase Cron + Queues instead of Inngest

**Recommendation: Supabase Cron + Supabase Queues (pgmq) for now.** Every extra provider is one more sub-processor to list, sign a DPA with and defend in a questionnaire. Supabase Cron and Queues run inside the same EU database. If the long Gmail and Drive syncs in phases 2 to 5 outgrow them, we revisit then. Trigger.dev and Inngest are the candidates, and they would be checked for EU hosting first.

## 5. Secrets manager ⚠ decision needed

Vercel and Supabase both store encrypted environment variables, which is fine for most app secrets. Bank questionnaires usually ask for a **dedicated secrets manager** with access logs and rotation.

**Recommendation:** use **1Password Business** (it also holds the offline break-glass credentials and gives you one place to manage staff passwords) or **Doppler**. Both sync secrets into Vercel and GitHub. Either way, OAuth tokens (Google, Xero) are stored **encrypted in the database** with a key that sits outside the database (see §6).

## 6. Things the brief asks for that need special care

- **"Audit log editable by no one, Owner included."** We can block edits and deletes inside the app and the database: the table is append-only, enforced by database triggers, and each row is hash-chained to the previous one, so tampering shows. But whoever controls the Supabase account (you) could in theory still alter the database. To make the "Owner included" claim honest, we **copy audit events every night to write-once storage** (an EU S3 bucket with Object Lock in compliance mode, which nobody can delete from until the retention date). Cost: a few euros a month. I recommend adding it in Phase 1.
- **Field-level encryption:** done in our server code with AES-256-GCM. The encryption key lives in the secrets manager, not in the database, so a database dump alone exposes no bank details, tokens or rates.
- **30-minute idle timeout on finance screens:** enforced on the server through a "last activity" timestamp, not just a browser timer.
- **Virus scanning of uploads (Phase 5):** needs a scanning service, for example ClamAV in a small EU container or a hosted scanner. We will decide this in the Phase 5 plan.
- **Transactional email:** Postmark or Resend (both have EU options), sending from a FinEvents subdomain with SPF, DKIM and DMARC. We will decide this in Phase 1, when the Owner alert emails are built.

## 7. Sub-processors (initial list for the security pack)

Supabase (EU), Vercel (EU functions), Google Workspace, Sentry (EU), GitHub, email provider (TBD), secrets manager (TBD), AWS S3 EU (audit archive). Later phases add Pennylane, Xero and an upload scanner.
