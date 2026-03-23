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
    const typeEl = qs("#filterType");
    const difficultyEl = qs("#filterDifficulty");
    const countryEl = qs("#filterCountry");
    const resultList = qs("#videoResultList");
    const resultsCount = qs("#resultsCount");

    modeTitle.textContent = modeInfo.label;

    const modeVideos = data.videos.filter(function (video) {
      return video.mode === mode;
    });

    const allTypes = ["All"].concat(Array.from(new Set(modeVideos.map(function (v) { return v.type; }))));
    const allDifficulties = ["All"].concat(Array.from(new Set(modeVideos.map(function (v) { return v.difficulty; }))));
    const allCountries = ["All"].concat(Array.from(new Set(modeVideos.map(function (v) { return v.country; }))));

    buildOptions(allTypes, typeEl);
    buildOptions(allDifficulties, difficultyEl);
    buildOptions(allCountries, countryEl);

    function filteredVideos() {
      return modeVideos.filter(function (video) {
        const passType = typeEl.value === "All" || video.type === typeEl.value;
        const passDifficulty = difficultyEl.value === "All" || video.difficulty === difficultyEl.value;
        const passCountry = countryEl.value === "All" || video.country === countryEl.value;
        return passType && passDifficulty && passCountry;
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
        const questionLine = video.question ? "<p class='video-question'>Q: " + video.question + "</p>" : "";
        card.innerHTML =
          "<h4>" + video.title + "</h4>" +
          "<p>" + video.type + " | " + video.difficulty + " | " + video.country + "</p>" +
          questionLine +
          "<button class='btn-small btn-primary-small'>Open Practice</button>";

        card.querySelector("button").addEventListener("click", function () {
          window.location.href = "ui_draft_3.html?mode=" + mode + "&videoId=" + video.id;
        });

        resultList.appendChild(card);
      });
    }

    [typeEl, difficultyEl, countryEl].forEach(function (el) {
      el.addEventListener("change", renderResults);
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
    const metaEl = qs("#detailMeta");
    const videoEl = qs("#practiceVideo");
    const showAnswerBtn = qs("#showAnswerBtn");
    const playAnswerBtn = qs("#playAnswerBtn");
    const recordBtn = qs("#recordBtn");
    const answerArea = qs("#answerArea");
    const noteArea = qs("#noteArea");
    const saveNoteBtn = qs("#saveNoteBtn");
    const noteStatus = qs("#noteStatus");
    const answerAudioPlayer = qs("#answerAudioPlayer");
    const recordingStatus = qs("#recordingStatus");
    const userRecordingPlayer = qs("#userRecordingPlayer");
    const deleteUserRecordingBtn = qs("#deleteUserRecordingBtn");

    titleEl.textContent = video.title + " (" + modeInfo.label + ")";
    metaEl.textContent = video.type + " | " + video.difficulty + " | " + video.country;
    videoEl.src = "Videos/" + video.videoFile;

    if (video.question) {
      answerArea.classList.remove("hidden");
      answerArea.innerHTML = "<p><strong>Question:</strong> " + video.question + "</p>";
    }

    const noteStorageKey = "practice-note-" + mode + "-" + video.id;
    noteArea.value = localStorage.getItem(noteStorageKey) || "";

    saveNoteBtn.addEventListener("click", function () {
      localStorage.setItem(noteStorageKey, noteArea.value);
      noteStatus.textContent = "Saved at " + new Date().toLocaleTimeString();
    });

    showAnswerBtn.addEventListener("click", function () {
      if (!video.answerText) {
        answerArea.classList.remove("hidden");
        answerArea.innerHTML = "<p>No text answer for this mode.</p>";
        return;
      }
      answerArea.classList.remove("hidden");
      answerArea.innerHTML =
        (video.question ? "<p><strong>Question:</strong> " + video.question + "</p>" : "") +
        "<p><strong>Reference answer:</strong> " + video.answerText + "</p>";
    });

    if (video.answerAudioFile) {
      answerAudioPlayer.src = "Videos/" + video.answerAudioFile;
      playAnswerBtn.classList.remove("hidden");
      playAnswerBtn.addEventListener("click", function () {
        answerAudioPlayer.classList.remove("hidden");
        answerAudioPlayer.play().catch(function () {});
      });
    } else {
      playAnswerBtn.classList.add("hidden");
    }

    if (mode !== "retell") {
      recordBtn.classList.add("hidden");
    }

    let recorder = null;
    let chunks = [];
    let currentUserRecordingUrl = "";

    recordBtn.addEventListener("click", async function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        recordingStatus.textContent = "Recording is unavailable in this browser context.";
        return;
      }

      if (recorder && recorder.state === "recording") {
        recorder.stop();
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        chunks = [];
        recorder = new MediaRecorder(stream);
        recorder.ondataavailable = function (event) {
          if (event.data.size > 0) {
            chunks.push(event.data);
          }
        };
        recorder.onstop = function () {
          const blob = new Blob(chunks, { type: "audio/webm" });
          if (currentUserRecordingUrl) {
            URL.revokeObjectURL(currentUserRecordingUrl);
          }
          currentUserRecordingUrl = URL.createObjectURL(blob);
          userRecordingPlayer.src = currentUserRecordingUrl;
          userRecordingPlayer.classList.remove("hidden");
          deleteUserRecordingBtn.classList.remove("hidden");
          recordingStatus.textContent = "Recording complete. You can replay it now.";
          recordBtn.textContent = "Record My Retell";
          stream.getTracks().forEach(function (track) { track.stop(); });
        };
        recorder.start();
        recordBtn.textContent = "Stop Recording";
        recordingStatus.textContent = "Recording...";
      } catch (_err) {
        recordingStatus.textContent = "Microphone permission denied or unsupported.";
      }
    });

    deleteUserRecordingBtn.addEventListener("click", function () {
      if (currentUserRecordingUrl) {
        URL.revokeObjectURL(currentUserRecordingUrl);
        currentUserRecordingUrl = "";
      }
      userRecordingPlayer.pause();
      userRecordingPlayer.removeAttribute("src");
      userRecordingPlayer.load();
      userRecordingPlayer.classList.add("hidden");
      deleteUserRecordingBtn.classList.add("hidden");
      recordingStatus.textContent = "Recording deleted.";
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
    }
  }

  document.addEventListener("DOMContentLoaded", bootstrap);
})();
