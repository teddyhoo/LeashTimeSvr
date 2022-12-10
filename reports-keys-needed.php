<? // reports-keys-needed.php WHOOPS!  THIS DUPLICATES the keys-needed.php report from the Home page (key icon)
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";

require_once "key-fns.php";

function findKeyNeedForProvider($providerptr, $lookahead) {
  $clientAppts = getNextAppointmentDatePerClientForProvider($providerptr, $lookahead);
  $clientIds = array_keys($clientAppts);
  //$clientIds = getActiveClientIdsForProvider($providerptr);
  $clients = getClientDetails($clientIds, array('sortname'));
