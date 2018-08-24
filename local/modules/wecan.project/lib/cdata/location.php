<?php
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 25.01.17
 * Time: 16:18
 */

namespace Wecan\Project\CData;

use Bitrix\Highloadblock\DataManager;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

class Location extends CData
{
    /**
     * @var DataManager
     */
    private $hb = null;

    public function __construct()
    {
        if (!Loader::includeModule('highloadblock')) {
            $this->response['error'][] = 'Module HIGHLOADBLOCK is not loaded';
            return false;
        }

        if (!Loader::includeModule('iblock')) {
            $this->response['error'][] = 'Module IBLOCK is not loaded';
            return false;
        }

        $this->initHb(HIGHLOAD_ID_LOCATIONS);
        if (!$this->hb) {
            $this->response['error'][] = 'Error in init coupons for slot';
            return false;
        }

    }

    private function add(array $fields)
    {
        $hb = $this->hb;
        $id = $hb::add($fields);
        if (!$id)
            $this->response['error'][] = 'Error in adding location with id ' . $fields['UF_XML_ID'] . ': ' . $id->getErrorMessages();

        return $id;
    }

    private function initHb(int $hbId)
    {
        $hb = HighloadBlockTable::getById($hbId)->fetch();
        if (!$hb) {
            $this->response['error'][] = 'Error in init HighloadBlock for Locations';
        }

        $hbEntity = HighloadBlockTable::compileEntity($hb);
        $this->hb = $hbEntity->getDataClass();
    }

    private function deleteAll()
    {
        $hb = $this->hb;
        $ar = $hb::getList(['select' => ['ID']])->fetchAll();
        foreach ($ar as $a) {
            $hb::delete($a['ID']);
        }

        return;
    }

    private function deleteExisting(array $existData, bool $full)
    {
        if (!$full)
            return;

        $arID = \ArrayHelper::getColumn($existData, 'ID');

        if (empty($arID))
            return;

        $hb = $this->hb;
        $ar = $hb::getList([
            'filter' => [
                '!ID' => $arID
            ],
            'select' => [
                'ID'
            ]
        ])->fetchAll();

        foreach ($ar as $a) {
            $hb::delete($a['ID']);
        }
    }

    private function update(int $id, array $fields)
    {
        $hb = $this->hb;
        $resId = $hb::update($id, $fields);
        if (!$resId->isSuccess()) {
            $this->response['error'][] = 'Error in update location with siteId ' . $id . ': ' . $resId->getErrorMessages();
            return false;
        }

        return $id;
    }

    private function updateAll(array $data, array $existData)
    {
        $existData = \ArrayHelper::index($existData, 'UF_XML_ID');

        foreach ($data as $datum) // city
        {
            if (!isset($existData[$datum['id']]))
                $cityId = $this->add(
                    [
                        'UF_XML_ID' => $datum['id'],
                        'UF_NAME' => $datum['name']
                    ]
                );
            else {
                $cityId = $this->update(intval($existData[$datum['id']]['ID']), [
                    'UF_NAME' => $datum['name']
                ]);
            }

            if ($cityId && isset($datum['districts']) && !empty($datum['districts'])) {
                foreach ($datum['districts'] as $district) {
                    $district['name'] = $district['name']?: 'Без названия';

                    if (!isset($existData[$district['id']]))
                        $districtId = $this->add(
                            [
                                'UF_XML_ID' => $district['id'],
                                'UF_NAME' => $district['name'],
                                'UF_CITY' => $cityId,
                                'UF_MIN_COST' => $district['delivery_min_cost']
                            ]
                        )->getId();
                    else {
                        $districtId = $this->update(intval($existData[$district['id']]['ID']), [
                            'UF_NAME' => $district['name'],
                            'UF_CITY' => $cityId,
                            'UF_MIN_COST' => $district['delivery_min_cost']
                        ]);
                    }

                    if ($districtId && isset($district['streets']) && !empty($district['streets'])) {
                        foreach ($district['streets'] as $street) {
                            /*if ($street['name'] == '1 Дом Инвалидов ул.')
                                dump($districtId); die();*/

                            if (!isset($existData[$street['id']]['ID'])) {
                                $this->add(
                                    [
                                        'UF_XML_ID' => $street['id'],
                                        'UF_NAME' => $street['name'],
                                        'UF_CITY' => $cityId,
                                        'UF_DISTRICT' => $districtId
                                    ]
                                );
                            } else $this->update(intval($existData[$street['id']]['ID']), [
                                'UF_NAME' => $street['name'],
                                'UF_CITY' => $cityId,
                                'UF_DISTRICT' => $districtId
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * 1.Если пусто, то удалим все местоположения
     * 2. Удалим местоположения, которые отсутствуют на сайте
     * 3. Добавим данных, которые отсутствуют на сайте
     * 4. Обновим данные, которые есть на сайте
     *
     * @param array $arData
     */
    public function updateData(array $arData)
    {
        $data = ($arData['data'])?: [];
        $full = (isset($arData['settings']) && $arData['settings']['full'])? $arData['settings']['full'] : false;

        if (empty($data) && $full) {
            $this->deleteAll();
            return;
        }

        $data = $this->validateData($data);
        $hb = $this->hb;
        $existData = $hb::getList([
            'filter' => [
                'UF_XML_ID' => \ArrayHelper::getColumnRecursive($data, 'id'),
            ],
            'select' => [
                'ID',
                'UF_XML_ID'
            ]
        ])->fetchAll();
        
        $this->deleteExisting($existData, $full);
        $this->updateAll($data, $existData);
    }

    private function validateData(array $data):array
    {
        $data = array_filter($data, function ($ar) {
            if (!isset($ar['id']) || !$ar['id'] || !isset($ar['name']) || !$ar['name']) {
                $this->response['error'][] = 'No valid data for city: id <' . $ar['id'] . '> name <' . $ar['name'] . '>';
                return false;
            } else {
                return true;
            }
        });
        foreach ($data as &$datum) {
            if (isset($datum['districts']) && !empty($datum['districts'])) {
                $datum['districts'] = array_filter($datum['districts'], function ($ar) {
                    if (
                        !isset($ar['id']) || !$ar['id'] || !isset($ar['name']) ||
                        !isset($ar['delivery_min_cost'])
                    ) {
                        $this->response['error'][] = 'No valid data for district: id <' . $ar['id'] . '> name <' .
                            $ar['name'] . '>';
                        return false;
                    } else {
                        if (!$ar['name'])
                            $ar['name'] = "Не заполнено";

                        return true;
                    }
                });

                foreach ($datum['districts'] as &$district) {
                    if (isset($district['streets']) && $district['streets'])
                        $district['streets'] = array_filter($district['streets'], function ($ar) {
                            if (!isset($ar['id']) || !$ar['id'] || !isset($ar['name']) || !$ar['name']) {
                                $this->response['error'][] = 'No valid data for street: id <' . $ar['id'] . '> name <' .
                                    $ar['name'] . '>';
                                return false;
                            } else
                                return true;
                        });
                }
            }
        }

        return $data;
    }

    public function getList($params)
    {
        return $this->hb::getList($params);
    }
}