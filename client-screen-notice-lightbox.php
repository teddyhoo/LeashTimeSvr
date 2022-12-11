<?
// client-screen-notice-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
require_once "screen-notice-fns.php";

locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

$property = $_REQUEST['prop'];

//echo "[[[".print_r($_REQUEST,1);exit;		
if($_POST) {
	if($_POST['action'] == 'drop') {setPreference($property, null);setPreference("{$property}ABBREV", null);}
	else {
		$message = screenNoticeWhiteList("{$_POST['message']}", $restore=false);
		$message = trim(
								str_replace("\n", "<br>", 
								str_replace("\n\n", 	"<p>", 
								str_replace("\r", "", strip_tags(screenNoticeWhiteList($message))))));
	
		$message = screenNoticeWhiteList($message, $restore=true);
		
		$title = screenNoticeWhiteList("{$_POST['title']}", $restore=false);
		$title = trim(str_replace("\n", "<br>", str_replace("\n\n", 	"<p>", str_replace("\r", "", strip_tags($title)))));
		$title = screenNoticeWhiteList($title, $restore=true);
		
		$message = trim(str_replace("\"", "&quot;" , $message));
		
		$first = $_POST['first'] ? trim($_POST['first']) : "";
		$first = strtotime($first) ? date('Y-m-d', strtotime($first)) : '';
		$last = $_POST['last'] ? trim($_POST['last']) : "";
		$last = strtotime($last) ? date('Y-m-d', strtotime($last)) : '';
		
		foreach(explode(',', 'headerSize,bodySize') as $k)
			$props[] = $_POST[$k];
		if($_POST['headerAlign'] == 'center') $props[] = 'CENTERHEADER';
		if($_POST['bodyAlign'] == 'center') $props[] = 'CENTERBODY';
		$value = array('title'=>$title, 'message'=>$message, 'first'=>$first, 'last'=>$last, 'props'=>$props);
}
		if($_POST['action'] == 'showpreview') {
			echo composeMessage($message, $title, $props);
			exit;
		}
		else setPreference($property, json_encode($value));
		
		$atitle = trim(replaceEOLsWithSpaces($title));
		$atitle = $value['title'] ? truncatedLabel($value['title'], 40) : '';
		$amsg = truncatedLabel($message, ($atitle ? (70 - strlen($atitle)) : 90));
		$abbrev = ($atitle ? "$atitle: " : '').$amsg;
		setPreference("{$property}ABBREV",$abbrev);
		
	}
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('$property', '');parent.$.fn.colorbox.close();</script>";
}
$extraHeadContent = 
'<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="ajax_fns.js"></script>
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
';
include "frame-bannerless.php";

if($_REQUEST['narrow']) 
	$helpStyle = "float: right; padding: 10px;  padding-top: 0px; background:lightblue; width:200px;height:250px;";
	//$helpStyle = "padding: 10px;  padding-top: 0px; background:lightblue;";
else 
	$helpStyle = "float: right; padding: 10px;  padding-top: 0px; background:lightblue; width:300px;height:250px;";
?>

<h2><?= $_REQUEST['label'] ?></h2>
<form method='POST' name='msgprops'>
<div style="<?= $helpStyle ?>">
<h3>No HTML Tags Allowed!</h3> <!-- <sup>*</sup> -->
Use the settings in this form to set the size and alignment of your message and its (optional) title.
<p>Use a single end of line instead of &lt;br&gt; and two line ends instead of &lt;p&gt;
<p>Click the little "Alignment" images to toggle between "left justified" (<img src='art/align-left.png' width=15>) and "centered" (<img src='art/align-center.png' width=15>).
<p>Click the preview button to see what the message will look like when it is "live".
<!-- p><sup>*</sup> Except for &lt;hr&gt; which will insert a horizontal line in the message. -->
</div>
<table>
<?
$notice = getPreference($property);
//echo $notice;
if($notice) {
//echo $notice;	
	$notice = json_decode($notice, 'assoc');
	if($notice['props']) { // 
		//HEADER1,HEADER2,HEADER3,CENTERHEADER,BODY1,BODY2,BODY3,BODY4,BODY5,BODY6,
		//BODY7,BODY8,BODY9,BODY10,CENTERBODY
		$props = $notice['props'];
		$headerAlign = in_array('CENTERHEADER', $props) ? 'center' : 'left';
		$bodyAlign = in_array('CENTERBODY', $props) ? 'center' : 'left';
		for($i = 1; $i <= 3; $i++) if(in_array("HEADER$i", $props)) $headerSize = "HEADER$i";
		for($i = 1; $i <= 10; $i++) if(in_array("BODY$i", $props)) $bodySize = "BODY$i";
	}
	$first = strtotime($notice['first']) ? shortDate(strtotime($notice['first'])) : '';
	$last = strtotime($notice['last']) ? shortDate(strtotime($notice['last'])) : '';

}


$headerAlign = $headerAlign ? $headerAlign : 'left';
$bodyAlign = $bodyAlign ? $bodyAlign : 'left';
$headerSize = $headerSize ? $headerSize : 'HEADER2';
$bodySize = $bodySize ? $bodySize : 'BODY2';
$message = str_replace("<br>", "\n", str_replace("<p>", "\n\n", str_replace("\r", "", $notice['message'])));
hiddenElement('prop', $property);
hiddenElement('action', '');
hiddenElement('headerAlign', $headerAlign);
hiddenElement('bodyAlign', $bodyAlign);

//hiddenElement('headerSize', $headerSize);
//hiddenElement('bodySize', $bodySize);

echo "<tr><td colspan=2>";
calendarSet('Show notice starting:', 'first', $first, null, null, true, 'last');
echo "<br>";
calendarSet('and ending on:', 'last', $last);
echo "</td></tr>";

inputRow('Title', 'title', $value=$notice['title'], $labelClass=null, $inputClass='Input45Chars', $rowId=null,  $rowStyle=null, $onBlur=null, $extraContent=null, $inputCellPrepend=null);
$options = explodePairsLine('LARGE|HEADER1||Medium|HEADER2||Small|HEADER3');
$headerFontSizeSelect = selectElement('Title font size: ', 'headerSize', $value=$headerSize, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title=null);
echo "<tr><td colspan=2>Align title: <img id='headerAlignEl' width=13 target='headerAlign' onclick='toggleImage(this)'> $headerFontSizeSelect</td></tr>";

textRow('Message', 'message', $value=$message, $rows=10, $cols=60, $labelClass=null, $inputClass=null, $rowId=null, $rowStyle=null, $maxlength=null, $rowClass=null, $textColSpan=2);
$options = explodePairsLine('small|0');
for($i=1; $i<= 10; $i++) $options[($i*10).'% larger'] = "BODY$i";
$bodyFontSizeSelect = selectElement('Message font size: ', 'bodySize', $value=$bodySize, $options, $onChange=null, $labelClass=null, $inputClass=null, $noEcho=true, $optExtras=null, $title=null);
echo "<tr><td colspan=2>Align message: <img id='bodyAlignEl' width=13 target='bodyAlign' onclick='toggleImage(this)'> $bodyFontSizeSelect</td></tr>";

?>
</table>
<p>
<?
echoButton('', 'Preview', 'preview()');
echo ' ';
echoButton('', 'Save Announcement', 'save()');
echo ' ';
echoButton('', 'Drop Announcement', 'drop()');
echo ' ';
echoButton('', 'Quit', 'parent.$.fn.colorbox.close()');
?>
<p>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript'>

function toggleImage(el) {
	var target = document.getElementById(el.getAttribute('target'));
	target.value = target.value == 'center' ? 'left' : 'center';
	el.title = 'Align to the '+target.value+'.  Click to change.';
	setSrc(el);
}

function setSrc(elOrName) {
	var el = typeof elOrName == 'string' ? document.getElementById(elOrName) : elOrName;
	var target = document.getElementById(el.getAttribute('target'));
//alert(target.value);
	if(target.value.indexOf('center') == 0) el.src = 'art/align-center.png';
	else el.src = 'art/align-left.png';
}

setSrc('headerAlignEl');
setSrc('bodyAlignEl');

function drop() {
	document.getElementById('action').value='drop';
	document.msgprops.submit();
}

setPrettynames('first','Starting date', 'last', 'Ending date', 'message', 'Message');

function formIsCorrect() {
	return MM_validateForm(
			'first', '', 'isDate',
		  'last', '', 'isDate',
		  'first', 'last', 'datesInOrder',
		  'message', '', 'R');
}
function preview() {
	if(formIsCorrect()) {
		document.getElementById('action').value = "showpreview";
		var params = {};
		params.action = "showpreview";
		params.first = document.getElementById('first').value;
		params.last = document.getElementById('last').value;
		params.title = document.getElementById('title').value;
		params.headerAlign = document.getElementById('headerAlign').value;
		params.bodyAlign = document.getElementById('bodyAlign').value;
		params.headerSize = document.getElementById('headerSize').value;
		params.bodySize = document.getElementById('bodySize').value;
		params.message = document.getElementById('message').value;
		var url = '<?= globalURL('client-screen-notice-lightbox.php') ?>';
		submitAJAXFormAndCallWith(formArgumentsFromObject(params), url, openPreview, null);
		document.getElementById('action').value = "";
	}
}

function openPreview(arg, responseHTML) {
	$.fn.colorbox({html:responseHTML, width:"730", height:"470", scrolling: true, opacity: "0.3"});
}
	
function save(drop) {
	if(formIsCorrect())
		document.msgprops.submit();
	if(document.getElementById('message').value.trim() == '')
		alert("If you want to discontinue the message,\nclick the [Drop Announcement] button.");
}

<?
dumpPopCalendarJS();
?>
</script>