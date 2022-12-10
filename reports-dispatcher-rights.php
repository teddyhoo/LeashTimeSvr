<? // reports-dispatcher-rights.php

require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');

$dispatchers = fetchAssociations(
	"SELECT * 
	 FROM tbluser 
	 WHERE active 
	  AND bizptr = '{$_SESSION["bizptr"]}' 
	 	AND (rights LIKE 'd-%' OR rights LIKE 'o-%')
	 	AND ltstaffuserid=0
	 ORDER BY lname, fname");
$columns['right'] = '';
$roles = explodePairsLine('o|Manager||d|Dispatcher');
foreach($dispatchers as $d) {
	$role = $roles[$d['rights'][0]];
	$style = $d['active'] ? '' : "style='color:red;";
	$columns[$d['userid']] = "<span title='($role) {$d['loginid']}' $style>({$role[0]}) {$d['fname']} {$d['lname']}</span>";
}

$allRights = fetchAssociationsKeyedBy("SELECT * FROM tblrights ORDER BY sequence", 'key');
$ccRights = explode(',','*cc,*cm');
foreach($allRights as $key => $right) {
	$row = array('right'=>"<span class='label' title='{$right['description']}'>{$right['label']}</span>");
	foreach($dispatchers as $d) {
		$rights = explode(',', substr($d['rights'], 2));
		$role = $d['rights'][0];
		
		$canDo = $role == 'o' ?
			(in_array($key, $ccRights) ? in_array($key, $rights) : true) 
			: in_array($key, $rights);
		$row[$d['userid']] = $canDo ? "<span class='yes'>yes</span>" : 'no';
	}
	$rows[] = $row;
	$rowClasses[] = ($rowClass = $rowClass == 'futuretask' ? 'futuretaskEVEN' : 'futuretask');
}
		
$pageTitle = "Dispatcher Rights";


if(!$print && !$csv) {
	$breadcrumbs = "<a href='reports.php'>Reports</a>";	
	$layout = $_SESSION['frameLayout'];
	$_SESSION['frameLayout'] = 'fullScreenTabletView';
	unset($_SESSION['bannerLogo']);
	include "frame.html";
	$_SESSION['frameLayout'] = $layout;
	unset($_SESSION['bannerLogo']);
}
?>
<style>
.label {font-weight:bold;}
.yes {color:green;font-weight:bold;}
.no {}
</style>
<span class='tiplooks'>Hover over row and column headers for descriptions and login ids</span>
<?
tableFrom($columns, $rows, "width='100%' border=1 bordercolor=black", $class=null, $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses, 'sortClick');



if(!$print && !$csv) {
	include "frame-end.html";
}

