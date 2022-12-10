<? // value-pack-standard-list.php
$pageTitle = "Value Packs";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "value-pack-fns.php";
locked('o-');

$max_rows = 999;

if(!$_SESSION['preferences']['enableValuePacks']) {
	echo "Value Packs feature not enabled";
	exit;
}

extract(extractVars('edit,add,delete', $_REQUEST));
setupValuePackTable();

function dumpEditForm($vpid, $pack) {
	$packs = getStandardValuePacks();
	foreach($packs as $k=>$v) {
		$labels[$k] = $v['label'];
	}
	if($vpid) unset($labels[$vpid]);	
	$labels = $labels ? urlencode(json_encode(array_values($labels))) : null;
//print_r($pack);	
	require "frame-bannerless.php";
	echo "<h2>Value Pack Template</h2>";
	echo "<form name='standardpackeditor' method='POST'>";
	echo "<table>";
	echoButton('', "Save", "checkAndSubmit()");
	inputRow('Label: ', 'label', $pack['label'], $labelClass=null, $inputClass='input300');
	inputRow('Number of Tokens: ', 'visits', $pack['visits']);
	inputRow('Price: ', 'price', $pack['price']);
	inputRow('Refill notication: ', 'refill', $pack['refill']);
	inputRow('Expires after days: ', 'duration', $pack['duration']);
	textRow('Notes', 'notes', $pack['notes'], $rows=6, $cols=60);
	hiddenElement('packid', ($vpid ? $vpid : ''));
	hiddenElement('servicetypes', $pack['servicetypes']);
	hiddenElement('save', '');
	echo "</table>";

	$sTypes = fetchAssociationsKeyedBy(
			"SELECT label, servicetypeid, active
				FROM tblservicetype
				ORDER BY active DESC, label", 'label', 1);
				
	$vpServiceTypes = valuePackServiceTypes();
				
	$serviceTypeOptions = array('-- Add a Service Type --' => 0);
	$wasActive = 1;
	$allServiceTypes = array();
	foreach($sTypes as $label => $type) {
		$allServiceTypes[$type['servicetypeid']] = $type['label'];
		// exclude any service type already associated with a vp template
		if(!$type['active'] && $wasActive) {
			$serviceTypeOptions['--- Inactive Service Types ---'] = -1;
			$wasActive = 0;
		}
		if($vpServiceTypes[$type['servicetypeid']] && $vpServiceTypes[$type['servicetypeid']] != $vpid) {
			// NO GO $serviceTypeOptions["<span style=\"color:red;font-style:italic;\">$label</span>"] = -1;
		}
		else $serviceTypeOptions[$label] = $type['servicetypeid'];
	}
	
	foreach($allServiceTypes as $id=>$label)
		$allServiceTypesJS[] = "$id:\"".str_replace('"', '&quot;', safeValue($label))."\"";
	$allServiceTypesJS = "{".join(',', (array)$allServiceTypesJS)."}";
	
	//selectElement($label, $name, $value=null, $options=null, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=false, $optExtras=null, $title=null) 

	$serviceSelect = selectElement("", 'allServiceTypes', $value=null, $serviceTypeOptions, $onChange='addServiceType(this)', null, null, $noEcho=true);
	echo "<tr><td style='padding-top:20px;' colspan=2>Tokens may be applied only to the services below, if any are specified:</td></tr>";
	echo "<tr><td colspan=2 title='$title'>$serviceSelect<br><span id='servicetypesHTML'>$serviceTypeHTML</span></td></tr>";


	echo "</form>";
	$test = "decodeURIComponent('".$labels."')";
	$labels = $labels == null ? 'new Array()' : "JSON.parse(decodeURIComponent('".$labels."'.replace(/\+/g, ' ')))";
	echo <<<JS
	<script language='javascript' src='check-form.js'></script>
	<script language='javascript'>
	var usedLabels = $labels;
	var allServiceTypes = $allServiceTypesJS;
	function checkAndSubmit() {
		var dupLabel = '';
		for(var i=0; i<usedLabels.length; i++) 
			if(document.getElementById('label').value == usedLabels[i])
				dupLabel = document.getElementById('label').value+' is already in use as a label.';
		if(MM_validateForm('label', '', 'R',
												dupLabel, '', 'MESSAGE',
												'visits', '', 'R',
												'visits', '', 'UNSIGNEDINT',
												'refill', '', 'UNSIGNEDINT',
												'price', '', 'R',
												'price', '', 'UNSIGNEDFLOAT',
												'duration', '', 'UNSIGNEDINT')) {
				document.getElementById('save').value = 1;
				document.standardpackeditor.submit();
			}
	}
	
function dropServiceType(doomedId) {
	var ids = document.getElementById('servicetypes').value.split(',');
	var newIds = new Array();
	for(var i=0; i<ids.length; i++) {
		if(ids[i] != doomedId) newIds[newIds.length] = ids[i];
	}
	document.getElementById('servicetypes').value = newIds.join(',');
	updateServiceTypes(document.getElementById('allServiceTypes'));
}


function addServiceType(sel) {
	var choiceId = sel.options[sel.selectedIndex].value;
	sel.options[sel.selectedIndex].selected = false;
	sel.selectedIndex = 0;
	if(choiceId <= 0) return;
	var chosen = document.getElementById('servicetypes').value;
	var chosenIds = chosen.split(',');
	for(var i=0; i<chosenIds.length; i++)
		if(chosenIds[i] == choiceId) return;
	// otherwise rebuild list with choiceId included
	var newChosenIds = new Array();
	for(var i=0; i<sel.options.length; i++) {
		var optVal = sel.options[i].value;
		if(optVal <= 0) continue;
		else if(optVal == choiceId) newChosenIds[newChosenIds.length] = choiceId;
		else for(var j=0; j<chosenIds.length; j++) 
					if(optVal == chosenIds[j]) 
						newChosenIds[newChosenIds.length] = chosenIds[j];
	}
	document.getElementById('servicetypes').value = newChosenIds.join(',');
	updateServiceTypes(sel);
}

function updateServiceTypes(sel) {
	var ids = document.getElementById('servicetypes').value.split(',');
	if(ids.length == 1 && ids[0] == '') {
		document.getElementById('servicetypesHTML').innerHTML = '<i>None selected</i>';
		return;
	}
	var out = new Array(); //, labels = {};
	/*for(var i=0; i<sel.options.length; i++) 
		if(sel.options[i].value > 0) 
			labels[sel.options[i].value] = sel.options[i].label;*/
	for(var i=0; i<ids.length; i++) {
		if(ids[i] == '') continue;
		var redX = "<span title='Remove' style='color:red;cursor:pointer;' onclick='dropServiceType("+ids[i]+")'>X</span>";
		out[out.length] = redX+" "+allServiceTypes[ids[i]];
	}
	document.getElementById('servicetypesHTML').innerHTML = out.join('<br>');
}
	
if(document.getElementById('allServiceTypes')) updateServiceTypes(document.getElementById('allServiceTypes'));
	
</script>
JS;
}
if($_REQUEST['save']) {
	foreach(explode(',', 'label,visits,refill,price,duration,notes,servicetypes') as $k) {
		$v = $_REQUEST[$k];
		if(!$v && in_array($k, explode(',','refill,duration'))) $v = '0';
		$pack[$k] = $v;
	}
	if($packid = $_REQUEST['packid']) {
//print_r($_REQUEST);exit;
		updateTable('tblvaluepack', $pack, "vpid=$packid", 1);
		$message = 'Value Pack changes saved.';
	}
	else {
		insertTable('tblvaluepack', $pack, 1);
		$message = 'Value Pack Added.';
	}
	echo "<script language='javascript'>window.parent.refresh();</script>";
	exit;
}
else if($add || $edit) {
	if($edit) {
		$pack = getValuePack($edit);
	}
	else $pack = array();
//echo "{$_REQUEST['edit']}<br>".print_r($pack, 1);
	dumpEditForm($edit, $pack);
	exit;
}
else if($_REQUEST['delete']) {
	deleteTable('tblvaluepack', "vpid = '$delete'", 1);
	globalRedirect('value-pack-standard-list.php?message=Value+Pack+Deleted');
}
if($sort) {
  $sort_key = substr($sort, 0, strpos($sort, '_'));
  $sort_dir = substr($sort, strpos($sort, '_')+1);
  $sort = "$sort_key $sort_dir";
  if($sort_key != 'label') $sort .= ", label ASC";
}
//$breadcrumbs = "&nbsp;<a href='discounted-visits.php'>Discounted Visits</a>";
// ***************************************************************************
include "frame.html";
if($message) echo "<span class='tiplooks'>$message</span><p>";
//tableFrom($columns, $data=null, $attributes=null, $class=null, $headerClass=null, $headerRowClass=null, $dataCellClass=null, $columnSorts=null) {
echoButton('', "Add New Value Pack", "editValuePack(null)");

$columns = array('label'=>'Package', 'visits'=>'# Visits', 'refill'=>'Refill', 'price'=>'Price', 'duration'=>'Duration (days)', 'notes'=>'Note');
$colKeys = array_keys($columns);
$columnSorts = null;
$standardPacks = getStandardValuePacks();
$data = array();
$rowClasses = array();
//print_r($standardPacks);
if($standardPacks) foreach($standardPacks as $id => $pack) {
	//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)
	$pack['label'] = 
		"".fauxLink("<span class='warning'><b>X</b></span>", "deleteValuePack(\"$id\")", 1, 'Delete this Standard Value Pack.', null, 'warning')
		.' '.fauxLink($pack['label'], "editValuePack(\"$id\")", 1, 'Edit this Standard Value Pack', "label_{$pack['json']}");
  $data[] = $pack;
	$rowClasses[] = 'futuretask';
}
if(!$data) echo "<p>No Standard Value Pack templates found.";
else tableFrom($columns, $data, 'WIDTH=100%', null, null, null, null, $columnSorts, $rowClasses);


include "refresh.inc";				

?>
<script language='javascript' src='common.js'></script>
<script language='javascript'>

function editValuePack(id) {
	var url = "value-pack-standard-list.php";
	var arg = id == null ? '?add=1' : "?edit="+id;
	$.fn.colorbox({href:url+arg, iframe: "TRUE", width:500, height:500, scrolling: true, opacity: "0.3"});
}

function deleteValuePack(id) {
	if(confirm('Delete this Value Pack template?'))
		document.location.href='?delete='+id;
}

function update(aspect, value) {
	document.location.href='?';//refresh();
}

</script>
<p><img src='art/spacer.gif' height=300>
<?
include "frame-end.html";
