<? // maint-prefs.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "js-gui-fns.php";

$locked = locked('z-');
extract(extractVars('bizdb,prop,val,new,delete', $_REQUEST));

if($bizdb && $delete && $prop) {
	deleteTable("$bizdb.tblpreference", "property = '$prop'", 1);
	echo "REFRESHNOW";
	exit;
}
else if($bizdb && !$delete && ($prop || $new)) {
	$success = null;
	if($new) {
		insertTable("$bizdb.tblpreference", array('property'=>$new, 'value'=>$val), "property = '$new'", 1);
		$success = !mysqli_error();
	}
	else $success = updateTable("$bizdb.tblpreference", array('value'=>$val), "property = '$prop'", 1);
	if($success) {
		if($new) echo "REFRESHNOW";
		else echo "Yes";
	}
	else echo "???";
	exit;
}

$dbs = fetchKeyValuePairs("SELECT db, db FROM tblpetbiz ORDER BY db");
$dbs = array_merge(array('Pick a biz'=>''), $dbs);
$orderBy = !$sorts ? "ORDER BY time DESC" : "ORDER BY ".str_replace('_', ' ', $sort);
$filter = array();
if($bizdb) {
	$result = doQuery("SELECT property, value FROM $bizdb.tblpreference");
	$bizid = fetchRow0Col0("SELECT bizid FROM tblpetbiz WHERE db = '$bizdb'", 1);
}

$windowTitle = 'Pet Business Preferences';
include 'frame-maintenance.php';
if($bizdb) echo "<h2>$bizdb ".loginAndEditElements($bizid)." Preferences</h2>";
?>
<style>
.biztable td {padding-left:10px;}
</style>

<?
selectElement('Business:', 'bizdb', $bizdb, $dbs, "document.location.href=\"maint-prefs.php?bizdb=\"+document.getElementById(\"bizdb\").options[document.getElementById(\"bizdb\").selectedIndex].value");
?>
<br><input type='button' value='New' onclick='newProp()'> Property: <input id='new' value='' size=30> Value: <input id='newval' value='' size=30><p>
<?
echo "<p><table>";
if(($result)) while($line = mysqli_fetch_assoc($result))
	echo "<tr><td>{$line['property']}: <td><input id='{$line['property']}' value='".safeValue($line['value'])."' size=30><td><input type='button' onClick='save(\"{$line['property']}\")' value='Save'> <input type='button' onClick='drop(\"{$line['property']}\")' value='Drop'>";
echo "</table>";
include "refresh.inc";
?>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript'>
function save(id) {
	var val = escape(document.getElementById(id).value);
	ajaxGetAndCallWith('maint-prefs.php?bizdb=<?= $bizdb ?>&prop='+id+'&val='+val, ok, id);
}

function drop(id) {
	if(!confirm("Delete "+id+"?")) return;
	var val = escape(document.getElementById(id).value);
	ajaxGetAndCallWith('maint-prefs.php?delete=1&bizdb=<?= $bizdb ?>&prop='+id, ok, 0-id);
}

function newProp() {
	var prop = escape(document.getElementById('new').value);
	var val = escape(document.getElementById('newval').value);
	if(!prop) {alert('Specify a property name');return;}
	ajaxGetAndCallWith('maint-prefs.php?bizdb=<?= $bizdb ?>&new='+prop+'&val='+val, ok, prop)
}

function ok(arg, result) {
	if(result.indexOf('REFRESHNOW') != -1) refresh();
	else alert(arg+' saved: '+result);
	}
</script>
