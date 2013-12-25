/**
 * User: Petja
 * Date: 16.11.13
 * Time: 14:10
 */

/*
todo petja:
 Если снять галки, то список с чекбоксами закрывается, хотя я может быть хотел одну снять, а другую поставить
 Поэтому надо сделать или запоминание через куки раскрытых списков, или закрывать только при первой загрузки страницы...
 Хотя лучше куки
 */

// Carets
(function ($) {
    $.extend({
        initCarets: function () {
            this.closeCarets();
            this.caretHandler();
        },
        closeCarets: function(){ // $.closeCarets();
            $('#flt-wrapper .chb-wrapper').each(function(){
                var $this = $(this);
                var checked = $this.find(':checked').length;
                if($this.hasClass('closed') && !checked){
                    $this.children('.collapse').hide();
                }
            });
        },
        caretHandler: function(){
            $('#flt-wrapper').on('click','a.header',function(e){
                e.preventDefault();
                var chbWrapper = $(e.target).closest('.chb-wrapper');
                var closed = chbWrapper.hasClass('closed');
                if(closed){
                    chbWrapper.removeClass('closed')
                        .children('.collapse').show();
                }else{
                    chbWrapper.addClass('closed')
                        .children('.collapse').hide();
                }
            });
        }
    });
})(jQuery);

// Catalog
(function($){
    var methods = {
        init: function(options){
            return this.each(function(){
                $.initCarets();
                var url = uri.get();
                url.price = $('#price-slider').val();
                uri.set(url); // Переделать урл в сокращенный вид
                service.renderSlider();
                handlers.init(); // Привязать события

                /**
                 * Варианты того, как можно собрать данные:
                 * 1) С uri (все данные)
                 * 2) С формы serializeArray() - только данные формы...
                 * 3) Обход элементов по всей странице: форма + сортировка + пагинация
                 *      Вывод: буду хранить данные в uri
                 *
                 * Варианты клика и действие:
                 * 1) поставить галку - добавить свойство в uri, отправить запрос из данных в uri
                 * 2) снять галку - убрать свойство из uri, отправить запрос
                 *
                 * Места вставки:
                 * 1) sortWr / [[+petja.sortby]]
                 * 2) totalWr / [[+petja.count]]
                 * 3) pagWr / [[+page.nav]]
                 * 4) prodsWr / [[+petja.products]]
                 * 5) mainWr / [[+petja.main]]
                 * 6) additWr / [[+petja.additional]]
                 */

            })
        }
        ,destroy: function(){
            return this.each(function(){
                $(window).off('.petjaCatalogNS');
            })
        }

        ,update: function(){
            var postData = uri.get();
            postData.pageId = globs.pageId;
            postData.q = globs.qVar;
            $.post('/assets/components/teamn/connector.php?action=catalog/update', postData).done(function(data){
                var html = $.parseJSON(data);
                $.each(html,function(i,v){
                    if(i != 'searchWord'){
                        $('#'+i).empty()
                            .html(v);
                    }else if(v){
                        $('.search-inp').val(v);
                    }
                });
                var url = uri.get();
                url.price = $('#price-slider').val();
                uri.set(url);
                $.closeCarets();
                service.renderSlider();
            });
        }
    };
    
    var service = {
        renderSlider: function(){
            $('#price-slider').slider({
                from: globs.priceMin,
                to: globs.priceMax,
                step: 10,
                dimension: '&nbsp;руб.',
                skin: "round_plastic",
                callback: function(){
                    var url = uri.get();
                    url.price = this.inputNode.val();
                    uri.set(url);
                    methods.update();
                }
            });
        }
        ,resetCheckGroup: function(id){
            var elements = $('#'+id+' input:checked'), count = elements.length;
            elements.each(function(){
                $(this).attr('checked',false);
                uri.remove(id,$(this).attr('value'));
                uri.remove('price');
                if (!--count) methods.update();
            });
        }
    };

    var handlers = {
        init: function(){
            /**
             * Установить обработчики на:
             * 1) Клик по галке checked
             * 2) Перетаскивание слайдера прайса
             * 3) Клик по пагинации
             * 4) Клик по сортировке
             * 5) Клик по сбросу resets
             * 6) Клик по поиску
             */
            this.checked();
            this.resets();
            this.sorts();
            this.search();
            this.pagination();

            //this.resets();
        }
        ,pagination: function(){
            $('#pagWr').on('click.petjaCatalogNS', 'a', function(e){
                e.preventDefault();
                var $this = $(this);
                if(!$this.parent().hasClass('active')){
                    var href = $this.attr('href');
                    var queryString = href.substring( href.indexOf('?') + 1 );
                    var queries = queryString.split("&"), params = [],temp, i, l;
                    for( i = 0, l = queries.length; i < l; i++ ){
                        temp = queries[i].split('=');
                        if(temp[0] == 'page') var page = temp[1];
                        break;
                    }
                    var url = uri.get();
                    if(!page){
                        delete url.page;
                    }else{
                        url.page = page;
                    }
                    //console.log(page);
                    uri.set(url);
                    methods.update();
                }
            });
        }
        ,sorts: function(){
            $('#sortWr').on('click.petjaCatalogNS', '.sortby', function(e){
                e.preventDefault();
                uri.remove('sortby');
                uri.add('sortby',$(this).data('sortby'));
                methods.update();
            });
        }
        ,search: function(){
            var $sw = $('#searchWr');
            $sw.on('click.petjaCatalogNS', ':submit', function(e){
                e.preventDefault();
                uri.remove('search');
                uri.remove('page');
                uri.remove('price');
                uri.add('search',$sw.find('input[name="search"]').val());
                methods.update();
            });
        }
        ,checked: function(){
            $('#flt-wrapper').on('click.petjaCatalogNS',':checkbox',function(e){
                var $this = $(this);
                if($this.is(':checked')){
                    uri.remove('page');
                    uri.remove('price');
                    uri.add($this.attr('name'),$this.val());
                }else{
                    uri.remove('page');
                    uri.remove('price');
                    uri.remove($this.attr('name'),$this.val());
                }
                methods.update();
            });
        }
        ,resets: function(){
            $('#petja-catalog').on('click.petjaCatalogNS','.reset',function(e){
                e.preventDefault();
                var $aReset = $(e.target),
                    target = $aReset.data('target') || $aReset.parent('.reset').data('target');
                /* При нажатии на ресет, надо:
                    -1) Сбрость все чекбоксы
                    +2) Обновить глобальный массив параметров, вычесть все настройки данного блока
                    3) Послать запрос с новыми параметрами
                    4) нарисовать новые фильтры
                 */

                var url = uri.get();
                switch (target){
                    case 'price':
                        var $priceSlider = $('#price-slider');
                        $priceSlider.slider("value", globs.priceMin, globs.priceMax);
                        //uri.add('price',$('#price-slider').val());
                        url.price = $priceSlider.val();
                        uri.set(url);
                        methods.update();
                        break;
                    case 'search':
                        var $inp = $('#searchWr').find('input[name="search"]');
                        if($inp.val()){
                            $inp.val('');
                            uri.remove('search');
                            uri.remove('page');
                            uri.remove('price');
                            methods.update();
                        }
                        break;
                    default:
                        // Запустить запрос на получение элементов.
                        var checkBoxId = $aReset.next().attr('id');
                        service.resetCheckGroup(checkBoxId);
                        break;
                }
            });
        }
    };

    var uri = {

        /**
         * Единый формат get/set
         * Имеем в строке 2 варианта:
         * 1) &exType[]=Белый+чай&exType[]=Жасминовый+чай
         * 2) &exType=Белый чай|Жасминовый чай
         * get дает:
         * Object { price="90|11440", exType="Белый+чай|Жасминовый+чай|Зеленый+чай"}
         */

        get: function(){
            var vars = {}, hash, splitter, hashes;
            if(this.oldBrowser()){
                var pos = window.location.href.indexOf('?');
                hashes = decodeURIComponent(window.location.href.substr(pos + 1));
                hashes = hashes.replace(/\+/g," ");
                splitter = '&';
            }else{
                hashes = decodeURIComponent(window.location.hash.substr(1));
                hashes = hashes.replace(/\+/g," ");
                splitter = '/'
            }
            if (hashes.length == 0) {return vars;}
            else {hashes = hashes.split(splitter);}
            for (var i in hashes) {
                if (hashes.hasOwnProperty(i)) {
                    hash = hashes[i].split('=');
                    if(!hash[1]) continue;
                    hash[0] = hash[0].replace(/\[\d*\]/g,"");
                    if(!vars[hash[0]]){
                        vars[hash[0]] = hash[1]
                    }else{
                        vars[hash[0]] = vars[hash[0]] + '|' + hash[1];
                    }
                }
            }
            return vars;
        }
        ,set: function(vars) {
            var hash = '';
            for (var i in vars) {
                if (vars.hasOwnProperty(i)) {
                    hash += '&' + i + '=' + vars[i];
                }
            }
            if (this.oldBrowser()) {
                if (hash.length != 0) {
                    hash = '?' + hash.substr(1);
                }
                window.history.pushState(hash, '', document.location.pathname + hash);
            }
            else {
                window.location.hash = hash.substr(1);
            }
        }
        ,add: function(key, val) {
            key = key.replace(/\[\d*\]/g,"");
            //val = val.replace(/\s/g,"+");
            var hash = this.get();
            if(hash[key]){
                hash[key] += '|' + val;
            }else{
                hash[key] = val;
            }
            this.set(hash);
        }
        ,remove: function(key, val) {
            key = key.replace(/\[\d*\]/g,"");
            var hash = this.get();
            if(val){
                hash[key] = hash[key].split('|');
                hash[key] = jQuery.grep(hash[key], function(value) {
                    return value != val;
                });
                hash[key] = hash[key].join('|');
                if(!hash[key]){
                    delete hash[key];
                }
            }else{
                delete hash[key];
            }
            this.set(hash);
        }
        ,oldBrowser: function() {
            return !!(window.history && history.pushState);
        }
    };

    $.fn.petjaCatalog = function(method){
        if(methods[method]){
            return methods[method].apply(this,Array.prototype.slice.call(arguments, 1));
        }else if(typeof method === 'object' || !method){
            if(arguments) methods.init.apply(this, arguments);
            else methods.init.apply();
        }else{
            $.error('Метод '+ method + ' не найден');
        }
    };

})(jQuery);