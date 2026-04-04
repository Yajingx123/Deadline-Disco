# Academic-Practice Video Room Step 2 Data Model And State Flow

Migration note:
The runtime code referenced in this document now lives under `video-chat-project/`.
Legacy `video-call/*` paths below are historical names.
The database SQL intentionally remains outside this folder at `video-call/sql/105_academic_practice_video_match_tables.sql`.

## Goal

Document the active room-centric data model that supports:

- creating a room with a topic
- public or private visibility
- invite URL access
- host-only member removal
- one-hour room lifetime
- host waiting in the lobby while others join later
- reopening the same room URL before expiry

This file is a design and runtime reference for the current `video-call/*` implementation.

## Current Application Model

Primary runtime path:

`Academic-Practice/training.html` -> `video-call/video-match.php` -> `video-call/api/video-room-*.php` -> `video-call/zego-call.php`

The primary entity is the room itself. Membership, presence, invites, and ZEGO access all attach to that room record.

## Core Design Decisions

### 1. Separate app room identity from ZEGO room identity

Use two identifiers:

- `room_public_id`
  - stable application identifier used in URLs and room APIs
- `zego_room_id`
  - internal identifier used only for the ZEGO call token and room join

### 2. Separate membership from live presence

Closing the ZEGO page must not automatically remove a member from the room.

- membership controls whether the user may still return
- presence controls whether the user is currently on the room page

### 3. Topic is stored as a snapshot

The room stores both:

- `topic_key`
- `topic_label`

This keeps historical room labels stable even if the prepared topic list changes later.

### 4. Room lifetime is explicit

Rooms stay open for one hour from creation unless the host ends them earlier.

The effective closed outcome is:

- `ended` when the host closes the room
- `cancelled` when the room expires without real participation
- `expired` when the room expires after participation

## Active Tables

These tables are the live runtime model inside `video-call/sql/105_academic_practice_video_match_tables.sql`.

### 1. `peer_video_rooms`

Purpose:

- one durable room
- one host
- one topic snapshot
- one visibility rule
- one expiry deadline

Important columns:

- `room_id`
- `room_public_id`
- `zego_room_id`
- `host_user_id`
- `topic_key`
- `topic_label`
- `visibility`
- `status`
- `active_member_count`
- `peak_member_count`
- `expires_at`
- `ended_at`
- `ended_reason`

### 2. `peer_video_room_members`

Purpose:

- who belongs to the room
- host vs participant role
- durable membership across browser close
- live presence state

Important columns:

- `room_id`
- `user_id`
- `role`
- `membership_status`
- `presence_status`
- `joined_via`
- `invite_id`
- `joined_at`
- `last_seen_at`
- `last_entered_room_at`
- `last_left_room_at`
- `removed_by_user_id`
- `removed_at`
- `remove_reason`

### 3. `peer_video_room_invites`

Purpose:

- reusable invite URLs for private rooms
- invite audit and expiry
- host refresh without exposing a permanent secret on the room row

Important columns:

- `invite_id`
- `room_id`
- `invite_token`
- `created_by_user_id`
- `target_user_id`
- `status`
- `expires_at`
- `consumed_by_user_id`
- `consumed_at`
- `revoked_at`

### 4. `peer_video_room_events`

Purpose:

- host notifications
- room audit trail
- incremental polling from `afterEventId`

Important event types:

- `room_created`
- `member_joined`
- `member_reentered`
- `member_presence_left`
- `member_removed`
- `invite_created`
- `invite_consumed`
- `room_ended`
- `room_cancelled`
- `room_expired`

## Active State Flow

### Create Room

1. Host opens `video-call/video-match.php`.
2. Frontend posts to `video-call/api/video-room-create.php`.
3. Backend creates `peer_video_rooms` plus the host row in `peer_video_room_members`.
4. Backend records `room_created` in `peer_video_room_events`.

### List Open Rooms

1. Lobby calls `video-call/api/video-room-list.php`.
2. Backend returns currently open rooms, ordered with the host's own room first.
3. Access flags are computed per viewer:
   - `canOpenRoomPage`
   - `canJoinDirectly`
   - `requiresInvite`
   - `joinMode`

### Private Invite

1. Host calls `video-call/api/video-room-invite.php`.
2. Backend reuses the active invite or creates a new one.
3. Invite rows live in `peer_video_room_invites`.
4. The generated room URL is `./zego-call.php?room=<room_public_id>&invite=<invite_token>`.

### Join Or Re-Enter Room

1. User opens `video-call/zego-call.php?room=<room_public_id>`.
2. Room page loads `video-call/api/video-room-detail.php`.
3. If direct access is not already allowed, the page can call `video-call/api/video-room-access.php`.
4. Public rooms grant access directly.
5. Private rooms require an active valid invite unless the user is already an active member.
6. Membership is created or reused in `peer_video_room_members`.

### Presence Tracking

1. Room page posts to `video-call/api/video-room-presence.php`.
2. Presence changes among:
   - `joining`
   - `in_room`
   - `offline`
3. Membership remains active while the room stays open.
4. Host notifications are written to `peer_video_room_events`.

### Host Moderation

1. Host can remove a participant through `video-call/api/video-room-member-remove.php`.
2. Backend changes membership status to `removed`.
3. Backend refreshes room member counts.
4. Backend records `member_removed`.

### End Or Expire Room

1. Host can end the room through `video-call/api/video-room-end.php`.
2. Expiry cleanup also runs inside the helper lifecycle checks.
3. Open invites are expired when the room closes.
4. Active members are moved to offline presence when the room closes.

## Legacy Tables

The following tables still exist in the SQL file but are not on the live PHP path:

- `peer_video_match_settings`
- `peer_video_sessions`
- `peer_video_match_queue`
- `peer_video_session_events`
- `peer_spaces`
- `peer_space_members`

They should be treated as legacy schema baggage, not as extension points for current room work.

## API Surface Backed By This Model

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

## Practical Consequences

1. The lobby is a room lobby, not a queue lobby.
2. Room access is driven by room membership and invite rules.
3. The ZEGO page may be reopened by URL while the room is still open.
4. A host can wait in the lobby without entering the call immediately.
5. Private rooms remain visible in the lobby, but direct entry is blocked without invite access.
