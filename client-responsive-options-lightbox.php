<?
// client-responsive-options-lightbox.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
require_once "preference-fns.php";
locked('o-');

// called by ajax to determine column properties for all provider schedule lists in LeashTime
// opens in an iframe lightbox

$property = $_REQUEST['prop'];
$labels = explodePairsLine('fname|First Name||lname|Last Name||phone|Phone||email|Email||address|Full Address||phoneOrEmail|Either Phone Or Email||'
														.'pets|Pets||note|Note||street1|Address||street2|Address 2||city|City||state|State||zip|ZIP||'
														.'whentocall|Best time for us to call||whenserviceneeded|When do you need service?||'
														.'referralsimple|How did you hear about us? (one line)||referralcodes|How did you hear about us? (menu)');

if($_POST) {
	setPreference('prospectFormFlexibleOptionSelected', $_POST['useFlexibleProspectForm']);
	foreach(array_keys($labels) as $fn) {
		if($_POST["required_$fn"]) {
			$requireds[] = $fn;
		}
		if($_POST["optional_$fn"]) {
			$optionalFields[] = $fn;
		}
	}
//echo "BANG! ".print_r($_POST, 1);			
	setPreference('prospectFormRequiredFields', join(',',(array)$requireds));
	setPreference('prospectFormOptionalFields', join(',',(array)$optionalFields));
//echo "BANG! ".print_r(getPreference('prospectFormRequiredFields'), 1);			
	foreach(explode(',', 'prospectFormGreeting,suppressMeetingFieldsInProspectForm,enforceProspectSpamDetection,prospectFormSimpleAddress,phoneNumbersDigitsOnly,prospectFormGoBackURL,useCellphoneForProspectPhone')
			as $k)
		setPreference($k, $_POST[$k]);
	
	echo "<script language='javascript'>if(parent.updateProperty) parent.updateProperty('$property', '');parent.$.fn.colorbox.close();</script>";
}

$useFlexibleProspectForm = getPreference('prospectFormFlexibleOptionSelected');
$customStyles = ".flexitable {background:#FFEFD7;border:solid gray 1px;} .flexitable td {padding:15px;}";
$extraHeadContent = 
	'<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
	<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
	<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>	';
include "frame-bannerless.php";
?>

<h2>Client Portal Options</h2>

<?
// ==============================================================================
$homepageOptions = "||clientLoginNoticeABBREV|Client Login Notice|customlightbox|client-screen-notice-lightbox.php?narrow=1&prop=clientLoginNotice&label=Client+Login+Notice|690,460";
//$homepageOptions .= "||enableProfileChangeRequestReminder|Remind client of pending Profile Change Requests.|boolean";
$homepageOptions .= "||enableClientPendingRequests|Show Pending Requests|boolean";
$homepageOptions .= "||clientUICalendarOmitYear|Omit Year view option in client's calendar|boolean"
							."||clientUICalendarOmitDay|Omit Day view option in client's calendar|boolean"
							."||clientUICalendarOmitWeek|Omit Week view option in client's calendar|boolean"
							."||clientUICalendarOmitToday|Omit Today button in client's calendar|boolean"
							."||clientUICalendarOmitPets|Omit pet names in client's calendar|boolean"
							."||clientUICalendarOmitCanceledVisits|Omit canceled visits in client's calendar|boolean";
if(staffOnlyTEST()) $homepageOptions .= "||suppressChangeButtonOnVisits|Suppress Change Button On Visits|boolean";
$staffOnlyPreferences[] = 'suppressChangeButtonOnVisits';
							
							
// ==============================================================================
$schedulerOptions = ""
							."||clientSchedulerWelcomeNoticeABBREV|Client Scheduler Welcome Message|customlightbox|client-screen-notice-lightbox.php?prop=clientSchedulerWelcomeNotice&label=Client+Scheduler+Welcome+Message|790,600"
              ."||warnOfLateScheduling|Warn clients who schedule at the last minute|custom_boolean|lastMinuteSchedule-pref-edit.php"
              ."||simpleClientScheduleMaker|Offer simpler Visit Request form|boolean|The \"simpler\" form is just a message composer without a scheduler tool.";

$schedulerOptions .=  "||suppressClientSchedulerPriceDisplay|Suppress Price Display in Client Scheduler|boolean";
$schedulerOptions .=  "||schedulePriceFootnote|Schedule Price Footnote|string";


// ==============================================================================
$accountPageOptions = ""
						."||offerClientUIAccountPage|Offer Client Account Page|boolean"
						."||showClientPSA|Offer link in Account tab for client to review Service Agreement|boolean"
						."||hideAccountBalanceFromClient|Hide Account Balance on Client Account Page|boolean";

// ==============================================================================
$creditCardOptions = ""
              ."||clientCreditCardRequired|Client Credit Card Is Required|boolean"
              ."||offerClientCreditCardMenuOption|Offer Client Credit Card Menu Option|boolean";
if(staffOnlyTEST()) $creditCardOptions .= "||suppressClientCreditCardEntry|Do not allow clients to enter credit cards.|boolean";
if(staffOnlyTEST()) $creditCardOptions .= "||suppressClientCheckingAccountEntry|Do not allow clients to enter E-Check Accts.|boolean";

$staffOnlyPreferences[] = 'suppressClientCreditCardEntry';
$staffOnlyPreferences[] = 'suppressClientCheckingAccountEntry';


// ==============================================================================
$profilePageOptions = ""
							."||enableProfileChangeRequestReminder|Remind client of pending Profile Change Requests|boolean";
if(staffOnlyTEST()) $profilePageOptions .= "||clientOwnEditSubmitReminder|Remind clients to click Submit in the Profile Editor.|boolean";
$staffOnlyPreferences[] = 'clientOwnEditSubmitReminder';


// ==============================================================================
$otherOptions = ""
							."||offerClientUIMessageCommsPage|Offer Messages page|boolean";
if($_SESSION['preferences']['enableOfficeDocuments']) $otherOptions .= "||offerOfficeDocumentsToClients|Offer Client Documents to clients.|boolean";
if($_SESSION['preferences']['enablePhotoGalleryOption']) $otherOptions .= "||offerPhotoGalleryToClients|Offer Photo Gallery to clients|boolean";
$staffOnlyPreferences[] = 'offerOfficeDocumentsToClients';
$staffOnlyPreferences[] = 'enablePhotoGalleryOption';



// ==============================================================================
$allPageOptions = ""
							."||clientUIContactUsButton|Offer Contact Us button in banner|boolean"
							."||clientUIPhoneButton|Offer Phone button in banner|boolean"
							."||clientUIFacebookButton|Offer Facebook access in banner|string"
							."||clientUIInstagramButton|Offer Instagram access in banner|string";
$explanations['All Pages'] = "clientUIFacebookButton|A link to make a banner icon.";
$explanations['All Pages'] .= "||clientUIInstagramButton|A link to make a banner icon.";

if(staffOnlyTEST()) $allPageOptions .= "||clientOnscreenRequestAcknowledgment|Request Acknowledgment|string";
$staffOnlyPreferences[] = 'clientOnscreenRequestAcknowledgment';
if(staffOnlyTEST() || dbTEST('dogslife')) $allPageOptions .= "||chooseClientColors|Choose color scheme|customlightbox|client-ui-customizer.php|690,560";
$staffOnlyPreferences[] = 'chooseClientColors';




// ==============================================================================
$prefListSections = 
						array(
									'All Pages'=>$allPageOptions,
									'Home Page'=>$homepageOptions,
									'Scheduler'=>$schedulerOptions,
									'Account Page'=>$accountPageOptions,
									'Credit Cards'=>$creditCardOptions,
									'Profile Page'=>$profilePageOptions,
									'Other Pages'=>$otherOptions
						);
//print_r($prefListSections);						
preferencesTable($prefListSections, $help, $_REQUEST['show'], !'userprefs', $explanations);
?>


<!-- input type='button' value='open?' onclick="alert(openSections());" -->


<script language='javascript' src='common.js'></script>

<script language='javascript'>
function updateProperty(property, value) {
	//window.refresh();
<? if(mattOnlyTEST()) echo "console.log(property: ["+property+"]: "+"["+value+"]"; ?>	
	document.location.href='<?= basename($_SERVER['SCRIPT_NAME']) ?>?show='+openSections();
	document.getElementById('prop_'+property).scrollIntoView();
}

function openSections() {
	var open = new Array();
	var numClosed = 0;
	var el;
	for(var i=1; el = document.getElementById('section'+i); i++) {
		if(el.style.display == 'none') numClosed++;
		else open.push(i);
	}
	if(numClosed == 0) return 'all';
	else return open.join(',');
}

<? 
dumpPrefsJS();
dumpShrinkToggleJS();
?>
</script>