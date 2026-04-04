# Database Bootstrap Directory

This directory is now legacy.

Canonical SQL location:

- `sql/101_acadbeat_core_tables.sql`
- `sql/102_acadbeat_core_seed_data.sql`
- `sql/105_academic_practice_video_match_tables.sql`

Compatibility behavior:

- `run_everything.php` only reads files from `sql/`.
- `tools/sync_bootstrap_sql.php` can sync from legacy locations into `sql/`.

Migration note:

Do not add new SQL files in this folder.
