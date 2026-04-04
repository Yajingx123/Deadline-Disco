# Video Chat Project

This directory contains the full non-database video chat stack.

## Included

- `video-match.php`
  Video room lobby entry
- `zego-call.php`
  In-room call page
- `video-room-lobby.js`
  Lobby client logic
- `video-room-topics.js`
  Lobby topic presets
- `api/`
  PHP backend APIs for room creation, access, invites, presence, events, and ZEGO token generation
- `realtime/`
  Node.js realtime relay service previously stored in `voice-room-server/`
- `zego_server_assistant/`
  ZEGO PHP helper library
- `docs/`
  Video-room architecture and migration notes

## Database

Database files remain outside this folder by request.

- `video-call/sql/105_academic_practice_video_match_tables.sql`

## Entry

Current training entry points now link here:

- `../video-chat-project/video-match.php`
