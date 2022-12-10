<? // confirmation-mod-ajax.php

// preference-list.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "confirmation-fns.php";

$locked = locked('o-');

if($_GET['action'] == 'cancel') cancelConfirmation($_GET['id'], $_GET['note']);
else if($_GET['action'] == 'receive') confirm($_GET['id'], $_GET['note']);

echo "done";