<? // referral-categories.php
$pageTitle = "Referral Categories";
require_once "common/init_session.php";
include "common/init_db_petbiz.php";
include "gui-fns.php";
include "referral-fns.php";
locked('o-');

if(!$_SESSION['referralsenabled']) {
	echo "Referrals are not enabled for this business account.";
	include "frame-end.html";
	exit;
}

if($_POST) {
	saveReferralCategories($_POST['cats']);
	$message = "Referral Categories saved.";
}
include "frame.html";
if($message) {
	echo "<font color='green'>$message</font>";
}

$referralCats = getReferralCategories($_SESSION['preferences']['masterPreferencesKey']);  // return site's own ref cats, or master cats
$referralCats = referralCategoriesDescription($referralCats);

?>
<link href="nestedListSort.css" type="text/css" rel="stylesheet">

<script type="text/javascript" src="jquery-1.2.1.js"></script>
<script type="text/javascript" src="jquery.ui-1.0/jquery.dimensions.js"></script>
<script type="text/javascript" src="jquery.ui-1.0/ui.mouse.js"></script>
<script type="text/javascript" src="jquery.ui-1.0/ui.draggable.js"></script>
<script type="text/javascript" src="jquery.ui-1.0/ui.droppable.js"></script>
<script type="text/javascript" src="jquery.ui-1.0/ui.sortable.js"></script>

<script type="text/javascript" src="nestedListSort.js"></script>
<script type="text/javascript" src="referral-categories.js"></script>

<form method='POST' name='referralcatlist'>
<?
hiddenElement('cats', '');
echoButton('', 'New Category Group', 'addACategoryTo()');
echo " ";
echoButton('', 'New Category', 'addAnItemTo()');
echo "<img src='art/spacer.gif' height=1 width=20>";
echoButton('', 'Save Changes', 'saveCats()');
echo " ";
echoButton('', 'Cancel Changes', 'document.location.href="referral-categories.php"');
?>
</form>
<table width=100%>
<tr><td style='vertical-align:top;width:50%'>
<h3>Local Referral Categories</h3>
<script type="text/javascript">

var referralCats = <?= $referralCats ?>;
dumpCategories(referralCats);

function saveCats() {
	var cats = gatherCategories();
	var origIds = gatherCategoryIds(referralCats);
	var currIds = gatherCategoryIds(gatherCategories());
	var deletions = 0;
	for(var i=0;i<origIds.length;i++) {
		var found = false;
		for(var j=0;j<currIds.length;j++)
			if(origIds[i] == currIds[j]) found = true;
		if(!found) deletions++;
	}
	
	if(deletions)
		if(!confirm("You will be eliminating "+deletions+" existing referral categories if you click OK.  Proceed?"))
			return;
	document.getElementById('cats').value = cats;
	document.referralcatlist.submit();
}
</script>
</td>
<td style='vertical-align:top;;width:50%;font-size:110%;'>
<?
if($_SESSION['orgptr']) {
	$cats = getOrganizationReferralCategories($_SESSION['orgptr']);
?>
<h3 style='font-size:110%;'>Global Referral Categories</h3>
<?
	if($cats)
		referralCategoryDisplayTable($cats);
	else echo "No Global referral categories defined.";
}
?>
</td>
</tr>
</table>

<p><img src='art/spacer.gif' height=300>
<?
include "frame-end.html";
?>