<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$basePath = dirname(dirname(dirname(dirname(__FILE__))));
$actions = array('modal/get/phone','modal/check/phone','catalog/update');

if (!in_array($_REQUEST['action'],$actions)) return;

@session_cache_limiter('public');
define('MODX_REQP',false); //отключаем проверку прав пользователя для нашего коннектора
require_once $basePath.'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CONNECTORS_PATH.'index.php';

/*
 * В этом блоке генерируется переменная HTTP_MODAUTH (различается для разных версий MODX),
 * которая проверяется в дальнейшем при запросе. Проще говоря обходим настройки безопасности MODX,
 * так как у нас публичный коннектор.
 * */
$version = $modx->getVersionData();
if (version_compare($version['full_version'],'2.1.1-pl') >= 0) {
    if ($modx->user->hasSessionContext($modx->context->get('key'))) {
        $_SERVER['HTTP_MODAUTH'] = $_SESSION["modx.{$modx->context->get('key')}.user.token"];
    } else {
        $_SESSION["modx.{$modx->context->get('key')}.user.token"] = 0;
        $_SERVER['HTTP_MODAUTH'] = 0;
    }
} else {
    $_SERVER['HTTP_MODAUTH'] = $modx->site_id;
}
$_REQUEST['HTTP_MODAUTH'] = $_SERVER['HTTP_MODAUTH'];
/*
 * КОНЕЦ генерируется переменная HTTP_MODAUTH
 * */

$moduleCorePath = $modx->getOption('core_path').'components/teamn/';
$processorsPath = $moduleCorePath.'processors/';

header("Cache-Control: no-store, no-cache, must-revalidate");

$modx->request->handleRequest(array(
        'processors_path' => $processorsPath,
        'location' => '',
    ));
