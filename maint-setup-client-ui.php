<? // maint-setup-client-ui.php
require_once "common/init_session.php";
require_once "common/init_db_common.php";
require_once "gui-fns.php";

$locked = locked('z-');
extract(extractVars('action,bizid,mockup,control,action,stylecss,nobannertopmargin', $_REQUEST));


if($bizid) {
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = $bizid LIMIT 1");
	$stagedir = "/var/www/prod/bizfiles/biz_$bizid";
	if(!file_exists($stagedir)) mkdir($stagedir);

	$stagedir = "/var/www/prod/bizfiles/biz_$bizid/stage";
	$clientui = dirname($stagedir)."/clientui";
	if(!file_exists($stagedir)) {
		mkdir($stagedir);
		if(file_exists($clientui))
			foreach(glob("$clientui/*") as $f)
				if(!is_dir($f)) {
					copy($f, "$stagedir/".basename($f));
					//chown("$stagedir/".basename($f), 'matt');
				}
	}
	//chown($stagedir, 'matt');
	//echo "file_exists($clientui): ".file_exists($clientui);
	//print_r(glob("$stagedir/*"));
}


if($bizid && $stylecss) {
	file_put_contents("$stagedir/style.css", $stylecss);
	echo "<script language='javascript'>
	parent.$.fn.colorbox.close();
	top.frames[0].location.href='maint-setup-client-ui.php?control=1&bizid=$bizid';
	top.frames[1].location.href='maint-setup-client-ui.php?mockup=1&bizid=$bizid';
	</script>";
	exit;
}

if($bizid && $action == 'editstyle') {
//echo htmlentities(print_r($_POST,1));	
	echo "<form method='POST'>";
	echo "<input type=submit><input type=hidden name=bizid value=$bizid><p><textarea cols=100 rows=40 name=stylecss id=stylecss>";
	echo file_get_contents("$stagedir/style.css");
	echo "</textarea></form>";
	exit;
}

if($action == 'install' && $bizid) {
	$files = glob("$stagedir/*");
	if(!$files) $error = "No files found to install.";
	if(!file_exists($clientui)) mkdir($clientui);
	$names = array_map('basename', $files);
	foreach($names as $nm) if(file_exists("$clientui/$nm")) unlink("$clientui/$nm");
	foreach($files as $f) {
		copy($f, "$clientui/".basename($f));
	}
	$success = (count($files) ? count($files) : 'No')." files installed.";
}


if($_FILES['bannerfile']) {
	$originalName = $_FILES['bannerfile']['name'];
	$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
	if($extension != 'JPG') $error = "$originalName must be a JPG! [$extension]";
	else {
		$destFile = "$stagedir/Header.jpg";
		if(file_exists($destFile)) unlink($destFile);
		if(!move_uploaded_file($_FILES['bannerfile']['tmp_name'], $destFile)) {
			$error = "There was an error uploading the file, please try again!";
		}
	}
	if(!$error) $success = "Banner uploaded.";
}
if($_FILES['navfile']) {
	$originalName = $_FILES['navfile']['name'];
	$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
	if($extension != 'JPG') $error = "$originalName ,must be a JPG!";
	else {
		$destFile = "$stagedir/nav.jpg";
		if(file_exists($destFile)) unlink($destFile);
		if(!move_uploaded_file($_FILES['navfile']['tmp_name'], $destFile)) {
			$error = "There was an error uploading the file, please try again!";
		}
	}
	if(!$error) $success = "Nav image uploaded.";
}
if($_POST['dropbackground']) {
	foreach(explode(',','gif,jpg,png') as $xt)
		if(file_exists("$stagedir/bg.$xt")) unlink("$stagedir/bg.$xt");
	if(!$error) $success = "Background image dropped.";
}
if($_FILES['bgfile']) {
	$originalName = $_FILES['bgfile']['name'];
	$extension = strtoupper(substr($originalName, strrpos($originalName, '.')+1));
	if($extension != 'JPG' && $extension != 'GIF' && $extension != 'PNG') $error = "$originalName ,must be a JPG, GIF, or PNG!";
	else {
		foreach(explode(',','gif,jpg,png') as $xt)
			if(file_exists("$stagedir/bg.$xt")) unlink("$stagedir/bg.$xt");
		$destFile = strtolower("$stagedir/bg.$extension");
		if(file_exists($destFile)) unlink($destFile);
		if(!move_uploaded_file($_FILES['bgfile']['tmp_name'], $destFile)) {
			$error = "There was an error uploading the file, please try again!";
		}
	}
	if(!$error) $success = "Background image uploaded.";
	$bgChanged = true;
}

if($control) {
	if($_POST['action'] == 'saveColorsAndStyle') if(saveStyle()) 	$success = "Changes saved.";
;
	$windowTitle = "Client UI Setup";
	include 'frame-maintenance.php';
	echo "<h2>Client UI Setup: ".($biz ? $biz['bizname'] : 'No Business Selected')."</h2>";
	if($biz) echo "<p>
	<a href='https://leashtime.com/maint-edit-biz.php?id=$bizid' target='_top'>Back to the Business Editor</a>
	<p>";
	
	//$bgrepeatOptions = 'tiled|repeat||horizontal|repeat-x'
	/*
	biz260:
	background: url(bg.jpg) no-repeat center center fixed;
	
	biz156:
	  background: #f6e8d6 url(topbg.png) top center repeat-x;

	biz308:
	  background-repeat: no-repeat; background-position: top center; background-attachment: scroll;


background-image: url(bg.jpg);
background-attachment: scroll|fixed
background-position: left top|left center|left bottom|right top|right center|right bottom|center top|center center|center bottom
background-repeat:repeat|repeat-x|repeat-y|no-repeat
background-size: auto|length|cover|contain

(if cover: 	
	-webkit-background-size: cover;
	-moz-background-size: cover;
	-o-background-size: cover;
	background-size: cover;  
)
	*/	
	
	echo "<form name='colorsform' method='POST'><table border=0><tr><td bgcolor=lightgrey><table>";
	hiddenElement('action', 'saveColorsAndStyle');
	$fieldstr = 'BACKGROUNDCOLOR;BUTTONCOLOR;BUTTONBACKGROUND;BUTTONDOWNCOLOR;BUTTONDOWNBACKGROUND;MENUCOLOR;MENUHOVERCOLOR';
	$fields = explode(';', strtolower($fieldstr));
	$style = getStyleVals();
	$prettyLabels = explodePairsLine(
		'backgroundcolor|Background Color||buttondownbackground|ButtonDown Background Color||'
		.'buttondowncolor|ButtonDown Text Color||buttonbackground|Button Background Color||buttoncolor|Button Text Color||'
		.'menuhovercolor|Nav Menu Text Hover Color||menucolor|Nav Menu Text Color');
	foreach($fields as $f) {
		$value = safeValue($style[$f]);
		echo "<tr><td><label for='$f'>{$prettyLabels[$f]}</label> <input style='width:170px'' id='$f' name='$f' value='$value' onBlur='updateButtonLooks(this)' autocomplete='off'></td>\n";
		echo "<td id='td_$f' style='width:30px;background:{$style[$f]};border:solid black 1px;'></td></tr>\n";
	}
	echo "<tr><td>";
	labeledCheckbox('No margin above banner', 'nobannertopmargin', $style['nobannertopmargin']);
	echo "<hr>";
	$mcbcid = 'mobileclientbannercolor';
	echo "<tr><td><label for='$f'>Mobile Client Banner Color</label> 
					<input style='width:170px'' id='$mcbcid' name='$mcbcid' value='{$style[$mcbcid]}' onBlur='updateButtonLooks(this)' autocomplete='off'></td>\n";
	echo "<td id='td_$mcbcid' style='width:30px;background:{$style[$mcbcid]};border:solid black 1px;'></td></tr>\n";
//No margin above banner: <input type=checkbox id=nobannertopmargin name=nobannertopmargin ".($nobannertopmargin ? 'CHECKED;
	echo "</td></tr>\n";
	echo "<tr><td>";
	echoButton('', 'Save', 'saveColorsAndStyle()');
	echo "</td></tr>\n";
	echo "</table></td><td valign=top bgcolor=lightgrey>";
	echo "<table><tr><td>Background</td></tr>";
	$options = explodePairsLine('scroll|scroll||fixed|fixed');
	selectRow('Attached:', 'attachment', $style['attachment'], $options);
	$options = array();
	foreach(explode('|', 'left top|left center|left bottom|right top|right center|right bottom|center top|center center|center bottom')
						as $p) $options[$p] = $p;
	selectRow('Position:', 'position', $style['position'], $options);
	$options = explodePairsLine('repeat|repeat||repeat-x|repeat-x||repeat-y|repeat-y||no-repeat|no-repeat');
	selectRow('Repeat:', 'repeat', $style['repeat'], $options);
	$options = explodePairsLine('auto|auto||cover|cover||contain|contain'); //length
	selectRow('Size:', 'size', $style['size'], $options);
	echo "<tr><td style='padding-top:20px;'>Hint:<br><a target='_blank' href='http://www.w3schools.com/html/html_colornames.asp'>Color Names<br>and Chooser</a></td></tr>
		</table></form></td>";


	echo "<td><table>";
	echo "<tr><td><form name=uploadbanner method=post enctype='multipart/form-data'>Banner: <input type='file' name='bannerfile'> <input type=submit value=Upload></form> ";
	echo " <span class='tiplooks'>JPG Exactly 780px X 100px</span></td></tr>";
	echo "<tr><td>&nbsp;</td></tr>";
	echo "<tr><td><form name=uploadnav method=post enctype='multipart/form-data'>Nav Menu Bar: <input type='file' name='navfile'> <input type=submit value=Upload></form> 
	<span class='tiplooks'>JPG Exactly 790px X 25px</span>";
	echo "<tr><td>&nbsp;</td></tr>";
	echo "<tr><td><form name=uploadbg method=post enctype='multipart/form-data'>Background: <input type='file' name='bgfile'> <input type=submit value=Upload></form> <form name=dropbg method='POST'><input type=hidden name='dropbackground' value='1'><input type=submit value='Drop Background' class='HotButton'></form>
	<span class='tiplooks'>JPG, GIF or PNG of reasonable dimensions and file size</span>";
	echo "<tr><td>";
	fauxLink('Edit stage/style.css', 'editStyle()');
	echo "</td></tr></table></td></tr></table>";
	
	echoButton('', 'Refresh Mockup', "top.frames[1].location.href=\"maint-setup-client-ui.php?mockup=1&bizid=$bizid\";");
	echo "<img src='art/spacer.gif' width=30 height=1>";
	echoButton('', 'Install', 'install()', 'BigButton', 'BigButtonDown');
	echo "<img src='art/spacer.gif' width=30 height=1><div style='display:inline;background:white'>";
	if($error) echo "<font color=red>$error</font><p>";
	if($success) echo "<font color=green>$success</font><p>";
	echo "</div>";
	
	
	
		
		
// <td rowspan=3 align=right>";  echo "</td>		
//echo "<td valign=bottom>";
?>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
function updateButtonLooks(el) {
	var id = 'td_'+el.id;
	document.getElementById(id).style.background=el.value;
}

function editStyle() {
	//alert("maint-setup-client-ui.php?action=editstyle&bizid=<?= $bizid ?>");
	$.fn.colorbox(
		{href: "https://leashtime.com/maint-setup-client-ui.php?action=editstyle&bizid=<?= $bizid ?>", 
		 width:"850", height:"800", iframe:true, scrolling: "auto", opacity: "0.3"	});
}

function saveColorsAndStyle() {
	if(!MM_validateForm(
<? foreach($fields as $f) $ff[] = "'$f', '', 'R'"; echo join(",\n", $ff); ?>
)) return;
	else document.colorsform.submit();
}

function install() {
	document.location.href='maint-setup-client-ui.php?bizid=<?= $bizid ?>&control=1&action=install';
}


<? if($bgChanged) echo "saveColorsAndStyle();"; ?>
top.frames[1].location.href='maint-setup-client-ui.php?mockup=1&bizid=<?= $bizid ?>';
</script>
<?
}

if($mockup) {
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
	require_once "gui-fns.php";

	if($thisBiz = $bizid) {
		locked('z-');
		$_SESSION["uidirectory"] = "bizfiles/biz_$thisBiz/stage/";
		if(!file_exists($_SESSION["uidirectory"].'style.css'))	$_SESSION["bizfiledirectory"] = "bizfiles/biz_$thisBiz/";
	}
//print_r($_SESSION["uidirectory"]);

	//echo "UI: ({$_REQUEST['bizid']}) ".file_exists($_SESSION["uidirectory"].'style.css');

	include "frame-client.html";

	echoButton('', 'This is a Button');


	echo "<br><img src='art/spacer.gif' width=1 height=500>";

	if($thisBiz) {
		unset($_SESSION["uidirectory"]);
		unset($_SESSION["bizfiledirectory"]);
		unset($_SESSION['bannerLogo']);
	}
	?>
	<script language='javascript'>
	function goto(where) {
		var bizid = document.getElementById('bizid').value;
		if(where == 0 || typeof where == 'undefined' || !where) {
			alert('Bad biz ID');
			return;
		}
		if(where == 'next') {bizid = parseInt(bizid) + 1;}
		else if(where == 'prev') bizid = parseInt(bizid) - 1;
		//alert(bizid);
		document.location.href='client-ui-test.php?bizid='+bizid;
	}

	</script>
	<?
	include "frame-end.html";

	exit;
}

if(!$mockup && !$control) {
	echo "<FRAMESET rows='365,100%'>
				<FRAME src='maint-setup-client-ui.php?control=1&bizid=$bizid'>
				<FRAME src='maint-setup-client-ui.php?mockup=1&bizid=$bizid'>
				</FRAMESET>";
}

function saveStyle() {
	global $stagedir;
	$bgfieldstr = 'ATTACHMENT;POSITION;REPEAT;SIZE';
	$fieldstr = 'BACKGROUNDCOLOR;BUTTONDOWNBACKGROUND;BUTTONDOWNCOLOR;BUTTONBACKGROUND;BUTTONCOLOR;MENUHOVERCOLOR;MENUCOLOR;MOBILECLIENTBANNERCOLOR;'
							.$bgfieldstr;
	$fields = explode(';', strtolower($fieldstr));
	$xml = "<style>";
	foreach($fields as $f) $xml .= "<property><name>$f</name><value>".htmlentities($_POST[$f])."</value></property>";
	if($_POST['nobannertopmargin']) "<property><name>NOBANNERTOPMARGIN</name><value>1</value></property>";
	$xml .= "</style>";
	file_put_contents("$stagedir/style.xml", $xml);
	$style = basicStyle();
	
	$bg = null;
	foreach(explode(',', 'jpg,gif,png') as $ext) {
		if(file_exists("$stagedir/bg.$ext")) $bg = "bg.$ext";
	}
	
	foreach($fields as $f) {
		if(!$bg && strpos($bgfieldstr, strtoupper($f)) !== FALSE) continue;  // ignore background attributes in the absence of bg
		$val = $_POST[$f];
		if($f == 'size' && $val == 'cover')
			$val = "cover; 	
	-webkit-background-size: cover;
	-moz-background-size: cover;
	-o-background-size: cover;
	background-size: cover";
		$style = str_replace(strtoupper($f), $val, $style);
	}
	$style = str_replace('BACKGROUNDIMAGE', $bg, $style);
	$style = str_replace('BACKGROUNDCOLOR', $_POST['backgroundcolor'], $style);
	$style = str_replace('NOBANNERTOPMARGIN', ($_POST['nobannertopmargin'] ? "  margin-top:-16px;\n" : ''), $style);
	file_put_contents("$stagedir/style.css", $style);
	return true;
}

function getStyleVals() {
	global $stagedir;
	if(file_exists("$stagedir/style.xml")) 
		$xml = file_get_contents("$stagedir/style.xml");
	if($xml) {
		$styleVals = new SimpleXMLElement($xml);
//print_r($styleVals);		
		foreach ($styleVals->property as $i =>$property) {
			//print_r($property->name);
			//echo "<br>{$property->name}: {$property->value}";
		 	$style["{$property->name}"] = html_entity_decode("{$property->value}");
	 	}	
	}
	else {
		$fieldstr = 'nobannertopmargin|1||menucolor|white||menuhovercolor|black||buttoncolor|black||buttonbackground|orange||buttondowncolor|black||buttondownbackground|brown||backgroundcolor|white||mobileclientbannercolor|blue';
		$style = explodePairsLine($fieldstr);
	}
	return $style;		
}

function basicStyle() {
	return <<<STYLE
div.Header 
{
  margin: 0 auto;
  position: relative;
  width: 790px;
  height: 135px;
  NOBANNERTOPMARGIN
}

div.Header  div
{
  width: 100%;
  height: 100%;
  background-image: url('Header.jpg');
  background-repeat: no-repeat;
  background-position: center center;
  text-align: left;
}

.logo
{
  position: relative;
  left: 269px;
  top: 0px;
}



.logo-name
{
  font-size: 66px;
  font-family: Arial;
  font-style: normal;
  font-weight: bold;
}

.logo-name a
{
  text-decoration: none;
  color: #F58519 !important;
}

.logo-text
{
  font-size: 36px;
  font-family: New Times Roman;
  font-style: normal;
  font-weight: bold;
  color: #F1E819 !important;
}

.nav .l, .nav .r div 
{
  background-position: left top;
  background-repeat: no-repeat;
  background-image: url('nav.jpg');
}

* html .nav .l, * html .nav .r div 
{
  font-size: 1px;
  background: none;
  behavior: expression(this.runtimeStyle.filter?'':this.runtimeStyle.filter="progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + (function(){var t=document.getElementsByTagName('link');for(var i=0;i<t.length;i++){var l=t[i];if(l.href&&/style\\.css$/.test(l.href))return l.href.replace('style.css','');}return '';})()+"nav.jpg',sizingMethod='crop')");
}


.menu a span span
{
  font-family: 'Arial';
  font-size: 12px;
  font-weight: normal;
  font-style: normal;
  text-decoration: none;
  color: MENUCOLOR;
  padding: 0 12px;
  margin: 0 0px;
  line-height: 25px;
  text-align: center;
  background-image: none;
  background-position: left top;
  background-repeat: repeat-x;
  
}

.menu a:hover span span
{
  color: MENUHOVERCOLOR;
  background-position: left -25px;
}

.menu li:hover a span span
{
  color: MENUHOVERCOLOR;
  background-position: left -25px;
}

* html .menu .menuhover .menuhoverA span span
{
  color: white;
  background-position: left -25px;
}



input.Button {
  font-family: inherit;
  font-size: inherit;
  color: BUTTONCOLOR;
  background: BUTTONBACKGROUND;
}

input.ButtonDown {
  font-family: inherit;
  font-size: inherit;
  color: BUTTONDOWNCOLOR;
  background: BUTTONDOWNBACKGROUND;
}

body
{
  margin: 0 auto;
  padding: 0;
  font-size: 62.5%; /* Resets 1em to 10px */
  font-family: 'Lucida Grande', Verdana, Arial, Sans-Serif;
  background-color: BACKGROUNDCOLOR;
  background-image: url("BACKGROUNDIMAGE");
	background-attachment: ATTACHMENT;
	background-position: POSITION;
	background-repeat: REPEAT;
	background-size: SIZE;  
  
  color: #000000;
}
STYLE;
}


