<? // client-flag-icon-picker.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-flag-fns.php";

$extraBodyStyle = 'background:white;background-image:none;';

require "frame-bannerless.php";

$images = strpos($_REQUEST['imgs'], 'COMPRESSED...') === 0
	? gzuncompress(substr($_REQUEST['imgs'], strlen('COMPRESSED...')))
	: $_REQUEST['imgs'];

if($_REQUEST['billing']) bizBillingFlagPicker($_REQUEST['index'], $_REQUEST['imgs'], $_REQUEST['src']);
else bizFlagPicker($_REQUEST['index'], $_REQUEST['imgs'], $_REQUEST['src']);