# SQL Migration Status

## Goal

Use `database/bootstrap/` as the single canonical location for repository-level bootstrap SQL.

## Current Status

- `run_everything.php` already supports:
  - Preferred path: `database/bootstrap/101_acadbeat_all_tables.sql` and `database/bootstrap/102_acadbeat_all_data.sql`
  - Backward-compatible fallback: root-level `101_acadbeat_all_tables.sql` and `102_acadbeat_all_data.sql`
- `SETUP_GUIDE.md` already recommends the canonical `database/bootstrap/` path.

## Pending Manual Action

Option A (recommended): run sync script from repository root:

```bash
php tools/sync_bootstrap_sql.php
```

Option B: manually place the two bootstrap SQL files in this folder:

- `database/bootstrap/101_acadbeat_all_tables.sql`
- `database/bootstrap/102_acadbeat_all_data.sql`

After all team members update their local workflows, root-level legacy SQL files can be retired in a later cleanup step.
