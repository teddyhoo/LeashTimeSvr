<?
// provider-payables.php

// id - provider id
// through - through date

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require "client-fns.php";
require "pay-fns.php";

// Determine access privs
$locked = locked('vh');

extract($_REQUEST);

$provider = getProviderShortNames("WHERE providerid = $id");
$provider = $id ? $provider[$id] : 'Unassigned';

if(userRole() == 'p' && $_SESSION["providerid"] != $id) {
  echo "<h2>Insufficient rights to view this page.<h2>";
  exit;
}

if($csv) {
	require "provider-payables-inc.php";
	exit;
}

//print_r($payables[1]);

$windowTitle = $provider."&apos;s Payables";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}";
require "frame-bannerless.php";

$includeCalendarWidgets = true;
echo "<form>";
include "provider-payables-inc.php";
echo "</form>";

?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
setPrettynames('starting', 'Starting date', 'ending', 'Ending date');
function showVisitsInRange() {
  if(MM_validateForm(
		  'starting', '', 'isDate',
		  'ending', '', 'isDate')) {
				document.location.href='provider-payables.php?id=<?= $id ?>&starting='+
													escape(document.getElementById('starting').value)+
													'&ending='+escape(document.getElementById('ending').value);
	}
	
}

</script>
