<?php
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 27/10/2017
 * Time: 14:22
 */

namespace Wecan\Project;


use Bitrix\Main\Config\Option;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\UserTable;
use Bitrix\Sale\BasketItem;

class Order
{
    static $orderPropBegin = 'ORDER_PROP_';

    public static function setTmpPropsValues(HttpRequest $request)
    {
        $arValues = $request->getPostList()->toArray();
        if ($arValues) {
            foreach ($arValues as $key=>$value) {
                if ($value === '') continue;
                if (strpos($key, self::$orderPropBegin) !== false) {
                    $_SESSION[$key] = $value;
                }
            }
        }
    }

    public static function getTmpPropsValues(array $values): array
    {
        foreach ($values as $kay=>&$value) {
            $orderKey = self::$orderPropBegin . $kay;
            if (isset($_SESSION[$orderKey])) {
                $value['VALUE'] = $_SESSION[$orderKey];
                unset($_SESSION[$orderKey]);
            }
        }

        return $values;
    }

    public static function getChargeBonuses(array $data): float
    {
        global $USER;
        $res = 0;

        if (!empty($data['products']) || isset($data['bonusesForSuspend'])) {
            $user = UserTable::getById($USER->GetID())->fetchRaw();

            if ($user && ($xmlId = $user['XML_ID'])) {
                foreach ($data['products'] as &$product) {
                    $product = (float)str_replace(' ', '', $product);
                }

                $res = QueryTo1C::getChargeBonuses([
                    'Client' => [
                        'id' => $xmlId,
                        'BonusesSum' => (float)$data['bonusesForSuspend'],
                        'PreOrder' => Order::isPreOrder($data['timeOfDelivery'])? 1 : 0
                    ],
                    'Products' => $data['products']
                ]);
            }
        }

        return $res;
    }

    public static function isPreOrder(string $date): bool
    {
        date_default_timezone_set('Asia/Yekaterinburg');
        $arTime = explode(" ", $date);
        $now = time();

        if (count($arTime) == 3) {
            $time = str_replace(" ", "", $date);
            $date = date('Y-m-d');
            $d = new \DateTime($date . ' ' . $time . ":00");
            $orderTime = $d->getTimestamp();
        } elseif (count($arTime) == 5) {
            $m = \Wecan\Project\Common::numberOfMonth($arTime[1]);
            $year = date('Y');
            if ($arTime[0] == '01' && $m == '01') $year++; // если первое января, то год следующий
            $d = new \DateTime($year . "-" . $m . "-" . $arTime[0] . " " . $arTime[2] . ":" . $arTime[4] . ":00");
            $orderTime = $d->getTimestamp();
        } else {
            return true;
        }

        $threeHour = 60 * 60 * 3;

        return (($orderTime - $now) > $threeHour);
    }

    public static function getAvailablePriceForBonusPay(array $arItem): float
    {
        $arId = [];
        $arAvailableItem = [];
        $maxPriceForSpend = 0;
        /**
         * @var $item BasketItem
         */
        foreach ($arItem as $item) {
            if (is_object($item))
                $arId[] = $item->getProductId();
            else
                $arId[] = $item['PRODUCT_ID'];
        }

        unset($item);

        if (!empty($arId)) {
            $itemsOb = \CIBlockElement::GetList(
                [],
                [
                    'ID' => $arId,
                    'IBLOCK_ID' => PRODUCTS_IBLOCK_ID,
                    'PROPERTY_' . CAN_PAY_FROM_BONUS_PROP_CODE => "true"
                ],
                false,
                false,
                ['IBLOCK_ID', 'PROPERTY_' . CAN_PAY_FROM_BONUS_PROP_CODE, 'ID']
            );
            while($item = $itemsOb->Fetch()) {
                $arAvailableItem[] = $item['ID'];
            }
        }

        if ($arAvailableItem) {
            $percent = Option::get('wecan.project', 'MAX_BONUSES_PERCENT')/100;
            $price = 0;

            foreach ($arItem as $item) {
                if (is_object($item)) {
                    if (!in_array($item->getProductId(), $arAvailableItem)) continue;
                    $price += $item->getFinalPrice();
                } else {
                    if (!in_array($item['PRODUCT_ID'], $arAvailableItem)) continue;
                    $price += $item['SUM_NUM'];
                }
            }

            $maxPriceForSpend = round($percent * $price, 0, PHP_ROUND_HALF_DOWN);
        }

        return $maxPriceForSpend;
    }
}