<? // reports-donotserve.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "provider-fns.php";
require_once "client-fns.php";

// Determine access privs
$locked = locked('o-#vr');
extract(extractVars('month,omitinactiveclients,omitinactiveproviders', $_REQUEST));

		
$pageTitle = "Do Not Serve Lists";

$result = doQuery(
	"SELECT providerptr, property
		FROM tblproviderpref 
		WHERE property LIKE 'donotserve_%'", 1);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	$clientptr = substr($row['property'], strlen('donotserve_'));
	$bySitter[$row['providerptr']][] = $clientptr;
	$byClient[$clientptr][] = $row['providerptr'];
}
$inactiveSitters = fetchCol0("SELECT providerid FROM tblprovider WHERE active = 0", 1);
$inactiveClients = fetchCol0("SELECT clientid FROM tblclient WHERE active = 0", 1);
$omitinactiveprovidersclause = $omitinactiveproviders ? "WHERE active = 1" : '';
$omitinactiveclientsclause = $omitinactiveclients ? "AND active = 1" : '';
if($bySitter) {
	$sitterNames = fetchKeyValuePairs(
		"SELECT providerid, CONCAT_WS(' ', fname, lname, IF(nickname IS NULL, '', CONCAT('(', nickname, ')')))
			FROM tblprovider $omitinactiveprovidersclause
			ORDER BY lname, fname");
	$clientNames = fetchKeyValuePairs( // , CONCAT('(@', clientid, ')')
		"SELECT clientid, CONCAT_WS(' ', fname, lname), lname, fname
			FROM tblclient
			WHERE clientid IN (".join(',', array_keys($byClient)).") $omitinactiveclientsclause
			ORDER BY lname, fname");
}
if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";
	$extraHeadContent = "<style>.addresstable td {font-size:1.1em;} .quicktableheaders td {font-weight:bold;} .inactive {color:#993333;font-style:italic;}</style>";
	include "frame.html";
	
	// ***************************************************************************
	if(!$bySitter) 
		echo "No <b>Do Not Serve Lists</b> set up.<p><img src='art/spacer.gif' width=1 height=300>";
	else {
		echo "<form method='GET' name='redo'>";
		labeledCheckbox('Omit inactive sitters', 'omitinactiveproviders', $omitinactiveproviders, $labelClass=null, $inputClass=null, $onClick="document.redo.submit()", $boxFirst=TRUE, $noEcho=false, $title=null);
		labeledCheckbox('Omit inactive clients', 'omitinactiveclients', $omitinactiveclients, $labelClass=null, $inputClass=null, $onClick="document.redo.submit()", $boxFirst=TRUE, $noEcho=false, $title=null);

		$rows = array();
		echo "<h3>By Sitter</h3>Inactive sitters and clients are shown in <span class='inactive'>italics</span>";
		foreach($sitterNames as $id => $name) if($bySitter[$id]) {
			if(in_array($id, $inactiveSitters)) $name = "<span class='inactive'>$name</span>";
			$list = array();
			foreach($clientNames as $memberid=>$nm) {
				if(in_array($memberid, $inactiveClients)) $nm = "<i>$nm</i>";
				if(in_array($memberid, $bySitter[$id])) $list[] = $nm;
			}
			if(!$list) continue;
			$rows[] = array('Sitter'=>$name, 'Clients'=>join(', ', $list));
		}
		quickTable($rows, "class='addresstable' border=1 bordercolor=gray");
		
		$rows = array();
		echo "<h3>By Client</h3>Inactive sitters and clients are shown in <span class='inactive'>italics</span>";
		foreach($clientNames as $id => $name) {
			if(in_array($id, $inactiveClients)) $name = "<span class='inactive'>$name</span>";
			$list = array();
			foreach($sitterNames as $memberid=>$nm) {
			if(in_array($memberid, $inactiveSitters)) $nm = "<i>$nm</i>";
				if(in_array($memberid, $byClient[$id])) $list[] = $nm;
			}
			if(!$list) continue;
			$rows[] = array('Client'=>$name, 'Sitters'=>join(', ', $list));
		}
		quickTable($rows, "class='addresstable' border=1 bordercolor=gray");
	}
	// ***************************************************************************
		include "frame-end.html";
}




function rowSort($a, $b) {
	global $sortKey;
	return strcmp(strtoupper($a[$sortKey]), strtoupper($b[$sortKey]));
}
	

function dumpCSVRow($row) {
	if(!$row) echo "\n";
	if(is_array($row)) echo join(',', array_map('csv',$row))."\n";
	else echo csv($row)."\n";
}

function csv($val) {
  $val = (strpos($val, '"') !== FALSE) ? str_replace('"', '""', $val) : $val;
  $val = (strpos($val, "\r") !== FALSE) ? str_replace("\r", ' ', $val) : $val;
  $val = (strpos($val, "\n") !== FALSE) ? str_replace("\n", ' ', $val) : $val;
	return "\"$val\"";
}

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>

function openRequest(id) {
	openConsoleWindow("viewrequest", "request-edit.php?id="+id+"&updateList=requests",610,600);
}

function payProjectionDetail(prov) {
	var td = document.getElementById('detail_'+prov);
	if(td.innerHTML) {
		$.fn.removeClass("selectedbackground");
		td.innerHTML = '';
	}
	else {
		$.fn.addClass("selectedbackground");
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, fillInDetail, 'detail_'+prov);
		//ajaxGet("payroll-projection-detail.php?start=<?= date('Y-m-d', strtotime($start)) ?>&end=<?= date('Y-m-d', strtotime($end)) ?>&prov="+prov, 'detail_'+prov);
	}
}

function fillInDetail(divid, html) {
	document.getElementById(divid).innerHTML = html;
	$('.BlockContent-body').busy("hide");
}

function sortClick(sortKey, direction) {
	var start = '<?= $start ?>';
	var end = '<?= $end ?>';
	//var providers = document.getElementById('providers');
	//providers = providers.options[providers.selectedIndex].value;
	document.location.href='reports-logins.php?sort='+sortKey+'_'+direction
		+'&start='+start+'&end='+end; //+'&providers='+providers
}
</script>