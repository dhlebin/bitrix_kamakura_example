<?php
/**
 * Created by PhpStorm.
 * User: fans
 * Date: 10.01.17
 * Time: 11:36
 */

namespace Wecan\Project;


use Bitrix\Main\Loader;

class QueryTo1C
{
    private static $bonusQuery = '?action=getpromo&id=';
    private static $usersByPhoneQuery = '?action=getusers_byphone&phone=';
    private static $createCounterAgentQuery = '?action=newuser&id=';
    private static $createNewDialBackQuery = '?action=newDialBack&id=';
    private static $createNewReservationQuery = '?action=newReservation&id=';
    private static $getCodeWordQuery = "?action=getCodeWordById&id=";
    private static $getChargeBonuses = "?action=getNumberOfBonuses";

    private static function makeRequest(string $query, string $method = 'GET', $body = ""): string
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => \COption::GetOptionString('wecan.project', '1C_URL')
        ]);

        $params = [
            'auth' => [
                \COption::GetOptionString('wecan.project', '1C_HTTP_USER'),
                \COption::GetOptionString('wecan.project', '1C_HTTP_PASS')
            ],
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'connect_timeout' => 5
        ];

        if ($method == 'POST')
            $params = array_merge($params, ['body' => $body]);

        try {
            $res = $client->request(
                $method,
                $query,
                $params
            );

            if ($res->getStatusCode() != 200)
                Common::writeLogs(
                    'Query to 1C',
                    ['error' => ['Плохой статус ' . \COption::GetOptionString('wecan.project', '1C_URL') . $query . ": " . $res->getStatusCode()]]
                );
            else
                return $res->getBody();
        } catch (\Exception $e) {
            Common::writeLogs(
                'Query to 1C',
                ['error' => ['Не удалось произвести запрос по адресу ' . \COption::GetOptionString('wecan.project', '1C_URL') . $query . ": " . $e->getMessage()]]
            );
        }

        return '';
    }

    private static function parseResponse($res, $query): array
    {
        try {
            $arr = \GuzzleHttp\json_decode($res, true);

            return $arr;
        } catch (\Exception $e) {
            Common::writeLogs(
                'Query to 1C',
                [
                    'error' => ['Ошибка при разборе ответа по запросу ' . $query]
                ]
            );

            return [];
        }
    }

    public static function getBonuses(string $userXmlId): float
    {
        try {
            $res = self::makeRequest(self::$bonusQuery . $userXmlId);
            $arRes = self::parseResponse($res, self::$bonusQuery . $userXmlId);

            if ($arRes && $arRes['data'] && $arRes['data'][0]) {
                $arBonus = $arRes['data'][0];
                if (!isset($arBonus['id']) && !isset($arBonus['bonus']))
                    Common::writeLogs(
                        'Запрос бонусов для пользователя',
                        [
                            'error' => ['Нет необходимых полей в ответе для пользователя ' . $userXmlId]
                        ]
                    );
                else {
                    $userId = $arBonus['id'];
                    if ($userId != $userXmlId)
                        Common::writeLogs(
                            'Запрос бонусов для пользователя',
                            [
                                'error' => ['Не совпадают ид пользователя в запросе (' . $userXmlId . ') и ответе (' . $userId . ')']
                            ]
                        );
                    else {
                        return round($arBonus['bonus'], 0, PHP_ROUND_HALF_DOWN);
                    }
                }
            } else {
                Common::writeLogs(
                    'Запрос бонусов для пользователя',
                    [
                        'error' => ['Нет данных для польхователя ' . $userXmlId]
                    ]
                );
            }
        } catch (\Exception $e) {
            Common::writeLogs(
                'Запрос бонусов для пользователя',
                [
                    'error' => ['Неудача для пользователя ' . $userXmlId . ':' . $e->getMessage()]
                ]
            );
        }

        return 0;
    }

    /**
     * Check one-use promo code in 1C
     *
     * @param string $promoCode
     * @return bool
     */
    public static function checkPromoCode(string $promoCode): bool
    {
        return true;
    }

    /**
     * Get users from 1C by Phone
     */
/*    public static function getUsersByPhone(string $phone): array
    {
        return [];
    }*/

    /**
     * get "catch moment" product info
     */
    public static function getCatchMomentProducts()
    {
        $json = '[{
          "id": "f578823e-fe4e-11e0-9b4e-e81132290e7c",
          "date_active_from": "01.02.2017",
          "date_active_to": "10.11.2017",
          "price": 2100,
          "quantity": 22,
          "sections": [
              31,
              34
          ]
        }, {
           "id": "ba0359a3-fe4c-11e0-9b4e-e81132290e7c",
          "date_active_from": "30.11.2016",
          "date_active_to": "11.10.2017",
          "price": 2000,
          "quantity": 28,
          "sections": [] 
        }, {
           "id": "f57881e8-fe4e-11e0-9b4e-e81132290e7c",
           "date_active_from": "30.11.2016",
           "date_active_to": "11.10.2017",
           "price": 11111,
           "quantity": 11,
           "sections": [
              31
          ] 
        }]';

        $data = json_decode($json, true);
        if (json_last_error()) {
            Common::writeLogs('Parse CatchMomentData', ['error' => 'Ошибка пр разборе json: ' . json_last_error_msg()]);
            return [];
        }

        $result = [];

        foreach($data as $product) {
            if (!isset($product['id']) || !$product['id'] || !isset($product['price']) || !$product['price']) {
                Common::writeLogs('Parse CatchMomentData', ['error' => 'Нет обязательных полей']);
            } else
                $result[] = $product;
        }

        return $result;

    }

    /**
     *
     * @param string $userXmlId
     * @return string
     */
    public static function getCodeWord(string $userXmlId): string
    {
        /* $result = '{
          "id": 177,
          "code": "wejkasjdf"
        }';*/

        $query = self::$getCodeWordQuery . $userXmlId;
        $response = self::makeRequest($query);
        $arRes = self::parseResponse($response, $query);

        if ($arRes) {
            if (!isset($arRes['id']) || !isset($arRes['code'])) {
                Common::writeLogs('Getting Code Word', ['error' => ['Required data is abent for user ' . $userXmlId]]);
                return "";
            }

            if ($arRes['id'] !== $userXmlId) {
                Common::writeLogs('Getting Code Word', ['error' => ['id in response don\'t equal to ' . $userXmlId]]);
                return "";
            }

            return $arRes['code'];

        } else {
            Common::writeLogs('Getting Code Word', ['error' => ['Empty response for user ' . $userXmlId]]);
            return "";
        }
    }

    /**
     * $result = '[{
     *  "id": 177,
     *   "name": "Василий"
     *   },{
     *   "id": 17,
     *   "name": "Иван"
     *  }]';
     *
     *
     * phone format: 9099099090
     *
     * @param int $phoneNumber
     * @return array
     */
    public static function getFioByPhone(int $phoneNumber, string $key = ''): array
    {
        // $key = $key?: $phoneNumber;
        // if ($_SESSION[$key])
        //    return $_SESSION[$key];

        $response = self::makeRequest(self::$usersByPhoneQuery . $phoneNumber);

        $arRes = self::parseResponse($response, self::$usersByPhoneQuery . $phoneNumber);
        $res = [];

        if ($arRes) {
            if ($arRes['response'] && $arRes['response'] == 'fail') {
                Common::writeLogs('Getting FIO by Phone ' . $phoneNumber, ['error' => [$arRes['error']?: 'fail']]);
                return [];
            }

            $res = array_filter($arRes, function ($ar) {
                if ((isset($ar['id']) && $ar['id']) && (isset($ar['name']) && $ar['name']))
                    return true;
                else {
                    Common::writeLogs('Getting FIO by Phone', ['error' => 'Required fields are absent']);
                    return false;
                }
            });

        }

        return $res;
    }

    /**
     * $arProducts = [
     *      [
     *          'id' => 'productXmlId',
     *          'quantity' => 'quantityInBasket'
     *      ],
     *      [
     *          'id' => 'anotherProductXmlId',
     *          'quantity' => 'quantityInBasket'
     *      ],
     * ]
     *
     * @param string $districtXmlId
     * @param array $arProducts
     * @return string
     */
    public static function getTimeForDelivery(string $districtXmlId, array $arProducts): array
    {
        $stringFor1c = json_encode([
            'district' => $districtXmlId,
            'products' => $arProducts
        ]);
        // getDeliveryTime=$stringFor1c
        $result = [
            'H' => '00',
            'i' => '00'
        ]; // in 24 format

        $counter = 95;
        $period = 15;
        $addMinutes = 60;

        list($result['H'], $result['i']) = explode('-', date('H-i', time() + $addMinutes*60));

        if ($result['i'] > 55) {
            if ($result['H'] == 23)
                $result['H'] = '00';
            else
                $result['H']++;

            $result['i'] = '00';
        }

        $diff = ($result['i']%5 > 0)? (5 - $result['i']%5) : 0;

        $result['i'] = $result['i'] + $diff;
        $arTime = [];
        if (strlen((string)$result['i']) ==1)
            $result['i'] = '0' . $result['i'];

        for ($i = $counter; $i >= 0; $i--) {
            if (count($arTime) == 0)
                $arTime[] = [
                    'H' => $result['H'],
                    'i' => $result['i']
                ];
            else {
                $time = $arTime[count($arTime) - 1];
                $min = $time['i'] + $period;
                if ($min >= 60) {
                    if ($time['H'] == 23)
                        $h = "00";
                    else
                        $h = $time['H'] + 1;
                    $min = $min - 60;
                } else
                    $h = $time['H'];

                if (strlen((string)$h) == 1)
                    $h = '0' . $h;
                if (strlen((string)$min) == 1)
                    $min = '0' . $min;

                $arTime[] = [
                    'H' => $h,
                    'i' => $min
                ];
            }
        }

        return $arTime;
    }

    private static function parseJson(string $data, string $title, string $message)
    {
        $json = json_decode($data, true);
        if (json_last_error()) {
            Common::writeLogs($title, ['error' => $message . ': ' . json_last_error_msg()]);
            return false;
        }

        return $json;
    }

    /**
     * info from dialBack form
     * $arData = [
     *  'id' => 'siteId',
     *  'phone' => '9099099990',
     *  'date' => '23.01.2017 12:30'
     * ]
     *
     * @param array $arData
     * @return string
     */
    public static function sendNewDialBack(array $arData): string
    {
        $json = \GuzzleHttp\json_encode($arData);

        if (!$arData['id'] && !$arData['phone'] && !$arData['date']) {
            Common::writeLogs(
                'Sending new dialBack',
                [
                    'error' => ['неправильный массив: ' . $json]
                ]
            );

            return "";
        }

        $query = self::$createNewDialBackQuery . $json;
        $response = self::makeRequest($query);
        $arRes = self::parseResponse($response, $query);

        if ($arRes) {
            if ($arRes['response'] && $arRes['response'] == 'fail') {
                Common::writeLogs(
                    'Sending new dialBack',
                    [
                        'error' => ['query: ' . ($arRes['error']?: 'fail')]
                    ]
                );

                return "";
            } else {
                if ($id = $arRes['id'])
                    return $id;
                else
                    Common::writeLogs(
                        'Sending new dialBack',
                        [
                            'error' => ['query ' . $query . ': ' . "нет ид в ответе"]
                        ]
                    );
            }
        }

        return "";
    }

    /**
     * info from reservation form
     * $arData = [
     *  'id' => 'siteId',
     *  'date' => '23.01.2017',
     *  'time' => '12:30',
     *  'guest_count' => 4,
     *  'name' => 'Вася',
     *  'phone' => '9094567890'
     * ]
     *
     * @param array $arData
     */
    public static function sendNewReservation(array $arData)
    {
        $json = \GuzzleHttp\json_encode($arData);

        if (!$arData['id'] && !$arData['phone']) {
            Common::writeLogs(
                'Sending new reservation',
                [
                    'error' => ['неправильный массив: ' . $json]
                ]
            );

            return "";
        }

        $query = self::$createNewReservationQuery . $json;
        $response = self::makeRequest($query);
        $arRes = self::parseResponse($response, $query);

        if ($arRes) {
            if ($arRes['response'] && $arRes['response'] == 'fail') {
                Common::writeLogs(
                    'Sending new reservation',
                    [
                        'error' => ['query: ' . ($arRes['error']?: 'fail')]
                    ]
                );

                return "";
            } else {
                if ($id = $arRes['id'])
                    return $id;
                else
                    Common::writeLogs(
                        'Sending new reservation',
                        [
                            'error' => ['query ' . $query . ': ' . "нет ид в ответе"]
                        ]
                    );
            }
        }

        return "";
    }

    public static function sendParseResultLog(array $arLog)
    {
        //sendLog=json_encode($arLog)
    }

    /**
     * $userData = {
     *  "name" => "Name",
     *  "email" => "email@email.com",
     *  "phone" => "9525555678",
     *  "birthday" => "23.05.1998",
     *  "id" => "ID",
     *  "xml_id" => 'ИД'
     * }
     *
     * @param array $userData
     * @return string
     */
    public static function createCounterAgent(array $userData): string
    {
        $userData['birthday'] = $userData['birthday']?: '01.01.1901';

        $query = self::$createCounterAgentQuery . \GuzzleHttp\json_encode($userData);
        $response = self::makeRequest($query);
        $arRes = self::parseResponse($response, $query);
        $xmlId = $userData['xml_id'];

        if ($arRes) {
            if ($arRes['response'] && $arRes['response'] == 'fail') {
                Common::writeLogs(
                    'Creating counter-agent',
                    [
                        'error' => ['query: ' . ($arRes['error']?: 'fail')]
                    ]
                );
            } elseif ($id = $arRes['id']){
                Common::writeLogs(
                    'Creating counter-agent',
                    [
                        'message' => ['query: ' . $query . "\n" . $response]
                    ]
                );
                return $id;
            }

        }

        return $xmlId?: ERROR_XML_ID;
    }

    /**
     * return sum of charge bonuses
     *
     * $data =  {
     *   "Client": {
     *       "id": "5dc64dd9-510c-11e2-8966-7a7919a3cd6c", //ид в 1с
     *       "BonusesSum": 345.5, //сумма бонусов к списанию
     *       "PreOrder": 1 //предзаказ
     *   },
     *   "Products": {
     *       "f5788293-fe4e-11e0-9b4e-e81132290e7c": 1000,
     *       "e57aaf2e-1c13-11e2-976f-005056c00008": 400
     *   }
     *   }
     *
     *   response
     *   {
     *       "BonusSum": 119.75
     *   }
     *
     * @param array $data
     * @return float
     */
    public static function getChargeBonuses(array $data): float
    {
        $res = 0.0;

        $data = \GuzzleHttp\json_encode($data);
        $query = self::$getChargeBonuses;
        $response = self::makeRequest($query, 'POST', $data);
        $arRes = self::parseResponse($response, $query);

        if ($arRes) {
            if ($arRes['response'] && $arRes['response'] == 'fail') {
                Common::writeLogs(
                    'Get Charged Bonuses',
                    [
                        'error' => ['query: ' . ($arRes['error']?: 'fail')]
                    ]
                );
            } elseif (array_key_exists('BonusSum', $arRes)){
                if (!$arRes['BonusSum'])
                    Common::writeLogs(
                        'Get Charged Bonuses',
                        [
                            'message' => ['data: ' . $data . "\nquery: " . $query . "\n" . $response]
                        ]
                    );

                return floatval($arRes['BonusSum']);
            }

        }

        return $res;
    }
}