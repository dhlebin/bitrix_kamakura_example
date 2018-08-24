<?php

namespace Wecan\Project\CData;

use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\SectionTable;
use Wecan\Project\Common;
use Wecan\Project\QueryTo1C;

class CatchMoment
{
    /**
     * 1. получим все товары "Лови момент" из 1С
     * 2. выделим их xml_id
     * 3. найдем товары на сайте по xml_id
     * 4. обновим товары
     * 5. найдем и уберем товары, у которых признак "Лови момент", но которые нет в 1С
     *
     */
    public static function actualizeCatchMoment()
    {
        $cmProductsData = QueryTo1C::getCatchMomentProducts();
        $productsXmlIds = \ArrayHelper::getColumn($cmProductsData, 'id');
        $productsIdToXmlMapping = \ArrayHelper::map(Common::getArProducts([
            'ID',
            'XML_ID'
        ], [
            'XML_ID' => $productsXmlIds
        ]), 'XML_ID', 'ID');
        self::updateProducts($productsIdToXmlMapping, $cmProductsData);
    }

    private static function updateProducts(array $productsIdToXmlMapping, array $cmProductsData)
    {
        $currentCmProductsIds = $newIds = $toAdd = $toDelete = $toUpdate = [];

        $sectionXmlIds = array_unique(
            \Helper::array_flatten(
                array_column($cmProductsData, 'sections')
            )
        );
        $arSections = \ArrayHelper::map(SectionTable::getList([
            'select' => [
                'ID',
                'XML_ID'
            ],
            'filter' => [
                '=XML_ID' => $sectionXmlIds
            ]
        ])->fetchAll(), 'XML_ID', 'ID');

        $obCurrentCatchMoment = \CIBlockElement::GetList(
            [],
            [
                '=IBLOCK_ID' => CATCH_MOMENT_IBLOCK_ID,
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_CATCH_PRODUCT'
            ]
        );

        while ($arCurrentCatchMoment = $obCurrentCatchMoment->Fetch()) {
            $currentCmProductsIds[$arCurrentCatchMoment['ID']] = $arCurrentCatchMoment['PROPERTY_CATCH_PRODUCT_VALUE'];
        }

        foreach ($cmProductsData as &$cmProductsDatum) {
            if (in_array($productsIdToXmlMapping[$cmProductsDatum['id']], $currentCmProductsIds)) {
                $cmProductsDatum['PRODUCT_ID'] = $productsIdToXmlMapping[$cmProductsDatum['id']];
                $toUpdate[array_search($productsIdToXmlMapping[$cmProductsDatum['id']], $currentCmProductsIds)] = $cmProductsDatum;
            } else {
                $toAdd[$productsIdToXmlMapping[$cmProductsDatum['id']]] = $cmProductsDatum;
            }
            $newIds[] = $productsIdToXmlMapping[$cmProductsDatum['id']];
        }
        unset($cmProductsDatum);

        $toDelete = array_diff($currentCmProductsIds, $newIds);

        foreach ($toAdd as $productId => $cmProduct) {
            $arFields = [
                'NAME' => $cmProduct['id'],
                'IBLOCK_ID' => CATCH_MOMENT_IBLOCK_ID,
                'DATE_ACTIVE_FROM' => $cmProduct['date_active_from'] ?? null,
                'DATE_ACTIVE_TO' => $cmProduct['date_active_to'] ?? null,
                'PROPERTY_VALUES' => [
                    'CATCH_PRODUCT' => $productId,
                    'CATCH_PRICE' => $cmProduct['price'],
                    'CATCH_QUANT' => $cmProduct['quantity'],
                    'CATCH_SECTION' => $cmProduct['sections']
                ]
            ];
            $obElement = new \CIBlockElement();
            $obElement->Add($arFields);
        }

        foreach ($toUpdate as $id => $cmProduct) {
            $arFields = [
                'NAME' => $cmProduct['id'],
                'IBLOCK_ID' => CATCH_MOMENT_IBLOCK_ID,
                'DATE_ACTIVE_FROM' => $cmProduct['date_active_from'] ?? null,
                'DATE_ACTIVE_TO' => $cmProduct['date_active_to'] ?? null,
                'PROPERTY_VALUES' => [
                    'CATCH_PRODUCT' => $cmProduct['PRODUCT_ID'],
                    'CATCH_PRICE' => $cmProduct['price'],
                    'CATCH_QUANT' => $cmProduct['quantity'],
                    'CATCH_SECTION' => $cmProduct['sections']
                ]
            ];
            $obElement = new \CIBlockElement();
            $obElement->Update($id, $arFields);
        }

        foreach ($toDelete as $id) {
            $obElement = new \CIBlockElement();
            $obElement->Update($id, ['ACTIVE' => 'N']);
        }
    }
}