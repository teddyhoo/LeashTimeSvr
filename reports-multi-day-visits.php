<? // reports-multi-day-visits.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "provider-fns.php";
require_once "service-fns.php";

$locked = locked('o-');
//http://leashtime.com/reports-daily-visits.php?date=2011-05-01&nocanceled=0&includeaddress=1&includepets=1

extract(extractVars('starting,ending,nocanceled,includeaddress,includepets,sortbytime,showstarts,showfinishes', $_REQUEST));
$showall = !$showstarts && !$showfinishes;
?>
<div style='position:absolute;top:0px;right:0px;'>
<form name='show' method='POST'>
<?
labeledCheckbox('Show All', 'showall', $showall, $labelClass=null, $inputClass=null, $onClick='filterClick(this)', $boxFirst=true, $noEcho=false, $title='Show All Visits');
labeledCheckbox('Show STARTs', 'showstarts', $showstarts, $labelClass=null, $inputClass=null, $onClick='filterClick(this)', $boxFirst=true, $noEcho=false, $title='Show All Visits');
labeledCheckbox('Show FINISHes', 'showfinishes', $showfinishes, $labelClass=null, $inputClass=null, $onClick='filterClick(this)', $boxFirst=true, $noEcho=false, $title='Show All Visits');
echoButton('', 'Show', 'document.show.submit()');
?>
</form>
</div>
<script>
function filterClick(el) {
	if(el.id == 'showall') {
		document.getElementById('showstarts').checked=false;
		document.getElementById('showfinishes').checked=false;
	}
	else document.getElementById('showall').checked=false;
}

</script>
<?

$starting = date('Y-m-d', strtotime($starting));
$ending = date('Y-m-d', strtotime($ending));
$nocanceled = 1;
$includeaddress = 0;
$includepets = 1;
$included = 1;
for($date = $starting; strcmp($date, $ending) <= 0; $date = date('Y-m-d', strtotime("+1 day", strtotime($date)))) {
	include "reports-daily-visits.php";
	$newPage = 1;
}
	//echo file_get_contents(globalURL("reports-daily-visits.php?date=$day&nocanceled=$nocanceled&includeaddress=$includeaddress&includepets=$includepets"));
