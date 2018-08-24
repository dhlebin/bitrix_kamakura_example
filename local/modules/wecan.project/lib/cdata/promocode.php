<?php
/**
 * Created by PhpStorm.
 * User: Khadeev Fanis
 * Date: 12/01/2017
 * Time: 18:21
 */

namespace Wecan\Project\CData;

use Bitrix\Catalog\ProductTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\Date;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Internals\DiscountCouponTable;
use Bitrix\Sale\Internals\DiscountGroupTable;
use Bitrix\Sale\Internals\DiscountTable;

class PromoCode
{
    /**
     * нужно протестировать
     * 
     * @var DataManager
     */
    private $hb = null;
    private $userGroups = [
        5 // sign users
    ];
    public $response = [
        'error' => [],
        'message' => []
    ];
    const TYPE_ONE_USE = 2;
    const TYPE_REPEATED = 4;

    public function __construct()
    {
        if (!Loader::includeModule('sale')) {
            $this->response['error'][] = 'Module SALE is not loaded';
            return false;
        }

        if (!Loader::includeModule('highloadblock')) {
            $this->response['error'][] = 'Module HIGHLOADBLOCK is not loaded';
            return false;
        }

        if (!Loader::includeModule('iblock')) {
            $this->response['error'][] = 'Module IBLOCK is not loaded';
            return false;
        }

        $this->initClassForSlots(HIGHLOAD_COUPONS_FOR_SLOT_ID);
        if (!$this->hb) {
            $this->response['error'][] = 'Error in init coupons for slot';
            return false;
        }
    }

    /**
     * @param array $arData
     */
    private function actualizeCoupons(array $arData, bool $full)
    {
        $arCode = \ArrayHelper::getColumn($arData, 'code'); // from 1C

        if (empty($arCode)) {
            $this->response['error'][] = 'No valid Data in PromoCodes';
            return;
        }

        $arCoupons = \ArrayHelper::index($this->getCoupons([], []), 'COUPON');
        $couponForDel = [];
        $arProductXmlId = [];

        foreach ($arData as $data) {
            if (!$data['product_id']) {
                $this->response['error'] = 'No linked product with coupon ' . $data['code'];
                if ($arCoupons[$data['code']] && $arCoupons[$data['code']]['ID'])
                    $couponForDel[] = $arCoupons[$data['code']]['ID'];

                continue;
            }

            $arProductXmlId[] = $data['product_id'];
        }

        $arProductId = $this->getProdIdByXml($arProductXmlId);

        if (!empty($arProductId)) {
            foreach ($arData as $data) {
                $productId = $arProductId[$data['product_id']];
                if ($arCoupons[$data['code']]) {
                    if ($productId)
                        $this->updateCoupon($arCoupons[$data['code']], $data, $productId);
                    else
                        $couponForDel[] = $arCoupons[$data['code']]['ID'];
                } elseif ($data['code'] && $productId)
                    $this->addCoupon($data, $productId);
            }
        }

        // if full exchange need delete coupon on site, if coupon is absent in 1C
        if ($full)
            foreach ($arCoupons as $code=>$coupon) {
                if (!in_array($code, $arCode)) {
                    $couponForDel[] = $coupon['ID'];
                }
            }

        if (!empty($couponForDel))
            $this->deleteCoupons($couponForDel);
    }

    /**
     * add Coupon, Rule, couponForSlot
     * @param array $coupon
     * @param int $productId
     * @return \Bitrix\Main\Entity\AddResult|bool|int
     */
    private function addCoupon(array $coupon, int $productId)
    {
        $dId = 0;

        $ruleId = $this->addRule($coupon, $productId);

        if ($ruleId) {
            try {
                $fields = [
                    'ACTIVE_FROM' => ($coupon['date_active_from']) ? new Date($coupon['date_active_from'], 'd.m.Y') : null,
                    'ACTIVE_TO' => ($coupon['date_active_to']) ? new Date($coupon['date_active_to'], 'd.m.Y') : null,
                    'TYPE' => ($coupon['one_use'] == 1) ? self::TYPE_ONE_USE : self::TYPE_REPEATED,
                    'COUPON' => $coupon['code'],
                    'DISCOUNT_ID' => $ruleId,
                    'ACTIVE' => 'Y',
                ];

                //dump($fields); die();
                $dAdd = DiscountCouponTable::add($fields);

                $dId = $dAdd->getId();

                if (!$dId)
                    $this->response['error'][] = "Error in adding coupon " . $coupon['code'] . ': ' . implode("; ", $dAdd->getErrorMessages());
                else {
                    DiscountCouponTable::update($dId, $fields);
                }
            } catch (\Exception $e) {
                $this->response['error'][] = "Error in adding coupon " . $coupon['code'] . ': ' . $e->getMessage();
            }
        }

        if (!$dId) {
            if ($ruleId)
                DiscountTable::delete($ruleId);

            return false;
        }

        if ($coupon['for_slot_mashine'] == 1)
            $this->addCouponForSlot($dId);

        $this->response['message'][] = 'Coupon ' . $coupon['code'] . ' is added';

        return $dId;
    }

    private function addCouponForSlot(int $couponId)
    {
        $id = $this->getCouponForSlot($couponId);

        if (!$id) {
            $couponForSlot = $this->hb;
            $couponForSlot::add(['UF_PROMO_CODE_ID' => $couponId]);
        }
    }

    private function addRule(array $arData, int $productId): int
    {

        $ruleId = (int)\CSaleDiscount::Add(array_merge(
            [
                'LID' => 's1',
                'NAME' => $arData['code'],
                'CURRENCY' => 'RUB',
                'ACTIVE' => 'Y',
                'LAST_DISCOUNT' => 'N'
            ],
            $this->getParamForRule($productId)
        ));

        if ($ruleId <= 0)
        {
            global $APPLICATION;
            if ($ex = $APPLICATION->GetException())
                $error = 'Error in adding rule: ' . $ex->GetString();
            else
                $error = 'Error in adding rule: ' . $arData['code'];

            $this->response['error'][] = $error;
        }

        return $ruleId?: 0;
    }

    private function createGift($giftOb, array $arProduct): array
    {
        $arGift = [];
        $res = [];
        if (is_object($giftOb)) {
            while ($gift = $giftOb->Fetch()) {
                $arGift[$gift['PROPERTY_' . GIFTS_PARENT_PROPERTY_CODE . '_VALUE']] = $gift['ID'];
            }
        }

        foreach ($arProduct as $product) {
            if (!$arGift[$product['ID']]) {
                $el = new \CIBlockElement();
                $giftId = $el->Add([
                    'IBLOCK_ID' => GIFTS_IBLOCK_ID,
                    'NAME' => $product['NAME'] . ' в подарок',
                    'PROPERTY_VALUES' => [
                        GIFTS_PARENT_PROPERTY_CODE => $product['ID']
                    ]
                ]);

                if ($giftId) {
                    ProductTable::add([
                        'ID' => $giftId
                    ]);

                    $parentPrice = \Bitrix\Catalog\PriceTable::getList([
                        'filter' => ['PRODUCT_ID' => $product['ID']],
                        'select' => [
                            'CATALOG_GROUP_ID',
                            'PRICE',
                            'CURRENCY',
                            'PRICE_SCALE'

                        ]
                    ])->fetchRaw();
                    if ($parentPrice) {
                        $parentPrice['PRODUCT_ID'] = $giftId;
                        \Bitrix\Catalog\PriceTable::add($parentPrice);
                    }
                }
            } else {
                $giftId = $arGift[$product['ID']];
            }

            if ($giftId)
                $res[$product['XML_ID']] = $giftId;
        }

        return $res;
    }

    private function delCouponForSlot(int $codeId)
    {
        $couponeForSlotId = $this->getCouponForSlot($codeId);
        if ($couponeForSlotId) {
            $couponeForSlot = $this->hb;
            $couponeForSlot::delete($couponeForSlotId);
        }
    }

    private function deleteCoupons(array $arId)
    {
        $arCoupons = $this->getCoupons($arId, [
            'ID',
            'DISCOUNT_ID',
            'COUPON'
        ]);

        $rulesId = \ArrayHelper::getColumn($arCoupons, 'DISCOUNT_ID');
        $arRules = $this->getRules(['ID'], (!empty($rulesId))? ['ID' => $rulesId] : []);
        $couponForSlot = $this->hb;
        $arSlotCoupons = $couponForSlot::getList(['select' => ['ID'], 'filter' => ['UF_PROMO_CODE_ID' => $arId]])->fetchAll();
        foreach ($arCoupons as $coupon) {
            DiscountCouponsManager::delete($coupon['COUPON']);
        }

        foreach ($arRules as $rule) {
            DiscountTable::delete($rule['ID']);
        }

        if (!empty($arRules))
            $this->deleteGroupsForRule(\ArrayHelper::getColumn($arRules, 'ID'));

        foreach ($arSlotCoupons as $slotCoupon) {
            $couponForSlot::delete($slotCoupon['ID']);
        }
    }

    private function deleteEmptyData()
    {
        $couponForSlot = $this->hb;
        $arSlots = $couponForSlot::getList([
            'filter' => [
                '!UF_PROMO_CODE_ID' =>
                    \ArrayHelper::getColumn(DiscountCouponTable::getList(['select' => ['ID']])->fetchAll(), 'ID')
            ],
            'select' => ['ID']
        ])->fetchAll();

        foreach ($arSlots as $slot)
            $couponForSlot::delete($slot['ID']);

        $arRules = DiscountTable::getList([
            'filter' => [
                '!ID' =>
                    \ArrayHelper::getColumn(DiscountCouponTable::getList(['select' => ['DISCOUNT_ID']])->fetchAll(), 'DISCOUNT_ID')
            ],
            'select' => ['ID']
        ])->fetchAll();

        foreach ($arRules as $rule) {
            DiscountTable::delete($rule['ID']);
        }

        $this->deleteGroupsForRule($arRules);
    }

    private function deleteGroupsForRule(array $arRules)
    {
        $arRulesId = \ArrayHelper::getColumn($arRules, 'ID');
        if (empty($arRulesId))
            return;

        $arGroups = DiscountGroupTable::getList([
            'filter' => [
                'DISCOUNT_ID' => $arRulesId
            ],
            'select' => [
                'ID'
            ]
        ])->fetchAll();

        if (!empty($arGroups))
            foreach ($arGroups as $group)
                DiscountGroupTable::delete($group);
    }

    private function getCouponForSlot(int $id)
    {
        $couponForSlot = $this->hb;

        $exCoupon = $couponForSlot::getList([
            'filter' => [
                'UF_PROMO_CODE_ID' => $id
            ]
        ])->fetchAll();

        if (count($exCoupon) > 1) {
            for ($i = 1; $i <= count($exCoupon); $i++) {
                $couponForSlot::delete($exCoupon[$i]['ID']);
            }
        }

        $id = (isset($exCoupon[0]) && $exCoupon[0]['ID'])? $exCoupon[0]['ID'] : 0;

        return $id;
    }

    private function getParamForRule(int $prodId): array
    {
        return [
            "CONDITIONS" => [
                "CLASS_ID" => "CondGroup",
                "DATA" => [
                    "All" => "AND",
                    "True" => "True"
                ],
                "CHILDREN" => []
            ],
            "ACTIONS" => [
                "CLASS_ID" => "CondGroup",
                "DATA" => [
                    "All" => "AND"
                ],
                "CHILDREN" =>[
                    0 => [
                        "CLASS_ID" => "GiftCondGroup",
                        "DATA" => [
                            "All" => "AND"
                        ],
                        "CHILDREN" => [
                            0 => [
                                "CLASS_ID" => "GifterCondIBElement",
                                "DATA" =>[
                                    "Type" => "one",
                                    "Value" => [
                                        0 => $prodId
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "USE_COUPONS" => "Y",
            'USER_GROUPS' => $this->userGroups
        ];
    }

    private function getCoupons($arId, array $arSelect = []): array
    {
        $filter['ACTIVE'] = 'Y';
        if ($arId)
            $filter['ID'] = $arId;

        $params['filter'] = $filter;
        if (!empty($arSelect))
            $params['select'] = $arSelect;
        $res = DiscountCouponTable::getList($params)->fetchAll();

        return $res;
    }

    private function getProdIdByXml(array $arXml): array
    {
        $res = [];
        if (empty($arXml))
            return $res;

        $arProd = ElementTable::getList([
            'filter' => [
                'XML_ID' => $arXml,
                'IBLOCK_ID' => PRODUCTS_IBLOCK_ID
            ],
            'select' => [
                'ID',
                'NAME',
                'XML_ID'
            ]
        ])->fetchAll();

        if (empty($arProd))
            return $res;

        $giftOb = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => GIFTS_IBLOCK_ID,
                'PROPERTY_' . GIFTS_PARENT_PROPERTY_CODE . '_VALUE' => \ArrayHelper::getColumnRecursive($arProd, 'ID')
            ],
            false,
            false,
            [
                'PROPERTY_' . GIFTS_PARENT_PROPERTY_CODE,
                'IBLOCK_ID',
                'ID',
                'CATALOG_GROUP_' . CATALOG_PRICE_ID
            ]
        );

        return $this->createGift($giftOb, $arProd);
    }

    private function getRules(array $arSelect = [], array $arFilter = []): array
    {
        $res = DiscountTable::getList([
            'select' => $arSelect,
            'filter' => $arFilter
        ])->fetchAll();

        return $res;
    }

    private function initClassForSlots(int $hbId)
    {
        $hb = HighloadBlockTable::getById(HIGHLOAD_COUPONS_FOR_SLOT_ID)->fetch();
        if (!$hb) {
            $this->response['error'][] = 'Error in init Block for MashineSlot';
        }

        $hbEntity = HighloadBlockTable::compileEntity($hb);
        $this->hb = $hbEntity->getDataClass();
    }

    /**
     * Need update Coupon, update Rule, update Highblock for SlotMashine
     * @param array $coupon
     * @param array $data
     * @return bool
     */
    private function updateCoupon(array $coupon, array $data, int $prodId): bool
    {
        try {
            DiscountCouponTable::update(
                $coupon['ID'], [
                    'ACTIVE_FROM' => ($data['date_active_from'])? new Date($data['date_active_from'], 'd.m.Y') : null,
                    'ACTIVE_TO' => ($data['date_active_to'])? new Date($data['date_active_to'], 'd.m.Y') : null,
                    'TYPE' => ($data['one_use'] == 1)? self::TYPE_ONE_USE : self::TYPE_REPEATED
                ]
            );

            if (!$coupon['DISCOUNT_ID']) {
                $this->response['error'][] = 'No linked rules for  PromoCode ' . $data['code'];
                return false;
            } elseif(!$this->updateRule($coupon['DISCOUNT_ID'], $data, $prodId))
                return false;

            if ($data['for_slot_mashine'] == 1)
                $this->addCouponForSlot($coupon['ID']);
            else
                $this->delCouponForSlot($coupon['ID']);

            $this->response['message'][] = 'Coupon ' . $data['code'] . ' is updated';

            return true;
        } catch (\Exception $exception) {
            $this->response['error'][] = 'Error in update promoCode ' . $data['code'] . ': ' . $exception->getMessage();
            return false;
        }

    }

    /**
     * 1. Удалить промокоды, которые не пришли из 1С
     *  - находим купоны правил, которых нет в 1С и удаляем
     *  - удалить хайлоад записи (PromocodesForAutomat)
     * 2. Находим купоны, которые есть на сайте и обновляем
     *  - купоны
     *  - связанные правила
     *  - связанные highload - блок
     * 3. Добавление новых купонов, правил, хайлоад блоков
     *  - найти правило, соответсвующее параметрам купона
     *      - если есть, привязать к нему, иначе новое правило
     * 4. Удаление правил не связанных с купонами
     * @param array $arData
     */
    public function updateData(array $arData)
    {
        $data = $arData['data'];
        $full = (isset($arData['settings']['full']))? $arData['settings']['full'] : false;

        if (empty($data) && $full) {
            $this->response['message'][] = 'No PromoCodes in data, deleting all coupons in system';
            $this->deleteCoupons([]);
            return;
        }

        # p. 2
        $this->actualizeCoupons($data, $full);
        # p. 3
        $this->deleteEmptyData();
    }

    private function updateRule(int $ruleId, array $data, int $prodId): bool
    {
        if (!$data['product_id'])
            return false;

        try {
            DiscountTable::update(
                $ruleId,
                $this->getParamForRule($prodId)
            );

            return true;

        } catch (\Exception $e) {
            $this->response['error'][] = 'Error in updateRule: ' . $e->getMessage();
            return false;
        }
    }
}