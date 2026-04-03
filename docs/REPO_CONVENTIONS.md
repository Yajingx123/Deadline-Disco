# Repository Conventions

## Runtime and Entry

- Canonical startup entry: `php start_all.php`
- Windows bat launcher is only a wrapper and must not diverge behavior
- Canonical stop entry: `php stop_all.php`

## Directory Hygiene

- Do not add new business files directly under repository root
- Put repository-level SQL under `database/bootstrap/`
- Put helper scripts under `tools/`
- Keep historical artifacts under `archive/` if immediate deletion is risky

## URL Configuration

- Prefer `shared/acadbeat-local-config.js` as local URL source of truth
- Avoid introducing new hardcoded `127.0.0.1` URLs in feature code

## Validation

- Run smoke check after startup:
  - `php tools/smoke_check.php`
- If smoke check fails, fix startup/runtime issue before merging structure changes
