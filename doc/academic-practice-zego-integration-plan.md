# Academic-Practice Video Room Integration Snapshot

## Decision

The repository has already converged on a standalone `video-call` module for durable room-based video.

The relevant integration is now:

- entry from `Academic-Practice/training.html`
- lobby in `video-call/video-match.php`
- room APIs in `video-call/api/video-room-*.php`
- call page in `video-call/zego-call.php`
- ZEGO token generation in `video-call/api/zego-token.php`

## Why This Layout

This keeps the user-visible entry inside Academic-Practice while isolating the room feature into its own runtime module.

The live flow depends on:

- login/session validation
- room creation
- room listing
- invite generation
- room membership and presence tracking
- host event polling
- ZEGO token authorization

## Current Target Layout

- `Academic-Practice/training.html`
- `video-call/video-match.php`
- `video-call/video-room-lobby.js`
- `video-call/video-room-topics.js`
- `video-call/zego-call.php`
- `video-call/api/bootstrap.php`
- `video-call/api/video-helpers.php`
- `video-call/api/video-room-create.php`
- `video-call/api/video-room-list.php`
- `video-call/api/video-room-detail.php`
- `video-call/api/video-room-access.php`
- `video-call/api/video-room-invite.php`
- `video-call/api/video-room-events.php`
- `video-call/api/video-room-presence.php`
- `video-call/api/video-room-member-remove.php`
- `video-call/api/video-room-end.php`
- `video-call/api/zego-config.php`
- `video-call/api/zego-token.php`
- `video-call/zego_server_assistant/src/ZEGO/*`
- `video-call/sql/105_academic_practice_video_match_tables.sql`

## Reused Foundations

- `Academic-Practice/api/_bootstrap.php`
- `Auth/backend/api/me.php`
- `$_SESSION['auth_user']`
- the bundled ZEGO PHP helper library

## Runtime Behavior

### Lobby

`video-call/video-match.php` is responsible for:

- room creation from prepared topics
- public/private visibility selection
- open-room discovery
- private invite refresh
- host event notifications
- room re-entry URL persistence

### Room Page

`video-call/zego-call.php` is responsible for:

- loading room detail
- resolving access from membership or invite
- updating member presence
- host-side invite generation
- host-side member removal
- requesting ZEGO token only after room access is allowed

### Helper Layer

`video-call/api/video-helpers.php` is now a room-only helper file.

It owns:

- room lifecycle cleanup
- room/member/invite/event queries
- access control
- host moderation rules
- presence transitions
- ZEGO room access assertions

Historical queue/session helper code should not remain in this file.

## Database Boundary

Active runtime tables:

- `peer_video_rooms`
- `peer_video_room_members`
- `peer_video_room_invites`
- `peer_video_room_events`

Legacy tables still present in SQL but not on the live API path:

- `peer_video_match_settings`
- `peer_video_sessions`
- `peer_video_match_queue`
- `peer_video_session_events`
- `peer_spaces`
- `peer_space_members`

## Entry Integration

The user path is:

`Academic-Practice/training.html` -> `video-call/video-match.php` -> `video-call/zego-call.php`

The training card should continue to describe room creation, invite-only access, and room re-entry rather than any retired match flow.

## Ongoing Cleanup Direction

If more cleanup is needed, keep it focused on:

1. removing legacy queue/session code and comments
2. reducing legacy schema baggage inside the SQL file
3. keeping docs aligned with `video-call/*` as the live path
