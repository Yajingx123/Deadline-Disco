<?php
declare(strict_types=1);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../src/Config/database.php';
require_once __DIR__ . '/../src/Http/json_response.php';
require_once __DIR__ . '/../src/Http/request_data.php';
require_once __DIR__ . '/../src/Support/json_columns.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/mode_helper.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/question_repository.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/answer_grader.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/exam_catalog_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/status_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/progress_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/result_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/submit_exam_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/user_api.php';
require_once __DIR__ . '/../src/Modules/ListeningExam/listening_exam_routes.php';

try {
    // Keep the public entry thin: load dependencies once, then hand off to the module router.
    dispatch_listening_exam_routes(request_method(), request_path());
} catch (PDOException $e) {
    send_error('Server error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    send_error('Server error: ' . $e->getMessage(), 500);
}
