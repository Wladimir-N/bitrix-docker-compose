<?php
if (ini_get('short_open_tag') == 0 && mb_strtoupper(ini_get('short_open_tag')) != 'ON')
	die('Error: short_open_tag parameter must be turned on in php.ini');
?><?
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

@ini_set('pcre.backtrack_limit', 1024*1024);

define('IP_LIMIT_DEFAULT', '#IP'.'_LIMIT_PLACEHOLDER#');
define('IP_LIMIT', '#IP_LIMIT_PLACEHOLDER#');
define('INIT_TIMESTAMP', '#INIT_TIMESTAMP#');

if (getenv('BITRIX_VA_VER'))
	define('VMBITRIX', 'defined');

if (version_compare(phpversion(), '7.0', '<'))
	die('Error: PHP version 7.0 or higher is required');

if(realpath(dirname(__FILE__)) != realpath($_SERVER['DOCUMENT_ROOT']))
	die('Error: this script must be started from Web Server\'s DOCUMENT ROOT');

if(isset($_SERVER["BX_PERSONAL_ROOT"]) && $_SERVER["BX_PERSONAL_ROOT"] <> "")
	define("BX_PERSONAL_ROOT", $_SERVER["BX_PERSONAL_ROOT"]);
else
	define("BX_PERSONAL_ROOT", "/bitrix");

if(!defined("START_EXEC_TIME"))
	define("START_EXEC_TIME", microtime(true));
$START_TIME = START_EXEC_TIME;

define("STEP_TIME", defined('VMBITRIX') ? 30 : 15);
# xdebug_start_trace();
define('RESTORE_FILE_LIST', $_SERVER['DOCUMENT_ROOT'].'/bitrix/tmp/restore.file_list.php');
define('RESTORE_FILE_DIR', $_SERVER['DOCUMENT_ROOT'].'/bitrix/tmp/restore.removed');
if (file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.txt') && file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.php'))
	unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.txt');
define('RESTORE_CLOUD_FILE_LIST', file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.txt') ? $_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.txt' : $_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud.php');
define('RESTORE_CLOUD_FILE_LIST_404', $_SERVER['DOCUMENT_ROOT'].'/bitrix/restore_cloud_404.php');

$strWarning = '';

if (function_exists('mb_internal_encoding'))
{
	$mb_overload_value = ini_get("mbstring.func_overload");
	switch ($mb_overload_value)
	{
		case 0:
			$bUTF_serv = false;
		break;
		case 2:
			$bUTF_serv = mb_internal_encoding() == 'UTF-8';
		break;
		default:
			die('PHP parameter mbstring.func_overload='.ini_get("mbstring.func_overload").'. The only supported values are 0 or 2.');
	}
	mb_internal_encoding('ISO-8859-1');
}
else
	$bUTF_serv = false;

if (!function_exists('htmlspecialcharsbx'))
{
	function htmlspecialcharsbx($string, $flags = ENT_COMPAT)
	{
		//function for php 5.4 where default encoding is UTF-8
		return htmlspecialchars($string, $flags, "ISO-8859-1");
	}
}

define('DEBUG', file_exists(dirname(__FILE__).'/restore.debug'));

#@set_time_limit(0);
ob_start();

$lang = 'en';
if (@preg_match('#ru#i',$_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'ru';
elseif (@preg_match('#de#i',$_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'de';
if (isset($_REQUEST['lang']))
	$lang = $_REQUEST['lang'];
if (!in_array($lang, array('ru','en','de')))
	$lang = 'en';
define("LANG", $lang);
if (!headers_sent())
	header("Content-type:text/html; charset=utf-8");

$dbconn = $_SERVER['DOCUMENT_ROOT']."/bitrix/php_interface/dbconn.php";
$main_version = $_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/classes/general/version.php";

$arc_name = $_REQUEST["arc_name"] ?? '';
if (LANG == 'ru')
{
	$MESS = array(
		"BEGIN" => "
		<p>
		<ul>
		<li>Перейдите в административную панель своего сайта на страницу <b>Настройки &gt; Инструменты &gt; Резервное копирование</b>
		<li>Создайте полную резервную копию, которая будет включать <b>публичную часть</b>, <b>ядро</b> и <b>базу данных</b>
		</ul>
		<a href='https://dev.1c-bitrix.ru/~4thxV' target='_blank'>Документация</a>
		</p>
		",
		"ARC_DOWN" => "Скачать резервную копию с другого сайта",
		"ARC_DOWN_BITRIXCLOUD" => "Развернуть резервную копию из облака &quot;1С-Битрикс&quot;",
		"BITRIXCLOUD_KEYS" => "Обновление ключей доступа к файлам в облаке",
		"LICENSE_KEY" => "Ваш лицензионный ключ:",
		"ARC_LOCAL_NAME" => "Имя архива:",
		"DB_SELECT" => "Выберите дамп БД:",
		"DB_SETTINGS" => "Параметры подключения к базе данных",
		"DB_SKIP" => "Пропустить восстановление базы",
		"SKIP" => "Пропустить",
		"DELETE_FILES" => "Удалить локальную резервную копию и служебные скрипты",
		"ARC_DOWN_URL" => "Ссылка на архив:",
		"TITLE0" => "Подготовка архива",
		"TITLE1" => "Загрузка резервной копии",
		"TITLE_PROCESS1" => "Распаковка архива",
		"FILE_IS_ENC" => "Архив зашифрован, для продолжения распаковки необходимо ввести пароль (с учетом регистра и пробелов): ",
		"WRONG_PASS" => "Введенный пароль неверен",
		"ENC_KEY" => "Пароль: ",
		"TITLE_PROCESS2" => "Выполняется восстановление базы данных",
		"TITLE2" => "Восстановление базы данных",
		"TITLE3" => "Загрузка файлов из облака",
		"ARC_SKIP" => "Архив уже распакован",
		"ARC_SKIP_DESC" => "переход к восстановлению базы данных",
		"ARC_NAME" => "Архив загружен в корневую папку сервера",
		"ARC_DOWN_PROCESS" => "Загружается:",
		"ERR_LOAD_FILE_LIST" => "Ошибочный ответ от сервиса 1С-Битрикс",
		"ARC_LOCAL" => "Загрузить с локального диска",
		"ARC_LOCAL_WARN" => "Загрузите все части многотомного архива",
		"ERR_NO_PARTS" => "Доступны не все части многотомного архива.<br>Общее число частей: ",
		"BUT_TEXT1" => "Далее",
		"BUT_TEXT_BACK" => "Назад",
		"DUMP_RETRY" => "Попробовать снова",
		"USER_NAME" => "Имя пользователя",
		"USER_PASS" => "Пароль",
		"SEARCHING_UNUSED" => "Поиск посторонних файлов в ядре...",
		"BASE_NAME" => "Имя базы данных",
		"BASE_HOST" => "Сервер баз данных",
		"BASE_RESTORE" => "Восстановить",
		"ERR_EXTRACT" => "Ошибка",
		"ERR_MSG" => "Ошибка!",
		"LICENSE_NOT_FOUND" => "Лицензионный ключ не найден",
		"SELECT_ARC" => "Выберите архив",
		"CNT_PARTS" => "частей",
		"ARC_LIST_EMPTY" => "Нет резервных копий, связанных с этим ключом",
		"ERR_UNKNOWN" => "Неизвестный ответ сервера",
		"ERR_UPLOAD" => "Не удалось загрузить файл на сервер",
		"ERR_DUMP_RESTORE" => "Ошибка восстановления базы данных",
		"ERR_CREATE_DB" => "Ошибка создания базы",
		"ERR_TAR_TAR" => "Присутствуют файлы с расширением tar.tar. Вместо них должны быть архивы с номерами: tar.1, tar.2 и т.д.",
		"FINISH" => "Операция выполнена успешно",
		"FINISH_MSG" => "Операция восстановления системы завершена.",
		"FINISH_BTN" => "Перейти на сайт",
		"BASE_CREATE_DB" => "Создать базу данных если не существует",
		"BASE_CLOUDS" => "Файлы из облачных хранилищ:",
		"BASE_CLOUDS_Y" => "сохранить локально",
		"BASE_CLOUDS_N" => "оставить в облаке",
		"FINISH_ERR_DELL" => "Не удалось удалить все временные файлы! Обязательно удалите их вручную.",
		"FINISH_ERR_DELL_TITLE" => "Ошибка удаления файлов",
		"NO_READ_PERMS" => "Нет прав на чтение корневой папки сайта",
		"UTF8_ERROR1" => "Сайт работал в кодировке UTF-8. Конфигурация сервера не соответствует требованиям.<br>Для продолжения установите настройки PHP: mbstring.func_overload=2 и mbstring.internal_encoding=UTF-8.",
		"UTF8_ERROR2" => "Сайт работал в однобайтовой кодировке, а конфигурация сервера рассчитана на кодировку UTF-8.<br>Для продолжения установите настройки PHP: mbstring.func_overload=0 или mbstring.internal_encoding=ISO-8859-1.",
		"FUNC_OVERLOAD_ERROR" => "Версия вашего продукта не поддерживает настройку PHP mbstring.func_overload, отличную от нуля. Для продолжения установите mbstring.func_overload = 0",
		"DOC_ROOT_WARN" => "Во избежание проблем с доступом был переписан путь к корню сайта в настройках сайтов. Проверьте настройки сайтов.",
		"CDN_WARN" => "Ускорение CDN было отключено т.к. текущий домен не соответствует домену из настроек CDN.",
		"HOSTS_WARN" => "Было отключено ограничение по доменам в модуле проактивной защиты т.к. текущий домен попадает под ограничения.",
		"WARN_CLEARED" => "При распаковке ядра были обнаружены файлы, которых не было в архиве. Эти файлы перенесены в /bitrix/tmp/restore.removed",
		"WARN_SITES" => "Вы распаковали многосайтовый архив, файлы дополнительных сайтов следует скопировать вручную из папки /bitrix/backup/sites",
		"WARNING" => "Внимание!",
		"DBCONN_WARN" => "Данные подключения взяты из dbconn.php. Если их не изменить, будет переписана база данных текущего сайта.",
		"HTACCESS_RENAMED_WARN" => "Файл .htaccess из архива был сохранен в корне сайта под именем .htaccess.restore, т.к. он может содержать директивы, недопустимые на данном сервере.",
		"HTACCESS_WARN" => "Файл .htaccess из архива был сохранен в корне сайта под именем .htaccess.restore, т.к. он может содержать директивы, недопустимые на данном сервере. В корне сайта создан .htaccess по умолчанию. Измените его вручную через FTP.",
		"HTACCESS_ERR_WARN" => "Файл .htaccess из архива был сохранен в корне сайта под именем .htaccess.restore, т.к. он может содержать директивы, недопустимые на данном сервере. <br> Не удалось создать корне сайта .htaccess по умолчанию. Переименуйте файл .htaccess.restore в .htaccess через FTP.",
		"ERR_CANT_DECODE" => "Невозможно восстановить архив т.к. он содержит файлы, имена которых нужно перекодировать, а модуль mbstring недоступен.",
		"ERR_CANT_DETECT_ENC" => "Невозможно восстановить архив т.к. он содержит файлы с именами в неизвестной кодировке:",
		'TAR_ERR_FILE_OPEN' => 'Не удалось открыть файл: ',
		"ARC_DOWN_OK" => "Все части архива загружены",
		"LOADER_SUBTITLE1" => "Загрузка резервной копии",
		"LOADER_SUBTITLE1_ERR" => "Ошибка загрузки",
		"LOADER_LOAD_QUERY_DISTR" => "Запрашиваю файл #DISTR#",
		"LOADER_LOAD_CONN2HOST" => "Подключение к серверу #HOST#",
		"LOADER_LOAD_NO_CONN2HOST" => "Не могу соединиться с #HOST#:",
		"LOADER_LOAD_SERVER_ANSWER" => "Ошибка загрузки. Сервер ответил: #ANS#",
		"LOADER_LOAD_SERVER_ANSWER1" => "Ошибка загрузки. У вас нет прав на доступ к этому файлу. Сервер ответил: #ANS#",
		"LOADER_LOAD_LOAD_DISTR" => "Загружаю файл #DISTR#",
		"LOADER_LOAD_ERR_RENAME" => "Не могу переименовать файл #FILE1# в файл #FILE2#",
		"ERROR_CANT_WRITE" => "Не могу записать файл #FILE#. Место на диске: #SPACE#",
		"ERROR_IP_CHANGED" => "IP адрес клиента изменился, продолжение невозможно.",
		"ERROR_INIT_TIMESTAMP" => "Время работы скрипта восстановления истекло. Загрузите новую версию.",
		"LOADER_LOAD_CANT_REDIRECT" => "Ошибочное перенаправление на адрес #URL#. Проверьте адрес для скачивания.",
		"LOADER_LOAD_CANT_OPEN_READ" => "Не могу открыть файл #FILE# на чтение",
		"LOADER_LOAD_LOADING" => "Загружаю файл, дождитесь окончания загрузки...",
		"LOADER_LOAD_FILE_SAVED" => "Файл сохранен: #FILE# [#SIZE# байт]",
		"UPDATE_SUCCESS" => "Обновлено успешно. <a href='?'>Открыть</a>.",
		"LOADER_NEW_VERSION" => "Доступна новая версия скрипта восстановления, но загрузить её не удалось",
	);
}
elseif (LANG == 'de')
{
	$MESS = array(
		"BACK" => "Zurück",
		"BEGIN" => "
		<p>
		<ul>
		<li>Öffnen Sie den Administrativen Bereich Ihrer alten Website und wählen Sie <b>Einstellungen &gt; Tools &gt; Backup</b>
		<li>Erstellen Sie ein vollständiges Archiv mit <b>öffentlichen Website-Dateien</b>, <b>Kernel-Dateien</b> und <b>Datenbank-Dump</b>
		</ul>
		<b>Dokumentation:</b> <a href='https://training.bitrix24.com/support/training/course/?COURSE_ID=12&LESSON_ID=5913&LESSON_PATH=3884.5489.5913' target='_blank'>Trainingskurs</a>
		</p>
		",
		"ARC_DOWN" => "Von Remote-Server herunterladen",
		"ARC_DOWN_BITRIXCLOUD" => "Backup aus der Bitrix Cloud wiederherstellen",
		"BITRIXCLOUD_KEYS" => "Refreshing Bitrix Cloud access keys",
		"LICENSE_KEY" => "Ihr Lizenzschlüssel:",
		"ARC_LOCAL_NAME" => "Archivname:",
		"DB_SELECT" => "Datenbank-Dump auswählen:",
		"DB_SETTINGS" => "Datenbank-Einstellungen",
		"DB_SKIP" => "Überspringen",
		"SKIP" => "Überspringen",
		"DELETE_FILES" => "Archiv und temporäre Scripts löschen",
		"ARC_DOWN_URL" => "Archiv-URL:",
		"TITLE0" => "Archiv erstellen",
		"TITLE1" => "Archiv herunterladen",
		"TITLE_PROCESS1" => "Archiv wird entpackt",
		"TITLE_PROCESS2" => "Datenbank wird wiederhergestellt...",
		"FILE_IS_ENC" => "Archiv ist verschlüsselt. Passwort eingeben: ",
		"WRONG_PASS" => "Passwort ist falsch",
		"ENC_KEY" => "Passwort: ",
		"TITLE2" => "Datenbank wiederherstellen",
		"TITLE3" => "Herunterladen von cloud-Dateien",
		"ARC_SKIP" => "Archiv wurde bereits entpackt",
		"ARC_SKIP_DESC" => "Wiederherstellung der Datenbank starten",
		"ARC_NAME" => "Archiv ist im Dokumenten-Root abgespeichert",
		"ARC_DOWN_PROCESS" => "Wird herunterladen:",
		"ERR_LOAD_FILE_LIST" => "Falsche Antwort vom Bitrixsoft Server",
		"ARC_LOCAL" => "Vom lokalen Speicher hochladen",
		"ARC_LOCAL_WARN" => "Vergessen Sie nicht, alle Teile eines mehrbändigen Archivs hochzuladen.",
		"ERR_NO_PARTS" => "Einige Teile des mehrbändigen Archivs fehlen.<br>Teile gesamt: ",
		"BUT_TEXT1" => "Fortfahren",
		"BUT_TEXT_BACK" => "Zurück",
		"DUMP_RETRY" => "Wiederholen",
		"USER_NAME" => "Name des datenbank-Nutzers",
		"USER_PASS" => "Passwort",
		"BASE_NAME" => "Datenbankname",
		"SEARCHING_UNUSED" => "Ungenutzte Kernel-Dateien werden gesucht...",
		"BASE_HOST" => "Datenbank-Host",
		"BASE_RESTORE" => "Wiederherstellen",
		"ERR_EXTRACT" => "Fehler",
		"ERR_MSG" => "Fehler!",
		"LICENSE_NOT_FOUND" => "Lizenz wurde nicht gefunden",
		"SELECT_ARC" => "Backup auswählen",
		"CNT_PARTS" => "Teile",
		"ARC_LIST_EMPTY" => "Backup-Liste ist leer für den aktuellen Lizenzschlüssel",
		"ERR_UNKNOWN" => "Unbekannte Server-Antwort",
		"ERR_UPLOAD" => "Datei kann nicht hochgeladen werden",
		"ERR_DUMP_RESTORE" => "Fehler bei Wiederherstellung der Datenbank:",
		"ERR_CREATE_DB" => "Fehler bei Erstellung der Datenbank",
		"ERR_TAR_TAR" => "Es gibt Dateien mit Erweiterung tar.tar. Es müssten tar.1, tar.2 und so weiter sein",
		"FINISH" => "Erfolgreich abgeschlossen",
		"FINISH_MSG" => "Wiederherstellung des Systems wurde abgeschlossen.",
		"FINISH_BTN" => "Website öffnen",
		"BASE_CREATE_DB" => "Datenbank erstellen",
		"BASE_CLOUDS" => "Cloud-Dateien:",
		"BASE_CLOUDS_Y" => "lokal speichern",
		"BASE_CLOUDS_N" => "in der Cloud lassen",
		"FINISH_ERR_DELL" => "Löschen von temporären Dateien ist fehlgeschlagen. Sie sollten diese manuell löschen",
		"FINISH_ERR_DELL_TITLE" => "Fehler beim Löschen von dateien",
		"NO_READ_PERMS" => "Sie haben nicht genügend Rechte, um Web-Server Root zu lesen",
		"UTF8_ERROR1" => "Ihr Server ist für die Codierung UTF-8 nicht konfiguriert. Definieren Sie bitte mbstring.func_overload=2 und mbstring.internal_encoding=UTF-8 um fortzufahren.",
		"UTF8_ERROR2" => "Ihr Server ist für die Codierung UTF-8 konfiguriert. Definieren Sie bitte mbstring.func_overload=0 oder mbstring.internal_encoding=ISO-8859-1 um fortzufahren.",
		"FUNC_OVERLOAD_ERROR" => "Ihre Produktversion unterstützt keine andere PHP-Einstellung mbstring.func_overload als Null. Um fortzufahren, setzen Sie mbstring.func_overload = 0",
		"DOC_ROOT_WARN" => "Um Probleme mit Zugriffsrechten zu vermeiden, wurde das Dokumenten-Root in den Einstellungen der Website aufgeräumt.",
		"CDN_WARN" => "CDN Web-Accelerator wurde deaktiviert, weil die aktuelle Domain sich von der unterscheidet, die in den CDN-Einstellungen gespeichrt ist.",
		"HOSTS_WARN" => "Domain-Einschränkung wurde deaktiviert (Sicherheitsmodul), weil die aktuelle Domain den Einstellungen nicht entspricht.",
		"WARN_CLEARED" => "Einige Dateien wurden in /bitrix gefunden, sie sind in der Backup nicht enthalten. Sie wurden nach /bitrix/tmp/restore.removed verschoben",
		"WARN_SITES" => "Sie haben das Multisite-Archiv entpackt, kopieren Sie bitte die Dateien zusätzlicher Websites von /bitrix/backup/sites in einen entsprechenden Ort",
		"WARNING" => "Warnung!",
		"DBCONN_WARN" => "Die Verbindungseinstellungen werden von dbconn.php gelesen. Wenn Sie sie nicht ändern, wird aktuelle Datenbank überschrieben.",
		"HTACCESS_RENAMED_WARN" => "Die Datei .htaccess wurde unter .htaccess.restore gespeichert, weil sie Anweisungen enthalten kann, die auf diesem Server nicht erlaubt sind.",
		"HTACCESS_WARN" => "Die Datei .htaccess wurde unter .htaccess.restore gespeichert, weil sie die Anweisungen enthalten kann, die auf diesem Server nicht erlaubt sind. Standarddatei .htaccess wurde im Dokumenten-Root erstellt. Sie sollten sie manuell via FTP aktualisieren.",
		"HTACCESS_ERR_WARN" => "Die Datei .htaccess wurde unter .htaccess.restore gespeichert, weil sie Anweisungen enthalten kann, die auf diesem Server nicht erlaubt sind. Es gab einen Fehler beim Erstellen der Standarddatei .htaccess. Sie sollten .htaccess.restore in .htaccess via FTP umbenennen.",
		"ERR_CANT_DECODE" => "Unmöglich fortzufahren, weil das Modul MBString nicht verfügbar ist.",
		"ERR_CANT_DETECT_ENC" => "Unmöglich fortzufahren wegen eines Fehlers in der Erkennung der Codierung des Dateinamen: ",
		'TAR_ERR_FILE_OPEN' => 'Datei kann nicht geöffnet werden: ',
		"ARC_DOWN_OK" => "Alle Archivteile wurden heruntergeladen",
		"LOADER_SUBTITLE1" => "Wird geladen",
		"LOADER_SUBTITLE1_ERR" => "Fehler beim Laden",
		"LOADER_LOAD_QUERY_DISTR" => "Paket #DISTR# wird angefragt",
		"LOADER_LOAD_CONN2HOST" => "Verbindung mit #HOST#",
		"LOADER_LOAD_NO_CONN2HOST" => "Keine Verbindung mit #HOST#:",
		"LOADER_LOAD_SERVER_ANSWER" => "Fehler beim Herunterladen. Die Antwort vom Server war: #ANS#",
		"LOADER_LOAD_SERVER_ANSWER1" => "Fehler beim Herunterladen. Sie können dieses Paket nicht herunterladen. Die Antwort vom Server war: #ANS#",
		"LOADER_LOAD_LOAD_DISTR" => "Das Paket #DISTR# wird heruntergeladen",
		"LOADER_LOAD_ERR_RENAME" => "Die Datei #FILE1# kann nicht in #FILE2# umbenannt werden",
		"ERROR_CANT_WRITE" => "In der Datei #FILE# kann nicht geschrieben werden. Freier Speicherplatz: #SPACE#",
		"ERROR_IP_CHANGED" => "IP address has changed. Permission denied.",
		"ERROR_INIT_TIMESTAMP" => "This script is outdated. Please upload the new version.",
		"LOADER_LOAD_CANT_REDIRECT" => "Inkorrekte Weiterleitung an #URL#. Überprüfen Sie die Download-URL.",
		"LOADER_LOAD_CANT_OPEN_READ" => "Die Datei #FILE# kann nicht zum Lesen geöffnet werden",
		"LOADER_LOAD_LOADING" => "Es wird nun heruntergeladen. Bitte warten...",
		"LOADER_LOAD_FILE_SAVED" => "Datei gespeichert: #FILE# [#SIZE# bytes]",
		"UPDATE_SUCCESS" => "Aktualisierung war erfolgreich. <a href='?'>Öffnen</a>.",
		"LOADER_NEW_VERSION" => "Beim Aktualisieren des Scripts restore.php ist ein Fehler aufgetreten.",
	);
}
else
{
	$MESS = array(
		"BEGIN" => "
		<p>
		<ul>
		<li>Open Control Panel section of your old site and select <b>Settings &gt; Tools &gt; Backup</b>
		<li>Create full archive which contains <b>public site files</b>, <b>kernel files</b> and <b>database dump</b>
		</ul>
		<b>Documentation:</b> <a href='https://training.bitrix24.com/support/training/course/?COURSE_ID=12&LESSON_ID=5913&LESSON_PATH=3884.5489.5913' target='_blank'>learning course</a>
		</p>
		",
		"ARC_DOWN" => "Download from remote server",
		"ARC_DOWN_BITRIXCLOUD" => "Restore the backup from the Bitrix Cloud",
		"BITRIXCLOUD_KEYS" => "Refreshing Bitrix Cloud access keys",
		"LICENSE_KEY" => "Your license key:",
		"ARC_LOCAL_NAME" => "Archive name:",
		"DB_SELECT" => "Select Database Dump:",
		"DB_SETTINGS" => "Database settings",
		"DB_SKIP" => "Skip",
		"SKIP" => "Skip",
		"DELETE_FILES" => "Delete archive and temporary scripts",
		"ARC_DOWN_URL" => "Archive URL:",
		"TITLE0" => "Archive Creation",
		"TITLE1" => "Archive download",
		"TITLE_PROCESS1" => "Extracting an archive",
		"TITLE_PROCESS2" => "Restoring database...",
		"FILE_IS_ENC" => "Archive is encrypted. Enter password: ",
		"WRONG_PASS" => "Wrong password",
		"ENC_KEY" => "Password: ",
		"TITLE2" => "Database restore",
		"TITLE3" => "Downloading cloud files",
		"ARC_SKIP" => "Archive is already extracted",
		"ARC_SKIP_DESC" => "Starting database restore",
		"ARC_NAME" => "Archive is stored in document root folder",
		"ARC_DOWN_PROCESS" => "Downloading:",
		"ERR_LOAD_FILE_LIST" => "Wrong Bitrixsoft server response",
		"ARC_LOCAL" => "Upload from local disk",
		"ARC_LOCAL_WARN" => "Don't forget to upload all the parts of multi-volume archive.",
		"ERR_NO_PARTS" => "Some parts of the multivolume archive are missed.<br>Total number of parts: ",
		"BUT_TEXT1" => "Continue",
		"BUT_TEXT_BACK" => "Back",
		"DUMP_RETRY" => "Retry",
		"USER_NAME" => "Database User Name",
		"USER_PASS" => "Password",
		"BASE_NAME" => "Database Name",
		"SEARCHING_UNUSED" => "Searching for unused kernel files...",
		"BASE_HOST" => "Database Host",
		"BASE_RESTORE" => "Restore",
		"ERR_EXTRACT" => "Error",
		"ERR_MSG" => "Error!",
		"LICENSE_NOT_FOUND" => "License not found",
		"SELECT_ARC" => "Select backup",
		"CNT_PARTS" => "parts",
		"ARC_LIST_EMPTY" => "Backup list is empty for current license key",
		"ERR_UNKNOWN" => "Unknown server response",
		"ERR_UPLOAD" => "Unable to upload file",
		"ERR_DUMP_RESTORE" => "Error restoring the database:",
		"ERR_CREATE_DB" => "Error creating the database",
		"ERR_TAR_TAR" => "There are files with tar.tar extension presents. Should be tar.1, tar.2 and so on",
		"FINISH" => "Successfully completed",
		"FINISH_MSG" => "Restoring of the system was completed.",
		"FINISH_BTN" => "Open site",
		"BASE_CREATE_DB" => "Create database",
		"BASE_CLOUDS" => "Cloud files:",
		"BASE_CLOUDS_Y" => "store locally",
		"BASE_CLOUDS_N" => "leave in the cloud",
		"FINISH_ERR_DELL" => "Failed to delete temporary files! You should delete them manually",
		"FINISH_ERR_DELL_TITLE" => "Error deleting the files",
		"NO_READ_PERMS" => "No permissions for reading Web Server root",
		"UTF8_ERROR1" => "Your server is not configured for UTF-8 encoding. Please set mbstring.func_overload=2 and mbstring.internal_encoding=UTF-8 to continue.",
		"UTF8_ERROR2" => "Your server is configured for UTF-8 encoding. Please set mbstring.func_overload=0 or mbstring.internal_encoding=ISO-8859-1 to continue.",
		"FUNC_OVERLOAD_ERROR" => "Invalid PHP value for mbstring.func_overload. Please set mbstring.func_overload = 0 to continue installation",
		"DOC_ROOT_WARN" => "To prevent access problems the document root has been cleared in the site settings.",
		"CDN_WARN" => "CDN Web Accelerator has been disabled because current domain differs from the one stored in CDN settings.",
		"HOSTS_WARN" => "Domain restriction has beed disabled (security module) because current domain doesn't correspond settings.",
		"WARN_CLEARED" => "Some files were found in /bitrix which don't present in the backup. They were moved to /bitrix/tmp/restore.removed",
		"WARN_SITES" => "You have extracted the multisite archive, please copy files of additional sites from /bitrix/backup/sites to an appropriate place",
		"WARNING" => "Warning!",
		"DBCONN_WARN" => "The connection settings are read from dbconn.php. If you don't change them, current database will be overwriten.",
		"HTACCESS_RENAMED_WARN" => "The file .htaccess was saved as .htaccess.restore, because it may contain directives which are not permitted on this server.",
		"HTACCESS_WARN" => "The file .htaccess was saved as .htaccess.restore, because it may contain directives which are not permitted on this server. Default .htaccess file has been created at the document root. Please modify it manually using FTP.",
		"HTACCESS_ERR_WARN" => "The file .htaccess was saved as .htaccess.restore, because it may contain directives which are not permitted on this server. There was an error in creating default .htaccess file. Please rename .htaccess.restore to .htaccess using FTP.",
		"ERR_CANT_DECODE" => "Unable to continue because the module MBString is not available.",
		"ERR_CANT_DETECT_ENC" => "Unable to continue due to error in encoding detection of file name: ",
		'TAR_ERR_FILE_OPEN' => 'Can\'t open file: ',
		"ARC_DOWN_OK" => "All archive parts have been downloaded",
		"LOADER_SUBTITLE1" => "Loading",
		"LOADER_SUBTITLE1_ERR" => "Loading Error",
		"LOADER_MENU_UNPACK" => "Unpack file",
		"LOADER_LOAD_QUERY_DISTR" => "Requesting package #DISTR#",
		"LOADER_LOAD_CONN2HOST" => "Connection to #HOST#...",
		"LOADER_LOAD_NO_CONN2HOST" => "Cannot connect to #HOST#:",
		"LOADER_LOAD_SERVER_ANSWER" => "Error while downloading. Server reply was: #ANS#",
		"LOADER_LOAD_SERVER_ANSWER1" => "Error while downloading. Your can not download this package. Server reply was: #ANS#",
		"LOADER_LOAD_LOAD_DISTR" => "Downloading package #DISTR#",
		"LOADER_LOAD_ERR_RENAME" => "Cannot rename file #FILE1# to #FILE2#",
		"ERROR_CANT_WRITE" => "Cannot write to file #FILE#. Free disk space: #SPACE#",
		"ERROR_IP_CHANGED" => "IP address has changed. Permission denied.",
		"ERROR_INIT_TIMESTAMP" => "This script is outdated. Please upload the new version.",
		"LOADER_LOAD_CANT_REDIRECT" => "Wrong redirect to #URL#. Check download url.",
		"LOADER_LOAD_CANT_OPEN_READ" => "Cannot open file #FILE# for reading",
		"LOADER_LOAD_LOADING" => "Download in progress. Please wait...",
		"LOADER_LOAD_FILE_SAVED" => "File saved: #FILE# [#SIZE# bytes]",
		"UPDATE_SUCCESS" => "Successful update. <a href='?'>Open</a>.",
		"LOADER_NEW_VERSION" => "Error occured while updating restore.php script!",
	);
}

$bClearUnusedStep = (bool)($_REQUEST['clear'] ?? false);
$bSelectDumpStep = (($_REQUEST['source'] ?? '') == 'dump');
$bCloudDownloadStep = ($_REQUEST['cloud_download'] ?? '');

$Step = intval($_REQUEST["Step"] ?? 0);

$strErrMsg = '';
if (!DEBUG && !$Step && $_SERVER['REQUEST_METHOD'] == 'GET')
{
	$this_script_name = basename(__FILE__);
	$bx_host = 'www.1c-bitrix.ru';
	$bx_url = '/download/files/scripts/'.$this_script_name;
	$form = '';

	// Check for updates
	$res = fsockopen('ssl://'.$bx_host, 443, $errno, $errstr, 3);

	if($res)
	{
		$strRequest = "HEAD ".$bx_url." HTTP/1.1\r\n";
		$strRequest.= "Host: ".$bx_host."\r\n";
		$strRequest.= "\r\n";

		fputs($res, $strRequest);

		while ($line = fgets($res, 4096))
		{
			if (preg_match("/Content-Length: *([0-9]+)/i", $line, $regs))
			{
				if (filesize(__FILE__) != trim($regs[1]))
				{
					$tmp_name = $this_script_name.'.tmp';
					if (LoadFile('https://'.$bx_host.$bx_url, $tmp_name))
					{
						if (rename($_SERVER['DOCUMENT_ROOT'].'/'.$tmp_name,__FILE__))
						{
							bx_accelerator_reset();
							echo '<script>document.location="?lang='.LANG.'";</script>'.getMsg('UPDATE_SUCCESS');
							die();
						}
						else
							$strErrMsg = getMsg("ERROR_CANT_WRITE", ["#FILE#" => $this_script_name, '#SPACE#' => freeSpace()]);
					}
					else
						$strErrMsg = getMsg('LOADER_NEW_VERSION');
				}
				break;
			}
		}
		fclose($res);
	}
}

@ini_set('pcre.backtrack_limit', 1024*1024);

if (!DEBUG)
{
	if (IP_LIMIT == IP_LIMIT_DEFAULT)
	{
		($str = file_get_contents(__FILE__)) || die(getMsg("LOADER_LOAD_CANT_OPEN_READ", ["#FILE#" => __FILE__]));
		$size = mb_strlen($str);
		$str = str_replace(IP_LIMIT, $_SERVER['REMOTE_ADDR'], $str);
		$str = str_replace(INIT_TIMESTAMP, time(), $str);
		$str = str_pad($str, $size, ' ');
		file_put_contents(__FILE__, $str) || die(getMsg("ERROR_CANT_WRITE", ["#FILE#" => __FILE__, '#SPACE#' => freeSpace()]));
		bx_accelerator_reset();
		header('Location: '.$_SERVER['REQUEST_URI']);
		die();
	}
	elseif ($_SERVER['REMOTE_ADDR'] != IP_LIMIT)
		$strErrMsg = getMsg('ERROR_IP_CHANGED');
	elseif (time() - INIT_TIMESTAMP > 86400*7)
		$strErrMsg = getMsg('ERROR_INIT_TIMESTAMP');

	if ($strErrMsg)
	{
		echo '<div style="color:red">'.getMsg('ERR_MSG').' '.$strErrMsg.'</div>';
		die();
	}
}

if (isset($_REQUEST['LoadFileList']) && $_REQUEST['LoadFileList'])
{
	$strLog = '';
	if (LoadFile("https://www.1c-bitrix.ru/buy_tmp/backup.php?license=".md5(trim($_REQUEST['license_key']))."&lang=".LANG."&action=get_info", $file = $_SERVER['DOCUMENT_ROOT'].'/file_list.xml') && ($str = file_get_contents($file)))
	{
		if (preg_match_all('/<file name="([^"]+)" size="([^"]+)".*?\\/>/', $str, $regs))
		{
			$arFiles = array();
			$arParts = array();
			foreach($regs[0] as $i => $wholeMatch)
			{
				$name = CTar::getFirstName($regs[1][$i]);
				$arFiles[$name] += $regs[2][$i];
				$arParts[$name]++;
			}
			krsort($arFiles);

			echo getMsg('SELECT_ARC').':&nbsp;<select name="bitrixcloud_backup">';
			foreach($arFiles as $name => $size)
				echo '<option value="'.htmlspecialcharsbx($name).'" '.(($_REQUEST['bitrixcloud_backup'] ?? '') == $name ? 'selected' : '').'>'.htmlspecialcharsbx($name).' ('.floor($size/1024/1024), ' Mb'.($arParts[$name] > 1 ? ', '.getMsg('CNT_PARTS').': '.$arParts[$name] : '').')</option>';
			echo '</select><br>';
			echo getMsg('ENC_KEY').'&nbsp;<input type="password" size=30 name="EncryptKey" autocomplete="off">';
		}
		else
		{
			if (mb_strpos($str,'<files>') !== false) // valid answer
				$strErrMsg = getMsg('ARC_LIST_EMPTY');
			elseif (preg_match('#error#i',$str))
			{
				$code = strip_tags($str);
				if ($code == 'LICENSE_NOT_FOUND')
					$strErrMsg = getMsg('LICENSE_NOT_FOUND');
				else
					$strErrMsg = $code;
			}
			else
				$strErrMsg = getMsg('ERR_UNKNOWN');
			echo '<div style="color:red">'.getMsg('ERR_MSG').' '.$strErrMsg.'</div>';
		}
		bx_unlink($file);
	}
	else
		echo '<div style="color:red">'.getMsg('ERR_LOAD_FILE_LIST').'</div><div style="text-align:left;color:#CCC">'.nl2br($strLog).'</div>';
	die();
}
elseif ($Step == 2 && !$bSelectDumpStep)
{
	if (isset($_REQUEST['arHeaders']) && is_array($_REQUEST['arHeaders']))
		$arHeaders = $_REQUEST['arHeaders'];
	else
		$arHeaders = array();

	$source = $_REQUEST['source'];
	if ($source == 'bitrixcloud' && !$_REQUEST['arc_down_url'])
	{
		$strLog = '';
		if (LoadFile('https://www.1c-bitrix.ru/buy_tmp/backup.php?license='.md5(trim($_REQUEST['license_key'])).'&lang='.LANG.'&action=read_file&file_name='.urlencode($_REQUEST['bitrixcloud_backup']).'&check_word='.CTar::getCheckword($_REQUEST['EncryptKey']), $file = $_SERVER['DOCUMENT_ROOT'].'/file_info.xml') && ($str = file_get_contents($file)))
		{
			bx_unlink($file);

			$host = preg_match('#<host>([^<]+)</host>#i',$str,$regs) ? $regs[1] : false;
			$path = preg_match('#<path>([^<]+)</path>#i',$str,$regs) ? $regs[1] : false;

			if (preg_match_all('/<header name="([^"]+)" value="([^"]+)".*?\\/>/', $str, $regs))
			{
				foreach($regs[0] as $i => $wholeMatch)
					$arHeaders[$regs[1][$i]] = $regs[2][$i];
			}

			if ($host && $path)
			{
				$_REQUEST['arc_down_url'] = $host.$path;
			}
			elseif (mb_strpos($str,'WRONG_FILE_NAME_OR_CHECKWORD') !== false)
				$strErrMsg = '<div style="color:red">'.getMsg('WRONG_PASS').'</div>';
			else
				$strErrMsg = '<div style="color:red">'.getMsg('ERR_LOAD_FILE_LIST').'</div>';
		}
		else
			$strErrMsg = '<div style="color:red">'.getMsg('ERR_LOAD_FILE_LIST').'</div><div style="text-align:left;color:#CCC">'.nl2br($strLog).'</div>';

		if (!$_REQUEST['try_next'] && $strErrMsg)
		{
			$text = $strErrMsg.
			getMsg('ENC_KEY').'<input type="password" size=30 name="EncryptKey" autocomplete="off" value="">'.
			'<input type="hidden" name="license_key" value="'.htmlspecialcharsbx($_REQUEST['license_key']).'">'.
			'<input type="hidden" name="source" value="bitrixcloud">'.
			'<input type="hidden" name="bitrixcloud_backup" value="'.htmlspecialcharsbx($_REQUEST['bitrixcloud_backup']).'">'.
			'<input type="hidden" name="Step" value="2">';
			$bottom .= '
			<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a>
			<input type="button" id="start_button" value="'.getMsg("BUT_TEXT1").'" onClick="reloadPage(2)">';
			showMsg(getMsg('TITLE1'),$text,$bottom);
			die();
		}
	}

	if ($source == 'download' || $source == 'bitrixcloud')
	{
		$strUrl = $_REQUEST['arc_down_url'];
		$res = false;
		if ($strUrl)
		{
			if (!preg_match('#https?://#',$strUrl))
				$strUrl = 'http://'.$strUrl;
			$arc_name = trim(basename($strUrl));

			if ($arc_name == CTar::getFirstName($arc_name))
			{
				$full = false;
				$tar = new CTar();
				$n = CTar::getLastNum($_SERVER['DOCUMENT_ROOT'].'/'.$arc_name);
				$arc_name1 = $arc_name;
				while(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$arc_name1))
				{
					if ($n && $arc_name1 == $arc_name.'.'.$n)
					{
						$full = true;
						break;
					}
					$arc_name1 = $tar->getNextName($arc_name1);
				}

				if (!$full)
				{
					$strUrl = str_replace($arc_name, $arc_name1, $strUrl);
					$_REQUEST['bitrixcloud_backup'] = $arc_name = $arc_name1;
				}
			}

			$strLog = '';
			$status = '';

			if ($_REQUEST['continue'])
			{
				$res = LoadFile($strUrl, $_SERVER['DOCUMENT_ROOT'].'/'.$arc_name, $arHeaders);
				if (file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/'.$arc_name))
				{
					if ($res == 1)
					{
						$f = fopen($_SERVER['DOCUMENT_ROOT'].'/'.$arc_name, 'rb');
						$id = fread($f, 2);
						fclose($f);

						if ($id != chr(31).chr(139)) // not gzip
						{
							$s = filesize($_SERVER['DOCUMENT_ROOT'].'/'.$arc_name);
							if ($s == 0 || $s%512 > 0) // not tar
							{
								bx_unlink($_SERVER['DOCUMENT_ROOT'].'/'.$arc_name);
								$res = false;
							}
						}
					}
				}
			}
			else
			{
				$res = 2;
				SetCurrentProgress(0);
			}
		}

		if ($res)
		{
			$text = getMsg('ARC_DOWN_PROCESS').' <b>'.htmlspecialcharsbx($arc_name).'</b>' . $status .
			'<input type=hidden name=Step value=2>'.
			'<input type=hidden name=continue value=Y>'.
			'<input type=hidden name="EncryptKey" value="'.htmlspecialcharsbx($_REQUEST['EncryptKey']).'">'.
			'<input type=hidden name="license_key" value="'.htmlspecialcharsbx($_REQUEST['license_key']).'">';

			if ($res == 2)
			{
				$text .= '<input type=hidden name=arc_down_url value="'.htmlspecialcharsbx($strUrl).'">';
				$text .= '<input type=hidden name=source value=download>';
				$text .= '<input type=hidden name="bitrixcloud_backup" value="'.htmlspecialcharsbx($_REQUEST['bitrixcloud_backup']).'">';
			}
			else
			{
				$tar = new CTar();
				$text .= '<input type=hidden name=try_next value=Y>';
				if (count($arHeaders)) // bitrixcloud
				{
					$text .= '<input type=hidden name=source value=bitrixcloud>';
					$text .= '<input type=hidden name="bitrixcloud_backup" value="'.htmlspecialcharsbx($tar->getNextName($_REQUEST['bitrixcloud_backup'])).'">';
				}
				else
				{
					$text .= '<input type=hidden name=source value=download>';
					$text .= '<input type=hidden name=arc_down_url value="'.htmlspecialcharsbx($tar->getNextName($strUrl)).'">';
				}
			}
			if (count($arHeaders))
			{
				foreach($arHeaders as $k=>$v)
					$text .= '<input type=hidden name="arHeaders['.htmlspecialcharsbx($k).']" value="'.htmlspecialcharsbx($v).'">';
			}
		}
		elseif ($_REQUEST['try_next'])
		{
			$text = getMsg('ARC_DOWN_OK').
			'<input type=hidden name=Step value=2>'.
			'<input type=hidden name="EncryptKey" value="'.htmlspecialcharsbx($_REQUEST['EncryptKey']).'">';

			if ($_REQUEST['bitrixcloud_backup'])
				$text .= '<input type=hidden name=arc_name value="'.htmlspecialcharsbx(CTar::getFirstName($_REQUEST['bitrixcloud_backup'])).'">';
			else
				$text .= '<input type=hidden name=arc_name value="'.htmlspecialcharsbx(CTar::getFirstName($arc_name)).'">';
		}
		else
		{
			if ($_REQUEST['bitrixcloud_backup'] && $replycode == 403 && count($arHeaders) && (!$_REQUEST['timestamp'] || time() - $_REQUEST['timestamp'] > 10)) // Retry for bitrixcloud
			{
				$status = '<div>'.getMsg('BITRIXCLOUD_KEYS').'</div>';
				$text = getMsg('ARC_DOWN_PROCESS').' <b>'.htmlspecialcharsbx($arc_name).'</b>' . $status .
					'<input type=hidden name=Step value=2>'.
					'<input type=hidden name=timestamp value='.time().'>'.
					'<input type=hidden name=continue value=Y>'.
					'<input type=hidden name="EncryptKey" value="'.htmlspecialcharsbx($_REQUEST['EncryptKey']).'">'.
					'<input type=hidden name="license_key" value="'.htmlspecialcharsbx($_REQUEST['license_key']).'">';
				$text .= '<input type=hidden name=source value=bitrixcloud>';
				$text .= '<input type=hidden name="bitrixcloud_backup" value="'.htmlspecialcharsbx($_REQUEST['bitrixcloud_backup']).'">';
			}
			else
			{
				$ar = array(
					'TITLE' => getMsg('LOADER_SUBTITLE1_ERR'),
					'TEXT' => nl2br($strLog),
					'BOTTOM' => '<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a>'
				);
				html($ar);
				die();
			}
		}
		$bottom = '<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a>';
		showMsg(getMsg('LOADER_SUBTITLE1'),$text,$bottom);
		?><script>reloadPage(2, 1);</script><?
		die();
	}
	elseif($source == 'upload')
	{
		if (!count($_FILES['archive']['tmp_name']))
		{
			$ar = array(
				'TITLE' => getMsg('ERR_EXTRACT'),
				'TEXT' => getMsg('ERR_UPLOAD'),
				'BOTTOM' => '<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a>'
			);
			html($ar);
			die();
		}

		foreach($_FILES['archive']['tmp_name'] as $k => $v)
		{
			if (!$v)
				continue;
			$arc_name = $_FILES['archive']['name'][$k];
			if (!@move_uploaded_file($v, $_SERVER['DOCUMENT_ROOT'].'/'.$arc_name))
			{
				$ar = array(
					'TITLE' => getMsg('ERR_EXTRACT'),
					'TEXT' => getMsg('ERR_UPLOAD'),
					'BOTTOM' => '<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a>'
				);
				html($ar);
				die();
			}
		}
		$text =
		'<input type=hidden name=Step value=2>'.
		'<input type=hidden name=arc_name value="'.htmlspecialcharsbx(CTar::getFirstName($arc_name)).'">';
		showMsg(getMsg('LOADER_SUBTITLE1'),$text);
		?><script>reloadPage(2, 1);</script><?
		die();
	}
}
elseif($Step == 3)
{
	$d_pos = (double) $_REQUEST["d_pos"];
	if ($d_pos < 0)
		$d_pos = 0;

	$oDB = new CDBRestore($_REQUEST["DBHost"], $_REQUEST["DBName"], $_REQUEST["DBLogin"], $_REQUEST["DBPassword"], $_REQUEST["dump_name"], $d_pos);
	$oDB->LocalCloud = $_REQUEST['LocalCloud'];

	if(!$oDB->Connect())
	{
		$strErrMsg = $oDB->getError();
		$Step = 2;
		$bSelectDumpStep = true;
	}
}




##################################### GUI #################################
if(!$Step)
{
	$ar = array(
		'TITLE' => getMsg("TITLE0"),
		'TEXT' =>
			($strErrMsg ? '<div style="color:red;padding:10px;border:1px solid red">'.$strErrMsg.'</div>' : '').
			getMsg('BEGIN'),
		'BOTTOM' =>
		(defined('VMBITRIX') ? '<a href="/?lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> ' : '').
		'<input type="button" value="'.getMsg("BUT_TEXT1").'" onClick="reloadPage(1)">'
	);
	html($ar);
}
elseif($Step == 1)
{
	$arc_down_url = $_REQUEST['arc_down_url'] ?? '';
	$local_arc_name = htmlspecialcharsbx(ltrim(($_REQUEST['local_arc_name'] ?? ''),'/'));
	$license_key = '';
	if (isset($_REQUEST['bitrixcloud_backup']) && $_REQUEST['bitrixcloud_backup'])
	{
		@include($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php');
		$license_key = $LICENSE_KEY;
	}

	$option = getArcList();
	$ar = array(
		'TITLE' => getMsg("TITLE1"),
		'TEXT' =>
				$local_arc_name
				?
				'<div class=t_div><input type=hidden name=arc_name value="'.$local_arc_name.'"> '.getMsg("ARC_LOCAL_NAME").' <b>'.$local_arc_name.'</div>'
				:
				($strErrMsg ? '<div style="color:red">'.$strErrMsg.'</div>' : '').
				'<input type="hidden" name="Step" value="2">'.
				'<div class=t_div>
					<label><input type=radio name=x_source onclick="div_show(0)" '.(isset($_REQUEST['bitrixcloud_backup']) && $_REQUEST['bitrixcloud_backup'] ? 'checked' : '').'>'.getMsg("ARC_DOWN_BITRIXCLOUD").'</label>
					<div id=div0 class="div-tool" style="display:none">
						<nobr>'.getMsg("LICENSE_KEY").'</nobr> <input name=license_key type="text" id=license_key size=30 value="'.htmlspecialcharsbx($license_key).'"> <input type="button" value=" OK " onclick="LoadFileList()"><br>
						<div id=file_list></div>
					</div>
				</div>
				<div class=t_div>
					<label><input type=radio name=x_source onclick="div_show(1)" '.(isset($_REQUEST['arc_down_url']) && $_REQUEST['arc_down_url'] ? 'checked' : '').'>'.getMsg("ARC_DOWN").'</label>
					<div id=div1 class="div-tool" style="display:none"><nobr>'.getMsg("ARC_DOWN_URL").'</nobr> <input name=arc_down_url type="text" size=40 value="'.htmlspecialcharsbx($arc_down_url).'"></div>
				</div>
				<div class=t_div>
					<label><input type=radio name=x_source onclick="div_show(2)">'. getMsg("ARC_LOCAL").'</label>
					<div id=div2 class="div-tool" style="display:none"><span style="color:#666">'.getMsg("ARC_LOCAL_WARN").'</span><br/><input type=file name="archive[]" size=40 multiple onchange="addFileField()"></div>
				</div>
				'
				.(mb_strlen($option) ?
				'<div class=t_div>
					<label><input type=radio name=x_source onclick="div_show(3)">'.getMsg("ARC_NAME").'</label>
					<div id=div3 class="div-tool" style="display:none">
						<select name="arc_name">'.$option.'</select>
					</div>'.
				'</div>'
				: '')
				.($option === false ? '<div style="color:red">'.getMsg('NO_READ_PERMS').'</div>' : '')
				.(count(getDumpList()) ?
				'<div class=t_div>'.
					'<label><input type=radio name=x_source onclick="div_show(4)">'.getMsg("ARC_SKIP").'</label>
					<div id=div4 class="div-tool" style="display:none;color:#999999">'.getMsg('ARC_SKIP_DESC').'</div>
				</div>' : '')
				,
		'BOTTOM' =>
		'<a href="/restore.php?Step=0&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> '.
		'<input type="button" id="start_button" value="'.getMsg("BUT_TEXT1").'" onClick="reloadPage(2)" '.($local_arc_name ? '' : 'disabled').'>'
	);
	html($ar);
}
elseif($Step == 2)
{
	if(!$bSelectDumpStep && !$bClearUnusedStep && !$bCloudDownloadStep)
	{
		$tar = new CTarRestore;
		$tar->path = $_SERVER['DOCUMENT_ROOT'];
		$tar->ReadBlockCurrent = intval($_REQUEST['ReadBlockCurrent'] ?? 0);
		$tar->EncryptKey = $_REQUEST['EncryptKey'] ?? '';

		$bottom = '<a href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> ';

		if ($rs = $tar->openRead($file1 = $file = $_SERVER['DOCUMENT_ROOT'].'/'.$arc_name))
		{
			$DataSize = intval($_REQUEST['DataSize'] ?? 0);
			$skip = '';

			if(!$DataSize) // first step
			{
				bx_unlink(RESTORE_FILE_LIST);
				DeleteDirRec(RESTORE_FILE_DIR);

				$Block = $tar->Block;
				if (!$ArchiveSize = $tar->getDataSize($file))
					$ArchiveSize = filesize($file) * 2; // for standard gzip files
				$DataSize = $ArchiveSize;

				while(file_exists($file1 = $tar->getNextName($file1)))
					$DataSize += $ArchiveSize;

				$r = true;
				SetCurrentProgress(0);

				if ($n = CTar::getLastNum($file))
				{
					for($i=1;$i<=$n;$i++)
					{
						if (!file_exists($file.'.'.$i))
						{
							$strErrMsg = getMsg('ERR_NO_PARTS').' <b>'.($n + 1).'</b>';
							$r = false;
							break;
						}
					}
				}

			}
			else
			{
				$Block = intval($_REQUEST['Block']);
				$skip = ' <input type=button value="'.getMsg('SKIP').'" onClick="document.forms.restore.skip.value=1;reloadPage(2)">';
				if ($r = $tar->SkipTo($Block))
				{
					if ($_REQUEST['skip'])
					{
						while($tar->readHeader() === false);
						$tar->SkipFile();
					}
					while(($r = $tar->extractFile()) && haveTime());
				}
				$strErrMsg = implode('<br>',$tar->err);
			}

			if ($r === 0) // Finish
				$bClearUnusedStep = true;
			else
			{
				SetCurrentProgress(($tar->BlockHeader + $tar->ReadBlockCurrent) * 512,$DataSize, $red=false);

				$hidden = '<input type="hidden" name="Block" value="'.$tar->BlockHeader.'">'.
				'<input type="hidden" name="ReadBlockCurrent" value="'.$tar->ReadBlockCurrent.'">'.
				'<input type="hidden" name="EncryptKey" value="'.htmlspecialcharsbx($tar->EncryptKey).'">'.
				'<input type="hidden" name="DataSize" value="'.$DataSize.'">'.
				'<input type="hidden" name="arc_name" value="'.htmlspecialcharsbx($arc_name).'">';

				if($r === false) // Error
				{
					showMsg(getMsg("ERR_EXTRACT"), $status.$hidden.'<div style="color:red">'.$strErrMsg.'</div>', $bottom.$skip);
					die();
				}
				else
				{
					showMsg(getMsg('TITLE_PROCESS1'),$status.$hidden,$bottom);
					?><script>reloadPage(2, 1);</script><?
					die();
				}
			}
			$tar->close();
		}
		elseif ($tar->LastErrCode == 'ENC_KEY')
		{
			$text = ($tar->EncryptKey ? '<div style="color:red">'.getMsg('WRONG_PASS').'</div>' : '').
			getMsg('FILE_IS_ENC').
			'<input type="password" size=30 name="EncryptKey" autocomplete="off">'.
			'<input type="hidden" name="arc_name" value="'.htmlspecialcharsbx($arc_name).'">'.
			'<input type="hidden" name="Step" value="2">';
			$bottom .= ' <input type="button" id="start_button" value="'.getMsg("BUT_TEXT1").'" onClick="reloadPage(2)">';
			showMsg(getMsg('TITLE_PROCESS1'),$text,$bottom);
			die();
		}
		else
		{
			showMsg(getMsg("ERR_EXTRACT"), getMsg('TAR_ERR_FILE_OPEN').' '.implode('<br>',$tar->err),$bottom);
			die();
		}
	}

	if ($bClearUnusedStep)
	{
		if (file_exists(RESTORE_FILE_LIST) && $f = fopen(RESTORE_FILE_LIST, 'rb'))
		{
			$modules = $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules';
			$components = $_SERVER['DOCUMENT_ROOT'].'/bitrix/components/bitrix';
			fgets($f); // "die" line
			$a = array();
			while(false !== $l = fgets($f))
				$a[trim($l)] = 1;
			fclose($f);

			$ds = new CDirRealScan;
			$ds->startPath = $_REQUEST['nextPath'] ?? '';
			if (mb_strpos($ds->startPath, $components) === 0)
				$init_path = $components;
			else
				$init_path = $modules;
			$res = $ds->Scan($init_path);

			if ($res === true && $init_path == $modules)
			{
				$ds->nextPath = $components;
				$res = 'BREAK';
			}

			if ($res === 'BREAK')
			{
				$status = getMsg("SEARCHING_UNUSED");
				$hidden = '<input type="hidden" name="nextPath" value="'.$ds->nextPath.'">'.
					'<input type="hidden" name="clear" value="1">'.GetHidden(array('dump_name', 'arc_name'));
				$bottom = '<a href="javascript:reloadPage(1)">'.getMsg('BUT_TEXT_BACK').'</a> ';
				showMsg(getMsg('TITLE_PROCESS1'),$status.$hidden,$bottom);
				?><script>reloadPage(2, 1);</script><?
				die();
			}
			bx_unlink(RESTORE_FILE_LIST);
		}

		if (file_exists(RESTORE_FILE_DIR))
			$strWarning.= '<li>'.getMsg('WARN_CLEARED');
		if (file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/backup/sites'))
			$strWarning.= '<li>'.getMsg('WARN_SITES');
		$bSelectDumpStep = true;
	}

	if ($strWarning)
	{
		$status = '<div style="color:red;text-align:center"><b>'.getMsg('WARNING').'</b></div> <ul style="color:red">'.$strWarning.'</ul>';
		$hidden = '<input type="hidden" name="source" value="dump">'.GetHidden(array('dump_name', 'arc_name'));
		$bottom = '<a href="javascript:reloadPage(1)">'.getMsg('BUT_TEXT_BACK').'</a> '.
			'<input type="button" value="'.getMsg("BUT_TEXT1").'" onClick="reloadPage(2)">';
		showMsg(getMsg('TITLE_PROCESS1'),$status.$hidden,$bottom);
		die();
	}

	if (file_exists(RESTORE_CLOUD_FILE_LIST))
	{
		$skip = $_REQUEST['skip'];
		if ($skip)
		{
		}
		elseif ($rsFile = fopen(RESTORE_CLOUD_FILE_LIST, 'rb'))
		{
			if ($start_position = intval($_REQUEST['position']))
				fseek($rsFile, $start_position);

			if (is_array($_REQUEST['mirror_list']) && time() - intval($_REQUEST['init_time']) < 600) // раз в 10 минут снова измеряем зеркала
			{
				$mirror_list = $_REQUEST['mirror_list'];
				arsort($mirror_list); // выбираем самое быстрое зеркало
			}
			else
			{
				$mirror_list = [
					'cdn.bitrix24.ru' => 999999,
					'cdn.bitrix24.com' => 999999,
					'cdn.bitrix24.de' => 999999,
					'cdn-ru.bitrix24.ru' => 999999,
					'cdn-ru.bitrix24.de' => 999999,
				];
				$_REQUEST['init_time'] = $init_time = time();
			}
			$mirror = key($mirror_list);
			if (DEBUG)
			{
				echo time() - intval($_REQUEST['init_time']);
				echo '<pre>';print_r($mirror_list);echo '</pre>';
			}

			$file_list = [];
			if (is_array($_REQUEST['file_list']))
			{
				foreach($_REQUEST['file_list'] as $file => $str)
				{
					if (!$f = unserialize($str))
					{
						$strErrMsg = 'Unserialize failure: '.$str;
						break;
					}
					$file_list[$file] = $f;
					$url = 'https://'.$mirror.'/'.$f['path'];
					CMultiGet::startLoad($url, $file);
				}
			}

			$concurrency = 20;
			$num = 0;
			$bFirst = true;
			while(!$strErrMsg)
			{
				while (haveTime() && count(CMultiGet::$connections) < $concurrency && (false !== $line = fgets($rsFile)))
				{
					$num++;
					if (mb_strpos($line,'<'.'?php') === 0)
						continue;
					if (!preg_match('#^(\d{4}-\d{2}-\d{2} [\d:]{8})[ \t]+(\d+)[ \t]+(.+)$#', trim($line), $regs))
					{
						$strErrMsg = 'File parse error '.RESTORE_CLOUD_FILE_LIST.' for line '.$num.': '.$line;
						break;
					}

					$date = $regs[1];
					$size = $regs[2];
					$path = str_replace(' ', '%20', $regs[3]);
					$save_path = preg_replace('#^[^/]+/#', '', $regs[3]);

					if (preg_match('#^[^/]*(cache/|tmp/|test.txt)#', $save_path))
						continue;

					$url = 'https://'.$mirror.'/'.$path;
					$file = $_SERVER['DOCUMENT_ROOT'].'/upload/'.$save_path;

					if (file_exists($file))
					{
						if (filesize($file) == $size)
							continue;
						if (filesize($file) > $size)
							bx_unlink($file);
					}

					if (!CMultiGet::startLoad($url, $file))
					{
						$strErrMsg = nl2br(htmlspecialcharsbx(CMultiGet::$error));
						break;
					}

					$file_list[$file] = [
						'date' => $date,
						'size' => $size,
						'path' => $path,
						'mirror' => $mirror,
					];
				}

				if (!CMultiGet::getPart())
				{
					debug(CMultiGet::$error);

					$connection = CMultiGet::$connections[CMultiGet::$current];
					$url = $connection['url'];
					CMultiGet::dropConnection(CMultiGet::$current);

					unset($file_list[$connection['file']]); // файла нет

					if($bFirst)
					{
						$mirror_list_orig = $mirror_list;
						$bFirst = false;
					}

					foreach($mirror_list as $mirror => $speed)
					{
						if (mb_strpos($url,'https://'.$mirror) === 0)
						{
							unset($mirror_list[$mirror]);
							CMultiGet::$bytes = 0;
							$START_TIME = microtime(1);
							break;
						}
					}

					if (count($mirror_list))
					{
						$mirror = key($mirror_list);
						foreach ($file_list as $file => $f)
                        {
	                        $file_list[$file]['mirror'] = $mirror;
                        }
						continue;
					}

					//$strErrMsg = nl2br(htmlspecialcharsbx(CMultiGet::$error));
					if(!file_exists(RESTORE_CLOUD_FILE_LIST_404))
						file_put_contents(RESTORE_CLOUD_FILE_LIST_404, '<'.'?php die(); ?'.'>'."\n");
					file_put_contents(RESTORE_CLOUD_FILE_LIST_404, nl2br(htmlspecialcharsbx(CMultiGet::$error))."\n", FILE_APPEND);
				}

				if (!haveTime())
					break;
			}
			// для следующего хита обновим список зеркал
			if ($bFirst === false)
			{
				$mirror_list = $mirror_list_orig;
			}

			if (!$strErrMsg && (!feof($rsFile) || count(CMultiGet::$connections)))
			{
				$position = ftell($rsFile);
				$size = filesize(RESTORE_CLOUD_FILE_LIST);
				SetCurrentProgress($position, $size);
				if (mb_strlen($file) > 80)
					$file = '...'.mb_substr($file,-80);
				$text =	getMsg('ARC_DOWN_PROCESS').' <b>'.htmlspecialcharsbx($file).'</b>'.
					$status.
					GetHidden(array('source', 'dump_name', 'arc_name', 'init_time')).'
					<input type="hidden" name="cloud_download" value="Y">
					<input type="hidden" name="position" value="'.$position.'">';

				foreach($file_list as $file => $f)
				{
					if (!file_exists($file) || filesize($file) < $f['size'])
						$text .= '<input type="hidden" name="file_list['.htmlspecialcharsbx($file).']" value="'.htmlspecialcharsbx(serialize($f)).'">';
				}

				$mirror_list[$mirror] = round(CMultiGet::$bytes * 8/1024/1024/STEP_TIME, 2); // Mbit/s
				foreach($mirror_list as $mirror => $speed)
				{
					$text .= '<input type="hidden" name="mirror_list['.htmlspecialcharsbx($mirror).']" value="'.$speed.'">';
				}

				$bottom = '<a href="javascript:reloadPage(1)">'.getMsg('BUT_TEXT_BACK').'</a>'.
					' <input type=button value="'.getMsg('SKIP').'" onClick="document.forms.restore.skip.value=1;reloadPage(2)">';
				showMsg(getMsg('TITLE3'),$text,$bottom);
				?><script>reloadPage(2, 1);</script><?
				die();
			}
			fclose($rsFile);
		}
		else
			$strErrMsg = getMsg("LOADER_LOAD_CANT_OPEN_READ", ["#FILE#" => RESTORE_CLOUD_FILE_LIST]);

		if (!$strErrMsg)
		{
			bx_unlink(RESTORE_CLOUD_FILE_LIST) || $strErrMsg = getMsg('FINISH_ERR_DELL_TITLE').': '.RESTORE_CLOUD_FILE_LIST;
			$bSelectDumpStep = true;
		}

		if ($strErrMsg)
		{
			$ar = array(
				'TITLE' => getMsg("TITLE3"),
				'TEXT' => '<div style="color:red">'.$strErrMsg.'</div>',
				'BOTTOM' =>
				'<input type="hidden" name="cloud_download" value="Y">'.
				GetHidden(array('source', 'dump_name', 'arc_name')).
				'<a href="javascript:reloadPage(1)">'.getMsg('BUT_TEXT_BACK').'</a> '.
				'<input type="button" value="'.getMsg("DUMP_RETRY").'" onClick="reloadPage(2)"> '
			);
			html($ar);
			?><script>reloadPage(2, 300);</script><?
			die();
		}
	}

	if ($bSelectDumpStep)
	{
		if (file_exists($dbconn) && $strFile = file_get_contents($dbconn))
		{
			$bUTF_conf = preg_match('#^[ \t]*define\(.BX_UTF.+true\)#mi', $strFile);

			if(file_exists($main_version) && include_once($main_version))
			{
				if(version_compare_bx(SM_VERSION, '20.100.0') >= 0)
				{
					if ($mb_overload_value > 0)
						$strErrMsg = getMsg('FUNC_OVERLOAD_ERROR').'<br><br>'.$strErrMsg;
				}
				else
				{
					if ($bUTF_conf && !$bUTF_serv)
						$strErrMsg = getMsg('UTF8_ERROR1').'<br><br>'.$strErrMsg;
                    elseif (!$bUTF_conf && $bUTF_serv)
						$strErrMsg = getMsg('UTF8_ERROR2').'<br><br>'.$strErrMsg;
				}
			}
			else
			{
				if ($bUTF_conf && !$bUTF_serv)
					$strErrMsg = getMsg('UTF8_ERROR1').'<br><br>'.$strErrMsg;
                elseif (!$bUTF_conf && $bUTF_serv)
					$strErrMsg = getMsg('UTF8_ERROR2').'<br><br>'.$strErrMsg;

			}
		}

		if ($strErrMsg)
		{
				$ar = array(
					'TITLE' => getMsg("TITLE2"),
					'TEXT' => '<div style="color:red">'.$strErrMsg.'</div>',
					'BOTTOM' =>
					'<input type="hidden" name="source" value="dump">'.GetHidden(array('dump_name', 'arc_name')).
					'<a href="javascript:reloadPage(1)">'.getMsg('BUT_TEXT_BACK').'</a> '.
					'<input type="button" value="'.getMsg("DUMP_RETRY").'" onClick="reloadPage(2)"> '
				);
				html($ar);
		}
		else
		{

			if (!isset($_REQUEST['DBName']) || !$_REQUEST['DBName'])
			{
				$DBName = '';
				if (file_exists($dbconn) && $str = file_get_contents($dbconn))
				{
					$ar = explode("\n", $str);
					foreach($ar as $l)
						if (preg_match('#^[ \t]*\$(DBHost|DBLogin|DBPassword|DBName)[ \t]*=[ \t]*["\']([^"\']+)["\']#', $l))
							eval($l);
				}

				if ($DBName && !preg_match('#^\*+$#', $DBName))
				{
					$strWarning .= '<li>'.getMsg('DBCONN_WARN');
					$create_db = false;
				}
				else
				{
					$DBHost = 'localhost'.(file_exists($_SERVER['DOCUMENT_ROOT'].'/../BitrixEnv.exe') ? ':31006' : '');
					$DBLogin = 'root';
					$DBPassword = '';
					$DBName = 'bitrix_'.(rand(11,99));
					$create_db = "Y";
				}
			}
			else
			{
				$DBHost = $_REQUEST["DBHost"];
				$DBLogin = $_REQUEST["DBLogin"];
				$DBPassword = $_REQUEST["DBPassword"];
				$DBName = $_REQUEST["DBName"];
				$create_db = $_REQUEST["create_db"] == "Y";
			}

			$arDName = getDumpList();
			$strDName = '';
			foreach($arDName as $db)
				$strDName .= '<option value="'.htmlspecialcharsbx($db).'">'.htmlspecialcharsbx($db).'</option>';

			if(count($arDName))
			{
				$ar = array(
					'TITLE' => getMsg("TITLE2"),
					'TEXT' =>
						($strWarning ? '<div style="color:red;text-align:center"><b>'.getMsg('WARNING').'</b></div> <ul style="color:red">'.$strWarning.'</ul>' : '').
						'<input type="hidden" name="arc_name" value="'.htmlspecialcharsbx($arc_name).'">'.
						(count($arDName)>1 ? getMsg("DB_SELECT").' <select name="dump_name">'.$strDName.'</select>' : '<input type=hidden name=dump_name value="'.htmlspecialcharsbx($arDName[0]).'">').
						'<div style="border:1px solid #aeb8d7;padding:5px;margin-top:4px;margin-bottom:4px;">
						<div style="text-align:center;color:#aeb8d7;margin:4px"><b>'.getMsg("DB_SETTINGS").'</b></div>
						<table width=100% cellspacing=0 cellpadding=2 border=0 class="content-table">
						<tr><td align=right>'. getMsg("BASE_HOST").':</td><td><input autocomplete=off type="text" name="DBHost" value="'.htmlspecialcharsbx($DBHost).'"></td></tr>
						<tr><td align=right>'. getMsg("USER_NAME").':</td><td><input autocomplete=off type="text" name="DBLogin" value="'.htmlspecialcharsbx($DBLogin).'"></td></tr>
						<tr><td align=right>'. getMsg("USER_PASS").':</td><td><input type="password" autocomplete=off name="DBPassword" value="'.htmlspecialcharsbx($DBPassword).'"></td></tr>
						<tr><td align=right>'. getMsg("BASE_NAME").':</td><td><input autocomplete=off type="text" name="DBName" value="'.htmlspecialcharsbx($DBName).'"></td></tr>
						<tr><td align=right>'. getMsg("BASE_CREATE_DB").'</td><td><input type="checkbox" name="create_db" value="Y" '.($create_db ? 'checked' : '').'></td></tr>
						</table>
						</div>'.
						(
						file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/backup/clouds') ?
						'<div>'.getMsg("BASE_CLOUDS").'
							<select name="LocalCloud">
								<option value="Y">'.getMsg("BASE_CLOUDS_Y").'</option>
								<option value="">'.getMsg("BASE_CLOUDS_N").'</option>
							</select>
						</div>'
						:
						''
						)
					,
					'BOTTOM' =>
					'<a style="padding: 0 13px;" href="/restore.php?Step=1&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> '.
					'<input type="button" style="padding: 0 13px;" value="'.getMsg("DB_SKIP").'" onClick="reloadPage(4)"> '.
					'<input type="button" style="padding: 0 13px;" value="'.getMsg("BASE_RESTORE").'" onClick="reloadPage(3)">'
				);
				html($ar);
			}
			else
				showMsg(getMsg('FINISH'),GetHidden(array('dump_name', 'arc_name')).'<script>reloadPage(4);</script>');
		}
	}
}
elseif($Step == 3)
{
	$d_pos = (double) $_REQUEST["d_pos"];
	if ($d_pos < 0)
		$d_pos = 0;

	if (!isset($_REQUEST['d_pos'])) // start
	{
		if(!file_exists($dbconn))
		{
			if (!is_dir($dir = dirname($dbconn)))
				mkdir($dir, 0777, true);
			file_put_contents($dbconn, '<?'."\n".
				'define("DBPersistent", false);'."\n".
				'$DBType = "mysql";'."\n".
				'$DBHost = "";'."\n".
				'$DBLogin = "";'."\n".
				'$DBPassword = "";'."\n".
				'$DBName = "";'."\n".
				"\n".
				'$DBDebug = false;'."\n".
				'$DBDebugToFile = false;'."\n".
				'?>');
		}

		if (file_exists($tmp = str_replace('dbconn.php','dbconn.restore.php',$dbconn)))
		{
			bx_unlink($dbconn);
			rename($tmp, $dbconn);
		}

		$arFile = file($dbconn);
		foreach($arFile as $line)
		{
			$line = str_replace("\r\n", "\n", $line);
			if (preg_match('#^[ \t]*\$(DBHost|DBLogin|DBPassword|DBName)#',$line,$regs))
			{
				$key = $regs[1];
				$line = '$'.$key.' = "'.str_replace('$','\$',addslashes($_REQUEST[$key])).'";'."\n";
			}
			$strFile .= $line;
		}

		if (defined('VMBITRIX') && !preg_match('#^[ \t]*define..BX_CRONTAB_SUPPORT#mi', $strFile))
			$strFile = '<'.'?define("BX_CRONTAB_SUPPORT", true);?'.'>'.$strFile;

		file_put_contents($dbconn, $strFile);

		if (file_exists($config = $_SERVER['DOCUMENT_ROOT']."/bitrix/.settings.php"))
		{
			ob_start();
			$ar = include($config);
			ob_end_clean();

			if (is_array($ar))
			{
				if (is_array($ar['connections']['value']['default']))
				{
					$ar['connections']['value']['default']['host'] = $_REQUEST['DBHost'];
					$ar['connections']['value']['default']['database'] = $_REQUEST['DBName'];
					$ar['connections']['value']['default']['login'] = $_REQUEST['DBLogin'];
					$ar['connections']['value']['default']['password'] = $_REQUEST['DBPassword'];
					$data = var_export($ar, true);
					file_put_contents($config, "<"."?php\nreturn ".$data.";\n");
				}
			}
			else
				rename($config, $_SERVER['DOCUMENT_ROOT']."/bitrix/.settings.restore.php"); // workaround for bug #47641
		}

		SetCurrentProgress(0);
		$r = true;
	}
	else
		$r = $oDB->restore();

	$bottom = '<a href="/restore.php?Step=2&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> ';
	if($r && !$oDB->is_end())
	{
		$d_pos = $oDB->getPos();
		$oDB->close();
		$arc_name = $_REQUEST["arc_name"] ?? '';
		$dump_name = preg_replace('#\.[0-9]+$#', '', $_SERVER['DOCUMENT_ROOT'].'/bitrix/backup/'.$_REQUEST['dump_name']);
		$dump_size = 0;
		while(file_exists($dump_name))
		{
			$dump_size += filesize($dump_name);
			$dump_name = CDBRestore::getNextName($dump_name);
		}
		SetCurrentProgress($d_pos, $dump_size);
		$text =
		$status . '
		<input type="hidden" name="arc_name" value="'.htmlspecialcharsbx($arc_name).'">
		<input type="hidden" name="dump_name" value="'. htmlspecialcharsbx($_REQUEST["dump_name"]).'">
		<input type="hidden" name="check_site_path" value="Y">
		<input type="hidden" name="d_pos" value="'.$d_pos.'">
		<input type="hidden" name="DBLogin" value="'.htmlspecialcharsbx($_REQUEST["DBLogin"]).'">
		<input type="hidden" name="DBPassword" value="'. (mb_strlen($_REQUEST["DBPassword"]) > 0 ? htmlspecialcharsbx($_REQUEST["DBPassword"]) : "").'">
		<input type="hidden" name="DBName" value="'. htmlspecialcharsbx($_REQUEST["DBName"]).'">
		<input type="hidden" name="DBHost" value="'. htmlspecialcharsbx($_REQUEST["DBHost"]).'">
		<input type="hidden" name="LocalCloud" value="'. ($_REQUEST["LocalCloud"] ? 'Y' : '').'">
		';
		showMsg(getMsg('TITLE_PROCESS2'),$text,$bottom);
		?><script>reloadPage(3, 1);</script><?
	}
	else
	{
		if($oDB->getError() != "")
			showMsg(getMsg("ERR_DUMP_RESTORE"), '<div style="color:red">'.$oDB->getError().'</div>', $bottom);
		else
		{
			if (!$oDB->ft_index)
			{
				$oDB->Query('UPDATE b_option SET VALUE="'.$oDB->escapeString(serialize(array())).'" WHERE MODULE_ID="main" AND NAME LIKE "~ft_%"');
			}
			showMsg(getMsg('FINISH'),GetHidden(array('DBLogin','DBPassword','DBHost','DBName','dump_name', 'arc_name', 'check_site_path')).'<script>reloadPage(4);</script>');
		}
	}
}
elseif($Step == 4)
{
	$strWarning .= CheckHtaccessAndWarn();

	if (isset($_REQUEST['check_site_path']) && $_REQUEST['check_site_path'])
	{
		$oDB = new CDBRestore($_REQUEST["DBHost"], $_REQUEST["DBName"], $_REQUEST["DBLogin"], $_REQUEST["DBPassword"], $_REQUEST["dump_name"], $d_pos);
		if ($oDB->Connect())
		{
			if ($rs = $oDB->Query('SELECT * FROM b_lang WHERE DOC_ROOT != "'.$oDB->escapeString($_SERVER['DOCUMENT_ROOT']).'" AND DOC_ROOT IS NOT NULL AND DOC_ROOT != ""'))
			{
				if ($oDB->Fetch($rs))
				{
					$oDB->Query('UPDATE b_lang SET DOC_ROOT = "" ');
					$strWarning .= '<li>'.getMsg('DOC_ROOT_WARN');
				}
			}

			$rs = $oDB->Query('SHOW TABLES LIKE "b_bitrixcloud_option"');
			if ($oDB->Fetch($rs))
			{
				$rs = $oDB->Query('SELECT * FROM b_bitrixcloud_option WHERE NAME="cdn_config_active" AND PARAM_VALUE=1');
				if ($oDB->Fetch($rs))
				{
					$rs = $oDB->Query('SELECT * FROM b_bitrixcloud_option WHERE NAME="cdn_config_domain"');
					if (($f = $oDB->Fetch($rs)) && $f['PARAM_VALUE'] != $_SERVER['HTTP_HOST'])
					{
						$oDB->Query('UPDATE b_bitrixcloud_option SET PARAM_VALUE=0 WHERE NAME="cdn_config_active"');
						$oDB->Query('UPDATE b_bitrixcloud_option SET PARAM_VALUE='.(time() + 86400 * 3650).' WHERE NAME="cdn_config_expire_time"');
						$strWarning .= '<li>'.getMsg('CDN_WARN');
					}
				}
			}

			if ($rs = $oDB->Query('SELECT * FROM b_module_to_module WHERE FROM_MODULE_ID="main" AND MESSAGE_ID="OnPageStart" AND TO_CLASS="Bitrix\\\\Security\\\\HostRestriction"'))
			{
				if ($f = $oDB->Fetch($rs)) // host restriction is turned on
				{
					$rs0 = $oDB->Query('SELECT * FROM b_option WHERE MODULE_ID="security" AND NAME="restriction_hosts_hosts"');
					if ($f0 = $oDB->Fetch($rs0))
					{
						if (mb_strpos($f0['VALUE'],$_SERVER['HTTP_HOST']) === false)
						{
							$oDB->Query('DELETE FROM b_module_to_module WHERE ID='.$f['ID']);
							$strWarning .= '<li>'.getMsg('HOSTS_WARN');
						}
					}
				}
			}

		}
		else
			$strWarning .= '<li>'.$oDB->getError();
	}

	$text =
	($strWarning ? '<div style="color:red;padding:10px;text-align:center"><b>'.getMsg('WARNING').'</b></div> <ul style="color:red">'.$strWarning.'</ul>' : '').
	getMsg("FINISH_MSG").GetHidden(array('dump_name', 'arc_name'));
	$bottom = '<a style="padding:0 13px;font-size:13px;" href="/restore.php?Step=2&source=dump&lang='.LANG.'">'.getMsg('BUT_TEXT_BACK').'</a> '.
		'<input type=button style="padding:0 13px;font-size:13px;" value="'.getMsg('DELETE_FILES').'" onClick="reloadPage(5)">';
	showMsg(getMsg("FINISH"), $text, $bottom);
}
elseif($Step == 5)
{
	$ok = true;
	$ok = bx_unlink($_SERVER['DOCUMENT_ROOT'].'/bitrixsetup.php') && $ok;

	if (isset($_REQUEST['dump_name']))
	{
		$ok = bx_unlink($_SERVER["DOCUMENT_ROOT"]."/bitrix/backup/".$_REQUEST["dump_name"]) && $ok;
		$ok = bx_unlink($_SERVER["DOCUMENT_ROOT"]."/bitrix/backup/".str_replace('.sql','_after_connect.sql',$_REQUEST["dump_name"])) && $ok;
	}

	if(isset($_REQUEST['arc_name']) && mb_strpos($_REQUEST['arc_name'],'bitrix/') === false)
	{
		$ok = bx_unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["arc_name"]) && $ok;
		$i = 0;
		while(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_REQUEST['arc_name'].'.'.++$i))
			$ok = bx_unlink($_SERVER['DOCUMENT_ROOT'].'/'.$_REQUEST['arc_name'].'.'.$i) && $ok;
	}

	foreach(array('cache', 'stack_cache', 'managed_cache', 'resize_cache', 'tmp') as $dir)
	{
		$ok = DeleteDirRec($_SERVER['DOCUMENT_ROOT'].'/bitrix/'.$dir) && $ok;
	}

	$ok = bx_unlink($_SERVER["DOCUMENT_ROOT"]."/restore.php") && $ok;
	if (!$ok)
		showMsg(getMsg("FINISH_ERR_DELL_TITLE"), '<span style="color:red">'.getMsg("FINISH_ERR_DELL").'</span>');
	else
	{
		showMsg(getMsg("FINISH"), getMsg("FINISH_MSG"), '<input type=button onclick="document.location=\'/\'" value="'.getMsg("FINISH_BTN").'">');
		?><script>window.setTimeout(function(){document.location="/";},5000);</script><?
	}
}

#################### END ############

class CDBRestore
{
	var $type = "";
	var $DBHost ="";
	var $DBName = "";
	var $DBLogin = "";
	var $DBPassword = "";
	var $DBdump = "";
	var $db_Conn = "";
	var $db_Error = "";
	var $f_end = false;
	var $start;
	var $d_pos;
	var $_dFile;
	var $mysqli;

	public function __construct($DBHost, $DBName, $DBLogin, $DBPassword, $DBdump, $d_pos)
	{
		$this->DBHost = $DBHost;
		$this->DBLogin = $DBLogin;
		$this->DBPassword = $DBPassword;
		$this->DBName = $DBName;
		$this->DBdump = $_SERVER["DOCUMENT_ROOT"]."/bitrix/backup/".$DBdump;
		$this->d_pos = $d_pos;
		$this->mysqli = function_exists('mysqli_connect');
		$this->ft_index = null;
	}

	function Query($sql)
	{
		if ($this->ft_index === false && preg_match("#^CREATE TABLE.*FULLTEXT KEY#ms", $sql))
		{
			$sql = preg_replace("#[\r\n\s]*FULLTEXT KEY[^\r\n]*[\r\n]*#m", "", $sql);
			$sql = str_replace("),)", "))", $sql);
		}

		$rs = $this->mysqli ? mysqli_query($this->db_Conn, $sql) : mysql_query($sql, $this->db_Conn);
		if ($this->ft_index === null)
		{
			if (preg_match("#^CREATE TABLE.*FULLTEXT KEY#ms", $sql))
			{
				$this->ft_index = (bool) $rs;
				return $rs ? $rs : $this->Query($sql);
			}
		}

		if (!$rs)
		{
			$this->db_Error = "<font color=#ff0000>MySQL query error!</font><br>".($this->mysqli ? mysqli_error($this->db_Conn) : mysql_error()).'<br><br>'.htmlspecialcharsbx($sql);
			return false;
		}
		return $rs;
	}

	function Connect()
	{
		$this->db_Conn = $this->mysqli ? @mysqli_connect($this->DBHost, $this->DBLogin, $this->DBPassword) : @mysql_connect($this->DBHost, $this->DBLogin, $this->DBPassword);
		if (!$this->db_Conn)
		{
			$this->db_Error = "<font color=#ff0000>MySQL connect error!</font><br>".($this->mysqli ? mysqli_connect_error() : mysql_error()).'<br>';
			return false;
		}

		$this->Query('SET FOREIGN_KEY_CHECKS = 0');

		$dbExists = $this->mysqli ? @mysqli_select_db($this->db_Conn, $this->DBName) : @mysql_select_db($this->DBName, $this->db_Conn);
		if(!$dbExists)
		{
			if (@$_REQUEST["create_db"]=="Y")
			{
				if(!@$this->Query("CREATE DATABASE `".$this->escapeString($this->DBName)."`"))
				{
					$this->db_Error = getMsg("ERR_CREATE_DB").': '.($this->mysqli ? mysqli_error($this->db_Conn) : mysql_error());
					return false;
				}
				$dbExists = $this->mysqli ? @mysqli_select_db($this->db_Conn, $this->DBName) : @mysql_select_db($this->DBName, $this->db_Conn);
			}

			if(!$dbExists)
			{
				$this->db_Error = "<font color=#ff0000>Error! mysql".($this->mysqli ? 'i' : '')."_select_db(".htmlspecialcharsbx($this->DBName).")</font><br>".($this->mysqli ? mysqli_error($this->db_Conn) : mysql_error())."<br>";
				return false;
			}
		}

		$this->Query("SET SQL_MODE=''");
		$this->Query("SET innodb_strict_mode=0");

		$after_file = str_replace('.sql','_after_connect.sql',$this->DBdump);
		if (file_exists($after_file))
		{
			$arSql = explode(';',file_get_contents($after_file));
			foreach($arSql as $sql)
			{
				$sql = str_replace('<DATABASE>', $this->DBName, $sql);
				if (trim($sql))
					$this->Query($sql);
			}
		}

		return true;
	}

	function Fetch($rs)
	{
		return $this->mysqli ? mysqli_fetch_assoc($rs) : mysql_fetch_assoc($rs);
	}

	function escapeString($str)
	{
		return $this->mysqli ? mysqli_real_escape_string($this->db_Conn, $str) : mysql_real_escape_string($str, $this->db_Conn);
	}

	function readSql()
	{
		$cache = "";

		while(CTar::substr($cache, -2, 1) != ";")
		{
			$line = fgets($this->_dFile);
			if (feof($this->_dFile) || $line === false)
			{
				fclose($this->_dFile);
				if (file_exists($next_part = self::getNextName($this->DBdump)))
				{
					$this->DBdump = $next_part;
					if (!$this->_dFile = fopen($this->DBdump, 'rb'))
					{
						$this->db_Error = "Can't open file: ".$this->DBdump;
						return false;
					}
				}
				else
				{
					$this->f_end = true;
					break;
				}
			}

			$cache .= $line;
		}

		if($this->f_end)
			return false;
		return $cache;
	}

	function restore()
	{
		clearstatcache();
		while($this->d_pos > ($s = filesize($this->DBdump)) && file_exists($this->DBdump))
		{
			$this->d_pos -= $s;
			$this->DBdump = self::getNextName($this->DBdump);
		}

		if (!$this->_dFile = fopen($this->DBdump, 'rb'))
		{
			$this->db_Error = "Can't open file: ".$this->DBdump;
			return false;
		}

		if($this->d_pos > 0)
			fseek($this->_dFile, $this->d_pos);

		while(haveTime() && ($sql = $this->readSql()))
		{
			if (defined('VMBITRIX'))
			{
				if (preg_match('#^CREATE TABLE#i',$sql))
				{
					$sql = preg_replace('#ENGINE=MyISAM#i','',$sql);
					$sql = preg_replace('#TYPE=MyISAM#i','',$sql);
				}
			}

			$rs = $this->Query($sql);

			if(!$rs)
			{
				if ($this->getErrorNum() != 1062) // Duplicate entry
					return false;

				if (preg_match('#^(INSERT[^\(]+VALUES[^\(]+)\((.+)\);?$#mis', $sql, $regs))
				{
					$insert = $regs[1];
					foreach(preg_split('#\),[^\(]\(#', $regs[2]) as $values)
					{
						$rs = $this->Query($insert.'('.$values.')');
						if (!$rs && $this->getErrorNum() != 1062)
							return false;
					}
					return true;
				}
			}
		}
		$this->Query('SET FOREIGN_KEY_CHECKS = 1');

		if ($this->LocalCloud && $this->f_end)
		{
			$i = '';
			while(file_exists($_SERVER['DOCUMENT_ROOT'].'/upload/'.($name = 'clouds'.$i)))
				$i++;
			if (!file_exists($f = $_SERVER['DOCUMENT_ROOT'].'/upload'))
				mkdir($f);
			if (rename($_SERVER['DOCUMENT_ROOT'].'/bitrix/backup/clouds', $_SERVER['DOCUMENT_ROOT'].'/upload/'.$name))
			{
				$arFiles = scandir($_SERVER['DOCUMENT_ROOT'].'/upload/'.$name);
				foreach($arFiles as $file)
				{
					if ($id = intval($file))
						$this->Query('UPDATE b_file SET SUBDIR = CONCAT("'.$name.'/'.$id.'/", SUBDIR), HANDLER_ID=NULL WHERE HANDLER_ID ='.$id);
				}
			}
		}
		return true;
	}

	function getError()
	{
		return $this->db_Error;
	}

	function getErrorNum()
	{
		return $this->mysqli ? mysqli_errno($this->db_Conn) : mysql_errno();
	}

	function getPos()
	{
		if (is_resource($this->_dFile))
		{
			$res = ftell($this->_dFile);
			$prev = preg_replace('#\.[0-9]+$#', '', $this->DBdump);
			clearstatcache();
			while($this->DBdump != $prev)
			{
				if (!file_exists($prev))
					return false;
				$res += filesize($prev);
				$prev = self::getNextName($prev);
			}
			return $res;
		}
	}

	function close()
	{
		unset($this->_dFile);
		return true;
	}

	function is_end()
	{
		return $this->f_end;
	}

	public static function getNextName($file)
	{
		static $CACHE;
		$c = &$CACHE[$file];

		if (!$c)
		{
			$l = mb_strrpos($file,'.');
			$num = CTar::substr($file,$l+1);
			if (is_numeric($num))
				$file = CTar::substr($file,0,$l+1).++$num;
			else
				$file .= '.1';
			$c = $file;
		}
		return $c;
	}
}

function getDumpList()
{
	$arDump = array();
	if (is_dir($back_dir = $_SERVER["DOCUMENT_ROOT"]."/bitrix/backup"))
	{
		$handle = opendir($back_dir);
		while (false !== ($file = readdir($handle)))
		{
			if($file == "." || $file == "..")
				continue;

			if(is_dir($back_dir.'/'.$file))
				continue;

			if (mb_strpos($file,'_after_connect.sql'))
				continue;

			if(mb_substr($file,mb_strlen($file) - 3,3) == "sql")
				$arDump[] = $file;
		}
	}

	return $arDump;
}

function getMsg($index, $ar = [])
{
	global $MESS;
	$str = $MESS[$index];
	foreach($ar as $k => $v)
		$str = str_replace($k, $v, $str);
	return $str;
}

function getArcList()
{
	$arc = "";
	global $strErrMsg;

	$handle = @opendir($_SERVER["DOCUMENT_ROOT"]);
	if (!$handle)
		return false;

	while (false !== ($file = @readdir($handle)))
	{
		if($file == "." || $file == "..")
			continue;

		if(is_dir($_SERVER["DOCUMENT_ROOT"]."/".$file))
			continue;

		if(preg_match('#\.(tar|enc)(\.gz)?$#',$file))
			$arc .= "<option value=\"$file\"> ".$file;

		if(mb_substr($file,mb_strlen($file) - 7,7) == "tar.tar")
			$strErrMsg = getMsg('ERR_TAR_TAR');
	}

	return $arc;
}

function showMsg($title, $msg, $bottom='')
{
	$ar = array(
		'TITLE' => $title,
		'TEXT' => $msg,
		'BOTTOM' => $bottom

	);
	html($ar);
}

function html($ar)
{
	$isCrm = getenv('BITRIX_ENV_TYPE') == 'crm';
?>
	<html>
	<head>
	<title><?=$ar['TITLE']?></title>
	</head>
	<body>
	<style>
		html, body {
			padding: 0 10px;
			margin: 0;
			background: #2fc6f7;
			position: relative;
		}
		p {
			margin: 0 0 40px;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			font-size:13px;
		}
		.wrap {
			min-height: 100vh;
			position: relative;
		}

		.cloud {
			background-size: contain;
			position: absolute;
			z-index: 1;
			background-repeat: no-repeat;
			background-position: center;
			opacity: .8;
		}

		.cloud-fill {
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDEiIGhlaWdodD0iNjMiIHZpZXdCb3g9IjAgMCAxMDEgNjMiPiAgPHBhdGggZmlsbD0iI0U0RjhGRSIgZmlsbC1ydWxlPSJldmVub2RkIiBkPSJNNDc3LjM5MjY1MywyMTEuMTk3NTYyIEM0NzYuNDcwMzYyLDIwMS41NDc2MjIgNDY4LjM0NDA5NywxOTQgNDU4LjQ1MjIxNCwxOTQgQzQ1MC44MTI2NzksMTk0IDQ0NC4yMjc3NzcsMTk4LjUwNDA2MyA0NDEuMTk5MDYzLDIwNC45OTk1NzkgQzQzOS4xNjkwNzYsMjA0LjI2MTQzMSA0MzYuOTc4NjM2LDIwMy44NTc3NzEgNDM0LjY5MzQzOSwyMDMuODU3NzcxIEM0MjQuMTgzMTE2LDIwMy44NTc3NzEgNDE1LjY2Mjk4MywyMTIuMzc3NTg4IDQxNS42NjI5ODMsMjIyLjg4NzkxMSBDNDE1LjY2Mjk4MywyMjMuMzg1MDY0IDQxNS42ODc5MzUsMjIzLjg3NjIxNSA0MTUuNzI1MjA2LDIyNC4zNjM4OTIgQzQxNC40NjU1ODUsMjI0LjA0OTYxOCA0MTMuMTQ4NDc4LDIyMy44ODA2MzcgNDExLjc5MTU3MywyMjMuODgwNjM3IEM0MDIuODMxNzczLDIyMy44ODA2MzcgMzk1LjU2ODczNCwyMzEuMTQzNjc2IDM5NS41Njg3MzQsMjQwLjEwMzQ3NiBDMzk1LjU2ODczNCwyNDkuMDYyOTYxIDQwMi44MzE3NzMsMjU2LjMyNiA0MTEuNzkxNTczLDI1Ni4zMjYgTDQ3Mi4zMTg0NzUsMjU2LjMyNiBDNDg0LjkzODM4LDI1Ni4zMjYgNDk1LjE2ODU0MSwyNDYuMDk1MjA3IDQ5NS4xNjg1NDEsMjMzLjQ3NTYxOCBDNDk1LjE2ODU0MSwyMjIuNjAwNDg1IDQ4Ny41Njk0MzUsMjEzLjUwNjQ0NyA0NzcuMzkyNjUzLDIxMS4xOTc1NjIiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0zOTUgLTE5NCkiIG9wYWNpdHk9Ii41Ii8+PC9zdmc+);
		}

		.cloud-border {
			background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxODUiIGhlaWdodD0iMTE3IiB2aWV3Qm94PSIwIDAgMTg1IDExNyI+ICA8cGF0aCBmaWxsPSJub25lIiBzdHJva2U9IiNFNEY4RkUiIHN0cm9rZS13aWR0aD0iMyIgZD0iTTEwODIuNjk2MzcsNTI5LjI1MjY1NyBDMTA4MS4wMjAzMSw1MTEuNzE2MDg3IDEwNjYuMjUyNjcsNDk4IDEwNDguMjc2NDMsNDk4IEMxMDM0LjM5MzMxLDQ5OCAxMDIyLjQyNjc0LDUwNi4xODUxMSAxMDE2LjkyMjc1LDUxNy45ODkyMzQgQzEwMTMuMjMzNzEsNTE2LjY0NzgxNyAxMDA5LjI1MzA4LDUxNS45MTQyNTcgMTAwNS4xMDAyNSw1MTUuOTE0MjU3IEM5ODYuMDAwMTMzLDUxNS45MTQyNTcgOTcwLjUxNjcyOCw1MzEuMzk3MDg4IDk3MC41MTY3MjgsNTUwLjQ5NzIwOSBDOTcwLjUxNjcyOCw1NTEuNDAwNjcxIDk3MC41NjIwNzMsNTUyLjI5MzIyNyA5NzAuNjI5ODA0LDU1My4xNzk0NjkgQzk2OC4zNDA3MjksNTUyLjYwODM0OCA5NjUuOTQ3MTg2LDU1Mi4zMDEyNjMgOTYzLjQ4MTMyMiw1NTIuMzAxMjYzIEM5NDcuMTk4OTIxLDU1Mi4zMDEyNjMgOTM0LDU2NS41MDAxODQgOTM0LDU4MS43ODI1ODQgQzkzNCw1OTguMDY0NDExIDk0Ny4xOTg5MjEsNjExLjI2MzMzMiA5NjMuNDgxMzIyLDYxMS4yNjMzMzIgTDEwNzMuNDc1Miw2MTEuMjYzMzMyIEMxMDk2LjQwOTAxLDYxMS4yNjMzMzIgMTExNSw1OTIuNjcxMTkyIDExMTUsNTY5LjczNzk1OSBDMTExNSw1NDkuOTc0ODc4IDExMDEuMTkwMzUsNTMzLjQ0ODUzMSAxMDgyLjY5NjM3LDUyOS4yNTI2NTciIHRyYW5zZm9ybT0idHJhbnNsYXRlKC05MzIgLTQ5NikiIG9wYWNpdHk9Ii41Ii8+PC9zdmc+);
		}

		.cloud-1 {
			top: 9%;
			left: 50%;
			width: 60px;
			height: 38px;
		}

		.cloud-2 {
			top: 14%;
			left: 12%;
			width: 80px;
			height: 51px;
		}

		.cloud-3 {
			top: 11%;
			right: 14%;
			width: 106px;
			height: 67px;
		}

		.cloud-4 {
			top: 33%;
			right: 13%;
			width: 80px;
			height: 51px;
		}

		.cloud-5 {
			bottom: 23%;
			right: 12%;
			width: 80px;
			height: 51px;
		}

		.cloud-6 {
			bottom: 23%;
			left: 12%;
			width: 80px;
			height: 51px;
		}

		.cloud-7 {
			top: 13%;
			left: 6%;
			width: 60px;
			height: 31px;
			opacity: 1;
		}

		.cloud-8 {
			top: 43%;
			right: 6%;
			width: 86px;
			height: 54px;
			opacity: 1;
		}

		.header {
			min-height: 220px;
			max-width: 727px;
			margin: 0 auto;
			box-sizing: border-box;
			position: relative;
			z-index: 10;
		}

		.logo-link,
		.buslogo-link{
			position: absolute;
			top: 50%;
			margin-top: -23px;
		}

		.logo {
			width: 255px;
			display: block;
			height: 46px;
			background-repeat: no-repeat;
			/*background-size:cover;*/
		}

		.wrap.en .logo,
		.wrap.de .logo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxODUiIGhlaWdodD0iMzYiIHZpZXdCb3g9IjAgMCAxODUgMzYiPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtLjAxMyAuMDIyKSI+ICAgIDxnIGZpbGw9IiNGRkZGRkYiIGZpbGwtcnVsZT0ibm9uemVybyIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMCAxLjcwMSkiPiAgICAgIDxwYXRoIGQ9Ik0uNDg1MTExMDIzIDIuNjA5MTUwMDdMMTAuMjI4MTI5NCAyLjYwOTE1MDA3QzE2Ljk2MDY2NDYgMi42MDkxNTAwNyAxOS45NzExNDc4IDYuNDQyNDE2NyAxOS45NzExNDc4IDEwLjMyMDU2OTQgMTkuOTcxMTQ3OCAxMi44MTYyMzI0IDE4LjcwMzA5NTggMTUuMjU4MDMyMiAxNi4zMDM4MzE5IDE2LjQ2MDk3NzdMMTYuMzAzODMxOSAxNi41NTA3NDk4QzIwLjAyNTg4MzkgMTcuNTIwMjg4IDIyLjA5NjczMTQgMjAuNDczNzg4NSAyMi4wOTY3MzE0IDIzLjg5NDEwMzcgMjIuMDk2NzMxNCAyOC41MDgzODcyIDE4LjQyOTQxNTUgMzMuMDMyODk4NiAxMS4wODU2NjEgMzMuMDMyODk4NkwuNDk0MjMzNjk5IDMzLjAzMjg5ODYuNDk0MjMzNjk5IDIuNjA5MTUwMDcuNDg1MTExMDIzIDIuNjA5MTUwMDd6TTkuMzc5NzIwNSAxNS4wMjQ2MjQ5QzEyLjIwNzc1MDIgMTUuMDI0NjI0OSAxMy42NTgyNTU3IDEzLjQ1MzYxNCAxMy42NTgyNTU3IDExLjQ2OTY1MTYgMTMuNjU4MjU1NyA5LjM5NTkxNzIzIDEyLjI0NDI0MDkgNy43ODAwMjAyOCA5LjIzMzc1NzY4IDcuNzgwMDIwMjhMNi45MjU3MjA1NSA3Ljc4MDAyMDI4IDYuOTI1NzIwNTUgMTUuMDI0NjI0OSA5LjM3OTcyMDUgMTUuMDI0NjI0OXpNMTAuMjI4MTI5NCAyNy44NTMwNTEyQzEzLjgwNDIxODYgMjcuODUzMDUxMiAxNS41OTIyNjMxIDI2LjY1MDEwNTcgMTUuNTkyMjYzMSAyMy44ODUxMjY1IDE1LjU5MjI2MzEgMjEuNTMzMDk4NyAxMy44MDQyMTg2IDIwLjE5NTQ5NTEgMTAuODM5MzQ4NyAyMC4xOTU0OTUxTDYuOTI1NzIwNTUgMjAuMTk1NDk1MSA2LjkyNTcyMDU1IDI3Ljg2MjAyODQgMTAuMjI4MTI5NCAyNy44NjIwMjg0IDEwLjIyODEyOTQgMjcuODUzMDUxMnpNMjUuMjM0OTMyMSA0LjQ0OTQ3NzE0QzI1LjIzNDkzMjEgMi40NjU1MTQ3OSAyNi44ODYxMzY1Ljg0OTYxNzg0NiAyOS4wOTM4MjQyLjg0OTYxNzg0NiAzMS4yNTU4OTg1Ljg0OTYxNzg0NiAzMi45MDcxMDI5IDIuMzc1NzQyNzQgMzIuOTA3MTAyOSA0LjQ0OTQ3NzE0IDMyLjkwNzEwMjkgNi40NzgzMjU1MyAzMS4yNTU4OTg1IDguMTM5MTA4NDkgMjkuMDkzODI0MiA4LjEzOTEwODQ5IDI2Ljg4NjEzNjUgOC4xNDgwODU3IDI1LjIzNDkzMjEgNi41MzIxODg3NiAyNS4yMzQ5MzIxIDQuNDQ5NDc3MTR6TTI1Ljg5MTc2NDggMTEuMTkxMzU4M0wzMi4yNDExNDc1IDExLjE5MTM1ODMgMzIuMjQxMTQ3NSAzMy4wMjM5MjE0IDI1Ljg5MTc2NDggMzMuMDIzOTIxNCAyNS44OTE3NjQ4IDExLjE5MTM1ODN6TTM4Ljc5MTIyOTIgMjcuMzUwMzI3N0wzOC43OTEyMjkyIDE2LjAzOTA0OTEgMzQuNjk1MTQ3NSAxNi4wMzkwNDkxIDM0LjY5NTE0NzUgMTEuMTkxMzU4MyAzOC43OTEyMjkyIDExLjE5MTM1ODMgMzguNzkxMjI5MiA2LjA2NTM3NDA4IDQ1LjE5NTM0OCA0LjI2MDk1NTgzIDQ1LjE5NTM0OCAxMS4xODIzODExIDUxLjkyNzg4MzIgMTEuMTgyMzgxMSA1MC40NjgyNTUgMTYuMDMwMDcxOSA0NS4xOTUzNDggMTYuMDMwMDcxOSA0NS4xOTUzNDggMjUuNzcwMzM5NkM0NS4xOTUzNDggMjcuNzk5MTg3OSA0NS44NTIxODA3IDI4LjQ5MDQzMjcgNDcuMzExODA4OSAyOC40OTA0MzI3IDQ4LjUzNDI0NzYgMjguNDkwNDMyNyA0OS43NTY2ODYyIDI4LjA3NzQ4MTMgNTAuNjA1MDk1MSAyNy41MjA4OTQ2TDUyLjQzODc1MzEgMzEuNzY3MTEyN0M1MC43ODc1NDg2IDMyLjg3MTMwODkgNDcuODc3NDE0OSAzMy40NzI3ODE3IDQ1LjY2MDYwNDUgMzMuNDcyNzgxNyA0MS40Mjc2ODI3IDMzLjQ5MDczNjEgMzguNzkxMjI5MiAzMS4xODM1OTQzIDM4Ljc5MTIyOTIgMjcuMzUwMzI3N3pNNTQuNjU1NTYzNCAxMS4xOTEzNTgzTDYwLjAxOTY5NzEgMTEuMTkxMzU4MyA2MC43MjIxNDMyIDEzLjQ5ODVDNjIuNjAxNDE0NiAxMS43OTI4MzEgNjQuMzg5NDU5MSAxMC42MzQ3NzE1IDY2Ljc5Nzg0NTcgMTAuNjM0NzcxNSA2Ny44ODM0NDQyIDEwLjYzNDc3MTUgNjkuMTk3MTA5NiAxMC45NTc5NTA5IDcwLjE4MjM1ODYgMTEuNjQ5MTk1N0w2Ny45NzQ2NzEgMTYuODIwMDY2QzY2Ljg0MzQ1OTEgMTYuMTI4ODIxMSA2NS43NjY5ODMzIDE1Ljk4NTE4NTkgNjUuMTQ2NjQxMyAxNS45ODUxODU5IDYzLjc3ODIzOTggMTUuOTg1MTg1OSA2Mi43MDE3NjQgMTYuNDQzMDIzMyA2MS4wMDQ5NDYyIDE3Ljc4OTYwNDFMNjEuMDA0OTQ2MiAzMy4wMjM5MjE0IDU0LjY1NTU2MzQgMzMuMDIzOTIxNCA1NC42NTU1NjM0IDExLjE5MTM1ODMgNTQuNjU1NTYzNCAxMS4xOTEzNTgzek03MS4yMjIzNDM4IDQuNDQ5NDc3MTRDNzEuMjIyMzQzOCAyLjQ2NTUxNDc5IDcyLjg3MzU0ODIuODQ5NjE3ODQ2IDc1LjA4MTIzNTkuODQ5NjE3ODQ2IDc3LjI0MzMxMDIuODQ5NjE3ODQ2IDc4Ljg5NDUxNDYgMi4zNzU3NDI3NCA3OC44OTQ1MTQ2IDQuNDQ5NDc3MTQgNzguODk0NTE0NiA2LjQ3ODMyNTUzIDc3LjI0MzMxMDIgOC4xMzkxMDg0OSA3NS4wODEyMzU5IDguMTM5MTA4NDkgNzIuODY0NDI1NSA4LjE0ODA4NTcgNzEuMjIyMzQzOCA2LjUzMjE4ODc2IDcxLjIyMjM0MzggNC40NDk0NzcxNHpNNzEuODc5MTc2NSAxMS4xOTEzNTgzTDc4LjIyODU1OTIgMTEuMTkxMzU4MyA3OC4yMjg1NTkyIDMzLjAyMzkyMTQgNzEuODc5MTc2NSAzMy4wMjM5MjE0IDcxLjg3OTE3NjUgMTEuMTkxMzU4M3oiLz4gICAgICA8cG9seWdvbiBwb2ludHM9Ijg4LjcyOSAyMi4wMzYgODAuNTkxIDExLjE5MSA4Ny4xNzggMTEuMTkxIDkyLjE2OCAxNy44MzQgOTcuMTU4IDExLjE5MSAxMDMuNzQ1IDExLjE5MSA5NS41MDcgMjIuMDM2IDEwMy44MzYgMzMuMDI0IDk3LjI0IDMzLjAyNCA5Mi4xMTMgMjYuMTkyIDg2Ljk0MSAzMy4wMjQgODAuMzU0IDMzLjAyNCIvPiAgICA8L2c+ICAgIDxwYXRoIGZpbGw9IiMyMTVGOTgiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTTEyMi41MjgyNzYsMTIuMDg0NTYzNCBDMTIyLjUyODI3Niw5LjM2NDQ3MDI0IDEyMC4yMzg0ODQsOC40MDM5MDkyOCAxMTcuODAyNzI5LDguNDAzOTA5MjggQzExNC41MzY4MTEsOC40MDM5MDkyOCAxMTEuODYzODY3LDkuNDU0MjQyMjkgMTA5LjM4MjQ5OSwxMC41NDk0NjEzIEwxMDcuNjc2NTU5LDUuNTMxMjAzNjEgQzExMC40NDk4NTIsNC4yOTIzNDkyOCAxMTQuMjk5NjIyLDIuOTAwODgyNDcgMTE4Ljg3OTIwNSwyLjkwMDg4MjQ3IEMxMjYuMDQwNTA2LDIuOTAwODgyNDcgMTI5LjQ5ODAwMSw2LjQzNzkwMTMzIDEyOS40OTgwMDEsMTEuNDAyMjk1OCBDMTI5LjQ5ODAwMSwyMC4wOTIyMzA1IDExNy4zMjgzNSwyMi41MzQwMzAzIDExNS4yNzU3NDgsMjkuMTY4MTg1IEwxMjkuOTM1ODg5LDI5LjE2ODE4NSBMMTI5LjkzNTg4OSwzNC43MDcxMjA2IEwxMDYuNjA5MjA1LDM0LjcwNzEyMDYgQzEwNy45MjI4NzEsMTkuMjAzNDg3MiAxMjIuNTI4Mjc2LDE4LjI5Njc4OTQgMTIyLjUyODI3NiwxMi4wODQ1NjM0IFoiLz4gICAgPHBhdGggZmlsbD0iIzIxNUY5OCIgZmlsbC1ydWxlPSJub256ZXJvIiBkPSJNMTI5LjI3OTA1NiwyMy4wNzI2NjI2IEwxNDUuMzQ0MDg5LDMuMDE3NTg2MTQgTDE1MC4xMTUyNDksMy4wMTc1ODYxNCBMMTUwLjExNTI0OSwyMS45Nzc0NDM2IEwxNTQuODg2NDA5LDIxLjk3NzQ0MzYgTDE1NC44ODY0MDksMjcuMjI5MTA4NiBMMTUwLjExNTI0OSwyNy4yMjkxMDg2IEwxNTAuMTE1MjQ5LDM0LjcyNTA3NSBMMTQzLjYzODE0OSwzNC43MjUwNzUgTDE0My42MzgxNDksMjcuMjI5MTA4NiBMMTI5LjI2OTkzNCwyNy4yMjkxMDg2IEwxMjkuMjY5OTM0LDIzLjA3MjY2MjYgTDEyOS4yNzkwNTYsMjMuMDcyNjYyNiBaIE0xNDAuNzE4ODkyLDIxLjk3NzQ0MzYgTDE0My42MzgxNDksMjEuOTc3NDQzNiBMMTQzLjYzODE0OSwxOC41ODQwNiBDMTQzLjYzODE0OSwxNi4xNTEyMzc0IDE0My44Mjk3MjUsMTMuMzMyMzk1IDE0My45MzAwNzUsMTIuNzEyOTY3OCBMMTM2LjY3NzU0NywyMi4xMjEwNzg5IEMxMzcuMjYxMzk4LDIyLjA2NzIxNTYgMTM5LjU5NjgwMywyMS45Nzc0NDM2IDE0MC43MTg4OTIsMjEuOTc3NDQzNiBaIi8+ICAgIDxwYXRoIGZpbGw9IiMwMDY2QTEiIGQ9Ik0xNzIuOTU4NDMxLDAuMzYwMzMzMzkzIEMxNjYuNTU0MzEyLDAuMzYwMzMzMzkzIDE2MS4zNzI2MzIsNS40NjgzNjMxNyAxNjEuMzcyNjMyLDExLjc2MTM4NCBDMTYxLjM3MjYzMiwxOC4wNTQ0MDQ5IDE2Ni41NjM0MzUsMjMuMTYyNDM0NyAxNzIuOTU4NDMxLDIzLjE2MjQzNDcgQzE3OS4zNjI1NSwyMy4xNjI0MzQ3IDE4NC41NDQyMywxOC4wNTQ0MDQ5IDE4NC41NDQyMywxMS43NjEzODQgQzE4NC41NDQyMyw1LjQ2ODM2MzE3IDE3OS4zNTM0MjcsMC4zNjAzMzMzOTMgMTcyLjk1ODQzMSwwLjM2MDMzMzM5MyBaIE0xNzMuMDA0MDQ0LDIwLjg2NDI3MDEgQzE2Ny45MDQ0NjgsMjAuODY0MjcwMSAxNjMuNzcxODk2LDE2Ljc5NzU5NjIgMTYzLjc3MTg5NiwxMS43NzkzMzg0IEMxNjMuNzcxODk2LDYuNzYxMDgwNzIgMTY3LjkwNDQ2OCwyLjY5NDQwNjc1IDE3My4wMDQwNDQsMi42OTQ0MDY3NSBDMTc4LjEwMzYyLDIuNjk0NDA2NzUgMTgyLjIzNjE5Myw2Ljc2MTA4MDcyIDE4Mi4yMzYxOTMsMTEuNzc5MzM4NCBDMTgyLjIzNjE5MywxNi43OTc1OTYyIDE3OC4wOTQ0OTgsMjAuODY0MjcwMSAxNzMuMDA0MDQ0LDIwLjg2NDI3MDEgWiBNMTc0LjAxNjY2MSw2LjI5NDI2NjA1IEwxNzEuNjA4Mjc1LDYuMjk0MjY2MDUgTDE3MS42MDgyNzUsMTIuODAyNzM5OCBMMTc4LjAwMzI3MSwxMi44MDI3Mzk4IEwxNzguMDAzMjcxLDEwLjUzMTUwNjkgTDE3NC4wMTY2NjEsMTAuNTMxNTA2OSBMMTc0LjAxNjY2MSw2LjI5NDI2NjA1IFoiLz4gIDwvZz48L3N2Zz4=); }

		.wrap.ru .logo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNDUiIGhlaWdodD0iNDciIHZpZXdCb3g9IjAgMCAyNDUgNDciPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtLjUzNiAtLjEyKSI+ICAgIDxwYXRoIGZpbGw9IiNGRkZGRkYiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTS45OTU4MjgxOTYgNC45Mjk4NzI3NUwyMS40NDcxMTc0IDQuOTI5ODcyNzUgMTkuODQ1MDY5MiAxMC4xMzUzNTUzIDcuNDY4MTAyNzkgMTAuMTM1MzU1MyA3LjQ2ODEwMjc5IDE2LjYwODAyMTkgOS41OTE5NjA5MyAxNi42MDgwMjE5QzEyLjk0MjUzMDIgMTYuNjA4MDIxOSAxNS45MTc3NjI1IDE3LjAyNzM3NzcgMTguMjMzODY2NSAxOC4yNDg5Nzk2IDIwLjk3MTA4MDIgMTkuNjUyOTEwMSAyMi42MjgwNTU3IDIyLjE4NzI3ODEgMjIuNjI4MDU1NyAyNi4xMjU1NzY3IDIyLjYyODA1NTcgMzEuNjU5MjUwOCAxOS4yNzc0ODY0IDM1LjgyNTQ2MDEgOS43Mzg0MzM5MSAzNS44MjU0NjAxTDEuMDA0OTgyNzYgMzUuODI1NDYwMSAxLjAwNDk4Mjc2IDQuOTI5ODcyNzUuOTk1ODI4MTk2IDQuOTI5ODcyNzV6TTkuODc1NzUyMzIgMzAuNTc0Mzk1NEMxNC4zMTU3MTQ0IDMwLjU3NDM5NTQgMTYuMTU1NzgxMSAyOS4xNzA0NjQ5IDE2LjE1NTc4MTEgMjYuMjE2NzQxIDE2LjE1NTc4MTEgMjQuNTMwMjAxMSAxNS40OTY2NTI3IDIzLjM1NDE4MTQgMTQuNDA3MjYgMjIuNjk3Nzk4MyAxMy4yMjYzMjE2IDIxLjk5NTgzMzEgMTEuNTIzNTczMyAyMS44MDQzODggOS41OTE5NjA5MyAyMS44MDQzODhMNy40NjgxMDI3OSAyMS44MDQzODggNy40NjgxMDI3OSAzMC41NzQzOTU0IDkuODc1NzUyMzIgMzAuNTc0Mzk1NCA5Ljg3NTc1MjMyIDMwLjU3NDM5NTR6TTI1LjY0OTA2MDggMTMuNjQ1MTgxNUwzMS44Mzc1NDQgMTMuNjQ1MTgxNSAzMS44Mzc1NDQgMjIuNTA2MzUzM0MzMS44Mzc1NDQgMjQuMzM4NzU2IDMxLjc5MTc3MTIgMjYuMTE2NDYwMiAzMS42NDUyOTgzIDI3LjM4MzY0NDNMMzEuNzgyNjE2NyAyNy4zODM2NDQzQzMyLjMwNDQyNjcgMjYuNDkwMjMzOSAzMy4zODQ2NjQ4IDI0LjYyMTM2NTQgMzQuNjY2MzAzNCAyMi43ODg5NjI2TDQxLjAzNzg3NzggMTMuNjQ1MTgxNSA0Ny4yNzIxMzM4IDEzLjY0NTE4MTUgNDcuMjcyMTMzOCAzNS44MTYzNDM3IDQxLjAzNzg3NzggMzUuODE2MzQzNyA0MS4wMzc4Nzc4IDI2Ljk1NTE3MkM0MS4wMzc4Nzc4IDI1LjEyMjc2OTIgNDEuMTI5NDIzNCAyMy4zNDUwNjUgNDEuMjc1ODk2NCAyMi4wNzc4ODFMNDEuMTM4NTc4IDIyLjA3Nzg4MUM0MC42MTY3NjggMjIuOTcxMjkxMyAzOS40ODE2MDI0IDI0Ljg0MDE1OTggMzguMjU0ODkxMyAyNi42NzI1NjI2TDMxLjg4MzMxNjkgMzUuODE2MzQzNyAyNS42NDkwNjA4IDM1LjgxNjM0MzcgMjUuNjQ5MDYwOCAxMy42NDUxODE1IDI1LjY0OTA2MDggMTMuNjQ1MTgxNXoiLz4gICAgPHBvbHlnb24gZmlsbD0iI0ZGRkZGRiIgZmlsbC1ydWxlPSJub256ZXJvIiBwb2ludHM9IjU2Ljc5MyAxOC44OTYgNTAuMTM4IDE4Ljg5NiA1MC4xMzggMTMuNjQ1IDcxLjIwMiAxMy42NDUgNjkuNTQ1IDE4Ljg5NiA2My4xNzQgMTguODk2IDYzLjE3NCAzNS44MTYgNTYuODAyIDM1LjgxNiA1Ni44MDIgMTguODk2Ii8+ICAgIDxwYXRoIGZpbGw9IiNGRkZGRkYiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTTczLjE4ODY5NTkgMTQuNTM4NTkxOUM3NS42NDIxMTgyIDEzLjY5MDc2MzcgNzguNjYzMTIzMyAxMy4wODkwNzkyIDgyLjAyMjg0NzIgMTMuMDg5MDc5MiA5MC4zMzUxODg1IDEzLjA4OTA3OTIgOTQuNTM3MTMyIDE3LjcyOTM0MyA5NC41MzcxMzIgMjQuNzEyNTI5NyA5NC41MzcxMzIgMzEuMzY3NTI1IDkwLjAwNTYyNDMgMzYuMjkwMzk4MSA4Mi43NzM1MjEyIDM2LjI5MDM5ODEgODEuNjg0MTI4NCAzNi4yOTAzOTgxIDgwLjYwMzg5MDIgMzYuMTUzNjUxNyA3OS41NjAyNzAzIDM1Ljg3MTA0MjNMNzkuNTYwMjcwMyA0Ni45MzgzOTA1IDczLjE4ODY5NTkgNDYuOTM4MzkwNSA3My4xODg2OTU5IDE0LjUzODU5MTkgNzMuMTg4Njk1OSAxNC41Mzg1OTE5ek04Mi4yOTc0ODQgMzEuMDg0OTE1NkM4Ni4xMjQwOTA1IDMxLjA4NDkxNTYgODguMDA5OTMwMSAyOC41OTYxMjk3IDg4LjAwOTkzMDEgMjQuNzEyNTI5NyA4OC4wMDk5MzAxIDIwLjQwMDQ1NzUgODUuNjQ4MDUzMyAxOC4yOTQ1NjE4IDgxLjcyOTkwMTIgMTguMjk0NTYxOCA4MC45MjQyOTk5IDE4LjI5NDU2MTggODAuMjY1MTcxNSAxOC4zNDAxNDM5IDc5LjU2MDI3MDMgMTguNTc3MTcxMUw3OS41NjAyNzAzIDMwLjYyOTA5NEM4MC40MTE2NDQ1IDMwLjkwMjU4NjkgODEuMjE3MjQ1OCAzMS4wODQ5MTU2IDgyLjI5NzQ4NCAzMS4wODQ5MTU2ek05Ny4wODIxIDEzLjY0NTE4MTVMMTAzLjI3MDU4MyAxMy42NDUxODE1IDEwMy4yNzA1ODMgMjIuNTA2MzUzM0MxMDMuMjcwNTgzIDI0LjMzODc1NiAxMDMuMjI0ODEgMjYuMTE2NDYwMiAxMDMuMDc4MzM3IDI3LjM4MzY0NDNMMTAzLjIxNTY1NiAyNy4zODM2NDQzQzEwMy43Mzc0NjYgMjYuNDkwMjMzOSAxMDQuODE3NzA0IDI0LjYyMTM2NTQgMTA2LjA5OTM0MiAyMi43ODg5NjI2TDExMi40NzA5MTcgMTMuNjQ1MTgxNSAxMTguNzA1MTczIDEzLjY0NTE4MTUgMTE4LjcwNTE3MyAzNS44MTYzNDM3IDExMi40NzA5MTcgMzUuODE2MzQzNyAxMTIuNDcwOTE3IDI2Ljk1NTE3MkMxMTIuNDcwOTE3IDI1LjEyMjc2OTIgMTEyLjU2MjQ2MyAyMy4zNDUwNjUgMTEyLjcwODkzNiAyMi4wNzc4ODFMMTEyLjU3MTYxNyAyMi4wNzc4ODFDMTEyLjA0OTgwNyAyMi45NzEyOTEzIDExMC45MTQ2NDIgMjQuODQwMTU5OCAxMDkuNjg3OTMgMjYuNjcyNTYyNkwxMDMuMzE2MzU2IDM1LjgxNjM0MzcgOTcuMDgyMSAzNS44MTYzNDM3IDk3LjA4MjEgMTMuNjQ1MTgxNXpNMTIzLjEwODUxNyAxMy42NDUxODE1TDEyOS40ODAwOTEgMTMuNjQ1MTgxNSAxMjkuNDgwMDkxIDIyLjEzMjU3OTUgMTMxLjg0MTk2OCAyMi4xMzI1Nzk1QzEzNC41MzM0MDkgMjIuMTMyNTc5NSAxMzQuNDQxODYzIDE3LjYyOTA2MjIgMTM2LjQxOTI0OCAxNS4wMDM1Mjk5IDEzNy4yNzA2MjMgMTMuODI3NTEwMiAxMzguNTg4ODc5IDEzLjA3OTk2MjggMTQwLjYyMTE5MiAxMy4wNzk5NjI4IDE0MS4yODAzMiAxMy4wNzk5NjI4IDE0Mi40NjEyNTkgMTMuMTcxMTI3MSAxNDMuMTc1MzE0IDEzLjQwODE1NDNMMTQzLjE3NTMxNCAxOC43OTU5NjU1QzE0Mi43OTk5NzcgMTguNjU5MjE5IDE0Mi4zMjM5NCAxOC41NTg5MzgzIDE0MS44NTcwNTggMTguNTU4OTM4MyAxNDEuMTUyMTU2IDE4LjU1ODkzODMgMTQwLjY3NjExOSAxOC43OTU5NjU1IDE0MC4zMDA3ODIgMTkuMjYwOTAzNSAxMzkuMTY1NjE3IDIwLjY2NDgzNCAxMzguOTgyNTI1IDIzLjUyNzM5MzYgMTM3LjMyNTU1IDI0LjU1NzU1MDRMMTM3LjMyNTU1IDI0LjY0ODcxNDdDMTM4LjQxNDk0MyAyNS4wNjgwNzA2IDEzOS4yMTEzODkgMjUuODcwMzE2NiAxMzkuODc5NjcyIDI3LjI3NDI0NzFMMTQzLjg5ODUyNSAzNS44MDcyMjcyIDEzNy4wMDUxNCAzNS44MDcyMjcyIDEzNC4zNTk0NzIgMjguODY5NjIyNkMxMzMuNzkxODg5IDI3LjUxMTI3NDMgMTMzLjIyNDMwNyAyNi45MDA0NzM0IDEzMi42MTA5NTEgMjYuOTAwNDczNEwxMjkuNDk4NCAyNi45MDA0NzM0IDEyOS40OTg0IDM1LjgwNzIyNzIgMTIzLjEyNjgyNiAzNS44MDcyMjcyIDEyMy4xMjY4MjYgMTMuNjQ1MTgxNSAxMjMuMTA4NTE3IDEzLjY0NTE4MTV6TTE0NC44NTA1OTkgMjQuODQ5Mjc2MkMxNDQuODUwNTk5IDE3LjgyMDUwNzMgMTUwLjMyNTAyNiAxMy4wNzk5NjI4IDE1Ni44OTgwMDEgMTMuMDc5OTYyOCAxNTkuOTE5MDA2IDEzLjA3OTk2MjggMTYxLjkwNTU0NiAxMy45Mjc3OTA5IDE2My4wODY0ODQgMTQuNjc1MzM4M0wxNjMuMDg2NDg0IDE5Ljk3MTk4NTJDMTYxLjQ4NDQzNiAxOC43NTAzODM0IDE1OS43ODE2ODggMTguMDk0MDAwMyAxNTcuNjEyMDU3IDE4LjA5NDAwMDMgMTUzLjY5MzkwNSAxOC4wOTQwMDAzIDE1MS4zNzc4MDEgMjEuMDQ3NzI0MiAxNTEuMzc3ODAxIDI0Ljc5NDU3NzYgMTUxLjM3NzgwMSAyOC45Njk5MDM0IDE1My43Mzk2NzggMzEuMjEyNTQ1NiAxNTcuMzI4MjY2IDMxLjIxMjU0NTYgMTU5LjMxNDgwNSAzMS4yMTI1NDU2IDE2MC44MjUzMDggMzAuNjQ3MzI2OCAxNjIuNDczMTI5IDI5LjcwODMzNDRMMTY0LjI2NzQyMyAzNC4wMjA0MDY2QzE2Mi40MjczNTYgMzUuMzMzMTcyOCAxNTkuNTQzNjY5IDM2LjI3MjE2NTMgMTU2LjQzMTExOSAzNi4yNzIxNjUzIDE0OS4wMDY3NyAzNi4yOTAzOTgxIDE0NC44NTA1OTkgMzEuMzY3NTI1IDE0NC44NTA1OTkgMjQuODQ5Mjc2MnoiLz4gICAgPHBhdGggZmlsbD0iIzIxNUY5OCIgZmlsbC1ydWxlPSJub256ZXJvIiBkPSJNMTgzLjE5OTA1NSwxMi44MzM4MTkxIEMxODMuMTk5MDU1LDEwLjA3MTU0MDMgMTgwLjkwMTI2LDkuMDk2MDgyMDggMTc4LjQ1Njk5Miw5LjA5NjA4MjA4IEMxNzUuMTc5NjU5LDkuMDk2MDgyMDggMTcyLjQ5NzM3MywxMC4xNjI3MDQ2IDE3MC4wMDczMzMsMTEuMjc0OTA5MyBMMTY4LjI5NTQzLDYuMTc4ODIzOSBDMTcxLjA3ODQxNiw0LjkyMDc1NjMxIDE3NC45NDE2NDEsMy41MDc3MDkzOSAxNzkuNTM3MjMsMy41MDc3MDkzOSBDMTg2LjcyMzU2MSwzLjUwNzcwOTM5IDE5MC4xOTMxMzksNy4wOTk1ODM1MSAxOTAuMTkzMTM5LDEyLjE0MDk3MDMgQzE5MC4xOTMxMzksMjAuOTY1Njc2MyAxNzcuOTgwOTU1LDIzLjQ0NTM0NTcgMTc1LjkyMTE3OSwzMC4xODIzODg4IEwxOTAuNjMyNTU4LDMwLjE4MjM4ODggTDE5MC42MzI1NTgsMzUuODA3MjI3MiBMMTY3LjIyNDM0NiwzNS44MDcyMjcyIEMxNjguNTQyNjAzLDIwLjA2MzE0OTUgMTgzLjE5OTA1NSwxOS4xMzMyNzM1IDE4My4xOTkwNTUsMTIuODMzODE5MSBaIi8+ICAgIDxwYXRoIGZpbGw9IiMyMTVGOTgiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTTE4OS45NzM0MywyMy45ODMyMTUyIEwyMDYuMDk0NjEyLDMuNjE3MTA2NTcgTDIxMC44ODI0NDcsMy42MTcxMDY1NyBMMjEwLjg4MjQ0NywyMi44NzEwMTA1IEwyMTUuNjcwMjgzLDIyLjg3MTAxMDUgTDIxNS42NzAyODMsMjguMjA0MTIzMSBMMjEwLjg4MjQ0NywyOC4yMDQxMjMxIEwyMTAuODgyNDQ3LDM1LjgxNjM0MzcgTDIwNC4zODI3MDksMzUuODE2MzQzNyBMMjA0LjM4MjcwOSwyOC4yMDQxMjMxIEwxODkuOTY0Mjc1LDI4LjIwNDEyMzEgTDE4OS45NjQyNzUsMjMuOTgzMjE1MiBMMTg5Ljk3MzQzLDIzLjk4MzIxNTIgWiBNMjAxLjQ1MzI0OSwyMi44NzEwMTA1IEwyMDQuMzgyNzA5LDIyLjg3MTAxMDUgTDIwNC4zODI3MDksMTkuNDI0OTk5MyBDMjA0LjM4MjcwOSwxNi45NTQ0NDYzIDIwNC41NzQ5NTUsMTQuMDkxODg2NyAyMDQuNjc1NjU1LDEzLjQ2Mjg1MjkgTDE5Ny4zOTc3NzksMjMuMDE2ODczNCBDMTk3Ljk4MzY3MSwyMi45NzEyOTEzIDIwMC4zMjcyMzgsMjIuODcxMDEwNSAyMDEuNDUzMjQ5LDIyLjg3MTAxMDUgWiIvPiAgICA8cGF0aCBmaWxsPSIjMDA2NkExIiBkPSJNMjMzLjgwNTQ2OCwwLjkxODY0Mjc1NiBDMjI3LjM3ODk2NiwwLjkxODY0Mjc1NiAyMjIuMTc5MTc1LDYuMTA1ODkyNDUgMjIyLjE3OTE3NSwxMi40OTY1MTExIEMyMjIuMTc5MTc1LDE4Ljg4NzEyOTggMjI3LjM4ODEyMSwyNC4wNzQzNzk1IDIzMy44MDU0NjgsMjQuMDc0Mzc5NSBDMjQwLjIzMTk3LDI0LjA3NDM3OTUgMjQ1LjQzMTc2LDE4Ljg4NzEyOTggMjQ1LjQzMTc2LDEyLjQ5NjUxMTEgQzI0NS40MzE3Niw2LjEwNTg5MjQ1IDI0MC4yMjI4MTUsMC45MTg2NDI3NTYgMjMzLjgwNTQ2OCwwLjkxODY0Mjc1NiBaIE0yMzMuODUxMjQxLDIxLjc0MDU3MyBDMjI4LjczMzg0MSwyMS43NDA1NzMgMjI0LjU4NjgyNSwxNy42MTA4Mjk0IDIyNC41ODY4MjUsMTIuNTE0NzQ0IEMyMjQuNTg2ODI1LDcuNDE4NjU4NjMgMjI4LjczMzg0MSwzLjI4ODkxNTAyIDIzMy44NTEyNDEsMy4yODg5MTUwMiBDMjM4Ljk2ODY0LDMuMjg4OTE1MDIgMjQzLjExNTY1Niw3LjQxODY1ODYzIDI0My4xMTU2NTYsMTIuNTE0NzQ0IEMyNDMuMTE1NjU2LDE3LjYxMDgyOTQgMjM4Ljk1OTQ4NiwyMS43NDA1NzMgMjMzLjg1MTI0MSwyMS43NDA1NzMgWiBNMjM0Ljg2NzM5Nyw2Ljk1MzcyMDYxIEwyMzIuNDUwNTkzLDYuOTUzNzIwNjEgTDIzMi40NTA1OTMsMTMuNTYzMTMzNyBMMjM4Ljg2Nzk0LDEzLjU2MzEzMzcgTDIzOC44Njc5NCwxMS4yNTY2NzY0IEwyMzQuODY3Mzk3LDExLjI1NjY3NjQgTDIzNC44NjczOTcsNi45NTM3MjA2MSBaIi8+ICA8L2c+PC9zdmc+); }

		.wrap.ua .logo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMTgiIGhlaWdodD0iNDciIHZpZXdCb3g9IjAgMCAyMTggNDciPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj4gICAgPGcgZmlsbD0iI0ZGRkZGRiIgZmlsbC1ydWxlPSJub256ZXJvIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwIDEuODYpIj4gICAgICA8cGF0aCBkPSJNLjg5NTY0MDI2NiAyLjYwNzU4NDU5TDIxLjI0OTM0NzMgMi42MDc1ODQ1OSAxOS42NTQ5NDMyIDcuODE2NzM2NDYgNy4zMzcwMzI3NiA3LjgxNjczNjQ2IDcuMzM3MDMyNzYgMTQuMjkzOTY1NiA5LjQ1MDc1NzA0IDE0LjI5Mzk2NTZDMTIuNzg1MzM5MyAxNC4yOTM5NjU2IDE1Ljc0NjM3NTQgMTQuNzEzNjE3IDE4LjA1MTQyODIgMTUuOTM2MDggMjAuNzc1NTgxNSAxNy4zNDEwMDAxIDIyLjQyNDY1MDggMTkuODc3MTU0NiAyMi40MjQ2NTA4IDIzLjgxODIyOTIgMjIuNDI0NjUwOCAyOS4zNTU4MDQgMTkuMDkwMDY4NiAzMy41MjQ5NSA5LjU5NjUzMTEyIDMzLjUyNDk1TC45MDQ3NTExNDYgMzMuNTI0OTUuOTA0NzUxMTQ2IDIuNjA3NTg0NTkuODk1NjQwMjY2IDIuNjA3NTg0NTl6TTkuNzMzMTk0MzMgMjguMjcwMTgzOUMxNC4xNTE5NzE0IDI4LjI3MDE4MzkgMTUuOTgzMjU4MyAyNi44NjUyNjM4IDE1Ljk4MzI1ODMgMjMuOTA5NDU3OCAxNS45ODMyNTgzIDIyLjIyMTcyOTEgMTUuMzI3Mjc0OSAyMS4wNDQ4ODA0IDE0LjI0MzA4MDIgMjAuMzg4MDM0NyAxMy4wNjc3NzY2IDE5LjY4NTU3NDYgMTEuMzczMTUyOCAxOS40OTM5OTQ2IDkuNDUwNzU3MDQgMTkuNDkzOTk0Nkw3LjMzNzAzMjc2IDE5LjQ5Mzk5NDYgNy4zMzcwMzI3NiAyOC4yNzAxODM5IDkuNzMzMTk0MzMgMjguMjcwMTgzOSA5LjczMzE5NDMzIDI4LjI3MDE4Mzl6TTI1LjYyMjU2OTkgNC40ODY4OTMzMkMyNS42MjI1Njk5IDIuNDcwNzQxNzIgMjcuMjcxNjM5My44Mjg2MjczMDEgMjkuNDc2NDcyMy44Mjg2MjczMDEgMzEuNjM1NzUxLjgyODYyNzMwMSAzMy4yODQ4MjA0IDIuMzc5NTEzMTUgMzMuMjg0ODIwNCA0LjQ4Njg5MzMyIDMzLjI4NDgyMDQgNi41NDg2NTkyMSAzMS42MzU3NTEgOC4yMzYzODc5MiAyOS40NzY0NzIzIDguMjM2Mzg3OTIgMjcuMjcxNjM5MyA4LjIzNjM4NzkyIDI1LjYyMjU2OTkgNi41OTQyNzM1IDI1LjYyMjU2OTkgNC40ODY4OTMzMnpNMjYuMjc4NTUzMyAxMS4zMzgxNTk2TDMyLjYxOTcyNjEgMTEuMzM4MTU5NiAzMi42MTk3MjYxIDMzLjUyNDk1IDI2LjI3ODU1MzMgMzMuNTI0OTUgMjYuMjc4NTUzMyAxMS4zMzgxNTk2eiIvPiAgICAgIDxwb2x5Z29uIHBvaW50cz0iNDIuMTY4IDE2LjU5MyAzNS41NDQgMTYuNTkzIDM1LjU0NCAxMS4zMzggNTYuNTA4IDExLjMzOCA1NC44NTkgMTYuNTkzIDQ4LjUxOCAxNi41OTMgNDguNTE4IDMzLjUyNSA0Mi4xNzcgMzMuNTI1IDQyLjE3NyAxNi41OTMiLz4gICAgICA8cGF0aCBkPSJNNTguOTA0NjE2MyAxMi4yMjMwNzY4QzYxLjM0NjMzMjIgMTEuMzc0NjUxIDY0LjM1MjkyMjggMTAuNzcyNTQyNCA2Ny42OTY2MTU5IDEwLjc3MjU0MjQgNzUuOTY5Mjk1NCAxMC43NzI1NDI0IDgwLjE1MTE4OTYgMTUuNDE2MDc3MSA4MC4xNTExODk2IDIyLjQwNDE4NjMgODAuMTUxMTg5NiAyOS4wNjM4NzI1IDc1LjY0MTMwMzcgMzMuOTkwMjE1OCA2OC40NDM3MDgxIDMzLjk5MDIxNTggNjcuMzU5NTEzNCAzMy45OTAyMTU4IDY2LjI4NDQyOTUgMzMuODUzMzcyOSA2NS4yNDU3ODkxIDMzLjU3MDU2NDNMNjUuMjQ1Nzg5MSA0NC42NDU3MTM4IDU4LjkwNDYxNjMgNDQuNjQ1NzEzOCA1OC45MDQ2MTYzIDEyLjIyMzA3NjggNTguOTA0NjE2MyAxMi4yMjMwNzY4ek02Ny45Njk5NDI0IDI4Ljc4MTA2MzlDNzEuNzc4MjkwNCAyOC43ODEwNjM5IDczLjY1NTEzMTggMjYuMjkwNTIzNyA3My42NTUxMzE4IDIyLjQwNDE4NjMgNzMuNjU1MTMxOCAxOC4wODkwNzQ1IDcxLjMwNDUyNDYgMTUuOTgxNjk0MyA2Ny40MDUwNjc4IDE1Ljk4MTY5NDMgNjYuNjAzMzEwMyAxNS45ODE2OTQzIDY1Ljk0NzMyNjkgMTYuMDI3MzA4NiA2NS4yNDU3ODkxIDE2LjI2NDUwMjlMNjUuMjQ1Nzg5MSAyOC4zMjQ5MjFDNjYuMDkzMTAxIDI4LjU5ODYwNjggNjYuODk0ODU4NSAyOC43ODEwNjM5IDY3Ljk2OTk0MjQgMjguNzgxMDYzOXpNODIuODc1MzQyOCA0LjQ4Njg5MzMyQzgyLjg3NTM0MjggMi40NzA3NDE3MiA4NC41MjQ0MTIyLjgyODYyNzMwMSA4Ni43MjkyNDUzLjgyODYyNzMwMSA4OC44ODg1MjM5LjgyODYyNzMwMSA5MC41Mzc1OTMzIDIuMzc5NTEzMTUgOTAuNTM3NTkzMyA0LjQ4Njg5MzMyIDkwLjUzNzU5MzMgNi41NDg2NTkyMSA4OC44ODg1MjM5IDguMjM2Mzg3OTIgODYuNzI5MjQ1MyA4LjIzNjM4NzkyIDg0LjUxNTMwMTMgOC4yMzYzODc5MiA4Mi44NzUzNDI4IDYuNTk0MjczNSA4Mi44NzUzNDI4IDQuNDg2ODkzMzJ6TTgzLjUzMTMyNjIgMTEuMzM4MTU5Nkw4OS44NzI0OTkgMTEuMzM4MTU5NiA4OS44NzI0OTkgMzMuNTI0OTUgODMuNTMxMzI2MiAzMy41MjQ5NSA4My41MzEzMjYyIDExLjMzODE1OTZ6TTk0LjMzNjgzMDUgMTEuMzM4MTU5NkwxMDAuNjc4MDAzIDExLjMzODE1OTYgMTAwLjY3ODAwMyAxOS44MzE1NDAzIDEwMy4wMjg2MSAxOS44MzE1NDAzQzEwNS43MDcyMDkgMTkuODMxNTQwMyAxMDUuNjE2MSAxNS4zMjQ4NDg1IDEwNy41ODQwNTEgMTIuNjk3NDY1NCAxMDguNDMxMzYzIDExLjUyMDYxNjggMTA5Ljc0MzMyOSAxMC43NzI1NDI0IDExMS43NjU5NDUgMTAuNzcyNTQyNCAxMTIuNDIxOTI4IDEwLjc3MjU0MjQgMTEzLjU5NzIzMiAxMC44NjM3NzEgMTE0LjMwNzg4IDExLjEwMDk2NTNMMTE0LjMwNzg4IDE2LjQ5MjU3NDNDMTEzLjkzNDMzNCAxNi4zNTU3MzE1IDExMy40NjA1NjkgMTYuMjU1MzggMTEyLjk5NTkxNCAxNi4yNTUzOCAxMTIuMjk0Mzc2IDE2LjI1NTM4IDExMS44MjA2MSAxNi40OTI1NzQzIDExMS40NDcwNjQgMTYuOTU3ODQwMSAxMTAuMzE3MzE1IDE4LjM2Mjc2MDIgMTEwLjEzNTA5NyAyMS4yMjczMzc2IDEwOC40ODYwMjggMjIuMjU4MjIwNUwxMDguNDg2MDI4IDIyLjM0OTQ0OTFDMTA5LjU3MDIyMyAyMi43NjkxMDA2IDExMC4zNjI4NjkgMjMuNTcxOTEyMSAxMTEuMDI3OTYzIDI0Ljk3NjgzMjJMMTE1LjAyNzY0IDMzLjUxNTgyNzIgMTA4LjE2NzE0NyAzMy41MTU4MjcyIDEwNS41MzQxMDMgMjYuNTczMzMyM0MxMDQuOTY5MjI4IDI1LjIxNDAyNjUgMTA0LjQwNDM1MyAyNC42MDI3OTUgMTAzLjc5MzkyNCAyNC42MDI3OTVMMTAwLjY5NjIyNSAyNC42MDI3OTUgMTAwLjY5NjIyNSAzMy41MTU4MjcyIDk0LjM1NTA1MjIgMzMuNTE1ODI3MiA5NC4zNTUwNTIyIDExLjMzODE1OTYgOTQuMzM2ODMwNSAxMS4zMzgxNTk2ek0xMTYuNTIxODI0IDIyLjU1MDE1MkMxMTYuNTIxODI0IDE1LjUxNjQyODUgMTIxLjk3MDEzMSAxMC43NzI1NDI0IDEyOC41MTE3NDMgMTAuNzcyNTQyNCAxMzEuNTE4MzM0IDEwLjc3MjU0MjQgMTMzLjQ5NTM5NSAxMS42MjA5NjgyIDEzNC42NzA2OTggMTIuMzY5MDQyNkwxMzQuNjcwNjk4IDE3LjY2OTQyM0MxMzMuMDc2Mjk0IDE2LjQ0Njk2IDEzMS4zODE2NyAxNS43OTAxMTQzIDEyOS4yMjIzOTIgMTUuNzkwMTE0MyAxMjUuMzIyOTM1IDE1Ljc5MDExNDMgMTIzLjAxNzg4MiAxOC43NDU5MjAyIDEyMy4wMTc4ODIgMjIuNDk1NDE0OCAxMjMuMDE3ODgyIDI2LjY3MzY4MzggMTI1LjM2ODQ4OSAyOC45MTc5MDY4IDEyOC45Mzk5NTUgMjguOTE3OTA2OCAxMzAuOTE3MDE2IDI4LjkxNzkwNjggMTMyLjQyMDMxMSAyOC4zNTIyODk2IDEzNC4wNjAyNjkgMjcuNDEyNjM1MkwxMzUuODQ2MDAyIDMxLjcyNzc0N0MxMzQuMDE0NzE1IDMzLjA0MTQzODYgMTMxLjE0NDc4OCAzMy45ODEwOTI5IDEyOC4wNDcwODggMzMuOTgxMDkyOSAxMjAuNjU4MTY0IDMzLjk5MDIxNTggMTE2LjUyMTgyNCAyOS4wNjM4NzI1IDExNi41MjE4MjQgMjIuNTUwMTUyeiIvPiAgICA8L2c+ICAgIDxwYXRoIGZpbGw9IiMyMTVGOTgiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTTE1NS41NzEwNTgsMTIuMzc2NDU1NiBDMTU1LjU3MTA1OCw5LjYxMjIyOTY4IDE1My4yODQyMjcsOC42MzYwODM4OCAxNTAuODUxNjIyLDguNjM2MDgzODggQzE0Ny41ODk5MjcsOC42MzYwODM4OCAxNDQuOTIwNDM5LDkuNzAzNDU4MjYgMTQyLjQ0MjI3OSwxMC44MTY0NDY5IEwxNDAuNzM4NTQ1LDUuNzE2NzY5MzUgQzE0My41MDgyNTIsNC40NTc4MTQ5NiAxNDcuMzUzMDQ0LDMuMDQzNzcxOTggMTUxLjkyNjcwNiwzLjA0Mzc3MTk4IEMxNTkuMDc4NzQ3LDMuMDQzNzcxOTggMTYyLjUzMTc3MSw2LjYzODE3OCAxNjIuNTMxNzcxLDExLjY4MzExODQgQzE2Mi41MzE3NzEsMjAuNTE0MDQ0OSAxNTAuMzc3ODU2LDIyLjk5NTQ2MjIgMTQ4LjMyNzkwOCwyOS43MzcyNTQyIEwxNjIuOTY5MDkzLDI5LjczNzI1NDIgTDE2Mi45NjkwOTMsMzUuMzY2MDU3NSBMMTM5LjY3MjU3MiwzNS4zNjYwNTc1IEMxNDAuOTg0NTM5LDE5LjYxMDg4MTkgMTU1LjU3MTA1OCwxOC42ODk0NzMzIDE1NS41NzEwNTgsMTIuMzc2NDU1NiBaIi8+ICAgIDxwYXRoIGZpbGw9IiMyMTVGOTgiIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTTE2Mi4zMTMxMSwyMy41NDI4MzM3IEwxNzguMzU3MzcsMy4xNjIzNjkxNCBMMTgzLjEyMjM2MSwzLjE2MjM2OTE0IEwxODMuMTIyMzYxLDIyLjQyOTg0NSBMMTg3Ljg4NzM1MSwyMi40Mjk4NDUgTDE4Ny44ODczNTEsMjcuNzY2NzE2OSBMMTgzLjEyMjM2MSwyNy43NjY3MTY5IEwxODMuMTIyMzYxLDM1LjM4NDMwMzMgTDE3Ni42NTM2MzYsMzUuMzg0MzAzMyBMMTc2LjY1MzYzNiwyNy43NjY3MTY5IEwxNjIuMzAzOTk5LDI3Ljc2NjcxNjkgTDE2Mi4zMDM5OTksMjMuNTQyODMzNyBMMTYyLjMxMzExLDIzLjU0MjgzMzcgWiBNMTczLjczODE1NCwyMi40MjA3MjIyIEwxNzYuNjUzNjM2LDIyLjQyMDcyMjIgTDE3Ni42NTM2MzYsMTguOTcyMjgxOSBDMTc2LjY1MzYzNiwxNi40OTk5ODc0IDE3Ni44NDQ5NjQsMTMuNjM1NDEgMTc2Ljk0NTE4NCwxMy4wMDU5MzI4IEwxNjkuNzAyMDM0LDIyLjU2NjY4NzkgQzE3MC4yODUxMywyMi41MjEwNzM2IDE3Mi42MTc1MTYsMjIuNDIwNzIyMiAxNzMuNzM4MTU0LDIyLjQyMDcyMjIgWiIvPiAgICA8cGF0aCBmaWxsPSIjMDA2NkExIiBkPSJNMjA1LjkzNjAwNSwwLjQ1Mjg4MDMzOCBDMTk5LjU0MDE2NywwLjQ1Mjg4MDMzOCAxOTQuMzY1MTg3LDUuNjQzNzg2NDkgMTk0LjM2NTE4NywxMi4wMzg5MDk5IEMxOTQuMzY1MTg3LDE4LjQzNDAzMzMgMTk5LjU0OTI3OCwyMy42MjQ5Mzk0IDIwNS45MzYwMDUsMjMuNjI0OTM5NCBDMjEyLjMzMTg0NCwyMy42MjQ5Mzk0IDIxNy41MDY4MjQsMTguNDM0MDMzMyAyMTcuNTA2ODI0LDEyLjAzODkwOTkgQzIxNy41MDY4MjQsNS42NDM3ODY0OSAyMTIuMzIyNzMzLDAuNDUyODgwMzM4IDIwNS45MzYwMDUsMC40NTI4ODAzMzggWiBNMjA1Ljk4MTU2LDIxLjI4OTQ4NzggQzIwMC44ODg1NzgsMjEuMjg5NDg3OCAxOTYuNzYxMzQ5LDE3LjE1NjgzMzIgMTk2Ljc2MTM0OSwxMi4wNTcxNTU2IEMxOTYuNzYxMzQ5LDYuOTU3NDc4MDMgMjAwLjg4ODU3OCwyLjgyNDgyMzM5IDIwNS45ODE1NiwyLjgyNDgyMzM5IEMyMTEuMDc0NTQyLDIuODI0ODIzMzkgMjE1LjIwMTc3MSw2Ljk1NzQ3ODAzIDIxNS4yMDE3NzEsMTIuMDU3MTU1NiBDMjE1LjIwMTc3MSwxNy4xNTY4MzMyIDIxMS4wNjU0MzEsMjEuMjg5NDg3OCAyMDUuOTgxNTYsMjEuMjg5NDg3OCBaIE0yMDYuOTkyODY4LDYuNDkyMjEyMjcgTDIwNC41ODc1OTUsNi40OTIyMTIyNyBMMjA0LjU4NzU5NSwxMy4xMDYyODQzIEwyMTAuOTc0MzIyLDEzLjEwNjI4NDMgTDIxMC45NzQzMjIsMTAuNzk4MjAxMiBMMjA2Ljk5Mjg2OCwxMC43OTgyMDEyIEwyMDYuOTkyODY4LDYuNDkyMjEyMjcgWiIvPiAgPC9nPjwvc3ZnPg==); }

		.buslogo {
			width: 255px;
			display: block;
			height: 46px;
			background-repeat: no-repeat;
			/*background-size:cover;*/
		}

		.wrap.en .buslogo,
		.wrap.de .buslogo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI5OS42NTYiIGhlaWdodD0iMzMuNzUiIHZpZXdCb3g9IjAgMCA5OS42NTYgMzMuNzUiPiAgPGRlZnM+ICAgIDxzdHlsZT4gICAgICAuY2xzLTEgeyAgICAgICAgZmlsbDogI2ZmZjsgICAgICAgIGZpbGwtcnVsZTogZXZlbm9kZDsgICAgICB9ICAgIDwvc3R5bGU+ICA8L2RlZnM+ICA8cGF0aCBpZD0iQml0cml4X2NvcHkiIGRhdGEtbmFtZT0iQml0cml4IGNvcHkiIGNsYXNzPSJjbHMtMSIgZD0iTTE4MS4wMTQsMTE3LjMxNGgxMC4xMzVjNy4zNDgsMCwxMS4xLTQuNDY3LDExLjEtOS4zNjZhNy42MjEsNy42MjEsMCwwLDAtNS45NTYtNy42Mzd2LTAuMWE3LjEzLDcuMTMsMCwwLDAsMy44NDMtNi41MzJjMC00LjEzMS0zLjA3NC04LjAyMS05Ljg0Ny04LjAyMWgtOS4yN3YzMS42NTNabTUuNzY0LTQuNzU1di04LjkzNEgxOTEuMWMzLjIxOCwwLDUuMjgzLDEuNjMzLDUuMjgzLDQuMzIzLDAsMy4yMTgtMi4wNjUsNC42MTEtNS45MDgsNC42MTFoLTMuN1ptMC0xMy42ODlWOTAuNDE2aDIuNjljMy40NTgsMCw0Ljk0NywxLjg3Myw0Ljk0Nyw0LjIyNywwLDIuNDUtMS42ODEsNC4yMjctNC45LDQuMjI3aC0yLjczOFptMTkuMjQ5LDE4LjQ0NGg1Ljc2NFY5NC42aC01Ljc2NHYyMi43MTlaTTIwOC45MDksOTAuOWEzLjQzNSwzLjQzNSwwLDEsMC0zLjUwNi0zLjQxQTMuMzg2LDMuMzg2LDAsMCwwLDIwOC45MDksOTAuOVptMTUuODQ2LDI2LjlhMTIuOTkyLDEyLjk5MiwwLDAsMCw2LjYyOS0xLjc3N2wtMS43My0zLjkzOGE2LjQyOSw2LjQyOSwwLDAsMS0zLjQ1OCwxLjFjLTEuNDg5LDAtMi4yMDktLjc2OC0yLjIwOS0yLjg4MlY5OS4wNjJoNS40NzVsMS4zOTMtNC40NjdoLTYuODY4Vjg3LjYzMWwtNS43MTYsMS42ODFWOTQuNmgtNC4wODN2NC40NjdoNC4wODN2MTIuNjMyQzIxOC4yNzEsMTE1LjQ4OSwyMjAuNzIsMTE3Ljc5NCwyMjQuNzU1LDExNy43OTRabTguNjM4LS40OGg1LjcxNlYxMDEuMjcyYzEuODczLTEuNjM0LDMuMTIyLTIuMjU4LDQuNjExLTIuMjU4YTQuODU2LDQuODU2LDAsMCwxLDIuNTk0Ljc2OWwyLjAxNy00Ljg1MWE1Ljk0Nyw1Ljk0NywwLDAsMC0zLjIxOC0uOTEzYy0yLjMwNiwwLTQuMTc5LDEuMDU3LTYuMjQ0LDMuMTIyTDIzOC4yNDQsOTQuNmgtNC44NTF2MjIuNzE5Wm0xNi40NjgsMGg1Ljc2NFY5NC42aC01Ljc2NHYyMi43MTlaTTI1Mi43NDMsOTAuOWEzLjQzNSwzLjQzNSwwLDEsMC0zLjUwNi0zLjQxQTMuMzg2LDMuMzg2LDAsMCwwLDI1Mi43NDMsOTAuOVptNC44NDcsMjYuNDE3aDZsNS41NzItNy41ODksNS40NzUsNy41ODloNmwtOC40LTExLjQzMUwyODAuNTQ5LDk0LjZoLTUuOTU2bC01LjM3OSw3LjQtNS4zMzItNy40aC02bDguMjE0LDExLjI4OFoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xODEgLTg0LjAzMSkiLz48L3N2Zz4=); }

		.wrap.ru .buslogo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMzAuNjU2IiBoZWlnaHQ9IjQzLjMxMyIgdmlld0JveD0iMCAwIDIzMC42NTYgNDMuMzEzIj4gIDxkZWZzPiAgICA8c3R5bGU+ICAgICAgLmNscy0xIHsgICAgICAgIGZpbGw6ICNmZmY7ICAgICAgICBmaWxsLXJ1bGU6IGV2ZW5vZGQ7ICAgICAgfSAgICA8L3N0eWxlPiAgPC9kZWZzPiAgPHBhdGggaWQ9Il8xQy3QkdC40YLRgNC40LrRgSIgZGF0YS1uYW1lPSIxQy3QkdC40YLRgNC40LrRgSIgY2xhc3M9ImNscy0xIiBkPSJNMTg3Ljg4MiwxMTcuMzE0aDUuNjY4Vjg1LjQyMWgtNC4zNzFsLTEwLjU2Nyw0LjgsMS45MjIsNC40NjcsNy4zNDgtMy4zMTR2MjUuOTM3Wm0zNi40LTYuNThhMTQuOTQ2LDE0Ljk0NiwwLDAsMS03Ljc4MSwyLjIwOWMtNi40ODQsMC0xMC41MTktNC42MTEtMTAuNTE5LTExLjIzOSwwLTYuMiwzLjctMTEuNDgsMTAuNzExLTExLjQ4YTE3LjAxNiwxNy4wMTYsMCwwLDEsOC4xNjUsMi4wNjVWODYuOTFhMjEuMTYxLDIxLjE2MSwwLDAsMC04LjMwOS0xLjUzN2MtMTAuMTgzLDAtMTYuNzE1LDcuMy0xNi43MTUsMTYuNDc1LDAsOS4yNyw1LjYyLDE1Ljk0NiwxNS45OTQsMTUuOTQ2YTIwLjI3NCwyMC4yNzQsMCwwLDAsMTAuMDM5LTIuNVptNC40NTUtMi45M0gyNDEuMzdWMTAzSDIyOC43Mzd2NC44Wm0yMy41NzcsNC43NTV2LTkuOTQzaDIuNGExMC41NjksMTAuNTY5LDAsMCwxLDUuMTM5Ljk2MSw0LjI0Miw0LjI0MiwwLDAsMSwyLjAxNyw0LjAzNWMwLDMuMzYyLTIuMDY1LDQuOTQ3LTYuNzcyLDQuOTQ3aC0yLjc4NlptLTUuODEyLDQuNzU1aDguNDU0YzkuMzY2LDAsMTIuNzc2LTQuMTMxLDEyLjc3Ni05Ljg0NiwwLTMuODkxLTEuNjMzLTYuNDg1LTQuNDY3LTcuOTc0LTIuMjU3LTEuMi01LjE4Ny0xLjU4NS04LjY0NS0xLjU4NWgtMi4zMDZWOTAuMzY4aDEyLjY4bDEuNTM3LTQuNzA3SDI0Ni41djMxLjY1M1ptMjUuMDYyLDBoNS41NzFsNy4xMDktMTAuMjMxYzEuMzQ1LTEuOTIxLDIuNC0zLjcsMy4wMjYtNC43NTVoMC4xYy0wLjEsMS4zNDUtLjE5MiwzLjA3NC0wLjE5Miw0Ljl2MTAuMDg3aDUuNjJWOTQuNmgtNS41NzJsLTcuMTA5LDEwLjIzMWMtMS4zLDEuOTIxLTIuNCwzLjctMy4wMjYsNC43NTVoLTAuMWMwLjEtMS4zNDUuMTkyLTMuMDc0LDAuMTkyLTQuOVY5NC42aC01LjYxOXYyMi43MTlabTMwLjg3MywwaDUuNzE2Vjk5LjM1aDYuNzI1bDEuNDg5LTQuNzU1SDI5NS41MjFWOTkuMzVoNi45MTZ2MTcuOTY0Wk0zMTguNDIzLDEyOC43aDUuNzE2VjExNy4yNjZhMTIuMTU1LDEyLjE1NSwwLDAsMCwzLjUwNi41MjhjNy4xMDksMCwxMS44MTYtNC45NDcsMTEuODE2LTExLjkxMSwwLTcuMjUzLTQuMjc1LTExLjg2NC0xMi40NC0xMS44NjRhMjYuNjQsMjYuNjQsMCwwLDAtOC42LDEuNDQxVjEyOC43Wm01LjcxNi0xNi4yMzVWOTkuMTFhOS41NzQsOS41NzQsMCwwLDEsMi42OS0uMzg0YzQuMDgyLDAsNi43NzIsMi4zMDUsNi43NzIsNy4xNTcsMCw0LjM3LTIuMTYxLDcuMTU2LTYuMzg4LDcuMTU2QTcuOTcsNy45NywwLDAsMSwzMjQuMTM5LDExMi40NjNabTE4LjY3NCw0Ljg1MWg1LjU3Mmw3LjEwOS0xMC4yMzFjMS4zNDUtMS45MjEsMi40LTMuNywzLjAyNi00Ljc1NWgwLjFjLTAuMSwxLjM0NS0uMTkyLDMuMDc0LTAuMTkyLDQuOXYxMC4wODdoNS42MTlWOTQuNmgtNS41NzFsLTcuMTA5LDEwLjIzMWMtMS4zLDEuOTIxLTIuNCwzLjctMy4wMjYsNC43NTVoLTAuMWMwLjEtMS4zNDUuMTkyLTMuMDc0LDAuMTkyLTQuOVY5NC42aC01LjYydjIyLjcxOVptMjUuNjg3LDBoNS43MTZWMTA3LjloMy40MWMwLjY3MiwwLDEuMy42MjQsMS45NjksMi4xNjFsMi44ODIsNy4yNTNoNi4ybC00LjEzLTguNjk0YTQuODc4LDQuODc4LDAsMCwwLTIuNjQyLTIuNzg1di0wLjFjMS45MjEtMS4xNTIsMi4xMTMtNC40MTgsMy4yNjYtNmExLjkxOSwxLjkxOSwwLDAsMSwxLjU4NS0uNzIxLDMuODU4LDMuODU4LDAsMCwxLDEuMy4xOTJWOTQuMzU1YTguMiw4LjIsMCwwLDAtMi4zNTQtLjMzNiw0LjgwOSw0LjgwOSwwLDAsMC00LjE3OCwyLjAxN2MtMS44NzQsMi43MzgtMS44MjYsNy40OTMtNC42Niw3LjQ5M2gtMi42NDFWOTQuNkgzNjguNXYyMi43MTlabTMyLjk4OCwwLjQ4YTE0LjIxNCwxNC4yMTQsMCwwLDAsNy43ODEtMi4yMDlsLTEuNjgxLTMuOTM5YTEwLjQ4OCwxMC40ODgsMCwwLDEtNS4yODMsMS41MzdjLTMuODkxLDAtNi40MzctMi41NDUtNi40MzctNy4yLDAtNC4xNzksMi41LTcuMzQ5LDYuNzI1LTcuMzQ5YTguOTQyLDguOTQyLDAsMCwxLDUuNTIzLDEuNzc3Vjk1LjU1NmExMS40MzMsMTEuNDMzLDAsMCwwLTYuMS0xLjUzNywxMS43NDEsMTEuNzQxLDAsMCwwLTEyLjAwOCwxMi4xQzM5MC4wMDgsMTEyLjcsMzk0LjA5MSwxMTcuNzk0LDQwMS40ODgsMTE3Ljc5NFoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0xNzguNjI1IC04NS4zNzUpIi8+PC9zdmc+); }

		.wrap.ua .buslogo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMzEuODQ0IiBoZWlnaHQ9IjQ0LjY1NyIgdmlld0JveD0iMCAwIDEzMS44NDQgNDQuNjU3Ij4gIDxkZWZzPiAgICA8c3R5bGU+ICAgICAgLmNscy0xIHsgICAgICAgIGZpbGw6ICNmZmY7ICAgICAgICBmaWxsLXJ1bGU6IGV2ZW5vZGQ7ICAgICAgfSAgICA8L3N0eWxlPiAgPC9kZWZzPiAgPHBhdGggaWQ9ItCRadGC0YBp0LrRgSIgY2xhc3M9ImNscy0xIiBkPSJNMTg2Ljg3NCwxMTIuNTU5di05Ljk0M2gyLjRhMTAuNTcxLDEwLjU3MSwwLDAsMSw1LjE0Ljk2MSw0LjI0Miw0LjI0MiwwLDAsMSwyLjAxNyw0LjAzNWMwLDMuMzYyLTIuMDY1LDQuOTQ3LTYuNzcyLDQuOTQ3aC0yLjc4NlptLTUuODEyLDQuNzU1aDguNDU0YzkuMzY2LDAsMTIuNzc2LTQuMTMxLDEyLjc3Ni05Ljg0NiwwLTMuODkxLTEuNjMzLTYuNDg1LTQuNDY3LTcuOTc0LTIuMjU3LTEuMi01LjE4Ny0xLjU4NS04LjY0Ni0xLjU4NWgtMi4zMDVWOTAuMzY4aDEyLjY4bDEuNTM3LTQuNzA3SDE4MS4wNjJ2MzEuNjUzWm0yNS4wNjEsMGg1Ljc2NFY5NC42aC01Ljc2NHYyMi43MTlaTTIwOS4wMDUsOTAuOWEzLjQzNSwzLjQzNSwwLDEsMC0zLjUwNi0zLjQxQTMuMzg2LDMuMzg2LDAsMCwwLDIwOS4wMDUsOTAuOVptMTIuNTMyLDI2LjQxN2g1LjcxNlY5OS4zNWg2LjcyNGwxLjQ4OS00Ljc1NUgyMTQuNjJWOTkuMzVoNi45MTd2MTcuOTY0Wk0yMzcuNTIzLDEyOC43aDUuNzE1VjExNy4yNjZhMTIuMTYyLDEyLjE2MiwwLDAsMCwzLjUwNy41MjhjNy4xMDgsMCwxMS44MTYtNC45NDcsMTEuODE2LTExLjkxMSwwLTcuMjUzLTQuMjc1LTExLjg2NC0xMi40NDEtMTEuODY0YTI2LjYyOSwyNi42MjksMCwwLDAtOC42LDEuNDQxVjEyOC43Wm01LjcxNS0xNi4yMzVWOTkuMTFhOS41NzQsOS41NzQsMCwwLDEsMi42OS0uMzg0YzQuMDgzLDAsNi43NzMsMi4zMDUsNi43NzMsNy4xNTcsMCw0LjM3LTIuMTYyLDcuMTU2LTYuMzg5LDcuMTU2QTcuOTczLDcuOTczLDAsMCwxLDI0My4yMzgsMTEyLjQ2M1ptMTguNjc1LDQuODUxaDUuNzY0Vjk0LjZoLTUuNzY0djIyLjcxOVpNMjY0LjgsOTAuOWEzLjQzNSwzLjQzNSwwLDEsMC0zLjUwNy0zLjQxQTMuMzg2LDMuMzg2LDAsMCwwLDI2NC44LDkwLjlabTcuMzQ0LDI2LjQxN2g1LjcxNlYxMDcuOWgzLjQxYzAuNjczLDAsMS4zLjYyNCwxLjk2OSwyLjE2MWwyLjg4Miw3LjI1M2g2LjJsLTQuMTMtOC42OTRhNC44NzgsNC44NzgsMCwwLDAtMi42NDItMi43ODV2LTAuMWMxLjkyMS0xLjE1MiwyLjExMy00LjQxOCwzLjI2Ni02YTEuOTE5LDEuOTE5LDAsMCwxLDEuNTg1LS43MjEsMy44NTgsMy44NTgsMCwwLDEsMS4zLjE5MlY5NC4zNTVhOC4yLDguMiwwLDAsMC0yLjM1NC0uMzM2LDQuODA5LDQuODA5LDAsMCwwLTQuMTc4LDIuMDE3Yy0xLjg3NCwyLjczOC0xLjgyNiw3LjQ5My00LjY1OSw3LjQ5M2gtMi42NDJWOTQuNmgtNS43MTZ2MjIuNzE5Wm0zMi45ODgsMC40OGExNC4yMTQsMTQuMjE0LDAsMCwwLDcuNzgxLTIuMjA5bC0xLjY4MS0zLjkzOWExMC40ODgsMTAuNDg4LDAsMCwxLTUuMjgzLDEuNTM3Yy0zLjg5MSwwLTYuNDM3LTIuNTQ1LTYuNDM3LTcuMiwwLTQuMTc5LDIuNS03LjM0OSw2LjcyNS03LjM0OWE4Ljk0Nyw4Ljk0NywwLDAsMSw1LjUyNCwxLjc3N1Y5NS41NTZhMTEuNDQsMTEuNDQsMCwwLDAtNi4xLTEuNTM3LDExLjc0LDExLjc0LDAsMCwwLTEyLjAwNywxMi4xQzI5My42NDgsMTEyLjcsMjk3LjczLDExNy43OTQsMzA1LjEyNywxMTcuNzk0WiIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoLTE4MS4wNjIgLTg0LjAzMSkiLz48L3N2Zz4=); }

		.content {
			z-index: 10;
			position: relative;
			margin-bottom: 20px;
		}

		.content-container {
			z-index: 10;
			max-width: 727px;
			margin: 0 auto;
			padding: 28px 25px 25px;
			border-radius: 11px;
			box-shadow: 0 4px 20px 0 rgba(6, 54, 70, .15);
			box-sizing: border-box;
			text-align: center;
			background-color: #fff;
			position: relative;
		}

		.content-block {
			position: relative;
			z-index: 10;
		}

		hr {
			margin: 79px 0 45px;
			border: none;
			height: 1px;
			background: #f2f2f2;
		}

		h1.content-header {
			color: #2fc6f7;
			font: 500 40px/45px "Helvetica Neue", Helvetica, Arial, sans-serif;
			margin-bottom: 13px;
			margin-top: 62px;
		}

		h2.content-header {
			color: #2fc6f7;
			font: 400 27px/27px "Helvetica Neue", Helvetica, Arial, sans-serif;
			margin-bottom: 13px;
			margin-top: 31px;
		}

		h3.content-header {
			color: #000;
			font: 400 30px/41px "Helvetica Neue", Helvetica, Arial, sans-serif;
			margin-bottom: 40px;
			margin-top: 46px;
		}

		.content-logo {
			width: 100%;
			height: 57px;
			background-repeat: no-repeat;
			background-position: center 0;
		}

		.wrap.de .content-logo,
		.wrap.en .content-logo{ background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMzIiIGhlaWdodD0iNDUiIHZpZXdCb3g9IjAgMCAyMzIgNDUiPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSguMDk3IC4wMjIpIj4gICAgPGcgZmlsbD0iIzJGQzdGNyIgZmlsbC1ydWxlPSJub256ZXJvIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwIDIuNzAxKSI+ICAgICAgPHBhdGggZD0iTS43NjA1OTI0OTEgMy4wOTYyOTc4OUwxMi45NzU1MDY0IDMuMDk2Mjk3ODlDMjEuNDE2MTQ5MiAzLjA5NjI5Nzg5IDI1LjE5MDQyMDMgNy45MDIxMDAzNyAyNS4xOTA0MjAzIDEyLjc2NDE3NjkgMjUuMTkwNDIwMyAxNS44OTMwMTMyIDIzLjYwMDY1MTYgMTguOTU0MzIwNiAyMC41OTI2NzE4IDIwLjQ2MjQ2NDdMMjAuNTkyNjcxOCAyMC41NzUwMTI4QzI1LjI1OTA0MzUgMjEuNzkwNTMxOSAyNy44NTUyODQ1IDI1LjQ5MzM2MzMgMjcuODU1Mjg0NSAyOS43ODE0NDQ3IDI3Ljg1NTI4NDUgMzUuNTY2NDE1NCAyMy4yNTc1MzYgNDEuMjM4ODM4IDE0LjA1MDYwMTggNDEuMjM4ODM4TC43NzIwMjk2NzcgNDEuMjM4ODM4Ljc3MjAyOTY3NyAzLjA5NjI5Nzg5Ljc2MDU5MjQ5MSAzLjA5NjI5Nzg5ek0xMS45MTE4NDgyIDE4LjY2MTY5NTZDMTUuNDU3Mzc1NiAxOC42NjE2OTU2IDE3LjI3NTg4ODEgMTYuNjkyMTA0NSAxNy4yNzU4ODgxIDE0LjIwNDc5MjIgMTcuMjc1ODg4MSAxMS42MDQ5MzE4IDE1LjUwMzEyNDQgOS41NzkwNjY1OCAxMS43Mjg4NTMyIDkuNTc5MDY2NThMOC44MzUyNDUzMyA5LjU3OTA2NjU4IDguODM1MjQ1MzMgMTguNjYxNjk1NiAxMS45MTE4NDgyIDE4LjY2MTY5NTZ6TTEyLjk3NTUwNjQgMzQuNzQ0ODE0NUMxNy40NTg4ODMxIDM0Ljc0NDgxNDUgMTkuNzAwNTcxNCAzMy4yMzY2NzA0IDE5LjcwMDU3MTQgMjkuNzcwMTg5OSAxOS43MDA1NzE0IDI2LjgyMTQzMDUgMTcuNDU4ODgzMSAyNS4xNDQ0NjQzIDEzLjc0MTc5NzggMjUuMTQ0NDY0M0w4LjgzNTI0NTMzIDI1LjE0NDQ2NDMgOC44MzUyNDUzMyAzNC43NTYwNjkzIDEyLjk3NTUwNjQgMzQuNzU2MDY5MyAxMi45NzU1MDY0IDM0Ljc0NDgxNDV6TTMxLjc4OTY3NjMgNS40MDM1MzMyN0MzMS43ODk2NzYzIDIuOTE2MjIwOTggMzMuODU5ODA2OC44OTAzNTU3NjEgMzYuNjI3NjA1Ny44OTAzNTU3NjEgMzkuMzM4MjE4Ni44OTAzNTU3NjEgNDEuNDA4MzQ5MSAyLjgwMzY3MjkxIDQxLjQwODM0OTEgNS40MDM1MzMyNyA0MS40MDgzNDkxIDcuOTQ3MTE5NiAzOS4zMzgyMTg2IDEwLjAyOTI1ODkgMzYuNjI3NjA1NyAxMC4wMjkyNTg5IDMzLjg1OTgwNjggMTAuMDQwNTEzNyAzMS43ODk2NzYzIDguMDE0NjQ4NDQgMzEuNzg5Njc2MyA1LjQwMzUzMzI3ek0zMi42MTMxNTM2IDEzLjg1NTg5MzJMNDAuNTczNDM0NiAxMy44NTU4OTMyIDQwLjU3MzQzNDYgNDEuMjI3NTgzMiAzMi42MTMxNTM2IDQxLjIyNzU4MzIgMzIuNjEzMTUzNiAxMy44NTU4OTMyek00OC43ODUzMzM3IDM0LjExNDU0NTNMNDguNzg1MzMzNyAxOS45MzM0ODg4IDQzLjY1MDAzNzUgMTkuOTMzNDg4OCA0My42NTAwMzc1IDEzLjg1NTg5MzIgNDguNzg1MzMzNyAxMy44NTU4OTMyIDQ4Ljc4NTMzMzcgNy40MjkzOTg0OSA1Ni44MTQyMzc4IDUuMTY3MTgyMzMgNTYuODE0MjM3OCAxMy44NDQ2MzgzIDY1LjI1NDg4MDUgMTMuODQ0NjM4MyA2My40MjQ5MzA5IDE5LjkyMjIzNCA1Ni44MTQyMzc4IDE5LjkyMjIzNCA1Ni44MTQyMzc4IDMyLjEzMzY5OTNDNTYuODE0MjM3OCAzNC42NzcyODU3IDU3LjYzNzcxNTEgMzUuNTQzOTA1OCA1OS40Njc2NjQ4IDM1LjU0MzkwNTggNjEuMDAwMjQ3NiAzNS41NDM5MDU4IDYyLjUzMjgzMDQgMzUuMDI2MTg0NyA2My41OTY0ODg3IDM0LjMyODM4NjZMNjUuODk1MzYyOSAzOS42NTE5MTAyQzYzLjgyNTIzMjQgNDEuMDM2MjUxNSA2MC4xNzY3NzAzIDQxLjc5MDMyMzUgNTcuMzk3NTM0MiA0MS43OTAzMjM1IDUyLjA5MDY4MDIgNDEuODEyODMzMSA0OC43ODUzMzM3IDM4LjkyMDM0NzggNDguNzg1MzMzNyAzNC4xMTQ1NDUzek02OC42NzQ1OTkgMTMuODU1ODkzMkw3NS4zOTk2NjM5IDEzLjg1NTg5MzIgNzYuMjgwMzI3MiAxNi43NDgzNzg1Qzc4LjYzNjM4NzQgMTQuNjA5OTY1MiA4MC44NzgwNzU3IDEzLjE1ODA5NTEgODMuODk3NDkyNiAxMy4xNTgwOTUxIDg1LjI1ODUxNzcgMTMuMTU4MDk1MSA4Ni45MDU0NzI0IDEzLjU2MzI2ODIgODguMTQwNjg4NCAxNC40Mjk4ODgzTDg1LjM3Mjg4OTUgMjAuOTEyNjU3QzgzLjk1NDY3ODYgMjAuMDQ2MDM2OSA4Mi42MDUwOTA3IDE5Ljg2NTk2IDgxLjgyNzM2MjEgMTkuODY1OTYgODAuMTExNzg0MyAxOS44NjU5NiA3OC43NjIxOTY0IDIwLjQzOTk1NTEgNzYuNjM0ODc5OSAyMi4xMjgxNzYxTDc2LjYzNDg3OTkgNDEuMjI3NTgzMiA2OC42NzQ1OTkgNDEuMjI3NTgzMiA2OC42NzQ1OTkgMTMuODU1ODkzMiA2OC42NzQ1OTkgMTMuODU1ODkzMnpNODkuNDQ0NTI3NSA1LjQwMzUzMzI3Qzg5LjQ0NDUyNzUgMi45MTYyMjA5OCA5MS41MTQ2NTgxLjg5MDM1NTc2MSA5NC4yODI0NTY5Ljg5MDM1NTc2MSA5Ni45OTMwNjk4Ljg5MDM1NTc2MSA5OS4wNjMyMDA0IDIuODAzNjcyOTEgOTkuMDYzMjAwNCA1LjQwMzUzMzI3IDk5LjA2MzIwMDQgNy45NDcxMTk2IDk2Ljk5MzA2OTggMTAuMDI5MjU4OSA5NC4yODI0NTY5IDEwLjAyOTI1ODkgOTEuNTAzMjIwOSAxMC4wNDA1MTM3IDg5LjQ0NDUyNzUgOC4wMTQ2NDg0NCA4OS40NDQ1Mjc1IDUuNDAzNTMzMjd6TTkwLjI2ODAwNDkgMTMuODU1ODkzMkw5OC4yMjgyODU4IDEzLjg1NTg5MzIgOTguMjI4Mjg1OCA0MS4yMjc1ODMyIDkwLjI2ODAwNDkgNDEuMjI3NTgzMiA5MC4yNjgwMDQ5IDEzLjg1NTg5MzJ6Ii8+ICAgICAgPHBvbHlnb24gcG9pbnRzPSIxMTEuMzkyIDI3LjQ1MiAxMDEuMTkxIDEzLjg1NiAxMDkuNDQ4IDEzLjg1NiAxMTUuNzA0IDIyLjE4NCAxMjEuOTYgMTMuODU2IDEzMC4yMTggMTMuODU2IDExOS44OSAyNy40NTIgMTMwLjMzMiA0MS4yMjggMTIyLjA2MyA0MS4yMjggMTE1LjYzNiAzMi42NjMgMTA5LjE1MSA0MS4yMjggMTAwLjg5MyA0MS4yMjgiLz4gICAgPC9nPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0xNTMuNzY3MjU4LDE1LjU0NDExNDIgQzE1My43NjcyNTgsMTIuMTMzOTA3NyAxNTAuODk2NTI0LDEwLjkyOTY0MzQgMTQ3Ljg0Mjc5NiwxMC45Mjk2NDM0IEMxNDMuNzQ4MjgzLDEwLjkyOTY0MzQgMTQwLjM5NzE4OCwxMi4yNDY0NTU4IDEzNy4yODYyNzQsMTMuNjE5NTQyMiBMMTM1LjE0NzUyLDcuMzI4MTA1MjMgQzEzOC42MjQ0MjQsNS43NzQ5NDE5IDE0My40NTA5MTcsNC4wMzA0NDY4NSAxNDkuMTkyMzg0LDQuMDMwNDQ2ODUgQzE1OC4xNzA1NzQsNC4wMzA0NDY4NSAxNjIuNTA1MjY3LDguNDY0ODQwNzEgMTYyLjUwNTI2NywxNC42ODg3NDg5IEMxNjIuNTA1MjY3LDI1LjU4MzQwMTggMTQ3LjI0ODA2MiwyOC42NDQ3MDkyIDE0NC42NzQ2OTUsMzYuOTYyMDExNCBMMTYzLjA1NDI1MiwzNi45NjIwMTE0IEwxNjMuMDU0MjUyLDQzLjkwNjIyNzIgTDEzMy44MDkzNjksNDMuOTA2MjI3MiBDMTM1LjQ1NjMyNCwyNC40NjkxNzU5IDE1My43NjcyNTgsMjMuMzMyNDQwNCAxNTMuNzY3MjU4LDE1LjU0NDExNDIgWiIvPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0xNjIuMjMwNzc1LDI5LjMxOTk5NzYgTDE4Mi4zNzE2NTgsNC4xNzY3NTkzNCBMMTg4LjM1MzMwNiw0LjE3Njc1OTM0IEwxODguMzUzMzA2LDI3Ljk0NjkxMTIgTDE5NC4zMzQ5NTQsMjcuOTQ2OTExMiBMMTk0LjMzNDk1NCwzNC41MzA5NzMyIEwxODguMzUzMzA2LDM0LjUzMDk3MzIgTDE4OC4zNTMzMDYsNDMuOTI4NzM2OCBMMTgwLjIzMjkwNSw0My45Mjg3MzY4IEwxODAuMjMyOTA1LDM0LjUzMDk3MzIgTDE2Mi4yMTkzMzgsMzQuNTMwOTczMiBMMTYyLjIxOTMzOCwyOS4zMTk5OTc2IEwxNjIuMjMwNzc1LDI5LjMxOTk5NzYgWiBNMTc2LjU3MzAwNSwyNy45NDY5MTEyIEwxODAuMjMyOTA1LDI3Ljk0NjkxMTIgTDE4MC4yMzI5MDUsMjMuNjkyNTk0MyBDMTgwLjIzMjkwNSwyMC42NDI1NDE2IDE4MC40NzMwODYsMTcuMTA4NTMyMyAxODAuNTk4ODk1LDE2LjMzMTk1MDYgTDE3MS41MDYzMzIsMjguMTI2OTg4MSBDMTcyLjIzODMxMiwyOC4wNTk0NTkzIDE3NS4xNjYyMzIsMjcuOTQ2OTExMiAxNzYuNTczMDA1LDI3Ljk0NjkxMTIgWiIvPiAgICA8cGF0aCBmaWxsPSIjMDA2NkExIiBkPSJNMjE2Ljk5MjAxOCwwLjg0NTMzNjUzNCBDMjA4Ljk2MzExNCwwLjg0NTMzNjUzNCAyMDIuNDY2NzkzLDcuMjQ5MzIxNTggMjAyLjQ2Njc5MywxNS4xMzg5NDExIEMyMDIuNDY2NzkzLDIzLjAyODU2MDcgMjA4Ljk3NDU1MSwyOS40MzI1NDU3IDIxNi45OTIwMTgsMjkuNDMyNTQ1NyBDMjI1LjAyMDkyMiwyOS40MzI1NDU3IDIzMS41MTcyNDQsMjMuMDI4NTYwNyAyMzEuNTE3MjQ0LDE1LjEzODk0MTEgQzIzMS41MTcyNDQsNy4yNDkzMjE1OCAyMjUuMDA5NDg1LDAuODQ1MzM2NTM0IDIxNi45OTIwMTgsMC44NDUzMzY1MzQgWiBNMjE3LjA0OTIwNCwyNi41NTEzMTUyIEMyMTAuNjU1ODE4LDI2LjU1MTMxNTIgMjA1LjQ3NDc3MywyMS40NTI4ODc3IDIwNS40NzQ3NzMsMTUuMTYxNDUwNyBDMjA1LjQ3NDc3Myw4Ljg3MDAxMzc1IDIxMC42NTU4MTgsMy43NzE1ODYyOSAyMTcuMDQ5MjA0LDMuNzcxNTg2MjkgQzIyMy40NDI1OTEsMy43NzE1ODYyOSAyMjguNjIzNjM2LDguODcwMDEzNzUgMjI4LjYyMzYzNiwxNS4xNjE0NTA3IEMyMjguNjIzNjM2LDIxLjQ1Mjg4NzcgMjIzLjQzMTE1NCwyNi41NTEzMTUyIDIxNy4wNDkyMDQsMjYuNTUxMzE1MiBaIE0yMTguMzE4NzMyLDguMjg0NzYzOCBMMjE1LjI5OTMxNSw4LjI4NDc2MzggTDIxNS4yOTkzMTUsMTYuNDQ0NDk4NyBMMjIzLjMxNjc4MiwxNi40NDQ0OTg3IEwyMjMuMzE2NzgyLDEzLjU5NzAzMjYgTDIxOC4zMTg3MzIsMTMuNTk3MDMyNiBMMjE4LjMxODczMiw4LjI4NDc2MzggWiIvPiAgPC9nPjwvc3ZnPg==); }

		.wrap.ru .content-logo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDciIGhlaWdodD0iNTkiIHZpZXdCb3g9IjAgMCAzMDcgNTkiPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtLjQyNiAtLjEyKSI+ICAgIDxwYXRoIGZpbGw9IiMyRkM3RjciIGZpbGwtcnVsZT0ibm9uemVybyIgZD0iTS44NTEyNjAxODcgNS44NjkwMjA2NkwyNi40OTEyMzQxIDUuODY5MDIwNjYgMjQuNDgyNzMxMiAxMi4zOTUxODMyIDguOTY1NjExODEgMTIuMzk1MTgzMiA4Ljk2NTYxMTgxIDIwLjUxMDAyNjIgMTEuNjI4MzEyOCAyMC41MTAwMjYyQzE1LjgyODk1MzEgMjAuNTEwMDI2MiAxOS41NTkwMjk4IDIxLjAzNTc3NjYgMjIuNDYyNzUxMiAyMi41NjczMTA0IDI1Ljg5NDQyMTggMjQuMzI3NDMxMyAyNy45NzE3ODc2IDI3LjUwNDc5MjQgMjcuOTcxNzg3NiAzMi40NDIyNzQzIDI3Ljk3MTc4NzYgMzkuMzc5ODkzNyAyMy43NzExNDczIDQ0LjYwMzEwOTYgMTEuODExOTQ3MyA0NC42MDMxMDk2TC44NjI3MzczNDYgNDQuNjAzMTA5Ni44NjI3MzczNDYgNS44NjkwMjA2Ni44NTEyNjAxODcgNS44NjkwMjA2NnpNMTEuOTg0MTA0NyAzOC4wMTk4MDAzQzE3LjU1MDUyNyAzOC4wMTk4MDAzIDE5Ljg1NzQzNiAzNi4yNTk2Nzk0IDE5Ljg1NzQzNiAzMi41NTY1Njc5IDE5Ljg1NzQzNiAzMC40NDIxMzcgMTkuMDMxMDgwNSAyOC45Njc3NSAxNy42NjUyOTg2IDI4LjE0NDgzNjMgMTYuMTg0NzQ1IDI3LjI2NDc3NTkgMTQuMDQ5OTkzNCAyNy4wMjQ3NTk0IDExLjYyODMxMjggMjcuMDI0NzU5NEw4Ljk2NTYxMTgxIDI3LjAyNDc1OTQgOC45NjU2MTE4MSAzOC4wMTk4MDAzIDExLjk4NDEwNDcgMzguMDE5ODAwMyAxMS45ODQxMDQ3IDM4LjAxOTgwMDN6TTMxLjc1OTI1MDIgMTYuNzk1NDg1NEwzOS41MTc4MDk5IDE2Ljc5NTQ4NTQgMzkuNTE3ODA5OSAyNy45MDQ4MTk4QzM5LjUxNzgwOTkgMzAuMjAyMTIwNSAzOS40NjA0MjQxIDMyLjQzMDg0NSAzOS4yNzY3ODk1IDM0LjAxOTUyNTVMMzkuNDQ4OTQ2OSAzNC4wMTk1MjU1QzQwLjEwMzE0NSAzMi44OTk0NDg2IDQxLjQ1NzQ0OTggMzAuNTU2NDMwNSA0My4wNjQyNTIxIDI4LjI1OTEyOTlMNTEuMDUyMzU1IDE2Ljc5NTQ4NTQgNTguODY4MzAwNSAxNi43OTU0ODU0IDU4Ljg2ODMwMDUgNDQuNTkxNjgwMiA1MS4wNTIzNTUgNDQuNTkxNjgwMiA1MS4wNTIzNTUgMzMuNDgyMzQ1OEM1MS4wNTIzNTUgMzEuMTg1MDQ1MSA1MS4xNjcxMjY2IDI4Ljk1NjMyMDYgNTEuMzUwNzYxMSAyNy4zNjc2NDAxTDUxLjE3ODYwMzcgMjcuMzY3NjQwMUM1MC41MjQ0MDU2IDI4LjQ4NzcxNyA0OS4xMDEyMzc5IDMwLjgzMDczNTEgNDcuNTYzMjk4NSAzMy4xMjgwMzU3TDM5LjU3NTE5NTcgNDQuNTkxNjgwMiAzMS43NTkyNTAyIDQ0LjU5MTY4MDIgMzEuNzU5MjUwMiAxNi43OTU0ODU0IDMxLjc1OTI1MDIgMTYuNzk1NDg1NHoiLz4gICAgPHBvbHlnb24gZmlsbD0iIzJGQzdGNyIgZmlsbC1ydWxlPSJub256ZXJvIiBwb2ludHM9IjcwLjgwNSAyMy4zNzkgNjIuNDYxIDIzLjM3OSA2Mi40NjEgMTYuNzk1IDg4Ljg3IDE2Ljc5NSA4Ni43OTIgMjMuMzc5IDc4LjgwNCAyMy4zNzkgNzguODA0IDQ0LjU5MiA3MC44MTYgNDQuNTkyIDcwLjgxNiAyMy4zNzkiLz4gICAgPHBhdGggZmlsbD0iIzJGQzdGNyIgZmlsbC1ydWxlPSJub256ZXJvIiBkPSJNOTEuMzYwMTM4NCAxNy45MTU1NjIzQzk0LjQzNjAxNzEgMTYuODUyNjMyMiA5OC4yMjM0Nzk3IDE2LjA5ODI5NDcgMTAyLjQzNTU5NyAxNi4wOTgyOTQ3IDExMi44NTY4NTggMTYuMDk4Mjk0NyAxMTguMTI0ODc0IDIxLjkxNTgzNzEgMTE4LjEyNDg3NCAzMC42NzA3MjQxIDExOC4xMjQ4NzQgMzkuMDE0MTU0MyAxMTIuNDQzNjggNDUuMTg2MDA2NyAxMDMuMzc2NzI0IDQ1LjE4NjAwNjcgMTAyLjAxMDk0MiA0NS4xODYwMDY3IDEwMC42NTY2MzcgNDUuMDE0NTY2NCA5OS4zNDgyNDEzIDQ0LjY2MDI1NjNMOTkuMzQ4MjQxMyA1OC41MzU0OTUgOTEuMzYwMTM4NCA1OC41MzU0OTUgOTEuMzYwMTM4NCAxNy45MTU1NjIzIDkxLjM2MDEzODQgMTcuOTE1NTYyM3pNMTAyLjc3OTkxMiAzOC42NTk4NDQyQzEwNy41NzczNjUgMzguNjU5ODQ0MiAxMDkuOTQxNjU5IDM1LjUzOTYyOTkgMTA5Ljk0MTY1OSAzMC42NzA3MjQxIDEwOS45NDE2NTkgMjUuMjY0NjM4NSAxMDYuOTgwNTUyIDIyLjYyNDQ1NzIgMTAyLjA2ODMyOCAyMi42MjQ0NTcyIDEwMS4wNTgzMzggMjIuNjI0NDU3MiAxMDAuMjMxOTgzIDIyLjY4MTYwMzkgOTkuMzQ4MjQxMyAyMi45Nzg3NjcyTDk5LjM0ODI0MTMgMzguMDg4Mzc2NEMxMDAuNDE1NjE3IDM4LjQzMTI1NzEgMTAxLjQyNTYwNyAzOC42NTk4NDQyIDEwMi43Nzk5MTIgMzguNjU5ODQ0MnpNMTIxLjMxNTUyNCAxNi43OTU0ODU0TDEyOS4wNzQwODQgMTYuNzk1NDg1NCAxMjkuMDc0MDg0IDI3LjkwNDgxOThDMTI5LjA3NDA4NCAzMC4yMDIxMjA1IDEyOS4wMTY2OTggMzIuNDMwODQ1IDEyOC44MzMwNjQgMzQuMDE5NTI1NUwxMjkuMDA1MjIxIDM0LjAxOTUyNTVDMTI5LjY1OTQxOSAzMi44OTk0NDg2IDEzMS4wMTM3MjQgMzAuNTU2NDMwNSAxMzIuNjIwNTI2IDI4LjI1OTEyOTlMMTQwLjYwODYyOSAxNi43OTU0ODU0IDE0OC40MjQ1NzQgMTYuNzk1NDg1NCAxNDguNDI0NTc0IDQ0LjU5MTY4MDIgMTQwLjYwODYyOSA0NC41OTE2ODAyIDE0MC42MDg2MjkgMzMuNDgyMzQ1OEMxNDAuNjA4NjI5IDMxLjE4NTA0NTEgMTQwLjcyMzQwMSAyOC45NTYzMjA2IDE0MC45MDcwMzUgMjcuMzY3NjQwMUwxNDAuNzM0ODc4IDI3LjM2NzY0MDFDMTQwLjA4MDY4IDI4LjQ4NzcxNyAxMzguNjU3NTEyIDMwLjgzMDczNTEgMTM3LjExOTU3MyAzMy4xMjgwMzU3TDEyOS4xMzE0NyA0NC41OTE2ODAyIDEyMS4zMTU1MjQgNDQuNTkxNjgwMiAxMjEuMzE1NTI0IDE2Ljc5NTQ4NTR6TTE1My45NDUwODggMTYuNzk1NDg1NEwxNjEuOTMzMTkxIDE2Ljc5NTQ4NTQgMTYxLjkzMzE5MSAyNy40MzYyMTYyIDE2NC44OTQyOTggMjcuNDM2MjE2MkMxNjguMjY4NTgzIDI3LjQzNjIxNjIgMTY4LjE1MzgxMSAyMS43OTAxMTQxIDE3MC42MzI4NzggMTguNDk4NDU5NSAxNzEuNzAwMjU0IDE3LjAyNDA3MjUgMTczLjM1Mjk2NCAxNi4wODY4NjUzIDE3NS45MDA4OTQgMTYuMDg2ODY1MyAxNzYuNzI3MjQ5IDE2LjA4Njg2NTMgMTc4LjIwNzgwMyAxNi4yMDExNTg5IDE3OS4xMDMwMjEgMTYuNDk4MzIyMUwxNzkuMTAzMDIxIDIzLjI1MzA3MThDMTc4LjYzMjQ1OCAyMy4wODE2MzE0IDE3OC4wMzU2NDUgMjIuOTU1OTA4NSAxNzcuNDUwMzEgMjIuOTU1OTA4NSAxNzYuNTY2NTY5IDIyLjk1NTkwODUgMTc1Ljk2OTc1NyAyMy4yNTMwNzE4IDE3NS40OTkxOTMgMjMuODM1OTY4OSAxNzQuMDc2MDI2IDI1LjU5NjA4OTggMTczLjg0NjQ4MiAyOS4xODQ5MDc4IDE3MS43NjkxMTcgMzAuNDc2NDI1TDE3MS43NjkxMTcgMzAuNTkwNzE4NkMxNzMuMTM0ODk4IDMxLjExNjQ2OSAxNzQuMTMzNDExIDMyLjEyMjI1MjQgMTc0Ljk3MTI0NCAzMy44ODIzNzMyTDE4MC4wMDk3MTcgNDQuNTgwMjUwOCAxNzEuMzY3NDE2IDQ0LjU4MDI1MDggMTY4LjA1MDUxNyAzNS44ODI1MTA2QzE2Ny4zMzg5MzMgMzQuMTc5NTM2NSAxNjYuNjI3MzQ5IDMzLjQxMzc2OTYgMTY1Ljg1ODM3OSAzMy40MTM3Njk2TDE2MS45NTYxNDUgMzMuNDEzNzY5NiAxNjEuOTU2MTQ1IDQ0LjU4MDI1MDggMTUzLjk2ODA0MiA0NC41ODAyNTA4IDE1My45NjgwNDIgMTYuNzk1NDg1NCAxNTMuOTQ1MDg4IDE2Ljc5NTQ4NTR6TTE4MS4yMDMzNDEgMzAuODQyMTY0NEMxODEuMjAzMzQxIDIyLjAzMDEzMDYgMTg4LjA2NjY4MyAxNi4wODY4NjUzIDE5Ni4zMDcyODMgMTYuMDg2ODY1MyAyMDAuMDk0NzQ2IDE2LjA4Njg2NTMgMjAyLjU4NTI4OSAxNy4xNDk3OTU0IDIwNC4wNjU4NDMgMTguMDg3MDAyN0wyMDQuMDY1ODQzIDI0LjcyNzQ1ODdDMjAyLjA1NzM0IDIzLjE5NTkyNSAxOTkuOTIyNTg4IDIyLjM3MzAxMTMgMTk3LjIwMjUwMiAyMi4zNzMwMTEzIDE5Mi4yOTAyNzcgMjIuMzczMDExMyAxODkuMzg2NTU2IDI2LjA3NjEyMjggMTg5LjM4NjU1NiAzMC43NzM1ODgzIDE4OS4zODY1NTYgMzYuMDA4MjMzNSAxOTIuMzQ3NjYzIDM4LjgxOTg1NTIgMTk2Ljg0NjcxIDM4LjgxOTg1NTIgMTk5LjMzNzI1MyAzOC44MTk4NTUyIDIwMS4yMzA5ODQgMzguMTExMjM1MSAyMDMuMjk2ODczIDM2LjkzNDAxMTRMMjA1LjU0NjM5NiA0Mi4zNDAwOTdDMjAzLjIzOTQ4NyA0My45ODU5MjQzIDE5OS42MjQxODIgNDUuMTYzMTQ4IDE5NS43MjE5NDggNDUuMTYzMTQ4IDE4Ni40MTM5NzIgNDUuMTg2MDA2NyAxODEuMjAzMzQxIDM5LjAxNDE1NDMgMTgxLjIwMzM0MSAzMC44NDIxNjQ0eiIvPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0yMjkuMjgxMTYyLDE1Ljc3ODI3MjcgQzIyOS4yODExNjIsMTIuMzE1MTc3NyAyMjYuNDAwMzk1LDExLjA5MjIzNjUgMjIzLjMzNTk5MywxMS4wOTIyMzY1IEMyMTkuMjI3MTcsMTEuMDkyMjM2NSAyMTUuODY0MzYzLDEyLjQyOTQ3MTIgMjEyLjc0MjU3NSwxMy44MjM4NTI3IEwyMTAuNTk2MzQ2LDcuNDM0ODQyNDkgQzIxNC4wODU0MDMsNS44NTc1OTEzIDIxOC45Mjg3NjQsNC4wODYwNDEwNSAyMjQuNjkwMjk4LDQuMDg2MDQxMDUgQzIzMy42OTk4NjgsNC4wODYwNDEwNSAyMzguMDQ5NzExLDguNTg5MjA3NDggMjM4LjA0OTcxMSwxNC45MDk2NDE2IEMyMzguMDQ5NzExLDI1Ljk3MzI1ODYgMjIyLjczOTE4MSwyOS4wODIwNDM1IDIyMC4xNTY4MiwzNy41MjgzMzc5IEwyMzguNjAwNjE1LDM3LjUyODMzNzkgTDIzOC42MDA2MTUsNDQuNTgwMjUwOCBMMjA5LjI1MzUxOSw0NC41ODAyNTA4IEMyMTAuOTA2MjMsMjQuODQxNzUyMyAyMjkuMjgxMTYyLDIzLjY3NTk1OCAyMjkuMjgxMTYyLDE1Ljc3ODI3MjcgWiIvPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0yMzcuNzc0MjYsMjkuNzU2Mzc1NiBMMjU3Ljk4NTUzNyw0LjIyMzE5MzMzIEwyNjMuOTg4MDkyLDQuMjIzMTkzMzMgTDI2My45ODgwOTIsMjguMzYxOTk0MSBMMjY5Ljk5MDY0NiwyOC4zNjE5OTQxIEwyNjkuOTkwNjQ2LDM1LjA0ODE2NzYgTDI2My45ODgwOTIsMzUuMDQ4MTY3NiBMMjYzLjk4ODA5Miw0NC41OTE2ODAyIEwyNTUuODM5MzA4LDQ0LjU5MTY4MDIgTDI1NS44MzkzMDgsMzUuMDQ4MTY3NiBMMjM3Ljc2Mjc4MiwzNS4wNDgxNjc2IEwyMzcuNzYyNzgyLDI5Ljc1NjM3NTYgTDIzNy43NzQyNiwyOS43NTYzNzU2IFogTTI1Mi4xNjY2MTcsMjguMzYxOTk0MSBMMjU1LjgzOTMwOCwyOC4zNjE5OTQxIEwyNTUuODM5MzA4LDI0LjA0MTY5NzQgQzI1NS44MzkzMDgsMjAuOTQ0MzQxOCAyNTYuMDgwMzI5LDE3LjM1NTUyMzkgMjU2LjIwNjU3NywxNi41NjY4OTgzIEwyNDcuMDgyMjM2LDI4LjU0NDg2MzggQzI0Ny44MTY3NzQsMjguNDg3NzE3IDI1MC43NTQ5MjcsMjguMzYxOTk0MSAyNTIuMTY2NjE3LDI4LjM2MTk5NDEgWiIvPiAgICA8cGF0aCBmaWxsPSIjMDA2NkExIiBkPSJNMjkyLjcyNjg5OCwwLjg0MDEwMzgzMiBDMjg0LjY2OTkzMywwLjg0MDEwMzgzMiAyNzguMTUwOTA2LDcuMzQzNDA3NjMgMjc4LjE1MDkwNiwxNS4zNTUzODY1IEMyNzguMTUwOTA2LDIzLjM2NzM2NTMgMjg0LjY4MTQxLDI5Ljg3MDY2OTEgMjkyLjcyNjg5OCwyOS44NzA2NjkxIEMzMDAuNzgzODY0LDI5Ljg3MDY2OTEgMzA3LjMwMjg5MSwyMy4zNjczNjUzIDMwNy4zMDI4OTEsMTUuMzU1Mzg2NSBDMzA3LjMwMjg5MSw3LjM0MzQwNzYzIDMwMC43NzIzODcsMC44NDAxMDM4MzIgMjkyLjcyNjg5OCwwLjg0MDEwMzgzMiBaIE0yOTIuNzg0Mjg0LDI2Ljk0NDc1MzkgQzI4Ni4zNjg1NTIsMjYuOTQ0NzUzOSAyODEuMTY5Mzk5LDIxLjc2NzI1NTQgMjgxLjE2OTM5OSwxNS4zNzgyNDUyIEMyODEuMTY5Mzk5LDguOTg5MjM0OTYgMjg2LjM2ODU1MiwzLjgxMTczNjUgMjkyLjc4NDI4NCwzLjgxMTczNjUgQzI5OS4yMDAwMTYsMy44MTE3MzY1IDMwNC4zOTkxNjksOC45ODkyMzQ5NiAzMDQuMzk5MTY5LDE1LjM3ODI0NTIgQzMwNC4zOTkxNjksMjEuNzY3MjU1NCAyOTkuMTg4NTM5LDI2Ljk0NDc1MzkgMjkyLjc4NDI4NCwyNi45NDQ3NTM5IFogTTI5NC4wNTgyNDksOC40MDYzMzc3OCBMMjkxLjAyODI3OSw4LjQwNjMzNzc4IEwyOTEuMDI4Mjc5LDE2LjY5MjYyMTIgTDI5OS4wNzM3NjcsMTYuNjkyNjIxMiBMMjk5LjA3Mzc2NywxMy44MDA5OTQgTDI5NC4wNTgyNDksMTMuODAwOTk0IEwyOTQuMDU4MjQ5LDguNDA2MzM3NzggWiIvPiAgPC9nPjwvc3ZnPg==); }

		.wrap.ua .content-logo { background-image: url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNzIiIGhlaWdodD0iNTkiIHZpZXdCb3g9IjAgMCAyNzIgNTkiPiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtLjU5NSAtLjA0KSI+ICAgIDxnIGZpbGw9IiMyRkM3RjciIGZpbGwtcnVsZT0ibm9uemVybyIgdHJhbnNmb3JtPSJ0cmFuc2xhdGUoMCAyLjg2KSI+ICAgICAgPHBhdGggZD0iTS43NzE5NTU3MzMgMy4wMTkzNTgwMUwyNi4yODk1ODk5IDMuMDE5MzU4MDEgMjQuMjkwNjcwNSA5LjU1MDEyMDc4IDguODQ3NTkwMTkgOS41NTAxMjA3OCA4Ljg0NzU5MDE5IDE3LjY3MDY4MzkgMTEuNDk3NTg2MiAxNy42NzA2ODM5QzE1LjY3ODE4MzQgMTcuNjcwNjgzOSAxOS4zOTA0NjIzIDE4LjE5NjgwNDkgMjIuMjgwMzI4NyAxOS43Mjk0MTgzIDI1LjY5NTYyNTMgMjEuNDkwNzc5OCAyNy43NjMwNzkxIDI0LjY3MDM4MDYgMjcuNzYzMDc5MSAyOS42MTEzNDMgMjcuNzYzMDc5MSAzNi41NTM4NTI2IDIzLjU4MjQ4MTkgNDEuNzgwNzUwMyAxMS42ODAzNDQ2IDQxLjc4MDc1MDNMLjc4MzM3ODEzIDQxLjc4MDc1MDMuNzgzMzc4MTMgMy4wMTkzNTgwMS43NzE5NTU3MzMgMy4wMTkzNTgwMXpNMTEuODUxNjgwNSAzNS4xOTI4MDA1QzE3LjM5MTU0MjkgMzUuMTkyODAwNSAxOS42ODc0NDQ2IDMzLjQzMTQzODkgMTkuNjg3NDQ0NiAyOS43MjU3MTcxIDE5LjY4NzQ0NDYgMjcuNjA5Nzk1NyAxOC44NjUwMzIxIDI2LjEzNDM2OTUgMTcuNTA1NzY2OSAyNS4zMTA4NzU3IDE2LjAzMjI3NzcgMjQuNDMwMTk1IDEzLjkwNzcxMTkgMjQuMTkwMDA5MyAxMS40OTc1ODYyIDI0LjE5MDAwOTNMOC44NDc1OTAxOSAyNC4xOTAwMDkzIDguODQ3NTkwMTkgMzUuMTkyODAwNSAxMS44NTE2ODA1IDM1LjE5MjgwMDUgMTEuODUxNjgwNSAzNS4xOTI4MDA1ek0zMS43NzIzNDAzIDUuMzc1NDY1MDdDMzEuNzcyMzQwMyAyLjg0Nzc5NjgyIDMzLjgzOTc5NDEuNzg5MDYyNSAzNi42MDQwMTQxLjc4OTA2MjUgMzkuMzExMTIyMS43ODkwNjI1IDQxLjM3ODU3NTkgMi43MzM0MjI2OSA0MS4zNzg1NzU5IDUuMzc1NDY1MDcgNDEuMzc4NTc1OSA3Ljk2MDMyMDM5IDM5LjMxMTEyMjEgMTAuMDc2MjQxOCAzNi42MDQwMTQxIDEwLjA3NjI0MTggMzMuODM5Nzk0MSAxMC4wNzYyNDE4IDMxLjc3MjM0MDMgOC4wMTc1MDc0NSAzMS43NzIzNDAzIDUuMzc1NDY1MDd6TTMyLjU5NDc1MjkgMTMuOTY0OTYyMkw0MC41NDQ3NDEgMTMuOTY0OTYyMiA0MC41NDQ3NDEgNDEuNzgwNzUwMyAzMi41OTQ3NTI5IDQxLjc4MDc1MDMgMzIuNTk0NzUyOSAxMy45NjQ5NjIyeiIvPiAgICAgIDxwb2x5Z29uIHBvaW50cz0iNTIuNTE1IDIwLjU1MyA0NC4yMTEgMjAuNTUzIDQ0LjIxMSAxMy45NjUgNzAuNDk0IDEzLjk2NSA2OC40MjcgMjAuNTUzIDYwLjQ3NyAyMC41NTMgNjAuNDc3IDQxLjc4MSA1Mi41MjcgNDEuNzgxIDUyLjUyNyAyMC41NTMiLz4gICAgICA8cGF0aCBkPSJNNzMuNDk4MzU1NCAxNS4wNzQzOTEyQzc2LjU1OTU1NzcgMTQuMDEwNzExOCA4MC4zMjg5NDg2IDEzLjI1NTg0MjYgODQuNTIwOTY4MiAxMy4yNTU4NDI2IDk0Ljg5MjUwNDQgMTMuMjU1ODQyNiAxMDAuMTM1Mzg0IDE5LjA3NzQ4NTcgMTAwLjEzNTM4NCAyNy44Mzg1NDQgMTAwLjEzNTM4NCAzNi4xODc4NTU0IDk0LjQ4MTI5ODEgNDIuMzY0MDU4NCA4NS40NTc2MDQ3IDQyLjM2NDA1ODQgODQuMDk4MzM5NSA0Mi4zNjQwNTg0IDgyLjc1MDQ5NjcgNDIuMTkyNDk3MiA4MS40NDgzNDM1IDQxLjgzNzkzNzRMODEuNDQ4MzQzNSA1NS43MjI5NTY2IDczLjQ5ODM1NTQgNTUuNzIyOTU2NiA3My40OTgzNTU0IDE1LjA3NDM5MTIgNzMuNDk4MzU1NCAxNS4wNzQzOTEyek04NC44NjM2NDAxIDM1LjgzMzI5NTZDODkuNjM4MjAxOSAzNS44MzMyOTU2IDkxLjk5MTIxNTYgMzIuNzEwODgxOSA5MS45OTEyMTU2IDI3LjgzODU0NCA5MS45OTEyMTU2IDIyLjQyODY0NzcgODkuMDQ0MjM3MyAxOS43ODY2MDUzIDg0LjE1NTQ1MTUgMTkuNzg2NjA1MyA4My4xNTAyODA2IDE5Ljc4NjYwNTMgODIuMzI3ODY4IDE5Ljg0Mzc5MjQgODEuNDQ4MzQzNSAyMC4xNDExNjUxTDgxLjQ0ODM0MzUgMzUuMjYxNDI1QzgyLjUxMDYyNjQgMzUuNjA0NTQ3NCA4My41MTU3OTczIDM1LjgzMzI5NTYgODQuODYzNjQwMSAzNS44MzMyOTU2ek0xMDMuNTUwNjgxIDUuMzc1NDY1MDdDMTAzLjU1MDY4MSAyLjg0Nzc5NjgyIDEwNS42MTgxMzUuNzg5MDYyNSAxMDguMzgyMzU1Ljc4OTA2MjUgMTExLjA4OTQ2My43ODkwNjI1IDExMy4xNTY5MTcgMi43MzM0MjI2OSAxMTMuMTU2OTE3IDUuMzc1NDY1MDcgMTEzLjE1NjkxNyA3Ljk2MDMyMDM5IDExMS4wODk0NjMgMTAuMDc2MjQxOCAxMDguMzgyMzU1IDEwLjA3NjI0MTggMTA1LjYwNjcxMiAxMC4wNzYyNDE4IDEwMy41NTA2ODEgOC4wMTc1MDc0NSAxMDMuNTUwNjgxIDUuMzc1NDY1MDd6TTEwNC4zNzMwOTQgMTMuOTY0OTYyMkwxMTIuMzIzMDgyIDEzLjk2NDk2MjIgMTEyLjMyMzA4MiA0MS43ODA3NTAzIDEwNC4zNzMwOTQgNDEuNzgwNzUwMyAxMDQuMzczMDk0IDEzLjk2NDk2MjJ6TTExNy45MjAwNTYgMTMuOTY0OTYyMkwxMjUuODcwMDQ0IDEzLjk2NDk2MjIgMTI1Ljg3MDA0NCAyNC42MTMxOTM2IDEyOC44MTcwMjMgMjQuNjEzMTkzNkMxMzIuMTc1MjA3IDI0LjYxMzE5MzYgMTMyLjA2MDk4MyAxOC45NjMxMTE2IDEzNC41MjgyMjEgMTUuNjY5MTM2NyAxMzUuNTkwNTA0IDE0LjE5MzcxMDQgMTM3LjIzNTMyOSAxMy4yNTU4NDI2IDEzOS43NzExMDEgMTMuMjU1ODQyNiAxNDAuNTkzNTEzIDEzLjI1NTg0MjYgMTQyLjA2NzAwMyAxMy4zNzAyMTY3IDE0Mi45NTc5NSAxMy42Njc1ODk0TDE0Mi45NTc5NSAyMC40MjcxMDA0QzE0Mi40ODk2MzEgMjAuMjU1NTM5MiAxNDEuODk1NjY3IDIwLjEyOTcyNzcgMTQxLjMxMzEyNCAyMC4xMjk3Mjc3IDE0MC40MzM2IDIwLjEyOTcyNzcgMTM5LjgzOTYzNSAyMC40MjcxMDA0IDEzOS4zNzEzMTcgMjEuMDEwNDA4NSAxMzcuOTU0OTQgMjIuNzcxNzcwMSAxMzcuNzI2NDkyIDI2LjM2MzExNzcgMTM1LjY1OTAzOCAyNy42NTU1NDU0TDEzNS42NTkwMzggMjcuNzY5OTE5NUMxMzcuMDE4MzAzIDI4LjI5NjA0MDUgMTM4LjAxMjA1MiAyOS4zMDI1MzI4IDEzOC44NDU4ODcgMzEuMDYzODk0NEwxNDMuODYwMzE5IDQxLjc2OTMxMjkgMTM1LjI1OTI1NCA0MS43NjkzMTI5IDEzMS45NTgxODIgMzMuMDY1NDQxN0MxMzEuMjQ5OTkzIDMxLjM2MTI2NzIgMTMwLjU0MTgwNCAzMC41OTQ5NjA1IDEyOS43NzY1MDQgMzAuNTk0OTYwNUwxMjUuODkyODg5IDMwLjU5NDk2MDUgMTI1Ljg5Mjg4OSA0MS43NjkzMTI5IDExNy45NDI5MDEgNDEuNzY5MzEyOSAxMTcuOTQyOTAxIDEzLjk2NDk2MjIgMTE3LjkyMDA1NiAxMy45NjQ5NjIyek0xNDUuNzMzNTkyIDI4LjAyMTU0MjZDMTQ1LjczMzU5MiAxOS4yMDMyOTczIDE1Mi41NjQxODUgMTMuMjU1ODQyNiAxNjAuNzY1NDY2IDEzLjI1NTg0MjYgMTY0LjUzNDg1NyAxMy4yNTU4NDI2IDE2Ny4wMTM1MTcgMTQuMzE5NTIyIDE2OC40ODcwMDYgMTUuMjU3Mzg5OEwxNjguNDg3MDA2IDIxLjkwMjUyNjdDMTY2LjQ4ODA4NyAyMC4zNjk5MTM0IDE2NC4zNjM1MjEgMTkuNTQ2NDE5NiAxNjEuNjU2NDEzIDE5LjU0NjQxOTYgMTU2Ljc2NzYyNyAxOS41NDY0MTk2IDE1My44Nzc3NjEgMjMuMjUyMTQxNCAxNTMuODc3NzYxIDI3Ljk1MjkxODEgMTUzLjg3Nzc2MSAzMy4xOTEyNTMyIDE1Ni44MjQ3MzkgMzYuMDA0ODU2OCAxNjEuMzAyMzE5IDM2LjAwNDg1NjggMTYzLjc4MDk3OSAzNi4wMDQ4NTY4IDE2NS42NjU2NzQgMzUuMjk1NzM3MiAxNjcuNzIxNzA2IDM0LjExNzY4MzdMMTY5Ljk2MDQ5NSAzOS41Mjc1OEMxNjcuNjY0NTk0IDQxLjE3NDU2NzQgMTY0LjA2NjUzOSA0Mi4zNTI2MjEgMTYwLjE4MjkyNCA0Mi4zNTI2MjEgMTUwLjkxOTM2IDQyLjM2NDA1ODQgMTQ1LjczMzU5MiAzNi4xODc4NTU0IDE0NS43MzM1OTIgMjguMDIxNTQyNnoiLz4gICAgPC9nPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0xOTQuNjg5OTg0LDE1Ljc5NDk0ODIgQzE5NC42ODk5ODQsMTIuMzI5NDEyMSAxOTEuODIyOTYzLDExLjEwNTYwODkgMTg4Ljc3MzE4MywxMS4xMDU2MDg5IEMxODQuNjgzOTY1LDExLjEwNTYwODkgMTgxLjMzNzIwMiwxMi40NDM3ODYyIDE3OC4yMzAzMTEsMTMuODM5MTUwNiBMMTc2LjA5NDMyMiw3LjQ0NTYzNjggQzE3OS41NjY3MzEsNS44NjcyNzM4MyAxODQuMzg2OTgyLDQuMDk0NDc0ODMgMTkwLjEyMTAyNSw0LjA5NDQ3NDgzIEMxOTkuMDg3NjA3LDQuMDk0NDc0ODMgMjAzLjQxNjY5NSw4LjYwMDgxNTUxIDIwMy40MTY2OTUsMTQuOTI1NzA0OCBDMjAzLjQxNjY5NSwyNS45OTcxMjA1IDE4OC4xNzkyMTgsMjkuMTA4MDk2OCAxODUuNjA5MTc5LDM3LjU2MDM0NSBMMjAzLjk2NDk3LDM3LjU2MDM0NSBMMjAzLjk2NDk3LDQ0LjYxNzIyODcgTDE3NC43NTc5MDIsNDQuNjE3MjI4NyBDMTc2LjQwMjcyNywyNC44NjQ4MTY2IDE5NC42ODk5ODQsMjMuNzA5NjM3OSAxOTQuNjg5OTg0LDE1Ljc5NDk0ODIgWiIvPiAgICA8cGF0aCBmaWxsPSIjMjE1Rjk4IiBmaWxsLXJ1bGU9Im5vbnplcm8iIGQ9Ik0yMDMuMTQyNTU4LDI5Ljc5NDM0MTYgTDIyMy4yNTczOTgsNC4yNDMxNjExOSBMMjI5LjIzMTMxMiw0LjI0MzE2MTE5IEwyMjkuMjMxMzEyLDI4LjM5ODk3NzIgTDIzNS4yMDUyMjUsMjguMzk4OTc3MiBMMjM1LjIwNTIyNSwzNS4wODk4NjM4IEwyMjkuMjMxMzEyLDM1LjA4OTg2MzggTDIyOS4yMzEzMTIsNDQuNjQwMTAzNSBMMjIxLjEyMTQxLDQ0LjY0MDEwMzUgTDIyMS4xMjE0MSwzNS4wODk4NjM4IEwyMDMuMTMxMTM1LDM1LjA4OTg2MzggTDIwMy4xMzExMzUsMjkuNzk0MzQxNiBMMjAzLjE0MjU1OCwyOS43OTQzNDE2IFogTTIxNy40NjYyNDMsMjguMzg3NTM5OCBMMjIxLjEyMTQxLDI4LjM4NzUzOTggTDIyMS4xMjE0MSwyNC4wNjQxOTc3IEMyMjEuMTIxNDEsMjAuOTY0NjU4OCAyMjEuMzYxMjgsMTcuMzczMzExMiAyMjEuNDg2OTI3LDE2LjU4NDEyOTcgTDIxMi40MDYxMjEsMjguNTcwNTM4NCBDMjEzLjEzNzE1NSwyOC41MTMzNTE0IDIxNi4wNjEyODgsMjguMzg3NTM5OCAyMTcuNDY2MjQzLDI4LjM4NzUzOTggWiIvPiAgICA8cGF0aCBmaWxsPSIjMDA2NkExIiBkPSJNMjU3LjgzMjk5MywwLjg0NjI0OTU2NCBDMjQ5LjgxNDQ3MSwwLjg0NjI0OTU2NCAyNDMuMzI2NTQ5LDcuMzU0MTM3NSAyNDMuMzI2NTQ5LDE1LjM3MTc2MzkgQzI0My4zMjY1NDksMjMuMzg5MzkwNCAyNDkuODI1ODkzLDI5Ljg5NzI3ODMgMjU3LjgzMjk5MywyOS44OTcyNzgzIEMyNjUuODUxNTE1LDI5Ljg5NzI3ODMgMjcyLjMzOTQzNywyMy4zODkzOTA0IDI3Mi4zMzk0MzcsMTUuMzcxNzYzOSBDMjcyLjMzOTQzNyw3LjM1NDEzNzUgMjY1Ljg0MDA5MywwLjg0NjI0OTU2NCAyNTcuODMyOTkzLDAuODQ2MjQ5NTY0IFogTTI1Ny44OTAxMDUsMjYuOTY5MzAwNiBDMjUxLjUwNDk4NSwyNi45NjkzMDA2IDI0Ni4zMzA2NCwyMS43ODgxNTI2IDI0Ni4zMzA2NCwxNS4zOTQ2Mzg4IEMyNDYuMzMwNjQsOS4wMDExMjQ5NiAyNTEuNTA0OTg1LDMuODE5OTc2OTIgMjU3Ljg5MDEwNSwzLjgxOTk3NjkyIEMyNjQuMjc1MjI1LDMuODE5OTc2OTIgMjY5LjQ0OTU3LDkuMDAxMTI0OTYgMjY5LjQ0OTU3LDE1LjM5NDYzODggQzI2OS40NDk1NywyMS43ODgxNTI2IDI2NC4yNjM4MDIsMjYuOTY5MzAwNiAyNTcuODkwMTA1LDI2Ljk2OTMwMDYgWiBNMjU5LjE1Nzk5MSw4LjQxNzgxNjkgTDI1Ni4xNDI0NzgsOC40MTc4MTY5IEwyNTYuMTQyNDc4LDE2LjcwOTk0MTIgTDI2NC4xNDk1NzgsMTYuNzA5OTQxMiBMMjY0LjE0OTU3OCwxMy44MTYyNzU4IEwyNTkuMTU3OTkxLDEzLjgxNjI3NTggTDI1OS4xNTc5OTEsOC40MTc4MTY5IFoiLz4gIDwvZz48L3N2Zz4=); }

		.setup-btn,
		.content-table input[type="submit"],
		.content-table input[type="button"]{
			height: 45px;
			line-height: 45px;
			color: #fff;
			background-color: #b5e00f;
			padding: 0 45px;
			text-transform: uppercase;
			text-decoration: none;
			font-family: "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
			vertical-align: middle;
			border-radius: 25px;
			transition: all 250ms ease;
			display: inline-block;
			font-size: 15px;
			border: none;
			cursor: pointer;
		}

		.setup-btn:hover {
			background-color: #9bc40e;
		}

		.lnk {
			color: #2fc6f7;
			font: 15px/25px "Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif;
			border-bottom: 1px solid;
			text-decoration: none;
			transition: all 250ms ease;
		}

		.lnk:hover {
			border-bottom-color: transparent;
		}

		.progressbar-container {
			width: 70%;
			margin: 55px auto;
		}

		.progressbar-track {
			height: 19px;
			background: #edf2f5;
			border-radius: 9px;
			overflow: hidden;
			position: relative;
		}

		.progressbar-loader {
			position: absolute;
			left: 0;
			top: 0;
			bottom: 0;
			border-radius: 9px;
			background-image: url(data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQECAgICAgICAgICAgMDAwMDAwMDAwP/2wBDAQEBAQEBAQEBAQECAgECAgMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwP/wgARCAATAEsDAREAAhEBAxEB/8QAGAABAQEBAQAAAAAAAAAAAAAABwYFCAT/xAAcAQEBAQABBQAAAAAAAAAAAAAEBQMAAQIGCAn/2gAMAwEAAhADEAAAAQH3m+e3Sk+lztQmvQKA2wScZcdsfU7NfVzoilWHXPH8zHek3PFnUgpIYmWrJISGUtoMOmVeWoMHFvXlqDCx7txXiSEytSvm4pG6G2ExPpf/xAAjEAACAgEDAwUAAAAAAAAAAAAEBQIDAQAGNBITMhQWIjEz/9oACAEBAAEFAoxtLIYGVo0YIsjS9ysoBrUgODj93teqG1wsEH7taesK2gHHve56e4jrxG1udk0lHiI0Dy5GkqpwXh33TJuHIiqXfO6y8zC5bobh6s4Mfs/jD/sy8AeSy89f/8QAMBEAAQIEAwUFCQAAAAAAAAAAAQACAwQRMRITIRAyNEFxBRRhgcEiIzNCUpGh0eH/2gAIAQMBAT8B1cfEp0cQYGXDumjE4BTExSFlMt6KEAXjFuqbmcbRDFlLUzA51gpuOYpDflClKMdmFd+18MXp+1GmBCIbX2kwktBddRZkMfgadUK0FbozPvMth1XVMmcyJgYdE4hoLjZQZgxXUbbZMcYa1uL2/HLZD43X6uf8Tt1ykOJ+91MfAi3tyXZu/E6eanuHffyXZu5E6+ez/8QAKxEAAQMDAQYFBQAAAAAAAAAAAQACAxESMQQFECEyM0ETNGFx8BQjQoHR/9oACAECAQE/AeDR6BNhMs178JxtaSoIKy+K/wCFSkhhplaWCwmQ5WorYWtyVpYfDBd+RWqq5tgX0fD1tUUBkBPZOADiG4UcF7LzhGleGENP9u9+50FjLn5QBJAGVLCI21Od0HlRSmO3zO6TyhpTl7JvMFreh/FB1o8Z7raHLH7rR9duP2toc7Pbd//EADcQAAADBAQKCgIDAAAAAAAAAAECAwARITEEEjKBExQiIzNBQlJhkQUQQ1FTYnFygsGhsRWTwv/aAAgBAQAGPwICxOtSFZjMx1DREbxYtDowuVMni6YhMTnB66/rEbxBkaMXtD5Rt0gROa4rJdGUXIwxCpuLsUVNwCEPEEHc2SIcMymOFX9hBs/MYMl0akLiwWXdw0Kf3yYKSqD0aG5SMjLdkFw5VzEoZDZmiW+4y5p/1lh6vZTpFUIJPSo7/EMGWcPaQXXtbLg/5XE+OL4rp/bjX4Y1KNsZKfuG0a4v7YY5tLNk/wBGvFlKUeBjhVKO6mETD8h/TKLGlZIHcQtljrGtqBhDd9ULBb/tlFjxOqYR5yAPRoaR1YfMseQfH6beOofmYwsFHRFxqmCKIbxtIp1A6tZPoncd7a6trRBo3VZcY1fywSmE5Xu1NtzLKrVv11WSlbC1Jk7doZuqy5vYktfrLZ8zJ27I2nVZ6na+r//EACUQAAIBBAICAgIDAAAAAAAAAAERACExUWFBcYGRELHR8KHB4f/aAAgBAQABPyEUikMJqRXqwmDc0jgYUCNw7iB6gQO7O9o+YoqyYI2AkNxDHwmIiF6EW+pnHeVQFQkWAvFoDZoEASMoIKvRDsJhQ0O9XscFAAiQoBuQXUbn7iR3H80gztL8dDuUEpyMhdCj+3wBEBECv8JfUGqLFrWDyKnZlBvQwyvyfcH7JF5TeEFBCEK56wjZFI6gXZNWJqakSdkx0psQkBPZUnRIjOTd35z3G+4jL+/fw1lHXEt7uE5fkefZtG0Vf0mC5U/KrlS3OOHHwex5Lx8XnoFe3pRmPWwPyls9fH//2gAMAwEAAgADAAAAEMj0WE70jTz++Bbl/wD/xAAlEQACAQMDAwUBAAAAAAAAAAABEUEAITFRcYEQodFhkbHB4fD/2gAIAQMBAT8QbJLN3NBJMEOcmnmUCb7TQQ2yCtAefNR+FniOaIb6z9D79qAUWXmPNDeftP55ojVsWG8n2+a2MHEv5ih4Bg9h+0X64Ot1zuYrIcL0+/MuZ9qaDKiyp2E0UlAGaJzW3O3T0x8Vu+Szd9Mdzfm+7SGoprDaOE8Q7PR2pMsr1OFZ6uHTWJYPh95WL0sLTLlELWWqa1MQ1lxqr8OliaawmFovV9P/xAAlEQACAQMCBgMBAAAAAAAAAAABEUEAITFRgRBhcaGx0ZHB4fD/2gAIAQIBAT8QwQEDxRSNsztgUsBcCikuEF7vXqpVwh72oIboH2fr5ojNQbT6oJ4ej99UA+M3PTSnhJu8fHejJFtt6FhYVRUP5E0AEuVYFZPb9pXQFDTYdzFDTZFCg4d9hw5ofmnW3g8W4bYf1xrKc0l1JjLWZV1qq0cMcm7jRSqW5BJdu3PNq2TYxiZekZpL8DLSFPXyqadN0zEvV8lw/8QAJBAAAgICAQMEAwAAAAAAAAAAAREAITFBUWGBkXGx8PEQodH/2gAIAQEAAT8QGG4TDsmyJZWmYY0dvgJVQL2mh+AioEtOEjYPKDcPgUi7XLYYBQxOY55zIY5GOhx2HURcAqzGSEi0rgYxCYCcTi8PVIc80PeBGqaDYqbA+RgihgUCk9C0AQQTiffKjg2745gm1fEKqxTUdWFiAoyyEzQigSJA5kLwDdRLSOQEWL6BhWscoSGhRgH1IkQqfLh1ayCxNgeIAi1s4ATS4hgCIeiemGySTBhh3AFs8WW6ULtkmDDlrBcleOJsBqf3l7/lmdgLbdXVdq0pQ5OcnOc76yx8y64vM3E1N2kr9qWsT46WvWfuKiXcPYXydtDpzP8AykWHscovL2tre+LBPanGvbA/qqn4/9k=);
			background-position: 0 0;
			background-size: auto 19px;
			-webkit-animation: progressbar 2s infinite linear;
			-moz-animation: progressbar 2s infinite linear;
			-ms-animation: progressbar 2s infinite linear;
			-o-animation: progressbar 2s infinite linear;
			animation: progressbar 2s infinite linear;
		}

		@-webkit-keyframes progressbar { 0 {background-position: 0 0;} 100% {background-position: 75px 0;} }
		@-moz-keyframes progressbar { 0 {background-position: 0 0;} 100% {background-position: 75px 0;} }
		@-ms-keyframes progressbar { 0 {background-position: 0 0;} 100% {background-position: 75px 0;} }
		@-o-keyframes progressbar { 0 {background-position: 0 0;} 100% {background-position: 75px 0;} }
		@keyframes progressbar { 0 {background-position: 0 0;} 100% {background-position: 75px 0;} }

		.progressbar-counter {
			padding-top: 10px;
			color: #000;
			font: 300 28px/29px "Helvetica Neue", Helvetica, Arial, sans-serif;
			text-align: center;
		}

		.lang {
			vertical-align: middle;
			text-align: left;
			box-sizing: border-box;
			color: #333;
			font: 12px/22px "Helvetica Neue", Helvetica, Arial, sans-serif;
			display: block;
			text-decoration: none;
			padding: 5px 5px 5px 35px;
		}
		.lang:after {
			background: no-repeat center url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAzCAMAAACpFXjLAAAAk1BMVEX///8TLa4TLq4ULq8bQOccQOc4N2A4N2FAP2VAQGZNTYBOToBOToFXV4dXWIdYWIdYWIhZW4paW4paW4thYY9iYo9iYpCPJR6QJR6uJRKvJRKvJhO/v7/AngDAnwDAwMDBMyzBMy3BNC3CNC3nNRfnNhfnNhjp6O7p6e/p6fD9/f3+/v7/0gD/0wD/1AD///8wMDDKTuTrAAAAAXRSTlMAQObYZgAAARRJREFUeNqtkGFvgyAQhk+n1lW7OV2rRVmFKtoBdv//1+3ARKf2U7PnuDfh8UJyQmW5jAcLLlLK+9wVVPL+h5VAKnA8z8djG8MFxzdmwoEXH+/W+KbthGWUKIgQjRCibRvRYhEgbWtVY4QQJU7gt3HKmDOUhJwJRoltgA1BGIS71yDI8yIvMCDsjt2pS/ZypIJdlnwk6SHmhisvIHjvT/3n7TBtG7xlSZpk8ZXVnDNm3+iPtySaJ9I426dRxGvGasbz7R8rmHmcMyzETsx8y4cTK2CDuwJcb8H/CDosoA+E1oPWJgYMhULpAS/mhmGEVlorxKZGQekXtTEmbPhZ8YwoxILiGUHVAmqFVtMyRszLIdvlfgFLUlmfWPoXcwAAAABJRU5ErkJggg==);
			content: '';
			display: block;
			width:16px;
			height:12px;
			position: absolute;
			top: 50%;
			left: 12px;
			margin-top: -6px;
		}
		.lang.ru:after{ background-position: 0 0;}
		.lang.en:after{ background-position: 0 -13px;}
		.lang.ua:after{ background-position: 0 -26px;}
		.lang.de:after{ background-position: 0 -39px;}
		/**/
		.select-container{
			border:2px solid #d5dde0;
			height: 32px;
			position: absolute;
			right: 0;
			bottom: 0;
			width:50px;
			border-radius: 2px;
		}

		.select-container > .select-block,
		.selected-lang {
			width:100%;
			display: block;
			height: 32px;
			position: relative;
			cursor: pointer;
		}

		.selected-lang:before{
			content: '';
			border:4px solid #fff;
			border-top:4px solid #7e939b;
			display: block;
			position: absolute;
			right: 8px;
			top: 15px;
		}
		.selected-lang:after{
			left:11px;
		}

		.select-popup{
			display: none;
			position: absolute;
			top:100%;
			z-index:100;
			min-width:100%;
			border-radius:2px;
			padding:5px 0;
			background-color: #fff;
			box-shadow: 0 5px 5px rgba(0,0,0,.4);
		}
		.select-lang-item{
			height: 32px;
			width:100%;
			position: relative;
			padding:0 10px;
			box-sizing: border-box;
			transition: 220ms all ease;
		}
		.select-lang-item:hover{
			background-color: #f3f3f3;
		}
		.sub-header{
			font-size:21px;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			text-align: left;
			margin-bottom: 10px;
		}

		.select-version-container{
			padding: 10px 20px 20px;
			text-align: left;
			font-size:16px;
			line-height:25px;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
		}

		.content-table {
			text-align: left;
			font-size:16px;
			font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
			width: 100%;
		}

		.input-license-container input,
		.content-table input[type="text"],
		.content-table input[type="password"],
		.content-table select{
			/*width:100%;*/
			box-sizing: border-box;
			border: 2px solid #e0e6e9;
			border-radius:3px;
			font-size:15px;
			padding: 10px;
			outline: none !important;
		}
		.input-license-container{
			padding-bottom:40px;
		}

		.div-tool
		{
			border:1px solid #CCCCCC;
			padding:10px;
			margin: 10px;
		}
		.t_div
		{
			padding:5px;
		}
	</style>
	<script>
		var frm;
		function reloadPage(val, delay)
		{
			frm = document.forms.restore;
			frm.Step.value = val;
			window.setTimeout(function() {frm.submit()}, delay == null ? 0 : delay * 1000);
		}

		function addFileField()
		{
			var input = document.createElement('input');
			input.type = 'file';
			input.name = 'archive[]';
			input.size = 40;
			input.onchange = addFileField;
			input.multiple = true;

			var div = document.getElementById('div2');
			div.appendChild(input);
		}

		function div_show(i)
		{
			document.getElementById('start_button').disabled = i == 0;
			for(j=0;j<=4;j++)
			{
				if (ob = document.getElementById('div' + j))
					ob.style.display = i == j ? 'block' : 'none';
			}

			arSources = [ 'bitrixcloud','download','upload','local','dump' ];
			document.forms.restore.source.value = arSources[i];

			if (i == 2)
				document.forms.restore.action = '/restore.php?lang=<?=LANG?>&Step=2&source=' + arSources[i];
		}

		function LoadFileList()
		{
			xml = new XMLHttpRequest(); // forget IE6

			xml.onreadystatechange = function ()
			{
				if (xml.readyState == 4)
				{
					str = xml.responseText;
					document.getElementById('file_list').innerHTML = str;
					document.getElementById('start_button').disabled = !/<select/.test(str);
				}
			}

			xml.open('POST', '/restore.php', true);
			query = 'LoadFileList=Y&lang=<?=LANG?>&bitrixcloud_backup=<?=htmlspecialcharsbx($_REQUEST['bitrixcloud_backup'] ?? '')?>&license_key=' + document.getElementById('license_key').value;

			xml.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
			xml.send(query);
		}

		<?
		if (isset($_REQUEST['arc_down_url']) && $_REQUEST['arc_down_url'])
		{
			?>
			window.onload = div_show(1);
			<?
		}
		elseif (isset($_REQUEST['bitrixcloud_backup']) && $_REQUEST['bitrixcloud_backup'])
		{
			?>
			window.onload = function() {
				div_show(0);
				LoadFileList();
			}
			<?
		}
		?>
	</script>
	<div class="wrap <?=LANG?>">
		<form name="restore" name="restore" action="restore.php" enctype="multipart/form-data" method="POST">
			<input type="hidden" name="lang" value="<?=LANG?>">
			<input type="hidden" name="source">
			<input type="hidden" name="skip">
			<input type="hidden" name="Step">

			<header class="header">
				<?if ($isCrm):?>
					<a href="" target="_blank" class="logo-link"><span class="logo <?=LANG?>"></span></a>
				<?else:?>
					<a href="" target="_blank" class="buslogo-link"><span class="buslogo <?=LANG?>"></span></a>
				<?endif?>
			</header>

			<section class="content">
				<div class="content-container">
					<table class="content-table">
						<tr>
							<td valign="middle">
								<table cellpadding=0 cellspacing=0 border=0 width=100%><tr>
									<td align=left >
										<h3 class="content-header" style="margin: 0;padding: 0;text-align: left;"><?=$ar['TITLE']?></h3>
										<hr style="margin: 15px 0;">
									</td>
									<td align=right>
										<?
										$arLang = array();
										foreach(array('en') as $l)
											$arLang[] = LANG == $l ? "<span style='color:grey'>$l</span>" : "<a href='?lang=$l' style='color:black'>$l</a>";
				#						echo implode(' | ',$arLang);
										?>
									</td>
								</tr></table>

							</td>
						</tr>
						<tr>
							<td style="padding:10px;font-size:10pt" valign="<?=($ar['TEXT_ALIGN'] ?? 'top')?>">
								<?=$ar['TEXT']?>
							</td>
						</tr>
						<tr>
							<td style="font-size:10pt" align="center" valign="middle"  height="40px"><?=$ar['BOTTOM']?></td>
						</tr>
					</table>

					<div class="content-block">
						<div class="select-container" onclick="document.getElementById('lang-popup').style.display = document.getElementById('lang-popup').style.display == 'block' ? 'none' : 'block'">
							<label for="ss"><span class="selected-lang lang <?=LANG?>"></span></label>
							<div class="select-popup" id="lang-popup">
								<?
								foreach(array('en','de','ru') as $l)
								{
									?>
									<div class="select-lang-item">
										<a href="?lang=<?=$l?>" class="lang <?=$l?>"><?=$l?></a>
									</div>
									<?
								}
								?>
							</div>
						</div>
					</div>
				</div>
			</section>
			<div class="cloud-layer">
				<div class="cloud cloud-1 cloud-fill"></div>
				<div class="cloud cloud-2 cloud-border"></div>
				<div class="cloud cloud-3 cloud-border"></div>
				<div class="cloud cloud-4 cloud-border"></div>
				<div class="cloud cloud-5 cloud-border"></div>
				<div class="cloud cloud-6 cloud-border"></div>
			</div>
		</form>
	</div>
	</body></html>
<?
}

function SetCurrentProgress($cur,$total=0,$red=true)
{
	global $status;
	if (!$total)
	{
		$total=100;
		$cur=0;
	}
	$val = intval($cur/$total*100);
	if ($val > 100)
		$val = 99;

	$status = '
	<div class="progressbar-container">
		<div class="progressbar-track">
			<div class="progressbar-loader" style="width:'.$val.'%"></div>
		</div>
		<div class="progressbar-counter">'.$val.'%</div>
	</div>';
}

function LoadFile($strRealUrl, $strFilename, $arHeaders = array(), $customHost = '')
{
	global $proxyaddr, $proxyport, $strUserAgent, $replycode;
	$ssl = preg_match('#^https://#i', $strRealUrl);

	$iStartSize = 0;
	if (file_exists($strFilename.".tmp"))
		$iStartSize = filesize($strFilename.".tmp");

	$parsedurl = parse_url($strRealUrl);
	$strOriginalFile = basename($parsedurl['path']);
	SetCurrentStatus(getMsg("LOADER_LOAD_QUERY_DISTR", ["#DISTR#" => $strOriginalFile]));
	$sockethandle = false;

	do
	{
		$lasturl = $strRealUrl;
		$redirection = "";

		$parsedurl = parse_url($strRealUrl);
		$useproxy = (($proxyaddr != "") && ($proxyport != ""));

		if (!$useproxy)
		{
			$host = $parsedurl["host"];
			$port = $parsedurl["port"] ?? null;
			$hostname = $host;
		}
		else
		{
			$host = $proxyaddr;
			$port = $proxyport;
			$hostname = $parsedurl["host"];
		}
		if ($customHost)
			$host = $customHost;
		SetCurrentStatus(getMsg("LOADER_LOAD_CONN2HOST", ["#HOST#" => $host]));

		$port = $port ? $port : ($ssl ? 443 : 80);

		$context = stream_context_create(
			array(
				'ssl' => array(
					'verify_peer' => false,
					'allow_self_signed' => true,
				)
			)
		);
		$sockethandle = stream_socket_client(($ssl ? 'ssl://' : '').$host.':'.$port, $error_id, $error_msg, 10, STREAM_CLIENT_CONNECT, $context);
		if (!$sockethandle)
		{
			SetCurrentStatus(getMsg("LOADER_LOAD_NO_CONN2HOST", ["#HOST#" => $host])." [".$error_id."] ".$error_msg);
			return false;
		}
		else
		{
			debug("\n======\nConnected to ".($ssl ? 'ssl://' : '')."$host:$port\n");

			if (!$parsedurl["path"])
				$parsedurl["path"] = "/";

			$request = "";
			if (!$useproxy)
			{
				$request .= "GET ".$parsedurl["path"].(isset($parsedurl["query"]) ? '?'.$parsedurl["query"] : '')." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
			}
			else
			{
				$request .= "GET ".$strRealUrl." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
			}

			if ($strUserAgent != "")
				$request .= "User-Agent: $strUserAgent\r\n";

			if ($iStartSize > 0)
				$request .= "Range: bytes=".$iStartSize."-\r\n";

			foreach($arHeaders as $k => $v)
				$request .= $k.': '.$v."\r\n";

			$request .= "\r\n";

			fwrite($sockethandle, $request);

			$result = "";

			$replyheader = "";
			while (($result = fgets($sockethandle, 4096)) && $result!="\r\n")
				$replyheader .= $result;

			debug("\n======\n".$request."\n\n".$replyheader);
			$ar_replyheader = explode("\r\n", $replyheader);

			$replyproto = "";
			$replyversion = "";
			$replycode = 0;
			$replymsg = "";
			if (preg_match("#([A-Z]{4})/([0-9.]{3}) ([0-9]{3})#", $ar_replyheader[0], $regs))
			{
				$replyproto = $regs[1];
				$replyversion = $regs[2];
				$replycode = intval($regs[3]);
				$replymsg = mb_substr($ar_replyheader[0],mb_strpos($ar_replyheader[0],$replycode) + mb_strlen($replycode) + 1,mb_strlen($ar_replyheader[0]) - mb_strpos($ar_replyheader[0],$replycode) + 1);
			}

			if ($replycode!=200 && $replycode!=206 && $replycode!=302 && $replycode!=301)
			{
				if ($replycode==403)
					SetCurrentStatus(getMsg("LOADER_LOAD_SERVER_ANSWER1", ["#ANS#" => $replycode." - ".$replymsg]));
				else
					SetCurrentStatus(getMsg("LOADER_LOAD_SERVER_ANSWER", ["#ANS#" => $replycode." - ".$replymsg]));
				return false;
			}

			$strContentRange = "";
			$strAcceptRanges = "";
			$strLocationUrl = "";
			$iNewRealSize = 0;
			foreach ($ar_replyheader as $i => $headerLine)
			{
				if (mb_strpos($headerLine,"Content-Range") !== false)
					$strContentRange = trim(mb_substr($headerLine,mb_strpos($headerLine,":") + 1,mb_strlen($headerLine) - mb_strpos($headerLine,":") + 1));
				elseif (mb_strpos($headerLine,"Location") === 0)
					$strLocationUrl = trim(mb_substr($headerLine,mb_strpos($headerLine,":") + 1,mb_strlen($headerLine) - mb_strpos($headerLine,":") + 1));
				elseif (mb_strpos($headerLine,"Content-Length") !== false)
					$iNewRealSize = intval(Trim(mb_substr($headerLine,mb_strpos($headerLine,":") + 1,mb_strlen($headerLine) - mb_strpos($headerLine,":") + 1)));
				elseif (mb_strpos($headerLine,"Accept-Ranges") !== false)
					$strAcceptRanges = Trim(mb_substr($headerLine,mb_strpos($headerLine,":") + 1,mb_strlen($headerLine) - mb_strpos($headerLine,":") + 1));
			}

			if (mb_strlen($strLocationUrl)>0)
			{
				$redirection = $strLocationUrl;
				$redirected = true;
				if (!preg_match('#^https?://#', $redirection))
					$strRealUrl = dirname($lasturl)."/".$redirection;
				else
					$strRealUrl = $redirection;
				$ssl = preg_match('#^https://#i', $strRealUrl);
			}

			if (!$strLocationUrl) // нет редиректа, загружаем файл
			{

				if (mb_strpos($strRealUrl,$strOriginalFile) === false)
				{
					SetCurrentStatus(getMsg("LOADER_LOAD_CANT_REDIRECT", ["#URL#" => htmlspecialcharsbx($strRealUrl)]));
					return false;
				}

				SetCurrentStatus(getMsg("LOADER_LOAD_LOAD_DISTR", ["#DISTR#" => $strRealUrl]));

				$fh = fopen($strFilename.".tmp", "ab");
				if (!$fh)
				{
					SetCurrentStatus(getMsg("ERROR_CANT_WRITE", ["#FILE#" => $strFilename.".tmp", '#SPACE#' => freeSpace()]));
					return false;
				}

				$bFinished = True;
				$downloadsize = (double) $iStartSize;
				SetCurrentStatus(getMsg("LOADER_LOAD_LOADING"));
				while (!feof($sockethandle))
				{
					if (!haveTime())
					{
						$bFinished = False;
						break;
					}

					$result = fread($sockethandle, 40960);
					$downloadsize += mb_strlen($result);
					if ($result=="")
						break;

					if (fwrite($fh, $result) === false)
					{
						SetCurrentStatus(getMsg("ERROR_CANT_WRITE", ["#FILE#" => $strFilename.".tmp", '#SPACE#' => freeSpace()]));
						return false;
					}
				}
				SetCurrentProgress($downloadsize,$iNewRealSize);

				fclose($fh);
				fclose($sockethandle);

				if ($bFinished)
				{
					bx_unlink($strFilename);
					if (rename($strFilename.".tmp", $strFilename))
					{
						SetCurrentStatus(getMsg("LOADER_LOAD_FILE_SAVED", ["#FILE#" => $strFilename, "#SIZE#" => $downloadsize]));
						return 1;
					}
					else
					{
						SetCurrentStatus(getMsg("LOADER_LOAD_ERR_RENAME", ["#FILE1#" => $strFilename.".tmp", "#FILE2#" => $strFilename]));
						return false;
					}
				}
				else
					return 2;
			}
			fclose($sockethandle);
		}
	}
	while (true);
}

function SetCurrentStatus($str)
{
	global $strLog;
	$strLog .= $str."\n";
}

class CTar
{
	var $gzip;
	var $file;
	var $err = array();
	var $LastErrCode;
	var $res;
	var $Block = 0;
	var $BlockHeader;
	var $path;
	var $FileCount = 0;
	var $DirCount = 0;
	var $ReadBlockMax = 2000;
	var $ReadBlockCurrent = 0;
	var $ReadFileSize = 0;
	var $header = null;
	var $ArchiveSizeLimit;
	const BX_EXTRA = 'BX0000';
	const BX_SIGNATURE = 'Bitrix Encrypted File';
	var $BufferSize;
	var $Buffer;
	var $dataSizeCache = array();
	var $EncryptKey;
	var $prefix = '';

	##############
	# READ
	# {
	function openRead($file)
	{
		if (!isset($this->gzip) && (self::substr($file,-3)=='.gz' || self::substr($file,-4)=='.tgz'))
			$this->gzip = true;

		$this->BufferSize = 51200;

		if ($this->open($file, 'r'))
		{
			if ('' !== $str = $this->gzip ? gzread($this->res,512) : fread($this->res,512))
			{
				$data = unpack("a100empty/a90signature/a10version/a56tail/a256enc", $str);
				if (trim($data['signature']) != self::BX_SIGNATURE)
				{
					if (self::strlen($this->EncryptKey))
						$this->Error('Invalid encryption signature','ENC_SIGN');

					// Probably archive is not encrypted
					$this->gzip ? gzseek($this->res, 0) : fseek($this->res, 0);
					$this->EncryptKey = null;

					return $this->res;
				}

				$version = trim($data['version']);
				if (version_compare($version, '1.2', '>'))
					return $this->Error('Unsupported archive version: '.$version, 'ENC_VER');

				$key = $this->getEncryptKey();
				$this->BlockHeader = $this->Block = 1;

				if (!$key || self::substr($str, 0, 256) != self::decrypt($data['enc'], $key))
					return $this->Error('Invalid encryption key', 'ENC_KEY');
			}
		}
		return $this->res;
	}

	function readBlock($bIgnoreOpenNextError = false)
	{
		if (!$this->Buffer)
		{
			$str = $this->gzip ? gzread($this->res, $this->BufferSize) : fread($this->res, $this->BufferSize);
			if ($str === '' && $this->openNext($bIgnoreOpenNextError))
				$str = $this->gzip ? gzread($this->res, $this->BufferSize) : fread($this->res, $this->BufferSize);
			if ($str !== '' && $key = $this->getEncryptKey())
				$str = self::decrypt($str, $key);
			$this->Buffer = $str;
		}

		$str = '';
		if ($this->Buffer)
		{
			$str = self::substr($this->Buffer, 0, 512);
			$this->Buffer = self::substr($this->Buffer, 512);
			$this->Block++;
		}

		return $str;
	}

	function SkipFile()
	{
		if ($this->Skip(ceil(intval($this->header['size'])/512)))
		{
			$this->header = null;
			return true;
		}
		return false;
	}

	function Skip($Block)
	{
		if ($Block == 0)
			return true;

		$this->Block += $Block;
		$toSkip = $Block * 512;

		if (self::strlen($this->Buffer) > $toSkip)
		{
			$this->Buffer = self::substr($this->Buffer, $toSkip);
			return true;
		}
		$this->Buffer = '';
		$NewPos = $this->Block * 512;

		if ($ArchiveSize = $this->getDataSize($file = self::getFirstName($this->file)))
		{
			while($NewPos > $ArchiveSize)
			{
				$file = $this->getNextName($file);
				$NewPos -= $ArchiveSize;
			}
		}

		if ($file != $this->file)
		{
			$this->close();
			if (!$this->open($file, $this->mode))
				return false;
		}

		if (0 === ($this->gzip ? gzseek($this->res, $NewPos) : fseek($this->res, $NewPos)))
			return true;
		return $this->Error('File seek error (file: '.$this->file.', position: '.$NewPos.')');
	}

	function SkipTo($Block)
	{
		return $this->Skip($Block - $this->Block);
	}

	function readHeader($Long = false)
	{
		$str = '';
		while(trim($str) == '')
		{
			if (!($l = self::strlen($str = $this->readBlock($bIgnoreOpenNextError = true))))
				return 0; // finish
		}

		if (!$Long)
			$this->BlockHeader = $this->Block - 1;

		if ($l != 512)
			return $this->Error('Wrong block size: '.self::strlen($str).' (block '.$this->Block.')');


		$data = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1type/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix", $str);
		$chk = $data['devmajor'].$data['devminor'];

		if (!is_numeric(trim($data['checksum'])) || ($chk != '' && (int)$chk != 0))
		{
			return $this->Error('Archive is corrupted, wrong block: '.($this->Block-1).', file: '.$this->file.', md5sum: '.md5_file($this->file));
		}

		$header['filename'] = trim(trim($data['prefix'], "\x00").'/'.trim($data['filename'], "\x00"),'/');
		$header['mode'] = OctDec($data['mode']);
		$header['uid'] = OctDec($data['uid']);
		$header['gid'] = OctDec($data['gid']);
		$header['size'] = OctDec($data['size']);
		$header['mtime'] = OctDec($data['mtime']);
		$header['type'] = trim($data['type'], "\x00");
//		$header['link'] = $data['link'];

		if (self::strpos($header['filename'],'./') === 0)
			$header['filename'] = self::substr($header['filename'], 2);

		if ($header['type']=='L') // Long header
		{
			$filename = '';
			$n = ceil($header['size']/512);
			for ($i = 0; $i < $n; $i++)
				$filename .= $this->readBlock();

			if (!is_array($header = $this->readHeader($Long = true)))
				return $this->Error('Wrong long header, block: '.$this->Block);
			$header['filename'] = self::substr($filename,0,self::strpos($filename,chr(0)));
		}

		if (self::strpos($header['filename'],'/') === 0) // trailing slash
			$header['type'] = 5; // Directory

		if ($header['type']=='5')
			$header['size'] = '';

		if ($header['filename']=='')
			return $this->Error('Filename is empty, wrong block: '.($this->Block-1));

		if (!$this->checkCRC($str, $data))
			return $this->Error('Checksum error on file: '.$header['filename']);

		$this->header = $header;

		return $header;
	}

	function checkCRC($str, $data)
	{
		$checksum = $this->checksum($str);
		$res = octdec($data['checksum']) == $checksum || $data['checksum']===0 && $checksum==256;
		return $res;
	}

	function extractFile()
	{
		$rs = false;
		if ($this->header === null)
		{
			if(($header = $this->readHeader()) === false || $header === 0 || $header === true)
			{
				if ($header === true && $this->SkipFile() === false)
					return false;
				return $header;
			}

			$this->lastPath = $f = $this->path.'/'.$header['filename'];

			if ($this->ReadBlockCurrent == 0)
			{
				if ($header['type']==5) // dir
				{
					if(!file_exists($f) && !self::xmkdir($f))
						return $this->ErrorAndSkip('Can\'t create folder: '.$f);
					//chmod($f, $header['mode']);
				}
				else // file
				{
					if (!self::xmkdir($dirname = dirname($f)))
						return $this->ErrorAndSkip('Can\'t create folder: '.$dirname);
					elseif (($rs = fopen($f, 'wb'))===false)
						return $this->ErrorAndSkip(getMsg('ERROR_CANT_WRITE', ['#FILE#' => $f, '#SPACE#' => freeSpace()]));
				}
			}
			else
				return $this->Skip($this->ReadBlockCurrent);
		}
		else // файл уже частично распакован, продолжаем на том же хите
		{
			$header = $this->header;
			$this->lastPath = $f = $this->path.'/'.$header['filename'];
		}

		if ($header['type'] != 5) // пишем контент в файл
		{
			if (!$rs)
			{
				if (($rs = fopen($f, 'ab'))===false)
					return $this->ErrorAndSkip(getMsg('ERROR_CANT_WRITE', ['#FILE#' => $f, '#SPACE#' => freeSpace()]));
			}

			$i = 0;
			$FileBlockCount = ceil($header['size'] / 512);
			while(++$this->ReadBlockCurrent <= $FileBlockCount && ($contents = $this->readBlock()))
			{
				if ($this->ReadBlockCurrent == $FileBlockCount && ($chunk = $header['size'] % 512))
					$contents = self::substr($contents, 0, $chunk);

				$ret = fwrite($rs, $contents);
				if ($ret === false || $ret === 0)
					return $this->Error(getMsg('ERROR_CANT_WRITE', ['#FILE#' => $f, '#SPACE#' => freeSpace()]));

				if ($this->ReadBlockMax && ++$i >= $this->ReadBlockMax)
				{
					fclose($rs);
					return true; // Break
				}
			}
			fclose($rs);

			if (($s = filesize($f)) != $header['size'])
				return $this->Error('File size is wrong: '.$header['filename'].' (real: '.$s.'  expected: '.$header['size'].')');

			//chmod($f, $header['mode']);
		}

		if ($this->header['type']==5)
			$this->DirCount++;
		else
			$this->FileCount++;

		$this->debug_header = $this->header;
		$this->BlockHeader = $this->Block;
		$this->ReadBlockCurrent = 0;
		$this->header = null;

		return true;
	}

	function openNext($bIgnoreOpenNextError)
	{
		if (file_exists($file = $this->getNextName()))
		{
			$this->close();
			return $this->open($file,$this->mode);
		}
		elseif (!$bIgnoreOpenNextError)
			return $this->Error("File doesn't exist: ".$file);
		return false;
	}

	public static function getLastNum($file)
	{
		$file = self::getFirstName($file);

		if (!file_exists($file))
			return false;
		$f = fopen($file, 'rb');
		fseek($f, 12);
		if (fread($f, 2) == 'LN')
			$res = end(unpack('va',fread($f, 2)));
		else
			$res = false;
		fclose($f);
		return $res;
	}

	# }
	##############

	##############
	# BASE
	# {
	function open($file, $mode='r')
	{
		$this->file = $file;
		$this->mode = $mode;

		if (is_dir($file))
			return $this->Error('File is directory: '.$file);

		if ($this->EncryptKey && !function_exists('mcrypt_encrypt') && !function_exists('openssl_encrypt'))
			return $this->Error('Function mcrypt_encrypt/openssl_encrypt is not available');

		if ($mode == 'r' && !file_exists($file))
			return $this->Error('File does not exist: '.$file);

		if ($this->gzip)
		{
			if(!function_exists('gzopen'))
				return $this->Error('Function &quot;gzopen&quot; is not available');
			else
			{
				if ($mode == 'a' && !file_exists($file) && !$this->createEmptyGzipExtra($file))
					return false;
				$this->res = gzopen($file,$mode."b");
			}
		}
		else
			$this->res = fopen($file,$mode."b");

		return $this->res;
	}

	function close()
	{
		if ($this->mode == 'a')
			$this->flushBuffer();

		if ($this->gzip)
		{
			gzclose($this->res);

			if ($this->mode == 'a')
			{
				// добавим фактический размер всех несжатых данных в extra поле
				$f = fopen($this->file, 'rb+');
				fseek($f, 18);
				fwrite($f, pack("V", $this->ArchiveSizeCurrent));
				fclose($f);

				$this->dataSizeCache[$this->file] = $this->ArchiveSizeCurrent;

				// сохраним номер последней части в первый архив для многотомных архивов
				if (preg_match('#^(.+)\.([0-9]+)$#', $this->file, $regs))
				{
					$f = fopen($regs[1], 'rb+');
					fseek($f, 12);
					fwrite($f, 'LN'.pack("v",$regs[2]));
					fclose($f);
				}
			}
		}
		else
			fclose($this->res);
		clearstatcache();
	}

	public function getNextName($file = '')
	{
		if (!$file)
			$file = $this->file;

		static $CACHE;
		$c = &$CACHE[$file];

		if (!$c)
		{
			$l = mb_strrpos($file,'.');
			$num = self::substr($file,$l+1);
			if (is_numeric($num))
				$file = self::substr($file,0,$l+1).++$num;
			else
				$file .= '.1';
			$c = $file;
		}
		return $c;
	}

	function checksum($s)
	{
		$chars = count_chars(self::substr($s,0,148).'        '.self::substr($s,156,356));
		$sum = 0;
		foreach($chars as $ch => $cnt)
			$sum += $ch*$cnt;
		return $sum;
	}

	public static function substr($s, $a, $b = null)
	{
		if (function_exists('mb_orig_substr'))
			return $b === null ? mb_orig_substr($s, $a) : mb_orig_substr($s, $a, $b);
		return $b === null ? mb_substr($s,$a) : mb_substr($s,$a,$b);
	}

	public static function strlen($s)
	{
		if (function_exists('mb_orig_strlen'))
			return mb_orig_strlen($s);
		return mb_strlen($s);
	}

	public static function strpos($s, $a)
	{
		if (function_exists('mb_orig_strpos'))
			return mb_orig_strpos($s, $a);
		return mb_strpos($s,$a);
	}

	function getDataSize($file)
	{
		$size = &$this->dataSizeCache[$file];
		if (!$size)
		{
			if (!file_exists($file))
				$size = false;
			else
			{
				if (preg_match('#\.gz(\.[0-9]+)?$#',$file))
				{
					$f = fopen($file, "rb");
					fseek($f, 16);
					if (fread($f, 2) == 'BX')
						$size = end(unpack("V", fread($f, 4)));
					else
					{
//						$this->Error('Wrong GZIP Extra Field');
						$size = false;
					}
					fclose($f);
				}
				else
					$size = filesize($file);
			}
		}

		return $size;
	}

	function Error($str = '', $code = '')
	{
		if ($code)
			$this->LastErrCode = $code;
		$this->err[] = $str;
		return false;
	}

	function ErrorAndSkip($str = '', $code = '')
	{
		$this->Error($str, $code);
		$this->SkipFile();
		if ($this->readHeader() === 0)
			$this->BlockHeader = $this->Block;
		return false;
	}

	public static function xmkdir($dir)
	{
		if (!file_exists($dir))
		{
			$upper_dir = dirname($dir);
			if (!file_exists($upper_dir) && !self::xmkdir($upper_dir))
				return $this->Error('Can\'t create folder: '.$upper_dir);

			return mkdir($dir);
		}

		return is_dir($dir);
	}

	function getEncryptKey()
	{
		if (!$this->EncryptKey)
			return false;
		static $key;
		if (!$key)
			$key = md5($this->EncryptKey);
		return $key;
	}

	function getFileInfo($f)
	{
		$f = str_replace('\\', '/', $f);
		$path = self::substr($f,self::strlen($this->path) + 1);

		$ar = array();

		if (is_dir($f))
		{
			$ar['type'] = 5;
			$path .= '/';
		}
		else
			$ar['type'] = 0;

		if (!$info = stat($f))
			return $this->Error('Can\'t get file info: '.$f);

		if ($info['size'] < 0)
			return $this->Error('File is too large: '.$f);

		$ar['mode'] = 0777 & $info['mode'];
		$ar['uid'] = $info['uid'];
		$ar['gid'] = $info['gid'];
		$ar['size'] = $ar['type']==5 ? 0 : $info['size'];
		$ar['mtime'] = $info['mtime'];
		$ar['filename'] = $this->prefix.$path;

		return $ar;
	}

	public static function getCheckword($key)
	{
		return md5('BITRIXCLOUDSERVICE'.$key);
	}

	public static function getFirstName($file)
	{
		return preg_replace('#\.[0-9]+$#','',$file);
	}

	public static function encrypt($data, $md5_key)
	{
		if ($m = self::strlen($data)%8)
			$data .= str_repeat("\x00",  8 - $m);
		if (function_exists('openssl_encrypt'))
			return openssl_encrypt($data, 'BF-ECB', $md5_key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
		else
			return mcrypt_encrypt(MCRYPT_BLOWFISH, $md5_key, $data, MCRYPT_MODE_ECB);
	}

	public static function decrypt($data, $md5_key)
	{
		if (function_exists('openssl_encrypt'))
			$val = openssl_decrypt($data, 'BF-ECB', $md5_key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING);
		else
			$val = mcrypt_decrypt(MCRYPT_BLOWFISH, $md5_key, $data, MCRYPT_MODE_ECB);
		return $val;
	}

	# }
	##############
}

class CTarRestore extends CTar
{
	function readHeader($Long = false)
	{
		$header = parent::readHeader($Long);
		if (!$Long && is_array($header))
		{
			$dr = str_replace(array('/','\\'),'',$_SERVER['DOCUMENT_ROOT']);
			$f = str_replace(array('/','\\'),'',$this->path.'/'.$header['filename']);

			if ($header['type'] != 5 && (self::strpos($f, $dr.'bitrixmodules') === 0 || self::strpos($f, $dr.'bitrixcomponentsbitrix') === 0))
			{
				if (!file_exists(RESTORE_FILE_LIST))
				{
					self::xmkdir($_SERVER['DOCUMENT_ROOT'].'/bitrix/tmp');
					file_put_contents(RESTORE_FILE_LIST, '<'.'?php die(); ?'.">\n");
				}
				file_put_contents(RESTORE_FILE_LIST, addslashes(self::substr(str_replace('\\','/',$header['filename']), 7))."\n", 8); // strlen(bitrix/) = 7
			}

			if ($f == $dr.'restore.php')
				return true;
			elseif ($f == $dr.'.htaccess')
			{
				$header['filename'] .= '.restore';
				$this->header['filename'] = $header['filename'];
			}
			elseif ($f == $dr.'bitrixphp_interfacedbconn.php' && file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/dbconn.php'))
				$header['filename'] = str_replace('dbconn.php','dbconn.restore.php',$header['filename']);
			elseif (preg_match('#[^\x00-\x7f]#', $header['filename'])) // non ASCII character detected
			{
				$this->header['filename'] = $header['filename'] = $this->DecodeFileName($header['filename']);
				if ($this->header['filename'] === false)
					return false;
			}
		}
		return $header;
	}

	function DecodeFileName($str)
	{
		if (!$this->EncCurrent)
		{
			if (PHP_EOL == "\r\n") // win
			{
				if (preg_match('#\.([0-9]+)$#', setlocale(LC_CTYPE, 0), $regs))
					$this->EncCurrent = 'cp'.$regs[1];
				else
					$this->EncCurrent = 'cp1251';
			}
			else
				$this->EncCurrent = 'utf-8';

			if (preg_match("/[\xC0-\xDF][\x80-\xBF]{1}|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF7][\x80-\xBF]{3}/", $str)) // 110xxxxx 10xxxxxx | 1110xxxx 10xxxxxx 10xxxxxx | 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
				$this->EncRemote = 'utf-8';
			elseif (preg_match("/[\xC0-\xFF]/",$str))
				$this->EncRemote = 'cp1251';
			else
				return $this->Error(getMsg('ERR_CANT_DETECT_ENC').' /'.$str);
		}

		if ($this->EncCurrent == $this->EncRemote)
			return $str;
		if (!function_exists('mb_convert_encoding'))
			return $this->Error(getMsg('ERR_CANT_DECODE'));
		return mb_convert_encoding($str, $this->EncCurrent, $this->EncRemote);
	}
}

function haveTime()
{
	return microtime(true) - START_EXEC_TIME < STEP_TIME;
}

function img($name)
{
	if (file_exists($_SERVER['DOCUMENT_ROOT'].'/images/'.$name))
		return '/images/'.$name;
	return 'https://www.1c-bitrix.ru/images/bitrix_setup/'.$name;
}

function bx_accelerator_reset()
{
	if(function_exists("accelerator_reset"))
		accelerator_reset();
	elseif(function_exists("wincache_refresh_if_changed"))
		wincache_refresh_if_changed();
}

function DeleteDirRec($path)
{
	if (file_exists($path) && $dir = opendir($path))
	{
		while(($item = readdir($dir)) !== false)
		{
			if ($item == '.' || $item == '..')
				continue;

			if (is_file($f = $path.'/'.$item))
			{
				if (!bx_unlink($f))
					return false;
			}
			else
			{
				if (!DeleteDirRec($f))
					return false;
			}
		}
		closedir($dir);
		if (!rmdir($path))
			return false;
	}
	return true;
	return true;
}

function CheckHtaccessAndWarn()
{
	$tmp = $_SERVER['DOCUMENT_ROOT'].'/.htaccess';
	$tmp1 = $tmp.'.restore';
	if (!file_exists($tmp1))
		return '';

	if (file_exists($tmp))
	{
		if (trim(file_get_contents($tmp)) == trim(file_get_contents($tmp1)))
		{
			bx_unlink($tmp1);
			return '';
		}
		else
			return '<li>'.getMsg('HTACCESS_RENAMED_WARN');
	}
	else
	{
		if (file_put_contents($tmp,
'Options -Indexes 
ErrorDocument 404 /404.php

<IfModule mod_php5.c>
	php_flag allow_call_time_pass_reference 1
	php_flag session.use_trans_sid off

	#php_value display_errors 1

	#php_value mbstring.internal_encoding UTF-8
</IfModule>

<IfModule mod_rewrite.c>
	Options +FollowSymLinks
	RewriteEngine On
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-l
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !/bitrix/urlrewrite.php$
	RewriteRule ^(.*)$ /bitrix/urlrewrite.php [L]
	RewriteRule .* - [E=REMOTE_USER:%{HTTP:Authorization}]
</IfModule>

<IfModule mod_dir.c>
	DirectoryIndex index.php index.html
</IfModule>

<IfModule mod_expires.c>
	ExpiresActive on
	ExpiresByType image/jpeg "access plus 3 day"
	ExpiresByType image/gif "access plus 3 day"
</IfModule>'))
			return '<li>'.getMsg('HTACCESS_WARN');
		else
			return '<li>'.getMsg('HTACCESS_ERR_WARN');
	}
}

function GetHidden($ar)
{
	$str = '';
	foreach($ar as $k)
	{
		if (isset($_REQUEST[$k]) && is_array($_REQUEST[$k]))
		{
			foreach($_REQUEST[$k] as $k0 => $v)
				$str .= '<input type=hidden name="'.$k.'['.htmlspecialcharsbx($k0).']" value="'.htmlspecialcharsbx($_REQUEST[$k][$k0]).'">';
		}
		else
			$str .= '<input type=hidden name="'.$k.'" value="'.htmlspecialcharsbx($_REQUEST[$k] ?? '').'">';
	}
	return $str;
}

class CDirScan
{
	public $DirCount = 0;
	public $FileCount = 0;
	public $err= array();
	public $bFound = false;
	public $nextPath = '';
	public $startPath = '';
	public $arIncludeDir = false;

	public function __construct()
	{
	}

	function ProcessDirBefore($f)
	{
		return true;
	}

	function ProcessDirAfter($f)
	{
		return true;
	}

	function ProcessFile($f)
	{
		return true;
	}

	function Skip($f)
	{
		if ($this->startPath)
		{
			if (mb_strpos($this->startPath.'/',$f.'/') === 0)
			{
				if ($this->startPath == $f)
					unset($this->startPath);
				return false;
			}
			else
				return true;
		}
		return false;
	}

	function Scan($dir)
	{
		$dir = str_replace('\\','/',$dir);

		if ($this->Skip($dir))
			return;

		$this->nextPath = $dir;

		if (is_dir($dir))
		{
		#############################
		# DIR
		#############################
			if (!$this->startPath)
			{
				$r = $this->ProcessDirBefore($dir);
				if ($r === false)
					return false;
			}

			if (!($handle = opendir($dir)))
			{
				$this->err[] = 'Error opening dir: '.$dir;
				return false;
			}

			while (($item = readdir($handle)) !== false)
			{
				if ($item == '.' || $item == '..' || false !== CTar::strpos($item,'\\'))
					continue;

				$f = $dir."/".$item;
				$r = $this->Scan($f);
				if ($r === false || $r === 'BREAK')
				{
					closedir($handle);
					return $r;
				}
			}
			closedir($handle);

			if (!$this->startPath)
			{
				if ($this->ProcessDirAfter($dir) === false)
					return false;
				$this->DirCount++;
			}
		}
		else
		{
		#############################
		# FILE
		#############################
			$r = $this->ProcessFile($dir);
			if ($r === false)
				return false;
			elseif ($r === 'BREAK')
				return $r;
			$this->FileCount++;
		}
		return true;
	}
}

class CDirRealScan extends CDirScan
{
	protected $cut;

	function Scan($dir)
	{
		if (!$this->cut)
			$this->cut = CTar::strlen($_SERVER['DOCUMENT_ROOT'].'/bitrix/');
		return parent::Scan($dir);
	}

	function ProcessFile($f)
	{
		if (!haveTime())
			return 'BREAK';
		global $a;
		if (!$a)
			return;
		$k = CTar::substr($f, $this->cut);
		if (!isset($a[$k]))
		{
			$to = RESTORE_FILE_DIR.'/'.$k;
			CTar::xmkdir(dirname($to));
			rename($f, $to);
		}
		return true;
	}
}

class CMultiGet
{
	static $error = '';
	static $free_connections = [];
	static $connections = [];
	static $current;
	static $bytes = 0;

	static function getConnection($connect_string)
	{
		$new_connection = null;
		foreach(self::$free_connections as $key => $connection)
		{
			if ($connection['connect_string'] == $connect_string)
			{
				if (!self::isAlive($connection['socket'])) // сервер закрыл соединение
				{
					unset(self::$free_connections[$key]);
					continue;
				}

				$new_connection = self::$free_connections[$key];
				unset(self::$free_connections[$key]);
				break;
			}
		}

		if (!$new_connection)
		{
			if ($sock = stream_socket_client($connect_string, $errno, $errstr, 5))
			{
				stream_set_blocking($sock, false);
			}
			else
			{
				self::$error = 'Can\'t connect to '.$connect_string.' ['.$errno.'] '.$errstr;
				return false;
			}

			$new_connection = [
				'connect_string' => $connect_string,
				'socket' => $sock,
				'code' => 0,
				'length' => 0,
				'saved_length' => 0,
				'microtime' => microtime(1),
			];
		}
		return $new_connection;
	}

	static function startLoad($url, $file)
	{
		$u = parse_url($url);
		if (!isset($u['port']))
			$u['port'] = $u['scheme'] == 'https' ? 443 : 80;
		$connect_string = ($u['scheme'] == 'https' ? 'ssl://' : 'tcp://').$u['host'].':'.$u['port'];

		if (!$connection = self::getConnection($connect_string))
			return false;

		$connection['state'] = 0;
		$connection['url'] = $url;
		$connection['file'] = $file;

		$strReq =
			'GET '.$u['path'].(isset($u['query']) ? '?'.$u['query'] : '').' HTTP/1.0'."\r\n".
			'Connection: keep-alive'."\r\n".
			'Host: '.$u['host']."\r\n";
		if (file_exists($file))
			$strReq .= 'Range: bytes='.filesize($file).'-'."\r\n";
		$strReq .= "\r\n";

		if (!fwrite($connection['socket'], $strReq))
		{
			self::$error = 'Can\'t write to '.$connect_string;
			return false;
		}

		self::$connections[] = $connection;
		end(self::$connections);
		debug('Connection #'.key(self::$connections).' request'."\n".$strReq);
		return true;
	}

	static function getPart()
	{
		if (!count(self::$connections))
			return true;

		$arReadSock = [];
		foreach(self::$connections as $key => $connection)
		{
			if (self::isAlive($connection['socket']))
				$arReadSock[] = $connection['socket'];
		}

		$n = stream_select($arReadSock, $w = null, $e = null, 1);
		if ($n === 0)
			return true;

		foreach($arReadSock as $sock)
		{
			$key = null;
			foreach(self::$connections as $key => $connection)
				if ($connection['socket'] == $sock)
					break;
			self::$current = $key;

			$file = self::$connections[$key]['file'];
			$state =& self::$connections[$key]['state'];
			$header = '';

			if ($state == 0) // начало загрузки, читаем заголовки
			{
				if (false === $line = fgets($sock))
					continue;

				if (!preg_match('#^HTTP/1.. ([0-9]+)#', trim($line), $regs))
				{
					self::$error = 'Invalid reply header: '.trim($line)."\n".'File: '.$file;
					return false;
				}

				$header .= $line;
				$state = $regs[1];

				if ($state == 416) // Requested Range Not Satisfiable
				{
					// файл загружен, ничего не делаем
				}
				elseif (!preg_match('#^2\d{2}$#', $state))
				{
					self::$error = 'Wrong reply code: '.trim($line)."\n".'File: '.$file;
					return false;
				}

				do
				{
					$line = fgets($sock);
					if (preg_match('#Content-length: (\d+)#i', $line, $regs))
					{
						self::$connections[$key]['length'] = $regs[1];
					}
					$header .= $line;
				} while ($line != "\r\n");
				debug('Connection #'.$key.' response'."\n".$header);
			}

			if (feof($sock) || $state == 416)
			{
				self::freeConnection($key);
			}
			else
			{
				$str = fread($sock, 1024 * 1024);
				$dir = dirname($file);
				if (!file_exists($dir))
					mkdir($dir, 0777, true);
				$bytes = file_put_contents($file, $str, 8);
				if ($bytes === false)
				{
					self::$error = getMsg('ERROR_CANT_WRITE', ['#FILE#' => $file, '#SPACE#' => freeSpace()]);
					return false;
				}
				self::$bytes += $bytes;
				self::$connections[$key]['saved_length'] += $bytes;

				if (self::$connections[$key]['saved_length'] >= self::$connections[$key]['length'])
				{
					self::freeConnection($key);
				}
			}
		}
		return true;
	}

	static function isAlive($sock)
	{
		return is_resource($sock) && get_resource_type($sock) == 'stream';
	}

	static function freeConnection($key)
	{
		$sock = self::$connections[$key]['socket'];
		if (self::isAlive($sock))
		{
			self::$free_connections[] = [
				'connect_string' => self::$connections[$key]['connect_string'],
				'microtime' => self::$connections[$key]['microtime'],
				'socket' => $sock,
			];
			unset(self::$connections[$key]);
		}
	}

	static function dropConnection($key)
	{
		$sock = self::$connections[$key]['socket'];
		if (self::isAlive($sock))
			fclose($sock);
		unset(self::$connections[$key]);
	}
}

function bx_unlink($file)
{
	if (!file_exists($file))
		return true;
	if (DEBUG)
		return rename($file, $file.'.debug');
	return unlink($file);
}

function debug($str)
{
	if (!DEBUG)
		return;
	if (is_array($str))
		$str = print_r($str, 1);
	file_put_contents($_SERVER['DOCUMENT_ROOT'].'/log.txt', date('[Y-m-d H:i:s]	').$str."\n", 8);
}

function freeSpace()
{
	$d = disk_free_space(__FILE__);
	if ($d === false)
		return 'N/A';
	return HumanSize($d);
}

function HumanSize($s)
{
	$i = 0;
	$ar = array('b','kb','Mb','Gb');
	while($s > 1024)
	{
		$s /= 1024;
		$i++;
	}
	return round($s,2).''.$ar[$i];
}
function version_compare_bx($a, $b)
{
	$a = trim($a);
	$b = trim($b);

	if ($a == $b)
		return 0;

	$arA = explode(".", $a);
	$arB = explode(".", $b);

	if (intval($arA[0]) > intval($arB[0])
		|| intval($arA[0]) == intval($arB[0]) && intval($arA[1]) > intval($arB[1])
		|| intval($arA[0]) == intval($arB[0]) && intval($arA[1]) == intval($arB[1]) && intval($arA[2]) > intval($arB[2]))
	{
		return 1;
	}

	if (intval($arA[0]) == intval($arB[0]) && intval($arA[1]) == intval($arB[1]) && intval($arA[2])==intval($arB[2]))
	{
		return 0;
	}

	return -1;
}
