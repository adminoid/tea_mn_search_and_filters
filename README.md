Пример кода, конец 2013-го
=========================

- Это клиентский скрипт, он у модуля один, с комментариями - ничего, все равно они у меня потом автоматом сжимаются через yui compressor:
https://github.com/adminoid/tea_mn_search_and_filters/blob/master/assets/components/teamn/js/web/petjacatalog.js
- Это файл, на который приходят ajax запросы, у MODX так приянято пропускать ajax запросы через коннектор:
https://github.com/adminoid/tea_mn_search_and_filters/blob/master/assets/components/teamn/connector.php
- который их проверяет и отправляет на процессор:
https://github.com/adminoid/tea_mn_search_and_filters/blob/master/core/components/teamn/processors/catalog/update.php
- А вот основные три серверных класса:
https://github.com/adminoid/tea_mn_search_and_filters/tree/master/core/components/teamn/model/petjacatalog
