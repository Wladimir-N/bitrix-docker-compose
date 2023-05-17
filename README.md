# Bitrix in Docker

## Возможности

- Запуск одной командой всех сервисов, необходимых для работы БУС / Битрикс24:
   - **Php-fpm** (Apache не используем)
   - **Nginx**
   - **MySQL** (в данной сборке используется Percona)
   - **Memcached**
   - **Sphinx**
- Решение полностью проходит все тесты Битрикс / Битрикс24.
- Создание нескольких сайтов на одном сервере, а также многосайтовые конфигурации.
- Автоматизация получения сертификатов для доменов (нужно только прописать email для LetsEncrypt - все остальное происходит автоматически).
- Правильная контейнеризация. Каждый сервис запускается в отдельном контейнере.
- Поддержка нескольких версий php на одном сервере для разных сайтов. Даже если сайты работают на одном ядре (только к такой конфигурации надо подходить с умом).
- Улучшенная безопасность. Если злоумышленник взломает один из сайтов на сервере, он не сможет получить доступ к другим сайтам. Таким образом, можно на одном сервере с новыми и важными проектами хранить устаревшие проекты и не обновлять их.
- Возможности для быстрого создания тестовых площадок и разграничения доступов разработчиков к файлам разных сайтов / площадок. 
- Все данные (БД) хранятся на хост-машине:
  - При удалении контейнеров данные не теряются.
  - Для переноса всех сайтов на новый сервер достаточно просто скопировать папку и запустить докер на новом сервере (повторно не нужно все настраивать).
- **Mailhog** для тестирования почты при локальной разработке.
- **Msmtp** для отправки почты с боевых серверов.
- **Crontab** и агенты на кроне "из коробки".
  - Крон работает в отдельном контейнере, что минимизирует влияние фоновых заданий на работу пользователей на сайте.
- **Xdebug** для отладки.
- **MySQL Adminer** для тех, кто не научился работать с БД в PhpStorm.
- **Traefik** удобный прокси-сервер для докер-контейнеров.
- Редирект с www на домены без www или наоборот. Редирект на https.
- Подготовленный **sphinx** для настройки синонимов и словоформ.
- Заглушка, если сайт не найден по домену.

> Push & pull сервер не включен в решение (можно использовать облачный Битрикс в настройках модуля Push & Pull). Возможно, добавим в будущих версиях.

## Сообщество

1. Если есть какие-то вопросы: вступайте в группу телеграм: https://t.me/bitrixdevops.
2. Если Вы нашли ошибку, у Вас есть замечания или предложения по функционалу, пишите тикет сюда: https://gitlab.com/bitrix-docker/server/-/issues.

## Дорожная карта

В планах следующие работы:
- Активное тестирование решения в продакшн-окружении.
- CI/CD для деплоя на бой и на тестовые площадки.
- Автоматизация рутинных операций (например, создание сайта).
- Автоматическое разворачивание площадок для тестовых и боевого серверов.
- Подключение своих SSL-сертификатов.
- FTP для работы с файлами сайтов.
- Полезный инструментарий для современной разработки, которым мало кто пользуется в Битрикс, но он повышает качество и скорость разработки:
   - Профилирование запросов.
   - Инструменты для сбора, хранения, поиска логов.
   - Инструменты для сбора и обработки ошибок.
   - Полуавтоматическое документирование REST API (в формате OpenAPI / Swagger).
   - Интеграция с серверами очередей (RabbitMQ, Kafka).
- Эксперименты с Kubernetes, развертывание кластеров.

## Установка Docker

### Ubuntu

Официальная документация: https://docs.docker.com/engine/install/ubuntu/

```bash
sudo apt-get update
sudo apt-get install \
    ca-certificates \
    curl \
    gnupg \
    lsb-release
sudo mkdir -m 0755 -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

sudo systemctl enable docker.service
sudo systemctl enable containerd.service
```

Кроме того, рекомендуется установить:
```bash
apt install git nano htop apache2-utils
```

### MacOS

Официальная документация: https://docs.docker.com/desktop/install/mac-install/.

Достаточно скачать соответствующий вашему процессору дистрибутив и запустить установку.

## Начало работы

1. Склонировать репозиторий, например, в папку **/srv/bitrix-server/**:
   ```bash
   mkdir -p /srv/bitrix-server/ && cd /srv/bitrix-server/ && git clone git@gitlab.com:bitrix-docker/server.git .
   ```
2. Скопировать файл [.env.example](.env.example) в файл [.env](.env) - это будет локальный конфиг, отредактировать в нем настройки.
   ```bash
   cp .env.example .env && nano .env
   ```
   В частности:
   - **COMPOSE_FILE** - если какие-то из сервисов не нужны, то их можно отключить, удалив соответствующий конфиг в этом параметре.
   - **MAIN_HOST** - для локальной разработки лучше оставить как есть. Для сервера указать имя основного домена (на поддоменах будут работать общие системные сервисы).
   - **MYSQL_ROOT_PASSWORD** - для сервера создать сложный пароль
   - **TRAEFIK_CERTIFICATESRESOLVERS_LETSENCRYPT_ACME_EMAIL** - указать адрес почты для создания SSL-сертификатов LetsEncrypt (куда будут приходить уведомления о продлении сертификата)
   - **TRAEFIK_BASIC_AUTH_USERS** - создать пароль для закрытия Traefik (прокси-сервер)
   - **TRAEFIK_SSL_MIDDLEWARES** - для сервера лучше поменять значение на: basic-auth,redirect-to-non-www@file
   - **TRAEFIK_MIDDLEWARES** - для сервера лучше поменять значение на: basic-auth,redirect-to-non-www@file,redirect-to-https@file
3. Создать файл **data/traefik/letsencrypt/acme.json** (для хранения данных сертификатов LetsEncrypt).
   ```shell
   echo "{}" > data/traefik/letsencrypt/acme.json && chmod 600 data/traefik/letsencrypt/acme.json
   ```
4. Запускаем контейнеры:
   ```bash
   docker compose up -d
   ```
   В результате будут запущены общие сервисы, необходимые для работы всей системы.
5. Для локальной разработки отредактировать файл hosts на локальной машине:
   ```bash
   sudo nano /etc/hosts
   ```
   Нужно добавить следующие ресурсы:
   ```
   # bitrix-docker local
   127.0.0.1 bitrix.local
   127.0.0.1 traefik.bitrix.local
   127.0.0.1 adminer.bitrix.local
   127.0.0.1 mailhog.bitrix.local
   ```
   А также можно сразу добавить локальные домены сайтов, с которыми предстоит работать.
6. Добавить хотя бы один сайт (см. ниже раздел руководства).
7. В Ubuntu может быть ошибка с доступами к папке с данными БД. Для ее решения:
   1. Перейти в папку [data/mysql](data/mysql)
   2. Выполнить команду:
    ```bash
    sudo chown 999:999 .
    ```

## Добавление нового сайта

1. Скопировать папку [bitrix-distr](bitrix-distr) в папку [sites](sites) и назвать новую папку в соответствии с продуктивным адресом проекта, например: `test.example.com`:
    ```bash
    cp -r bitrix-distr/ sites/test.example.com
    ```
2. Скопировать в новой папке сайта файл **.env.local.example** либо **.env.server.example** в **.env** и отредактировать в нем настройки сайта.
3. Для локального проекта редактируем файл hosts на машине, добавляем туда запись с локальным доменом сайта (например, test.example.local), если не сделали это ранее. На linux-подобных машинах для этого выполняем:
   ```bash
   sudo nano /etc/hosts
   ```
4. Добавить запись следующего вида в файл hosts (заменив **test.example.local** на имя своего локального домена):
   ```
   # bitrix-docker local
   127.0.0.1 test.example.local www.test.example.local
   ```
5. Перейти в консоли в папку сайта и запустить сервисы:
   ```bash
   docker compose up -d
   ```
6. Открываем домен (test.example.local в примерах выше) в браузере и устанавливаем сайт через обычный мастер установки сайта Битрикс.
7. На этапе создания БД вписываем имя БД для сайта, имя пользователя root, пароль как указан в основном .env-файле в параметре MYSQL_ROOT_PASSWORD. Для использования другой учетной записи MySQL читаем руководство ниже.

## Пользователь MySQL

По умолчанию используется пользователь root и пароль, указанный в общем .env-файле сервера.
Для смены учетной записи MySQL для конкретного сайта:
1. Открываем файлы /bitrix/php_interface/dbconn.php и /bitrix/.settings.php. Берем из них имя базы данных.
2. Открываем MySQL-консоль в админке Битрикс (/bitrix/admin/sql.php).
3. Выполняем следующие запросы, заменив username, password и dbname на свои значения логина, пароля и имя базы данных соответственно:
   ```mysql
   CREATE USER username IDENTIFIED BY 'password';
   GRANT ALL PRIVILEGES ON dbname.* TO 'username';
   FLUSH PRIVILEGES;
   ```
4. В файлах /bitrix/php_interface/dbconn.php и /bitrix/.settings.php прописываем новые логин и пароль.

## Версия php

Редактируется в файле *.env* сайта.

## Настройка редиректов

### Настройка редиректов

Настройка редиректов может быть выполнена через переменные **PROJECT_SSL_MIDDLEWARES** и **PROJECT_MIDDLEWARES** файла **.env** сайта.
В системе преднастроены готовые конфигурации редиректов для обычного использования, такие как:
- http -> https
- www -> без www
- без www -> www

Аналогично для общих сервисов (traefik, adminer, mailhog) в общем файле [.env](/.env) проекта настраиваются редиректы в переменных TRAEFIK_SSL_MIDDLEWARES и TRAEFIK_MIDDLEWARES.

## Memcached

Для настройки Memcached необходимо для каждого сайта выполнить следующие шаги:

1. В папке сайта в файле **dbconn.php** добавить объявление констант:
   ```php
   # sites/домен.сайта/php_interface/dbconn.php
   
   define("BX_CACHE_TYPE", "memcache");
   define("BX_CACHE_SID", $_SERVER["DOCUMENT_ROOT"]."#01");
   define("BX_MEMCACHE_HOST", "memcached");
   define("BX_MEMCACHE_PORT", "11211");
   ```
2. В файле **.settings_extra.php** (если его нет, то создать):
   ```php
   <?php
   # sites/домен.сайта/bitrix/.settings_extra.php
   
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
   Либо в файле **.settings.php** можно создать / отредактировать узел `cache`.

> Если используется многосайтовость, то нужно указывать статичный sid, без `$_SERVER["DOCUMENT_ROOT"]`. Иначе для двух сайтов кеш будет отличаться, так-как папки сайтов разные.

Документация Битрикс:
1. https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=32&LESSON_ID=9421
2. https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2795#cache

> Расширения memcached и memcache для php - это разные вещи. Устанавливать надо именно memcache.

## Как ускорить разархивирование существующего сайта из бекапа Битрикс

Данный метод может значительно ускорить процесс разархивирования бекапа на MacOS.

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

### Выполнение агентов на cron

Для каждого сайта нужно выполнить первые шаги из официальной инструкции (https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=37&LESSON_ID=5507), а именно:
1. Выполнить команды в php-консоли:
   ```php
   COption::SetOptionString("main", "agents_use_crontab", "N");
   echo COption::GetOptionString("main", "agents_use_crontab", "N");
   
   COption::SetOptionString("main", "check_agents", "N");
   echo COption::GetOptionString("main", "check_agents", "Y");
   
   COption::SetOptionString("main", "mail_event_bulk", "20"); 
   echo COption::GetOptionString("main", "mail_event_bulk", "5");
   ```
2. Убираем из файла **dbconn.php** определение следующих констант:
   ```php
   # sites/домен.сайта/bitrix/php_interface/dbconn.php
   
   define("BX_CRONTAB_SUPPORT", true);
   define("BX_CRONTAB", true);
   ```
   И добавляем:
   ```php
   # sites/домен.сайта/bitrix/php_interface/dbconn.php
   
   if(!(defined("CHK_EVENT") && CHK_EVENT===true))
   define("BX_CRONTAB_SUPPORT", true);
   ```

> Остальные шаги из официальных и полуофициальных инструкций выполнять не надо - уже реализовано и настраивается автоматически.

### Добавление своих cron-заданий

> Делать это обычно не следует, так как в концепции Битрикс правильнее создавать агентов.

Для настройки cron-заданий сайта необходимо отредактировать файл **/crontab/root**, расположенный в папке сайта.

## Настройка Sphinx

1. Скопировать файл [config/sphinx/example.conf](config/sphinx/example.conf) в папку [config/sphinx/sites](config/sphinx/sites), имя файла присвоить в соответствии с доменом сайта, с заменой точек на дефисы. Расширение должно быть обязательно **.conf**.
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

## Xdebug

По умолчанию включен.

Для отключения Xdebug нужно переименовать файл в папке сайта /config/php-fpm/xdebug.ini в /config/php-fpm/xdebug.ini.disabled либо удалить его.
Для включения: вернуть файл / старое название обратно.

## Настройка почты

Из коробки можно настроить:
- mailhog (https://github.com/mailhog/MailHog)
- msmtp

> Внимание: mailhog и msmtp не могут функционировать одновременно. При одновременном включении работать будет тот вариант, который попадет в настройки sedmail_path в phpinfo.

### Настройка mailhog
Для локального тестирования почтовых сообщений можно использовать mailhog. Интерфейс доступен по умолчанию по адресу: [mailhog.bitrix.local](http://mailhog.bitrix.local).

По умолчанию включен. 

Для отключения mailhog переименовать файл в папке сайта /config/php-fpm/mailhog.ini в /config/php-fpm/mailhog.ini.disabled либо удалить его.
Для включения: вернуть файл / старое название обратно и, если включен msmtp, отключить его.

### Настройка msmtp

1. Отключить mailhog.
2. Переименовать файл в папке сайта /config/php-fpm/msmtp.ini.disabled в /config/php-fpm/msmtp.ini.
3. Скопировать файл [config/msmtp/msmtprc.example](config/msmtp/msmtprc.example) в **config/msmtp/msmtprc** и отредактировать, создав одну или несколько учетных записей.
4. В папке сайта в файлах **/crontab/root** и **/config/php-fpm/msmtp.ini** вместо default указать нужную учетную запись msmtp для сайта.

Если нужно отключить msmtp, переименовать обратно /config/php-fpm/msmtp.ini в /config/php-fpm/msmtp.ini.disabled или удалить его.

## Многосайтовая конфигурация

1. Создаем и настраиваем конфиги сайта аналогично руководству выше. Но ничего устанавливать не надо. Фактически нам необходимо далее прокинуть папки **bitrix**, **upload**, **images** из основного сайта в дополнительный. Во всех инструкциях ниже заменить **main.ru** на папку, где лежит основной сайт (ядро Битрикс).
2. Редактируем файл **docker-compose.yml** сайта (не основного, где расположено ядро). 
   1. Добавляем в секции `volumes` подобное:
      ```yaml
      # sites/домен.сайта/docker-compose.yml
      
      services:
        nginx:
          # .....
          volumes:
            # .....
            - &bitrix-volume ./../main.ru/www/bitrix:/var/main.ru/bitrix:cached
            - &upload-volume ./../main.ru/www/upload:/var/main.ru/upload:cached
            - &images-volume ./../main.ru/www/images:/var/main.ru/images:cached
        
        php-fpm:
          # .....
          volumes:
            # .....
            - *bitrix-volume
            - *upload-volume
            - *images-volume
      ```
   2. Секцию **cron** вообще удаляем или комментируем, иначе cron-задания будут запускаться дважды.
3. Создаем симлинки внутри php-fpm контейнера:
   ```bash
   # зайти в контейнер
   docker compose exec php-fpm sh
   ```
   ```bash
   # внутри контейнера создать симлинки
   ln -s /var/main.ru/bitrix/ /var/www/www/bitrix \
   && ln -s /var/main.ru/upload/ /var/www/www/upload \
   && ln -s /var/main.ru/images/ /var/www/www/images
   ```
4. Чтобы папки подключились и на хост-машине (обычно это нужно для разработки, чтобы IDE видела файлы ядра), можно аналогично создать симлинк:
   ```bash
   # /srv/bitrix-server - папка с установленным сервером из этого проекта:
   ln -s /srv/bitrix-server/sites/main.ru/www /var/main.ru
   ```
   > В MacOS последняя команда требует sudo.

## Часовой пояс

По умолчанию стоит UTC. Если нужно изменить, в .env сайта меняем переменную PHP_TIMEZONE и SERVER_TIMEZONE, например:
```apacheconf
SERVER_TIMEZONE=Europe/Moscow
PHP_TIMEZONE=Europe/Moscow
```
А в общем .env сервера указываем:
```apacheconf
SERVER_TIMEZONE=Europe/Moscow
```

Полный перечень доступных значений можно получить по ссылке: https://www.php.net/manual/en/timezones.php.

> Важно! SERVER_TIMEZONE во всех env-файлах должен совпадать.

Если по каким-то причинам требуется указывать PHP_TIMEZONE отличный от SERVER_TIMEZONE (например, разное время на разных сайтах):
1. Добавить в /bitrix/php_interface/after_connect_d7.php:
    ```php
    $connection = Bitrix\Main\Application::getConnection();
    $connection->queryExecute("SET LOCAL time_zone='".date('P')."'");
    ```
2. Добавить в /bitrix/php_interface/after_connect.php:
    ```php
    $DB->Query("SET LOCAL time_zone='".date('P')."'");
    ```

## Установка обновлений

Выполнить в папке сервера (общие сервисы) и аналогично в папке сайта:

```bash
docker compose pull \
&& docker compose up --force-recreate --remove-orphans --build -d \
&& docker image prune -f
```

## Решение проблем

### Не запускается MySQL

#### Ошибка: mysqld: Can't create/write to file '/var/lib/mysql/is_writable' (Errcode: 13 - Permission denied)

1. Перейти в папку [data/mysql](data/mysql)
2. Выполнить команду:
    ```bash
    sudo chown 999:999 .
    ```

#### Ошибка после некорректного завершения работы сервера

Иногда после некорректного завершения работы MySQL не хочет перезапускаться. В этом случае необходимо зайти в папку [data/mysql](data/mysql) и удалить файлы **mysql.sock** и **mysql.sock.lock**.

### Старый сайт с mbstring.func_overload = 2

Если нужно для определенного сайта прописать настройки mbstring (например, старая установка Битрикс), нужно создать в папке **config/php-fpm** сайта файл, например, mbstring.ini со следующим содержимым:

```editorconfig
[mbstring]
mbstring.internal_encoding = UTF-8
mbstring.func_overload = 2
```

Аналогичным образом можно добавлять любые другие настройки php для сайта.

### Ошибка на запись файлов в php-контейнере:

Перейти **в папку сайта** [sites/...](sites) и выполнить команды:

```bash
# зайти в контейнер
docker compose exec php-fpm sh
# внутри контейнера установить права на папку /var/www
chown -R www-data:www-data /var/www
chown -R www-data:www-data /var/log

# зайти в контейнер cron
docker compose exec cron sh
# внутри контейнера установить права на папку /var/www
chown -R www-data:www-data /var/www
chown -R www-data:www-data /var/log
```

### Ошибка при работе Rest API: Https required

Полный ответ сервиса в случае данной проблемы выглядит так:
```json
{
    "error": "INVALID_REQUEST",
    "error_description": "Https required."
}
```

Для решения проблемы необходимо добавить определение константы REST_APAUTH_ALLOW_HTTP в **dbconn.php**:
```php
# sites/домен.сайта/bitrix/php_interface/dbconn.php

define('REST_APAUTH_ALLOW_HTTP', true);
```

> Данное решение не понижает безопасность (если настроен https по инструкции выше).
> Дело в том, что внутри контейнеров нет смысла поднимать https, так как это занимает дополнительное время на каждый запрос.
> Поэтому прокси-сервер "общается" с контейнером php-fpm по 80 порту.

### Прохождение тестов Битрикс

#### Ошибка работы с сокетами при прохождении теста Битрикс проверки системы на сервере с SSL

Редактируем файл **docker-compose.yml** сайта.
Нужно раскомментировать 2 строки, extra_hosts и следующая за ней:
```yaml
# sites/домен.сайта/docker-compose.yml

services:
  php-fpm:
    # .....
    extra_hosts:
    - "${PROJECT_HOST}:127.0.0.1" # вместо 127.0.0.1 укажите внешний IP-адрес вашего сервера
```

#### Не проходит тест "Внутреннее перенаправление (функция LocalRedirect)"

При неуспешном прохождении данного теста при настроенном https необходимо в **dbconn.php** добавить:
```php
# sites/домен.сайта/bitrix/php_interface/dbconn.php

$_SERVER['HTTPS'] = 'on';
```
