const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const toastEl = $("#toast");
const toastTextEl = $("#toastText");
let toastTimer = null;

function toast(msg) {
  if (!toastEl || !toastTextEl) return;
  toastTextEl.textContent = msg;
  toastEl.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toastEl.hidden = true), 1600);
}

function setActiveView(view) {
  $$(".nav__item").forEach((btn) => btn.classList.toggle("isActive", btn.dataset.view === view));
  $$("[data-panel]").forEach((panel) => {
    panel.hidden = panel.dataset.panel !== view;
  });
}

let starred = false;
let selectedMinutes = 10;

function highlightMode(minutes) {
  selectedMinutes = minutes;
  $$(".modeCard").forEach((c) => c.classList.toggle("isSelected", Number(c.dataset.minutes) === minutes));
  const btnStart = $("#btnStartSession");
  if (btnStart) btnStart.textContent = `Start ${minutes}-minute session →`;
}

function speakWord(text) {
  const synth = window.speechSynthesis;
  if (!synth || typeof SpeechSynthesisUtterance === "undefined") {
    toast("Your browser does not support speech synthesis.");
    return;
  }
  synth.cancel();
  const u = new SpeechSynthesisUtterance(text);
  u.lang = "en-US";
  u.rate = 0.95;
  synth.speak(u);
}

function updateStarUI() {
  const btn = $("#btnStar");
  if (!btn) return;
  btn.setAttribute("aria-pressed", starred ? "true" : "false");
  btn.textContent = starred ? "★" : "☆";
}

function wire() {
  // Navigation
  $$(".nav__item").forEach((btn) => {
    btn.addEventListener("click", () => {
      const view = btn.dataset.view;
      setActiveView(view);
      if (view) window.location.hash = `#${view}`;
    });
  });

  // Hero buttons
  $("#btnGoModes")?.addEventListener("click", () => {
    setActiveView("home");
    $("#modes")?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  $("#btnGoWordbank")?.addEventListener("click", () => setActiveView("wordbank"));

  // Quick start + future social entry
  $("#btnStartSession")?.addEventListener("click", () => {
    toast(`Starting a ${selectedMinutes}-minute session (UI)`);
  });
  $("#btnInvite")?.addEventListener("click", () => toast("Friends & groups will be available in a future version."));

  // Mode selection
  $$(".modeCard").forEach((card) => {
    card.addEventListener("click", () => {
      highlightMode(Number(card.dataset.minutes) || 10);
      toast(`Mode selected: ${selectedMinutes} minutes`);
    });
  });

  // Preview actions
  $("#btnSpeak")?.addEventListener("click", () => speakWord($("#wordText")?.textContent || ""));
  $("#btnStar")?.addEventListener("click", () => {
    starred = !starred;
    updateStarUI();
    toast(starred ? "Saved" : "Removed");
  });
  $("#btnTryMatch")?.addEventListener("click", () => toast("Image ↔ sentence/word matching (UI)"));
  $("#btnTrySpelling")?.addEventListener("click", () => toast("Fill missing letters (UI)"));
  $("#btnWhatIncluded")?.addEventListener("click", () =>
    toast("Planned: image match, audio match, spelling completion, and more.")
  );
  $("#btnEditProfile")?.addEventListener("click", () => toast("Edit profile (UI)"));
  $("#btnAddGoal")?.addEventListener("click", () => toast("Set a goal (UI)"));

  // Word bank search (UI)
  const search = $("#searchInput");
  const empty = $("#searchEmpty");
  const rows = $$(".listRow");
  if (search) {
    const run = () => {
      const q = search.value.trim().toLowerCase();
      let shown = 0;
      rows.forEach((r) => {
        const text = r.textContent?.toLowerCase() || "";
        const ok = !q || text.includes(q);
        r.hidden = !ok;
        if (ok) shown += 1;
      });
      if (empty) empty.hidden = shown !== 0;
    };
    search.addEventListener("input", run);
    run();
  }
}

updateStarUI();
wire();

// Initial state
highlightMode(10);
setActiveView(window.location.hash?.replace("#", "") || "home");
