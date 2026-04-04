# SQL Migration Status

## Goal

Use root `sql/` as the single canonical location for repository-level SQL.

## Current Status

- `run_everything.php` now reads:
  - `sql/101_acadbeat_core_tables.sql`
  - `sql/102_acadbeat_core_seed_data.sql`
  - `sql/105_academic_practice_video_match_tables.sql`
- `tools/sync_bootstrap_sql.php` syncs legacy SQL files into `sql/`.

## Pending Manual Action

Option A (recommended): run sync script from repository root:

```bash
php tools/sync_bootstrap_sql.php
```

Option B: manually place the two bootstrap SQL files in this folder:

- `sql/101_acadbeat_core_tables.sql`
- `sql/102_acadbeat_core_seed_data.sql`
- `sql/105_academic_practice_video_match_tables.sql`

After all team members update local workflows, legacy SQL paths can be removed completely.
