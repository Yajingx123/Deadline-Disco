<?php
namespace ZEGO;

class ZegoServerAssistant
{
    private static function makeNonce()
    {
        return rand();
    }

    private static function makeRandomIv($number = 16)
    {
        $str = '0123456789abcdefghijklmnopqrstuvwxyz';
        $result = [];
        $strLen = strlen($str);
        for ($i = 0; $i < $number; $i++) {
            $result[] = $str[rand(0, $strLen - 1)];
        }
        return implode('', $result);
    }

    public static function generateToken04($appId, $userId, $secret, $effectiveTimeInSeconds, $payload)
    {
        $assistantToken = new ZegoAssistantToken();
        $assistantToken->code = ZegoErrorCodes::success;

        if ($appId == 0) {
            $assistantToken->code = ZegoErrorCodes::appIDInvalid;
            $assistantToken->message = 'appID invalid';
            return $assistantToken;
        }

        if ($userId == '') {
            $assistantToken->code = ZegoErrorCodes::userIDInvalid;
            $assistantToken->message = 'userID invalid';
            return $assistantToken;
        }

        if (strlen($secret) != 32) {
            $assistantToken->code = ZegoErrorCodes::secretInvalid;
            $assistantToken->message = 'secret must be a 32 byte string';
            return $assistantToken;
        }

        if ($effectiveTimeInSeconds <= 0) {
            $assistantToken->code = ZegoErrorCodes::effectiveTimeInSecondsInvalid;
            $assistantToken->message = 'effectiveTimeInSeconds invalid';
            return $assistantToken;
        }

        $tokenInfo = [
            'app_id' => $appId,
            'user_id' => $userId,
            'nonce' => self::makeNonce(),
            'ctime' => time(),
            'expire' => time() + $effectiveTimeInSeconds,
            'payload' => $payload,
        ];

        $plaintext = json_encode($tokenInfo, JSON_BIGINT_AS_STRING);
        $cipher = 'aes-256-gcm';
        $iv = self::makeRandomIv(12);
        $encrypted = openssl_encrypt($plaintext, $cipher, $secret, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            $assistantToken->code = ZegoErrorCodes::secretInvalid;
            $assistantToken->message = 'Encryption failed';
            return $assistantToken;
        }

        $encrypted .= $tag;
        $binary = pack('J', $tokenInfo['expire']);
        $binary .= pack('n', strlen($iv)) . $iv;
        $binary .= pack('n', strlen($encrypted)) . $encrypted;
        $binary .= pack('C', 1);

        $assistantToken->token = '04' . base64_encode($binary);
        return $assistantToken;
    }
}
