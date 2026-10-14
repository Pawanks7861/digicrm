# DigiCRM — MilesWeb cPanel deployment

Target host paths (this project):

| Item | Value |
|------|--------|
| Project | `/home/happyk682/digicrm` |
| Document root | `/home/happyk682/digicrm/public` |
| URL | `https://demo.buildify360.com` |
| PHP CLI | `/opt/cpanel/ea-php83/root/usr/bin/php` |
| Composer | `/usr/local/bin/composer` |
| Node/npm | `/opt/alt/alt-nodejs22/root/usr/bin` |

This is a standard Laravel app. It does **not** need a Node application or Passenger restart for PHP request handling. Node is used **only** at build time.

---

## Severity-ranked findings (application)

| Severity | Finding | Fix in repo |
|----------|---------|-------------|
| Critical | Phase11 dropped `mms_unique` while it was the only `client_id` FK support index → partial ALTER, migrate stuck Pending | Idempotent `2026_09_25_210000_...` adds `mms_client_id_idx` before drop; resumes partial state |
| Critical | Permission migration failed when empty MyISAM `permissions` already existed | Recreate empty incompatible `permissions`; abort if populated+incompatible |
| Critical | `clients` FK failed when `users` was MyISAM; left empty partial `clients` | Convert `users` to InnoDB; recreate empty partial `clients` |
| High | Phase12 `client_id->change()` with live FK | Drop FK → `MODIFY` → re-add FK via `MysqlSchema::makeClientIdNullable` |
| High | MySQL `engine` default unset → MyISAM on some hosts | `config/database.php` defaults to `InnoDB` |
| Medium | `npm` not on default PATH on shared host | Deploy script prepends Node 22 bin dir |
| Medium | `NODE_ENV=production` can skip Vite deps | Deploy uses `npm ci --include=dev` |
| Medium | MySQL identifier limit (64) on long auto index names | Short explicit names on phase11 + 3 HRMS indexes |
| Low | `public/hot` can hijack production assets | Deploy removes `public/hot`; preflight fails if present |

Verification evidence: [`docs/DEPLOYMENT_VERIFICATION.md`](./DEPLOYMENT_VERIFICATION.md).

---

## First installation

1. Point the subdomain document root at `.../digicrm/public` (not the project root).
2. Ensure MySQL database/user exist; note credentials.
3. Upload or `git clone` into `/home/happyk682/digicrm`.
4. In cPanel, set **PHP version 8.3** for the domain (match CLI).
5. Run:

```bash
cd /home/happyk682/digicrm && \
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:$PATH" && \
bash scripts/cpanel-deploy.sh --first-install
```

6. Edit `.env` **before** migrate if the script paused, or immediately after first create:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://demo.buildify360.com`
- `DB_*` for the cPanel MySQL database
- Leave `QUEUE_CONNECTION=sync` and `CACHE_DRIVER=file` / `SESSION_DRIVER=file` unless you deliberately configure otherwise
- **Do not regenerate `APP_KEY` after the first successful generate**

7. If `.env` was filled after the script created it, finish with:

```bash
cd /home/happyk682/digicrm && \
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force && \
/opt/cpanel/ea-php83/root/usr/bin/php artisan db:seed --force && \
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache && \
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache && \
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

Seed only on first install (demo accounts). Skip `db:seed` on upgrades unless you intentionally want seed data refreshed.

---

## Subsequent deployment (upgrade)

```bash
cd /home/happyk682/digicrm && \
export PATH="/opt/alt/alt-nodejs22/root/usr/bin:$PATH" && \
bash scripts/cpanel-deploy.sh
```

What the script does:

1. `git pull --ff-only` (fails on dirty tree)
2. Refuses to create a new `APP_KEY` if one already exists
3. `composer install --no-dev --optimize-autoloader` via ea-php83
4. `npm ci --include=dev` + `npm run build`
5. Removes `public/hot` if present
6. `php artisan deploy:preflight`
7. `php artisan migrate --force`
8. Clears then rebuilds config/route/view caches
9. `storage:link` only if missing
10. `queue:restart` only when `QUEUE_CONNECTION` is not `sync`

**Always take a MySQL dump in cPanel before migrate on production.**

---

## One copy-paste command (upgrade)

```bash
cd /home/happyk682/digicrm && export PATH="/opt/alt/alt-nodejs22/root/usr/bin:$PATH" && bash scripts/cpanel-deploy.sh
```

---

## Recovery: stuck phase11 performance migration

Symptoms:

- `php artisan migrate` fails or re-fails on `2026_09_25_210000_create_phase11_performance_tables`
- `marketing_metric_snapshots` already has `campaign_id` / `provider` / `sync_run_id` / etc.
- Error previously: `Cannot drop index 'mms_unique': needed in a foreign key constraint`
- Migration row **not** in `migrations` table (still Pending)

Safe recovery (preserves data):

1. **Backup** the database in cPanel.
2. Deploy the fixed code (this repository revision) with git pull / upload.
3. Preflight:

```bash
cd /home/happyk682/digicrm
/opt/cpanel/ea-php83/root/usr/bin/php artisan deploy:preflight
```

4. Migrate:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
```

The fixed migration will:

- Skip columns/FKs already present
- Add `mms_client_id_idx` (or another alternate `client_id` leading index) **before** dropping `mms_unique`
- Create remaining phase11 tables if missing
- Abort with a clear error if `marketing_metric_snapshots` is missing required base columns or a later table exists with the wrong shape

5. Confirm:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status | grep phase11
```

Expect `Ran` for `2026_09_25_210000_create_phase11_performance_tables`.

**Do not** run `migrate:fresh`, `migrate:refresh`, or `migrate:reset` on production.  
**Do not** delete populated `marketing_metric_snapshots` rows.

### If migrate still refuses

Read the exception text. Common cases:

| Message | Action |
|---------|--------|
| missing base columns | Schema drifted — compare to phase10 create; fix manually or restore backup |
| table exists but missing columns (phase11 child) | Incompatible partial child table — empty tables can be dropped manually after backup; populated tables need DBA reconciliation |
| permissions incompatible with rows | Do not auto-drop; export data, fix schema, re-import |

---

## Recovery: permissions / clients (historical)

Already mitigated in migrations for **empty** partial leftovers:

- Empty incompatible `permissions` → dropped and recreated
- Empty partial `clients` → dropped and recreated after `users` → InnoDB
- Populated incompatible tables → migration **stops** (no silent data loss)

---

## Rollback limitations

| Layer | Safe? |
|-------|--------|
| Code: `git checkout` / previous tag | Yes for PHP/Vue |
| `php artisan migrate:rollback` | Only if you understand FK order; phase11 `down()` restores `mms_unique` safely but may not reverse every host-specific index name |
| Database content | **Not** undone by code rollback — restore the dump taken before migrate |

---

## Preflight

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan deploy:preflight --expect-dir=/home/happyk682/digicrm
```

PHP preflight uses **native** checks only for mandatory gates (extensions, `.env`/`APP_KEY`, DB connectivity, schema diagnostics, writable paths, Vite manifest, `public/hot`). It does **not** require `exec` / `shell_exec` / `proc_open`.

Optional git dirty-tree probing is skipped with a clear “unavailable” warning when process functions are disabled (common on MilesWeb). `scripts/cpanel-deploy.sh` owns executable presence (PHP, Composer, Node, npm, git) and the mandatory clean-tree check before `git pull`.

---

## Cron (scheduler)

If you rely on reminders/automations/metrics sync, add one cron in cPanel:

```cron
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/happyk682/digicrm/artisan schedule:run >> /dev/null 2>&1
```

With `QUEUE_CONNECTION=sync`, jobs run inline; no queue worker is required.

---

## Hosting-only checks (must verify on server)

These cannot be proven from the developer workstation alone:

1. cPanel **PHP 8.3** for the vhost matches CLI ea-php83 extensions (`pdo_mysql`, `bcmath`, `mbstring`, …).
2. Document root is `public/`.
3. HTTPS and `APP_URL=https://demo.buildify360.com` (no mixed content).
4. Login page loads CSS/JS from `/build/assets/...`.
5. Session/CSRF cookie works over HTTPS (`SESSION_SECURE_COOKIE` if forced behind SSL).
6. `storage/` and `bootstrap/cache/` owned by the account user and writable without `chmod 777`.
7. MySQL user privileges: `CREATE`, `ALTER`, `INDEX`, `REFERENCES`, `DROP` (for migrations), `SELECT/INSERT/UPDATE/DELETE`.
8. Actual partial schema on demo matches the known phase11 description (compare `SHOW CREATE TABLE marketing_metric_snapshots` / `SHOW INDEX`).
9. Web vs CLI `php -m` parity.

---

## Demo logins (after seed)

Password: `password` (local/demo only — change on shared demos that are public).

See README demo table (`superadmin@digicrm.test`, `admin@digicrm.test`, …).
