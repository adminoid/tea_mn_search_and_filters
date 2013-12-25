<?php
/**
 * User: Petja
 * Date: 09.11.13
 * Time: 3:01
 */

class petjaCatalog {

    public
        $where
        ,$fieldTypes
        ,$allowedReq = array();
    public
        $modx, $pdo
        ,$pageId
        ,$sProp
        ,$req
        ,$qType
        ,$idsChild
        ,$url
        ,$lexicon
        ,$idsFiltered = false;

    /*
    Варианты вывода фильтров:
    1)  Форма отправляется в первый раз - собираются все данные из БД по всем значениям
    2)  Форма отправляется с типом и с какими-то другими данными, тогда надо составить фильтр, взять все значения,
        но напротив каждого - посчитать сколько их при данных параметрах, нулевые выключить

    Если параметр 1, то его чекнуть и выключить

    Мне надо брать - все дочерние id, чтобы брать все значения, id только тех продуктов, что подходят по фильтрам
    При создании элемента, если запрос в первый раз - то брать все дочерние айди и считать их,
    Если запрос идет с данными - то брать все дочерние айди - и брать названия + брать все айди по фильтру и брать счетчик
    */

    public function __construct(modX &$modx,array $scriptProperties = array()){
        $this->modx = $modx;
        $this->pdo = $modx->getService('pdoFetch');
        $this->pageId = $modx->resource->id;
        $this->sProp['main'] = explode(',',$scriptProperties['main']);
        $this->sProp['additional'] = explode(',',$scriptProperties['additional']);
        $this->allowedReq = array('sortby','page','search');
        $this->lexicon = array(
            'exType' => 'Какой чай',
            'exMedical' => 'Лечебный эффект',
            'exPsycho' => 'Психологический эффект',
            'exUnit' => 'В каком виде',
            'exLeaf' => 'Тип листа',
            'exSort' => 'Сорт чая',
            'exPlace' => 'Место сбора',
            'vendor' => 'Завод',
        );
        $this->fieldTypes = array(
            'asColor' => array('exMedical','exPsycho'),
            'vendor' => array('vendor'),
            'simple' => array('exType','exUnit','exLeaf','exSort','exPlace')
        );

    }

    public function initialize(){

        // Обработка данных и урла
        $this->processRequestData();
        $this->processUrl();



        $this->switchQueryType(); // getFilteredIds()

        $this->makeFilters(); // -> makeFilter::makeOneFilter();
        $this->makePrice();
        $this->makeProducts();

        $this->sortProcessor();
        $this->searchProcessor();

    }

    public function processRequestData(){
        // Обработка $_REQUEST
        $allowedReqFields = array_merge($this->sProp['main'],$this->sProp['additional'],$this->allowedReq);
        foreach($_REQUEST as $name => $var){
            if(!in_array($name,$allowedReqFields)) continue;
            if(strpos($var,'|')) $var = explode('|',$var);
            $var = str_replace("не указан","",$var);
            $this->req[$name] = $var;
        }
        if($this->req['search']){ // Подключение Sphinx, если есть запрос
            require_once ("sphinxapi.php");
            $this->sphinx = new SphinxClient();
        }
        $this->modx->setPlaceholder('petja.q',$_REQUEST['q']);
    }

    protected function processUrl(){
        $prefix = '';
        foreach($this->req as $n => $v){
            if(is_array($v)){
                $prefix .= '&'.$n.'=';
                foreach($v as $_v){
                    $prefix .= $_v.'|';
                }
                $prefix = rtrim($prefix, "|");
            }else{
                $prefix .= '&'.$n.'='.$v;
            }
        }
        $prefix = ltrim($prefix, "&");

        $this->url = $prefix;
        parse_str(ltrim($prefix,'?'), $newReq);
        $_GET = $newReq;
    }

    public function getChildIds($parent,$level = 5){

        $where = array();
        $where['Data.price:>'] = 0;

        $ret = $this->modx->runSnippet('msProducts',array(
                'returnIds' => '1'
            ,'parents' => $parent
            ,'depth' => $level
            ,'where' => $this->modx->toJSON($where)
            ,'limit' => '0'
                //,'return' => 'sql'
            ));
        $ret = array_map('intval', explode(',', $ret));
        return $ret;
    }

    protected function switchQueryType(){
        /**
         * Change Query Type: emptyQuery and nonemptyQuery
         *
         * If emptyQuery gets only $this->idsChild = $this->getChildIds
         * If nonemptyQuery gets $this->idsChild and $this->idsFiltered = $this->getFilteredIds()
         */
        $this->idsChild = $this->getChildIds($this->pageId,3);
        $check_main = array_intersect_key(array_flip($this->sProp['main']),$this->req);
        $check_additional = array_intersect_key(array_flip($this->sProp['additional']),$this->req);
        if(!$check_main and !$check_additional and !$this->req['price'] and !$this->req['search']){
            $this->qType = 'emptyQuery';
        }else{
            $this->qType = 'nonemptyQuery';
            $this->idsFiltered = $this->getFilteredIds();
        }
    }

    protected function makeFilters(){

        require_once( dirname(__FILE__).'/makefilter.class.php');
        $mf = new makeFilter($this);

        $mainFilters = $this->sProp['main'];
        $additionalFilters = $this->sProp['additional'];
        $outputMain = $outputAdditional = '';
        $ids = implode(',',$this->idsChild);
        foreach($mainFilters as $name){
            $outputMain .= $mf->makeOneFilter($name, $ids, 'main');
        }
        foreach($additionalFilters as $name){
            $outputAdditional .= $mf->makeOneFilter($name, $ids, 'additional');
        }

        $this->modx->setPlaceholder('petja.main',$outputMain);
        $this->modx->setPlaceholder('petja.additional',$outputAdditional);

    }

    protected function makePrice(){

        /**
         * Включить учет цены:
         * 1) Когда чекбоксы не менялись, а что-либо другое (сортировка, цена, пагинация) менялось
         * Отключить учет цены:
         * 1) Когда был выбран какойлибо чекбокс + раньше были выбраны другие чекбоксы
         *
         * Задачи:
         * 1) Если клик был не по чекбоксу, то послать и цену для фильтрации
         * 2) Если клик был по чекбоксу и при этом какие-то чекбоксы уже были выбраны до этого - то послать без цены
         *      - как определить были ли чекбоксы установлены?
         *
         * Итого:
         * 1) Если клик по чекбоксу, то убрать цену из запроса
         * 2) Если клик не по чекбоксу, то оставить цену в запросе
         */

        $idsChild = implode(',',$this->idsChild);
        $allSql = "SELECT MIN( price ) as min , MAX( price ) as max FROM modx_ms2_products WHERE price > 0 AND id IN ( $idsChild );";
        $allQ = $this->modx->prepare($allSql);
        $allQ->execute();
        $allRes = $allQ->fetchAll(PDO::FETCH_ASSOC);

        $gPrice['petja.price.min'] = $lMin = floor($allRes[0]['min']/10) * 10;
        $gPrice['petja.price.max'] = $lMax = ceil($allRes[0]['max']/10) * 10;

        if($this->qType == 'nonemptyQuery'){
            $fSql = "SELECT MIN( price ) as min , MAX( price ) as max FROM modx_ms2_products WHERE price > 0 AND id IN ( $this->idsFiltered );";
            $fQ = $this->modx->prepare($fSql);
            $fQ->execute();
            $fRes = $fQ->fetchAll(PDO::FETCH_ASSOC);
            $lMin = (floor($fRes[0]['min']/10) * 10);
            $lMax = (ceil($fRes[0]['max']/10) * 10);
        }

        $priceHtml = "
        <h5>Цена</h5>
        <input type=\"slider\" id=\"price-slider\" value=\"$lMin|$lMax\" name=\"price\">
        <div class=\"reset-wrap\">
            <a class=\"reset\" data-target=\"price\" href=\"#\">[Сброс]</a>
        </div>
        <hr/>
        ";
        $this->modx->setPlaceholder('petja.price',$priceHtml);
        $this->modx->setPlaceholders($gPrice);
    }

    protected function makeProducts(){
        $where = array();
        if($this->qType == 'nonemptyQuery'){
            $where['id:IN'] = explode(',',$this->idsFiltered);
        }elseif($this->qType == 'emptyQuery'){
            $where['id:IN'] = $this->idsChild;
            $where['Data.price:>'] = '0';
        }

        if($sortby = $this->req['sortby']){

            $sortby = "{\"Data.price\": \"$sortby[1]\"}";
        }

        // Подсчитать товары
        $leftJoin = '[{"class":"msProductData","alias":"Data","on":"msProduct.id=Data.id"}]';
        $this->pdo->config = array(
            'class' => 'msProduct'
            ,'where' => $this->modx->toJSON($where)
            ,'leftJoin' => $leftJoin
            ,'fastMode' => true
            ,'return' => 'data'
            //,'return' => 'sql'
            ,'limit' => 0
        );
        $runCount = $this->pdo->run();
        $count = count($runCount);
        $this->modx->setPlaceholder('petja.count',$count);

        $prods = $this->modx->runSnippet('pdoPage',array(
                'element' => 'msProducts'
                ,'includeThumbs' => '183x122'
                ,'limit' => '12'
                ,'where' => $this->modx->toJSON($where)
                ,'sortby' => $sortby
            ));
        $this->modx->setPlaceholder('petja.products',"<!--$prods-->");
    }

    private function getFilteredIds(){
        $where = $likes = array();

        $allProps = array_merge($this->sProp['main'],$this->sProp['additional']);

        foreach($allProps as $prop){
            if(in_array($prop,$this->fieldTypes['asColor'])){
                if(!is_null($reqItems = $this->req[$prop])){

                    //($where["Data.$prop:LIKE"]) ? $prefix = 'OR:' : $prefix = '';

                    if(count($reqItems) > 1){
                        foreach($reqItems as $item){
                            ($item) ? $likes[$prop][] = '%'.addslashes(json_encode($item)).'%' : $likes[$prop][] = false;
                        }
                    }elseif($reqItems == ''){
                        $where["Data.$prop"] = '';
                    }else{
                        (is_array($reqItems)) ? $likes[$prop][] = '%'.addslashes(json_encode($reqItems[0])).'%' : $likes[$prop][] = '%'.addslashes(json_encode($reqItems)).'%';
                    }
                }
            }else{
                if(!is_null($reqItems = $this->req[$prop])){
                    if(count($reqItems) > 1){
                        $where["Data.$prop:IN"] = $reqItems;
                    }elseif($reqItems == ''){
                        $where["Data.$prop"] = '';
                    }else{
                        (is_array($reqItems)) ? $where["Data.$prop"] = $reqItems[0] : $where["Data.$prop"] = $reqItems;
                    }
                }
            }
        }

        if($price = $this->req['price']){
            $min = $price[0];
            $max = $price[1];
            $where['Data.price:>='] = $min;
            $where['Data.price:<='] = $max;
        }else{
            $where['Data.price:>'] = 0;
        }

        if($search = $this->req['search']){
            $this->sphinx->SetMatchMode(SPH_MATCH_EXTENDED2);
            $this->sphinx->SetSortMode(SPH_SORT_RELEVANCE);
            $result = $this->sphinx->Query($search, '*');
            $found = array_keys($result['matches']);
            $diff = array_intersect($this->idsChild,$found);
            $where['Data.id:IN'] = $diff;
            //$this->dump(array($found,$diff,$this->idsChild),$type='some');
            //$this->dump($found); // 777
        }else{
            $where['Data.id:IN'] = $this->idsChild;
        }

        $this->where = $where;

        $add = '';
        foreach($likes as $kk => $vv){
            $add .= '(';
            $sum = count($vv);
            foreach($vv as $k => $v){
                if($k == $sum-1){
                    $or = '';
                }else{
                    $or = 'OR ';
                }
                if($v){
                    $vs = addslashes($v);
                    $add .= "`Data`.`$kk` LIKE '$vs' $or";
                }else{
                    $add .= "`Data`.`$kk` = '' $or";
                }
            }
            $add .= ')';
        }
        $where[] = $add;

        $idsFiltered = $this->modx->runSnippet('msProducts',array(
                'returnIds' => '1'
                ,'limit' => '0'
                ,'where' => $this->modx->toJSON($where)
                //,'return' => 'sql'
                ,'fastMode' => true
            ));
        //print_r($idsFiltered);

        return $idsFiltered;

    }

    protected function sortProcessor(){

        $prefix = '?'.preg_replace("#(\?|&)?sortby=price\|(asc|desc)#i","",$this->url);

        $baseUrl = $this->modx->makeUrl($this->pageId);

        if($prefix){
            $priceAscUrl = $baseUrl . $prefix . '&sortby=price|asc';
            $priceDescUrl = $baseUrl . $prefix . '&sortby=price|desc';
        }else{
            $priceAscUrl = $baseUrl . '?sortby=price|asc';
            $priceDescUrl = $baseUrl . '?sortby=price|desc';
        }

        $price = '';

        if($sortby = $this->req['sortby']){
            if($sortby[0] != 'price') return false;
            switch($sortby[1]){
            case 'asc':
                $price = "
                <li><a href=\"$priceDescUrl\" class=\"sortby\" data-sortby=\"price|desc\">Дороже</a></li>
                <li>&nbsp;/&nbsp;</li>
                <li class=\"active\">Дешевле</li>
                ";

                break;
            case 'desc':
                $price = "
                <li class=\"active\">Дороже</li>
                <li>&nbsp;/&nbsp;</li>
                <li><a href=\"$priceAscUrl\" class=\"sortby\" data-sortby=\"price|asc\">Дешевле</a></li>
                ";

                break;
            }

            $phSort = "<input type=\"hidden\" name=\"sortby\" value=\"{$sortby[0]}|{$sortby[1]}\"/>";
            $this->modx->setPlaceholder('petja.hidden.sortby',$phSort);

        }else{
            $price = "
            <li><a href=\"$priceDescUrl\" class=\"sortby\" data-sortby=\"price|desc\">Дороже</a></li>
            <li>&nbsp;/&nbsp;</li>
            <li><a href=\"$priceAscUrl\" class=\"sortby\" data-sortby=\"price|asc\">Дешевле</a></li>
            ";
        }
        $price = "<li class=\"sort-head\"><span class=\"glyphicon glyphicon-sort\"></span></li><li>&nbsp;</li>$price";
        $this->modx->setPlaceholder('petja.sortby',$price);
    }

    protected function searchProcessor(){
        parse_str($this->url,$gets);
        $hidden = '';
        foreach($gets as $n => $v){
            if($n == 'search') continue;
            $hidden .= "<input type=\"hidden\" name=\"$n\" value=\"$v\">";
        }
        $baseUrl = $this->modx->makeUrl($this->pageId);
        $this->modx->setPlaceholder('petja.search_action',$baseUrl);
        $this->modx->setPlaceholder('petja.search_hidden_filters',$hidden);
        $this->modx->setPlaceholder('petja.search_word',$gets['search']);
    }

    public function dump($var, $type = 'one'){
        ob_start();
        if($type == 'some'){
            foreach($var as $v){
                var_dump($v);
            }
        }else{
            var_dump($var);
        }

        $dump = ob_get_clean();
        $this->modx->setPlaceholder('petja.dump',$dump);
    }

}