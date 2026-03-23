(function () {
  const data = window.PracticeData;
  if (!data) {
    return;
  }

  function qs(selector) {
    return document.querySelector(selector);
  }

  function getParam(name, fallback) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name) || fallback;
  }

  function goBack(target) {
    if (window.history.length > 1) {
      window.history.back();
      return;
    }
    if (target) {
      window.location.href = target;
    }
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

  function getPersonMetaText(video) {
    const author = video.author || "Unknown";
    const country = video.country || "N/A";
    const flag = getCountryFlag(country);
    const timeSpecific = video.timeSpecific || video.duration || "N/A";
    return author + " | " + flag + " " + country + " | " + timeSpecific;
  }

  function getCountryFlag(country) {
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
      au: "AU",
      us: "US"
    };

    const iso = isoMap[normalized] || (normalized.length === 2 ? normalized.toUpperCase() : null);
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

  function getVideoSource(video) {
    return video.videoPath || ("Videos/" + video.videoFile);
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

    if (video.transcriptText) {
      contentEl.textContent = video.transcriptText;
      return;
    }

    if (!video.transcriptPath) {
      contentEl.textContent = "No transcript file for this video.";
      return;
    }

    try {
      const response = await fetch(video.transcriptPath);
      if (!response.ok) {
        throw new Error("transcript fetch failed");
      }
      contentEl.textContent = await response.text();
    } catch (_err) {
      contentEl.textContent = "Failed to load transcript text.";
    }
  }

  function applySubtitleTrack(videoEl, video) {
    videoEl.querySelectorAll("track").forEach(function (trackEl) {
      trackEl.remove();
    });
    if (!video.subtitlePath) {
      return;
    }
    const track = document.createElement("track");
    track.kind = "subtitles";
    track.label = "English";
    track.srclang = "en";
    track.src = video.subtitlePath;
    track.default = true;
    videoEl.appendChild(track);
  }

  function parseVttTime(timeText) {
    const t = String(timeText || "").trim();
    const parts = t.split(":");
    if (parts.length < 2) {
      return 0;
    }
    if (parts.length === 2) {
      const secParts = parts[1].split(".");
      return Number(parts[0]) * 60 + Number(secParts[0]) + (Number(secParts[1] || 0) / 1000);
    }
    const secParts = parts[2].split(".");
    return Number(parts[0]) * 3600 + Number(parts[1]) * 60 + Number(secParts[0]) + (Number(secParts[1] || 0) / 1000);
  }

  function formatSubtitleTime(seconds) {
    const total = Math.max(0, Math.floor(seconds));
    const m = Math.floor(total / 60);
    const s = total % 60;
    return String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
  }

  function parseVttText(vttText) {
    return String(vttText || "")
      .replace(/\r/g, "")
      .split("\n\n")
      .map(function (block) { return block.trim(); })
      .filter(function (block) { return block && block !== "WEBVTT"; })
      .map(function (block) {
        const lines = block.split("\n").filter(function (line) { return line.trim(); });
        const timeLine = lines.find(function (line) { return line.indexOf("-->") !== -1; });
        if (!timeLine) {
          return null;
        }
        const timeParts = timeLine.split("-->");
        const start = parseVttTime(timeParts[0]);
        const text = lines.slice(lines.indexOf(timeLine) + 1).join(" ").trim();
        if (!text) {
          return null;
        }
        return { start: start, text: text };
      })
      .filter(function (item) { return Boolean(item); });
  }

  function buildSubtitleLines(videoEl, linesEl, subtitles) {
    linesEl.innerHTML = "";
    if (!subtitles || !subtitles.length) {
      linesEl.innerHTML = "<p class='subtitle-line-item'>Subtitle file is empty.</p>";
      return;
    }
    subtitles.forEach(function (item) {
      const line = document.createElement("p");
      line.className = "subtitle-line-item";
      line.textContent = "[" + formatSubtitleTime(item.start) + "] " + item.text;
      line.addEventListener("click", function () {
        videoEl.currentTime = Math.max(0, item.start + 0.01);
        if (videoEl.paused) {
          videoEl.play().catch(function () {
            // Ignore autoplay block.
          });
        }
      });
      linesEl.appendChild(line);
    });
  }

  function getSubtitlesFromTrack(videoEl) {
    const textTrack = videoEl.textTracks && videoEl.textTracks[0];
    if (!textTrack) {
      return [];
    }
    textTrack.mode = "hidden";
    const cues = textTrack.cues;
    if (!cues || !cues.length) {
      return [];
    }
    return Array.from(cues).map(function (cue) {
      return {
        start: cue.startTime,
        text: cue.text
      };
    });
  }

  async function initSubtitleDock(videoEl, toggleBtn, dockEl, linesEl, subtitlePath) {
    if (!videoEl || !toggleBtn || !dockEl || !linesEl) {
      return;
    }

    function setDockText(collapsed) {
      toggleBtn.textContent = collapsed ? "字幕" : "收起字幕";
    }

    toggleBtn.addEventListener("click", function () {
      const willShow = dockEl.classList.contains("hidden");
      dockEl.classList.toggle("hidden", !willShow);
      setDockText(!willShow);
    });

    setDockText(true);
    linesEl.innerHTML = "";
    if (!subtitlePath) {
      linesEl.innerHTML = "<p class='subtitle-line-item'>No subtitle file for this video.</p>";
      return;
    }
    try {
      const response = await fetch(subtitlePath);
      if (!response.ok) {
        throw new Error("subtitle fetch failed");
      }
      const raw = await response.text();
      const subtitles = parseVttText(raw);
      buildSubtitleLines(videoEl, linesEl, subtitles);
    } catch (_err) {
      // Fallback for local file:// environments where fetch may be blocked.
      window.setTimeout(function () {
        const subtitlesFromTrack = getSubtitlesFromTrack(videoEl);
        if (subtitlesFromTrack.length) {
          buildSubtitleLines(videoEl, linesEl, subtitlesFromTrack);
          return;
        }
        linesEl.innerHTML = "<p class='subtitle-line-item'>Failed to load subtitle file.</p>";
      }, 500);
    }
  }

  function initBackButtons() {
    document.querySelectorAll(".back-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        goBack(btn.dataset.backTarget || "index.html");
      });
    });
  }

  function initTrainingHome() {
    const topicList = qs("#topicList");
    const topicTitle = qs("#topicTitle");
    const topicDesc = qs("#topicDesc");
    const topicPosts = qs("#topicPosts");
    const textPostForm = qs("#textPostForm");
    const postText = qs("#postText");
    const recordBtn = qs("#topicRecordBtn");
    const recordStatus = qs("#topicRecordStatus");

    if (!topicList || !topicTitle || !topicDesc || !topicPosts || !textPostForm || !postText || !recordBtn || !recordStatus) {
      return;
    }

    let activeTopicId = data.discussionTopics[0].id;
    let topicRecorder = null;
    let topicChunks = [];

    function getTopicStorageKey(topicId) {
      return "practice-topic-posts-" + topicId;
    }

    function loadPosts(topicId) {
      try {
        return JSON.parse(localStorage.getItem(getTopicStorageKey(topicId)) || "[]");
      } catch (_err) {
        return [];
      }
    }

    function savePosts(topicId, posts) {
      localStorage.setItem(getTopicStorageKey(topicId), JSON.stringify(posts));
    }

    function renderTopics() {
      topicList.innerHTML = "";
      data.discussionTopics.forEach(function (topic) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "topic-btn" + (topic.id === activeTopicId ? " active" : "");
        btn.textContent = topic.title;
        btn.addEventListener("click", function () {
          activeTopicId = topic.id;
          renderTopics();
          renderThread();
        });
        topicList.appendChild(btn);
      });
    }

    function renderThread() {
      const topic = data.discussionTopics.find(function (item) {
        return item.id === activeTopicId;
      });
      if (!topic) {
        return;
      }

      topicTitle.textContent = topic.title;
      topicDesc.textContent = topic.description;
      const posts = loadPosts(activeTopicId);
      topicPosts.innerHTML = "";

      if (!posts.length) {
        const empty = document.createElement("p");
        empty.className = "empty-hint";
        empty.textContent = "No replies yet. Be the first one to post.";
        topicPosts.appendChild(empty);
        return;
      }

      posts.forEach(function (post, index) {
        const item = document.createElement("article");
        item.className = "post-item";
        const time = new Date(post.createdAt).toLocaleString();
        item.innerHTML = "<p class='post-time'>" + time + "</p>";

        if (post.kind === "text") {
          const content = document.createElement("p");
          content.className = "post-text";
          content.textContent = post.content;
          item.appendChild(content);
        } else if (post.kind === "audio") {
          const audioRow = document.createElement("div");
          audioRow.className = "audio-item-row";

          const audio = document.createElement("audio");
          audio.controls = true;
          audio.src = post.content;

          const deleteBtn = document.createElement("button");
          deleteBtn.type = "button";
          deleteBtn.className = "btn-small delete-audio-btn";
          deleteBtn.textContent = "Delete";
          deleteBtn.addEventListener("click", function () {
            const currentPosts = loadPosts(activeTopicId);
            currentPosts.splice(index, 1);
            savePosts(activeTopicId, currentPosts);
            renderThread();
          });

          audioRow.appendChild(audio);
          audioRow.appendChild(deleteBtn);
          item.appendChild(audioRow);
        }
        topicPosts.appendChild(item);
      });
    }

    textPostForm.addEventListener("submit", function (event) {
      event.preventDefault();
      const text = postText.value.trim();
      if (!text) {
        return;
      }
      const posts = loadPosts(activeTopicId);
      posts.unshift({
        kind: "text",
        content: text,
        createdAt: Date.now()
      });
      savePosts(activeTopicId, posts);
      postText.value = "";
      renderThread();
    });

    recordBtn.addEventListener("click", async function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        recordStatus.textContent = "Recording is unavailable in this browser context.";
        return;
      }

      if (topicRecorder && topicRecorder.state === "recording") {
        topicRecorder.stop();
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        topicChunks = [];
        topicRecorder = new MediaRecorder(stream);
        topicRecorder.ondataavailable = function (event) {
          if (event.data.size > 0) {
            topicChunks.push(event.data);
          }
        };
        topicRecorder.onstop = function () {
          const blob = new Blob(topicChunks, { type: "audio/webm" });
          const audioUrl = URL.createObjectURL(blob);
          const posts = loadPosts(activeTopicId);
          posts.unshift({
            kind: "audio",
            content: audioUrl,
            createdAt: Date.now()
          });
          savePosts(activeTopicId, posts);
          recordBtn.textContent = "Record Voice Reply";
          recordStatus.textContent = "Voice reply posted.";
          renderThread();
          stream.getTracks().forEach(function (track) { track.stop(); });
        };
        topicRecorder.start();
        recordBtn.textContent = "Stop Recording";
        recordStatus.textContent = "Recording...";
      } catch (_err) {
        recordStatus.textContent = "Microphone permission denied or unsupported.";
      }
    });

    renderTopics();
    renderThread();
  }

  function initVideoList() {
    const mode = getParam("mode", "understand");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.understand;
    const modeTitle = qs("#modeTitle");
    const searchEl = qs("#smartSearch");
    const typeEl = qs("#smartType");
    const difficultyEl = qs("#smartDifficulty");
    const durationEl = qs("#smartDuration");
    const sourceEl = qs("#smartSource");
    const countryEl = qs("#smartCountry");
    const clearBtn = qs("#clearSmartFilters");
    const resultList = qs("#videoResultList");
    const resultsCount = qs("#resultsCount");

    if (!modeTitle || !searchEl || !typeEl || !difficultyEl || !durationEl || !sourceEl || !countryEl || !clearBtn || !resultList || !resultsCount) {
      return;
    }

    modeTitle.textContent = modeInfo.label;

    const modeVideos = data.videos.filter(function (video) {
      return video.mode === mode;
    });

    function normalizedType(video) {
      return video.type === "Academic" ? "Academic" : "Campus&Life";
    }

    function getCoverFile(video) {
      if (video.coverFile) {
        return video.coverFile;
      }
      const match = String(video.id || "").match(/(\d+)$/);
      if (!match) {
        return null;
      }
      const baseIndex = Number(match[1]);
      if (mode === "respond") {
        return String(baseIndex + 12) + ".png";
      }
      return String(baseIndex) + ".png";
    }

    function getCoverFolder() {
      return mode === "respond" ? "cover2" : "cover";
    }

    const allTypes = ["All", "Campus&Life", "Academic"];
    const allCountries = ["All"].concat(Array.from(new Set(modeVideos.map(function (v) { return v.country; }))));

    buildOptions(allTypes, typeEl);
    buildOptions(allCountries, countryEl);
    typeEl.options[0].textContent = "Type: All";
    countryEl.options[0].textContent = "Country: All";

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
        const metaLine =
          "<div class='video-meta-row'>" +
          "<span class='video-meta-pill'>" + normalizedType(video) + "</span>" +
          "<span class='video-meta-pill'>" + video.difficulty + "</span>" +
          "<span class='video-meta-pill'>" + (video.duration || "N/A") + "</span>" +
          "<span class='video-meta-pill'>" + (video.source || "N/A") + "</span>" +
          "<span class='video-meta-pill'>" + getCountryFlag(video.country) + " " + video.country + "</span>" +
          "</div>";
        const coverFile = getCoverFile(video);
        const coverFolder = getCoverFolder();
        const coverMedia =
          "<div class='video-cover-box'>" +
          (coverFile
            ? "<img class='video-cover-image' src='" + coverFolder + "/" + coverFile + "' alt='Video cover'>"
            : "<div class='video-cover-placeholder'>Video Cover</div>") +
          "<button class='btn-small video-go-btn' type='button' aria-label='Play video' title='Play'>&#9658;</button>" +
          "<div class='video-cover-title'>" + video.title + "</div>" +
          "</div>";
        const personLine = "<p class='video-person-line'>" + getPersonMetaText(video) + "</p>";
        const questionLine = video.question ? "<p class='video-question'>Q: " + video.question + "</p>" : "";
        card.innerHTML =
          metaLine +
          coverMedia +
          personLine +
          questionLine;

        card.querySelector(".video-go-btn").addEventListener("click", function () {
          const targetPage = mode === "respond" ? "ui_draft_respond.html" : "ui_draft_3.html";
          window.location.href = targetPage + "?mode=" + mode + "&videoId=" + video.id;
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

    countryEl.addEventListener("change", function () {
      filterState.country = countryEl.value;
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
      countryEl.value = "All";
      renderResults();
    });

    renderResults();
  }

  function initVideoDetail() {
    const mode = getParam("mode", "understand");
    const videoId = getParam("videoId", "");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.understand;
    const video = data.videos.find(function (item) {
      return item.id === videoId;
    });

    if (!video) {
      alert("Video not found. Please return to the previous page.");
      return;
    }

    const titleEl = qs("#detailTitle");
    const detailPersonMetaEl = qs("#detailPersonMeta");
    const metaEl = qs("#detailMeta");
    const videoEl = qs("#practiceVideo");
    const transcriptToggleBtn = qs("#transcriptToggleBtn");
    const transcriptCloseBtn = qs("#transcriptCloseBtn");
    const transcriptPanelEl = qs("#transcriptPanel");
    const transcriptContentEl = qs("#transcriptContent");
    const noteLayoutEl = qs(".detail-note-layout");
    const studyWorkspaceEl = qs("#studyWorkspace");
    const noteShareBtn = qs("#noteShareBtn");
    const noteDockToggleBtn = qs("#noteDockToggleBtn");
    const noteMainContentEl = qs("#noteMainContent");
    const noteKeyWordEl = qs("#noteKeyWord");
    const notePersonalViewEl = qs("#notePersonalView");

    if (!titleEl || !detailPersonMetaEl || !metaEl || !videoEl || !studyWorkspaceEl || !noteShareBtn || !noteDockToggleBtn || !noteMainContentEl || !noteKeyWordEl || !notePersonalViewEl) {
      return;
    }

    titleEl.textContent = video.title;
    detailPersonMetaEl.textContent = getPersonMetaText(video);
    metaEl.innerHTML =
      "<span class='detail-meta-pill'>" + modeInfo.label + "</span>" +
      "<span class='detail-meta-pill'>" + video.type + "</span>" +
      "<span class='detail-meta-pill'>" + video.difficulty + "</span>" +
      "<span class='detail-meta-pill'>" + (video.duration || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + (video.source || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + getCountryFlag(video.country) + " " + video.country + "</span>";
    videoEl.src = getVideoSource(video);
    applySubtitleTrack(videoEl, video);
    initTranscriptPanel(transcriptToggleBtn, transcriptCloseBtn, transcriptPanelEl, transcriptContentEl, video);

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

    function persistNoteDraft() {
      localStorage.setItem(noteStorageKey, JSON.stringify({
        mainContent: noteMainContentEl.value.trim(),
        keyWord: noteKeyWordEl.value.trim(),
        personalView: notePersonalViewEl.value.trim()
      }));
    }

    noteMainContentEl.addEventListener("input", persistNoteDraft);
    noteKeyWordEl.addEventListener("input", persistNoteDraft);
    notePersonalViewEl.addEventListener("input", persistNoteDraft);

    noteShareBtn.addEventListener("click", function () {
      persistNoteDraft();
      // Placeholder for future route/jump behavior.
    });

    function refreshNoteDockBtnText() {
      const isCollapsed = studyWorkspaceEl.classList.contains("note-dock-collapsed");
      noteDockToggleBtn.textContent = isCollapsed ? "展开" : "折叠";
    }

    async function switchVideoFullscreenToWorkspace() {
      // Keep notes editable by switching from video-only fullscreen to workspace fullscreen.
      if (document.fullscreenElement !== videoEl) {
        return;
      }
      try {
        await document.exitFullscreen();
        await studyWorkspaceEl.requestFullscreen();
      } catch (_err) {
        // Ignore: browser may block chained fullscreen requests.
      } finally {
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

  function initRespondDetail() {
    const mode = getParam("mode", "respond");
    const videoId = getParam("videoId", "");
    const modeInfo = data.modeMeta[mode] || data.modeMeta.respond;
    const video = data.videos.find(function (item) {
      return item.id === videoId;
    });

    if (!video) {
      alert("Video not found. Please return to the previous page.");
      return;
    }

    const titleEl = qs("#respondTitle");
    const personMetaEl = qs("#respondPersonMeta");
    const metaEl = qs("#respondMeta");
    const transcriptToggleBtn = qs("#respondTranscriptToggleBtn");
    const transcriptCloseBtn = qs("#respondTranscriptCloseBtn");
    const transcriptPanelEl = qs("#respondTranscriptPanel");
    const transcriptContentEl = qs("#respondTranscriptContent");
    const videoEl = qs("#respondVideo");
    const recordBtn = qs("#respondRecordBtn");
    const recordStatus = qs("#respondRecordStatus");
    const playbackEl = qs("#respondPlayback");

    if (!titleEl || !personMetaEl || !metaEl || !transcriptToggleBtn || !transcriptPanelEl || !transcriptContentEl || !videoEl || !recordBtn || !recordStatus || !playbackEl) {
      return;
    }

    titleEl.textContent = video.title;
    personMetaEl.textContent = getPersonMetaText(video);
    metaEl.innerHTML =
      "<span class='detail-meta-pill'>" + modeInfo.label + "</span>" +
      "<span class='detail-meta-pill'>" + video.type + "</span>" +
      "<span class='detail-meta-pill'>" + video.difficulty + "</span>" +
      "<span class='detail-meta-pill'>" + (video.duration || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + (video.source || "N/A") + "</span>" +
      "<span class='detail-meta-pill'>" + getCountryFlag(video.country) + " " + video.country + "</span>";
    videoEl.src = getVideoSource(video);
    applySubtitleTrack(videoEl, video);
    initTranscriptPanel(transcriptToggleBtn, transcriptCloseBtn, transcriptPanelEl, transcriptContentEl, video);

    const responseStorageKey = "practice-response-audio-" + video.id;
    let recorder = null;
    let chunks = [];
    let currentStream = null;

    function setPlaybackSource(src) {
      playbackEl.src = src || "";
      if (!src) {
        playbackEl.removeAttribute("src");
        playbackEl.load();
      }
    }

    const savedAudio = localStorage.getItem(responseStorageKey);
    if (savedAudio) {
      setPlaybackSource(savedAudio);
      recordStatus.textContent = "Loaded your last recording.";
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
        currentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        recorder = new MediaRecorder(currentStream);

        recorder.ondataavailable = function (event) {
          if (event.data.size > 0) {
            chunks.push(event.data);
          }
        };

        recorder.onstop = function () {
          const blob = new Blob(chunks, { type: "audio/webm" });
          const reader = new FileReader();
          reader.onloadend = function () {
            const base64Audio = String(reader.result || "");
            localStorage.setItem(responseStorageKey, base64Audio);
            setPlaybackSource(base64Audio);
            recordStatus.textContent = "Recorded. You can now replay.";
          };
          reader.readAsDataURL(blob);

          recordBtn.textContent = "Start Recording";
          if (currentStream) {
            currentStream.getTracks().forEach(function (track) { track.stop(); });
            currentStream = null;
          }
        };

        recorder.start();
        recordBtn.textContent = "Stop Recording";
        recordStatus.textContent = "Recording...";
      } catch (_err) {
        recordStatus.textContent = "Microphone permission denied or unavailable.";
      }
    });
  }

  function bootstrap() {
    initBackButtons();
    const page = document.body.dataset.page;

    if (page === "training-home") {
      initTrainingHome();
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
