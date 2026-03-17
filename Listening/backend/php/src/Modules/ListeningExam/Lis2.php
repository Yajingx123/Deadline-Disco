<?php
/*
 * @Author: yzp 488361078@qq.com
 * @Date: 2026-03-15 16:15:41
 * @LastEditors: yzp 488361078@qq.com
 * @LastEditTime: 2026-03-17 19:45:00
 * @FilePath: \listening\api\materials.php
 * @Description: 音频相关接口函数库（纯函数，无入口逻辑）
 */

// 公共函数：参数校验
function validateIntParam($param, $default = 0) {
    return isset($param) && is_numeric($param) ? intval($param) : $default;
}

// ===================== 音频基础查询 =====================
function getAudioList($pdo) {
    try {
        $sql = "SELECT * FROM audio";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $audioList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'code' => 0,
            'data' => $audioList,
            'msg' => 'success'
        ], JSON_UNESCAPED_UNICODE);
    } catch(PDOException $e) {
        echo json_encode([
            'code' => -1,
            'msg' => '查询音频列表失败：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getAudioDetailById($pdo) {
    $id = validateIntParam($_GET['id']);
    if ($id <= 0) {
        echo json_encode([
            'code' => -1,
            'msg' => '请传入正确的音频id'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "SELECT * FROM audio WHERE audio_id = :audio_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':audio_id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $audio = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$audio) {
            echo json_encode([
                'code' => -1,
                'msg' => '音频不存在'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'code' => 0,
                'msg' => '获取成功',
                'data' => $audio
            ], JSON_UNESCAPED_UNICODE);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'code' => -1,
            'msg' => '查询失败：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ===================== 收藏相关 =====================
function getMyCollection($pdo) {
    $user_id = validateIntParam($_GET['user_id']);
    if ($user_id <= 0) {
        echo json_encode([
            'code' => -1,
            'msg' => '请传入有效 user_id'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "
            SELECT 
                a.*, 
                up.progress_percent, 
                up.current_index, 
                up.status, 
                up.update_time 
            FROM user_audio_progress up 
            INNER JOIN audio a ON up.audio_id = a.audio_id 
            WHERE up.user_id = :user_id 
            ORDER BY up.update_time DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'code' => 0,
            'data' => $list,
            'msg' => 'success'
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        echo json_encode([
            'code' => -1,
            'msg' => '查询失败：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getAllAudioWithSimpleCollectStatus($pdo) {
    $user_id = validateIntParam($_GET['user_id']);
    if ($user_id <= 0) {
        echo json_encode([
            'code' => -1,
            'msg' => '参数错误：user_id必须为有效数字'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "
            SELECT 
                a.*,
                IF(up.user_id IS NOT NULL, 1, 0) AS is_collected
            FROM 
                audio a
            LEFT JOIN 
                user_audio_progress up 
                ON a.audio_id = up.audio_id 
                AND up.user_id = :user_id
            ORDER BY 
                is_collected DESC,
                a.audio_id ASC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $audioList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'code' => 0,
            'msg' => '获取音频列表（含收藏状态）成功',
            'data' => $audioList
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        echo json_encode([
            'code' => -2,
            'msg' => '查询失败：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

function addUserAudioCollection($pdo) {
    $userId = validateIntParam($_REQUEST['user_id']);
    $audioId = validateIntParam($_REQUEST['audio_id']);
    
    if ($userId <= 0 || $audioId <= 0) {
        echo json_encode([
            'code' => -1,
            'msg' => '用户ID和音频ID必须为正整数'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "INSERT INTO user_audio_progress 
                (user_id, audio_id, progress_percent, status) 
                VALUES (?, ?, 0, 'Not Started')
                ON DUPLICATE KEY UPDATE update_time = CURRENT_TIMESTAMP";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $audioId]);
        
        echo json_encode([
            'code' => 0,
            'msg' => '收藏成功',
            'progress_id' => $pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    } catch(PDOException $e) {
        $msg = $e->getCode() == 23000 ? '该音频已收藏' : '收藏失败：'.$e->getMessage();
        echo json_encode([
            'code' => -1,
            'msg' => $msg
        ], JSON_UNESCAPED_UNICODE);
    }
}

function cancelUserAudioCollection($pdo) {
    $userId = validateIntParam($_REQUEST['user_id']);
    $audioId = validateIntParam($_REQUEST['audio_id']);
    
    if ($userId <= 0 || $audioId <= 0) {
        echo json_encode([
            'code' => -2,
            'msg' => '用户ID和音频ID必须为正整数'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "DELETE FROM user_audio_progress 
                WHERE user_id = ? AND audio_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $audioId]);

        $rowCount = $stmt->rowCount();
        if ($rowCount > 0) {
            echo json_encode([
                'code' => 0,
                'msg' => '取消收藏成功'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'code' => -1,
                'msg' => '该音频未收藏，无需取消'
            ], JSON_UNESCAPED_UNICODE);
        }

    } catch (PDOException $e) {
        error_log("取消收藏失败: " . $e->getMessage());
        echo json_encode([
            'code' => -1,
            'msg' => '取消收藏失败：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ===================== 进度相关 =====================
function saveAudioProgress($pdo) {
    $userId = validateIntParam($_REQUEST['user_id']);
    $audioId = validateIntParam($_REQUEST['audio_id']);
    $progressPercent = validateIntParam($_REQUEST['progress_percent']);
    $currentIndex = validateIntParam($_REQUEST['current_index']);
    $progressData = isset($_REQUEST['progress_data']) ? $_REQUEST['progress_data'] : '[]';
    $status = isset($_REQUEST['status']) ? trim($_REQUEST['status']) : 'in_progress';

    if (!is_string($progressData)) {
        $progressData = json_encode($progressData, JSON_UNESCAPED_UNICODE);
    }

    if ($userId <= 0 || $audioId <= 0) {
        echo json_encode([
            'code' => -1,
            'msg' => '用户ID和音频ID不能为空'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $sql = "REPLACE INTO user_audio_progress 
                (user_id, audio_id, progress_percent, current_index, progress_data, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $audioId, $progressPercent, $currentIndex, $progressData, $status]);
        
        echo json_encode([
            'code' => 0,
            'msg' => '进度保存成功',
            'progress_id' => $pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
    } catch(PDOException $e) {
        $msg = $e->getCode() == 23000 ? '数据重复，保存失败' : '进度保存失败：'.$e->getMessage();
        echo json_encode([
            'code' => -1,
            'msg' => $msg
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getAudioProgress($pdo) {
    $userId = validateIntParam($_REQUEST['user_id'], 1);
    $audioId = validateIntParam($_REQUEST['audio_id']);

    try {
        $sql = "SELECT progress_percent, current_index, progress_data, status 
                FROM user_audio_progress 
                WHERE user_id = ? AND audio_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $audioId]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$progress) {
            echo json_encode([
                'code' => 0,
                'msg' => '暂无进度数据',
                'data' => []
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $progress['progress_data'] = json_decode($progress['progress_data'], true) ?: [];
            echo json_encode([
                'code' => 0,
                'msg' => '获取进度成功',
                'data' => $progress
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch(PDOException $e) {
        echo json_encode([
            'code' => -1,
            'msg' => '获取进度失败：'.$e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>