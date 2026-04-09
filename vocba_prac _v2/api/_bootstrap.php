<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

if (!vocab_is_authenticated()) {
  vocab_json_response([
    'ok' => false,
    'message' => 'Login required.',
  ], 401);
}

