<?
// maint-biz-search.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');
extract($_GET);
$pat = mysql_real_escape_string($pat);
if($pat) { // AJAX
	// find db name and biz name matches
	$bizids = fetchCol0("SELECT bizid FROM tblpetbiz WHERE bizname LIKE '%$pat%' OR db LIKE '%$pat%' OR state LIKE '%$pat%' OR CONCAT('[', state) LIKE '%$pat%'");
	
	// WASTE OF TIME
	//$ltDB = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1"); 
	//reconnectPetBizDB($ltDB['db'], $ltDB['dbhost'], $ltDB['dbuser'], $ltDB['dbpass'], $force=true);
	//$ggcodes = fetchCol0("SELECT garagegatecode FROM tblclient WHERE garagegatecode IS NOT NULL AND CONCAT_WS(' ', fname, lname) LIKE '%$pat%' OR homephone LIKE '%$pat%' OR cellphone LIKE '%$pat%' OR state LIKE '%$pat%' OR CONCAT('[', state) LIKE '%$pat%'");
	//require "common/init_db_common.php";
	
	// find manager name matches
	$bizids = array_unique(array_merge((array)$ggcodes, $bizids, fetchCol0(
		"SELECT bizptr 
			FROM tbluser 
			WHERE NOT ltstaffuserid AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
				AND (loginid LIKE '%$pat%'
							OR email LIKE '%$pat%'
							OR CONCAT_WS(' ', fname, lname) LIKE '%$pat%')
			ORDER BY rights DESC", 'userid')));
	$allowners = fetchAssociationsKeyedBy(
		"SELECT * 
			FROM tbluser 
			WHERE NOT ltstaffuserid AND (rights LIKE 'o-%' OR rights LIKE 'd-%')
			ORDER BY rights DESC", 'userid');
	foreach($allowners as $owner) $owners[$owner['bizptr']][] = $owner;
	
	if($bizids) {
		$businessesOnly = FALSE; //in_array('b', getMaintRights());
		$bizzes = fetchAssociations(
			"SELECT * 
				FROM tblpetbiz 
				WHERE bizid IN ('".join("','", $bizids)."') 
				ORDER BY bizname");
			
		$databases = fetchCol0("SHOW DATABASES");
		foreach($bizzes as $biz) {
			$row = $biz;
			if(!in_array($biz['db'], $databases)) continue;

			$row['state'] = $row['state'] ? "[{$row['state']}]" : '';
			if($row['test']) $row['state'] .= "(T)";
			$countrynames = explodePairsLine('UK|Britain||CA|Canada||AU|Australia||NZ|New Zealand');
			$flag  = $biz['country'] == 'US' ? '' : " <img src='art/world-flag-{$biz['country']}.gif' height=11 title='{$countrynames[$biz['country']]}'> ";
			$row['bizname'] = loginLink($row['bizid']).$flag.searchBizLink($row['bizname'], $row['bizid']);
			$row['activebiz'] = $row['activebiz'] ? 'active' : 'INACTIVE';
			$bizowners = array();
			if($owners[$row['bizid']])
				foreach($owners[$row['bizid']] as $owner)
					$bizowners[] = FALSE && $businessesOnly ? emailLink($owner) : ownerLink($owner);
			$row['owners'] = join(', ', $bizowners);
			$row['db'] = dbLink($row);
			$rowClass =	
				$biz['activebiz'] 
					? (strpos($rowClass, 'EVEN') ? 'futuretask' : 'futuretaskEVEN')
					: (strpos($rowClass, 'EVEN') ? 'canceledtask' : 'canceledtaskEVEN');
			$rowClasses[] =	$rowClass;

			$rows[] = $row;
		}
		$columns = explodePairsLine("bizid|Biz ID||state| ||bizname|Business Name||db|DB Name||owners|Owners");
		tableFrom($columns, $rows, "width=100%", $class='biztable', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);
	}
	exit;
}

function searchBizLink($name, $id) {
	$businessesOnly = FALSE && in_array('b', getMaintRights());
	if($businessesOnly) return $name;
	return fauxLink($name, "document.location.href=\"maint-edit-biz.php?id=$id\"", 1);
}

function dbLink($biz) {
	if(!mattOnlyTEST()) return $biz['db'];
	return "<a href='http://leashtime.com/eegah/index.php?db={$biz['db']}&lang=en-utf-' target=MYSQLDB>{$biz['db']}</a>";
}

function loginLink($id) {
	return "<img src='art/branch.gif' onclick='stafflogin($id)'> ";
}

function ownerLink($owner) {
	$prefix = strpos($owner['rights'], 'd-') === 0 ? '[D]' : '';
	$label = $prefix.$owner['loginid'];
	$label = $owner['isowner'] == 1 ? "<b>$label</b>" : $label;
	//$label .= " ".print_r($owner,1);
	return fauxLink($label, 
									"openConsoleWindow(\"logineditor\", \"maint-edit-user.php?userid={$owner['userid']}\", 600,400)", 
									1,
									"{$owner['fname']} {$owner['lname']} [{$owner['email']}]");
}

function emailLink($owner) {
	$prefix = strpos($owner['rights'], 'd-') === 0 ? '[D]' : '';
	return "<span title='".safeValue("{$owner['fname']}{$owner['lname']} [{$owner['email']}]")."]'>$prefix{$owner['loginid']}</span>";
}

function getMaintRights() {
	$rights = $_SESSION['rights'];
	return explode(',', (strlen($rights) > 2 ? substr($rights, 2) : ''));
	
}
?>
<style>
.biztable td {padding-left:10px;}
</style>
<form>
Search: <input id='pat' onkeyup="search(this)" onload='focus()'> Enter the partial name of a business or database, or the name, loginid, or email of a manager or dispatcher.
</form>
<div id='resultsDiv'></div>
<!-- script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script -->
<script language='javascript'>
function search(el) {
	var pat = el.value;
	if(!pat || pat.length < 2) return;
	$.ajax({url: 'maint-biz-search.php?pat='+pat, success: function(data) {$('#resultsDiv').html(data);}});
}
//$.ready(function() {$('#pat').focus();});
//document.getElementById('pat').focus();
//document.forms[0].pat.focus();
$('#cboxWrapper').click(function() {$('#pat').focus();});
//$('#pat').filter(':parent').click(function() {$('#pat').focus();});

</script>
