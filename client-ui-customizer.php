<? // client-ui-customizer.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "request-fns.php";
require_once "client-fns.php";
require_once "gui-fns.php";

$locked = locked('o-');//locked('o-'); 
$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');

extract($_REQUEST);

$error = null;

function generateCustomPetOwnerCSS($colors) {
	list($color, $background) = explode('_', $colors);
	ob_start();
	ob_implicit_flush(0);
	
/*	echo <<<CSS
.client-brand {
    background-color: $background !important;
		border-color: $background !important;
		color: $color !important;
}
.white-text {
		color: $color !important;
}
CSS;*/

	echo <<<CSS
.client-brand {
    background-color: $background !important;
		/* Safari 3-4, iOS 1-3.2, Android 1.6- */
		-webkit-border-color: $background !important;
		/* Firefox 1-3.6 */
		-moz-border-color: $background !important;
		/* Opera 10.5, IE 9, Safari 5, Chrome, Firefox 4, iOS 4, Android 2.1+ */
		border-color: $background !important;
		color: $color !important;
}
.white-text {
		color: $color !important;
}
CSS;




	$stylecss = ob_get_contents();
	ob_end_clean();
	$clientuidir = "/var/www/prod/bizfiles/biz_{$_SESSION["bizptr"]}/clientui";
	ensureDirectory($clientuidir);
	file_put_contents("$clientuidir/mobile-style.css", $stylecss);
	//return $css;
}

function ensureDirectory($dir) {
  if(file_exists($dir)) return true;
  ensureDirectory(dirname($dir));
  mkdir($dir);
  chmod($dir, 0775); // group needs x for matt to be able to edit the dir contents
  chgrp($dir, 503 /* www-access */ );
}

if($_REQUEST['colors']) {
	//$colors =  strpos($colors, 'cl_') === 0 ? substr($colors, 3) : $colors;
	$result = generateCustomPetOwnerCSS($colors);
	echo json_encode(array('success'=>'Colors set.'." $result $colors"));
	exit;
}

$noteCols = 80;
if($mobileclient) {
	$noteCols = 40;
	//$extraBodyStyle = 'font-size:1.1em;';
	$customStyles = "
h2 {font-size:2.5em;} 
td {font-size:1.5em;} 
.standardInput {font-size:1.5em;}
.emailInput {font-size:1.5em;}
/*input:radio {font-size:1.5em;} */
label {font-size:1.5em;} 
.mobileLabel {font-size:2.0em;} 
.mobileInput {font-size:2.0em;}
textarea {font-size:1.2em;}
input.Button {font-size:2.0em;}
input.ButtonDown {font-size:2.0em;}
";
}
$pageTitle = "Choose Client Portal Colors";

//$_SESSION['bannerLogo'] = 

$SAVED_BANNER_LOGO = $_SESSION['bannerLogo'];

if($_SESSION) {
	if(isset($_SESSION["bizfiledirectory"])) {
		$headerBizLogo = $_SESSION["bizfiledirectory"];
		if(file_exists($_SESSION["bizfiledirectory"].'logo.jpg')) $headerBizLogo .= 'logo.jpg';
		else if(file_exists($_SESSION["bizfiledirectory"].'logo.gif')) $headerBizLogo .= 'logo.gif';
		else $headerBizLogo = '';
		if($headerBizLogo) {
			$dimensions = getimagesize($headerBizLogo);
			$logoX = $dimensions[0] ? 780 - $dimensions[0] : 511;
			$_SESSION['bannerLogo'] = $headerBizLogo;
		}
		$_SESSION['bannerLogo'] = $headerBizLogo;
	}
	else $_SESSION['bannerLogo'] = null;
}

/*
.client-brand {
    background-color: red !important;
		border-color: red !important;
}
*/


$extraHeadContent = "
<style>
body {font-size:1.2em;} 
.leashtime-content {font-size:1.0em;}
td {font-size:1.0em;}  /* 1.8 /
input.Button {font-size:1.0em;} /* 2.8 /
</style>";
include "frame-client-responsive.html";
$frameEndURL = "frame-client-responsive-end.html";


$onLoadFragments[] = "$('.white-text').attr('href','#');
$('#hamburgeranchor').attr('href','javascript:void(0);');";

// ***************************************************************************

$combos = "
white,#0c7cd5
white,green
white,black
black,white
";
$combos = explode("\n", trim($combos));
$combos = array_map('combo', $combos);
function combo($str) {
	$parts = explode(",", trim($str));
	$colorCombo = "{$parts[0]}_{$parts[1]}";
	$label = count($parts) > 2 ? safeValue($parts[2]) : $colorCombo;
	if(count($parts) > 3) $url = $parts[3];
	return 
		array('label'=>$label, 'color'=>$parts[0], 'background'=>$parts[1], 'colorCombo'=>$colorCombo, 'comboClass'=>str_replace('#', '', "cl_$colorCombo"), 'url'=>$url);
}

// Standard color choices

$standardCombos = "
#FCECED,#69256B,royal
#FCECED,#8534AA,royal-light
#FCECED,#510E6A,royal-dark
#FCECED,#C91026,crimson
#FCECED,#D61128,crimson-light
#FCECED,#AD0D20,crimson-dark
#E1F4AC,#527010,verdant
#E1F4AC,#628713,verdant-light
#E1F4AC,#304209,verdant-dark
#E6EFFC,#3B3BBF,blue
#F4F8FE,#696CD9,blue-light
#E6EFFC,#282880,blue-dark
#FCEEDC,#A85219,brown
#FCEEDC,#D1661F,brown-light
#FCEEDC,#874214,brown-dark
";
$standardCombos = explode("\n", trim($standardCombos));
$standardCombos = array_map('combo', $standardCombos);
//array_push($combos, "<hr><div>Standard high contrast color combos.</div>");
array_push($combos, "<p>");
foreach($standardCombos as $combo) array_push($combos, $combo);


$specialCombos = "
white,#000066,Annapolis Dog Walkers/Crofton Dog Walkers,http://www.annapolisdogwalkers.com/
#18225A,#7BAAB4,Annapolis Dog Walkers/Crofton Dog Walkers,http://www.croftondogwalkers.com/
#2E385B,white,Mobile Mutts,http://www.mobilemutts.com/
white,#2E385B,Mobile Mutts,http://www.mobilemutts.com/
white,#008FC8,House Broken,http://housebrokenny.com/
white,#966D5B,Dog Walking DC,https://www.dogwalkingdc.com/
#966D5B,white,Dog Walking DC,https://www.dogwalkingdc.com/
white,#41124A,Jordan's,https://www.jordanspetcare.com/
#55595C,white,Peak City,https://peakcitypuppy.com/
#55595C,#FECA28,Peak City,https://peakcitypuppy.com/
#EAEDCC,#4D4DFF,Five Paws Delco,https://www.fivepawsdelco.com/
#281AED,#D7D2CC,Five Paws Delco,https://www.fivepawsdelco.com/
#055182,#F89D1B,Five Paws Delco,https://www.fivepawsdelco.com/
white,#7A910F,Canine Adventure,https://sites.google.com/a/canineadventure.com/canineadventure/
#495900,white,Canine Adventure,https://sites.google.com/a/canineadventure.com/canineadventure/
#38761D,#EEEEEE,Canine Adventure,https://sites.google.com/a/canineadventure.com/canineadventure/
#4A3F45,#E67FB9,Sarah's Pet Sitting,https://sarahspetsittingonline.com/
white,#EF247D,Sarah's Pet Sitting,https://sarahspetsittingonline.com/
";
$specialCombos = explode("\n", trim($specialCombos));
$specialCombos = array_map('combo', $specialCombos);
array_push($combos, "<hr><div>Some color combos lifted from the primary beta test business websites...</div><div id='citation'></div>");
foreach($specialCombos as $combo) array_push($combos, $combo);

//print_r($combos);
?>
<style>
.warning {color: red; text-align:center;}
.message {color: darkgreen; text-align:center;}
<?
foreach($combos as $combo) {
	if(!is_array($combo)) continue;
	$className = $combo['comboClass'];
	echo ".$className { background-color: {$combo['background']} !important; border-color: {$combo['background']} !important; color: {$combo['color']}  !important;}\n";
}

?>
</style>
<div class='message' id='message'></div>
<div class='warning' id='warning'></div>
<h3>Pick a color scheme...</h3>
<?
foreach($combos as $combo) {
	if(!is_array($combo)) echo $combo;
	else echo "<input type='button' 
				value='{$combo['label']}' 
				comboClass='{$combo['comboClass']}'
				colorCombo='{$combo['colorCombo']}'
				url='{$combo['url']}'
				style='color: {$combo['color']}; 
							background: {$combo['background']};' 
				onclick='showCombo(this)'> ";
	//echoButton('', $label, 'showCombo()');
	echo " ";
}
?>
<p>&nbsp;</p>
<span id='hidey' style='display:none;font-size:1.4em;'>... and <input type='button' id='setbutton' value='Make it So!' class='BigButton' onclick='setCombo()'></span>
<?
/*if($error) echo "<font color='red'>$error</font>";
if($message) {
	echo $message;
	if(!$pop) include $frameEndURL;
	exit;
}

$spacer = "<img src='art/spacer.gif' width=1 height=145>";
*/



?>
<script language='javascript'>
function clearCombos() {
<? foreach($combos as $combo) if(is_array($combo)) $nms[] = $combo['comboClass'];
	 echo "var nms = '".join(',', $nms)."';\n";
?>
	nms.split(',').forEach(
	  function (nm, index) {
		  $('.client-brand').removeClass(nm);
		  $('.white-text').removeClass(nm);

		});
}
var currentCombo = '';
var currentColors = '';

function setCombo() {
	if(currentCombo == '') {
		alert('Please choose a combo first.');
		return;
	}
	//alert(encodeURIComponent(currentColors));
	$.ajax({
				url: 'client-ui-customizer.php?colors='+encodeURIComponent(currentColors),
				dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				//contentType: 'application/json',
				//data: JSON.stringify(data),
				processData: false,
				success: reportSuccess,
				error: reportFailure
				});
}

function reportSuccess(data, textStatus, jQxhr) {
	$('#message').html(data.success);
}

function reportFailure(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'+errorThrown+textStatus;
	console.log(message );
}

function showCombo(el) {
  if(currentCombo == '') {
  	$('#hidey').toggle();
  }
  	
	currentCombo = $(el).attr('comboClass');
	currentColors = $(el).attr('colorCombo');
	
	alert(currentColors);
	
	var url = $(el).attr('url');
	url = url ? "See: <b><a target='citation' href='"+url+"'>"+url+"</a></b>" : "";
	// clean up classname
	clearCombos();
	//alert(currentCombo);
  $('#citation').html(url);
  $('.client-brand').addClass(currentCombo);
  $('.white-text').addClass(currentCombo);
}

</script>
<?
// ***************************************************************************
include $frameEndURL;
$_SESSION['bannerLogo'] = $SAVED_BANNER_LOGO;


/*
.client-brand {
    background-color: red !important;
		border-color: red !important;
}
*/
