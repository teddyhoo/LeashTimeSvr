<?
// killNRPackage.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "service-fns.php";

extract($_REQUEST);

deleteNRPackage($id, $descndents=false, $ancestors=false);