<?php
########## Proxy config ######
$proxyAddr = "";
$proxyPort = "";
$proxyUserName = "";
$proxyPassword = "";
##############################

header("Content-type: text/html; charset=utf-8");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Expires: 0");
header("Pragma: public");

error_reporting(E_ALL & ~E_NOTICE);
if(version_compare(phpversion(), '7.3.0') == -1)
	die('PHP 7.3.0 or higher is required!');

if(!function_exists('gzopen'))
	die('zlib module is not installed!');

ob_implicit_flush(true);
set_time_limit(1800);
define('TIMEOUT',10);

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
define('UPDATE_FLAG', dirname(__FILE__).'/bitrixsetup.update');

$this_script_name = basename(__FILE__);

umask(0);
if (file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/dbconn.php'))
	include($file);

$debug = file_exists(dirname(__FILE__).'/bitrixsetup.debug');

if (!defined("BX_DIR_PERMISSIONS"))
	define("BX_DIR_PERMISSIONS", 0755);

if (!defined("BX_FILE_PERMISSIONS"))
	define("BX_FILE_PERMISSIONS", 0644);

$strAction = $_REQUEST["action"] ?? '';
$edition = $_REQUEST['edition'] ?? 0;

if ($short = (getenv('BITRIX_ENV_TYPE') == 'crm'))
{
	$arEditions = array(
		'ru' => array(
			array(
				'NAME' => '1С-Битрикс24',
				'LIST' => array(
					'portal/bitrix24' => 'Корпоративный портал',
					'portal/bitrix24_enterprise' => 'Энтерпрайз',
					'portal/bitrix24_crm' => 'Битрикс24.CRM'

				)
			),
		),
		'en' => array(
			array(
				'NAME' => 'Bitrix24',
				'LIST' => array(
					'portal/en_bitrix24_crm' => "Bitrix24.CRM",
					'portal/en_bitrix24' => 'Business',
					'portal/en_bitrix24_enterprise' => 'Enterprise'
				),
			)
		),
		'de' => array(
			array(
				'NAME' => 'Bitrix24',
				'LIST' => array(
					'de/de_bitrix24_crm' => "Bitrix24.CRM",
					'de/de_bitrix24' => 'Business',
					'de/de_bitrix24_enterprise' => 'Enterprise'
				)
			)
		)
	);

	$edition = 0;
}
else
{
	$arEditions = array(
		'ru' => array(
			array(
				'NAME' => '1С-Битрикс: Управление сайтом',
				'LIST' => array(
					"business"=>"Бизнес",
					"small_business"=>"Малый бизнес",
					"standard"=>"Стандарт",
					"start"=>"Старт",
				),
			),
			array(
				'NAME' => '1С-Битрикс24',
				'LIST' => array(
					'portal/bitrix24_shop' => 'Интернет-магазин плюс CRM',
					'portal/bitrix24' => 'Корпоративный портал',
					'portal/bitrix24_enterprise' => 'Энтерпрайз',
				)
			),
			array(
				'NAME' => 'Отраслевые решения 1С-Битрикс',
				'LIST' => array(
					'edu/eduportal' => 'Внутренний портал учебного заведения',
				),
			),
		),
		'en' => array(
			array(
				'NAME' => 'Bitrix24',
				'LIST' => array(
					'portal/en_bitrix24' => 'Business',
					'portal/en_bitrix24_enterprise' => 'Enterprise'
				),
			)
		),
		'de' => array(
			array(
				'NAME' => 'Bitrix24',
				'LIST' => array(
					'de/de_bitrix24' => 'Business',
					'de/de_bitrix24_enterprise' => 'Enterprise'
				),
			)
		)
	);
}
$single = count($arEditions[LANG]) == 1;
####### MESSAGES ########
$MESS = array();
$ar = array();
if (LANG == "ru")
{
	$MESS["NO_PERMS"] = "Нет прав на запись в файл ";
	$MESS["LOADER_LICENSE_KEY"] = "Лицензионный ключ";
	$MESS["INTRANET"] = "Корпоративный портал";
	$MESS["LOADER_TITLE"] = "Загрузка продуктов \"1С-Битрикс\"";
	$MESS["UPDATE_SUCCESS"] = "Обновлено успешно. <a href='?'>Открыть</a>.";
	$MESS["LOADER_NEW_STEPS"] = "Загрузка продуктов \"1С-Битрикс\"";
	$MESS["LOADER_SUBTITLE1"] = "Загрузка продукта";
	$MESS["LOADER_SUBTITLE2"] = "1С-Битрикс";
	$MESS["LOADER_MENU_LIST"] = "Выбор продукта";
	$MESS["LOADER_MENU_LOAD"] = "Загрузка дистрибутива с сервера";
	$MESS["LOADER_MENU_UNPACK"] = "Распаковка дистрибутива";
	$MESS["LOADER_TECHSUPPORT"] = "";
	$MESS["LOADER_TITLE_LIST"] = "Выбор дистрибутива";
	$MESS["LOADER_TITLE_LOAD"] = "Загрузка дистрибутива на сайт";
	$MESS["LOADER_TITLE_UNPACK"] = "Распаковка дистрибутива";
	$MESS["LOADER_TITLE_LOG"] = "Отчет по загрузке";
	$MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Внимание!</b></font> PHP на вашем сайте работает в Safe Mode. Установка продукта в автоматическом режиме невозможна. Пожалуйста, обратитесь в службу технической поддержки для получения дополнительной информации.";
	$MESS["LOADER_NO_PERMS_ERR"] = "<font color=\"#FF0000\"><b>Внимание!</b></font> PHP не имеет прав на запись в корневую папку #DIR# вашего сайта. Загрузка продукта может оказаться невозможной. Пожалуйста, установите необходимые права на корневую папку вашего сайта или обратитесь к администраторам вашего хостинга.";
	$MESS["LOADER_EXISTS_ERR"] = "";
	$MESS["LOADER_IS_DISTR"] = "На сайте найдены загруженые дистрибутивы. Нажмите на название любого из дистрибутивов для его распаковки:";
	$MESS["LOADER_OVERWRITE"] = "<b>Внимание!</b> Существующие на сайте файлы могут быть перезаписаны файлами из дистрибутива.";
	$MESS["LOADER_IS_DISTR_PART"] = "На сайте найдены недогруженные дистрибутивы. Нажмите на название любого из недогруженных дистрибутивов для полной загрузки:";
	$MESS["LOADER_NEW_LOAD_TITLE"] = "Загрузка дистрибутива с сайта <a href=\"https://www.1c-bitrix.ru\" target=\"_blank\">https://www.1c-bitrix.ru</a>";
	$MESS["LOADER_NEW_ED"] = "Выбор дистрибутива";
	$MESS["LOADER_NEW_VERSION"] = "Доступна новая версия скрипта установки, но загрузить её не удалось";
	$MESS["LOADER_NEW_AUTO"] = "Автоматически запустить распаковку после загрузки";
	$MESS["LOADER_NEW_STEPS"] = "Загружать по шагам с шагом";
	$MESS["LOADER_NEW_STEPS0"] = "неограниченно долгим";
	$MESS["LOADER_NEW_STEPS30"] = "не более 30 секунд";
	$MESS["LOADER_NEW_STEPS60"] = "не более 60 секунд";
	$MESS["LOADER_NEW_STEPS120"] = "не более 120 секунд";
	$MESS["LOADER_NEW_STEPS180"] = "не более 180 секунд";
	$MESS["LOADER_NEW_STEPS240"] = "не более 240 секунд";
	$MESS["LOADER_NEW_LOAD"] = "Загрузить";
	$MESS["LOADER_DESCR"] = "Этот скрипт предназначен для загрузки дистрибутивов \"1С-Битрикс\" с сайта <a href=\"https://www.1c-bitrix.ru/download/index.php\" target=\"_blank\">www.1c-bitrix.ru</a> непосредственно на ваш сайт, а так же для распаковки дистрибутива на вашем сайте.<br><br> Загрузите этот скрипт в корневую папку вашего сайта и откройте его в браузере (введите в адресной строке браузера <nobr>http://&lt;ваш сайт&gt;/".$this_script_name."</nobr>).";
	$MESS["LOADER_BACK_2LIST"] = "Вернуться в список дистрибутивов";
	$MESS["LOADER_BACK"] = "Назад";
	$MESS["LOADER_LOG_ERRORS"] = "Произошли следующие ошибки:";
	$MESS["LOADER_NO_LOG"] = "Log-файл не найден";
	$MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Внимание!</font></b> По окончании установки продукта <b>обязательно</b> удалите скрипт <nobr>/".$this_script_name."</nobr> с вашего сайта. Доступ постороннего человека к этому скрипту может повлечь за собой нарушение работы вашего сайта.";
	$MESS["LOADER_KB"] = "кб";
	$MESS["LOADER_LOAD_QUERY_SERVER"] = "Запрашиваю сервер...";
	$MESS["LOADER_LOAD_QUERY_DISTR"] = "Запрашиваю дистрибутив #DISTR#";
	$MESS["LOADER_LOAD_CONN2HOST"] = "Открываю соединение к #HOST#...";
	$MESS["LOADER_LOAD_NO_CONN2HOST"] = "Не могу соединиться с #HOST#:";
	$MESS["LOADER_LOAD_QUERY_FILE"] = "Запрашиваю файл...";
	$MESS["LOADER_LOAD_WAIT"] = "Ожидаю ответ...";
	$MESS["LOADER_LOAD_SERVER_ANSWER"] = "Ошибка загрузки. Ответа сервера:<br><br> #ANS#";
	$MESS["LOADER_LOAD_SERVER_ANSWER1"] = "Ответ сервера: 403 Доступ запрещён.<br>Проверьте правильность ввода ключа.";
	$MESS["LOADER_LOAD_NEED_RELOAD"] = "Докачка дистрибутива невозможна. Начинаю качать заново.";
	$MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Не могу открыть файл #FILE# на запись";
	$MESS["LOADER_LOAD_LOAD_DISTR"] = "Загружаю дистрибутив #DISTR#";
	$MESS["LOADER_LOAD_ERR_SIZE"] = "Ошибка размера файла";
	$MESS["LOADER_LOAD_ERR_RENAME"] = "Не могу переименовать файл #FILE1# в файл #FILE2#";
	$MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Не могу открыть файл #FILE# на запись";
	$MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Не могу открыть файл #FILE# на чтение";
	$MESS["LOADER_LOAD_LOADING"] = "Загружаю файл... дождитесь окончания загрузки...";
	$MESS["LOADER_LOAD_FILE_SAVED"] = "Файл сохранен: #FILE# [#SIZE# байт]";
	$MESS["LOADER_UNPACK_ACTION"] = "Распаковываю дистрибутив... дождитесь окончания распаковки...";
	$MESS["LOADER_UNPACK_UNKNOWN"] = "Неизвестная ошибка. Повторите процесс еще раз или обратитесь в службу технической поддержки";
	$MESS["LOADER_UNPACK_DELETE"] = "Ошибка удаления временных файлов. Удалите файлы вручную.";
	$MESS["LOADER_UNPACK_SUCCESS"] = "Дистрибутив успешно распакован";
	$MESS["LOADER_UNPACK_ERRORS"] = "Дистрибутив распакован с ошибками";
	$MESS["LOADER_KEY_DEMO"] = "Демонстрационная версия";
	$MESS["LOADER_KEY_COMM"] = "Коммерческая версия";
	$MESS["LOADER_KEY_TITLE"] = "Введите лицензионный ключ";
	$MESS["LOADER_NOT_EMPTY"] = "В текущей папке уже есть установленная версия продукта, установка новой версии возможна только в чистую корневую папку веб-сервера.";
}
elseif (LANG == "de")
{
	$MESS["NO_PERMS"] = "Nicht genunugend Rechte um die Datei zu beschreiben";
	$MESS["LOADER_LICENSE_KEY"] = "Lizenzschlussel";
	$MESS["INTRANET"] = "Intranet Portal ";
	$MESS["LOADER_TITLE"] = "Download der \"Bitrix24\" Software";
	$MESS["UPDATE_SUCCESS"] = "Aktualisierung erfolgreich durchgefuhrt. <a href='?'>Offnen?</a>.";
	$MESS["LOADER_NEW_STEPS"] = "Download der \"Bitrix24\" Software";
	$MESS["LOADER_SUBTITLE1"] = "Softwaredownload";
	$MESS["LOADER_SUBTITLE2"] = "Bitrix24";
	$MESS["LOADER_MENU_LIST"] = "Auswahl des Installationspacks";
	$MESS["LOADER_MENU_LOAD"] = "Download des Installationspacks von dem Server";
	$MESS["LOADER_MENU_UNPACK"] = "Auspacken des Installationspacks";
	$MESS["LOADER_TECHSUPPORT"] = "";
	$MESS["LOADER_TITLE_LIST"] = "Auswahl des Installationspacks";
	$MESS["LOADER_TITLE_LOAD"] = "Upload des Installationspacks auf die Website";
	$MESS["LOADER_TITLE_UNPACK"] = "Auspacken des Installationspacks";
	$MESS["LOADER_TITLE_LOG"] = "Uploadbericht";
	$MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Achtung!</b></font> PHP auf Ihrer Website arbeitet im Safe Mode. Die automatische Installation der Software ist nicht moglich. Bitte wenden Sie sich fur weitere Informationen an den technischen Support.";
	$MESS["LOADER_NO_PERMS_ERR"] = "<font
color=\"#FF0000\"><b>Achtung!</b></font> PHP hat nicht genugend Rechte um das Hautverzeichnis #DIR# Ihrer Website zu uberschreiben. Es konnen Fehler bei der Installation auftreten. Bitte stellen Sie alle erforderlichen Rechte ein, oder wenden Sie sich an Ihren Hosting-Anbieter.";
	$MESS["LOADER_EXISTS_ERR"] = "";
	$MESS["LOADER_IS_DISTR"] = "Hochgeladene Installationspacks wurden gefunden. Klicken Sie auf den Namen des erforderlichen Installationspacks um mit dem Auspacken zu beginnen:";
	$MESS["LOADER_OVERWRITE"] = "<b>Achtung!</b> Die existierenden Dateien konnen durch die Dateien aus dem Installationspack uberschrieben werden.";
	$MESS["LOADER_IS_DISTR_PART"] = "Auf der Website wurden nicht vollstandig geladenen Installationspacks hochgeladen. Klicken Sie auf den Namen des erforderlichen Installationspacks um mit Upload fortzufuhren:";
	$MESS["LOADER_NEW_LOAD_TITLE"] = "Download des Installationspacks von der Site <a href=\"https://www.bitrix.de\" target=\"_blank\">https://www.bitrix.de</a>";
	$MESS["LOADER_NEW_ED"] = "Auswahl des Installationspacks";
	$MESS["LOADER_NEW_VERSION"] = "Neue Version des Installationsskripts ins verfugbar!";
	$MESS["LOADER_NEW_AUTO"] = "Auspacken automatisch nach dem Upload starten";
	$MESS["LOADER_NEW_STEPS"] = "Hochladen in folgenden Schritten";
	$MESS["LOADER_NEW_STEPS0"] = "uneingeschrankt ";
	$MESS["LOADER_NEW_STEPS30"] = "max. 30 Sekunden";
	$MESS["LOADER_NEW_STEPS60"] = " max. 60 Sekunden";
	$MESS["LOADER_NEW_STEPS120"] = "max. 120 Sekunden";
	$MESS["LOADER_NEW_STEPS180"] = "max. 180 Sekunden";
	$MESS["LOADER_NEW_STEPS240"] = "max. 240 Sekunden";
	$MESS["LOADER_NEW_LOAD"] = "Hochladen";
	$MESS["LOADER_DESCR"] = "Dieses Skript ist dient dem Download der \"Bitrix24\"-Installationspacks von der Website <a
href=\"https://www.bitrix24.de/prices/self-hosted.php\" target=\"_blank\">www.bitrix24.de</a> direkt auf Ihre Website, sowie dem Auspacken des Installationspacks auf Ihrer Website.<br><br> 
Laden Sie dieses Skript in das Hauptverzeichnis, und offnen Sie es in Ihrem Internet-Browser. Geben Sie dafur in der Adresszeile <nobr>https://&amp;lt;IhreWebsite&amp;gt;/".$this_script_name."</nobr>).";
	$MESS["LOADER_BACK_2LIST"] = "Zur der Installationspack-Liste zuruckkehren";
	$MESS["LOADER_BACK"] = "Zurück";
	$MESS["LOADER_LOG_ERRORS"] = "Folgende Fehler sind aufgetreten:";
	$MESS["LOADER_NO_LOG"] = "Log-Datei nicht gefunden";
	$MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Achtung!</font></b> Nach der Installation des Produkts loschen Sie <b>unbedingt</b> das Skript<nobr>/".$this_script_name."</nobr> von Ihrer Website. Der Fremdzugriff zu diesem Skript kann fehlerhafte Funktion Ihrer Website mit sich fuhren.";
	$MESS["LOADER_KB"] = "kb";
	$MESS["LOADER_LOAD_QUERY_SERVER"] = "Anfrage an den Server...";
	$MESS["LOADER_LOAD_QUERY_DISTR"] = "Anfrage fur das Installationspack #DISTR#";
	$MESS["LOADER_LOAD_CONN2HOST"] = "Aufbau der Verbindung mit #HOST#...";
	$MESS["LOADER_LOAD_NO_CONN2HOST"] = "Keine Verbindung mit #HOST#:";
	$MESS["LOADER_LOAD_QUERY_FILE"] = "Anfrage fur die Datei...";
	$MESS["LOADER_LOAD_WAIT"] = "Abwarten der Ruckmeldung...";
	$MESS["LOADER_LOAD_SERVER_ANSWER"] = "Upload-Fehler. Serverruckmeldung:<br><br> #ANS#";
	$MESS["LOADER_LOAD_SERVER_ANSWER1"] = " Serverruckmeldung: 403 Zugriff verweigert.<br>Uberprufen Sie die Richtigkeit des Codes.";
	$MESS["LOADER_LOAD_NEED_RELOAD"] = "Fortfuhrung des Downloadvorgangs nicht moglich. Der Downloadvorgang wird erneut ausgefuhrt.";
	$MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Die #FILE# kann nicht bearbeitet werden";
	$MESS["LOADER_LOAD_LOAD_DISTR"] = "Installationspack #DISTR# wird hochgeladen";
	$MESS["LOADER_LOAD_ERR_SIZE"] = "Fehler bei der Dateigro?e";
	$MESS["LOADER_LOAD_ERR_RENAME"] = "Die Datei #FILE1# kann nicht in #FILE2# umbenannt warden";
	$MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Die Datei #FILE# kann nicht bearbeitet werden";
	$MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Die Datei #FILE# kann nicht gelesen werden";
	$MESS["LOADER_LOAD_LOADING"] = "Upload-Vorgang lauft... Bitte warten Sie bis der Vorgang beendet wird.";
	$MESS["LOADER_LOAD_FILE_SAVED"] = "Datei gespeichert: #FILE# [#SIZE# Byte]";
	$MESS["LOADER_UNPACK_ACTION"] = " Das Installationspack wird ausgepackt... Bitte warten Sie bis der Vorgang beendet wird.";
	$MESS["LOADER_UNPACK_UNKNOWN"] = "Unbekannter Fehler. Fuhren Sie den Vorgang nochmal aus, oder wenden Sie sich an den technischen Support";
	$MESS["LOADER_UNPACK_DELETE"] = "Fehler beim Loschen der temporaren Dateien. Loschen Sie bitte die Dateien manuell.";
	$MESS["LOADER_UNPACK_SUCCESS"] = "Das Installationspack wurde erfolgreich ausgepackt";
	$MESS["LOADER_UNPACK_ERRORS"] = " Das Installationspack wurde mit Fehlern ausgepackt ";
	$MESS["LOADER_KEY_DEMO"] = "Testversion";
	$MESS["LOADER_KEY_COMM"] = "Vollversion";
	$MESS["LOADER_KEY_TITLE"] = "Lizenzcode eingeben";
	$MESS["LOADER_NOT_EMPTY"] = "Es existiert bereits eine Produktversion im aktuellen Ordner. Eine neue Version kann nur in einem leeren Root-Verzeichnis des Webservers installiert werden.";
}
else
{
	$MESS["NO_PERMS"] = "No permissions to write the file ";
	$MESS["LOADER_LICENSE_KEY"] = "Your license key";
	$MESS["INTRANET"] = "Bitrix Intranet Portal";
	$MESS["LOADER_TITLE"] = "Loading Product \"Bitrix24\"";
	$MESS["UPDATE_SUCCESS"] = "Successful update. <a href='?'>Open</a>.";
	$MESS["LOADER_SUBTITLE1"] = "Loading";
	$MESS["LOADER_SUBTITLE2"] = "Bitrix24";
	$MESS["LOADER_MENU_LIST"] = "Choose a package";
	$MESS["LOADER_MENU_LOAD"] = "Download installation package from server";
	$MESS["LOADER_MENU_UNPACK"] = "Unpacking the Installation Package";
	$MESS["LOADER_TECHSUPPORT"] = "";
	$MESS["LOADER_TITLE_LIST"] = "Select installation package";
	$MESS["LOADER_TITLE_LOAD"] = "Uploading installation package to the site";
	$MESS["LOADER_TITLE_UNPACK"] = "Unpacking the Installation Package";
	$MESS["LOADER_TITLE_LOG"] = "Upload report";
	$MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Attention!</b></font> Your PHP functions in Safe Mode. The Setup cannot proceed in automatic mode. Please consult the technical support service for additional instructions.";
	$MESS["LOADER_NO_PERMS_ERR"] = "<font color=\"#FF0000\"><b>Attention!</b></font> PHP has not enough permissions to write to the root directory #DIR# of your site. Loading is likely to fail. Please set the required access permissions to the root directory of your site or consult administrators of your hosting service.";
	$MESS["LOADER_EXISTS_ERR"] = "";
	$MESS["LOADER_IS_DISTR"] = "Uploaded installation packages were found on the site. Click the name of any package to start installation:";
	$MESS["LOADER_OVERWRITE"] = "<b>Attention!</b> Files currently present on your site will possibly be overwritten with files from the package.";
	$MESS["LOADER_IS_DISTR_PART"] = "Incompletely uploaded installation packages were found on the site. Click the name of any package to finish loading:";
	$MESS["LOADER_NEW_LOAD_TITLE"] = "Download new installation package from <a href=\"https://www.bitrix24.com\" target=\"_blank\">https://www.bitrix24.com</a>";
	$MESS["LOADER_NEW_VERSION"] = "New version of bitrixsetup script is available!";
	$MESS["LOADER_NEW_ED"] = "Choose a package";
	$MESS["LOADER_NEW_AUTO"] = "automatically start unpacking after loading";
	$MESS["LOADER_NEW_STEPS"] = "load gradually with interval:";
	$MESS["LOADER_NEW_STEPS0"] = "unlimited";
	$MESS["LOADER_NEW_STEPS30"] = "less than 30 seconds";
	$MESS["LOADER_NEW_STEPS60"] = "less than 60 seconds";
	$MESS["LOADER_NEW_STEPS120"] = "less than 120 seconds";
	$MESS["LOADER_NEW_STEPS180"] = "less than 180 seconds";
	$MESS["LOADER_NEW_STEPS240"] = "less than 240 seconds";
	$MESS["LOADER_NEW_LOAD"] = "Download";
	$MESS["LOADER_DESCR"] = "";
	$MESS["LOADER_BACK_2LIST"] = "Back to packages list";
	$MESS["LOADER_BACK"] = "Back";
	$MESS["LOADER_LOG_ERRORS"] = "The following errors occured:";
	$MESS["LOADER_NO_LOG"] = "Log file not found";
	$MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Attention!</font></b> After you have finished installing, <b>please be sure</b> to delete the script <nobr>/".$this_script_name."</nobr> from your site. Otherwise, unauthorized persons may access this script and damage your site.";
	$MESS["LOADER_KB"] = "kb";
	$MESS["LOADER_LOAD_QUERY_SERVER"] = "Connecting server...";
	$MESS["LOADER_LOAD_QUERY_DISTR"] = "Requesting package #DISTR#";
	$MESS["LOADER_LOAD_CONN2HOST"] = "Establishing connection to #HOST#...";
	$MESS["LOADER_LOAD_NO_CONN2HOST"] = "Cannot connect to #HOST#:";
	$MESS["LOADER_LOAD_QUERY_FILE"] = "Requesting file...";
	$MESS["LOADER_LOAD_WAIT"] = "Waiting for response...";
	$MESS["LOADER_LOAD_SERVER_ANSWER"] = "Error while downloading. Server reply was: #ANS#";
	$MESS["LOADER_LOAD_SERVER_ANSWER1"] = "Server reply: 403 Forbidden.<br>Please check your licence key.";
	$MESS["LOADER_LOAD_NEED_RELOAD"] = "Cannot resume download. Starting new download.";
	$MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Cannot open file #FILE# for writing";
	$MESS["LOADER_LOAD_LOAD_DISTR"] = "Downloading package #DISTR#";
	$MESS["LOADER_LOAD_ERR_SIZE"] = "File size error";
	$MESS["LOADER_LOAD_ERR_RENAME"] = "Cannot rename file #FILE1# to #FILE2#";
	$MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Cannot open file #FILE# for writing";
	$MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Cannot open file #FILE# for reading";
	$MESS["LOADER_LOAD_LOADING"] = "Download in progress. Please wait...";
	$MESS["LOADER_LOAD_FILE_SAVED"] = "File saved: #FILE# [#SIZE# bytes]";
	$MESS["LOADER_UNPACK_ACTION"] = "Unpacking the package. Please wait...";
	$MESS["LOADER_UNPACK_UNKNOWN"] = "Unknown error occured. Please try again or consult the technical support service";
	$MESS["LOADER_UNPACK_DELETE"] = "Errors occured while deleting temporary files";
	$MESS["LOADER_UNPACK_SUCCESS"] = "The installation package successfully unpacked";
	$MESS["LOADER_UNPACK_ERRORS"] = "Errors occured while unpacking the installation package";
	$MESS["LOADER_KEY_DEMO"] = "Demo version";
	$MESS["LOADER_KEY_COMM"] = "Commercial version";
	$MESS["LOADER_KEY_TITLE"] = "Specify license key";
	$MESS["LOADER_NOT_EMPTY"] = "An instance of the system already exists in the current folder. New version can only be installed to an empty folder in the web server root.";
}
####### /MESSAGES ########

function LoaderGetMessage($name)
{
	global $MESS;
	return $MESS[$name];
}


if(LANG == 'ru')
	$bx_host = 'www.1c-bitrix.ru';
elseif(LANG == 'de')
	$bx_host = 'www.bitrix.de';
else
	$bx_host = 'www.bitrixsoft.com';

$bx_url = '/download/files/scripts/'.$this_script_name;
$form = '';


$strError = '';
if (!$strAction)
{
	if (!$debug && file_exists($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main'))
		die(ShowError(LoaderGetMessage('LOADER_NOT_EMPTY')));
	$strAction = "LIST";

	// Check for updates
	if ((!file_exists(UPDATE_FLAG) || time() - filemtime(UPDATE_FLAG) > 3600) && !$debug && !$proxyAddr)
	{
		file_put_contents(UPDATE_FLAG, time());
		$res = @fsockopen('ssl://'.$bx_host, 443, $errno, $errstr, 3);

		if($res)
		{
			$strRequest = "HEAD ".$bx_url." HTTP/1.1\r\n";
			$strRequest.= "Host: ".$bx_host."\r\n";
			$strRequest.= "\r\n";

			fputs($res, $strRequest);

			while ($line = fgets($res, 4096))
			{
				if (@preg_match("/Content-Length: *([0-9]+)/i", $line, $regs))
				{
					if (filesize(__FILE__) != trim($regs[1]))
					{
						$tmp_name = $this_script_name.'.tmp';
						if (LoadFile('https://'.$bx_host.$bx_url, $tmp_name, 0))
						{
							if (rename($_SERVER['DOCUMENT_ROOT'].'/'.$tmp_name,__FILE__))
							{
								bx_accelerator_reset();
								echo '<script>document.location="?lang='.LANG.'";</script>'.LoaderGetMessage('UPDATE_SUCCESS');
								die();
							}
							else
								$strError = str_replace("#FILE#", $this_script_name, LoaderGetMessage("LOADER_LOAD_CANT_OPEN_WRITE"));
						}
						else
							$strError = LoaderGetMessage('LOADER_NEW_VERSION');
					}
					break;
				}
			}
			fclose($res);
		}
	}
}

if ($strAction=="UNPACK" && (!isset($_REQUEST["filename"]) || strlen($_REQUEST["filename"])<=0))
	$strAction = "LIST";


$script = '';
if ($strAction=="LIST")
{
	$txt = '';
	if ($strError)
		$txt = ShowError($strError);

	/*************************************************/
	if (ini_get("safe_mode") == "1")
		$txt .= LoaderGetMessage("LOADER_SAFE_MODE_ERR") . '<br>';

	if (!is_writable($_SERVER["DOCUMENT_ROOT"]))
		$txt .= str_replace("#DIR#", $_SERVER["DOCUMENT_ROOT"], LoaderGetMessage("LOADER_NO_PERMS_ERR")) . '<br>';

	if (file_exists($_SERVER["DOCUMENT_ROOT"]."/bitrix")
		&& is_dir($_SERVER["DOCUMENT_ROOT"]."/bitrix"))
		$txt .= LoaderGetMessage("LOADER_EXISTS_ERR") . '<br>';

	$arLocalDistribs = array();
	$arLocalDistribs_tmp = array();

	$handle = opendir($_SERVER["DOCUMENT_ROOT"]);
	if (isset($_REQUEST['test']) && $_REQUEST['test'] && $handle)
	{
		while (false !== ($ffile = readdir($handle)))
		{
			if (!is_file($_SERVER["DOCUMENT_ROOT"]."/".$ffile))
				continue;

			if (strtolower(substr($ffile, -7))==".tar.gz")
				$arLocalDistribs[] = $ffile;
			elseif (strtolower(substr($ffile, -11))==".tar.gz.tmp")
				$arLocalDistribs_tmp[] = $ffile;
			elseif (strtolower(substr($ffile, -11))==".tar.gz.log")
				$arLocalDistribs_tmp[] = $ffile;
		}
		closedir($handle);
	}

	if (count($arLocalDistribs)>0)
	{
		$txt .= LoaderGetMessage("LOADER_IS_DISTR").'<br>';
		for ($i = 0; $i < count($arLocalDistribs); $i++)
			$txt .= '<a href="'.$this_script_name.'?action=UNPACK&filename='.urlencode($arLocalDistribs[$i]).'&by_step=Y">'.$arLocalDistribs[$i].'</a><br><br>';
		$txt .= LoaderGetMessage("LOADER_OVERWRITE") . '<br><br>';
	}


	if (count($arLocalDistribs_tmp)>0 && $_REQUEST['action']=='LIST')
	{
		//		$txt .= '<br>'.LoaderGetMessage("LOADER_IS_DISTR_PART") . '<br>';
		foreach($arLocalDistribs_tmp as $distr)
			@unlink($_SERVER['DOCUMENT_ROOT'].'/'.$distr);
		//			$txt .= '<a href="'.$this_script_name.'?action=LOAD&url='.urlencode(substr($arLocalDistribs_tmp[$i], 0, strlen($arLocalDistribs_tmp[$i])-4)).'">'.$arLocalDistribs_tmp[$i].'</a><br>';
	}


	$txt .= '<script>
		function show_select(k)
		{
			document.getElementById("download_button").disabled = false;
	';
	foreach($arEditions[LANG] as $k => $ED)
		$txt .= 'if (ob = document.getElementById("select_'.$k.'"))
				ob.disabled = "disabled";
			';
	$txt .= '
			if (ob = document.getElementById("select_" + k))
				ob.disabled = "";
		}
		</script>
	';

	$form = '
	<form method="post" action="'.$this_script_name.'?action=LOAD" onsubmit="document.getElementById(\'download_button\').disabled=true">
	<input type=hidden name=lang value="'.LANG.'">
	';

	$txt .= '<table cellspacing=0 cellpadding=2 border=0>';
	$txt .= '<tr><td colspan=2>'.( $single ? '' : LoaderGetMessage("LOADER_NEW_ED").':').'</td></tr>';

	foreach($arEditions[LANG] as $k => $ED)
	{
		if ($single)
			$txt.=' 
			<tr><td style="padding-left:10px;font-weight:bold"><input type=hidden name=edition value=0>'.$ED['NAME'].'</td>';
		else
			$txt.=' 
			<tr><td style="padding-left:10px;width:46%;"><input name=edition type=radio value="'.$k.'" id="radio_'.$k.'" onclick="show_select('.$k.')"> <label for="radio_'.$k.'">'.$ED['NAME'].'</label></td>';

		$txt .= '<td>';
		if (is_array($ED['LIST']))
		{
			$txt .= '
				<select name="url" '.($single ? '' : 'disabled').' id="select_'.$k.'">';
			foreach ($ED['LIST'] as $key => $value)
				$txt .= '<option value="'.$key.'">'.$value.'</option>';
			$txt .= '
				</select>';
		}
		$txt .= '</td>';
	}


	$txt.='
		<tr>
			<td colspan=2 height=15></td>
		</tr>
		<tr>
			<td colspan=2 style="padding-left:10px; width: 46%"><input type="radio" name="licence_type" value="demo" checked id="licence_type_1" onclick="document.getElementById(\'license_key\').disabled=\'disabled\'"><label for="licence_type_1">'.LoaderGetMessage("LOADER_KEY_DEMO").'</label></td>
		</tr>
		<tr>
			<td colspan=2 style="padding-left:10px"><input type="radio" name="licence_type" value="src" id="licence_type_2" onclick="document.getElementById(\'license_key\').disabled=\'\'"><label for="licence_type_2">'.LoaderGetMessage("LOADER_KEY_COMM").'</label></td>
		</tr>
		<tr>
			<td style="padding-left:30px;">'.LoaderGetMessage("LOADER_LICENSE_KEY").': </td>
			<td><input type="text" disabled value="" id="license_key" name="LICENSE_KEY" size="40" title="'.LoaderGetMessage("LOADER_KEY_TITLE").'"></td>
		</tr>				
				
	</table>';

	$ar = array(
		'FORM' => $form,
		'TITLE' => LoaderGetMessage("LOADER_TITLE"),
		'HEAD' => LoaderGetMessage("LOADER_MENU_LIST"),
		'TEXT' => $txt,
		'TEXT_ALIGN' => 'top',
		'BOTTOM' => (file_exists($_SERVER['DOCUMENT_ROOT'].'/index.php')?'<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\'/index.php?lang='.LANG.'\'">&nbsp;':'').
			'<input type="submit" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_NEW_LOAD").'&nbsp;&nbsp;&nbsp;" id="download_button" '.($single ? '' : 'disabled').'>'
	);

	/*************************************************/
}
elseif ($strAction=="LOAD")
{
	/*********************************************************************/

	if(LANG == "ru")
		$site = "https://www.1c-bitrix.ru/";
	else
		$site = "https://www.bitrixsoft.com/";

	if($_REQUEST['licence_type'] == 'src' || $_REQUEST['LICENSE_KEY'])
	{
		$path = 'private/download/';
		$suffix = '_source.tar.gz';
		@mkdir($_SERVER['DOCUMENT_ROOT'].'/bitrix');
		$fres = @fopen($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php','wb');
		@fwrite($fres,'<'.'? $LICENSE_KEY = "'.EscapePHPString($_REQUEST['LICENSE_KEY']).'"; ?'.'>') && fclose($fres);
	}
	else
	{
		@unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php');
		$path = 'download/';

		if ($edition == 2) // отраслевые
			$suffix = '_encode_php5.tar.gz';
		else
			$suffix = '_encode.tar.gz';
	}

	$ED = $arEditions[LANG][$edition];
	if (is_array($ED['LIST']))
		$url = $_REQUEST['url'];
	else
		$url = $ED['LIST'];

	if ($_REQUEST['LICENSE_KEY'] && false !== $p = strpos($url, '/'))
		$url = substr($url,$p+1);

	$strRequestedUrl = $site.$path.$url.$suffix;

	$iTimeOut = TIMEOUT;

	$strUserAgent = "BitrixSiteLoader";
	$strFilename = $_SERVER["DOCUMENT_ROOT"]."/".basename($strRequestedUrl);

	$strLog = '';
	$status = '';
	if ($_REQUEST['start'])
	{
		$res = 2;
		SetCurrentProgress(1,100);
	}
	else
	{
		$res = LoadFile($strRequestedUrl.($_REQUEST["LICENSE_KEY"] <> '' ? "?lp=".md5($_REQUEST["LICENSE_KEY"]) : ''), $strFilename, $iTimeOut);
	}
	if (!$res)
		$txt = nl2br($strLog);
	elseif ($res==2) // частичная закачка
	{
		$txt = $status;
		$script = "<script>GoToPage(\"".$this_script_name."?action=LOAD&edition=".urlencode($edition)."&url=".urlencode($url)."&lang=".urlencode(LANG)."&LICENSE_KEY=".urlencode($_REQUEST["LICENSE_KEY"])."&action_next=".urlencode($_REQUEST["action_next"])."&xz=".rand(0, 32000)."\");</script>\n";
	}
	else
	{
		$txt = $status;
		$script = "<script>GoToPage(\"".$this_script_name."?action=UNPACK&by_step=Y&filename=".urlencode(basename($strRequestedUrl))."&lang=".urlencode(LANG)."&xz=".rand(0, 32000)."\");</script>\n";
	}

	$ar = array(
		'FORM' => $form,
		'TITLE' => LoaderGetMessage("LOADER_MENU_LOAD"),
		'HEAD' => LoaderGetMessage("LOADER_MENU_LOAD"),
		'TEXT' => $txt,
		'BOTTOM' => (file_exists($_SERVER['DOCUMENT_ROOT'].'/index.php') && $short ?'<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\'/index.php?lang='.LANG.'\'">&nbsp;':'<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\''.$this_script_name.'?action=LIST&lang='.LANG.'\'">')
	);

	/*********************************************************************/
}
elseif ($strAction=="UNPACK")
{
	/*********************************************************************/
	//	$iNumDistrFiles = 8000;

	SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_ACTION"));
	$oArchiver = new CArchiver($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"], true);
	$tres = $oArchiver->extractFiles($_SERVER["DOCUMENT_ROOT"]);

	SetCurrentProgress($oArchiver->iCurPos, $oArchiver->iArchSize, False);
	$txt = $status;
	if ($tres)
	{
		if (!$oArchiver->bFinish)
			$script = "<script>GoToPage(\"".$this_script_name."?action=UNPACK&filename=".urlencode(basename($oArchiver->_strArchiveName))."&by_step=Y&lang=".urlencode(LANG)."&seek=".$oArchiver->iCurPos."\");</script>\n";
		else // finish
		{
			$res = unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"]) && unlink(__FILE__);
			@unlink(UPDATE_FLAG);
			@unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"].'.log');
			@unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"].'.tmp');

			@unlink($_SERVER['DOCUMENT_ROOT'].'/restore.php');
			$strInstFile = "index.php";

			if (!$res)
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_DELETE"));
			elseif (!file_exists($_SERVER["DOCUMENT_ROOT"]."/".$strInstFile))
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_UNKNOWN"));
			else
			{
				bx_accelerator_reset();
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_SUCCESS"));
				$script = "<script>document.location = \"/".$strInstFile."\";</script>";
			}
		}
	}
	else
	{
		SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_ERRORS"));
		$arErrors = &$oArchiver->GetErrors();
		if (count($arErrors)>0)
		{
			if ($ft = fopen($_SERVER["DOCUMENT_ROOT"]."/".$this_script_name.".log", "wb"))
			{
				foreach ($arErrors as $value)
				{
					$str = "[".$value[0]."] ".$value[1]."\n";
					fwrite($ft, $str);
					$txt .= $str . '<br>';
				}
				fclose($ft);
			}
		}
	}

	$ar = array(
		'FORM' => $form,
		'TITLE' => LoaderGetMessage("LOADER_MENU_UNPACK"),
		'HEAD' => LoaderGetMessage("LOADER_MENU_UNPACK"),
		'TEXT' => $txt,
		'BOTTOM' => '<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\''.$this_script_name.'?action=LIST\'">'
	);

	/*********************************************************************/
}

########### HTML #########
html($ar);
echo $script;
##########################


function LoadFile($strRequestedUrl, $strFilename, $iTimeOut)
{
	global $proxyAddr, $proxyPort, $proxyUserName, $proxyPassword, $strUserAgent, $strRequestedSize;
	$ssl = preg_match('#https://#', $strRequestedUrl);

	$iTimeOut = IntVal($iTimeOut);
	if ($iTimeOut>0)
		$start_time = getmicrotime();

	$strRealUrl = $strRequestedUrl;
	$iStartSize = 0;
	$iRealSize = 0;

	$bCanContinueDownload = False;

	// ИНИЦИАЛИЗИРУЕМ, ЕСЛИ ДОКАЧКА
	$strRealUrl_tmp = "";
	$iRealSize_tmp = 0;
	if (file_exists($strFilename.".tmp") && file_exists($strFilename.".log") && filesize($strFilename.".log")>0)
	{
		$fh = fopen($strFilename.".log", "rb");
		$file_contents_tmp = fread($fh, filesize($strFilename.".log"));
		fclose($fh);

		list($strRealUrl_tmp, $iRealSize_tmp) = explode("\n", $file_contents_tmp);
		$strRealUrl_tmp = Trim($strRealUrl_tmp);
		$iRealSize_tmp = Trim($iRealSize_tmp);
	}
	if ($iRealSize_tmp<=0 || strlen($strRealUrl_tmp)<=0)
	{
		$strRealUrl_tmp = "";
		$iRealSize_tmp = 0;

		if (file_exists($strFilename.".tmp"))
			@unlink($strFilename.".tmp");

		if (file_exists($strFilename.".log"))
			@unlink($strFilename.".log");
	}
	else
	{
		$strRealUrl = $strRealUrl_tmp;
		$iRealSize = $iRealSize_tmp;
		$iStartSize = filesize($strFilename.".tmp");
	}
	// КОНЕЦ: ИНИЦИАЛИЗИРУЕМ, ЕСЛИ ДОКАЧКА

	//	SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_SERVER"));

	// ИЩЕМ ФАЙЛ И ЗАПРАШИВАЕМ ИНФО
	do
	{
		//		SetCurrentStatus(str_replace("#DISTR#", $strRealUrl, LoaderGetMessage("LOADER_LOAD_QUERY_DISTR")));

		$lasturl = $strRealUrl;
		$redirection = "";

		$parsedurl = parse_url($strRealUrl);
		$useproxy = (($proxyAddr != "") && ($proxyPort != ""));

		if (!$useproxy)
		{
			$host = $parsedurl["host"];
			$port = $parsedurl["port"] ?? null;
			$hostname = $host;
		}
		else
		{
			$host = $proxyAddr;
			$port = $proxyPort;
			$hostname = $parsedurl["host"];
		}

		$port = $port ? $port : ($ssl ? 443 : 80);

		//		SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_CONN2HOST")));
		$sockethandle = fsockopen(($ssl ? 'ssl://' : '').$host, $port, $error_id, $error_msg, 30);
		if (!$sockethandle)
		{
			//			SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_NO_CONN2HOST"))." [".$error_id."] ".$error_msg);
			return false;
		}
		else
		{
			if (!$parsedurl["path"])
				$parsedurl["path"] = "/";

			//			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_FILE"));
			$request = "";
			if (!$useproxy)
			{
				$request .= "HEAD ".$parsedurl["path"].(isset($parsedurl["query"]) ? '?'.$parsedurl["query"] : '')." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
			}
			else
			{
				$request .= "HEAD ".$strRealUrl." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
				if ($proxyUserName)
					$request .= "Proxy-Authorization: Basic ".base64_encode($proxyUserName.":".$proxyPassword)."\r\n";
			}

			if ($strUserAgent != "")
				$request .= "User-Agent: $strUserAgent\r\n";

			$request .= "\r\n";

			fwrite($sockethandle, $request);

			$result = "";
			//			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_WAIT"));

			$replyheader = "";
			while (($result = fgets($sockethandle, 4024)) && $result!="\r\n")
			{
				$replyheader .= $result;
			}
			fclose($sockethandle);

			$ar_replyheader = explode("\r\n", $replyheader);

			$replyproto = "";
			$replyversion = "";
			$replycode = 0;
			$replymsg = "";
			if (preg_match("#([A-Z]{4})/([0-9.]{3}) ([0-9]{3})#", $ar_replyheader[0], $regs))
			{
				$replyproto = $regs[1];
				$replyversion = $regs[2];
				$replycode = IntVal($regs[3]);
				$replymsg = substr($ar_replyheader[0], strpos($ar_replyheader[0], $replycode) + strlen($replycode) + 1, strlen($ar_replyheader[0]) - strpos($ar_replyheader[0], $replycode) + 1);
			}

			if ($replycode!=200 && $replycode!=302)
			{
				if ($replycode==403)
					SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER1"));
				else
					SetCurrentStatus(str_replace("#ANS#", $replycode." - ".$replymsg, LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER")).'<br>'.htmlspecialchars($strRequestedUrl));
				return false;
			}

			$strLocationUrl = "";
			$iNewRealSize = 0;
			$strAcceptRanges = "";
			for ($i = 1; $i < count($ar_replyheader); $i++)
			{
				if (strpos($ar_replyheader[$i], "Location") !== false)
					$strLocationUrl = trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
				elseif (strpos($ar_replyheader[$i], "Content-Length") !== false)
					$iNewRealSize = IntVal(Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1)));
				elseif (strpos($ar_replyheader[$i], "Accept-Ranges") !== false)
					$strAcceptRanges = Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
			}

			if (strlen($strLocationUrl)>0)
			{
				$redirection = $strLocationUrl;
				$redirected = true;
				if (!preg_match("#https?://#", $redirection))
					$strRealUrl = dirname($lasturl)."/".$redirection;
				else
					$strRealUrl = $redirection;
			}

			if (strlen($strLocationUrl)<=0)
				break;
		}
	}
	while (true);
	// КОНЕЦ: ИЩЕМ ФАЙЛ И ЗАПРАШИВАЕМ ИНФО

	$bCanContinueDownload = ($strAcceptRanges == "bytes");

	/*
		// ЕСЛИ НЕЛЬЗЯ ДОКАЧИВАТЬ
		if (!$bCanContinueDownload
			|| ($iRealSize>0 && $iNewRealSize != $iRealSize))
		{
		//	SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_NEED_RELOAD"));
		//	$iStartSize = 0;
			die(LoaderGetMessage("LOADER_LOAD_NEED_RELOAD"));
		}
		// КОНЕЦ: ЕСЛИ НЕЛЬЗЯ ДОКАЧИВАТЬ
	*/

	// ЕСЛИ МОЖНО ДОКАЧИВАТЬ
	if ($bCanContinueDownload)
	{
		$fh = fopen($strFilename.".log", "wb");
		if (!$fh)
		{
			SetCurrentStatus(str_replace("#FILE#", $strFilename.".log", LoaderGetMessage("LOADER_LOAD_NO_WRITE2FILE")));
			return false;
		}
		fwrite($fh, $strRealUrl."\n");
		fwrite($fh, $iNewRealSize."\n");
		fclose($fh);
	}
	// КОНЕЦ: ЕСЛИ МОЖНО ДОКАЧИВАТЬ

	//	SetCurrentStatus(str_replace("#DISTR#", $strRealUrl, LoaderGetMessage("LOADER_LOAD_LOAD_DISTR")));
	$strRequestedSize = $iNewRealSize;

	// КАЧАЕМ ФАЙЛ
	$parsedurl = parse_url($strRealUrl);
	$useproxy = (($proxyAddr != "") && ($proxyPort != ""));

	if (!$useproxy)
	{
		$host = $parsedurl["host"];
		$port = $parsedurl["port"] ?? null;
		$hostname = $host;
	}
	else
	{
		$host = $proxyAddr;
		$port = $proxyPort;
		$hostname = $parsedurl["host"];
	}

	$port = $port ? $port : ($ssl ? 443 : 80);

	SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_CONN2HOST")));
	$sockethandle = fsockopen(($ssl ? 'ssl://' : '').$host, $port, $error_id, $error_msg, 30);
	if (!$sockethandle)
	{
		SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_NO_CONN2HOST"))." [".$error_id."] ".$error_msg);
		return false;
	}
	else
	{
		if (!$parsedurl["path"])
			$parsedurl["path"] = "/";

		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_FILE"));

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
			if ($proxyUserName)
				$request .= "Proxy-Authorization: Basic ".base64_encode($proxyUserName.":".$proxyPassword)."\r\n";
		}

		if ($strUserAgent != "")
			$request .= "User-Agent: $strUserAgent\r\n";

		if ($bCanContinueDownload && $iStartSize>0)
			$request .= "Range: bytes=".$iStartSize."-\r\n";

		$request .= "\r\n";

		fwrite($sockethandle, $request);

		$result = "";
		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_WAIT"));

		$replyheader = "";
		while (($result = fgets($sockethandle, 4096)) && $result!="\r\n")
			$replyheader .= $result;

		$ar_replyheader = explode("\r\n", $replyheader);

		$replyproto = "";
		$replyversion = "";
		$replycode = 0;
		$replymsg = "";
		if (preg_match("#([A-Z]{4})/([0-9.]{3}) ([0-9]{3})#", $ar_replyheader[0], $regs))
		{
			$replyproto = $regs[1];
			$replyversion = $regs[2];
			$replycode = IntVal($regs[3]);
			$replymsg = substr($ar_replyheader[0], strpos($ar_replyheader[0], $replycode) + strlen($replycode) + 1, strlen($ar_replyheader[0]) - strpos($ar_replyheader[0], $replycode) + 1);
		}

		if ($replycode!=200 && $replycode!=302 && $replycode!=206)
		{
			SetCurrentStatus(str_replace("#ANS#", $replycode." - ".$replymsg, LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER")));
			return false;
		}

		$strContentRange = "";
		$iContentLength = 0;
		$strAcceptRanges = "";
		for ($i = 1; $i < count($ar_replyheader); $i++)
		{
			if (strpos($ar_replyheader[$i], "Content-Range") !== false)
				$strContentRange = trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
			elseif (strpos($ar_replyheader[$i], "Content-Length") !== false)
				$iContentLength = doubleval(Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1)));
			elseif (strpos($ar_replyheader[$i], "Accept-Ranges") !== false)
				$strAcceptRanges = Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
		}

		$bReloadFile = True;
		if (strlen($strContentRange)>0)
		{
			if (preg_match("# *bytes +([0-9]*) *- *([0-9]*) */ *([0-9]*)#i", $strContentRange, $regs))
			{
				$iStartBytes_tmp = doubleval($regs[1]);
				$iEndBytes_tmp = doubleval($regs[2]);
				$iSizeBytes_tmp = doubleval($regs[3]);

				if ($iStartBytes_tmp==$iStartSize
					&& $iEndBytes_tmp==($iNewRealSize-1)
					&& $iSizeBytes_tmp==$iNewRealSize)
				{
					$bReloadFile = False;
				}
			}
		}

		if ($bReloadFile)
		{
			@unlink($strFilename.".tmp");
			$iStartSize = 0;
		}

		if (($iContentLength+$iStartSize)!=$iNewRealSize)
		{
			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_ERR_SIZE"));
			return false;
		}

		$fh = fopen($strFilename.".tmp", "ab");
		if (!$fh)
		{
			SetCurrentStatus(str_replace("#FILE#", $strFilename.".tmp", LoaderGetMessage("LOADER_LOAD_CANT_OPEN_WRITE")));
			return false;
		}

		$bFinished = True;
		$downloadsize = (double) $iStartSize;
		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_LOADING"));
		while (!feof($sockethandle))
		{
			if ($iTimeOut>0 && (getmicrotime()-$start_time)>$iTimeOut)
			{
				$bFinished = False;
				break;
			}

			$result = fread($sockethandle, 256 * 1024);
			$downloadsize += strlen($result);
			if ($result=="")
				break;

			fwrite($fh, $result);
		}
		SetCurrentProgress($downloadsize,$iNewRealSize);

		fclose($fh);
		fclose($sockethandle);

		if ($bFinished)
		{
			@unlink($strFilename);
			if (!@rename($strFilename.".tmp", $strFilename))
			{
				SetCurrentStatus(str_replace("#FILE2#", $strFilename, str_replace("#FILE1#", $strFilename.".tmp", LoaderGetMessage("LOADER_LOAD_ERR_RENAME"))));
				return false;
			}
			@unlink($strFilename.".tmp");
		}
		else
			return 2;

		SetCurrentStatus(str_replace("#SIZE#", $downloadsize, str_replace("#FILE#", $strFilename, LoaderGetMessage("LOADER_LOAD_FILE_SAVED"))));
		@unlink($strFilename.".log");
		return 1;
	}
	// КОНЕЦ: КАЧАЕМ ФАЙЛ
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
	<script>
		function GoToPage(url)
		{
			window.setTimeout('document.location="' + url + '"', 500);
		}
	</script>
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
		.content-table select{
			width:100%;
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
	</style>
	<div class="wrap <?=LANG?>">
		<header class="header">
			<?if ($isCrm || LANG != 'ru'):?>
				<a href="" target="_blank" class="logo-link"><span class="logo <?=LANG?>"></span></a>
			<?else:?>
				<a href="" target="_blank" class="buslogo-link"><span class="buslogo <?=LANG?>"></span></a>
			<?endif?>
		</header>
		<section class="content">
			<?=$ar['FORM']?>
			<div class="content-container">
				<table class="content-table">
					<tr>
						<td align=left style="/*font-size:14pt;color:#E11537;padding-left:25px*/">
							<h3 class="content-header" style="margin: 0;padding: 0;text-align: left;"><?=$ar['HEAD']?></h3>
							<hr style="margin: 15px 0;">
						</td>
					</tr>
					<tr>
						<td style="font-size:10pt" valign="<?=($ar['TEXT_ALIGN'] ?? 'middle')?>"><?=$ar['TEXT']?></td>
					</tr>
					<tr>
						<td style="font-size:10pt; padding-top: 20px" valign="middle" align="center" height="40px"><?=$ar['BOTTOM']?></td>
					</tr>
				</table>

				<div class="content-block">
					<div class="select-container" onclick="document.getElementById('lang-popup').style.display = document.getElementById('lang-popup').style.display == 'block' ? 'none' : 'block'">
						<label for="ss"><span class="selected-lang lang <?=LANG?>"></span></label>
						<div class="select-popup" id="lang-popup">
							<?
							$langs = array('en','de');
							if(LANG == 'ru')
								$langs = array('en','de','ru');
								
							foreach($langs as $l)
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
			</form>
		</section>
		<div class="cloud-layer">
			<div class="cloud cloud-1 cloud-fill"></div>
			<div class="cloud cloud-2 cloud-border"></div>
			<div class="cloud cloud-3 cloud-border"></div>
			<div class="cloud cloud-4 cloud-border"></div>
			<div class="cloud cloud-5 cloud-border"></div>
			<div class="cloud cloud-6 cloud-border"></div>
		</div>
	</div>
	</body>
	</html>
	<?
}

function SetCurrentStatus($str)
{
	global $strLog;
	$strLog .= $str."\n";
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
	if ($val > 99)
		$val = 99;

	$status = '
	<div class="progressbar-container">
		<div class="progressbar-track">
			<div class="progressbar-loader" style="width:'.$val.'%"></div>
		</div>
		<div class="progressbar-counter">'.$val.'%</div>
	</div>';
}

function getmicrotime()
{
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

class CArchiver
{
	var $_strArchiveName = "";
	var $_bCompress = false;
	var $_strSeparator = " ";
	var $_dFile = 0;

	var $_arErrors = array();
	var $iArchSize = 0;
	var $iCurPos = 0;
	var $bFinish = false;

	function __construct($strArchiveName, $bCompress = false)
	{
		$this->_bCompress = false;
		if (!$bCompress)
		{
			if (file_exists($strArchiveName))
			{
				if ($fp = fopen($strArchiveName, "rb"))
				{
					$data = fread($fp, 2);
					fclose($fp);
					if ($data == "\37\213")
					{
						$this->_bCompress = True;
					}
				}
			}
			else
			{
				if (substr($strArchiveName, -2) == 'gz')
				{
					$this->_bCompress = True;
				}
			}
		}
		else
		{
			$this->_bCompress = True;
		}

		$this->_strArchiveName = $strArchiveName;
		$this->_arErrors = array();
	}

	function extractFiles($strPath, $vFileList = false)
	{
		$this->_arErrors = array();

		$v_result = true;
		$v_list_detail = array();

		$strExtrType = "complete";
		$arFileList = 0;
		if ($vFileList!==false)
		{
			$arFileList = &$this->_parseFileParams($vFileList);
			$strExtrType = "partial";
		}

		if ($v_result = $this->_openRead())
		{
			$v_result = $this->_extractList($strPath, $v_list_detail, $strExtrType, $arFileList, '');
			$this->_close();
		}

		return $v_result;
	}

	function &GetErrors()
	{
		return $this->_arErrors;
	}

	function _extractList($p_path, &$p_list_detail, $p_mode, $p_file_list, $p_remove_path)
	{
		global $iNumDistrFiles;

		$v_result = true;
		$v_nb = 0;
		$v_extract_all = true;
		$v_listing = false;

		$p_path = str_replace("\\", "/", $p_path);

		if ($p_path == ''
			|| (substr($p_path, 0, 1) != '/'
				&& substr($p_path, 0, 3) != "../"
				&& !strpos($p_path, ':')))
		{
			$p_path = "./".$p_path;
		}

		$p_remove_path = str_replace("\\", "/", $p_remove_path);
		if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/'))
			$p_remove_path .= '/';

		$p_remove_path_size = strlen($p_remove_path);

		switch ($p_mode)
		{
			case "complete" :
				$v_extract_all = TRUE;
				$v_listing = FALSE;
				break;
			case "partial" :
				$v_extract_all = FALSE;
				$v_listing = FALSE;
				break;
			case "list" :
				$v_extract_all = FALSE;
				$v_listing = TRUE;
				break;
			default :
				$this->_arErrors[] = array("ERR_PARAM", "Invalid extract mode (".$p_mode.")");
				return false;
		}

		clearstatcache();

		$tm=time();
		while((extension_loaded("mbstring")? mb_strlen($v_binary_data = $this->_readBlock(), "latin1") : strlen($v_binary_data = $this->_readBlock())) != 0)
		{
			$v_extract_file = FALSE;
			$v_extraction_stopped = 0;

			if (!$this->_readHeader($v_binary_data, $v_header))
				return false;

			if ($v_header['filename'] == '')
				continue;

			// ----- Look for long filename
			if ($v_header['typeflag'] == 'L')
			{
				if (!$this->_readLongHeader($v_header))
					return false;
			}


			if ((!$v_extract_all) && (is_array($p_file_list)))
			{
				// ----- By default no unzip if the file is not found
				$v_extract_file = false;

				for ($i = 0; $i < count($p_file_list); $i++)
				{
					// ----- Look if it is a directory
					if (substr($p_file_list[$i], -1) == '/')
					{
						// ----- Look if the directory is in the filename path
						if ((strlen($v_header['filename']) > strlen($p_file_list[$i]))
							&& (substr($v_header['filename'], 0, strlen($p_file_list[$i])) == $p_file_list[$i]))
						{
							$v_extract_file = TRUE;
							break;
						}
					}
					elseif ($p_file_list[$i] == $v_header['filename'])
					{
						// ----- It is a file, so compare the file names
						$v_extract_file = TRUE;
						break;
					}
				}
			}
			else
			{
				$v_extract_file = TRUE;
			}

			// ----- Look if this file need to be extracted
			if (($v_extract_file) && (!$v_listing))
			{
				if (($p_remove_path != '')
					&& (substr($v_header['filename'], 0, $p_remove_path_size) == $p_remove_path))
				{
					$v_header['filename'] = substr($v_header['filename'], $p_remove_path_size);
				}
				if (($p_path != './') && ($p_path != '/'))
				{
					while (substr($p_path, -1) == '/')
						$p_path = substr($p_path, 0, strlen($p_path)-1);

					if (substr($v_header['filename'], 0, 1) == '/')
						$v_header['filename'] = $p_path.$v_header['filename'];
					else
						$v_header['filename'] = $p_path.'/'.$v_header['filename'];
				}
				if (file_exists($v_header['filename']))
				{
					if ((@is_dir($v_header['filename'])) && ($v_header['typeflag'] == ''))
					{
						$this->_arErrors[] = array("DIR_EXISTS", "File '".$v_header['filename']."' already exists as a directory");
						return false;
					}
					if ((is_file($v_header['filename'])) && ($v_header['typeflag'] == "5"))
					{
						$this->_arErrors[] = array("FILE_EXISTS", "Directory '".$v_header['filename']."' already exists as a file");
						return false;
					}
					if (!is_writeable($v_header['filename']))
					{
						$this->_arErrors[] = array("FILE_PERMS", "File '".$v_header['filename']."' already exists and is write protected");
						return false;
					}
				}
				elseif (($v_result = $this->_dirCheck(($v_header['typeflag'] == "5" ? $v_header['filename'] : dirname($v_header['filename'])))) != 1)
				{
					$this->_arErrors[] = array("NO_DIR", "Unable to create path for '".$v_header['filename']."'");
					return false;
				}

				if ($v_extract_file)
				{
					if ($v_header['typeflag'] == "5")
					{
						if (!@file_exists($v_header['filename']))
						{
							if (!@mkdir($v_header['filename'], BX_DIR_PERMISSIONS))
							{
								$this->_arErrors[] = array("ERR_CREATE_DIR", "Unable to create directory '".$v_header['filename']."'");
								return false;
							}
						}
					}
					else
					{
						if (($v_dest_file = fopen($v_header['filename'], "wb")) == 0)
						{
							$this->_arErrors[] = array("ERR_CREATE_FILE", LoaderGetMessage('NO_PERMS') .' '. $v_header['filename']);
							return false;
						}
						else
						{
							$n = floor($v_header['size']/512);
							for ($i = 0; $i < $n; $i++)
							{
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, 512);
							}
							if (($v_header['size'] % 512) != 0)
							{
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
							}

							@fclose($v_dest_file);

							@chmod($v_header['filename'], BX_FILE_PERMISSIONS);
							@touch($v_header['filename'], $v_header['mtime']);
						}

						clearstatcache();
						if (filesize($v_header['filename']) != $v_header['size'])
						{
							$this->_arErrors[] = array("ERR_SIZE_CHECK", "Extracted file '".$v_header['filename']."' have incorrect file size '".filesize($v_filename)."' (".$v_header['size']." expected). Archive may be corrupted");
							return false;
						}
					}
				}
				else
				{
					$this->_jumpBlock(ceil(($v_header['size']/512)));
				}
			}
			else
			{
				$this->_jumpBlock(ceil(($v_header['size']/512)));
			}

			if ($v_listing || $v_extract_file || $v_extraction_stopped)
			{
				if (($v_file_dir = dirname($v_header['filename'])) == $v_header['filename'])
					$v_file_dir = '';
				if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == ''))
					$v_file_dir = '/';

				$p_list_detail[$v_nb++] = $v_header;

				if ($v_nb % 100 == 0)
					SetCurrentProgress($this->iCurPos, $this->iArchSize, False);
			}

			if ($_REQUEST['by_step'] && (time()-$tm) > TIMEOUT)
			{
				SetCurrentProgress($this->iCurPos, $this->iArchSize, False);
				return true;
			}
		}
		$this->bFinish = true;
		return true;
	}

	function _readBlock()
	{
		$v_block = "";
		if (is_resource($this->_dFile))
		{
			if (isset($_REQUEST['seek']))
			{
				if ($this->_bCompress)
					gzseek($this->_dFile, intval($_REQUEST['seek']));
				else
					fseek($this->_dFile, intval($_REQUEST['seek']));

				$this->iCurPos = IntVal($_REQUEST['seek']);

				unset($_REQUEST['seek']);
			}
			if ($this->_bCompress)
				$v_block = gzread($this->_dFile, 512);
			else
				$v_block = fread($this->_dFile, 512);

			$this->iCurPos +=  (extension_loaded("mbstring")? mb_strlen($v_block, "latin1") : strlen($v_block));
		}
		return $v_block;
	}

	function _readHeader($v_binary_data, &$v_header)
	{
		if ((extension_loaded("mbstring")? mb_strlen($v_binary_data, "latin1") : strlen($v_binary_data)) ==0)
		{
			$v_header['filename'] = '';
			return true;
		}

		if ((extension_loaded("mbstring")? mb_strlen($v_binary_data, "latin1") : strlen($v_binary_data)) != 512)
		{
			$v_header['filename'] = '';
			$this->_arErrors[] = array("INV_BLOCK_SIZE", "Invalid block size : ".strlen($v_binary_data)."");
			return false;
		}

		$chars = count_chars(substr($v_binary_data,0,148).'        '.substr($v_binary_data,156,356));
		$v_checksum = 0;
		foreach($chars as $ch => $cnt)
			$v_checksum += $ch*$cnt;

		$v_data = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix/a12temp", $v_binary_data);

		$v_header['checksum'] = OctDec(trim($v_data['checksum']));
		if ($v_header['checksum'] != $v_checksum)
		{
			$v_header['filename'] = '';

			if (($v_checksum == 256) && ($v_header['checksum'] == 0))
				return true;

			$this->_arErrors[] = array("INV_BLOCK_CHECK", "Invalid checksum for file '".$v_data['filename']."' : ".$v_checksum." calculated, ".$v_header['checksum']." expected");
			return false;
		}

		// ----- Extract the properties
		$v_header['filename'] = trim($v_data['prefix'], "\x00")."/".trim($v_data['filename'], "\x00");
		$v_header['mode'] = OctDec(trim($v_data['mode']));
		$v_header['uid'] = OctDec(trim($v_data['uid']));
		$v_header['gid'] = OctDec(trim($v_data['gid']));
		$v_header['size'] = OctDec(trim($v_data['size']));
		$v_header['mtime'] = OctDec(trim($v_data['mtime']));
		if (($v_header['typeflag'] = $v_data['typeflag']) == "5")
			$v_header['size'] = 0;

		return true;
	}

	function _readLongHeader(&$v_header)
	{
		$v_filename = '';
		$n = floor($v_header['size']/512);
		for ($i = 0; $i < $n; $i++)
		{
			$v_content = $this->_readBlock();
			$v_filename .= $v_content;
		}
		if (($v_header['size'] % 512) != 0)
		{
			$v_content = $this->_readBlock();
			$v_filename .= $v_content;
		}

		$v_binary_data = $this->_readBlock();

		if (!$this->_readHeader($v_binary_data, $v_header))
			return false;

		$v_header['filename'] = trim($v_filename, "\x00");

		return true;
	}

	function _jumpBlock($p_len = false)
	{
		if (is_resource($this->_dFile))
		{
			if ($p_len === false)
				$p_len = 1;

			if ($this->_bCompress)
				gzseek($this->_dFile, gztell($this->_dFile)+($p_len*512));
			else
				fseek($this->_dFile, ftell($this->_dFile)+($p_len*512));
		}
		return true;
	}

	function &_parseFileParams(&$vFileList)
	{
		if (isset($vFileList) && is_array($vFileList))
			return $vFileList;
		elseif (isset($vFileList) && strlen($vFileList)>0)
			return explode($this->_strSeparator, $vFileList);
		else
			return array();
	}

	function _openRead()
	{

		if ($this->_bCompress)
		{
			$this->_dFile = gzopen($this->_strArchiveName, "rb");
			$this->iArchSize = filesize($this->_strArchiveName) * 3;
		}
		else
		{
			$this->_dFile = fopen($this->_strArchiveName, "rb");
			$this->iArchSize = filesize($this->_strArchiveName);
		}

		if (!$this->_dFile)
		{
			$this->_arErrors[] = array("ERR_OPEN", "Unable to open '".$this->_strArchiveName."' in read mode");
			return false;
		}

		return true;
	}

	function _close()
	{
		if (is_resource($this->_dFile))
		{
			if ($this->_bCompress)
				gzclose($this->_dFile);
			else
				fclose($this->_dFile);

			$this->_dFile = 0;
		}

		return true;
	}

	function _dirCheck($p_dir)
	{
		if ((is_dir($p_dir)) || ($p_dir == ''))
			return true;

		$p_parent_dir = dirname($p_dir);

		if (($p_parent_dir != $p_dir) &&
			($p_parent_dir != '') &&
			(!$this->_dirCheck($p_parent_dir)))
			return false;

		if (!is_dir($p_dir) && !mkdir($p_dir, BX_DIR_PERMISSIONS))
		{
			$this->_arErrors[] = array("CANT_CREATE_PATH", "Unable to create directory '".$p_dir."'");
			return false;
		}

		return true;
	}

}

function img($name)
{
	if (file_exists($_SERVER['DOCUMENT_ROOT'].'/images/'.$name))
		return '/images/'.$name;

	if(LANG == 'ru')
		return 'https://www.1c-bitrix.ru/images/bitrix_setup/'.$name;
	else
		return 'https://www.bitrixsoft.com/images/bitrix_setup/'.$name;

}

function ShowError($str)
{
	return	'<div style="color:red;text-align:center;border:1px solid red;padding:5px;">'.$str.'</div>';
}

function bx_accelerator_reset()
{
	if(function_exists("accelerator_reset"))
		accelerator_reset();
	elseif(function_exists("wincache_refresh_if_changed"))
		wincache_refresh_if_changed();
}

function EscapePHPString($str)
{
	$str = str_replace("\\", "\\\\", $str);
	$str = str_replace("\$", "\\\$", $str);
	$str = str_replace("\"", "\\"."\"", $str);
	return $str;
}
