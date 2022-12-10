<?
// get-client-rates-ajax.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";

echo getClientChargesJSArray($_REQUEST['client']);
