from fastapi import APIRouter, Query, Request

from .service import (
    delete_progress_data,
    exam_status_data,
    get_exam_data,
    get_progress_data,
    get_result_data,
    list_exams_data,
    normalize_mode,
    put_progress_data,
    put_result_data,
    submit_exam_data,
)

router = APIRouter(prefix="/api")


@router.get("/health")
def health():
    return {"ok": True, "service": "listening-exam-python-backend"}


@router.get("/exams")
def list_exams():
    return list_exams_data()


@router.get("/exams/{exam_id}")
def get_exam(exam_id: str):
    return get_exam_data(exam_id)


@router.get("/exam-status")
def exam_status(userId: str = Query(default="demo-user"), mode: str = Query(default="exam")):
    return exam_status_data(userId.strip() or "demo-user", normalize_mode(mode))


@router.get("/exams/{exam_id}/progress")
def get_progress(exam_id: str, userId: str = Query(default="demo-user"), mode: str = Query(default="exam")):
    return get_progress_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode))


@router.put("/exams/{exam_id}/progress")
async def put_progress(
    exam_id: str,
    request: Request,
    userId: str = Query(default="demo-user"),
    mode: str = Query(default="exam"),
):
    body = await request.json()
    return put_progress_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode), body)


@router.delete("/exams/{exam_id}/progress")
def delete_progress(exam_id: str, userId: str = Query(default="demo-user"), mode: str = Query(default="exam")):
    return delete_progress_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode))


@router.get("/exams/{exam_id}/result")
def get_result(exam_id: str, userId: str = Query(default="demo-user"), mode: str = Query(default="exam")):
    return get_result_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode))


@router.put("/exams/{exam_id}/result")
async def put_result(
    exam_id: str,
    request: Request,
    userId: str = Query(default="demo-user"),
    mode: str = Query(default="exam"),
):
    body = await request.json()
    return put_result_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode), body)


@router.post("/exams/{exam_id}/submit")
async def submit_exam(
    exam_id: str,
    request: Request,
    userId: str = Query(default="demo-user"),
    mode: str = Query(default="exam"),
):
    body = await request.json()
    return submit_exam_data(exam_id, userId.strip() or "demo-user", normalize_mode(mode), body)
