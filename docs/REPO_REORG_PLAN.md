# Repo Reorganization Plan (Safe-First)

## Goal

This plan reorganizes the repository structure without changing business logic.
All changes are designed to be incremental, reversible, and runtime-safe.

## Current Reality

This repository is a multi-module monorepo-like layout:

- Root PHP pages and entry (`index.php`, `home.html`, `owner.html`)
- Multiple frontend apps:
  - `forum-project/` (React + Vite + PHP API + SQL)
  - `message-center-project/` (React + Vite)
  - `admin_page/` (React + Vite)
- Multiple PHP modules:
  - `vocba_prac/`
  - `Auth/`
  - `challenge/`
  - `Academic-Practice/`
- Realtime service:
  - `voice-room-server/` (Node.js + ws)
- Shared runtime config:
  - `shared/acadbeat-local-config.js`

## Known Structure Issues

- Naming inconsistency (`vocba_prac` spelling, `doc/` and `docs/` coexistence)
- Duplicate historical files in root (for example, challenge assets)
- SQL files scattered across root and module folders
- Startup scripts duplicated across platforms with partially different conventions
- README content out of sync with current runnable modules

## Execution Principles

- No business logic edits in reorganization commits
- One small scope per commit, with rollback possible by commit
- Keep old paths compatible before deleting anything
- Validate after every phase with smoke tests

## Phased Plan

### Phase 1 - Documentation Alignment (zero runtime risk)

Actions:
- Align `README.md` with current architecture and startup commands
- Maintain a single architecture source in `docs/ARCHITECTURE.md`
- Add this reorganization plan and migration checklist

Validation:
- New developers can start local services using documentation only
- Main pages and module links open successfully

Rollback:
- Revert documentation commit

### Phase 2 - Non-runtime Asset Consolidation

Actions:
- Introduce `database/bootstrap/` and move root SQL there
- Keep root compatibility files or explicit migration notes before removal
- Move clearly obsolete duplicate static files to `archive/`

Validation:
- DB import path works from new location
- Existing runtime still resolves all required assets

Rollback:
- Revert move commit, or restore moved files from git

### Phase 3 - Startup Entry Normalization

Actions:
- Define `start_all.php` as canonical entry
- Keep OS wrappers as thin wrappers to canonical entry
- Unify runtime logs path convention

Validation:
- Windows and macOS startup behavior matches expected process list and ports
- `stop_all.php` stops all services started by either entry path

Rollback:
- Revert startup script commit

### Phase 4 - Naming and Boundary Hardening

Actions:
- Plan compatibility mapping for potential `vocba_prac` rename
- Consolidate `doc/` into `docs/` with redirects or clear mapping
- Clarify subproject boundaries in docs before physical moves

Validation:
- Full user flow smoke tests pass (login, forum, admin, message center, vocab)

Rollback:
- Revert the specific rename/move commit

## Smoke Test Checklist (Every Phase)

- Home page renders and navigation works
- Login and role-based redirect works
- Forum loads and can access API
- Message center loads for authenticated user
- Vocabulary module opens and core actions work
- Realtime service health endpoint responds (`/health`)

## Change Policy

- Any step that changes import/URL/path references must be isolated
- If uncertainty exists, do not delete; archive first
- Prefer additive compatibility over immediate replacement
