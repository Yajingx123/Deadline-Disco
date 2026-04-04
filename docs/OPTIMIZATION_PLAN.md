# Optimization Plan (Risk-graded)

## Objective

Improve maintainability and consistency without introducing runtime regressions.

## P0 - Safe and Immediate (no behavior change)

- Keep one architecture source of truth (`docs/ARCHITECTURE.md`)
- Keep one startup source of truth (`start_all.php`)
- Keep one SQL canonical directory (`database/bootstrap/`)
- Add/maintain migration and runbook docs
- Remove duplicate, unreferenced assets only after search verification

## P1 - Low Risk Engineering Hygiene

- Add a lightweight smoke test checklist script (URL reachability + health checks)
- Add a repository conventions document:
  - no new business files at repo root
  - new SQL must go under `database/`
  - avoid committing runtime log noise
- Normalize docs location (`doc/` -> `docs/` migration map)

## P2 - Medium Risk Structural Cleanup

- Introduce top-level grouping without moving runtime paths first:
  - `apps/` for frontend apps
  - `services/` for backend runtimes
  - `modules/` for PHP business modules
- Add compatibility wrappers or path aliases before physical moves
- Move in small batches with smoke test after each batch

## P3 - Higher Risk / Needs Dedicated Branch

- Rename `vocba_prac` to `vocab_prac` with compatibility redirects
- Unify frontend dependency versions across Vite subprojects
- Split mixed projects (for example frontend/api/sql mixed in one folder) into clearer boundaries

## Suggested Execution Order

1. Finish SQL migration sync and team workflow adoption
2. Add smoke test script and conventions doc
3. Start doc/docs consolidation
4. Create dedicated branch for naming and boundary refactors
