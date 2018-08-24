<?php
/**
 * Created by PhpStorm.
 * User: Khadeev Fanis
 * Date: 27/01/2017
 * Time: 17:42
 */

namespace Wecan\Project\CData;


use Bitrix\Main\Loader;
use Wecan\Project\QueryTo1C;

class Bonus extends CData
{
    const CURRENCY = 'RUB';

    public function __construct()
    {
        Loader::includeModule('sale');
    }

    public function clearUsersBalance(array $arUsers)
    {
        $userAccount = new \CSaleUserAccount;
        $arUsersId = array_column($arUsers, 'ID');
        $ob = $userAccount->GetList(
            [],
            [
                '!USER_ID' => $arUsersId,
                '!CURRENT_BUDGET' => 0
            ],
            false,
            false,
            [
                'ID'
            ]
        );

        while ($res = $ob->Fetch()) {
            $userAccount->Update($res['ID'], ['CURRENT_BUDGET' => 0]);
        }
    }

    public static function getBonusByXmlID(string $xmlId, int $userId, bool $returnCode = false): int
    {
        $bonuses = QueryTo1C::getBonuses($xmlId); // query to 1C

        $code = self::updateUserBalance($bonuses, $userId);

        if ($returnCode)
            return $code;
        else
            return $bonuses;
    }

    public static function updateUserBalance(float $bonuses, int $userId)
    {
        if (!Loader::includeModule('sale'))
            return false;

        $userAccount = new \CSaleUserAccount;
        $accountByUser = $userAccount->GetByUserID($userId, self::CURRENCY);
        $accountCode = false;

        if (!$accountByUser && !$bonuses)
            return false;

        if (!$accountByUser) {
            $accountCode = $userAccount->Add(
                [
                    'USER_ID'        => $userId,
                    'CURRENT_BUDGET' => $bonuses,
                    'CURRENCY'       => self::CURRENCY,
                ]
            );
        } elseif ($budget = $accountByUser['CURRENT_BUDGET']) {
            if ($accountByUser['ID']/* && $budget != $bonuses*/) {
                $accountCode = $userAccount->Update($accountByUser['ID'], ['CURRENT_BUDGET' => $bonuses]);
            }
        }

        return $accountCode;
    }

    public function updateBonuses(array $data)
    {
        $bonuses = $data['data'];
        $full = (isset($data['settings']['full']))? $data['settings']['full'] : false;

        if (empty($bonuses)) {
            $this->response['message'] = "BONUSES is empty";
            return;
        }

        $userXml = \ArrayHelper::getColumn($bonuses, 'id');
        $bonuses = \ArrayHelper::index($bonuses, 'id');
        $updatedUsers = [];

        $users = \Bitrix\Main\UserTable::getList([
            'filter' => [
                'XML_ID' => $userXml,
                'ACTIVE' => 'Y'
            ],
            'select' => [
                'ID',
                'XML_ID'
            ]
        ])->fetchAll();

        foreach ($users as $user) {
            $res = self::updateUserBalance($bonuses[$user['XML_ID']]['bonus'], $user['ID']);
            if ($res)
                $updatedUsers[] = $user['XML_ID'];
        }

        if ($full)
            $this->clearUsersBalance($users);

        $this->response['message'][] = 'Updated users: ' . implode(',', $updatedUsers);
    }
}