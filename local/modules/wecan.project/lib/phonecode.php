<?php
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 29/05/2017
 * Time: 17:14
 */

namespace Wecan\Project;


use Bitrix\Main\Context;
use Wecan\Project\SmsUslugi\Transport;

class PhoneCode
{
    public static function checkPhone(int $code, int $phone): bool
    {
        $sessionCode = $_SESSION[self::getCodeSessionKey($phone)];
        if ($sessionCode) {
            $time = self::getCodeRemainedTime($sessionCode);
            if ($time > 0) {
                $checkCode = self::getCode($sessionCode);
                $res = ($code === $checkCode);
                if ($res) {
                    $_SESSION[self::getCheckedPhoneKey($phone)] = 'y';
                    return $res;
                }
            }
        }

        return false;
    }

    private static function getCheckedPhoneKey (int $phone): string
    {
        return 'phone_' . $phone . '_checked';
    }

    private static function getCode(string $code): int
    {
        $arSession = explode(":", $code);
        if (count($arSession) == 2)
            return $arSession[1]?: "0";
    }

    private static function getCodeSessionKey(int $phone): string
    {
        return 'phone_' . $phone;
    }

    private static function getCodeRemainedTime(string $code): int
    {
        $arSession = explode(":", $code);
        if (count($arSession) == 2) {
            $diff = $arSession[0] - time();
            if ($diff > 0)
                return $diff;
        }

        return 0;
    }

    /**
     * session format: timestamp:code
     *
     * @param int $phone
     * @return int
     */
    public static function makeCheckCode(int $phone): int
    {
        $sessionKey = self::getCodeSessionKey($phone);
        $session = $_SESSION[$sessionKey];
        $pause = \COption::GetOptionString('wecan.project', 'PAUSE_FOR_RESEND_PHONE_CODE');
        $liveTime = \COption::GetOptionString('wecan.project', 'LIVE_TIME_IF_PHONE_CODE');

        $sendTime = $_SESSION['send_time']; // check pause for resend code
        if ($sendTime) {
            $age = time() - $sendTime;
            if ($age && $age < $pause) {
                return $pause - $age;
            }
        }

        $code = false;
        if ($session) {
            $diff = self::getCodeRemainedTime($session);
            if ($diff > 0 && $diff > $pause) {
                $arSession = explode(":", $session);
                if (count($arSession) == 2)
                    $code = $arSession[1];
            }
        }

        if (!$code) $code = rand(1000, 9999);
        $res = self::sendMessage($phone, $code);
        if ($res) {
            $_SESSION['send_time'] = time();
            $time = time() + $liveTime;
            $_SESSION[$sessionKey] = $time . ':' . $code;
            return $liveTime < $pause? $liveTime : $pause;
        } else {
            return 0;
        }
    }

    /**
     * @param int $phone
     * @return bool
     */
    public static function phoneIsChecked(int $phone): bool
    {
        $sessionKey = self::getCheckedPhoneKey($phone);
        if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] == 'y')
            return true;
        else
            return false;
    }

    private static function sendMessage($phone, $code): bool
    {
        $text = 'Ваш проверочный код: ' . $code;

        if (SMS_SERVICE_DEV_VERSION) {
            mail(SMS_SERVICE_DEV_MAIL, 'kamakura', $text);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/dev_log', 'code ' . $code . ' is sent: ' . date(DATE_RFC2822) . "\n");
            return true;
        }

        $api = new Transport();
        $send = $api->send(['text' => $text], ['7' . $phone]);

        if (is_array($send) && isset($send['code'])) {
            if ($send['code'] == 1)
                return true;
            else {
                Common::writeLogs(
                    'Отправка проверочного кода для номера ' . $phone,
                    ['error' => ['Код ошибки: '  . $send['code'] . ', Текст: ' . $send['descr']?: '']]
                );
            }
        }

        return false;
    }
}