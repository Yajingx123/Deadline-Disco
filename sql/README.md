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

## Recommended Initialization Order

1. Run `101_acadbeat_core_tables.sql`
2. Run `102_acadbeat_core_seed_data.sql`
3. Run `105_academic_practice_video_match_tables.sql`
4. Start services with `start_all.php` or `run_everything.php`

## Important Notes

- `102_acadbeat_core_seed_data.sql` is a full sample-data reset for forum, chat, challenge, vocab, and related modules.
- Do not rerun `102_acadbeat_core_seed_data.sql` if you only want to reset video-call data.
- `105_academic_practice_video_match_tables.sql` creates and updates video-call related tables only.
- `105_academic_practice_video_match_tables.sql` does not insert any sample video rooms, sessions, invites, queue rows, or runtime records.
- If you only want to clear video-call runtime state, clear these tables only:
  - `peer_spaces` for `space_type = 'voice_room'`
  - `peer_space_members`
  - `peer_video_sessions`
  - `peer_video_match_queue`
  - `peer_video_session_events`
  - `peer_video_match_settings`
