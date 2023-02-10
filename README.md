# Bitrix in Docker

## Сообщество

1. Если есть какие-то вопросы: вступайте в группу телеграм: https://t.me/bitrixdevops.
2. Если Вы нашли ошибку, у Вас есть замечания или предложения по функционалу, пишите тикет сюда: https://gitlab.com/groups/bitrix-docker/-/issues.

## Начало работы

1. Скопировать файл [.env.example](.env.example) в файл [.env](.env) - это будет локальный конфиг.
2. Создать сайт (см. ниже раздел руководства).
3. Запустить сервер:
   ```bash
   docker-compose up -d
   ```
4. Для локальной разработки добавить в hosts на локальной машине локальные ресурсы:
   ```editorconfig
   # bitrix-docker local
   127.0.0.1 bitrix.local
   127.0.0.1 traefik.bitrix.local
   127.0.0.1 adminer.bitrix.local
   127.0.0.1 mailhog.bitrix.local
   ```

## Добавление нового сайта

1. Скопировать папку [bitrix-distr/](bitrix-distr/) в папку [sites/](sites/).
2. Переименовать новую папку в соответствии с продуктивным адресом проекта, например: `test.example.com`. 
3. В файле **.env** в папке сайта заменить `example.ru` на имя папки нового сайта.
4. Открываем файл docker-compose.yml в новой папке и:
   1. Находим все вхождения подстроки "stub" и заменяем на имя проекта, при этом точки экранируем через дефисы, например: `test-example-com`. При этом в директивах volumes указываем с точками (так как это пути к папке на диске)
   2. Редактируем директиву Host(`${MAIN_HOST}`), указываем тут имя домена, для локального проекта, например, можно указать `example.local`, либо создать еще одну переменную в файле **.env**.
   3. Для локального проекта редактируем файл hosts на машине, добавляем туда запись. На linux-подобных машинах для этого выполняем:
      ```bash
      sudo nano /etc/hosts
      ```
5. В файле [.env](.env) добавляем в переменную COMPOSE_FILE путь к нашему новому compose-файлу, например: `:sites/test.example.com/docker-compose.yml`.
6. Перезапустить сервер (или из папки сайта - тогда запустится только он один, или из корня этого проекта - тогда запустятся все сайты сразу):
   ```bash
   docker-compose up -d
   ```

## Версия php

Для смены версии php необходимо изменить образ контейнера php-fpm в файле **docker-compose.yml**.
```yaml
stub-php-fpm:
    image: registry.gitlab.com/bitrix-docker/images/php-fpm-8.1:latest
```

Доступные на текущий момент образы:
- registry.gitlab.com/bitrix-docker/images/php-fpm-7.4:latest
- registry.gitlab.com/bitrix-docker/images/php-fpm-8.1:latest

Также можно собрать и использовать свой образ, например, если нужно добавить какие-то нестандартные компоненты, например, redis.

## Memcached

Для настройки Memcached необходимо для каждого сайта выполнить следующие шаги:

1. В файле **/bitrix/php_interface/dbconn.php** добавить объявление констант:
   ```php
   define("BX_CACHE_TYPE", "memcache");
   define("BX_CACHE_SID", $_SERVER["DOCUMENT_ROOT"]."#01");
   define("BX_MEMCACHE_HOST", "memcached");
   define("BX_MEMCACHE_PORT", "11211");
   ```
2. В файле **/bitrix/.settings_extra.php** (если его нет, то создать):
   ```php
   <?php
   # /bitrix/.settings_extra.php
   return array(
    'cache' => array(
      'value' => array(
        'type' => 'memcache',
        'memcache' => array(
          'host' => 'memcached',
          'port' => '11211',
        ),
        'sid' => $_SERVER["DOCUMENT_ROOT"]."#01"
      ),
    ),
   );
   ?>
   ```
   Либо в **/bitrix/.settings.php** можно создать / отредактировать узел `cache`.

> Если используется многосайтовость, то нужно указывать статичный sid, без `$_SERVER["DOCUMENT_ROOT"]`. Иначе для двух сайтов кеш будет отличаться, так-как папки сайтов разные.

Документация Битрикс:
1. https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=32&LESSON_ID=9421
2. https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2795#cache

> Расширения memcached и memcache для php - это разные вещи. Устанавливать надо именно memcache.

## Как развернуть существующий сайт из бекапа Битрикс

1. Скачать бекап в корень папки www сайта
2. Для разархивирования бекапа проще воспользоваться командой ниже, чем ждать, пока распакуются файлы скриптом restore.php:
   ```bash
   cat *.tar.* | tar xzpvf -
   ```
3. На некоторых сайтах этот метод не срабатывает и заканчивается ошибкой, тогда запускаем распаковку архива обычным restore.php.
4. После распаковки архива нужно создать базу данных с помощью restore.php. Указываем:
   1. Сервер баз данных: **db**
   2. Имя пользователя: **root**
   3. Пароль: **test** (указывается в файле [.env](.env) в переменной MYSQL_ROOT_PASSWORD)
   4. Имя базы данных - придумать в зависимости от проекта, лучше не включать спецсимволы и знаки пунктуации, например: `examplecom`

## Настройка Cron

Для настройки cron-заданий сайта необходимо отредактировать файл **/crontabs/root**, расположенный в папке сайта.

## Настройка Sphinx

1. Скопировать файл [conf/sphinx/example.conf](conf/sphinx/example.conf) в папку [conf/sphinx/sites](conf/sphinx/sites), имя файла присвоить в соответствии с доменом сайта, с заменой точек на дефисы. Расширение должно быть обязательно **.conf**.
2. Идентификатор индекса (первая строка) заменить на имя домена без спецсимволов.
3. Строку **example-ru** в двух местах заменить на имя домена с заменой точек на дефисы.
4. В папке [data/sphinx/synonyms](data/sphinx/synonyms) создать файл, аналогичный [data/sphinx/synonyms/example.txt](data/sphinx/synonyms/example.txt).
5. Пересоздать контейнер, если ранее был запущен.
6. Перейти в настройки модуля поиска в админке (/bitrix/admin/settings.php?lang=ru&mid=search). На вкладке "Морфология" указать:
   - Использовать морфологию: `Да`.
   - Полнотекстовый поиск с помощью: `Sphinx`.
   - Строка подключения для управления индексом (протокол MySql): `sphinx:9306`.
   - Идентификатор индекса: как было задано в п.2.
7. Выполнить полную переиндексацию модулем поиска (/bitrix/admin/search_reindex.php).

## Настройка почты

Для локального тестирования почтовых сообщений можно использовать mailhog. Доступен по умолчанию по адресу: [mailhog.bitrix.local](http://mailhog.bitrix.local).

## Многосайтовая конфигурация

1. Настраиваем сайт аналогично руководству выше. Но ничего устанавливать не надо. Фактически нам необходимо далее прокинуть папки **bitrix**, **upload**, **images** из основного сайта в дополнительный. Во всех инструкциях ниже заменить **main.ru** на папку, где лежит основной сайт (ядро Битрикс).
2. Добавляем в секцию `volumes` подобное:
   ```yaml
   volumes:
      - ./../sites/main.ru/www/bitrix:/var/main.ru/bitrix:cached
      - ./../sites/main.ru/www/upload:/var/main.ru/upload:cached
      - ./../sites/main.ru/www/images:/var/main.ru/images:cached
   ```
3. Cоздаем симлинки внутри php-fpm контейнера:
   ```bash
   ln -s /var/main.ru/bitrix/ /var/www/www/bitrix
   ln -s /var/main.ru/upload/ /var/www/www/upload
   ln -s /var/main.ru/images/ /var/www/www/images
   ```
4. Чтобы папки подключились и на хост-машине (обычно это нужно для разработки, чтобы IDE видела файлы ядра), можно аналогично создать симлинк:
   ```bash
   ln -s ~/Work/bitrix-docker/sites/main.ru/www/ /var/main.ru
   ```
   > В MacOS последняя команда требует sudo.

## Установка обновлений

```bash
docker-compose pull \
&& docker-compose up --force-recreate --remove-orphans --build -d \
&& docker image prune -f
```

## Решение проблем

### Не запускается MySQL

Иногда после перезапуска MySQL не хочет перезапускаться. В этом случае необходимо зайти в папку [data/mysql](data/mysql) и удалить файлы mysql.sock и mysql.sock.lock.
