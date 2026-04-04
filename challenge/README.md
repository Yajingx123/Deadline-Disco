# Challenge Module

Challenge-related code is organized here.

## Frontend

- `challenge-panel.html`
  Challenge modal markup injected into `home.html`
- `challenge-home.css`
  Challenge modal styles
- `challenge-home.js`
  Challenge modal behavior, realtime sync, and API calls

## Backend

- `api/challenge.php`
  Main challenge API entry
- `api/challenge-lib.php`
  Shared challenge business logic

## Related files outside this folder

- `home.html`
  Contains the `challengeMount` placeholder and loads the challenge bundle
- `sql/101_acadbeat_core_tables.sql` (canonical)
  Challenge table definitions
- `sql/102_acadbeat_core_seed_data.sql` (canonical)
  Challenge reset and seed cleanup data
