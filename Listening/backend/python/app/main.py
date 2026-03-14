from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.modules.listening_exam.router import router as listening_exam_router

app = FastAPI(title="Listening Exam Python Backend")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.include_router(listening_exam_router)
