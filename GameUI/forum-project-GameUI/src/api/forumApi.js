// Reuse the canonical forum API layer so GameUI and classic UI always share
// the same backend behavior and SQL-facing endpoints.
export * from '../../../../forum-project/src/api/forumApi.js'
