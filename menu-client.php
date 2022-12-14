<? // menu-client.php
if($_REQUEST['ajax']) { // allow this to be called via ajax.
	require_once "common/init_session.php";
	require_once "common/init_db_petbiz.php";
}

$scheduleMakerURL = 
		$_SESSION['preferences']['simpleClientScheduleMaker'] ? "client-own-schedule-request.php" : (
    $JUST_BUILD_menuLinks || $_REQUEST['ajax'] ? "client-sched-makerV3.php" :
    "client-sched-makerV2.php");
    					
//if(dbTEST('tonkatest,dogslife2') || 
//		(time() > strtotime('2013-03-25 00:00:00'))) $scheduleMakerURL = 'client-sched-makerV2.php';    					


$creditCardOptionLabel = 'CREDIT CARD';
if(!$_SESSION['preferences']['ccAcceptedList']) $creditCardOptionLabel = 'E-PAYMENT';
$contactUs = $_SESSION && loginidsOnlyTEST('ccdl,valleyps,ang-happypaws') ? "client-own-comms.php" : "client-own-request.php";
// rick is a tonkatest client
$menuItemList = array(
	'home'=>array('HOME', 'index.php'),
	'schedule'=>array('REQUEST VISITS', $scheduleMakerURL), 
	'profile'=>array('PROFILE', 
		($_SESSION['preferences']['segmentedClientEditableProfile']
		 ?'client-own-edit-segmented.php'
		 : 'client-own-edit.php')), 
	'password'=>array('CHANGE PASSWORD', 'password-change-page.php'), 
	'creditcard'=>array($creditCardOptionLabel, 'client-own-account-cc.php'), 
	'contactus'=>array('CONTACT US', $contactUs), 
	'account'=>array('ACCOUNT', 'client-own-account.php'), 
	'othermenu'=>null,
	'logout'=>array('LOGOUT', 'login-page.php?logout=1'));
	
if($_SESSION['preferences']['enableOfficeDocuments'] && $_SESSION['preferences']['offerOfficeDocumentsToClients'])
	$otherMenu['clientdocs'] = array('INFO', 'client-info-page.php');
	//$otherMenu['clientdocs'] = '<li><a href="client-public-documents.php"><span><span>Documents</span></span></a></li>';
	
if(!$otherMenu  && !$_SESSION["responsiveClient"]) unset($menuItemList['othermenu']); // && !$_SESSION["responsiveClient"]
else {
	if($menuItemList['creditcard']) {
		$otherMenu = array_merge(array('creditcard'=>$menuItemList['creditcard']), (array)$otherMenu);
		unset($menuItemList['creditcard']);
	}
	if($_SESSION['preferences']['offerClientUIMessageCommsPage']) {
		$otherMenu['messages'] = array('MESSAGES', 'client-own-comms.php');
	}
	$otherMenu['logout'] = $menuItemList['logout'];
	unset($menuItemList['logout']);
	$menuItemList['othermenu'] = "<li><a href='#'><img height=23 src='art/LarryHead.png'></span></a>\n<ul class=\"menu\">\n";
	foreach($otherMenu as $key => $item) {
		if(is_string($item)) $menuItemList['othermenu'] .= "$item\n";
		else $menuItemList['othermenu'] .= "<li><a href=\"{$item[1]}\"> {$item[0]}</a></li>\n";
	}
  $menuItemList['othermenu'] .= "</ul>\n</li>\n";
 }
	
	
if($_SESSION['preferences']['suppressClientScheduling'])
	unset($menuItemList['schedule']);
	
//if($_SESSION['preferences']['suppressClientCreditCardEntry']) {
//	unset($menuItemList['creditcard']);
//}
	
if($db == 'leashtimecustomers')
	$menuItemList = array(
		'home'=>array('HOME', 'index.php'),
		//'schedule'=>array('REQUEST VISITS', $scheduleMakerURL), 
		//'profile'=>array('PROFILE', 'client-own-edit.php'), 
		'password'=>array('CHANGE PASSWORD', 'password-change-page.php'), 
		'creditcard'=>array($creditCardOptionLabel, 'client-own-account-cc.php'), 
		'contactus'=>array('CONTACT US', $contactUs), 
		'account'=>array('ACCOUNT', 'client-own-account.php'), 
		'logout'=>array('LOGOUT', 'login-page.php?logout=1'));
	
if(!$_SESSION['preferences']['offerClientUIAccountPage'])
	unset($menuItemList['account']);
if($_SESSION["creditCardIsRequired"] && !$_SESSION['preferences']['suppressClientCreditCardEntry']) 
	$exclusiveMenuItemList = array('creditcard', 'contactus', 'logout');

if(!($_SESSION['preferences']['ccGateway'] 
			&& $_SESSION['preferences']['offerClientUIAccountPage'] 
			&& $_SESSION['preferences']['offerClientCreditCardMenuOption'] 
			&& !$_SESSION['preferences']['suppressClientCreditCardEntry'] )) {
	unset($menuItemList['creditcard']);
	unset($otherMenu['creditcard']);
	unset($exclusiveMenuItemList['creditcard']);
}
if($JUST_BUILD_menuLinks || $_REQUEST['ajax']) {
	unset($menuItemList['othermenu']);
	foreach($menuItemList as $key => $link) {
		if($exclusiveMenuItemList && !in_array($key, $exclusiveMenuItemList)) continue;
		$menuLinks[$key] = array('label'=>$link[0], 'target'=>$link[1]);
	}
	foreach((array)$otherMenu as $key => $link)
		$menuLinks[$key] = array('label'=>$link[0], 'target'=>$link[1]);
	if($_REQUEST['ajax']) {
		header("Content-type: application/json");
		echo json_encode($menuLinks);
		exit;
	}
}
else {
?>
    <div class="nav"> <!-- client menu -->
    	<ul class="menu">
<?    	
foreach($menuItemList as $key => $link) {
	if($exclusiveMenuItemList && !in_array($key, $exclusiveMenuItemList)) continue;
	if(is_string($link)) echo $link;
	else echo "<li><a href=\"{$link[1]}\"> <span><span>{$link[0]}</span></span></a></li>\n";
}
?>
    	</ul>
    	<div class="l"></div>
    	<div class="r"><div></div></div>
    </div>
<? } ?>