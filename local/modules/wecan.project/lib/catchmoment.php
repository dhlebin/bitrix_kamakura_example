<?php namespace Wecan\Project;

class CatchMoment
{
    protected $arSelect = [
        "ID",
        "IBLOCK_ID",
        "NAME",
        "DATE_ACTIVE_TO",
        "ACTIVE_DATE",
        "PROPERTY_CATCH_QUANT",
        "PROPERTY_CATCH_SECTION",
        "PROPERTY_CATCH_PRODUCT",
        "PROPERTY_CATCH_PRICE"
    ];

    protected $arFilter = [
        "IBLOCK_ID"             => CATCH_MOMENT_IBLOCK_ID,
        "ACTIVE"                => "Y",
        ">PROPERTY_CATCH_QUANT" => '0',
        ">PROPERTY_CATCH_PRICE" => '0',
        "ACTIVE_DATE"           => "Y",
    ];

    public function getForSection(int $sectionId)
    {
        $catchMoment = null;

        $sectionIds = [$sectionId];
        $obChildSections = \CIBlockSection::GetList([], [
            'IBLOCK_ID'  => PRODUCTS_IBLOCK_ID,
            'ACTIVE'     => 'Y',
            'SECTION_ID' => $sectionId
        ], false, [
            'ID',
            'NAME'
        ]);

        while($arSection = $obChildSections->Fetch()) {
            $sectionIds[] = $arSection['ID'];
        }

        $arSelect = array_merge($this->arSelect, []);
        $arFilter = array_merge($this->arFilter, [
            [
                "LOGIC" => "OR",
                [
                    "PROPERTY_CATCH_SECTION" => $sectionIds,
                    'INCLUDE_SUBSECTIONS' => 'Y'
                ],
                [
                    "PROPERTY_CATCH_SECTION" => false
                ]
            ],
        ]);

        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $arFilter, false, Array("nTopCount" => 1), $arSelect);
        if ($arrRes = $res->Fetch()) {
            $catchMoment = $this->formatResult($arrRes);
        }

        return $catchMoment;
    }

    public function getByProduct(int $productId)
    {
        $catchMoment = null;

        $arSelect = array_merge($this->arSelect, []);
        $arFilter = array_merge($this->arFilter, [
            'PROPERTY_CATCH_PRODUCT' => $productId
        ]);
        $res = \CIBlockElement::GetList(['SORT' => 'ASC'], $arFilter, false, Array("nTopCount" => 1), $arSelect);

        if ($arrRes = $res->Fetch()) {
            $catchMoment = $this->formatResult($arrRes);
        }

        return $catchMoment;
    }

    protected function formatResult($result)
    {
        $now = new \DateTime;
        $stop = new \DateTime($result['DATE_ACTIVE_TO']);
        $diff = $now->diff($stop);
        $catchMoment['TIME']['HOUR'] = $diff->h;
        if ($diff->days > 0) {
            $catchMoment['TIME']['HOUR'] += $diff->days * 24;
        }
        $catchMoment['TIME']['MIN'] = $diff->i;
        $catchMoment['TIME']['SEC'] = $diff->s;

        $catchMoment['QUANTITY'] = $result['PROPERTY_CATCH_QUANT_VALUE'];
        $catchMoment['PRICE'] = $result['PROPERTY_CATCH_PRICE_VALUE'];
        $catchMoment['SECTION'] = $result['PROPERTY_CATCH_SECTION_VALUE'];
        $catchMoment['PRODUCT_ID'] = $result['PROPERTY_CATCH_PRODUCT_VALUE'];

        return $catchMoment;
    }
}