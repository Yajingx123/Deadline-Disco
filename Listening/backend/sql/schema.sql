CREATE DATABASE IF NOT EXISTS listening_exam DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE listening_exam;

CREATE TABLE IF NOT EXISTS exams (
  id VARCHAR(64) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  difficulty VARCHAR(64) NOT NULL,
  duration_seconds INT NOT NULL,
  audio_url TEXT NOT NULL,
  transcript TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS questions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  exam_id VARCHAR(64) NOT NULL,
  question_key VARCHAR(64) NOT NULL,
  type VARCHAR(64) NOT NULL,
  question_text TEXT NOT NULL,
  options_json JSON NULL,
  left_items_json JSON NULL,
  right_items_json JSON NULL,
  ordering_items_json JSON NULL,
  correct_answer_json JSON NOT NULL,
  explanation TEXT NULL,
  transcript_reference VARCHAR(255) NULL,
  sort_order INT NOT NULL,
  CONSTRAINT fk_questions_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  UNIQUE KEY uq_exam_question (exam_id, question_key)
);

CREATE TABLE IF NOT EXISTS exam_progress (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(128) NOT NULL,
  exam_id VARCHAR(64) NOT NULL,
  mode VARCHAR(32) NOT NULL,
  current_question INT NOT NULL DEFAULT 0,
  answers_json JSON NOT NULL,
  audio_time DOUBLE NOT NULL DEFAULT 0,
  answered_questions INT NOT NULL DEFAULT 0,
  exam_duration_seconds INT NULL,
  timer_seconds INT NOT NULL DEFAULT 0,
  exam_status VARCHAR(32) NOT NULL DEFAULT 'in_progress',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_progress_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  UNIQUE KEY uq_progress_user_exam_mode (user_id, exam_id, mode)
);

CREATE TABLE IF NOT EXISTS exam_results (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(128) NOT NULL,
  exam_id VARCHAR(64) NOT NULL,
  mode VARCHAR(32) NOT NULL,
  score INT NOT NULL,
  total INT NOT NULL,
  per_question_json JSON NOT NULL,
  submitted_at BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_results_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  UNIQUE KEY uq_result_user_exam_mode (user_id, exam_id, mode)
);
