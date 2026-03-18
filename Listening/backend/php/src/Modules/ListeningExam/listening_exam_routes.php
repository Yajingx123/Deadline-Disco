<?php
declare(strict_types=1);

function dispatch_listening_exam_routes(string $method, string $path): void
{
    $db = listening_db();

    // ========== 原有基础接口路由 ==========
    if ($method === 'GET' && $path === '/api/users') {
        api_list_users($db);
        return; // 执行后返回，避免后续逻辑干扰
    }

    if ($method === 'GET' && $path === '/api/health') {
        api_health_check();
        return;
    }

    if ($method === 'GET' && $path === '/api/exams') {
        api_list_exams($db);
        return;
    }

    if ($method === 'GET' && preg_match('#^/api/exams/([^/]+)$#', $path, $matches) === 1) {
        api_get_exam($db, $matches[1]);
        return;
    }

    if ($method === 'GET' && $path === '/api/exam-status') {
        $userId = trim(request_query_string('userId', 'demo-user')) ?: 'demo-user';
        $mode = listening_mode(request_query_string('mode', 'exam'));
        api_get_exam_statuses($db, $userId, $mode);
        return;
    }

    // ========== 新增 Lis2（音频）接口路由 ==========
    // 1. 获取音频列表
    if ($method === 'GET' && $path === '/api/audio/list') {
        getAudioList($db);
        return;
    }

    // 2. 获取音频详情（带ID参数）
    if ($method === 'GET' && preg_match('#^/api/audio/(\d+)$#', $path, $matches) === 1) {
        $_GET['id'] = $matches[1]; // 给函数传参
        getAudioDetailById($db);
        return;
    }

    // 3. 获取我的收藏列表
    if ($method === 'GET' && $path === '/api/audio/collection') {
        getMyCollection($db);
        return;
    }

    // 4. 获取所有音频+收藏状态
    if ($method === 'GET' && $path === '/api/audio/all-with-collect') {
        getAllAudioWithSimpleCollectStatus($db);
        return;
    }

    // 5. 添加音频收藏（POST）
    if ($method === 'GET' && $path === '/api/audio/collection/add') {
        addUserAudioCollection($db);
        return;
    }

    // 6. 取消音频收藏（POST/DELETE，兼容两种方式）
    if (($method === 'GET' || $method === 'DELETE') && $path === '/api/audio/collection/cancel') {
        cancelUserAudioCollection($db);
        return;
    }

    // 7. 保存音频进度（POST/PUT）
    if (($method === 'POST' || $method === 'PUT') && $path === '/api/audio/progress/save') {
        saveAudioProgress($db);
        return;
    }

    // 8. 获取音频进度
    if ($method === 'GET' && $path === '/api/audio/progress') {
        getAudioProgress($db);
        return;
    }

    // ========== 考试相关动态路由（带正则匹配） ==========
    if (preg_match('#^/api/exams/([^/]+)/(progress|result|submit)$#', $path, $matches) !== 1) {
        send_error('Not found', 404);
        return;
    }

    // 解析动态参数
    $examId = $matches[1];
    $resource = $matches[2];
    $userId = trim(request_query_string('userId', 'demo-user')) ?: 'demo-user';
    $mode = listening_mode(request_query_string('mode', 'exam'));
    $body = request_json_body();

    // 进度相关
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
        return;
    }

    // 结果相关
    if ($resource === 'result') {
        if ($method === 'GET') {
            api_get_exam_result($db, $examId, $userId, $mode);
        }
        if ($method === 'PUT') {
            api_save_exam_result($db, $examId, $userId, $mode, $body);
        }
        return;
    }

    // 提交考试
    if ($resource === 'submit' && $method === 'POST') {
        api_submit_exam($db, $examId, $userId, $mode, $body);
        return;
    }

    // 所有路由都匹配失败时返回404
    send_error('Not found', 404);
}