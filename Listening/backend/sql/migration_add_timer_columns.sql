USE listening_exam;

SET @db_name = DATABASE();

SET @has_exam_duration := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_progress'
    AND COLUMN_NAME = 'exam_duration_seconds'
);

SET @sql_exam_duration := IF(
  @has_exam_duration = 0,
  'ALTER TABLE exam_progress ADD COLUMN exam_duration_seconds INT NULL AFTER answered_questions',
  'SELECT "exam_duration_seconds already exists"'
);
PREPARE stmt1 FROM @sql_exam_duration;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @has_timer_seconds := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_progress'
    AND COLUMN_NAME = 'timer_seconds'
);

SET @sql_timer_seconds := IF(
  @has_timer_seconds = 0,
  'ALTER TABLE exam_progress ADD COLUMN timer_seconds INT NOT NULL DEFAULT 0 AFTER exam_duration_seconds',
  'SELECT "timer_seconds already exists"'
);
PREPARE stmt2 FROM @sql_timer_seconds;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @has_old_progress_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_progress'
    AND INDEX_NAME = 'uq_progress_user_exam'
);
SET @drop_old_progress_idx_sql := IF(
  @has_old_progress_idx > 0,
  'ALTER TABLE exam_progress DROP INDEX uq_progress_user_exam',
  'SELECT "old progress index not found"'
);
PREPARE stmt3 FROM @drop_old_progress_idx_sql;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @has_new_progress_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_progress'
    AND INDEX_NAME = 'uq_progress_user_exam_mode'
);
SET @add_new_progress_idx_sql := IF(
  @has_new_progress_idx = 0,
  'ALTER TABLE exam_progress ADD UNIQUE KEY uq_progress_user_exam_mode (user_id, exam_id, mode)',
  'SELECT "new progress index already exists"'
);
PREPARE stmt4 FROM @add_new_progress_idx_sql;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

SET @has_old_result_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_results'
    AND INDEX_NAME = 'uq_result_user_exam'
);
SET @drop_old_result_idx_sql := IF(
  @has_old_result_idx > 0,
  'ALTER TABLE exam_results DROP INDEX uq_result_user_exam',
  'SELECT "old result index not found"'
);
PREPARE stmt5 FROM @drop_old_result_idx_sql;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

SET @has_new_result_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'exam_results'
    AND INDEX_NAME = 'uq_result_user_exam_mode'
);
SET @add_new_result_idx_sql := IF(
  @has_new_result_idx = 0,
  'ALTER TABLE exam_results ADD UNIQUE KEY uq_result_user_exam_mode (user_id, exam_id, mode)',
  'SELECT "new result index already exists"'
);
PREPARE stmt6 FROM @add_new_result_idx_sql;
EXECUTE stmt6;
DEALLOCATE PREPARE stmt6;
