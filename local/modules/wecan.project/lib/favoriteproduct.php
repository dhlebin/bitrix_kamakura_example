<?php namespace Wecan\Project;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class FavoriteProduct
{
    const USER_FIELD_CODE = 'UF_FAVORITE_PRODUCTS';
    const MAX_FAVORITES_COUNT = 20;

    public static function add(array $product)
    {
        $_SESSION['favorite_products'][$product['ID']] = $product['NAME'];

        if(count($_SESSION['favorite_products']) >= self::MAX_FAVORITES_COUNT) {
            reset($_SESSION['favorite_products']);
            $firstKey = key($_SESSION['favorite_products']);
            unset($_SESSION['favorite_products'][$firstKey]);
        }

        return isset($_SESSION['favorite_products'][$product['ID']]);
    }

    public static function remove(array $product)
    {
        if (isset($_SESSION['favorite_products'][$product['ID']])) {
            unset($_SESSION['favorite_products'][$product['ID']]);
        }

        global $USER, $USER_FIELD_MANAGER;
        if($USER->IsAuthorized()) {
            if ($products = self::getList([], ['ID'])) {
                if ($user = UserTable::getRowById($USER->GetID())) {
                    return $USER_FIELD_MANAGER->Update('USER', $USER->GetID(), [
                        self::USER_FIELD_CODE => array_diff(array_column($products, 'ID'), [$product['ID']])
                    ]);
                }
            }
        }

        return !isset($_SESSION['favorite_products'][$product['ID']]);
    }

    public static function getList($filter = [], $select = [])
    {
        Loader::includeModule('iblock');
        $ids = self::getUserFavoriteProductsIds();
        $arProducts = [];

        $ids = array_unique(array_merge($ids, self::getSessionFavoriteProductsIds()));

        if (!empty($ids)) {
            $obProducts = \CIBlockElement::GetList([], array_merge([
                'IBLOCK_ID' => PRODUCTS_IBLOCK_ID,
                '=ID'       => $ids
            ], $filter), false, false, $select);
            while ($arProduct = $obProducts->GetNext(false, false)) {
                $arProducts[] = $arProduct;
            }
        }

        return $arProducts;
    }

    public static function getUserFavoriteProductsIds()
    {
        global $USER;
        $ids = [];
        if ($USER->IsAuthorized()) {
            $arFilter = ["ID" => $USER->GetID()];
            $arParams["SELECT"] = [self::USER_FIELD_CODE];
            $arRes = \CUser::GetList($by, $desc, $arFilter, $arParams);
            if ($res = $arRes->Fetch()) {
                $ids = $res["UF_FAVORITE_PRODUCTS"] ?: [];
            }
        }

        return $ids;
    }

    public static function getSessionFavoriteProductsIds()
    {
        if (isset($_SESSION['favorite_products']) && !empty($_SESSION['favorite_products'])) {
            return array_keys($_SESSION['favorite_products']);
        }

        return [];
    }

    public static function saveToUser(int $userId)
    {
        global $USER_FIELD_MANAGER;
        if ($products = self::getList([], ['ID'])) {
            if ($user = UserTable::getRowById($userId)) {
                return $USER_FIELD_MANAGER->Update('USER', $userId, [
                    self::USER_FIELD_CODE => array_column($products, 'ID')
                ]);
            }
        }

        return false;
    }
}