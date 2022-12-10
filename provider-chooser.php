<? //provider-chooser.php
// Used in wag.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require "provider-fns.php";


// Determine access privs
$locked = locked('+o-,+d-,#as');

$uproviders = array(0=>'<b>Unassigned</b>');
$providers = getProviderShortNames($filter='WHERE active = 1');
asort($providers);
foreach($providers as $k=>$v) $uproviders[$k] = $v;
$providers = $uproviders;
$provids = strlen($_REQUEST['providerids']) == 0 ? array() : explode(',', (string)$_REQUEST['providerids']);

$perColumn = (int)(count($providers) / 4) + (count($providers) % 4 ? 1 : 0);


echoButton('', 'Choose Selected Sitters', 'checkAndSubmit()');
echo "<img src='art/spacer.gif' width=30 height=1>";
fauxLink('Select All', 'checkAllProviders(true)');
echo "<img src='art/spacer.gif' width=20 height=1>";
fauxLink('Un-Select All', 'checkAllProviders(false)');
echo "<img src='art/spacer.gif' width=30 height=1>";
echoButton('', "Quit", 'quit()');
?>
<link rel="stylesheet" href="style.css" type="text/css" /> 
<link rel="stylesheet" href="pet.css" type="text/css" /> 
<form name='sitters' method='POST'>
<p>
<table>
<tr>
<?
for($i=0; $i<4; $i++) {
	$slice = array_slice($providers , $i*$perColumn, $perColumn, $preserve_keys = true);
	if($slice) {
		echo "<td style='vertical-align:top'>";
		foreach($slice as $id => $name) {
			labeledCheckbox($name, "prov_$id", in_array($id, $provids), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
			echo "<br>";
		}
		echo "<td>";
	}
}
?>
</tr>
</table>
<script language='javascript'>
function checkAllProviders(state) {
	var el = document.sitters.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('prov_') > -1) el[i].checked = (state ? true : false);
	updatePayroll();
}

function checkAndSubmit() {
	var providers = new Array();
	var el = document.sitters.elements;
	for(var i=0;i<el.length;i++)
	  if(el[i].name.indexOf('prov_') > -1 &&  el[i].checked)
	  	providers[providers.length] = el[i].id.substring('prov_'.length);
	providers = providers.join(',', providers);
	parent.update('providers', providers);
	quit();
}

function quit() {
	if(parent && parent.$)
		parent.$.fn.colorbox.close();
}

</script>
