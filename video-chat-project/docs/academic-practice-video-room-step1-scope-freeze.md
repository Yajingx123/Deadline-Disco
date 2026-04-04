# Academic-Practice Video Room Step 1 Scope Freeze

Migration note:
The runtime code referenced in this document now lives under `video-chat-project/`.
Legacy `video-call/*` paths below are historical names.
The database SQL intentionally remains outside this folder at `video-call/sql/105_academic_practice_video_match_tables.sql`.

## Goal

Freeze the current replacement boundary for the live video-room implementation before later cleanup or schema work.

This document reflects the code that is live in this repository now. It no longer describes the retired queue-based match flow.

## Frozen Current Flow

Current live flow:

`Academic-Practice/training.html` -> `video-call/video-match.php` -> `video-call/api/video-room-*.php` -> `video-call/zego-call.php`

## Live Entry Page

- `Academic-Practice/training.html`
  - Mode 04 links to `../video-call/video-match.php`.
  - The card copy describes public/private room creation, invites, and room re-entry.

## Live Lobby Page

- `video-call/video-match.php`
  - The page acts as a room lobby, not a queue lobby.
  - Hosts create a room from a prepared topic.
  - Visibility is selected as `public` or `private`.
  - Hosts can refresh invite links for private rooms.
  - Hosts can remain on the lobby page while other users join later.
  - The page lists currently open rooms and exposes direct open/join actions when access rules allow it.
  - The page keeps the latest room re-entry URL in browser storage for quick return.

- `video-call/video-room-lobby.js`
  - Loads the signed-in user from `../Auth/backend/api/me.php`.
  - Lists rooms through `./api/video-room-list.php`.
  - Creates rooms through `./api/video-room-create.php`.
  - Creates or refreshes private invite links through `./api/video-room-invite.php`.
  - Ends the hosted room through `./api/video-room-end.php`.
  - Polls `./api/video-room-events.php` for host-side join/re-entry/member removal notifications.

## Live Room Page

- `video-call/zego-call.php`
  - Resolves room access from durable room membership, not from a transient match session.
  - Accepts `?room=<room_public_id>` as the primary room URL.
  - Still accepts `?roomID=<zego_room_id>` as a compatibility fallback while resolving the public room identifier.
  - Uses `./api/video-room-detail.php` to load room metadata and current access state.
  - Uses `./api/video-room-access.php` to grant access when a public room or valid invite allows entry.
  - Uses `./api/video-room-presence.php` to track `joining`, `in_room`, and `offline`.
  - Uses `./api/video-room-invite.php` for host-side private invite generation.
  - Uses `./api/video-room-member-remove.php` for host-only member removal.
  - Uses `./api/zego-token.php` only after room access has been verified.

## Frozen API Boundary

These endpoints are the active backend surface for the current room flow:

- `video-call/api/video-room-list.php`
  - Returns open rooms visible to the current user.
- `video-call/api/video-room-create.php`
  - Creates one durable room for the current host.
- `video-call/api/video-room-detail.php`
  - Returns room data and resolves compatibility lookups from legacy `roomID`.
- `video-call/api/video-room-access.php`
  - Grants membership for public rooms or valid private invites.
- `video-call/api/video-room-invite.php`
  - Creates or refreshes the active private invite for a hosted room.
- `video-call/api/video-room-events.php`
  - Streams room events for hosts and active members.
- `video-call/api/video-room-presence.php`
  - Updates room-page presence without destroying membership.
- `video-call/api/video-room-member-remove.php`
  - Removes a participant from an open room.
- `video-call/api/video-room-end.php`
  - Ends the hosted room and closes access.
- `video-call/api/zego-token.php`
  - Generates ZEGO tokens after room token access is validated.

## Frozen Shared Runtime

- `video-call/api/bootstrap.php`
  - Reuses the existing Academic-Practice bootstrap and DB connection.
- `video-call/api/video-helpers.php`
  - Contains the active room lifecycle, membership, invite, event, and access rules.
- `video-call/api/zego-config.php`
  - Loads ZEGO configuration and room-prefix settings.

## Frozen Database Boundary

The active room model now lives inside `video-call/sql/105_academic_practice_video_match_tables.sql`.

### Tables On The Active Path

- `peer_video_rooms`
  - Durable room row with host, visibility, topic snapshot, ZEGO room ID, and expiry.
- `peer_video_room_members`
  - Membership and presence split for reopen-by-URL behavior.
- `peer_video_room_invites`
  - Reusable invite links for private rooms.
- `peer_video_room_events`
  - Audit and notification stream for host polling.

### Legacy Tables Still Present In SQL But Not On The Live Path

- `peer_video_match_settings`
- `peer_video_sessions`
- `peer_video_match_queue`
- `peer_video_session_events`
- `peer_spaces`
- `peer_space_members`

These tables remain in the SQL file for historical compatibility only. The active PHP endpoints no longer call them.

## Replacement Scope For Later Steps

The following files remain the primary change surface for later room work:

- `Academic-Practice/training.html`
- `video-call/video-match.php`
- `video-call/video-room-lobby.js`
- `video-call/zego-call.php`
- `video-call/api/video-helpers.php`
- `video-call/api/video-room-list.php`
- `video-call/api/video-room-create.php`
- `video-call/api/video-room-detail.php`
- `video-call/api/video-room-access.php`
- `video-call/api/video-room-invite.php`
- `video-call/api/video-room-events.php`
- `video-call/api/video-room-presence.php`
- `video-call/api/video-room-member-remove.php`
- `video-call/api/video-room-end.php`
- `video-call/api/zego-token.php`
- `video-call/sql/105_academic_practice_video_match_tables.sql`

## Keep As Stable Foundations

- `Academic-Practice/api/_bootstrap.php`
- `Auth/backend/api/me.php`
- `video-call/api/bootstrap.php`
- `video-call/api/zego-config.php`
- `video-call/zego_server_assistant/src/ZEGO/*`

## Explicitly Out Of Scope

These files are not part of the current live room chain:

- `Academic-Practice/voice_room_discarded.html`
- `Academic-Practice/voice-room.js`

## Freeze Decisions

1. The live feature is a room-based video flow, not a legacy queue-based flow.
2. `video-call/video-match.php` is the durable lobby entry point.
3. `video-call/zego-call.php` authorizes by room membership and invite rules, not by a two-user session record.
4. The active API contract is `video-room-*`, not `video-match-*`.
5. Legacy queue/session helpers are historical only and should not be treated as live dependencies.

## Exit Criteria For Step 1

Step 1 is complete when later cleanup work treats this document as the current runtime boundary baseline.
