(function () {
  const AUTH_ME_URL = "/Auth/backend/api/me.php";
  const ROOM_LIST_URL = "/video-chat-project/api/video-room-list.php";
  const ROOM_CREATE_URL = "/video-chat-project/api/video-room-create.php";
  const ROOM_INVITE_URL = "/video-chat-project/api/video-room-invite.php";
  const ROOM_END_URL = "/video-chat-project/api/video-room-end.php";
  const ROOM_EVENTS_URL = "/video-chat-project/api/video-room-events.php";
  const ROOM_REENTRY_STORAGE_KEY = "acadbeat.video_room.latest_reentry";

  const state = {
    me: null,
    hostedRoom: null,
    hostedInvite: null,
    latestHostedEventId: 0,
    pollTimer: null,
  };

  const els = {
    authStatus: document.getElementById("authStatus"),
    identityName: document.getElementById("identityName"),
    identityCopy: document.getElementById("identityCopy"),
    hostWatchCopy: document.getElementById("hostWatchCopy"),
    stageTitle: document.getElementById("stageTitle"),
    stageCopy: document.getElementById("stageCopy"),
    topicSelect: document.getElementById("topicSelect"),
    topicPreview: document.getElementById("topicPreview"),
    createRoomBtn: document.getElementById("createRoomBtn"),
    refreshRoomsBtn: document.getElementById("refreshRoomsBtn"),
    createInviteBtn: document.getElementById("createInviteBtn"),
    endHostedRoomBtn: document.getElementById("endHostedRoomBtn"),
    hostedRoomValue: document.getElementById("hostedRoomValue"),
    stageNote: document.getElementById("stageNote"),
    hostedLinkValue: document.getElementById("hostedLinkValue"),
    hostedLinkMeta: document.getElementById("hostedLinkMeta"),
    openHostedRoomBtn: document.getElementById("openHostedRoomBtn"),
    copyHostedLinkBtn: document.getElementById("copyHostedLinkBtn"),
    roomListContainer: document.getElementById("roomListContainer"),
    recentRoomValue: document.getElementById("recentRoomValue"),
    recentRoomMeta: document.getElementById("recentRoomMeta"),
    reenterRoomBtn: document.getElementById("reenterRoomBtn"),
    copyRoomUrlBtn: document.getElementById("copyRoomUrlBtn"),
    toastStack: document.getElementById("toastStack"),
  };

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>\"']/g, (char) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      "\"": "&quot;",
      "'": "&#39;",
    }[char] || char));
  }

  function formatDateTime(value) {
    if (!value) {
      return "Unknown";
    }

    const date = new Date(value.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return date.toLocaleString();
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/json",
        ...(options.headers || {}),
      },
      ...options,
    });

    const text = await response.text();
    let payload = null;
    try {
      payload = text ? JSON.parse(text) : {};
    } catch (error) {
      payload = {};
    }

    if (!response.ok || payload.ok === false || payload.status === "error") {
      const err = new Error(payload.message || payload.error || `Request failed: ${response.status}`);
      err.status = response.status;
      err.payload = payload;
      throw err;
    }

    return payload;
  }

  function roomUrl(room, inviteUrl = "") {
    const target = inviteUrl || room?.roomPageUrl || "";
    if (!target) {
      return "";
    }

    return new URL(String(target), window.location.href).toString();
  }

  function readStoredRoomReentry() {
    try {
      const raw = window.localStorage.getItem(ROOM_REENTRY_STORAGE_KEY);
      if (!raw) {
        return null;
      }
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === "object" ? parsed : null;
    } catch (error) {
      return null;
    }
  }

  async function copyText(value) {
    if (!value) {
      return false;
    }

    try {
      await navigator.clipboard.writeText(value);
      return true;
    } catch (error) {
      return false;
    }
  }

  function showToast(title, body, timeout = 5000) {
    const toast = document.createElement("div");
    toast.className = "toast";
    toast.innerHTML = `<h3>${escapeHtml(title)}</h3><p>${escapeHtml(body)}</p>`;
    els.toastStack.appendChild(toast);
    window.setTimeout(() => {
      toast.remove();
    }, timeout);
  }

  function eventSummary(event) {
    const actor = event.actor?.displayName || event.actor?.username || "Someone";
    const target = event.target?.displayName || event.target?.username || "a member";

    switch (event.type) {
      case "member_joined":
        return ["Member joined", `${actor} entered your room.`];
      case "member_reentered":
        return ["Member re-entered", `${actor} came back to the room.`];
      case "member_presence_left":
        return ["Member left page", `${actor} left the call page.`];
      case "member_removed":
        return ["Member removed", `${target} was removed from the room.`];
      case "room_ended":
        return ["Room ended", "This hosted room was ended."];
      case "room_cancelled":
        return ["Room cancelled", "The hosted room expired with only the host present."];
      case "room_expired":
        return ["Room expired", "The hosted room reached its one-hour lifetime."];
      default:
        return null;
    }
  }

  function topicOptions() {
    if (Array.isArray(window.VideoRoomTopics) && window.VideoRoomTopics.length) {
      return window.VideoRoomTopics;
    }

    if (Array.isArray(window.PracticeData?.discussionTopics) && window.PracticeData.discussionTopics.length) {
      return window.PracticeData.discussionTopics;
    }

    return [];
  }

  function selectedTopic() {
    const topics = topicOptions();
    const topicId = String(els.topicSelect.value || "");
    return topics.find((item) => item.id === topicId) || null;
  }

  function updateTopicPreview() {
    const topic = selectedTopic();
    els.topicPreview.textContent = topic
      ? `${topic.title}. ${topic.description}`
      : "Topic details will appear here after the prepared topic list loads.";
  }

  function populateTopics() {
    const topics = topicOptions();
    if (!topics.length) {
      els.topicSelect.innerHTML = '<option value="">No topics available</option>';
      els.topicSelect.disabled = true;
      els.createRoomBtn.disabled = true;
      updateTopicPreview();
      return;
    }

    els.topicSelect.innerHTML = topics.map((topic) =>
      `<option value="${escapeHtml(topic.id)}">${escapeHtml(topic.title)}</option>`
    ).join("");
    els.topicSelect.disabled = false;
    updateTopicPreview();
  }

  function setAuthState(loggedIn, message) {
    els.authStatus.className = `status ${loggedIn ? "success" : "danger"}`;
    els.authStatus.textContent = loggedIn ? "Session Ready" : "Login Required";
    els.identityCopy.textContent = message;
    els.createRoomBtn.disabled = !loggedIn || !selectedTopic();
    els.refreshRoomsBtn.disabled = !loggedIn;
  }

  function visibilityValue() {
    const checked = document.querySelector('input[name="roomVisibility"]:checked');
    return checked ? String(checked.value || "public") : "public";
  }

  function renderHostedState() {
    const room = state.hostedRoom;
    const inviteUrl = state.hostedInvite?.inviteUrl || "";
    const canonicalRoomUrl = room ? roomUrl(room) : "";
    const visibleLink = inviteUrl || canonicalRoomUrl;

    if (!room) {
      els.hostedRoomValue.textContent = "No active hosted room yet.";
      els.stageNote.textContent = "Hosts can stay on this page, wait for others to enter, and open the actual room only when they want to join the call.";
      els.hostedLinkValue.textContent = "No hosted room link yet.";
      els.hostedLinkMeta.textContent = "Create a room and its current room URL or invite URL will appear here.";
      els.openHostedRoomBtn.hidden = true;
      els.openHostedRoomBtn.href = "/video-chat-project/video-match.php";
      els.copyHostedLinkBtn.disabled = true;
      els.createInviteBtn.disabled = true;
      els.createInviteBtn.textContent = "Create Invite";
      els.endHostedRoomBtn.disabled = true;
      return;
    }

    const statusText = room.visibility === "private" ? "Private room" : "Public room";
    els.hostedRoomValue.textContent = `${room.topic?.label || "Untitled room"} | ${statusText} | ${room.memberCount || 1} member(s) | expires ${formatDateTime(room.expiresAt)}`;
    els.stageNote.textContent = room.visibility === "private"
      ? "Invite-only room is active. Create or refresh the invite link before sharing it."
      : "Public room is active. Anyone logged in can join directly from the room list.";
    els.hostedLinkValue.textContent = visibleLink || "Hosted room URL unavailable.";
    els.hostedLinkMeta.textContent = inviteUrl
      ? `Invite active until ${formatDateTime(state.hostedInvite?.expiresAt)}.`
      : "Open the room directly or create an invite if this is a private room.";
    els.openHostedRoomBtn.hidden = false;
    els.openHostedRoomBtn.href = canonicalRoomUrl || "/video-chat-project/video-match.php";
    els.copyHostedLinkBtn.disabled = !visibleLink;
    els.createInviteBtn.disabled = room.visibility !== "private";
    els.createInviteBtn.textContent = inviteUrl ? "Refresh Invite" : "Create Invite";
    els.endHostedRoomBtn.disabled = false;
  }

  function renderStoredReentry() {
    const stored = readStoredRoomReentry();
    if (!stored?.roomPageUrl) {
      els.recentRoomValue.textContent = "No saved room URL yet.";
      els.recentRoomMeta.textContent = "Open a durable room once and the latest re-entry URL will be kept here for quick return.";
      els.reenterRoomBtn.disabled = true;
      els.copyRoomUrlBtn.disabled = true;
      return;
    }

    const url = stored.inviteUrl || stored.shareUrl || stored.roomPageUrl;
    els.recentRoomValue.textContent = `${stored.topicLabel || "Video room"} | ${url}`;
    els.recentRoomMeta.textContent = `Saved ${formatDateTime(stored.savedAt)}. Visibility: ${stored.visibility || "public"}.`;
    els.reenterRoomBtn.disabled = false;
    els.copyRoomUrlBtn.disabled = false;
    els.reenterRoomBtn.dataset.url = url;
    els.copyRoomUrlBtn.dataset.url = url;
  }

  function roomActionMarkup(room) {
    const directUrl = roomUrl(room);
    if (room?.access?.canOpenRoomPage) {
      return `<a class="btn primary" href="${escapeHtml(directUrl)}">Open Room</a>`;
    }

    if (room?.access?.canJoinDirectly) {
      return `<a class="btn primary" href="${escapeHtml(directUrl)}">Join Room</a>`;
    }

    if (room?.access?.requiresInvite) {
      return `<button class="btn" type="button" disabled>Invite Required</button>`;
    }

    return `<button class="btn" type="button" disabled>Unavailable</button>`;
  }

  function renderRoomList(rooms) {
    if (!rooms.length) {
      els.roomListContainer.innerHTML = '<div class="empty-box">No open rooms yet. Create the first one.</div>';
      return;
    }

    els.roomListContainer.innerHTML = rooms.map((room) => {
      const chips = [
        `<span class="room-chip ${room.visibility === "private" ? "private" : "public"}">${escapeHtml(room.visibility || "public")}</span>`,
      ];

      if (state.me && Number(room.host?.userId || 0) === Number(state.me.user_id || state.me.userId || 0)) {
        chips.push('<span class="room-chip host">Hosted By You</span>');
      }

      return `
        <div class="room-row">
          <div class="room-row-head">
            ${chips.join("")}
          </div>
          <h2>${escapeHtml(room.topic?.label || "Untitled room")}</h2>
          <p>Host: ${escapeHtml(room.host?.displayName || room.host?.username || "Unknown")} | Members: ${Number(room.memberCount || 0)} | Expires: ${escapeHtml(formatDateTime(room.expiresAt))}</p>
          <p>${escapeHtml(room.access?.joinMode === "invite_required" ? "Visible in the lobby, but invite access is required." : "Room access is available from the lobby.")}</p>
          <div class="room-row-meta">${roomActionMarkup(room)}</div>
        </div>
      `;
    }).join("");
  }

  async function refreshRooms({ silent = false } = {}) {
    if (!state.me) {
      return;
    }

    try {
      const payload = await fetchJson(ROOM_LIST_URL);
      const rooms = Array.isArray(payload.rooms) ? payload.rooms : [];
      renderRoomList(rooms);

      const myUserId = Number(state.me.user_id || state.me.userId || 0);
      state.hostedRoom = rooms.find((room) => Number(room.host?.userId || 0) === myUserId) || null;
      if (!state.hostedRoom) {
        state.hostedInvite = null;
        state.latestHostedEventId = 0;
      }
      renderHostedState();
      renderStoredReentry();
    } catch (error) {
      if (!silent) {
        els.roomListContainer.innerHTML = `<div class="empty-box">${escapeHtml(error.message || "Unable to load open rooms.")}</div>`;
      }
    }
  }

  async function createRoom() {
    const topic = selectedTopic();
    if (!topic) {
      return;
    }

    els.createRoomBtn.disabled = true;

    try {
      const payload = await fetchJson(ROOM_CREATE_URL, {
        method: "POST",
        body: JSON.stringify({
          topicKey: topic.id,
          topicLabel: topic.title,
          visibility: visibilityValue(),
        }),
      });
      state.hostedRoom = payload.room || null;
      state.hostedInvite = null;
      state.latestHostedEventId = 0;
      renderHostedState();
      await refreshRooms({ silent: true });
      showToast("Room created", `${topic.title} is ready.`);
    } catch (error) {
      const room = error.payload?.room || null;
      if (error.status === 409 && room) {
        state.hostedRoom = room;
        renderHostedState();
        showToast("Hosted room reused", "You already had an active room, so that room remains active.");
      } else {
        showToast("Create room failed", error.message || "Unable to create the room.");
      }
    } finally {
      els.createRoomBtn.disabled = false;
      renderHostedState();
      await refreshRooms({ silent: true });
    }
  }

  async function createInvite() {
    if (!state.hostedRoom) {
      return;
    }

    els.createInviteBtn.disabled = true;
    try {
      const payload = await fetchJson(ROOM_INVITE_URL, {
        method: "POST",
        body: JSON.stringify({
          room: state.hostedRoom.roomPublicId,
        }),
      });
      state.hostedInvite = payload.invite || null;
      renderHostedState();
      showToast("Invite ready", "Private invite link refreshed.");
    } catch (error) {
      showToast("Invite failed", error.message || "Unable to create the invite link.");
    } finally {
      renderHostedState();
    }
  }

  async function endHostedRoom() {
    if (!state.hostedRoom) {
      return;
    }

    els.endHostedRoomBtn.disabled = true;
    try {
      await fetchJson(ROOM_END_URL, {
        method: "POST",
        body: JSON.stringify({
          room: state.hostedRoom.roomPublicId,
        }),
      });
      showToast("Room ended", "The hosted room has been closed.");
      state.hostedRoom = null;
      state.hostedInvite = null;
      state.latestHostedEventId = 0;
      renderHostedState();
      await refreshRooms({ silent: true });
    } catch (error) {
      showToast("End room failed", error.message || "Unable to end the hosted room.");
    }
  }

  async function pollHostedEvents() {
    if (!state.hostedRoom) {
      return;
    }

    try {
      const url = new URL(ROOM_EVENTS_URL, window.location.href);
      url.searchParams.set("room", state.hostedRoom.roomPublicId);
      url.searchParams.set("after", String(state.latestHostedEventId || 0));
      url.searchParams.set("limit", "20");
      const payload = await fetchJson(url.toString());
      const events = Array.isArray(payload.events) ? payload.events : [];
      state.latestHostedEventId = Number(payload.latestEventId || state.latestHostedEventId || 0);
      events.forEach((event) => {
        const summary = eventSummary(event);
        if (summary) {
          showToast(summary[0], summary[1], 4500);
        }
      });
    } catch (error) {
      // Ignore intermittent polling failures.
    }
  }

  function startPolling() {
    if (state.pollTimer) {
      window.clearInterval(state.pollTimer);
    }

    state.pollTimer = window.setInterval(async () => {
      await refreshRooms({ silent: true });
      await pollHostedEvents();
    }, 10000);
  }

  async function init() {
    populateTopics();
    renderStoredReentry();
    updateTopicPreview();

    els.topicSelect.addEventListener("change", () => {
      updateTopicPreview();
      els.createRoomBtn.disabled = !state.me || !selectedTopic();
    });

    document.querySelectorAll('input[name="roomVisibility"]').forEach((input) => {
      input.addEventListener("change", () => {
        renderHostedState();
      });
    });

    els.createRoomBtn.addEventListener("click", createRoom);
    els.refreshRoomsBtn.addEventListener("click", () => refreshRooms());
    els.createInviteBtn.addEventListener("click", createInvite);
    els.endHostedRoomBtn.addEventListener("click", endHostedRoom);
    els.copyHostedLinkBtn.addEventListener("click", async () => {
      const success = await copyText(els.hostedLinkValue.textContent);
      showToast(success ? "Link copied" : "Copy failed", success ? "The hosted room link is on your clipboard." : "Clipboard access was not available.");
    });
    els.reenterRoomBtn.addEventListener("click", () => {
      const url = els.reenterRoomBtn.dataset.url || "";
      if (url) {
        window.location.href = url;
      }
    });
    els.copyRoomUrlBtn.addEventListener("click", async () => {
      const url = els.copyRoomUrlBtn.dataset.url || "";
      const success = await copyText(url);
      showToast(success ? "URL copied" : "Copy failed", success ? "The saved re-entry URL is on your clipboard." : "Clipboard access was not available.");
    });

    try {
      const mePayload = await fetchJson(AUTH_ME_URL);
      state.me = mePayload.user || null;
    } catch (error) {
      state.me = null;
    }

    if (!state.me) {
      els.identityName.textContent = "Guest";
      els.hostWatchCopy.textContent = "Login is required before room creation and room notifications can start.";
      setAuthState(false, "Your login session is missing or expired. Sign in before using the video chat lobby.");
      els.roomListContainer.innerHTML = '<div class="empty-box">Log in to load the room list.</div>';
      return;
    }

    const displayName = state.me.username || `User ${state.me.user_id || state.me.userId || ""}`;
    els.identityName.textContent = displayName;
    els.hostWatchCopy.textContent = "Hosted-room notifications poll the durable room event stream every few seconds.";
    setAuthState(true, "Your login is verified. You can create a room, browse open rooms, or re-enter a previous room URL.");

    await refreshRooms();
    startPolling();
  }

  void init();
})();
