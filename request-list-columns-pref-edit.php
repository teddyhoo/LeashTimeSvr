<? // request-list-columns-pref-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require "preference-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out

// Verify login information here
locked('o-');
$failure = false;

if($failure) {
	$windowTitle = 'Insufficient Access Rights';
	require "frame-bannerless.php";	
	echo "<h2>$windowTitle</h2>";
	exit;
}
//		$allColumns = explodePairsLine("clientname|Client||requesttype|Request||date|Date||
// address|Address||phone|Phone||
// note|Request Note||officenotes|Office Note||condnote|Note");

extract(extractVars('address,phone,note,officenotes,condnote', $_REQUEST));
if($_POST) {
print_r($_POST);
	
	$cols = array("clientname|requesttype|date");
	foreach(explode(',', 'address,phone') as $k)
		if($_POST[$k]) $cols[] = $k;
	foreach(explode(',', 'note,officenotes,condnote') as $v)
		if($_POST['note'] == $v) $cols[] = $v;
	setPreference('requestlistcolumns', join('|', $cols));
	echo "<script language='javascript'>
	if(window.opener && window.opener.updateProperty) 
		window.opener.updateProperty('requestlistcolumns', 1);
	window.close();</script>";
	exit;
}

$prefs = fetchPreferences();

}


$windowTitle = 'Request List Columns';
require "frame-bannerless.php";
echo "<h2>$windowTitle</h2>\n";
?>
<style>
table td {padding-right: 5px;vertical-align:top;}
.toppad {padding-top:6px;}</style>
<?
echoButton('', 'Save', 'document.prefeditor.submit()');

if(!($requestlistcolumns = $prefs['requestlistcolumns']))
	$requestlistcolumns = 'clientname|requesttype|date|address|phone';
$requestlistcolumns = explode('|', $requestlistcolumns);
echo "\n<form method='POST' name='prefeditor'>\n";
echo "\n<p>Include the following columns in the Home Page Request List<br>";
//print_r($prefs['requestlistcolumns']);
echo "<table border=1 bordercolor='gray'><tr>";

echo "\n<td class='toppad'>Client Name<br>(required)";
echo "\n<td class='toppad'>Request<br>(required)";
echo "\n<td class='toppad'>Date<br>(required)<td>";
labeledCheckbox('Address', 'address', in_array('address', $requestlistcolumns), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
echo "<td>";
labeledCheckbox('Phone', 'phone', in_array('phone', $requestlistcolumns), $labelClass=null, $inputClass=null, $onClick=null, $boxFirst=true, $noEcho=false, $title=null);
echo "<td class='toppad'>";
echo "\nNote:<br>";
$options = explodePairsLine('none|0||Request Note|note||Office Notes|officenotes||Office Notes, or <br>Request Note if no Office Notes|condnote');
foreach(explode(',', 'note,officenotes,condnote') as $k)
	if(in_array($k, $requestlistcolumns)) $notevalue = $k;
$radios = radioButtonSet('note', $notevalue, $options, $onClick=null, $labelClass=null, $inputClass=null, $rawLabel=false);
foreach($radios as $radio) echo "\n- $radio<br>";
echo "</td></tr></table>";
echo "</form>";
?>
