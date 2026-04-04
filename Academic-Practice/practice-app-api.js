(function () {
  const data = window.PracticeDataAPI;
  const LISTENING_RECORD_API = "./api/save-record.php";
  const INTEGRATED_RECORD_API = "./api/save-integrated-record.php";
  const L = window.ACADBEAT_LOCAL || {};
  const MAIN_ORIGIN = L.mainOrigin || window.location.origin;
  const FORUM_COMPOSE_URL = `${MAIN_ORIGIN}/forum-project/dist/index.html?view=forum&compose=1`;
  const FORUM_PREFILL_WINDOW_NAME_KEY = "__acadbeat_forum_prefill__";
  const CHAT_API_BASE = `${MAIN_ORIGIN}/forum-project/api`;
  if (!data) {
    return;
  }

  function qs(selector) {
    return document.querySelector(selector);
  }

  function escapeHtml(value) {
    return String(value == null ? "" : "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function getParam(name, fallback) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name) || fallback;
  }

  function getCookie(name) {
    const prefix = name + "=";
    const pair = document.cookie.split("; ").find(function (item) { return item.indexOf(prefix) === 0; });
    return pair ? decodeURIComponent(pair.slice(prefix.length)) : "";
  }

  // Listening flows should stay in classic UI unless URL explicitly asks for godot.
  const UI_MODE = getParam("ui", "");

  function withUiMode(url) {
    if (UI_MODE !== "godot") {
      return url;
    }
    var u = String(url);
    if (u.indexOf("ui=godot") !== -1) {
      return u;
    }
    return u + (u.indexOf("?") === -1 ? "?" : "&") + "ui=godot";
  }

  function buildAbsoluteProjectUrl(pathWithQuery) {
    return new URL(pathWithQuery, window.location.origin + "/").toString();
  }

  async function forumApiFetch(path, options) {
    const response = await fetch(CHAT_API_BASE + path, {
      method: (options && options.method) || "GET",
      headers: { "Content-Type": "application/json", ...((options && options.headers) || {}) },
      credentials: "include",
      body: options && options.body ? options.body : undefined
    });
    const payload = await response.json().catch(function () {
      return { ok: false, message: "Invalid server response." };
    });
    if (!response.ok || payload.ok === false) {
      throw new Error(payload.message || "Request failed.");
    }
    return payload;
  }

  function avatarFallbackLabel(value) {
    const trimmed = String(value || "").trim();
    if (!trimmed) return "U";
    return trimmed.slice(0, Math.min(trimmed.length, 2)).toUpperCase();
  }

  async function uploadPracticeAsset(file, kind) {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("kind", kind);

    const response = await fetch(CHAT_API_BASE + "/upload.php", {
      method: "POST",
      credentials: "include",
      body: formData
    });
    const payload = await response.json().catch(function () {
      return { ok: false, message: "Invalid server response." };
    });
    if (!response.ok || payload.ok === false) {
      throw new Error(payload.message || "Upload failed.");
    }
    return payload;
  }

  function dataUrlToFile(dataUrl, fileName, mimeType) {
    const raw = String(dataUrl || "");
    const parts = raw.split(",");
    if (parts.length < 2) {
      throw new Error("Invalid audio data.");
    }
    const header = parts[0] || "";
    const inferredMime = mimeType || (header.match(/data:(.*?);base64/) || [])[1] || "application/octet-stream";
    const binary = atob(parts[1]);
    const bytes = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) {
      bytes[index] = binary.charCodeAt(index);
    }
    return new File([bytes], fileName, { type: inferredMime });
  }

  function ensureShareSheetStyles() {
    if (document.getElementById("practiceShareSheetStyles")) {
      return;
    }
    const styleEl = document.createElement("style");
    styleEl.id = "practiceShareSheetStyles";
    styleEl.textContent = `
      .practice-share-modal {
        position: fixed;
        inset: 0;
        z-index: 4200;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
      }
      .practice-share-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(34, 41, 55, 0.32);
        backdrop-filter: blur(6px);
      }
      .practice-share-modal__card {
        position: relative;
        width: min(860px, 100%);
        max-height: min(88vh, 860px);
        overflow: auto;
        border-radius: 28px;
        background: #fffdf9;
        box-shadow: 0 28px 70px rgba(58, 78, 107, 0.18);
        padding: 28px;
      }
      .practice-share-modal__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 20px;
      }
      .practice-share-modal__title {
        font-size: 1.35rem;
        margin: 0 0 8px;
        color: #24354d;
      }
      .practice-share-modal__subtitle {
        margin: 0;
        color: rgba(36, 53, 77, 0.68);
        line-height: 1.6;
        font-size: 0.94rem;
      }
      .practice-share-modal__close {
        border: none;
        background: rgba(155, 183, 212, 0.14);
        color: #3A4E6B;
        width: 38px;
        height: 38px;
        border-radius: 999px;
        cursor: pointer;
        font-size: 1rem;
      }
      .practice-share-modal__grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
      }
      .practice-share-panel {
        border: 1px solid rgba(58, 78, 107, 0.1);
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.92);
        padding: 22px;
      }
      .practice-share-panel h4 {
        margin: 0 0 8px;
        font-size: 1rem;
        color: #24354d;
      }
      .practice-share-panel p {
        margin: 0 0 14px;
        color: rgba(36, 53, 77, 0.68);
        line-height: 1.55;
        font-size: 0.9rem;
      }
      .practice-share-primary {
        width: 100%;
        min-height: 46px;
        border-radius: 999px;
        border: none;
        background: #3A4E6B;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
      }
      .practice-share-search {
        width: 100%;
        border: 1px solid rgba(58, 78, 107, 0.14);
        border-radius: 16px;
        padding: 12px 14px;
        font-size: 0.95rem;
        margin-bottom: 14px;
      }
      .practice-share-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 300px;
        overflow: auto;
      }
      .practice-share-item {
        width: 100%;
        border: 1px solid rgba(58, 78, 107, 0.08);
        border-radius: 18px;
        background: #fff;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
      }
      .practice-share-item__meta {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .practice-share-item__avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #9BB7D4;
        color: #24354d;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
      }
      .practice-share-item__copy {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 3px;
      }
      .practice-share-item__copy strong,
      .practice-share-item__copy span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      .practice-share-item__copy span {
        color: rgba(36, 53, 77, 0.58);
        font-size: 0.84rem;
      }
      .practice-share-item__action {
        border: none;
        border-radius: 999px;
        min-height: 38px;
        padding: 0 14px;
        background: rgba(58, 78, 107, 0.1);
        color: #24354d;
        font-weight: 700;
        cursor: pointer;
      }
      .practice-share-feedback {
        margin-top: 14px;
        min-height: 20px;
        color: #3A4E6B;
        font-size: 0.9rem;
      }
      @media (max-width: 800px) {
        .practice-share-modal__grid {
          grid-template-columns: 1fr;
        }
      }
    `;
    document.head.appendChild(styleEl);
  }

  function closeShareModal() {
    const modal = document.getElementById("practiceShareModal");
    if (modal) {
      modal.remove();
    }
  }

  async function sharePracticeContentToConversation(conversationId, content) {
    await forumApiFetch("/chat-messages.php", {
      method: "POST",
      body: JSON.stringify({
        conversationId: conversationId,
        content: content
      })
    });
  }

  function openShareModal(options) {
    ensureShareSheetStyles();
    closeShareModal();

    const modal = document.createElement("div");
    modal.className = "practice-share-modal";
    modal.id = "practiceShareModal";
    modal.innerHTML = `
      <div class="practice-share-modal__backdrop"></div>
      <div class="practice-share-modal__card">
        <div class="practice-share-modal__header">
          <div>
            <h3 class="practice-share-modal__title">Share your practice</h3>
            <p class="practice-share-modal__subtitle">Send this draft into the forum review flow, or send it directly to one of your existing chats.</p>
          </div>
          <button type="button" class="practice-share-modal__close" aria-label="Close">✕</button>
        </div>
        <div class="practice-share-modal__grid">
          <section class="practice-share-panel">
            <h4>Share to Forum</h4>
            <p>The draft will be prefilled into the normal post flow and still goes through admin review.</p>
            <button type="button" class="practice-share-primary" id="practiceShareForumBtn">Continue to forum draft</button>
          </section>
          <section class="practice-share-panel">
            <h4>Share to Private Chat</h4>
            <p>Pick one of your existing group chats, or search a person and send it into a direct conversation.</p>
            <input type="text" class="practice-share-search" id="practiceShareSearch" placeholder="Search a username to share privately">
            <div class="practice-share-list" id="practiceShareList"></div>
            <div class="practice-share-feedback" id="practiceShareFeedback"></div>
          </section>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const closeBtn = modal.querySelector(".practice-share-modal__close");
    const backdrop = modal.querySelector(".practice-share-modal__backdrop");
    const forumBtn = modal.querySelector("#practiceShareForumBtn");
    const searchInput = modal.querySelector("#practiceShareSearch");
    const listEl = modal.querySelector("#practiceShareList");
    const feedbackEl = modal.querySelector("#practiceShareFeedback");

    function setFeedback(message, isError) {
      if (!feedbackEl) return;
      feedbackEl.textContent = message || "";
      feedbackEl.style.color = isError ? "#c2410c" : "#3A4E6B";
    }

    function renderShareItems(items, kind) {
      if (!listEl) return;
      if (!items.length) {
        listEl.innerHTML = `<div class="chat-sidebar__empty">${kind === "search" ? "No users found." : "No group chats yet."}</div>`;
        return;
      }
      listEl.innerHTML = items.map(function (item) {
        const actionLabel = kind === "search" ? "Share" : "Send";
        const subline = kind === "search"
          ? escapeHtml(item.email || "")
          : escapeHtml((item.memberCount || 0) + " members");
        const avatarLabel = escapeHtml(item.avatar || avatarFallbackLabel(item.title || item.username || "U"));
        const targetId = String(item.id || item.conversationId || item.user_id || "");
        return `
          <div class="practice-share-item">
            <div class="practice-share-item__meta">
              <span class="practice-share-item__avatar">${avatarLabel}</span>
              <span class="practice-share-item__copy">
                <strong>${escapeHtml(item.title || item.username || "Conversation")}</strong>
                <span>${subline}</span>
              </span>
            </div>
            <button type="button" class="practice-share-item__action" data-kind="${kind}" data-id="${escapeHtml(targetId)}">
              ${actionLabel}
            </button>
          </div>
        `;
      }).join("");
    }

    async function loadGroupChats() {
      try {
        const payload = await forumApiFetch("/chat-conversations.php");
        const groups = (payload.conversations || []).filter(function (conversation) {
          return String(conversation.type || "") === "group";
        });
        renderShareItems(groups, "group");
      } catch (error) {
        setFeedback(error.message || "Unable to load chats.", true);
        renderShareItems([], "group");
      }
    }

    async function runUserSearch(query) {
      try {
        const payload = await forumApiFetch("/chat-users.php?q=" + encodeURIComponent(query));
        renderShareItems(payload.users || [], "search");
      } catch (error) {
        setFeedback(error.message || "Unable to search users.", true);
      }
    }

    closeBtn.addEventListener("click", closeShareModal);
    backdrop.addEventListener("click", closeShareModal);
    forumBtn.addEventListener("click", function () {
      closeShareModal();
      openForumWithDraft(options.forumDraft);
    });

    searchInput.addEventListener("input", function () {
      const query = searchInput.value.trim();
      setFeedback("", false);
      if (!query) {
        loadGroupChats();
        return;
      }
      runUserSearch(query);
    });

    listEl.addEventListener("click", async function (event) {
      const button = event.target.closest(".practice-share-item__action");
      if (!button) return;
      const kind = button.getAttribute("data-kind");
      const targetId = Number(button.getAttribute("data-id") || 0);
      if (!targetId) return;
      button.disabled = true;
      setFeedback("Sending…", false);
      try {
        if (kind === "group") {
          await sharePracticeContentToConversation(targetId, options.chatContent);
        } else {
          const directPayload = await forumApiFetch("/chat-conversations.php", {
            method: "POST",
            body: JSON.stringify({
              action: "direct",
              targetUserId: targetId
            })
          });
          await sharePracticeContentToConversation(Number(directPayload.conversation.id), options.chatContent);
        }
        setFeedback("Shared successfully.", false);
        window.setTimeout(closeShareModal, 650);
      } catch (error) {
        button.disabled = false;
        setFeedback(error.message || "Share failed.", true);
      }
    });

    loadGroupChats();
  }

  function openForumWithDraft(draft) {
    try {
      const bridgePayload = {};
      bridgePayload[FORUM_PREFILL_WINDOW_NAME_KEY] = draft || {};
      window.name = JSON.stringify(bridgePayload);
    } catch (_err) {
      window.name = "";
    }
    window.location.href = withUiMode(FORUM_COMPOSE_URL);
  }

  function goBack(target) {
    if (target) {
      window.location.href = withUiMode(target);
      return;
    }
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
    window.location.href = withUiMode("index.html");
  }

  function buildOptions(values, selectEl) {
    selectEl.innerHTML = "";
    values.forEach(function (value) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value;
      selectEl.appendChild(option);
    });
  }

  function getCountryIso(country) {
    const raw = String(country || "").trim();
    const normalized = raw.toLowerCase();
    const isoMap = {
      germany: "DE",
      thailand: "TH",
      uk: "GB",
      "united kingdom": "GB",
      us: "US",
      usa: "US",
      "united states": "US",
      australia: "AU",
      canada: "CA",
      ireland: "IE",
      "new zealand": "NZ",
      japan: "JP",
      singapore: "SG",
      france: "FR",
      gb: "GB",
      ca: "CA",
      ie: "IE",
      nz: "NZ",
      jp: "JP",
      sg: "SG",
      fr: "FR",
      de: "DE",
      th: "TH",
      au: "AU"
    };

    if (normalized === "us") {
      return "US";
    }
    return isoMap[normalized] || (normalized.length === 2 ? normalized.toUpperCase() : null);
  }

  function getCountryFlagPath(country) {
    const iso = getCountryIso(country);
    return iso ? "flags/flags/" + iso.toLowerCase() + ".png" : "";
  }

  function getCountryFlagImgHtml(country, flagUrl) {
    // 优先使用数据库中的外网URL
    if (flagUrl) {
      return "<img class='country-flag-icon' src='" + escapeHtml(flagUrl) + "' alt='" + escapeHtml(country || "Country") + " flag' onerror=\"this.style.display='none'\">";
    }
    // 回退到本地路径
    const src = getCountryFlagPath(country);
    if (!src) {
      return "";
    }
    return "<img class='country-flag-icon' src='" + escapeHtml(src) + "' alt='" + escapeHtml(country || "Country") + " flag' onerror=\"this.style.display='none'\">";
  }

  function getCountryLabelHtml(country) {
    return "<span class='country-label'>" + escapeHtml(country || "N/A") + "</span>";
  }

  function getCountryInlineHtml(country, flagUrl) {
    const flagHtml = getCountryFlagImgHtml(country, flagUrl);
    const labelHtml = getCountryLabelHtml(country);
    if (!flagHtml) {
      return labelHtml;
    }
    return "<span class='country-inline'>" + flagHtml + labelHtml + "</span>";
  }

  function getPersonMetaText(video) {
    const author = video.author || "Unknown";
    const country = video.country || "N/A";
    const flag = getCountryFlag(country);
    const timeSpecific = video.timeSpecific || video.duration || "N/A";
    return author + " | " + flag + " " + country + " | " + timeSpecific;
  }

  function getCountryFlag(country) {
    const iso = getCountryIso(country);
    if (!iso || iso.length !== 2) {
      return "🏳️";
    }
    const codeA = iso.charCodeAt(0) - 65;
    const codeB = iso.charCodeAt(1) - 65;
    if (codeA < 0 || codeA > 25 || codeB < 0 || codeB > 25) {
      return "🏳️";
    }
    return String.fromCodePoint(127397 + codeA) + String.fromCodePoint(127397 + codeB);
  }

  function getPersonMetaHtml(video) {
    const author = escapeHtml(video.author || "Unknown");
    // 直接使用 flagUrl 显示国旗图片，放大尺寸
    const flagImg = video.flagUrl 
      ? "<img class='country-flag-icon' src='" + video.flagUrl + "' alt='flag' style='width:24px;height:18px;vertical-align:middle;margin-right:4px;'>"
      : "";
    const countryLabel = escapeHtml(video.country || "N/A");
    const timeSpecific = escapeHtml(video.timeSpecific || video.duration || "N/A");
    // 紧凑布局：作者 | 国旗+国家 | 时长
    return "<span>" + author + "</span><span class='person-meta-separator'>|</span><span>" + flagImg + countryLabel + "</span><span class='person-meta-separator'>|</span><span>" + timeSpecific + "</span>";
  }

  function createCountryFilter(containerEl, values, initialValue, onChange) {
    if (!containerEl) {
      return { setValue: function () {} };
    }

    let currentValue = initialValue || "All";
    let isOpen = false;
    containerEl.innerHTML = "";

    const dropdownEl = document.createElement("div");
    dropdownEl.className = "country-select";

    const triggerEl = document.createElement("button");
    triggerEl.type = "button";
    triggerEl.className = "country-select-btn";
    triggerEl.setAttribute("aria-haspopup", "listbox");
    triggerEl.setAttribute("aria-expanded", "false");

    const menuEl = document.createElement("div");
    menuEl.className = "country-select-menu hidden";
    menuEl.setAttribute("role", "listbox");

    function renderTrigger() {
      const contentHtml = currentValue === "All"
        ? "<span class='country-inline'><span class='country-label'>Country: All</span></span>"
        : getCountryInlineHtml(currentValue, null);  // 国家过滤器不需要国旗
      triggerEl.innerHTML = contentHtml + "<span class='country-select-caret' aria-hidden='true'>▾</span>";
      triggerEl.setAttribute("aria-expanded", isOpen ? "true" : "false");
    }

    function closeMenu() {
      isOpen = false;
      menuEl.classList.add("hidden");
      dropdownEl.classList.remove("is-open");
      renderTrigger();
    }

    function openMenu() {
      isOpen = true;
      menuEl.classList.remove("hidden");
      dropdownEl.classList.add("is-open");
      renderTrigger();
    }

    function setValue(nextValue, notify) {
      currentValue = nextValue;
      Array.from(menuEl.children).forEach(function (child) {
        child.classList.toggle("is-active", child.dataset.value === currentValue);
        child.setAttribute("aria-selected", child.dataset.value === currentValue ? "true" : "false");
      });
      renderTrigger();
      if (notify && typeof onChange === "function") {
        onChange(currentValue);
      }
    }

    values.forEach(function (value) {
      const optionEl = document.createElement("button");
      optionEl.type = "button";
      optionEl.className = "country-select-option";
      optionEl.dataset.value = value;
      optionEl.setAttribute("role", "option");
      optionEl.setAttribute("aria-selected", value === currentValue ? "true" : "false");
      optionEl.innerHTML = value === "All"
        ? "<span class='country-inline'><span class='country-label'>Country: All</span></span>"
        : getCountryInlineHtml(value, null);  // 国家过滤器选项不需要国旗
      optionEl.addEventListener("click", function () {
        setValue(value, true);
        closeMenu();
      });
      menuEl.appendChild(optionEl);
    });

    triggerEl.addEventListener("click", function () {
      if (isOpen) {
        closeMenu();
        return;
      }
      openMenu();
    });

    document.addEventListener("click", function (event) {
      if (!containerEl.contains(event.target)) {
        closeMenu();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        closeMenu();
      }
    });

    dropdownEl.appendChild(triggerEl);
    dropdownEl.appendChild(menuEl);
    containerEl.appendChild(dropdownEl);
    setValue(currentValue, false);

    return {
      setValue: function (value) {
        setValue(value, false);
        Array.from(menuEl.children).forEach(function (child) {
          child.setAttribute("aria-selected", child.dataset.value === currentValue ? "true" : "false");
        });
      }
    };
  }

  // 获取视频源URL（优先使用外网服务器的URL）
  function getVideoSource(video) {
    return video.videoUrl || video.videoPath || ("Videos/" + video.videoFile);
  }

  // 封面：管理员上传 URL > 本地 cover/ cover2/（与旧 practice-app.js 一致）> 外网占位图
  function getCoverUrl(video, mode) {
    if (video.coverUrl) {
      return video.coverUrl;
    }
    if (video.coverFile) {
      const folder = mode === "respond" ? "cover2" : "cover";
      return folder + "/" + video.coverFile;
    }
    const match = String(video.id || "").match(/(\d+)$/);
    if (!match) {
      return null;
    }
    const baseIndex = Number(match[1]);
    const coverNum = mode === "respond" ? baseIndex + 12 : baseIndex;
    const coverFolder = mode === "respond" ? "cover2" : "cover";
    const localPath = coverFolder + "/" + coverNum + ".png";
    if (video.dataSource === "local") {
      return localPath;
    }
    return "http://111.231.10.140/media/" + coverFolder + "/" + coverNum + ".png";
  }

  async function initTranscriptPanel(toggleBtn, closeBtn, panelEl, contentEl, video) {
    if (!toggleBtn || !panelEl || !contentEl) {
      return;
    }

    toggleBtn.addEventListener("click", function () {
      panelEl.classList.remove("hidden");
    });

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        panelEl.classList.add("hidden");
      });
    }

    // 优先使用数据库中的文本内容
    if (video.transcriptText) {
      contentEl.textContent = video.transcriptText;
      return;
    }

    // 否则从外网服务器获取
    if (!video.transcriptUrl && !video.transcriptPath) {
      contentEl.textContent = "No transcript file for this video.";
      return;
    }

    try {
      const url = video.transcriptUrl || video.transcriptPath;
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("transcript fetch failed");
      }
      contentEl.textContent = await response.text();
    } catch (_err) {
      contentEl.textContent = "Failed to load transcript text.";
    }
  }

  async function loadTextContent(url, fallbackText, failureText) {
    if (fallbackText) {
      return fallbackText;
    }
    if (!url) {
      return failureText;
    }
    try {
      const response = await fetch(url);
      if (!response.ok) {
        throw new Error("text fetch failed");
      }
      return await response.text();
    } catch (_err) {
      return failureText;
    }
  }

  async function initDetailResourceTabs(transcriptBtn, sampleNotesBtn, panelEl, titleEl, contentEl, video) {
    if (!transcriptBtn || !panelEl || !titleEl || !contentEl) {
      return;
    }

    const tabButtons = [transcriptBtn];
    const contentCache = {};
    let activeTab = "";

    if (sampleNotesBtn && video.sampleNotesUrl) {
      sampleNotesBtn.classList.remove("hidden");
      tabButtons.push(sampleNotesBtn);
    }

    function setActiveButton(targetBtn) {
      tabButtons.forEach(function (btn) {
        btn.classList.toggle("is-active", btn === targetBtn);
      });
    }

    async function openTab(tabName) {
      if (activeTab === tabName && !panelEl.classList.contains("hidden")) {
        panelEl.classList.add("hidden");
        activeTab = "";
        setActiveButton(null);
        return;
      }

      activeTab = tabName;
      panelEl.classList.remove("hidden");

      if (tabName === "sample-notes") {
        titleEl.textContent = "Sample Notes";
        if (!contentCache.sampleNotes) {
          contentCache.sampleNotes = await loadTextContent(
            video.sampleNotesUrl, 
            null, 
            "No sample notes available for this video."
          );
        }
        contentEl.textContent = contentCache.sampleNotes;
        setActiveButton(sampleNotesBtn);
        return;
      }

      titleEl.textContent = "Transcript";
      if (!contentCache.transcript) {
        contentCache.transcript = await loadTextContent(
          video.transcriptUrl || video.transcriptPath, 
          video.transcriptText, 
          "Failed to load transcript text."
        );
      }
      contentEl.textContent = contentCache.transcript || "No transcript file for this video.";
      setActiveButton(transcriptBtn);
    }

    transcriptBtn.addEventListener("click", function () {
      openTab("transcript");
    });

    if (sampleNotesBtn && video.sampleNotesUrl) {
      sampleNotesBtn.addEventListener("click", function () {
        openTab("sample-notes");
      });
    }
  }

  function applySubtitleTrack(videoEl, video) {
    videoEl.querySelectorAll("track").forEach(function (trackEl) {
      trackEl.remove();
    });
    
    // 优先使用外网服务器的VTT URL
    const vttUrl = video.vttUrl;
    if (!vttUrl) {
      return;
    }
    
    const track = document.createElement("track");
    track.kind = "subtitles";
    track.label = "English";
    track.srclang = "en";
    track.src = vttUrl;
    track.default = true;
    videoEl.appendChild(track);
  }

  function initBackButtons() {
    document.querySelectorAll(".back-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        goBack(btn.dataset.backTarget || "index.html");
      });
    });
  }

  // ==================== 视频列表页面 ====================
  async function initVideoList() {
    const mode = getParam("mode", "understand");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.understand;
    const modeTitle = qs("#modeTitle");
    const searchEl = qs("#smartSearch");
    const typeEl = qs("#smartType");
    const difficultyEl = qs("#smartDifficulty");
    const durationEl = qs("#smartDuration");
    const sourceEl = qs("#smartSource");
    const countryFilterEl = qs("#smartCountryFilter");
    const clearBtn = qs("#clearSmartFilters");
    const resultList = qs("#videoResultList");
    const resultsCount = qs("#resultsCount");

    if (!modeTitle || !searchEl || !typeEl || !difficultyEl || !durationEl || !sourceEl || !countryFilterEl || !clearBtn || !resultList || !resultsCount) {
      return;
    }

    // 设置返回按钮
    var backList = qs(".back-btn");
    if (backList) {
      if (UI_MODE === "godot") {
        var gAc =
          (typeof window !== "undefined" && window.ACADBEAT_LOCAL && window.ACADBEAT_LOCAL.godotAcademicWebUrl) ||
          "http://127.0.0.1:5500/index.html?ui=godot&scene=academic";
        backList.dataset.backTarget = gAc;
      } else {
        backList.dataset.backTarget = "training.html";
      }
    }

    modeTitle.textContent = modeInfo.label;

    // 从API获取视频列表
    let modeVideos = [];
    try {
      modeVideos = await data.getVideos({ mode: mode });
    } catch (err) {
      console.error("Failed to load videos:", err);
      resultList.innerHTML = "<p class='empty-hint'>Failed to load videos. Please try again later.</p>";
      return;
    }

    function normalizedType(video) {
      return video.type === "Academic" ? "Academic" : "Campus&Life";
    }

    // 获取所有可用的过滤选项
    const allTypes = ["All", "Campus&Life", "Academic"];
    const allCountries = ["All"].concat(Array.from(new Set(modeVideos.map(function (v) { return v.country; }))));

    buildOptions(allTypes, typeEl);
    const countryFilter = createCountryFilter(countryFilterEl, allCountries, "All", function (value) {
      filterState.country = value;
      renderResults();
    });
    typeEl.options[0].textContent = "Type: All";

    const filterState = {
      search: "",
      difficulty: "All",
      duration: "All",
      source: "All",
      type: "All",
      country: "All"
    };

    function filteredVideos() {
      const keyword = filterState.search.toLowerCase();
      return modeVideos.filter(function (video) {
        const keywordSource = [
          video.title,
          video.type,
          video.duration || "",
          video.source || "",
          video.country,
          video.question || "",
          video.answerText || ""
        ].join(" ").toLowerCase();

        const passSearch = !keyword || keywordSource.indexOf(keyword) !== -1;
        const passType = filterState.type === "All" || normalizedType(video) === filterState.type;
        const passDifficulty = filterState.difficulty === "All" || video.difficulty === filterState.difficulty;
        const passDuration = filterState.duration === "All" || video.duration === filterState.duration;
        const passSource = filterState.source === "All" || video.source === filterState.source;
        const passCountry = filterState.country === "All" || video.country === filterState.country;
        return passSearch && passType && passDifficulty && passDuration && passSource && passCountry;
      });
    }

    function renderResults() {
      const videos = filteredVideos();
      resultList.innerHTML = "";
      resultsCount.textContent = videos.length + " videos";

      if (!videos.length) {
        const empty = document.createElement("p");
        empty.className = "empty-hint";
        empty.textContent = "No matches. Try broader filters.";
        resultList.appendChild(empty);
        return;
      }

      videos.forEach(function (video) {
        const card = document.createElement("article");
        card.className = "video-card";
        // 标签栏只显示基本信息，国旗移到下方 personLine
        const metaLine =
          "<div class='video-meta-row'>" +
          "<span class='video-meta-pill'>" + normalizedType(video) + "</span>" +
          "<span class='video-meta-pill'>" + video.difficulty + "</span>" +
          "<span class='video-meta-pill'>" + (video.duration || "N/A") + "</span>" +
          "<span class='video-meta-pill'>" + (video.source || "N/A") + "</span>" +
          "</div>";
        
        // 使用外网服务器的封面URL
        const coverUrl = getCoverUrl(video, mode);
        const coverMedia =
          "<div class='video-cover-box'>" +
          (coverUrl
            ? "<img class='video-cover-image' src='" + coverUrl + "' alt='Video cover'>"
            : "<div class='video-cover-placeholder'>Video Cover</div>") +
          "<button class='btn-small video-go-btn' type='button' aria-label='Play video' title='Play'>&#9658;</button>" +
          "<div class='video-cover-title'>" + video.title + "</div>" +
          "</div>";
        
        // respond 模式显示完整作者信息，understand 模式只显示国旗+国家
        const personLine = mode === "respond" 
          ? "<p class='video-person-line'>" + getPersonMetaHtml(video) + "</p>"
          : "<p class='video-person-line'><span>" + (video.flagUrl ? "<img class='country-flag-icon' src='" + video.flagUrl + "' alt='flag' style='width:24px;height:18px;vertical-align:middle;margin-right:4px;'>" : "") + escapeHtml(video.country || "N/A") + "</span></p>";
        const questionLine = video.question ? "<p class='video-question'>Q: " + video.question + "</p>" : "";
        card.innerHTML =
          metaLine +
          coverMedia +
          personLine +
          questionLine;

        card.querySelector(".video-go-btn").addEventListener("click", function () {
          const targetPage = mode === "respond" ? "respond_training.html" : "note_training.html";
          window.location.href = withUiMode(targetPage + "?mode=" + mode + "&videoId=" + video.id);
        });

        resultList.appendChild(card);
      });
    }

    searchEl.addEventListener("input", function () {
      filterState.search = searchEl.value.trim();
      renderResults();
    });

    typeEl.addEventListener("change", function () {
      filterState.type = typeEl.value;
      renderResults();
    });

    difficultyEl.addEventListener("change", function () {
      filterState.difficulty = difficultyEl.value;
      renderResults();
    });

    durationEl.addEventListener("change", function () {
      filterState.duration = durationEl.value;
      renderResults();
    });

    sourceEl.addEventListener("change", function () {
      filterState.source = sourceEl.value;
      renderResults();
    });

    clearBtn.addEventListener("click", function () {
      filterState.search = "";
      filterState.difficulty = "All";
      filterState.duration = "All";
      filterState.source = "All";
      filterState.type = "All";
      filterState.country = "All";
      searchEl.value = "";
      typeEl.value = "All";
      difficultyEl.value = "All";
      durationEl.value = "All";
      sourceEl.value = "All";
      countryFilter.setValue("All");
      renderResults();
    });

    renderResults();
  }

  // ==================== 视频详情页面 (Listening and Understand) ====================
  async function initVideoDetail() {
    const mode = getParam("mode", "understand");
    const videoId = getParam("videoId", "");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.understand;
    
    // 从API获取视频详情
    const video = await data.getVideoById(videoId);

    if (!video) {
      alert("Video not found. Please return to the previous page.");
      return;
    }

    const backBtnDetail = qs(".back-btn");
    if (backBtnDetail) {
      backBtnDetail.dataset.backTarget = "listening.html?mode=" + encodeURIComponent(mode);
    }

    const titleEl = qs("#detailTitle");
    const detailPersonMetaEl = qs("#detailPersonMeta");
    const metaEl = qs("#detailMeta");
    const videoEl = qs("#practiceVideo");
    const transcriptToggleBtn = qs("#transcriptToggleBtn");
    const sampleNotesToggleBtn = qs("#sampleNotesToggleBtn");
    const transcriptPanelEl = qs("#transcriptPanel");
    const detailResourceTitleEl = qs("#detailResourceTitle");
    const transcriptContentEl = qs("#transcriptContent");
    const noteLayoutEl = qs(".detail-note-layout");
    const studyWorkspaceEl = qs("#studyWorkspace");
    const noteShareBtn = qs("#noteShareBtn");
    const noteDockToggleBtn = qs("#noteDockToggleBtn");
    const noteMainContentEl = qs("#noteMainContent");
    const noteKeyWordEl = qs("#noteKeyWord");
    const notePersonalViewEl = qs("#notePersonalView");
    const noteMainContentDisplayEl = qs("#noteMainContentDisplay");
    const noteKeyWordDisplayEl = qs("#noteKeyWordDisplay");
    const notePersonalViewDisplayEl = qs("#notePersonalViewDisplay");
    const recordAnswerBtn = qs("#recordAnswerBtn");
    const recordAnswerError = qs("#recordAnswerError");
    const recordAnswerFeedback = qs("#recordAnswerFeedback");

    if (!titleEl || !detailPersonMetaEl || !metaEl || !videoEl || !studyWorkspaceEl || !noteShareBtn || !noteDockToggleBtn || !noteMainContentEl || !noteKeyWordEl || !notePersonalViewEl) {
      return;
    }

    titleEl.textContent = video.title;
    detailPersonMetaEl.innerHTML = getPersonMetaHtml(video);
    metaEl.innerHTML =
      "<span class='detail-meta-pill'>" + modeInfo.label + "</span>" +
      "<span class='detail-meta-pill'>" + video.type + "</span>" +
      "<span class='detail-meta-pill'>" + video.difficulty + "</span>" +
      "<span class='detail-meta-pill'>" + (video.duration || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + (video.source || "N/A") + "</span>" +
      "<span class='detail-meta-pill detail-meta-pill--country'>" + getCountryInlineHtml(video.country, video.flagUrl) + "</span>";
    
    // 使用外网服务器的视频URL
    videoEl.src = getVideoSource(video);
    applySubtitleTrack(videoEl, video);
    initDetailResourceTabs(transcriptToggleBtn, sampleNotesToggleBtn, transcriptPanelEl, detailResourceTitleEl, transcriptContentEl, video);

    if (mode === "respond" && noteLayoutEl) {
      noteLayoutEl.classList.add("hidden");
      return;
    }

    const noteStorageKey = "practice-note-" + mode + "-" + video.id;
    function loadNoteDraft() {
      try {
        return JSON.parse(localStorage.getItem(noteStorageKey) || "{}");
      } catch (_err) {
        return {};
      }
    }

    const noteDraft = loadNoteDraft();
    noteMainContentEl.value = noteDraft.mainContent || "";
    noteKeyWordEl.value = noteDraft.keyWord || "";
    notePersonalViewEl.value = noteDraft.personalView || "";

    function noteValues() {
      return {
        mainContent: noteMainContentEl.value.trim(),
        keyWord: noteKeyWordEl.value.trim(),
        personalView: notePersonalViewEl.value.trim()
      };
    }

    function persistNoteDraft() {
      localStorage.setItem(noteStorageKey, JSON.stringify(noteValues()));
    }

    function buildSharedAnswerText(values) {
      const sections = [];
      if (values.mainContent) {
        sections.push("Main Content: " + values.mainContent);
      }
      if (values.keyWord) {
        sections.push("Key Word: " + values.keyWord);
      }
      if (values.personalView) {
        sections.push("Personal View: " + values.personalView);
      }
      return sections.join("\n");
    }

    function setEditingMode() {
      noteMainContentEl.value = "";
      noteKeyWordEl.value = "";
      notePersonalViewEl.value = "";
      noteMainContentEl.classList.remove("hidden");
      noteKeyWordEl.classList.remove("hidden");
      notePersonalViewEl.classList.remove("hidden");
      if (noteMainContentDisplayEl) noteMainContentDisplayEl.classList.add("hidden");
      if (noteKeyWordDisplayEl) noteKeyWordDisplayEl.classList.add("hidden");
      if (notePersonalViewDisplayEl) notePersonalViewDisplayEl.classList.add("hidden");
      if (recordAnswerError) recordAnswerError.classList.add("hidden");
      if (recordAnswerFeedback) recordAnswerFeedback.classList.add("hidden");
      recordAnswerBtn.textContent = "Record your answer";
      persistNoteDraft();
    }

    function setSavedMode(values) {
      noteMainContentEl.classList.add("hidden");
      noteKeyWordEl.classList.add("hidden");
      notePersonalViewEl.classList.add("hidden");
      if (noteMainContentDisplayEl) {
        noteMainContentDisplayEl.textContent = values.mainContent;
        noteMainContentDisplayEl.classList.remove("hidden");
      }
      if (noteKeyWordDisplayEl) {
        noteKeyWordDisplayEl.textContent = values.keyWord;
        noteKeyWordDisplayEl.classList.remove("hidden");
      }
      if (notePersonalViewDisplayEl) {
        notePersonalViewDisplayEl.textContent = values.personalView;
        notePersonalViewDisplayEl.classList.remove("hidden");
      }
      if (recordAnswerError) recordAnswerError.classList.add("hidden");
      if (recordAnswerFeedback) {
        recordAnswerFeedback.innerHTML = "<strong>Saved to your study record</strong><span>You can still make multiple attempts and save</span>";
        recordAnswerFeedback.classList.remove("hidden");
      }
      recordAnswerBtn.textContent = "Saved. Start second attempt";
    }

    noteMainContentEl.addEventListener("input", persistNoteDraft);
    noteKeyWordEl.addEventListener("input", persistNoteDraft);
    notePersonalViewEl.addEventListener("input", persistNoteDraft);

    noteShareBtn.addEventListener("click", function () {
      persistNoteDraft();
      const values = noteValues();
      if (!values.mainContent && !values.keyWord && !values.personalView) {
        if (recordAnswerFeedback) recordAnswerFeedback.classList.add("hidden");
        if (recordAnswerError) recordAnswerError.classList.remove("hidden");
        return;
      }

      if (recordAnswerError) recordAnswerError.classList.add("hidden");

      const trainingPath = withUiMode("Academic-Practice/note_training.html?mode=" + encodeURIComponent(mode) + "&videoId=" + encodeURIComponent(video.id));
      const trainingUrl = buildAbsoluteProjectUrl(trainingPath);
      const answerText = buildSharedAnswerText(values);
      const prefillContent = [
        "Related training: [" + (video.title || "Open training") + "](" + trainingUrl + ")",
        "",
        "My answer: ",
        answerText
      ].join("\n");

      openShareModal({
        forumDraft: {
          title: "",
          content: prefillContent
        },
        chatContent: prefillContent
      });
    });

    if (recordAnswerBtn && recordAnswerFeedback) {
      recordAnswerBtn.addEventListener("click", async function () {
        if (recordAnswerBtn.textContent === "Saved. Start second attempt") {
          setEditingMode();
          return;
        }

        persistNoteDraft();
        const values = noteValues();
        if (!values.mainContent && !values.keyWord && !values.personalView) {
          if (recordAnswerError) recordAnswerError.classList.remove("hidden");
          if (recordAnswerFeedback) recordAnswerFeedback.classList.add("hidden");
          return;
        }
        if (recordAnswerError) recordAnswerError.classList.add("hidden");
        recordAnswerBtn.disabled = true;
        recordAnswerBtn.textContent = "Saving...";
        try {
          const response = await fetch(LISTENING_RECORD_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({
              videoId: video.id,
              mode: mode,
              title: video.title,
              personMeta: getPersonMetaText(video),
              difficulty: video.difficulty || "",
              duration: video.duration || "",
              source: video.source || "",
              country: video.country || "",
              mainContent: values.mainContent,
              keyWord: values.keyWord,
              personalView: values.personalView
            })
          });
          const payload = await response.json().catch(function () {
            return { ok: false, message: "Invalid response." };
          });
          if (!response.ok || !payload.ok) {
            throw new Error(payload.message || "Unable to save this record.");
          }

          setSavedMode(values);
        } catch (error) {
          recordAnswerFeedback.innerHTML = "<strong>" + escapeHtml((error && error.message) || "Unable to save this record.") + "</strong><span>Please log in first, then try again.</span>";
          recordAnswerFeedback.classList.remove("hidden");
          recordAnswerBtn.textContent = "Try Again";
        } finally {
          recordAnswerBtn.disabled = false;
        }
      });
    }

    function refreshNoteDockBtnText() {
      const isCollapsed = studyWorkspaceEl.classList.contains("note-dock-collapsed");
      noteDockToggleBtn.textContent = isCollapsed ? "展开" : "折叠";
    }

    async function switchVideoFullscreenToWorkspace() {
      if (document.fullscreenElement !== videoEl) {
        return;
      }
      try {
        await document.exitFullscreen();
        await studyWorkspaceEl.requestFullscreen();
      } catch (_err) {}
      finally {
        refreshNoteDockBtnText();
      }
    }

    noteDockToggleBtn.addEventListener("click", function () {
      studyWorkspaceEl.classList.toggle("note-dock-collapsed");
      refreshNoteDockBtnText();
    });

    document.addEventListener("fullscreenchange", function () {
      if (document.fullscreenElement === studyWorkspaceEl) {
        studyWorkspaceEl.classList.add("note-dock-collapsed");
      } else {
        studyWorkspaceEl.classList.remove("note-dock-collapsed");
      }
      refreshNoteDockBtnText();
      switchVideoFullscreenToWorkspace();
    });
    refreshNoteDockBtnText();
  }

  // ==================== 响应页面 (Listening and Respond) ====================
  async function initRespondDetail() {
    const mode = getParam("mode", "respond");
    const videoId = getParam("videoId", "");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.respond;
    
    // 从API获取视频详情
    const video = await data.getVideoById(videoId);

    if (!video) {
      alert("Video not found. Please return to the previous page.");
      return;
    }

    const backBtnRespond = qs(".back-btn");
    if (backBtnRespond) {
      backBtnRespond.dataset.backTarget = "listening.html?mode=" + encodeURIComponent(mode);
    }

    const titleEl = qs("#respondTitle");
    const personMetaEl = qs("#respondPersonMeta");
    const metaEl = qs("#respondMeta");
    const transcriptToggleBtn = qs("#respondTranscriptToggleBtn");
    const transcriptPanelEl = qs("#respondTranscriptPanel");
    const respondResourceTitleEl = qs("#respondResourceTitle");
    const transcriptContentEl = qs("#respondTranscriptContent");
    const videoEl = qs("#respondVideo");
    const recordBtn = qs("#respondRecordBtn");
    const recordStatus = qs("#respondRecordStatus");
    const playbackEl = qs("#respondPlayback");
    const respondSaveBtn = qs("#respondSaveBtn");
    const respondShareBtn = qs("#respondShareBtn");
    const respondSaveError = qs("#respondSaveError");
    const respondSaveFeedback = qs("#respondSaveFeedback");

    if (!titleEl || !personMetaEl || !metaEl || !transcriptToggleBtn || !transcriptPanelEl || !transcriptContentEl || !videoEl || !recordBtn || !recordStatus || !playbackEl) {
      return;
    }

    titleEl.textContent = video.title;
    personMetaEl.innerHTML = getPersonMetaHtml(video);
    metaEl.innerHTML =
      "<span class='detail-meta-pill'>" + modeInfo.label + "</span>" +
      "<span class='detail-meta-pill'>" + video.type + "</span>" +
      "<span class='detail-meta-pill'>" + video.difficulty + "</span>" +
      "<span class='detail-meta-pill'>" + (video.duration || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + (video.source || "N/A") + "</span>" +
      "<span class='detail-meta-pill detail-meta-pill--country'>" + getCountryInlineHtml(video.country, video.flagUrl) + "</span>";
    
    // 使用外网服务器的视频URL
    videoEl.src = getVideoSource(video);
    applySubtitleTrack(videoEl, video);
    initDetailResourceTabs(transcriptToggleBtn, null, transcriptPanelEl, respondResourceTitleEl, transcriptContentEl, video);

    let recorder = null;
    let chunks = [];
    let currentStream = null;
    let currentAudioData = "";
    let currentAudioMime = "audio/webm";
    let currentAudioShareUrl = "";

    function getAudioExtensionFromMime(mimeType) {
      const normalized = String(mimeType || "").toLowerCase();
      if (normalized.indexOf("ogg") !== -1) return "ogg";
      if (normalized.indexOf("wav") !== -1) return "wav";
      if (normalized.indexOf("mp4") !== -1 || normalized.indexOf("m4a") !== -1) return "m4a";
      if (normalized.indexOf("mpeg") !== -1 || normalized.indexOf("mp3") !== -1) return "mp3";
      return "webm";
    }

    function setPlaybackSource(src) {
      playbackEl.src = src || "";
      if (!src) {
        playbackEl.removeAttribute("src");
        playbackEl.load();
      }
    }

    function setRespondIdleState() {
      recordBtn.classList.remove("is-recording");
      recordBtn.textContent = currentAudioData ? "Re-record" : "SPEAKING";
    }

    function buildRespondShareContent(audioUrl) {
      const trainingPath = withUiMode("Academic-Practice/respond_training.html?mode=" + encodeURIComponent(mode) + "&videoId=" + encodeURIComponent(video.id));
      const trainingUrl = buildAbsoluteProjectUrl(trainingPath);
      const audioFileName = "response-" + video.id + "." + getAudioExtensionFromMime(currentAudioMime);
      return [
        "Related training: [" + (video.title || "Open training") + "](" + trainingUrl + ")",
        "",
        "My answer: ",
        "![audio:" + audioFileName + "](" + audioUrl + ")"
      ].join("\n");
    }

    function resetRespondAttempt() {
      currentAudioData = "";
      currentAudioMime = "audio/webm";
      currentAudioShareUrl = "";
      chunks = [];
      if (recorder && recorder.state === "recording") {
        try {
          recorder.stop();
        } catch (_err) {}
      }
      recorder = null;
      if (currentStream) {
        currentStream.getTracks().forEach(function (track) { track.stop(); });
        currentStream = null;
      }
      setPlaybackSource("");
      recordStatus.textContent = "Ready to record.";
      if (respondSaveError) respondSaveError.classList.add("hidden");
      if (respondSaveFeedback) respondSaveFeedback.classList.add("hidden");
      if (respondSaveBtn) {
        respondSaveBtn.disabled = false;
        respondSaveBtn.textContent = "Save your answer";
      }
      setRespondIdleState();
    }

    setRespondIdleState();

    if (respondSaveBtn && respondSaveFeedback && respondSaveError) {
      respondSaveBtn.addEventListener("click", async function () {
        if (respondSaveBtn.textContent === "Saved. Start second attempt") {
          resetRespondAttempt();
          return;
        }
        if (!currentAudioData) {
          respondSaveError.classList.remove("hidden");
          respondSaveFeedback.classList.add("hidden");
          return;
        }
        respondSaveError.classList.add("hidden");
        respondSaveBtn.disabled = true;
        respondSaveBtn.textContent = "Saving...";
        try {
          const response = await fetch(INTEGRATED_RECORD_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify({
              videoId: video.id,
              mode: mode,
              title: video.title,
              personMeta: getPersonMetaText(video),
              difficulty: video.difficulty || "",
              duration: video.duration || "",
              source: video.source || "",
              country: video.country || "",
              audioData: currentAudioData,
              audioMime: currentAudioMime
            })
          });
          const payload = await response.json().catch(function () {
            return { ok: false, message: "Invalid response." };
          });
          if (!response.ok || !payload.ok) {
            throw new Error(payload.message || "Unable to save this record.");
          }
          respondSaveFeedback.innerHTML = "<strong>Saved to your study record</strong><span>You can still make multiple attempts and save</span>";
          respondSaveFeedback.classList.remove("hidden");
          respondSaveBtn.textContent = "Saved. Start second attempt";
        } catch (error) {
          respondSaveFeedback.innerHTML = "<strong>" + escapeHtml((error && error.message) || "Unable to save this record.") + "</strong><span>Please log in first, then try again.</span>";
          respondSaveFeedback.classList.remove("hidden");
          respondSaveBtn.textContent = "Try Again";
        } finally {
          respondSaveBtn.disabled = false;
        }
      });
    }

    if (respondShareBtn && respondSaveError && respondSaveFeedback) {
      respondShareBtn.addEventListener("click", async function () {
        if (!currentAudioData) {
          respondSaveError.textContent = "no recording yet";
          respondSaveError.classList.remove("hidden");
          respondSaveFeedback.classList.add("hidden");
          return;
        }

        respondSaveError.classList.add("hidden");
        respondShareBtn.disabled = true;
        try {
          if (!currentAudioShareUrl) {
            const extension = getAudioExtensionFromMime(currentAudioMime);
            const audioFile = dataUrlToFile(currentAudioData, "response-" + video.id + "." + extension, currentAudioMime);
            const uploaded = await uploadPracticeAsset(audioFile, "audio");
            currentAudioShareUrl = String(uploaded.url || "");
          }
          const sharedContent = buildRespondShareContent(currentAudioShareUrl || currentAudioData);
          openShareModal({
            forumDraft: {
              title: "",
              content: sharedContent
            },
            chatContent: sharedContent
          });
        } catch (error) {
          respondSaveFeedback.innerHTML = "<strong>" + escapeHtml((error && error.message) || "Share failed.") + "</strong><span>Please try again.</span>";
          respondSaveFeedback.classList.remove("hidden");
        } finally {
          respondShareBtn.disabled = false;
        }
      });
    }

    recordBtn.addEventListener("click", async function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        recordStatus.textContent = "Recording is unavailable in this browser.";
        return;
      }

      if (recorder && recorder.state === "recording") {
        recorder.stop();
        return;
      }

      try {
        if (respondSaveError) respondSaveError.classList.add("hidden");
        currentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        recorder = new MediaRecorder(currentStream);
        currentAudioMime = recorder.mimeType || "audio/webm";

        recorder.ondataavailable = function (event) {
          if (event.data.size > 0) {
            chunks.push(event.data);
          }
        };

        recorder.onstop = function () {
          const blob = new Blob(chunks, { type: currentAudioMime });
          const reader = new FileReader();
          reader.onloadend = function () {
            currentAudioData = String(reader.result || "");
            currentAudioShareUrl = "";
            setPlaybackSource(currentAudioData);
            recordStatus.textContent = "Recorded. You can replay or save your answer.";
            if (respondSaveFeedback) respondSaveFeedback.classList.add("hidden");
          };
          reader.readAsDataURL(blob);

          setRespondIdleState();
          if (currentStream) {
            currentStream.getTracks().forEach(function (track) { track.stop(); });
            currentStream = null;
          }
        };

        recorder.start();
        recordBtn.classList.add("is-recording");
        recordBtn.textContent = "RECORDING";
        recordStatus.textContent = "Recording...";
      } catch (_err) {
        recordStatus.textContent = "Microphone permission denied or unavailable.";
        setRespondIdleState();
      }
    });
  }

  function bootstrap() {
    initBackButtons();
    const page = document.body.dataset.page;

    if (page === "training-home") {
      // training.html 页面 - 不需要修改
    } else if (page === "video-list") {
      initVideoList();
    } else if (page === "video-detail") {
      initVideoDetail();
    } else if (page === "response-detail") {
      initRespondDetail();
    }
  }

  document.addEventListener("DOMContentLoaded", bootstrap);
})();
