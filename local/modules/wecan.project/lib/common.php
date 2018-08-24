<?php

namespace Wecan\Project;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Main\UserTable;

class Common
{
    const MODULE_NAME = 'wecan.project';
    const LOG_FILE_OPTION = 'LOG_DIR';

    /**
     * @param array $select
     * @param array $filter
     * @return array
     */
    public static function getArProducts(array $select = [], array $filter = []): array
    {
        Loader::includeModule('iblock');
        $result = [];

        $ob = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            $select
        );

        while ($res = $ob->Fetch()) {
            $result[] = $res;
        }

        return $result;
    }

    public static function getArPropValueId(string $propId, array $value): array
    {
        $res = [];

        $ob = \CIBlockPropertyEnum::GetList(
            [],
            [
                'VALUE' => $value,
                'CODE'  => $propId
            ]
        );

        while ($result = $ob->Fetch()) {
            $res[$result['VALUE']] = $result['ID'];
        }

        return $res;
    }

    public static function getBitrixDate($date, string $format = 'DD-MM-YYYY')
    {
        return ($date) ?
            new Date(\CDatabase::FormatDate($date, $format, 'DD.MM.YYYY'), 'd.m.Y') :
            null;
    }

    public static function writeLogs(string $title, array $logs = [])
    {
        $logFile = \COption::GetOptionString(self::MODULE_NAME, self::LOG_FILE_OPTION);
        if (!$logFile || empty($logs)) {
            return;
        }

        $logFile = $_SERVER['DOCUMENT_ROOT'] . $logFile;

        if (file_exists($logFile)) {
            file_put_contents($logFile, "\n\n////////////////\n\n" . date("d-m-Y H-i") . ' ' . $title, FILE_APPEND);
            file_put_contents($logFile, "\nErrors:\n", FILE_APPEND);
            file_put_contents($logFile, implode("\n", $logs['error']), FILE_APPEND);
            file_put_contents($logFile, "\nMessage:\n", FILE_APPEND);
            file_put_contents($logFile, implode("\n", $logs['message']), FILE_APPEND);
        }
    }

    public function getSections(array $select, array $filter)
    {
        return SectionTable::getList([
            'select' => $select,
            'filter' => $filter
        ])->fetchAll();
    }

    public static function getBonus($withTime = false)
    {

        if (!Loader::includeModule('sale')) {
            return false;
        }

        global $USER;
        $uid = $USER->GetId();
        $bonuses = 0;

        if (\CSite::InDir(CART_DIR) && !\Helper::isXhrRequest() && !\Bitrix\Main\Context::getCurrent()->getRequest()->isAjaxRequest()) {
            $xmlId = \Bitrix\Main\UserTable::getList(['filter' => ['ID' => $USER->GetID()], 'select' => ['XML_ID']])->fetchRaw();
            if ($xmlId)
                $bonuses = \Wecan\Project\CData\Bonus::getBonusByXmlID($xmlId['XML_ID'], $USER->GetID());
        } else {
            $userAccount = new \CSaleUserAccount;
            $accountByUser = $userAccount->GetByUserID($uid, \Wecan\Project\cdata\bonus::CURRENCY);
            $bonuses = floor($accountByUser['CURRENT_BUDGET']);

            if ($withTime) {
                return [
                    'bonus' => $bonuses,
                    'time'  => $accountByUser['TIMESTAMP_X']
                ];
            }
        }

        return $bonuses;
    }

    public static function updateCodeWordForUser(\CUser $user)
    {
        $xmlId = UserTable::getRow([
            'filter' =>
                [
                    '=ID' => $user->GetID()
                ],
            'select' =>
                [
                    'ID',
                    'XML_ID'
                ]
        ])['XML_ID'];
        $codeWord = \Wecan\Project\QueryTo1C::getCodeWord($xmlId);
        if (strlen($codeWord) > 0) {
            if ($user->Update($user->GetID(), [
                'UF_CODEWORD' => $codeWord
            ])
            ) {
                \CEvent::Send('CODEWORD_REQUEST', 's1', [
                    'EMAIL'    => $user->GetEmail(),
                    'CODEWORD' => $codeWord
                ]);

                return $codeWord;
            }
        }

        return false;
    }

    public static function getShortName(\CUser $user)
    {
        $uid = $user->GetId();
        $fio = self::getUserFIO($uid);
        $n = '';

        if ($fio) {
            $names = explode(" ", $fio);
            if (count($names) >= 2) {
                $n = mb_substr($names[0], 0, 1) . mb_substr($names[1], 0, 1);
            } else {
                $n = mb_substr($names[0], 0, 1);
            }
        } else {
            $login = $user->GetLogin();
            $n = mb_substr($login, 0, 1);
        }

        return $n;
    }

    public static function getUserFIO(int $userId): string
    {
        $user = \CUser::GetByID($userId)->Fetch();
        if ($user['UF_NAME_LAST_NAME']) {
            return $user['UF_NAME_LAST_NAME'];
        }

        $fio = $user['NAME'] ?: '';
        if ($fio && $user['LAST_NAME']) {
            $fio = $fio . " " . $user['LAST_NAME'];
        } elseif (!$fio && $user['LAST_NAME']) {
            return $user['LAST_NAME'];
        }

        return $fio;
    }

    public static function getShareCartPath($socNetwork, $sharedCartId)
    {
        $cartSharePath = false;
        switch ($socNetwork) {
            case 'vk':
                $shareUrl = 'http://vkontakte.ru/share.php?url=#PAGE_URL#';
                break;
            case 'facebook':
                $shareUrl = 'http://www.facebook.com/share.php?u=#PAGE_URL#';
                break;
            case 'ok':
                $shareUrl = 'https://connect.ok.ru/offer?url=#PAGE_URL#';
                break;
            case 'twitter':
                $shareUrl = 'http://twitter.com/home/?status=#PAGE_URL#';
                break;
            default:
                $shareUrl = '';
                break;
        }

        if (!strlen($shareUrl)) {
            return false;
        }

        $sharedCartClass = \Helper::getHLClass(SHARED_CARTS_HBLOCK_ID);
        if ($sharedCart = $sharedCartClass::getRowById($sharedCartId)) {
            $sharedCartPageUrl = 'http://' . $_SERVER['HTTP_HOST'] . "/carts/?id={$sharedCart['UF_HASH']}";
            $cartSharePath = str_replace('#PAGE_URL#', $sharedCartPageUrl, $shareUrl);
        }

        return $cartSharePath;
    }

    public static function getUserXmlId(\CUser $user): string
    {
        $user = UserTable::getById($user->GetID())->fetch();

        return $user['XML_ID'] ?: "";
    }

    public static function numberOfMonth(string $ruShortMonth): string
    {
        $month = [
            "Янв" => "01",
            "Фев" => "02",
            "Мар" => "03",
            "Апр" => "04",
            "Май" => "05",
            "Июн" => "06",
            "Июл" => "07",
            "Авг" => "08",
            "Сен" => "09",
            "Окт" => "10",
            "Ноя" => "11",
            "Дек" => "12",
        ];

        return $month[$ruShortMonth];
    }

    public static function getPluraliseBonusName(int $count): string
    {
        return \Helper::pluralize($count, ['гурманчик', 'гурманчика', 'гурманчиков']);
    }
}