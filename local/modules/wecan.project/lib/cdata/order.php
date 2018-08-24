<?php
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 18/10/2017
 * Time: 12:10
 */

namespace Wecan\Project\CData;


use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Loader;
use Bitrix\Sale\BasketItem;

class Order
{
    private static $accordance = [
        'НомерЗаказа' => 'ID',
        'Данные' => [
            'кмк_КонтактноеЛицо' => FIO_ORDER_PROP,
            'кмк_КонтактныйНомерТелефона' => PHONE_ORDER_PROP,
            '_АдресДоставки' => ADDRESS_ORDER_PROP,
            '_Домофон' => ENTRYPHONE_ORDER_PROP_ID,
            '_Этаж' => FLOOR_ORDER_PROP,
            '_ДатаЗаказа' => TIME_OF_DELIVERY_ORDER_PROP,
            '_ОплаченоГурманчиками' => 'getPayedBonuses',
            '_Ингридиенты' => 'getIngredients',
        ]
    ];

    public static function prepareDataFor1C(int $orderId): array
    {
        Loader::includeModule('sale');
        $result = [];
        $order = \Bitrix\Sale\Order::load($orderId);
        if (!$order)
            return $result;
        $result = self::$accordance;

        foreach ($result as $key=>&$field) {
            if (!$field) continue;
            if (is_array($field)) {
                foreach ($field as $k=>&$f) {
                    if (!$f) continue;
                    $f = self::getFieldValue($f, $order);
                }
            } else {
                $field = self::getFieldValue($field, $order);
            }
        }

        return $result;
    }

    private static function getFieldValue($propId, \Bitrix\Sale\Order $order)
    {
        if (is_callable([self::class, $propId]))
            $val = self::$propId($order);
        else {
            $propsCollection = $order->getPropertyCollection();
            if (is_int($propId)) {
                $val = $propsCollection->getItemByOrderPropertyId($propId)->getValue();
            } else
                $val = $order->getField($propId);

        }

        return self::changeValue($val, $propId);
    }

    private static function changeValue($val, $propId)
    {
        if (is_a($val, 'Bitrix\Main\Type\DateTime'))
            $val = $val->toString();

        if ($propId == ENTRYPHONE_ORDER_PROP_ID) {
            $val = ($val == 'Y')? "Есть домофон" : 'Нет домофона';
        }

        return $val;
    }

    private static function getIngredients(\Bitrix\Sale\Order $order)
    {
        /**
         * @var BasketItem $item
         */
        $basket = $order->getBasket();
        $arIngr = [];
        foreach ($basket->getIterator() as $item) {
            $propCollection = $item->getPropertyCollection();
            $propsValues = $propCollection->getPropertyValues();
            if ($ingr = $propsValues[SELECTED_INGR_PROP_CODE])
                $arIngr[$item->getProductId()] = $ingr['VALUE'];
        }

        if ($arIngr)
            $arIngr = self::ingredientsToXmlId($arIngr);

        return $arIngr;
    }

    private static function ingredientsToXmlId(array $arIngr): array
    {
        $arIds = [];
        $res = [];
        foreach ($arIngr as $key=>$ingr) {
            $val = array_keys(json_decode($ingr, true));
            $val[] = $key;
            $arIds = $val + $arIds;
        }

        $arXml = self::getXmlId($arIds);

        foreach ($arIngr as $key=>$item) {
            $arItem = json_decode($item, true);
            $val = [];
            foreach ($arItem as $k=>$ing) {
                $val[$arXml[$k]] = $ing;
            }
            $res[$arXml[$key]] = $val;
        }

        return $res;
    }

    private static function getXmlId(array $arIds): array
    {
        Loader::includeModule('iblock');
        $res = [];
        $arItems = ElementTable::getList([
            'filter' => [
                'ID' => $arIds
            ],
            'select' => ['XML_ID', 'ID']
        ])->fetchAll();

        foreach ($arItems as $item) {
            $res[$item['ID']] = $item['XML_ID']?: 'siteId:' . $item['ID'];
        }

        return $res;
    }

    private static function getPayedBonuses(\Bitrix\Sale\Order $order): float
    {
        $paymentCollection = $order->loadPaymentCollection();
        $innerSystem = $paymentCollection->getInnerPayment();
        if ($innerSystem->isPaid())
            return $innerSystem->getSum();
        else
            return 0.0;
    }
}