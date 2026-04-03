# Archive Notes

This directory stores files removed from active runtime paths during safe cleanup.

## 2026-04-02

- Removed root-level duplicate files:
  - `challenge-home.js`
  - `challenge-home.css`

Reason:

- Runtime pages already load `challenge/challenge-home.js` and `challenge/challenge-home.css`.
- Root-level duplicates were not referenced and increased maintenance noise.
