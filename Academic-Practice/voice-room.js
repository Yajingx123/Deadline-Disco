(function () {
  const ROOMS = [
    { id: 'PSR-101', topic: 'Introduce Your Major To A New Classmate', people: 1, capacity: 2, difficulty: 'easy', availability: 'available', duration: '3min' },
    { id: 'PSR-102', topic: 'Discuss Tips For Understanding Lecture Structure', people: 0, capacity: 2, difficulty: 'easy', availability: 'available', duration: '5min' },
    { id: 'PSR-103', topic: 'Explain How You Would Prepare For Office Hours', people: 2, capacity: 2, difficulty: 'intermediate', availability: 'full', duration: '5min' },
    { id: 'PSR-104', topic: 'Compare Two Note-taking Strategies For Fast Lectures', people: 1, capacity: 2, difficulty: 'intermediate', availability: 'available', duration: '10min' },
    { id: 'PSR-105', topic: 'Respond To A Professor\'s Research Question Politely', people: 0, capacity: 2, difficulty: 'advanced', availability: 'available', duration: '10min' },
    { id: 'PSR-106', topic: 'Summarize A Short Academic Video With Clear Structure', people: 2, capacity: 2, difficulty: 'advanced', availability: 'full', duration: '3min' },
  ];

  const state = {
    filters: {
      difficulty: 'all',
      availability: 'all',
      duration: 'all',
      search: '',
    },
    countdownTimer: null,
  };

  function qs(selector) {
    return document.querySelector(selector);
  }

  function qsa(selector) {
    return Array.from(document.querySelectorAll(selector));
  }

  function capitalize(value) {
    const raw = String(value || '').trim();
    return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : '';
  }

  function formatCountdown(totalSeconds) {
    const seconds = Math.max(0, totalSeconds);
    const minutes = Math.floor(seconds / 60);
    const remain = seconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(remain).padStart(2, '0')}`;
  }

  function buildSessionUrl(config) {
    const params = new URLSearchParams();
    params.set('topic', config.topic || 'Pair Speaking Topic');
    params.set('duration', config.duration || '5min');
    params.set('difficulty', config.difficulty || 'intermediate');
    params.set('leftName', config.leftName || 'You');
    params.set('rightName', config.rightName || 'Partner');
    return `voice_room_session.html?${params.toString()}`;
  }

  function initBackButton() {
    const btn = qs('.back-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (window.history.length > 1) {
        window.history.back();
        return;
      }
      window.location.href = btn.dataset.backTarget || 'training.html';
    });
  }

  function renderRooms() {
    const gridEl = qs('#voiceRoomGrid');
    const emptyEl = qs('#voiceRoomEmpty');
    const metaEl = qs('#voiceRoomResultMeta');
    if (!gridEl || !emptyEl || !metaEl) return;

    const keyword = state.filters.search.trim().toLowerCase();
    const rooms = ROOMS.filter(function (room) {
      const matchesSearch = !keyword || room.topic.toLowerCase().includes(keyword);
      const matchesDifficulty = state.filters.difficulty === 'all' || room.difficulty === state.filters.difficulty;
      const matchesAvailability = state.filters.availability === 'all' || room.availability === state.filters.availability;
      const matchesDuration = state.filters.duration === 'all' || room.duration === state.filters.duration;
      return matchesSearch && matchesDifficulty && matchesAvailability && matchesDuration;
    });

    metaEl.textContent = rooms.length ? `${rooms.length} rooms available` : 'No rooms yet';
    gridEl.innerHTML = '';

    if (!rooms.length) {
      emptyEl.classList.remove('hidden');
      return;
    }

    emptyEl.classList.add('hidden');

    rooms.forEach(function (room) {
      const card = document.createElement('article');
      card.className = `voice-room-card${room.availability === 'full' ? ' is-full' : ''}`;
      card.innerHTML =
        `<div class="voice-room-card-top">` +
          `<span class="voice-room-card-id">${room.id}</span>` +
          `<span class="voice-room-people">People ${room.people}/${room.capacity}</span>` +
        `</div>` +
        `<h3>${room.topic}</h3>` +
        `<p>Topic for speaking practice</p>` +
        `<div class="voice-room-tag-list">` +
          `<span class="voice-room-tag">${capitalize(room.difficulty)}</span>` +
          `<span class="voice-room-tag voice-room-tag--${room.availability}">${capitalize(room.availability)}</span>` +
          `<span class="voice-room-tag">${room.duration}</span>` +
        `</div>` +
        `<button type="button" class="voice-room-join-btn"${room.availability === 'full' ? ' disabled' : ''}>${room.availability === 'full' ? 'Room Full' : 'Join Room'}</button>`;
      gridEl.appendChild(card);

      const joinBtn = card.querySelector('.voice-room-join-btn');
      if (joinBtn && room.availability !== 'full') {
        joinBtn.addEventListener('click', function () {
          window.location.href = buildSessionUrl({
            topic: room.topic,
            duration: room.duration,
            difficulty: room.difficulty,
            leftName: 'You',
            rightName: room.people > 0 ? 'Partner' : 'Waiting...',
          });
        });
      }
    });
  }

  function initLobbyFilters() {
    qsa('.voice-room-chip-row').forEach(function (row) {
      const group = row.dataset.filterGroup;
      row.addEventListener('click', function (event) {
        const target = event.target.closest('.voice-room-chip');
        if (!target || !group) return;
        state.filters[group] = target.dataset.value || 'all';
        row.querySelectorAll('.voice-room-chip').forEach(function (chip) {
          chip.classList.toggle('is-active', chip === target);
        });
        renderRooms();
      });
    });

    const searchEl = qs('#voiceRoomSearch');
    if (searchEl) {
      searchEl.addEventListener('input', function () {
        state.filters.search = searchEl.value || '';
        renderRooms();
      });
    }
  }

  function initCreateRoomModal() {
    const modal = qs('#voiceRoomCreateModal');
    const openBtn = qs('#voiceRoomCreateBtn');
    const cancelBtn = qs('#voiceRoomCreateCancelBtn');
    const form = qs('#voiceRoomCreateForm');
    const topicInput = qs('#voiceRoomTopicInput');
    const durationSelect = qs('#voiceRoomDurationSelect');
    const difficultySelect = qs('#voiceRoomDifficultySelect');

    if (!modal || !openBtn || !cancelBtn || !form || !topicInput || !durationSelect || !difficultySelect) {
      return;
    }

    function openModal() {
      modal.classList.remove('hidden');
      topicInput.focus();
    }

    function closeModal() {
      modal.classList.add('hidden');
    }

    openBtn.addEventListener('click', openModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal();
      }
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      const topic = topicInput.value.trim();
      if (!topic) {
        topicInput.focus();
        return;
      }

      window.location.href = buildSessionUrl({
        topic,
        duration: durationSelect.value,
        difficulty: difficultySelect.value,
        leftName: 'You',
        rightName: 'Waiting...',
      });
    });
  }

  function durationToSeconds(value) {
    const raw = String(value || '').trim().toLowerCase();
    const parsed = Number.parseInt(raw, 10);
    if (!Number.isFinite(parsed) || parsed <= 0) {
      return 300;
    }
    return parsed * 60;
  }

  function openTimeupModal(title, message) {
    const modal = qs('#voiceRoomTimeupModal');
    const titleEl = qs('#voiceRoomTimeupTitle');
    const messageEl = modal ? modal.querySelector('p') : null;
    if (!modal || !titleEl || !messageEl) return;
    titleEl.textContent = title;
    messageEl.textContent = message;
    modal.classList.remove('hidden');
  }

  function initSessionPage() {
    const params = new URLSearchParams(window.location.search);
    const topicEl = qs('#voiceRoomSessionTopic');
    const stateEl = qs('#voiceRoomSessionState');
    const timerEl = qs('#voiceRoomCountdown');
    const userOneEl = qs('#voiceRoomUserOne');
    const userTwoEl = qs('#voiceRoomUserTwo');
    const userOneInitialEl = qs('#voiceRoomUserOneInitial');
    const userTwoInitialEl = qs('#voiceRoomUserTwoInitial');
    const exitBtn = qs('#voiceRoomExitBtn');
    const modalBtn = qs('#voiceRoomTimeupBtn');

    const topic = params.get('topic') || 'Pair Speaking Topic';
    const duration = params.get('duration') || '5min';
    const leftName = params.get('leftName') || 'You';
    const rightName = params.get('rightName') || 'Partner';

    if (topicEl) topicEl.textContent = topic;
    if (stateEl) stateEl.textContent = 'UI preview mode for the pair speaking room.';
    if (timerEl) timerEl.textContent = formatCountdown(durationToSeconds(duration));
    if (userOneEl) userOneEl.textContent = leftName;
    if (userTwoEl) userTwoEl.textContent = rightName;
    if (userOneInitialEl) userOneInitialEl.textContent = leftName.slice(0, 1).toUpperCase();
    if (userTwoInitialEl) userTwoInitialEl.textContent = rightName.slice(0, 1).toUpperCase();

    window.clearInterval(state.countdownTimer);
    let secondsLeft = durationToSeconds(duration);
    state.countdownTimer = window.setInterval(function () {
      secondsLeft -= 1;
      if (timerEl) timerEl.textContent = formatCountdown(secondsLeft);
      if (secondsLeft <= 0) {
        window.clearInterval(state.countdownTimer);
        openTimeupModal('Time is up!', 'Go and enjoy your next conversation');
      }
    }, 1000);

    function backToLobby() {
      window.clearInterval(state.countdownTimer);
      window.location.href = 'voice_room.html';
    }

    if (exitBtn) {
      exitBtn.addEventListener('click', backToLobby);
    }
    if (modalBtn) {
      modalBtn.addEventListener('click', backToLobby);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initBackButton();

    const page = document.body.dataset.page;
    if (page === 'voice-room-home') {
      initLobbyFilters();
      initCreateRoomModal();
      renderRooms();
      return;
    }

    if (page === 'voice-room-session') {
      initSessionPage();
    }
  });
})();
