<?
// prov-switch-interface.php
/* args:
mode: mobile or web
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
locked('p-');

$_SESSION['mobileVersionPreferred'] = $_GET['mode'] == 'mobile';

globalRedirect('index.php');