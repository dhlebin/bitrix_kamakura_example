<?php namespace Wecan\Project;
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 24/08/2017
 * Time: 17:20
 */

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

class LocationRepository
{
    public function __construct() {
        Loader::includeModule('highloadblock');
        $hblock = HighloadBlockTable::getRowById(HIGHLOAD_ID_LOCATIONS);
        HighloadBlockTable::compileEntity($hblock);
    }

    public static function getMinCost(array $filter): int
    {
        $minCost = 0;
        if (empty($filter))
            return $minCost;

        $locationHL = \Helper::getHLClass(HIGHLOAD_ID_LOCATIONS);
        $location = $locationHL::getList([
            'filter' => $filter,
            'select' => [
                'UF_MIN_COST',
                'UF_DISTRICT',
                'UF_CITY'
            ]
        ])->fetchRaw();

        if ($location) {
            if ($location['UF_DISTRICT']) {
                $minCost = self::getMinCost(['ID' => $location['UF_DISTRICT']]);
            } elseif ($location['UF_CITY']) {
                $minCost = $location['UF_MIN_COST'];
            }
        }


        return $minCost?: 0;
    }

    public function getDeliveryPriceByStreet(string $street, int $orderPrice)
    {
        $deliveryPrice = false;
        $arLocation = \LocationsTable::getList([
            'filter' => ['UF_NAME' => $street, '!UF_DISTRICT' => false],
            'select' => ['UF_DISTRICT']
        ])->fetchRaw();

        if ($arLocation) {
            Loader::includeModule('iblock');
            $zoneOb = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => DELIVERY_ZONE_IBLOCK_ID,
                    'PROPERTY_INCLUDE' => $arLocation['UF_DISTRICT']
                ],
                false,
                false,
                [
                    'PROPERTY_DELIVERY_PRICE',
                    'ID',
                    'IBLOCK_ID',
                    'PROPERTY_FREE_DELIVERY'
                ]
            );

            if ($zone = $zoneOb->Fetch()) {
                if ($zone['PROPERTY_FREE_DELIVERY_VALUE'] && $orderPrice >= $zone['PROPERTY_FREE_DELIVERY_VALUE'])
                    $deliveryPrice = 0;
                else
                    $deliveryPrice = $zone['PROPERTY_DELIVERY_PRICE_VALUE'];
            }
        };

        return $deliveryPrice;
    }
}