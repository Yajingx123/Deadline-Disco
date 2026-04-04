(function () {
  const API_ROOMS = '../api/video/video-call-rooms.php';
  const API_CHAT_USERS = '../../forum-project/api/chat-users.php';

  const state = {
    rooms: [],
    search: '',
    inviteSuggestions: [],
    filters: {
      visibility: 'all',
      availability: 'all',
    },
  };

  function qs(selector) {
    return document.querySelector(selector);
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, {
      method: options && options.method ? options.method : 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(options && options.body ? { 'Content-Type': 'application/json' } : {}),
      },
      body: options && options.body ? options.body : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false || data.status === 'error') {
      const error = new Error(data.message || 'Request failed.');
      error.status = response.status;
      throw error;
    }
    return data;
  }

  let inviteAbortController = null;

  async function loadInviteSuggestions(query) {
    const suggestionsEl = qs('#voiceRoomInviteSuggestions');
    if (!suggestionsEl) return;

    const keyword = String(query || '').trim();
    if (keyword.length < 1) {
      state.inviteSuggestions = [];
      suggestionsEl.innerHTML = '';
      suggestionsEl.classList.add('hidden');
      return;
    }

    if (inviteAbortController) {
      inviteAbortController.abort();
    }
    inviteAbortController = new AbortController();

    try {
      const response = await fetch(`${API_CHAT_USERS}?q=${encodeURIComponent(keyword)}`, {
        credentials: 'include',
        signal: inviteAbortController.signal,
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.ok === false) {
        throw new Error(data.message || 'Failed to search users.');
      }
      state.inviteSuggestions = Array.isArray(data.users) ? data.users : [];
      renderInviteSuggestions();
    } catch (error) {
      if (error.name === 'AbortError') return;
      state.inviteSuggestions = [];
      renderInviteSuggestions();
    }
  }

  function renderInviteSuggestions() {
    const suggestionsEl = qs('#voiceRoomInviteSuggestions');
    const inputEl = qs('#voiceRoomInviteInput');
    if (!suggestionsEl || !inputEl) return;

    const query = inputEl.value.trim().toLowerCase();
    if (!query || !state.inviteSuggestions.length) {
      suggestionsEl.innerHTML = '';
      suggestionsEl.classList.add('hidden');
      return;
    }

    suggestionsEl.innerHTML = '';
    state.inviteSuggestions.forEach((user) => {
      const option = document.createElement('button');
      option.type = 'button';
      option.className = 'voice-room-invite-option';
      option.innerHTML = `
        <strong>@${user.username || 'unknown'}</strong>
        <span>${user.email || 'User account'}</span>
      `;
      option.addEventListener('click', () => {
        inputEl.value = user.username || '';
        suggestionsEl.classList.add('hidden');
      });
      suggestionsEl.appendChild(option);
    });
    suggestionsEl.classList.remove('hidden');
  }

  function showToast(message, tone) {
    const toast = qs('#voiceRoomToast');
    if (!toast) return;
    toast.textContent = message;
    toast.className = `voice-room-toast ${tone === 'error' ? 'is-error' : 'is-success'}`;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
      toast.className = 'voice-room-toast hidden';
    }, 2600);
  }

  function openRoom(roomId) {
    const url = new URL('./room.php', window.location.href);
    url.searchParams.set('roomID', roomId);
    window.open(url.toString(), '_blank', 'noopener');
  }

  function filteredRooms() {
    const keyword = state.search.trim().toLowerCase();
    return state.rooms.filter((room) => {
      const availability = room.memberCount >= room.capacity ? 'full' : 'available';
      const matchesVisibility = state.filters.visibility === 'all' || room.visibility === state.filters.visibility;
      const matchesAvailability = state.filters.availability === 'all' || availability === state.filters.availability;
      const text = `${room.topic || ''} ${room.owner?.username || ''}`.toLowerCase();
      const matchesKeyword = !keyword || text.includes(keyword);
      return matchesVisibility && matchesAvailability && matchesKeyword;
    });
  }

  function renderRooms() {
    const grid = qs('#voiceRoomGrid');
    const empty = qs('#voiceRoomEmpty');
    const status = qs('#voiceRoomResultMeta');
    const count = document.getElementById('videoCallRoomCount');
    if (!grid || !empty || !status) return;

    const rooms = filteredRooms();
    if (count) count.textContent = String(state.rooms.length);
    status.textContent = rooms.length ? `${rooms.length} rooms available` : 'No rooms yet';
    grid.innerHTML = '';

    if (!rooms.length) {
      empty.classList.remove('hidden');
      return;
    }

    empty.classList.add('hidden');
    rooms.forEach((room) => {
      const availability = room.memberCount >= room.capacity ? 'full' : 'available';
      const joinDisabled = availability === 'full' || room.visibility === 'private';
      const joinLabel = availability === 'full'
        ? 'Room Full'
        : room.visibility === 'private'
          ? 'Invite Only'
          : 'Join Room';
      const card = document.createElement('article');
      card.className = `voice-room-card${availability === 'full' ? ' is-full' : ''}`;
      card.innerHTML = `
        <div class="voice-room-card-top">
          <span class="voice-room-card-id">${room.visibility === 'private' ? 'Private Room' : 'Public Room'}</span>
          <span class="voice-room-people">People ${room.memberCount}/${room.capacity}</span>
        </div>
        <h3>${room.topic || 'Untitled room'}</h3>
        <p class="voice-room-card-host">Hosted by @${room.owner?.username || 'unknown'}</p>
        <p class="voice-room-card-note">${room.visibility === 'private' ? 'Invite URL supported through direct message.' : 'Open room, URL invite, and browser tab launch enabled.'}</p>
        <div class="voice-room-tag-list">
          <span class="voice-room-tag voice-room-tag--${room.visibility}">${room.visibility === 'private' ? 'Private' : 'Public'}</span>
          <span class="voice-room-tag voice-room-tag--${availability}">${availability === 'full' ? 'Full' : 'Available'}</span>
          <span class="voice-room-tag">URL Invite</span>
        </div>
        <button type="button" class="voice-room-join-btn"${joinDisabled ? ' disabled' : ''}>${joinLabel}</button>
      `;
      const button = card.querySelector('.voice-room-join-btn');
      if (room.visibility === 'private') {
        button.title = 'Private rooms can only be opened from their invite link.';
      } else if (availability === 'full') {
        button.title = 'This room is already full.';
      } else {
        button.addEventListener('click', () => openRoom(room.roomId));
      }
      grid.appendChild(card);
    });
  }

  async function loadRooms() {
    try {
      const data = await fetchJson(API_ROOMS);
      state.rooms = Array.isArray(data.rooms) ? data.rooms : [];
      renderRooms();
    } catch (error) {
      state.rooms = [];
      renderRooms();
      showToast(error.message || 'Failed to load rooms.', 'error');
    }
  }

  function closeModal() {
    qs('#voiceRoomCreateModal')?.classList.add('hidden');
  }

  function openModal() {
    qs('#voiceRoomCreateModal')?.classList.remove('hidden');
    qs('#voiceRoomTopicInput')?.focus();
  }

  function initBackButton() {
    const btn = qs('.back-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      window.location.href = btn.dataset.backTarget || '../training.html';
    });
  }

  function initCreateFlow() {
    const modal = qs('#voiceRoomCreateModal');
    const form = qs('#voiceRoomCreateForm');
    const inviteInput = qs('#voiceRoomInviteInput');
    qs('#voiceRoomCreateBtn')?.addEventListener('click', openModal);
    qs('#voiceRoomOpenCreateBtn')?.addEventListener('click', openModal);
    qs('#voiceRoomCreateCancelBtn')?.addEventListener('click', closeModal);

    modal?.addEventListener('click', (event) => {
      if (event.target === modal) closeModal();
    });

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const topic = qs('#voiceRoomTopicInput')?.value.trim() || '';
      const inviteUsername = qs('#voiceRoomInviteInput')?.value.trim() || '';
      const visibility = document.querySelector('input[name="visibility"]:checked')?.value || 'public';

      if (!topic) {
        showToast('Topic is required.', 'error');
        qs('#voiceRoomTopicInput')?.focus();
        return;
      }

      try {
        const data = await fetchJson(API_ROOMS, {
          method: 'POST',
          body: JSON.stringify({ topic, visibility, inviteUsername }),
        });
        closeModal();
        showToast(inviteUsername ? 'Room created and invite sent.' : 'Room created.', 'success');
        if (data.room && data.room.roomId) {
          openRoom(data.room.roomId);
        }
        form.reset();
        const publicVisibility = document.querySelector('input[name="visibility"][value="public"]');
        if (publicVisibility) publicVisibility.checked = true;
        await loadRooms();
      } catch (error) {
        showToast(error.message || 'Failed to create room.', 'error');
      }
    });

    inviteInput?.addEventListener('input', (event) => {
      void loadInviteSuggestions(event.target.value || '');
    });

    inviteInput?.addEventListener('blur', () => {
      window.setTimeout(() => {
        qs('#voiceRoomInviteSuggestions')?.classList.add('hidden');
      }, 120);
    });

    inviteInput?.addEventListener('focus', () => {
      if (state.inviteSuggestions.length) {
        renderInviteSuggestions();
      }
    });
  }

  function initFilters() {
    document.querySelectorAll('.voice-room-chip-row[data-filter-group]').forEach((row) => {
      const group = row.dataset.filterGroup;
      row.addEventListener('click', (event) => {
        const target = event.target.closest('.voice-room-chip');
        if (!target || !group) return;
        state.filters[group] = target.dataset.value || 'all';
        row.querySelectorAll('.voice-room-chip').forEach((chip) => {
          chip.classList.toggle('is-active', chip === target);
        });
        renderRooms();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initBackButton();
    initCreateFlow();
    initFilters();
    qs('#voiceRoomRefreshBtn')?.addEventListener('click', () => { void loadRooms(); });
    qs('#voiceRoomSearch')?.addEventListener('input', (event) => {
      state.search = event.target.value || '';
      renderRooms();
    });
    void loadRooms();
  });
})();
