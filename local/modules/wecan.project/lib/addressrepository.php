<?php namespace Wecan\Project;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

class AddressRepository
{
    static public $propsCode = [
        'room' => 'UF_ROOM_NUMBER',
        'floor' => 'UF_ETAJ',
        'porch' => 'UF_POD',
        'house' => 'UF_HOUSE_NUMBER',
        'city' => 'UF_CITY',
        'street' => 'UF_STREET',
        'userId' => 'UF_USER_ID'
    ];

    public function __construct()
    {
        Loader::includeModule('highloadblock');
        $hblock = HighloadBlockTable::getRowById(ADDRESSES_HBLOCK_ID);
        HighloadBlockTable::compileEntity($hblock);
    }

    public function getListByUser(int $userId): array
    {
        return \UserAddressTable::getList(
            ['filter' => ['UF_USER_ID' => $userId]],
            ['order' => ['ID' => 'asc']]
        )->fetchAll();
    }

    public function getList($filter, $select = ['*'])
    {
        return array_map(function ($val) {
            return $val + [
                    'FULL_NAME' => self::makeFullAddress($val)
                ];
        }, \UserAddressTable::getList([
            'filter' => $filter,
            'select' => $select
        ])->fetchAll());
    }

    public function add($fields)
    {
        return \UserAddressTable::add($fields);
    }

    public function getById(int $id): array
    {
        return \UserAddressTable::getById($id)->fetchRaw();
    }

    public function delete($id)
    {
        return \UserAddressTable::delete($id);
    }

    public static function makeFullAddress(array $arVal):string
    {
        $arVal['UF_HOUSE_NUMBER'] = 'д. ' . $arVal['UF_HOUSE_NUMBER'];
        $arVal['UF_ROOM_NUMBER'] = 'кв/оф. ' . $arVal['UF_ROOM_NUMBER'];
        $arVal[self::$propsCode['floor']] = 'эт. ' . $arVal[self::$propsCode['floor']];
        $arVal[self::$propsCode['porch']] = 'под. ' . $arVal[self::$propsCode['porch']];

        return implode(', ', \ArrayHelper::only(array_filter($arVal), [
            'UF_CITY', 'UF_STREET',
            'UF_HOUSE_NUMBER', self::$propsCode['porch'], self::$propsCode['floor'], 'UF_ROOM_NUMBER'
        ]));
    }

    public static function getMinCost(array $filter): int
    {
        $addrHl = \Helper::getHLClass(ADDRESSES_HBLOCK_ID);
        $loc = $addrHl::getList(['filter' => $filter])->fetchRaw();
        if ($street = $loc[self::$propsCode['street']])
            $minCost = LocationRepository::getMinCost(['UF_NAME' => $street]);
        else
            $minCost = 0;

        return $minCost;
    }
}