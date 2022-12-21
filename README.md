# Bitrix in docker

## Начало работы

1. Скопировать файл [.env.example](.env.example) в файл [.env](.env) - это будет локальный конфиг.
2. Создать сайт (см. ниже раздел руководства).
3. Запустить сервер:
   ```bash
   docker-compose up -d
   ```

## Добавление новой площадки (docker-compose)

1. Скопировать папку [bitrix-distr/](bitrix-distr/) в папку [sites/](sites/).
2. Переименовать новую папку в соответствии с продуктивным адресом проекта, например: `test.example.com`. 
3. В файле .env в папке сайта заменить example.ru на имя папки нового сайта.
4. Открываем файл docker-compose.yml в новой папке и:
   1. Находим все вхождения подстроки "stub" и заменяем на имя проекта, при этом точки экранируем через дефисы, например: `test-example-com`. При этом в директивах volumes указываем с точками (так как это пути к папке на диске)
   2. Редактируем директиву Host(`${MAIN_HOST}`), указываем тут имя домена, для локального проекта, например, можно указать `example.local`.
   3. Для локального проекта редактируем файл hosts на машине, добавляем туда запись. На linux-подобных машинах для этого выполняем:
      ```bash
      sudo nano /etc/hosts
      ```
5. В файле [.env](.env) добавляем в переменную COMPOSE_FILE путь к нашему новому compose-файлу, например: `:sites/test.example.com/docker-compose.yml`.
6. Перезапустить сервер (или из папки сайта - тогда запустится только он один, или из корня этого проекта - тогда запустятся все сайты сразу):
   ```bash
   docker-compose up -d
   ```

## Как развенуть существующий сайт из бекапа Битрикс

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

## Многосайтовая конфигурация

1. Настраиваем сайт аналогично руководству выше. Но ничего устанавливать не надо. Фактически нам необходимо далее прокинуть папки **bitrix**, **upload**, **images** из основого сайта в дополнительный. Во всех инструкциях ниже заменить **main.ru** на папку, где лежит основной сайт (ядро Битрикс).
2. Добавляем в секцию `volumes` подобное:
   ```yaml
   volumes:
      - ./../sites/main.ru/www/bitrix:/var/main.ru/bitrix:cached
      - ./../sites/main.ru/www/upload:/var/main.ru/upload:cached
      - ./../sites/main.ru/www/images:/var/main.ru/images:cached
   ```
3. Cоздаем симлинки внутри контейнера:
   ```bash
   ln -s /var/main.ru/bitrix/ /var/www/www/bitrix
   ln -s /var/main.ru/upload/ /var/www/www/upload
   ln -s /var/main.ru/images/ /var/www/www/images
   ```
4. Чтобы папки подключиились и на хост-машине (обычно это нужно для разработки, чтобы IDE видела файлы ядра), можно аналогично создать симлинк:
   ```bash
   ln -s ~/Work/bitrix-docker/sites/main.ru/www/ /var/main.ru
   ```
   > В MacOS последняя команда требует sudo.
