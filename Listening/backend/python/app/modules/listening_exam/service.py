import json
import time
from typing import Any

from fastapi import HTTPException

from app.core.db import decode_json_column, get_conn


def normalize_mode(mode: str | None, default: str = "exam") -> str:
    if mode in ("practice", "exam"):
        return mode
    return default


def normalize_exam_row(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": row["id"],
        "title": row["title"],
        "difficulty": row["difficulty"],
        "durationSeconds": int(row["duration_seconds"]),
        "audioUrl": row["audio_url"],
        "transcript": row["transcript"],
    }


def load_questions(conn, exam_id: str) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT question_key, type, question_text, options_json, left_items_json,
                   right_items_json, ordering_items_json, correct_answer_json,
                   explanation, transcript_reference
            FROM questions
            WHERE exam_id = %s
            ORDER BY sort_order ASC
            """,
            (exam_id,),
        )
        rows = cur.fetchall()

    questions: list[dict[str, Any]] = []
    for row in rows:
        q = {
            "id": row["question_key"],
            "type": row["type"],
            "questionText": row["question_text"],
            "correctAnswer": decode_json_column(row["correct_answer_json"]),
            "explanation": row["explanation"],
            "transcriptReference": row["transcript_reference"],
        }
        options = decode_json_column(row["options_json"])
        left_items = decode_json_column(row["left_items_json"])
        right_items = decode_json_column(row["right_items_json"])
        ordering_items = decode_json_column(row["ordering_items_json"])
        if options is not None:
            q["options"] = options
        if left_items is not None:
            q["leftItems"] = left_items
        if right_items is not None:
            q["rightItems"] = right_items
        if ordering_items is not None:
            q["orderingItems"] = ordering_items
        questions.append(q)
    return questions


def is_correct(question: dict[str, Any], answer: Any) -> bool:
    qtype = question.get("type")
    correct_answer = question.get("correctAnswer")

    if qtype == "multiple_choice":
        return answer == correct_answer
    if qtype == "multiple_select":
        if not isinstance(answer, list) or not isinstance(correct_answer, list):
            return False
        return sorted(answer) == sorted(correct_answer)
    if qtype == "fill_blank":
        a = str(answer or "").strip().lower()
        b = str(correct_answer or "").strip().lower()
        return a != "" and a == b
    if qtype == "matching":
        if not isinstance(answer, dict) or not isinstance(correct_answer, dict):
            return False
        return dict(sorted(answer.items())) == dict(sorted(correct_answer.items()))
    if qtype == "ordering":
        return isinstance(answer, list) and isinstance(correct_answer, list) and answer == correct_answer
    return False


def list_exams_data():
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT e.id, e.title, e.difficulty, e.duration_seconds,
                       COUNT(q.id) AS question_count
                FROM exams e
                LEFT JOIN questions q ON q.exam_id = e.id
                GROUP BY e.id, e.title, e.difficulty, e.duration_seconds
                ORDER BY e.created_at ASC
                """
            )
            rows = cur.fetchall()
        conn.close()
        return {
            "exams": [
                {
                    "id": r["id"],
                    "title": r["title"],
                    "difficulty": r["difficulty"],
                    "durationSeconds": int(r["duration_seconds"]),
                    "questionCount": int(r["question_count"] or 0),
                }
                for r in rows
            ]
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def get_exam_data(exam_id: str):
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute("SELECT * FROM exams WHERE id = %s LIMIT 1", (exam_id,))
            row = cur.fetchone()
        if not row:
            conn.close()
            raise HTTPException(status_code=404, detail="Exam not found")
        exam = normalize_exam_row(row)
        exam["questions"] = load_questions(conn, exam_id)
        conn.close()
        return {"exam": exam}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def exam_status_data(user_id: str, mode: str):
    try:
        conn = get_conn()
        statuses: dict[str, Any] = {}
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM exams")
            exam_rows = cur.fetchall()
            for exam_row in exam_rows:
                exam_id = exam_row["id"]
                cur.execute(
                    """
                    SELECT score, total
                    FROM exam_results
                    WHERE user_id = %s AND exam_id = %s AND mode = %s
                    LIMIT 1
                    """,
                    (user_id, exam_id, mode),
                )
                result = cur.fetchone()
                if result:
                    statuses[exam_id] = {
                        "status": "Completed",
                        "answeredQuestions": int(result["total"]),
                        "bestScore": f"{result['score']}/{result['total']}",
                    }
                else:
                    statuses[exam_id] = {
                        "status": "Not Started",
                        "answeredQuestions": 0,
                        "bestScore": "-",
                    }
        conn.close()
        return {"statuses": statuses}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def get_progress_data(exam_id: str, user_id: str, mode: str):
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT mode, current_question, answers_json, audio_time, answered_questions,
                       exam_duration_seconds, timer_seconds, exam_status, updated_at
                FROM exam_progress
                WHERE user_id = %s AND exam_id = %s AND mode = %s
                LIMIT 1
                """,
                (user_id, exam_id, mode),
            )
            row = cur.fetchone()
        conn.close()
        if not row:
            return {"progress": None}
        return {
            "progress": {
                "exam_id": exam_id,
                "mode": row["mode"],
                "current_question": int(row["current_question"]),
                "answers": decode_json_column(row["answers_json"]) or {},
                "audio_time": float(row["audio_time"]),
                "answered_questions": int(row["answered_questions"]),
                "exam_duration_seconds": int(row["exam_duration_seconds"]) if row["exam_duration_seconds"] is not None else None,
                "timer_seconds": int(row["timer_seconds"]),
                "exam_status": row["exam_status"],
                "updated_at": int(row["updated_at"].timestamp() * 1000) if row.get("updated_at") else 0,
            }
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def put_progress_data(exam_id: str, user_id: str, mode: str, body: dict[str, Any]):
    body_mode = normalize_mode(body.get("mode"), mode)
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO exam_progress
                  (user_id, exam_id, mode, current_question, answers_json, audio_time,
                   answered_questions, exam_duration_seconds, timer_seconds, exam_status)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                  mode = VALUES(mode),
                  current_question = VALUES(current_question),
                  answers_json = VALUES(answers_json),
                  audio_time = VALUES(audio_time),
                  answered_questions = VALUES(answered_questions),
                  exam_duration_seconds = VALUES(exam_duration_seconds),
                  timer_seconds = VALUES(timer_seconds),
                  exam_status = VALUES(exam_status)
                """,
                (
                    user_id,
                    exam_id,
                    body_mode,
                    int(body.get("current_question", 0)),
                    json.dumps(body.get("answers", {}), ensure_ascii=False),
                    float(body.get("audio_time", 0)),
                    int(body.get("answered_questions", 0)),
                    int(body["exam_duration_seconds"]) if body.get("exam_duration_seconds") is not None else None,
                    int(body.get("timer_seconds", 0)),
                    body.get("exam_status", "in_progress"),
                ),
            )
        conn.close()
        return {"ok": True}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def delete_progress_data(exam_id: str, user_id: str, mode: str):
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                "DELETE FROM exam_progress WHERE user_id = %s AND exam_id = %s AND mode = %s",
                (user_id, exam_id, mode),
            )
        conn.close()
        return {"ok": True}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def get_result_data(exam_id: str, user_id: str, mode: str):
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT mode, score, total, per_question_json, submitted_at
                FROM exam_results
                WHERE user_id = %s AND exam_id = %s AND mode = %s
                LIMIT 1
                """,
                (user_id, exam_id, mode),
            )
            row = cur.fetchone()
        conn.close()
        if not row:
            return {"result": None}
        return {
            "result": {
                "examId": exam_id,
                "mode": row["mode"],
                "score": int(row["score"]),
                "total": int(row["total"]),
                "perQuestion": decode_json_column(row["per_question_json"]) or [],
                "submittedAt": int(row["submitted_at"]),
            }
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def put_result_data(exam_id: str, user_id: str, mode: str, body: dict[str, Any]):
    body_mode = normalize_mode(body.get("mode"), mode)
    try:
        conn = get_conn()
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO exam_results
                  (user_id, exam_id, mode, score, total, per_question_json, submitted_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                  mode = VALUES(mode),
                  score = VALUES(score),
                  total = VALUES(total),
                  per_question_json = VALUES(per_question_json),
                  submitted_at = VALUES(submitted_at)
                """,
                (
                    user_id,
                    exam_id,
                    body_mode,
                    int(body.get("score", 0)),
                    int(body.get("total", 0)),
                    json.dumps(body.get("perQuestion", []), ensure_ascii=False),
                    int(body.get("submittedAt", int(time.time() * 1000))),
                ),
            )
        conn.close()
        return {"ok": True}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")


def submit_exam_data(exam_id: str, user_id: str, mode: str, body: dict[str, Any]):
    answers = body.get("answers") if isinstance(body.get("answers"), dict) else {}
    submit_mode = normalize_mode(body.get("mode"), normalize_mode(mode))
    try:
        conn = get_conn()
        questions = load_questions(conn, exam_id)
        if not questions:
            conn.close()
            raise HTTPException(status_code=404, detail="Exam not found")
        per_question: list[dict[str, Any]] = []
        for q in questions:
            qid = q["id"]
            user_answer = answers.get(qid)
            correct = is_correct(q, user_answer)
            per_question.append({
                "questionId": qid,
                "correct": correct,
                "userAnswer": user_answer,
                "correctAnswer": q.get("correctAnswer"),
                "explanation": q.get("explanation"),
            })
        score = len([row for row in per_question if row["correct"] is True])
        result = {
            "examId": exam_id,
            "mode": submit_mode,
            "score": score,
            "total": len(questions),
            "perQuestion": per_question,
            "submittedAt": int(time.time() * 1000),
        }
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO exam_results
                  (user_id, exam_id, mode, score, total, per_question_json, submitted_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                  mode = VALUES(mode),
                  score = VALUES(score),
                  total = VALUES(total),
                  per_question_json = VALUES(per_question_json),
                  submitted_at = VALUES(submitted_at)
                """,
                (
                    user_id,
                    exam_id,
                    submit_mode,
                    result["score"],
                    result["total"],
                    json.dumps(result["perQuestion"], ensure_ascii=False),
                    result["submittedAt"],
                ),
            )
            cur.execute(
                "DELETE FROM exam_progress WHERE user_id = %s AND exam_id = %s AND mode = %s",
                (user_id, exam_id, submit_mode),
            )
        conn.close()
        return {"result": result}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Server error: {e}")
