<?php
/**
 * User: Petja
 * Date: 29.11.13
 * Time: 9:21
 */

class makeFilter {

    public $parent, $modx, $flag, $vendors;

    function __construct(&$parent){
        $this->parent = $parent;
        $this->modx = $parent->modx;
    }

    function makeOneFilter($name,$ids,$tpl){
        $output = '';
        if(in_array($name,$this->parent->fieldTypes['asColor'])){
            $this->flag = 'asColor';
        }else{
            $this->flag = 'simple';
        }

        if(in_array($name,$this->parent->fieldTypes['vendor'])){
            $venSql = "SELECT `id`, `name` FROM `modx_ms2_vendors`;";
            $venQ = $this->modx->prepare($venSql);
            $venQ->execute();
            $venRes = $venQ->fetchAll(PDO::FETCH_ASSOC);
            foreach($venRes as $r){
                $this->vendors[$r['id']] = $r['name'];
            }
        }

        $allRes = $this->getAll($name,$ids);
        if($this->parent->qType == 'nonemptyQuery'){
            $complex = $this->getComplexFromDb($name,$allRes);
            foreach($complex as $fName => $fVal){
                (empty($fVal[0])) ? $fVal[0] = 0 : $fVal[0];
                (empty($fVal[1])) ? $fVal[1] = 0 : $fVal[1];
                ($fVal[0] < 1) ? $isZero = ' zero' : $isZero = ' match';
                $checkedMark = false;
                if(!is_null($this->parent->req[$name]) and ($fName == $this->parent->req[$name] or in_array($fName,$this->parent->req[$name]))){
                    $checkedMark = ' checked';
                    $isZero = '';
                }

                if($this->vendors[$fName]){
                    $checkText = $this->vendors[$fName];
                    $checkValue = $fName;
                }else{
                    $checkText = $checkValue = $fName;
                }

                $checkText = $checkText ?: "не указан";
                $checkValue = $checkValue ?: "не указан";

                $data = array(
                    'name' => $name,
                    'value' => $checkValue,
                    'text' => "{$checkText} ($fVal[0])<sup>$fVal[1]</sup>",
                    'class' => $checkedMark.$isZero,
                    'mark' => $checkedMark,
                );
                if($tpl == 'main'){
                    $output .= $this->makeFilterElement($data,'fullBig');
                }elseif($tpl == 'additional'){
                    $data['disabled'] = ($isZero == ' zero') ? ' disabled="disabled"' : '';
                    $output .= $this->makeFilterElement($data,'fullBig');
                }
            }
        }elseif($this->parent->qType == 'emptyQuery'){
            foreach($allRes as $filter){

                if($this->vendors[$filter['val']]){
                    $checkText = $this->vendors[$filter['val']];
                    $checkValue = $filter['val'];
                }else{
                    $checkText = $checkValue = $filter['val'];
                }

                $checkText = $checkText ?: "не указан";
                $checkValue = $checkValue ?: "не указан";

                $data = array(
                    'name' => $name,
                    'value' => $checkValue,
                    'text' => "{$checkText} ({$filter['cnt']})",
                    'class' => '',
                    'mark' => '',
                );
                if($tpl == 'main'){
                    $output .= $this->makeFilterElement($data,'emptyBig');
                }elseif($tpl == 'additional'){
                    $output .= $this->makeFilterElement($data,'emptyBig');
                }
            }
        }
        // Обертка фильров:
        if($tpl == 'main'){
            $output = "<div class=\"chb-wrapper\"><a class=\"header\" href=\"{$this->modx->makeUrl($this->parent->pageId)}#\" data-toggle=\"collapse\" data-target=\"#$name\"><span class=\"caret\"></span> {$this->parent->lexicon[$name]}</a>&nbsp;&nbsp;<a class=\"reset\" data-target=\"checkbox\" href=\"{$this->modx->makeUrl($this->parent->pageId)}#\">[Сброс]</a><div class=\"body collapse\" id=\"$name\">$output</div></div>";
        }elseif($tpl == 'additional'){
            $output = "<div class=\"chb-wrapper closed\"><a class=\"header\" href=\"{$this->modx->makeUrl($this->parent->pageId)}#\" data-toggle=\"collapse\" data-target=\"#$name\"><span class=\"caret\"></span> {$this->parent->lexicon[$name]}</a>&nbsp;&nbsp;<a class=\"reset\" data-target=\"checkbox\" href=\"{$this->modx->makeUrl($this->parent->pageId)}#\">[Сброс]</a><div class=\"body collapse\" id=\"$name\">$output</div></div>";
        }
        return $output;
    }

    function getAll($name,$ids){
        $allRes = array();
        if($this->flag == 'asColor'){
            // id IN ( $ids ) - в ids уже лежат только опубликованные товары
            $allSql = "SELECT `$name` as val FROM `modx_ms2_products` WHERE price > 0 AND id IN ( $ids );";
            $allQ = $this->modx->prepare($allSql);
            $allQ->execute();
            $allRes = $allQ->fetchAll(PDO::FETCH_ASSOC);
            $newArr = array();
            $emptyCount = 0;
            foreach($allRes as $item){
                if(!$item['val']){
                    $emptyCount++;
                }else{
                    $tmp = json_decode($item['val']);
                    foreach($tmp as $t){
                        array_push($newArr, $t);
                    }
                }
            }
            $newArr = array_count_values($newArr);
            $allRes = array(0 => array('val' => '', 'cnt' => $emptyCount));
            $c = 1;
            foreach($newArr as $k => $v){
                $allRes[$c]['val'] = $k;
                $allRes[$c]['cnt'] = $v;
                $c++;
            }
        }elseif($this->flag == 'simple'){
            // Взять сгруппированные по имени и количество в группе val => cnt
            $allSql = "SELECT `$name` as val, COUNT(*) as cnt FROM `modx_ms2_products` WHERE price > 0 AND id IN ( $ids ) GROUP BY `$name`;";
            $allQ = $this->modx->prepare($allSql);
            $allQ->execute();
            $allRes = $allQ->fetchAll(PDO::FETCH_ASSOC);
            // Если пустое значение, то val - пустой, а cnt - счетчик пустых значений
        }
        return $allRes;
    }

    private function getComplexFromDb($name,$allRes){

        // Внимание!!! Беред данные из $this->parent->where, там надо тоже менять, если вдруг что поменялось
        $where = $this->parent->where;
        // Убрать текущее поле из запроса, чтобы не вычитал конкурентов
        unset($where['Data.'.$name],$where['Data.'.$name.':IN'],$where['Data.'.$name.':LIKE']);
        // Просто заменить Data на msProductData
        $nWere = array();
        foreach($where as $wk => $wv){
            $nk = str_replace('Data.','msProductData.',$wk);
            $nWere[$nk] = $wv;
        }
        $fRes = array();
        if($this->flag == 'asColor'){

            // Сделать запрос с фильтрами
            $conf = array(
                'class' => 'msProductData',
                'select' => 'msProductData.'.$name.' as val, COUNT(*) as cnt ',
                'where' => $nWere,
                'groupby' => $name,
                'fastMode' => true,
                'return' => 'data',
                'limit' => 0
            );
            $this->parent->pdo->setConfig($conf);
            $fRes = $this->parent->pdo->run();

            $newArr = array();
            $empty = 0;
            foreach($fRes as $item){
                if(!$item['val']){
                    $empty += $item['cnt'];
                }elseif(is_array($item['val'])){
                    foreach($item['val'] as $val){
                        if(!$val){
                            $empty += $item['cnt'];
                        }else{
                            $newArr[] = $val;
                        }
                    }
                }else{
                    $newArr[] = $item['val'];
                }
            }

            $newArr = array_count_values($newArr);

            $fRes = array(0 => array('val' => '', 'cnt' => $empty));
            $c = 1;
            foreach($newArr as $k => $v){
                $fRes[$c]['val'] = $k;
                $fRes[$c]['cnt'] = $v;
                $c++;
            }

        }elseif($this->flag == 'simple'){
            // Сделать запрос с фильтрами
            $conf = array(
                'class' => 'msProductData',
                'select' => 'msProductData.'.$name.' as val, COUNT(*) as cnt ',
                'where' => $nWere,
                'groupby' => $name,
                'fastMode' => true,
                'return' => 'data',
                //'return' => 'sql',
                'limit' => 0
            );
            $this->parent->pdo->setConfig($conf);
            $fRes = $this->parent->pdo->run();
        }

        $newF = array();
        foreach($fRes as $f){
            $newF[$f['val']] = $f['cnt'];
        }
        // Составить комплексный массив, чтобы содержал и общий счетчик и счетчик с текущим фильтром
        $complex = array();
        foreach($allRes as $all){
            $complex[$all['val']] = array($newF[$all['val']],$all['cnt']);
        }

        return $complex;
    }

    private function makeFilterElement($data = array(), $tpl = ''){

        $html = '';

        switch($tpl){
        case 'emptyBig':
            $html = "<div class=\"checkbox\">
                        <label>
                            <input type=\"checkbox\" name=\"{$data['name']}[]\" value=\"{$data['value']}\"> {$data['text']}
                        </label>
                    </div>";
            break;
        case 'fullBig':
            $html = "<div class=\"checkbox{$data['class']}\">
                        <label>
                            <input type=\"checkbox\" name=\"{$data['name']}[]\" value=\"{$data['value']}\"{$data['mark']}> {$data['text']}
                        </label>
                    </div>";
            break;
        case 'emptyMini':
            $html = "<option value=\"{$data['value']}\"> {$data['text']}</option>";
            break;
        case 'fullMini':
            $html = "<option value=\"{$data['value']}\"{$data['mark']}{$data['disabled']}>{$data['text']}</option>";
            break;
        }

        return $html;

    }

}