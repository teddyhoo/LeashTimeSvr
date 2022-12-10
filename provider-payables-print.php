<?
// provider-payables-print.php

// ids - provider ids
// through - through date

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";
require "client-fns.php";
require "pay-fns.php";

// Determine access privs
$locked = locked('#pa');

extract($_REQUEST);

$windowTitle = "Sitter Payables";
$customStyles = ".dateRow {background: lightblue;font-weight:bold;text-align:center;}
@media print
{
.breakbefore {page-break-before:always}
}";
require "frame-bannerless.php";
$includeCalendarWidgets = true;
$print = true;

echo "<span class='h2 fauxlink' style='color:blue' id='printlink' onclick='document.getElementById(\"printlink\").style.display=\"none\";window.print();'>Print</span>";

foreach(explode(',', $_REQUEST['ids']) as $id) {

	$provider = getProviderShortNames("WHERE providerid = $id");
	$provider = $id ? $provider[$id] : 'Unassigned';
	include "provider-payables-inc.php";
	$h2class = 'class="breakbefore"';
}
?>
