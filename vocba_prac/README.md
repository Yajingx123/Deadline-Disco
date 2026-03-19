# Vocabulary Practice · UI

Static UI for the **Vocabulary Practice** module (students entering college soon).

## Page flow

- **index.html** — Intro: “Up next: Vocabulary session mode” → Enter goes to **profile.html**
- **profile.html** — Profile (student info, goal, streak)
- **wordbank.html** — Word Bank: choose word books (Daily + CS, Mechanical, Civil, Traffic, Math)
- **practice.html** — Practice: **only** 10 / 30 / 60 minute session modes
- **progress.html** — Progress: learning by word book, daily usage time, daily plan (UI ready for strong interaction)
- **friends.html** — Friends & Groups (reserved)
- **vocab.html** — Redirects to profile.html (kept for old links)

## Layout

- **Top horizontal nav** (no sidebar): Word Bank · Practice · Progress · Profile · Friends & Groups (Module Home removed)
- Same top bar on all sub-pages: brand, nav links, Focus, Settings, Avatar

## How to preview

1. Open **index.html** in the browser, then click **Enter module**
2. Use the top nav to switch between Word Bank, Practice, Progress, Profile, Friends & Groups

## Files

- **index.html** — Intro gate
- **profile.html**, **wordbank.html**, **practice.html**, **progress.html**, **friends.html** — Sub-pages
- **vocab.html** — Redirect to profile
- **styles.css** — Theme, topnav, cards, wordbooks, daily plan, progress bars
- **practice-session.html** — Run a practice session (learn cards + image/audio/fill exercises). Uses 10 test words; mode from `?mode=10|30|60`.
- **script.js** — (used only on legacy/other pages if needed)

### Practice session and test words

- **Test words** (in `practice-session.html`):
  - **Daily (日常)**: apple, banana, orange, grape, pear, peach, watermelon, strawberry, pineapple, mango.
  - **CS / Mechanical / Civil / Traffic / Math**: run, eat, see, go, do, play, read, write, listen, watch (same list for all four for now).
- Session uses the **selected word books** from Word Bank (localStorage): words from selected books are merged and deduplicated. If none selected, Daily is used.
- **Images**: Optional. Put images in `images/words/` named by word (e.g. `apple.jpg`). If missing, a placeholder is shown.
