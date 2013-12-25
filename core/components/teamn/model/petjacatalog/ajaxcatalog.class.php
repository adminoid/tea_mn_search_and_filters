<?php
/**
 * User: Petja
 * Date: 20.11.13
 * Time: 10:27
 */

require_once(dirname(__FILE__) . '/petjacatalog.class.php');

class ajaxCatalog extends petjaCatalog{

    public $qVar;

    public function __construct(modX &$modx,array $scriptProperties = array()) {

        parent::__construct($modx);

        $this->sProp['main'] = array('exType');
        $this->sProp['additional'] = array('vendor','exMedical','exPsycho','exUnit','exLeaf','exSort','exPlace');
        $this->pageId = $_REQUEST['pageId'];
        $this->allowedReq = array('sortby','page','price','search');

        unset($this->req['action']);

    }

    public function getSeparatedData(){
        /**
         * 1) sortWr / [[+petja.sortby]]
         * 2) totalWr / [[+petja.count]]
         * 3) pagWr / [[+page.nav]]
         * 4) prodsWr / [[+petja.products]]
         * 5) mainWr / [[+petja.main]]
         * 6) additWr / [[+petja.additional]]
         */
        $html['sortWr'] = $this->modx->getPlaceholder('petja.sortby');
        $html['totalWr'] = $this->modx->getPlaceholder('petja.count');
        $html['pagWr'] = $this->modx->getPlaceholder('page.nav');
        $html['mainWr'] = $this->modx->getPlaceholder('petja.main');
        $html['additWr'] = $this->modx->getPlaceholder('petja.additional');
        $html['priceWr'] = $this->modx->getPlaceholder('petja.price');
        $html['searchWord'] = $this->modx->getPlaceholder('petja.search_word');

        if($dump = $this->modx->getPlaceholder('petja.dump')){
            $html['dumpWr'] = $dump;
        }

        $html['prodsWr'] = $this->modx->getPlaceholder('petja.products');
        $this->modx->getParser()->processElementTags('', $html['prodsWr'], true, true, '[[', ']]', array(), 1);

        //$html['dumpWr'] = $this->modx->getPlaceholder('petja.dump');

        return $html;

    }

} 