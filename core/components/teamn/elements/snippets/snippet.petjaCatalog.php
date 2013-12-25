<?php
/**
 * User: Petja
 * Date: 09.11.13
 * Time: 2:57
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

/** @var $petjaCatalog petjaCatalog */

$modx->regClientHTMLBlock('<script type="text/javascript">
    $("#catalog").plusMinus();
</script>');

$petjaCatalog = $modx->getService('petjacatalog','petjaCatalog', MODX_CORE_PATH.'components/teamn/model/petjacatalog/', $scriptProperties);

$petjaCatalog->initialize();

/*$config = array(
    'element' => 'msProducts',
);*/

//$petjaCatalog->getPagination($config, 'page.nav', 'proverka');





