<?// import-bluewave.php
?>
<head>
<script language='javascript' src='ajax_fns.js'></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.3.1/jquery.min.js" type="text/javascript"></script>
<!-- Required for jQuery dialog demo-->
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/jquery-ui.min.js" type="text/javascript"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-darkness/jquery-ui.css" type="text/css" media="all" />
<!-- AJAX Upload script doesn't have any dependencies-->
<!-- script type="text/javascript" src="ajaxupload.3.6.js"></script -->
<script type="text/javascript" src="ajaxupload.js"></script>

<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="colorbox/jquery.colorbox.js"></script>



<style type="text/css">
body {font-family: verdana, arial, helvetica, sans-serif;font-size: 12px;background: #373A32;color: #D0D0D0;}
h1 {color: #C7D92C;	font-size: 18px; font-weight: 400;}
a {	color: white;}
a:hover, a.hover {color: #C7D92C;}
#text {	margin: 25px; }
ul { list-style: none; }

.example {	
	padding: 0 20px;
	float: left;		
	width: 230px;
}

.wrapper {
	width: 133px;
	margin: 0 auto;
}

div.button {
	height: 29px;	
	width: 133px;
	background: url(button.png) 0 0;
	
	font-size: 14px;
	color: #C7D92C;
	text-align: center;
	padding-top: 15px;
}
/* 
We can't use ":hover" preudo-class because we have
invisible file input above, so we have to simulate
hover effect with javascript. 
 */
div.button.hover {
	background: url(button.png) 0 56px;
	color: #95A226;	
}
</style>
<style>
.uploadedfilelooks {color:lightblue;}
</style>
</head>
<?
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "client-fns.php";
require_once "key-fns.php";
require_once "contact-fns.php";
require_once "custom-field-fns.php";

$locked = locked('o-');
extract($_REQUEST);
require_once "common/init_db_common.php";
$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = '{$_SESSION['bizptr']}'");
reconnectPetBizDB($biz['db'], $biz['dbhost'], $biz['dbuser'], $biz['dbpass']);


?>
<h2>Bluewave Migration for <?= $biz['bizname'] ?></h2>

<table width=100%>
<tr><td style='font-weight:bold'><a target='_blank' href='https://secure.professionalpetsitter.com/bpsa/core/login.cfm'>https://secure.professionalpetsitter.com/bpsa/core/login.cfm</a></td></tr>
<tr>
<td valign=top width=500>
<b>Collect: </b>(Note - any of the following may be uploaded as gzips)
<ol>
<li>Vet data. (HTML) Admin > Vet List Maintenance<br>
OR (XLS) Extract Vet Detail data to excel (active vets) 
<li>Referral Cats. (HTML) Admin > Referral Code List Maintenance 
<li>Pet Codes. (HTML) Admin > Pet Classification List Maintenance
<li>Petsitters. (HTML) Staff > (Active | Inactive)
<li>Service Types (HTML) Admin > Item List Maintenance
<li>Client Data  (XLS) Admin >  Extract Client data to excel (all customers) 
<li>History (XLS) Admin >  Extract Service Detail data to excel (date range)
<li>Pets (XLS) Admin >   	Extract Pet Detail data to excel (all pets)
</ol>


<? // ########################################################################################### ?>


<form method='POST' name='importform'>
<input type='hidden' name='action'>
<b>Process: (after running Client Setup)</b>
<ol>
<li>Import Vet data
	<ol>
	<li><span id="vetsfile" class="uploadedfilelooks"></span> <div id="vetsupload" class="button" style='display:inline'>Upload Now</div>
	<li><? echoButton('', 'Test Vets Data', 'testFileKind("vets")') ?>
	<li><? echoButton('', 'Install Vets Data', 'installFileKind("vets")') ?>
	</ol>
<?

?>
<li>Import Sitter data
	<ol>
	<li><span id="sittersfile" class="uploadedfilelooks"></span> <div id="sittersupload" class="button" style='display:inline'>Upload Now</div>
	<li><? echoButton('', 'Test Sitters Data', 'testFileKind("sitters")') ?>
	<li><? echoButton('', 'Install Sitters Data', 'installFileKind("sitters")') ?>
	<li><? echoButton('', 'Install Sitter Details',  'getSitterDetails()') ?>
	</ol>
<li>Import Clients: 
<ol>
	<li><span id="referralsfile" class="uploadedfilelooks"></span> <div id="referralsupload" class="button" style='display:inline'>Upload Referrals Now</div><br>
			<div id="referralText" style="color:green;width:400px;display:inline;"></div>
	<li><span id="clientsfile" class="uploadedfilelooks"></span> <div id="clientsupload" class="button" style='display:inline'>Upload Clients Now</div>
	<li><? echoButton('', 'Test Clients Data', 'testClients()') ?>
	<li><? echoButton('', 'Install Clients Data', 'installClients()') ?>
</ol>
<li>Import Pets:
<ol>
	<li>(HTML)<span id="petcategoriesfile" class="uploadedfilelooks"></span> <div id="petcategoriesupload" class="button" style='display:inline'>Upload Pet Categories Now</div><br>
			<div id="petcategoriesText" style="color:green;width:400px;display:inline;"></div>
	<li><span id="petsfile" class="uploadedfilelooks"></span> <div id="petsupload" class="button" style='display:inline'>Upload Pets Now</div>
	<li><? echoButton('', 'Test Pets Data', 'testPets()') ?>
	<li><? echoButton('', 'Install Pets Data', 'installPets()') ?>
</ol>
<li>Import Client Detail Data: 
<ol>
	<li><span id="clientdetailsfile" class="uploadedfilelooks"></span> <div id="clientdetailsupload" class="button" style='display:inline'>Upload Client Details File Now</div>
	<li><? echoButton('', 'Test Client Details Data', 'testFileKind("clientdetails")') ?>
	<li><? echoButton('', 'Install Client Details Data', 'installFileKind("clientdetails")') ?>
</ol>
<li>Import Historical Data:
<ol>
	<li><span id="itemlistfile" class="uploadedfilelooks"></span> <div id="itemlistupload" class="button" style='display:inline'>Upload Item List Now</div> <input type=checkbox id=useuploadeditemlistfile> use file<br>
			<div id="itemlistText" style="color:green;width:400px;height:200px;overflow:scroll;display:none;"></div>
	<li><span id="historicalfile" class="uploadedfilelooks"></span> <div id="historicalupload" class="button" style='display:inline'>Upload Historical Data Now</div>
	<li><? echoButton('', 'Test Historical Data', 'testHistoricalData()') ?>
	<li><? echoButton('', 'Install Historical Data', 'installHistoricalData()') ?>
</ol>
<li>Create New Service Types:
<ul>
<li><a href='import-service-list-bluewave.php?auto=1'>Go to Service Creation Page</a>
</ul>
</ol>
</td>
<td valign=top>

<div id='logwrapper' style='height:740px;width:500px;overflow:hidden;position:absolute;top:100px;left:560px;'>
<? echoButton('', 'Clear Log', 'document.getElementById("log").innerHTML =""'); ?><p>
<div id='log' style='height:680px;width:500px;overflow:auto;'></div>
</div>
</td>
</tr>
</table>
</form>

<script type= "text/javascript">/*<![CDATA[*/
$(document).ready(function(){

	/* example 1 */
	var interval;
	setupUploadButton($('#vetsupload'), 'vets', 'vetsfile');
	setupUploadButton($('#sittersupload'), 'sitters', 'sittersfile');
	setupUploadButton($('#referralsupload'), 'referrals', 'referralsfile', function(refs) {document.getElementById('referralText').innerHTML = refs;});
	setupUploadButton($('#clientsupload'), 'clients', 'clientsfile');
	setupUploadButton($('#clientdetailsupload'), 'clientdetails', 'clientdetailsfile');
	setupUploadButton($('#petcategoriesupload'), 'petcategories', 'petcategoriesfile', function(cats) {document.getElementById('petcategoriesText').innerHTML = cats;});
	setupUploadButton($('#petsupload'), 'pets', 'petsfile');
	setupUploadButton($('#itemlistupload'), 'itemlist', 'itemlistfile', 
												function(items) {var d = document.getElementById('itemlistText'); d.innerHTML = items; d.style.display='inline';});
	setupUploadButton($('#historicalupload'), 'historical', 'historicalfile');
});/*]]>*/


function setupUploadButton(button, filekind, divname, callbackfn) {
	new AjaxUpload(button,{
		//action: 'upload-test.php', // I disabled uploads in this example for security reasons
		action: 'import-upload-data.php', 
		name: 'uploadedfile',
		data: {filekind: filekind},
		onSubmit : function(file, ext){
			// change button text, when user selects file			
			button.text('Uploading');
			
			// If you want to allow uploading only 1 file at time,
			// you can disable upload button
			this.disable();
			
			// Uploding -> Uploading. -> Uploading...
			interval = window.setInterval(function(){
				var text = button.text();
				if (text.length < 13){
					button.text(text + '.');					
				} else {
					button.text('Uploading');				
				}
			}, 200);
		},
		onComplete: function(file, response){
			button.text('Upload');
			document.getElementById(divname).innerHTML = "Uploaded: "+file;
			if(callbackfn) callbackfn(response);
			else if(response) alert(response);
			window.clearInterval(interval);
						
			// enable upload button
			this.enable();
			
			// add file to the list
			$('<li></li>').appendTo('#example1 .files').text(file);						
		}
	});
}

function testClients() {
	var refs = document.getElementById('referralText').innerHTML;
	testFileKind('clients', refs);
}

function testPets() {
	var cats = document.getElementById('petcategoriesText').innerHTML;
	testFileKind('pets', cats);
}

function testHistoricalData() {
	var cats = document.getElementById('itemlistText').innerHTML;
	var useuploadedfile = 
		document.getElementById('useuploadeditemlistfile').checked
			? 'itemlist.htm'
			: false;
	testFileKind('historical', cats, useuploadedfile);
}

function installClients() {
	var refs = document.getElementById('referralText').innerHTML;
	installFileKind('clients', refs);
}

function installPets() {
	var cats = document.getElementById('petcategoriesText').innerHTML;
	installFileKind('pets', cats);
}

function installHistoricalData() {
	var cats = document.getElementById('itemlistText').innerHTML;
	var useuploadedfile = 
		document.getElementById('useuploadeditemlistfile').checked
			? 'itemlist.htm'
			: false;
	installFileKind('historical', cats, useuploadedfile);
}

function testFileKind(filekind, extraData, useuploadedfile) {
	if(useuploadedfile != undefined && useuploadedfile) extraData = '&extraDataFile='+useuploadedfile;
	else if(extraData && (typeof(extraData) != 'undefined')) extraData = '&extraData='+escape(extraData);
	else extraData = '';
	//alert('import-test-file-ajax.php?kind='+filekind+extraData);
	tryAndLog('import-test-file-ajax.php?kind='+filekind+extraData, 'Test '+filekind);
}

function installFileKind(filekind, extraData, useuploadedfile) {
	if(useuploadedfile != undefined && useuploadedfile) extraData = '&extraDataFile='+useuploadedfile;
	else if(extraData && (typeof(extraData) != 'undefined')) extraData = '&extraData='+escape(extraData);
	else extraData = '';
	tryAndLog('import-install-file-ajax.php?kind='+filekind+extraData, 'Install '+filekind);
}

function tryAndLog(url, action) {
	ajaxGetAndCallWith(url, logThis, action);
}

function logThis(action, response) {
	document.getElementById('log').innerHTML =
		document.getElementById('log').innerHTML
		+ '<hr>'
		+ new Date()
		+ '<br>'
		+ action
		+ ': <br>'
		+ response
		;
}

var bssm = null;
var NS6 = (document.getElementById&&!document.all)
var IE = (document.all)
var NS = (navigator.appName=="Netscape" && navigator.appVersion.charAt(0)=="4")
var YOffset=120; // no quotes!!
var staticYOffset=30; // no quotes!!
var lastY=0;
var winY;

function makeStatic() {
	if(!bssm) bssm = document.getElementById('logwrapper');
//if(!confirm(bssm.style.top)) return;		
	if (NS||NS6) {winY = window.pageYOffset;}
	if (IE) {winY = document.body.scrollTop;}
	if (NS6||IE||NS) {
	if (winY!=lastY&&winY>YOffset-staticYOffset) {
		smooth = .2 * (winY - lastY - YOffset + staticYOffset);
	}
	else if (YOffset-staticYOffset+lastY>YOffset-staticYOffset) {
		smooth = .2 * (winY - lastY - (YOffset-(YOffset-winY)));}
		else {smooth=0}
		if(smooth > 0) smooth = Math.ceil(smooth);
		else smooth = Math.floor(smooth);
		if (IE) bssm.pixelTop+=smooth;
		if (NS6||NS) bssm.style.top=parseInt(bssm.style.top)+smooth
		lastY = lastY+smooth;
		setTimeout('makeStatic()', 50)
	}
}

function getSitterDetails() {
	$.fn.colorbox({href: "import-server-file-chooser.php?doneaction="+escape('parent.update("sitterdetails", getSels())'), width:"600", height:"470", iframe:true, scrolling: "auto", opacity: "0.3"});
}
function update(aspect, details) {
	$.fn.colorbox.close();
	tryAndLog('import-sitter-detail-bluewave.php?files='+escape(details), 'Import Sitter Details');
}

makeStatic();
</script>
