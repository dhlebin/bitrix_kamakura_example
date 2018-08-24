<?php
IncludeModuleLangFile(__FILE__);

class wecan_project  extends CModule
{
    public $MODULE_ID = "wecan.project";
    public $MODULE_NAME;

    public function __construct() {
        $this->MODULE_NAME = "WeCan Project";
    }

    public function installFiles() {
        CopyDirFiles(dirname(__FILE__) . "/admin", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin", true);

        return true;
    }

    public function unInstallFiles() {
        DeleteDirFiles(dirname(__FILE__) . "/admin", $_SERVER["DOCUMENT_ROOT"] . "/bitrix/admin");

        return true;
    }

    public function DoInstall() {
        RegisterModule($this->MODULE_ID);
        $this->InstallFiles();
    }

    public function DoUninstall() {
        UnRegisterModule($this->MODULE_ID);
        $this->UnInstallFiles();
    }
}
