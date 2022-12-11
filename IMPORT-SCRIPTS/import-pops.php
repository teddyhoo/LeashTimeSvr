<?// import-pops.php
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

.lilyella {color:yellow;font-size:0.9em;}
</style>
<style>
.uploadedfilelooks {color:blue;}
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
<h2>POPS Migration for <?= $biz['bizname'] ?></h2>

<table width=100%><tr>
<td valign=top width=500>
<a  target=login href='https://www.powerpetsitter.net/signin.aspx'>Login</a><p>
<b>Collect:</b>
<ol>
<li><a target=pettypes href=https://www.powerpetsitter.net/admin/pettypes.aspx>Pet Types</a><br>
<li><a target=vets href=https://www.powerpetsitter.net/admin/vets.aspx>Vet Data</a><br>
<li><a target=clients href=https://www.powerpetsitter.net/admin/users.aspx>Client Data. (XLS)</a>:Export Client, All. Both<br>
<li><a target=sitters href=https://www.powerpetsitter.net/admin/users.aspx>ACTIVE Provider Data. (XLS)</a>:Export Staff.<br>
<li><a target=sitters href=https://www.powerpetsitter.net/admin/users.aspx>INACTIVE Provider Data. (XLS)</a>:Export Staff<br>
<li><a target=pets href=https://www.powerpetsitter.net/admin/rpt_clientsByVet.aspx>Clients/Pets by Vet</a><br>
<li><a target=pets2 href=https://www.powerpetsitter.net/admin/rpt_petreport.aspx>Pet Birthdays</a>: Export.  Don't convert<br>
<li><a target=custom href=https://www.powerpetsitter.net/admin/clientproperties.aspx>Custom Client Fields</a><br>
<li><a target=timeblocks href=https://www.powerpetsitter.net/admin/timeblocks.aspx>Time Blocks</a>: https://www.powerpetsitter.net/admin/timeblocks.aspx<br>
<li><a target=holidays href=https://www.powerpetsitter.net/admin/holidays.aspx>Holidays</a><br>
<li><a target=referrals href=https://www.powerpetsitter.net/admin/referralchoices.aspx>Referral Choices</a><br>
<li><a target=keys href=https://www.powerpetsitter.net/admin/rpt_keyaudit.aspx>Key Audit (Locations) Report (HTML)</a><br>
</ol>
<b>Process: (after running Client Setup)</b>
<p>
<?
$links = array(
		'vets'=>'https://leashtime.com/import-vets-detail-pops.php?file=',
		'providers'=>'https://leashtime.com/import-providers-mapped.php?map=map-powerpetsitter-providers.csv&file=',
		'INACTIVEproviders'=>'https://leashtime.com/import-providers-mapped.php?map=map-powerpetsitter-providers.csv&inactive=1&file=',
		'clients'=>'https://leashtime.com/import-clients.php?map=map-powerpetsitter-clients.csv&file=',
		'clientsXMLFormat'=>'https://leashtime.com/import-clients-pops-xml.php?map=map-powerpetsitter-clients-xml.csv&file=',
		'pets'=>'https://leashtime.com/import-clients-and-pets-by-vet-pops.php?file=',
		'pets2'=>'https://leashtime.com/import-pet-birthdays-pops.php?file=',
		'keyaudit'=>'https://leashtime.com/import-pops-keyaudit.php?file=');

		
function goButton($type) {
	echo "<input id='$type' name=$type'> <input type=button value=Go onclick='go(\"$type\")'>";
}
?>
Source Directory: <input id='sourcedir' onchange='$(".basedir").html(this.value+"/")'>
<ol>
<li>Import Vet data: <span class='lilyella'><?= $links['vets'] ?><span class='basedir'></span></span><? goButton('vets'); ?>
<br><b>Go in and eliminate <a target=dups href='duplicate-entries.php?table=tblclinic'>duplicate Veterinary Clinics</a> from the database</b>
<li>Import ACTIVE Sitter data: <span class='lilyella'><?= $links['providers'] ?><span class='basedir'></span></span><? goButton('providers'); ?>
<li>Import INACTIVE Sitter data: <span class='lilyella'><?= $links['providers'] ?><span class='basedir'></span></span><? goButton('INACTIVEproviders'); ?>
<br><b>Go in and eliminate <a target=dups href='duplicate-entries.php?table=tblprovider'>duplicate Sitters</a> from the database</b>
<li>Import Client data: <span class='lilyella'><?= $links['clients'] ?><span class='basedir'></span></span><? goButton('clients'); ?> <input type='checkbox' CHECKED id='clientxmlformat'> <label for='clientxmlformat'>XML formatted</label>
<br><b>Go in and eliminate <a target=dups href='duplicate-entries.php?table=tblclient'>duplicate Clients</a> from the database</b>
<!--li>Import Pets by Vet data: <span style='color:red'>DO NOT USE https://leashtime.com/import-pops-clients-by-vet.php?file=</span -->
<li>Import Pets by Vet data: <span class='lilyella'><?= $links['pets'] ?><span class='basedir'></span></span><? goButton('pets'); ?>
<li>Import Pet Birthdays: <span class='lilyella'><?= $links['pets2'] ?><span class='basedir'></span></span><? goButton('pets2'); ?>
<li>Import Key Audit data: <span class='lilyella'><?= $links['keyaudit'] ?><span class='basedir'></span></span><? goButton('keyaudit'); ?>
</ol>

<script language=javascript>
var links = {
<? foreach($links as $k => $link) $pairs[] = "$k:'$link'";
	 echo join(',', $pairs);
?>
};

function go(key) {
	var linkBase;
	if(key=='clients' && document.getElementById('clientxmlformat').checked) linkBase = links['clientsXMLFormat'];
	else linkBase = links[key];
	link = linkBase+document.getElementById('sourcedir').value+'/'+document.getElementById(key).value;
	window.open(link, '_newtab','toolbar=1,location=1,directories=1,status=1,menubar=1,scrollbars=1,resizable=1'); 
}
</script>