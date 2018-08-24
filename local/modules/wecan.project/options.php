<?php
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();
defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'wecan.project');

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);
\Bitrix\Main\Loader::includeModule('iblock');

function ShowParamsHTMLByArray($arParams)
{
    foreach($arParams as $Option)
    {
        __AdmSettingsDrawRow(ADMIN_MODULE_NAME, $Option);
    }
}

/*
 *
 * VALUES
 *
 * */

$obIblocks = \Bitrix\Iblock\IblockTable::getList([
    'filter' =>
        [
            'ACTIVE' => 'Y'
        ],
    'select' =>
        [
            'ID',
            'NAME'
        ]
]);

$arIblocks = [];
while($arIblock = $obIblocks->fetch()) {
    $arIblocks[$arIblock['ID']] = $arIblock['NAME'];
}

/**/

$tabControl = new CAdminTabControl("tabControl", [
    [
        "DIV" => "edit1",
        "TAB" => Loc::getMessage("MAIN_TAB_SET"),
        "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_SET"),
    ]
]);

$arAllOptions = [
    "settings" => [
        ["BONUS_FILE", Loc::getMessage("WECAN_PROJECT_BONUSES_FILE"), "../1C/bonuses.json", ["text"]],
        ["PROMOCODES_FILE", Loc::getMessage("WECAN_PROJECT_PROMOCODES_FILE"), "/../1C/from1C/promocodes.json", ["text"]],
        ["LOCATIONS_FILE", Loc::getMessage("WECAN_PROJECT_LOCATIONS_FILE"), "/../1C/from1C/locations.json", ["text"]],
        ["LOG_DIR", Loc::getMessage("WECAN_PROJECT_LOG_DIR"), "../1C/log", ["text"]],
        ["OLD_FILES_DIR", Loc::getMessage("WECAN_PROJECT_OLD_FILES_DIR"), "../1C/old_files", ["text"]],
        ["1C_URL", Loc::getMessage("WECAN_PROJECT_1C_URL"), "http://94.181.34.68/hs/bitrix", ["text"]],
        ["1C_HTTP_USER", Loc::getMessage("WECAN_PROJECT_1C_HTTP_USER"), 'Admin', ['text']],
        ["1C_HTTP_PASS", Loc::getMessage("WECAN_PROJECT_1C_HTTP_PASS"), 'Admin', ['text']],
        ["LIVE_TIME_IF_PHONE_CODE", Loc::getMessage("WECAN_PROJECT_LIVE_TIME_IF_PHONE_CODE"), "300", ["text"]],
        ["PAUSE_FOR_RESEND_PHONE_CODE", Loc::getMessage("WECAN_PAUSE_FOR_RESEND_PHONE_CODE"), "30", ["text"]],
        ["MAX_BONUSES_PERCENT", Loc::getMessage('WECAN_PROJECT_MAX_BONUSES_PERCENT'), "25", ["text"]]
    ]
];

if($REQUEST_METHOD=="POST" && strlen($Update.$Apply.$RestoreDefaults)>0 && check_bitrix_sessid())
{
    if(strlen($RestoreDefaults)>0)
    {
        COption::RemoveOption("iblock");
    }
    else
    {
        foreach($arAllOptions['settings'] as $arOption)
        {
            $name=$arOption[0];
            $val=$_REQUEST[$name];
            if($arOption[2][0]=="checkbox" && $val!="Y")
                $val="N";

            COption::SetOptionString(ADMIN_MODULE_NAME, $name, $val, $arOption[1]);
        }
    }
    if(strlen($Update)>0 && strlen($_REQUEST["back_url_settings"])>0)
        LocalRedirect($_REQUEST["back_url_settings"]);
    else
        LocalRedirect($APPLICATION->GetCurPage()."?mid=".urlencode($mid)."&lang=".urlencode(LANGUAGE_ID)."&back_url_settings=".urlencode($_REQUEST["back_url_settings"])."&".$tabControl->ActiveTabParam());
}

$tabControl->begin();
?>
<form method="post" action="<?=sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID)?>">
    <? $tabControl->BeginNextTab(); ?>
    <? ShowParamsHTMLByArray($arAllOptions["settings"]); ?>
    <? $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?=GetMessage("MAIN_SAVE")?>" title="<?=GetMessage("MAIN_OPT_SAVE_TITLE")?>" class="adm-btn-save">
    <input type="submit" name="Apply" value="<?=GetMessage("MAIN_OPT_APPLY")?>" title="<?=GetMessage("MAIN_OPT_APPLY_TITLE")?>">
    <?if(strlen($_REQUEST["back_url_settings"])>0):?>
        <input type="button" name="Cancel" value="<?=GetMessage("MAIN_OPT_CANCEL")?>" title="<?=GetMessage("MAIN_OPT_CANCEL_TITLE")?>" onclick="window.location='<?echo htmlspecialcharsbx(CUtil::addslashes($_REQUEST["back_url_settings"]))?>'">
        <input type="hidden" name="back_url_settings" value="<?=htmlspecialcharsbx($_REQUEST["back_url_settings"])?>">
    <?endif?>
    <input type="submit" name="RestoreDefaults" title="<?echo GetMessage("MAIN_HINT_RESTORE_DEFAULTS")?>" OnClick="return confirm('<?echo AddSlashes(GetMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING"))?>')" value="<?echo GetMessage("MAIN_RESTORE_DEFAULTS")?>">
    <?=bitrix_sessid_post();?>
</form>
<script>
    window.onload = function() {
        var logLink = document.getElementById("LOG_FILE_LINK");
        var logLinkValue = document.getElementsByName("LOG_FILE")[0].value;
        if (logLink && logLinkValue)
            logLink.setAttribute('href', logLinkValue);
    }
</script>
<?php
$tabControl->end();