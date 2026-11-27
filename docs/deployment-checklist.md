# Deployment checklist (staging and production)

Settings each environment must have before it goes live. Claude Code keeps this updated as each step adds requirements. **Values never go in this file or in the repository.** They live in Dashlane and in Laravel Cloud's encrypted environment settings.

## Secrets (generate once per environment, store in Dashlane)

| Setting | How to create it | Why |
|---|---|---|
| `APP_KEY` | `php artisan key:generate --show` | Sessions and general app encryption |
| `FIELD_ENCRYPTION_KEY` | `php artisan finevents:generate-field-key` | Encrypts budgets now; bank details, rates, salaries and tokens later. Without it, production refuses to read or write those fields. |
| `AUDIT_CHAIN_KEY` | `php artisan finevents:generate-field-key` (same format, **different value**) | Seals the audit log. Without it, production refuses to write audit entries. |

Changing either key later makes existing encrypted fields, or existing audit seals, unverifiable until they are re-processed. Treat both as permanent.

## MySQL (managed, EU)

1. **Three logins** (permission layer 3):
   - **Migrator:** `DB_MIGRATOR_USERNAME` / `DB_MIGRATOR_PASSWORD`, with all privileges on the app database `WITH GRANT OPTION`, plus `CREATE USER`. Used only by deploy commands.
   - **App:** `DB_USERNAME` / `DB_PASSWORD`. Created empty. Its permissions come from the command below.
   - **Sensitive:** `DB_SENSITIVE_USERNAME` / `DB_SENSITIVE_PASSWORD`. Created empty, same as the app login. Also set `DB_SENSITIVE_CONNECTION=mysql_sensitive`.
2. **`log_bin_trust_function_creators = 1`** in the database's parameter settings. Managed MySQL keeps a binary log, and without this setting the migrator can't create the triggers that make the audit log append-only. The deploy fails loudly if it's missing.
3. **Point-in-time recovery switched on**, with daily encrypted backups (RPO ≤ 24 h).

## Deploy commands (Laravel Cloud → Deployments)

```bash
php artisan migrate --database=mysql_migrator --force
```

```bash
php artisan finevents:db:privileges --apply
```

The second command must run after **every** migration, because new tables need their permissions. To see exactly what it will grant without changing anything, run it without `--apply`.

## Audit log archive

- `AUDIT_ARCHIVE_DISK` must name a filesystem disk that is **write-once EU storage**, for example an S3 bucket in Frankfurt or Paris with **Object Lock in compliance mode** and a retention of at least 12 months. *(The S3 disk package and bucket are set up with the hosting; until then the archive writes to local storage, which is fine for development only.)*
- The scheduler must run, so that `finevents:audit:verify` runs at 02:10 UTC and `finevents:audit:archive` at 02:20 UTC every day. Laravel Cloud runs the scheduler when it's enabled for the environment.

## Before go-live (from CLAUDE.md)

- [ ] Penetration test
- [ ] Restore test from backup
- [ ] Security pack for clients
- [ ] Data migration from the prototypes, Toggl and Who's off
