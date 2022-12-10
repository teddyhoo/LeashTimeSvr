<?
/*
* petpick-update-ajax.php
*/
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "petpick-grid.php";
require_once "pet-fns.php";


// Determine access privs
$locked = locked('o-');

extract($_REQUEST);  // if POSTed from here, id will be null, but clientid may be set
$client = isset($client) ? $client : '';
$allPetNames = $client ? getClientPetNames($client) : '';

petPickerInnerHTML($allPetNames, $petpickerOptionPrefix);