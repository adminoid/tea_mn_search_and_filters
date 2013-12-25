<?php
/**
 * User: Petja
 * Date: 20.11.13
 * Time: 10:25
 */

if (!$modx->loadClass('ajaxCatalog', MODX_CORE_PATH . 'components/teamn/model/petjacatalog/', false, true)) {return false;}
$cat = new ajaxCatalog($modx);
$cat->initialize();
$html = $cat->getSeparatedData();

return json_encode($html);