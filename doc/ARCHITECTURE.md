# AcadBeat Repository Architecture (Dual UI Modes)

## Core Principles

- **One project, one account system, one data layer**: auth sessions are managed in `Auth/backend`, and all modules read the same logged-in user.
- **Classic mode (baseline acceptance path)**: browser entry is `home.html`; all major modules must remain fully usable.
- **Godot mode (alternate shell)**: same business flow through the Godot web export (`5500`) using `ui_mode=godot` / `ui=godot`.
- **Repo reorganization approach**: keep changes incremental, low-risk, and reversible.

## Directory Responsibilities

| Path | Responsibility |
|------|----------------|
| `Auth/` | Login, registration, session, same-origin cookie flow with PHP pages |
| `home.html` / `owner.html` / `technology.html` | Classic home, profile, technology pages |
| `Academic-Practice/` | Academic listening/speaking features (`practice-app.js` drives multi-page flow) |
| `vocba_prac/` | Vocabulary practice module |
| `forum-project/` | Classic forum frontend (Vite) |
| `admin_page/` | Admin frontend build/dev app |
| `message-center-project/` | Message center frontend |
| `GameUI/` | Reorganized UI module outputs used by Godot-linked pages |
| `gameUI_src/` | Godot project source; web export in `gameUI_src/Release` |
| `shared/` | Cross-module shared config (`acadbeat-local-config.js`) |
| `shared-nav.js` / `shared-nav.css` | Shared top navigation layer |
| `doc/` | Architecture, deployment, and team docs |

## Single Source of Local URL Truth

- File: `shared/acadbeat-local-config.js`
- Exposes: `window.ACADBEAT_LOCAL`
- Includes canonical local URLs such as admin, forum, message center, and Godot entry.
- HTML pages should load this script **before** role guards and `initializeAcadBeatNav`.
- If host/port changes, update both frontend config and backend CORS/redirect config together.

## Classic vs Godot Mode Switching

- Home page **Switch** writes `ui_mode=godot` and opens `godotWebEntryUrl`.
- Sub-pages read URL query `ui=godot` or cookie `ui_mode=godot` to select behavior/styling.
- Godot scene links should stay aligned with `ACADBEAT_LOCAL` URL strategy.

## Startup Scripts (Simplified)

Only keep one starter per OS:

- Linux: `php start_all_linux.php`
- macOS: `php start_all_mac.php`
- Windows: `php start_all_windows.php`

Optional full mode (extra dev services):

- `php start_all_linux.php --full`
- `php start_all_mac.php --full`
- `php start_all_windows.php --full`

## Required Runtime Services

Default start profile brings up:

- Main site: `http://127.0.0.1:8001`
- Realtime server: `ws://127.0.0.1:3001/ws`
- Godot web export static server: `http://127.0.0.1:5500`

The scripts also build key frontends and install runtime dependencies where needed.

## Acceptance Baseline (Classic Mode)

With normal user login in classic mode, the following should be reachable:

1. Academic flow (`Academic-Practice/...`)
2. Forum
3. Technology
4. Vocabulary module
5. Owner page
6. Message center

Admin users should be redirected to the admin app instead of student flows.

## Notes

- Godot mode is allowed to be partially styled/integrated as long as core navigation is not blocked.
- If you update Godot-side hardcoded URLs, keep them consistent with `shared/acadbeat-local-config.js`.
