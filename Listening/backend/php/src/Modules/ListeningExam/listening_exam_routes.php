<?php
declare(strict_types=1);

function dispatch_listening_exam_routes(string $method, string $path): void
{
    $db = listening_db();

    if ($method === 'GET' && $path === '/api/health') {
        api_health_check();
    }

    if ($method === 'GET' && $path === '/api/exams') {
        api_list_exams($db);
    }

    if ($method === 'GET' && preg_match('#^/api/exams/([^/]+)$#', $path, $matches) === 1) {
        api_get_exam($db, $matches[1]);
    }

    if ($method === 'GET' && $path === '/api/exam-status') {
        $userId = trim(request_query_string('userId', 'demo-user')) ?: 'demo-user';
        $mode = listening_mode(request_query_string('mode', 'exam'));
        api_get_exam_statuses($db, $userId, $mode);
    }

    if (preg_match('#^/api/exams/([^/]+)/(progress|result|submit)$#', $path, $matches) !== 1) {
        send_error('Not found', 404);
    }

    $examId = $matches[1];
    $resource = $matches[2];
    $userId = trim(request_query_string('userId', 'demo-user')) ?: 'demo-user';
    $mode = listening_mode(request_query_string('mode', 'exam'));
    $body = request_json_body();

    if ($resource === 'progress') {
        if ($method === 'GET') {
            api_get_exam_progress($db, $examId, $userId, $mode);
        }
        if ($method === 'PUT') {
            api_save_exam_progress($db, $examId, $userId, $mode, $body);
        }
        if ($method === 'DELETE') {
            api_delete_exam_progress($db, $examId, $userId, $mode);
        }
    }

    if ($resource === 'result') {
        if ($method === 'GET') {
            api_get_exam_result($db, $examId, $userId, $mode);
        }
        if ($method === 'PUT') {
            api_save_exam_result($db, $examId, $userId, $mode, $body);
        }
    }

    if ($resource === 'submit' && $method === 'POST') {
        api_submit_exam($db, $examId, $userId, $mode, $body);
    }

    send_error('Not found', 404);
}
