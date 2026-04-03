# Database Bootstrap Directory

This directory is the canonical location for repository-level bootstrap SQL files.

Expected files:

- `101_acadbeat_all_tables.sql`
- `102_acadbeat_all_data.sql`

Current compatibility behavior:

- `run_everything.php` first checks this directory.
- If not found, it falls back to root-level SQL files for backward compatibility.

Migration note:

Keep the root SQL files until all setup scripts and team workflows are updated.
