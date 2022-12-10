<? //viewVet-mobile-public.php
require "common/init_session.php";
require "common/init_db_common.php";
//require_once "client-fns.php";
//require_once "vet-fns.php";
require_once "gui-fns.php";

extract($_REQUEST);

$object = fetchFirstAssoc("SELECT * FROM vetclinic_us WHERE clinicid = $id LIMIT 1");
$name = $object['clinicname'];
$pageIsPrivate = false;	
$noOptions = true;
$homeLink = 'mobile-nearby-vets-public.php';

require_once "mobile-frame.php";
$leashtimeLink = "<a href='https://{$_SERVER["HTTP_HOST"]}'><img src='art/LeashTimeTM127x31.jpg' style='border:0px;padding-bottom:5px;'></a>";
echo "<div style='position:absolute; left:150; top:5; font-size:1.5em;'>$leashtimeLink<br>Find a Vet</div>";
echo "	
<style>
.topline td {font-size: 1.08em;font-weight:bold;}
.smaller {font-size: 0.8em;}
td {vertical-align:top;}
.labelcell {
  font-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
  background: #FF8B00;
  color:black;
  font-weight: bold;
}
.dataCell {
  font-size: 1.08em; 
  padding-bottom: 4px; 
  border-collapse: collapse;
  vertical-align: top;
}
.jobstable {background: white;color:black;}
.jobstablecell {
		padding-bottom: 4px; 
		border-collapse: collapse;
		vertical-align: top;
		border-top: solid black 1px;
	}
.sortableListHeader {
}
.sortableListCell {
		font-size: 0.85em; 
}
.dateRow {background: yellow;font-weight:bold;text-align:center;border:solid black 1px;}

.noHpadding td {
	padding-left: 0px;
	padding-right: 0px;
}
.flagLegend {}
</style>
";

$addr = array();
foreach(array('street1','street2', 'city', 'state', 'zip') as $k) $addr[] = $object[$k];
$oneLineAddr = oneLineAddress($addr);
$addr = htmlFormattedAddress($addr);
$email = !trim($object['email']) ? '' : "<a href='mailto:{$object['email']}'>{$object['email']}</a>";

?>

<table width=100% border=0 bordercolor=red><tr>
<td class='topline' colspan=1><b><?= $name ?></b></td>
<td style='text-align:right'><?= addressLink('Map', trim($oneLineAddr)) ?></td>
</tr>
<tr><td colspan=2>
<table width=100% cellspacing=0 border=0 bordercolor=red>
<? 
if($object['lname']) 
	oneByTwoLabelRows('', ($object['fname'] ? "{$object['fname']} " : '')
												.$object['lname']
												.($object['creds'] ? " ({$object['creds']})" : ''));
$phones = explodePairsLine('officephone|Office||cellphone|Cell||pager|Pager||homephone|Home');
$safeVetName = safeValue($name);
foreach($phones as $phone => $label) {
	if(!trim($object[$phone])) continue;
	$phoneLink = fauxLink($object[$phone], "openCallBox(\"$safeVetName ($label)\", \"{$object[$phone]}\")", 1, 1);
	oneByTwoLabelRows($label.':', '', $phoneLink, 'labelcell','dataCell','','','raw');
}
if(trim($oneLineAddr)) oneByTwoLabelRows('Address:', '', $addr, 'labelcell','dataCell','','','raw');
if($email) oneByTwoLabelRows('Email:', '', $email, 'labelcell','dataCell','','','raw');
if(trim($object['fax'])) oneByTwoLabelRows('Fax:', '', $object['fax'], 'labelcell','dataCell');

if($object['category']) $object['services'][] = $object['category'];
if($object['subcategory']) $object['services'][] = $object['subcategory'];
$object['services'] = $object['services'] ? join('<br>', $object['services']) : '';
if($object['url']) $object['url'] = fauxLink($object['url'], "document.location.href=\"{$object['url']}\"", 1);
$texts = explodePairsLine('notes|Notes||services|Services||url|Website||afterhours|After Hours||directions|Directions');
foreach($texts as $text => $label) {
	if(!$object[$text]) continue;
	oneByTwoLabelRows($label.':', '', $object[$text], 'labelcell','smaller','','','raw');
}
/*
		else if($label == 'web_meta_title') $clinic['webmetatitle'] = $trimVal;
		else if($label == 'web_meta_desc') $clinic['webmetadescription'] = $trimVal;
		else if($label == 'web_meta_keys') $clinic['webmetakeys'] = $trimVal;
*/
?>
</td></tr>




</table>
</table>
<?
echo "<tr><td colspan=1>";
echo "</td></tr>";




?>
</table></td></tr>
<tr><td>
<?

function addressLink($label, $googleAddress) {
	if(!trim($googleAddress)) return;
	$fulladr = urlencode($googleAddress);
	if(!trim($label)) $label = '';
	else ;//$label = truncatedLabel($label, 24);
	return "<a href='http://maps.google.com/maps?output=mobile&t=m&q=$fulladr'>$label</a>";  //http://mapki.com/wiki/Google_Map_Parameters
}
?>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
<script type="text/javascript" src="jquery.busy.js"></script> 	
<script type="text/javascript">jQuery().busy("defaults", { img: 'art/busy.gif', offset : 0, hide : false });</script> 	
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>
<script type="text/javascript" src="common.js"></script>
<script language='javascript'>
var callBox = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=false, $class=false); ?>";
var callBoxSMS = "<?= telephoneSMSDialogueHTML($name=null, $tel=null, $sms=true, $class=false); ?>";

function changeDay(by) {
	document.location.href='visit-sheet-mobile.php?<?= "noappointments=$noappointments&id=$id&date=$date" ?>&delta='+by;
}

function openCallBox(telname, tel, sms) {
	var box = sms ? callBoxSMS : callBox;
	box = box.replace('#NAME#', telname);
	box = box.replace(/#TEL#/g, tel);
	$.fn.colorbox({	html: box,	width:"280", height:"200", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function showFlagLegend() {
	$.fn.colorbox({	html: "<?= $flagLegend ?>",	width:"280", height:"300", iframe:false, scrolling: "auto", opacity: "0.3"});
}

function goHome(date) {
	document.location.href='https://<?= $_SERVER["HTTP_HOST"] ?>/index.php?date='+escape(date);
}

function withHTMLBreaks($val) {
	//if($_SERVER['REMOTE_ADDR'] != '68.225.89.173') return $val;
	return str_replace("\n\n", "<p>", str_replace("\n", "<br>", cleanseString(trim($val))));
}
</script>