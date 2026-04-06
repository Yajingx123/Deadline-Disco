# SQL Directory

All repository SQL files are centralized here.

## Naming Convention

Use: `NNN_module_purpose.sql`

- `NNN`: execution/order hint (3 digits)
- `module`: owning domain
- `purpose`: schema/seed/migration intent

## Main Entry Files

- `001_acadbeat_all_create_tables.sql`
- `002_acadbeat_all_other_sql.sql`

These are now the two primary SQL entrypoints for database setup.

## Legacy Source Files

- `101_acadbeat_core_tables.sql`
- `102_acadbeat_core_seed_data.sql`
- `105_academic_practice_video_match_tables.sql`
- `210_academic_practice_video_resources.sql`
- `220_forum_announcements.sql`
- `230_chat_draw_guess_game_tables.sql`

## Runtime Usage

- `run_everything.php` imports:
  - `001_acadbeat_all_create_tables.sql`
  - `002_acadbeat_all_other_sql.sql`

## Recommended Initialization Order

1. Run `001_acadbeat_all_create_tables.sql`
2. Run `002_acadbeat_all_other_sql.sql`
3. Start services with `start_all.php` or `run_everything.php`

## Important Notes

- `001_acadbeat_all_create_tables.sql` first drops all managed project tables, then centralizes all `CREATE TABLE` statements in one place.
- `002_acadbeat_all_other_sql.sql` centralizes the remaining SQL in one place, including seed data, indexes, updates, video resources, and draw & guess word seed data.
- `002_acadbeat_all_other_sql.sql` is a full sample-data reset for forum, chat, challenge, vocab, and related modules.
- Do not rerun `002_acadbeat_all_other_sql.sql` if you only want to reset video-call data.
- If you only want to clear video-call runtime state, clear these tables only:
  - `peer_spaces` for `space_type = 'voice_room'`
  - `peer_space_members`
  - `peer_video_sessions`
  - `peer_video_match_queue`
  - `peer_video_session_events`
  - `peer_video_match_settings`
