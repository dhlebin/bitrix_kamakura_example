<?php

namespace Wecan\Project;


use Bitrix\Main\UserTable;
use Wecan\Project\CData\Bonus;

class ParseData
{
    const MODULE_NAME = 'wecan.project';
    const OLD_FILES_DIR_OPTION = 'OLD_FILES_DIR';

    public $response = [
        'error' => [],
        'message' => []
    ];

    public function getData(string $optionName, string $msg, bool $debug = false)
    {
        $file = \COption::GetOptionString(self::MODULE_NAME, $optionName);
        $res = false;

        if (!$file)
            $this->response['error'][] = "Attempt to get $msg file name from settings is fail";
        $file = $_SERVER['DOCUMENT_ROOT'] . $file;
        if (!file_exists($file)) {
            $this->response['message'][] = "$msg FILE is not exist";
        } else {
            $data = file_get_contents($file);
            if (!$data)
                $this->response['message'][] = "Json file $msg is Empty";
            else {
                $data = json_decode($data, true);
                if ($data === NULL)
                    $this->response['error'][] = "$msg json error: " . json_last_error_msg();
                else {
                    if (!isset($data['settings']) || !isset($data['data']))
                        $this->response['error'][] = "No valid data $msg json error: ";
                    else
                        $res = $data;
                }
            }
            if (!$debug)
                $this->removeFile($file);
        }

        return $res;
    }

    /**
     * @todo сделать чтоб название файла записывалось в переменную класса, и в этом методе бралось оттуда
     * @param string $file
     */
    public function removeFile(string $file)
    {
        $oldFileDir = \COption::GetOptionString(self::MODULE_NAME, self::OLD_FILES_DIR_OPTION);
        if ($oldFileDir && is_dir($_SERVER['DOCUMENT_ROOT'] . $oldFileDir)) {
            $arFile = explode('/', $file);
            $fileName = $arFile[(count($arFile) - 1)];
            rename($file, $_SERVER['DOCUMENT_ROOT'] . $oldFileDir . '/' . date('d-m-Y') . $fileName . '.old');
        } else
            $this->response['error'] = 'DIR for old files didn\'t set';
    }
}