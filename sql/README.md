# SQL Directory

All repository SQL files are centralized here.

## Naming Convention

Use: `NNN_module_purpose.sql`

- `NNN`: execution/order hint (3 digits)
- `module`: owning domain
- `purpose`: schema/seed/migration intent

## Current Files

- `101_acadbeat_core_tables.sql`
- `102_acadbeat_core_seed_data.sql`
- `105_academic_practice_video_match_tables.sql`
- `210_academic_practice_video_resources.sql`
- `220_forum_announcements.sql`

## Runtime Usage

- `run_everything.php` imports:
  - `101_acadbeat_core_tables.sql`
  - `102_acadbeat_core_seed_data.sql`
  - `105_academic_practice_video_match_tables.sql`
