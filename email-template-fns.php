<? // email-template-fns.php 
// #STANDARD templates are not offered in compsers
// #UNDELETABLE templates ARE offered in composers
//  Neither is deletable


$standardPrefix = '#STANDARD - ';
function standardPrefix() { return '#STANDARD - '; }
function undeletablePrefix() { return '#UNDELETABLE - '; }
function getSystemPrefix($label) { return strpos($label, standardPrefix()) === 0 ? standardPrefix() : (
												strpos($label, undeletablePrefix()) === 0 ? undeletablePrefix() : null); }
																								
function getStandardTemplates() {
	$templates = array(
	'#STANDARD - Upcoming Schedule'=>
		array('type' =>'client', 'personalize'=>0, 'extratokens'=>'#SCHEDULE#',
						'body'=>"Dear #RECIPIENT#,\n\nHere is your upcoming schedule:\n\n#SCHEDULE#"),
	"#STANDARD - Client's Schedule"=>
		array('type' =>'other', 'extratokens'=>'#SCHEDULE#',
						'body'=>"#LOGO#\n\nDear #RECIPIENT#,\n\nHere is your upcoming schedule.\n\nSincerely,\n\n#BIZNAME#\n\n#SCHEDULE#"),
	"#UNDELETABLE - Client Login Credentials"=>
		array('type' =>'client',
						'body'=>"<p>#LOGO#</p><p>Hi #FIRSTNAME#,</p><p>Here are your username and password for logging in to view your account with #BIZNAME#. The password is temporary; the very next time you try to login (whether using this password or not), this password will be erased. If you login with this password, you will be asked to supply a new permanent password.</p><table cellspacing='10' align='center' bgcolor='lightblue'><tbody><tr><td>Username:</td><td bgcolor='white'><strong>#LOGINID#</strong></td></tr><tr><td>Temp Password:</td><td bgcolor='white'><strong>#TEMPPASSWORD#</strong></td></tr></tbody></table><p>If your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: <a href='https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=#BIZID#'>https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=#BIZID#</a> using the forgotten password link. To obtain a new password, you will need to supply your username (#LOGINID#) and this email address (#EMAIL#). Once you do, a new temporary password will be emailed immediately to that email address. Please contact us at #BIZEMAIL# or #BIZPHONE# if you have any questions.</p><p>Thank you,</p><p>#MANAGER#</p>"),
	"#UNDELETABLE - Sitter Login Credentials"=>
		array('type' =>'provider',
						'body'=>"<p>#LOGO#</p><p>Hi #FIRSTNAME#,</p><p>Here are your username and password for logging in to view your visits for #BIZNAME#. The password is temporary; the very next time you try to login (whether using this password or not), this password will be erased. If you login with this password, you will be asked to supply a new permanent password. Please save this email.</p><table cellspacing='10' align='center' bgcolor='lightblue'><tbody><tr><td>Username:</td><td bgcolor='white'><strong>#LOGINID#</strong></td><td>Temp Password:</td><td bgcolor='white'><strong>#TEMPPASSWORD#</strong></td></tr></tbody></table><p>If your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: <a href='https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=#BIZID#'>https://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid=#BIZID#</a> using the forgotten password link. To obtain a new password, you will need to supply your username (#LOGINID#) and this email address (#EMAIL#). Once you do, a new temporary password will be emailed immediately to that email address. Please contact us at #BIZEMAIL# or #BIZPHONE# if you have any questions.</p><p>Thank you,</p><p>#MANAGER#</p>"),
	'#STANDARD - Invoice Email'=>
		array('type' =>'other', 'personalize'=>'1',  'subject'=>'Your Invoice', 'extratokens'=>'#AMOUNTDUE#',
						'body'=>"Hi #RECIPIENT#,\n\nHere is your latest invoice.\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Invoice Autopay Email'=>
		array('type' =>'other', 'personalize'=>'1',  'subject'=>'Your Invoice', 'extratokens'=>'#AMOUNTDUE#',
						'body'=>"Hi #RECIPIENT#,<p>\n\nHere is your latest invoice reflecting the latest charge to your #PAYMENTMODE#.\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Request Honored'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Your Request has been honored', 'extratokens'=>'#REQUESTTYPE#,#REQUESTSUMMARY#,#DATE#',
						'body'=>"Dear #RECIPIENT#,\n\nWe are happy tell you that the #REQUESTTYPE# request you submitted on #DATE# has been honored.\n\n#REQUESTSUMMARY#\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Request Declined'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Your Request has been declined', 'extratokens'=>'#REQUESTTYPE#,#DATE#',
						'body'=>"Dear #RECIPIENT#,\n\nWe are sorry to tell you that we cannot honor the #REQUESTTYPE# request you submitted on #DATE#.\n\n#REQUESTSUMMARY#\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Request Resolved'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Your Request has been resolved', 'extratokens'=>'#REQUESTTYPE#,#DATE#',
						'body'=>"Dear #RECIPIENT#,\n\nWe are writing to tell you that the  #REQUESTTYPE# request you submitted on #DATE# has been resolved.\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Visit Completion Reminder'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Visit Completion Reminder', 'extratokens'=>'#VISITS#',
						'body'=>"Dear #FIRSTNAME#,\r\n\r\nPlease mark the following visits and surcharges complete or canceled, as appropriate:\r\n\r\n#VISITS#\r\n\r\nThank you,\r\n\r\n#BIZNAME#"),
	'#STANDARD - Visit Change Notification'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Pet Care Services Update', 'extratokens'=>'##ConfirmationRequestText##',
						'body'=>"Dear #RECIPIENT#,\n\nWe have made changes to a scheduled visit.  Please review the visit and schedule details for correctness.\n\n##ConfirmationRequestText##\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - New Schedule Notification'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Pet Care Services Update', 'extratokens'=>'##ConfirmationRequestText##',
						'body'=>"Dear #RECIPIENT#,\n\nWe have set up the following service schedule for you.  Please review the schedule details for correctness.\n\n##ConfirmationRequestText##\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Schedule Change Notification'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Pet Care Services Update', 'extratokens'=>'##ConfirmationRequestText##',
						'body'=>"Dear #RECIPIENT#,\n\nWe have made changes to your service schedule.  Please review the schedule details for correctness.\n\n##ConfirmationRequestText##\n\nSincerely,\n\n#BIZNAME#"),
	'#STANDARD - Upcoming Sitter Schedule' =>
		array('type' =>'provider', 'personalize'=>0, 'extratokens'=>'#SCHEDULE#',
						'body'=>"Dear #RECIPIENT#,\n\nHere is your upcoming schedule:\n\n#SCHEDULE#"),
	'#STANDARD - Meeting Set Up'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Meeting Set Up', 'extratokens'=>'#DATE#,#TIME#,#PETS#',
						'body'=>"Dear #FIRSTNAME#,\r\n\r\nWe look forward to meeting with you on #DATE# at #TIME#.\r\n\r\nThank you,\r\n\r\n#BIZNAME#"),
	'#STANDARD - Sitter Schedule Email'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Your Schedule', 'extratokens'=>'#DATE#,#TIME#,#PETS,#SCHEDULE#',
						'body'=>"Hi #RECIPIENT#,\r\n\r\nHere is your schedule.<p>Sincerely,\r\n\r\n#BIZNAME#\r\n\r\n#SCHEDULE#"),
	'#STANDARD - Send Client Schedule Request to Sitter'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Schedule Request from #CLIENTNAME#', 
						'extratokens'=>'#REQUESTEDSCHEDULE#, #CLIENTNAME#, #CLIENTADDRESSONELINE#, #CLIENTADDRESSTHREELINE#'
														.'<br>Subject tokens: #CLIENTNAME#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#',
						'body'=>"Hi #FIRSTNAME#,\n\nPlease let us know if you are interested in the following schedule.\n\nSincerely,\n\n#BIZNAME#\n\n<hr>#REQUESTEDSCHEDULE#"),
	'#STANDARD - Send General Client Request to Sitter'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'General Request from #CLIENTNAME#', 
						'extratokens'=>'#REQUESTDESCRIPTION#, #CLIENTNAME#, #CLIENTADDRESSONELINE#, #CLIENTADDRESSTHREELINE#'
														.'<br>Subject tokens: #CLIENTNAME#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#',
						'body'=>"Hi #FIRSTNAME#,\n\nWe received the following request.\n\nSincerely,\n\n#BIZNAME#\n\n<hr>#REQUESTDESCRIPTION#"),													
	'#STANDARD - Send Prospect Request to Sitter'=>
		array('type'=>'other', 'personalize'=>'1',  'subject'=>'Prospect Request from #CLIENTNAME#', 
						'extratokens'=>'#REQUESTDESCRIPTION#, #CLIENTNAME#, #CLIENTADDRESSONELINE#, #CLIENTADDRESSTHREELINE#'
														.'<br>Subject tokens: #CLIENTNAME#, #RECIPIENT#, #FIRSTNAME#, #LASTNAME#',
						'body'=>"Hi #FIRSTNAME#,\n\nWe received the following request.\n\nSincerely,\n\n#BIZNAME#\n\n<hr>#REQUESTDESCRIPTION#")														
	);
	
	$templates['#STANDARD - Sitter Arrived'] = array('type'=>'other', 'personalize'=>'1',
		'subject'=>'Sitter arrival',
		'body'=>"Hi #RECIPIENT#,\n\nThis note is to inform you that #SITTER# arrived to care for #PETS# at your home on #DATE# at #TIME#.\n\nSincerely,\n\n#BIZNAME#",
		'extratokens'=>'#DATE#, #TIME#');
	
	$templates['#STANDARD - Visit Completed'] = array('type'=>'other', 'personalize'=>'1',
		'subject'=>'Visit completed',
		'body'=>"Hi #RECIPIENT#,\r\n\r\nThis note is to inform you that #SITTER# finished a visit to care for #PETS# at your home on #DATE# at #TIME#.#IF_NOVISITNOTE#\r\n\r\nIt is always a pleasure to visit with #PETS#.#END_NOVISITNOTE##IF_VISITNOTE#<hr>Visit note:\r\n\r\n#VISITNOTE#<hr>#END_VISITNOTE#\r\n\r\nSincerely,\r\n\r\n#BIZNAME#",
		'extratokens'=>'#DATE#, #TIME#, #SITTERNAME#, #SITTERFIRSTNAME#, #IF_NOVISITNOTE#, #END_NOVISITNOTE#, #IF_VISITNOTE#, #VISITNOTE#, #END_VISITNOTE#');
	
	
	
	
	
	$templates['#STANDARD - Upcoming Holiday Visits Cancellation'] = array('type'=>'other', 'personalize'=>'1',
		'subject'=>'Upcoming Holiday Visits Cancellation',
		'body'=>"Dear #RECIPIENT#,\n\nThe following visit(s) fall on a Holiday.  Please click one of the following links to <a href='##ConfirmationURL_CANCEL##'>Cancel the visits</a> or <a href='##ConfirmationURL_RETAIN##'>Confirm the visits</a>:\n\n#VISIT_TABLE#\n\nSincerely,\n#BIZNAME#");
	if($_SESSION['preferences']['enableSitterProfiles']) 
		$templates['#STANDARD - Sitter Profile'] = array('type'=>'other', 'personalize'=>'1',
			'subject'=>'Sitter Profile',
			'body'=>"Hi #FIRSTNAME#,\n\nHere is the profile of your sitter, #SITTERNAME#.\n\nSincerely,\n\n#BIZNAME#\n\n#SITTERPROFILE#");

	//if($_SESSION['preferences']['homeSafeEnabled']) 
		$templates['#STANDARD - Send Home Safe Request to Client'] = array('type'=>'other', 'personalize'=>'1',
			'subject'=>'Please let us know you are home safe',
			'body'=>"Hi #FIRSTNAME#,\n\nOur last visit to your home is scheduled for #LASTVISIT#.\n\nPlease let us know when you have gotten home so that we will know your pets are safe.\n\nJust <a href='#RESPONSEURL#'>click here</a> to let us know.\n\nSincerely,\n\n#BIZNAME#");

	if(dbTEST('dogslife,leashtimecustomers')|| $_SESSION['preferences']['enableChargeEmailTemplates']) {
		$templates['#STANDARD - Credit Card/Bank Account Charged'] = array('type'=>'other', 'personalize'=>'1',
			'subject'=>'Charge to your #PAYMENTTYPE#',
			'extratokens'=>'#PAYMENTTYPE#, #PAYMENTSOURCE#, #PAYMENTAMOUNT#, #GRATUITY#, #TRANSACTIONID#.<br>In Subject: #PAYMENTTYPE#',
			'body'=>"Dear #RECIPIENT#,\n\nThis note is to inform you that we have charged your #PAYMENTTYPE# "
									. "(#PAYMENTSOURCE#) in the amount of #PAYMENTAMOUNT##GRATUITY#.  (Transaction ##TRANSACTIONID#)\n\nThank you for your business.\n\nSincerely,\n\n#BIZNAME#"
							);
		$templates['#STANDARD - Thanks for your Credit Card/Bank Account Payment'] = array('type'=>'other', 'personalize'=>'1',
			'subject'=>'Thanks for your payment!',
			'extratokens'=>'#PAYMENTTYPE#, #PAYMENTSOURCE#, #PAYMENTAMOUNT#, #GRATUITY#, #TRANSACTIONID#.<br>In Subject: #PAYMENTTYPE#',
			'body'=>"Dear #RECIPIENT#,\n\nThis note is to thank you for your #PAYMENTTYPE# payment "
									. "(#PAYMENTSOURCE#) in the amount of #PAYMENTAMOUNT##GRATUITY#.  (Transaction ##TRANSACTIONID#)\n\nThank you for your business.\n\nSincerely,\n\n#BIZNAME#"
							);
							
	}
	if($_SESSION['preferences']['enableIncompleteScheduleNotifications'])
		$templates['#STANDARD - Problems Scheduling?'] =
			array('type'=>'client', 'personalize'=>'1',  'subject'=>'Having Problems Scheduling?', 'extratokens'=>'',
							'body'=>"Dear #RECIPIENT#,\n\nIt looks like you tried to request a schedule but we did not receive the request."
							."\n\nIf you did intend to submit a schedule request, please contact us by email at #BIZEMAIL# or give us a call at #BIZPHONE#.\n\nSincerely,\n\n#BIZNAME#");

	if($_SESSION['preferences']['enableGratuitySoliciation'])
		$templates['Gratuity Solicitation'] =
			array('type'=>'client', 'personalize'=>'1',  'subject'=>'Please support your walker', 'extratokens'=>'#GRATUITYLINK#, #GRATUITYLOOKBACK#, #ENDGRATUITYLOOKBACK#',
							'body'=>
								"Dear #FIRSTNAME#,\n\nDuring the coronavirus pandemic, please remember your pet's caregivers,"
								." who may be suffering a drastic cut to their income.\n\nYou can go to our <a href=\"#GRATUITYLINK#\">gratuity page</a>"
								." to help out.  Even a small gift can make a big difference.\n\nThank you for your generosity in this difficult time,\n\n"
								."#BIZNAME#\n\n#GRATUITYLOOKBACK#90#ENDGRATUITYLOOKBACK#"
							);
	ksort($templates);
	return $templates;
}



function ensureStandardTemplates($type=null) {
	if(!$type) {
		foreach(array('client', 'provider', 'other') as $type)
			ensureStandardTemplates($type);
		return;
	}
	$data = array();
	$templates = fetchAssociationsKeyedBy("SELECT * FROM tblemailtemplate WHERE targettype = '$type' ORDER BY label", 'label');
	$rowClasses = array();
//deleteTable('tblemailtemplate', "label = '#STANDARD - Client Login Credentials'", 1);
	foreach(getStandardTemplates() as $stndlabel=>$temp) {
//if(strpos($stndlabel, undeletablePrefix()) !== FALSE && !dbTEST('dogslife,tonkapetsitters')) continue;
		if(!isset($templates[$stndlabel]) && $temp['type'] == $type) {
			$systemPrefix = getSystemPrefix($stndlabel);
			$subject = $temp['subject'] ? $temp['subject'] : substr($stndlabel, strlen($systemPrefix)); // problems with the global $standardPrefix....
			$template = array('label'=>$stndlabel, 
													'subject'=>$subject, 
													'targettype'=>$type, 'personalize'=>0, 
												'salutation'=>'', 'farewell'=>sqlVal("''"), 'active'=>1,
												'extratokens' => ($temp['extratokens'] ? $temp['extratokens'] : sqlVal("''")),
												'body'=>$temp['body']);
			$id = insertTable('tblemailtemplate', $template, 1);
			$template['templateid'] = $id;
			$templates = array_reverse($templates);
			$templates[] = $template;
			$templates = array_reverse($templates);
		}
	}

	return $templates;
}



function getOrganizationEmailTemplates($target) {
	// find this biz's organization
	if($_SESSION["orgptr"]) {
		list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
		require "common/init_db_common.php";
		$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]}");
		reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
		$templates = fetchAssociations(
			"SELECT *
				FROM tblemailtemplate 
				WHERE targettype = '$target' 
				AND active = 1
				AND published = 1
				ORDER BY label");		// if zip known and protected, echo message about contacting
		reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
		return $templates;
	}
}

function getOrganizationEmailTemplateOptions($target) {
	// find this biz's organization
	$orgTemplates = getOrganizationEmailTemplates($target);
	if($orgTemplates) foreach($orgTemplates as $template)
		$options[$template['label']] = "O_".$template['templateid'];
	return $options;
}

function fetchOrganizationTemplate($id) {
	global $dbhost, $db, $dbuser, $dbpass;
	if(strpos($id, 'O_') === 0) $id = substr($id, strlen('O_'));
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	
	$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]}");
	reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
	$template = fetchFirstAssoc("SELECT * FROM tblemailtemplate WHERE templateid = $id LIMIT 1", 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $template;
}
	
function templateLogoIMG($attributes='') { // convenience method copied from logoIMG() in comm-composer-fns.php
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='https://{$_SERVER["HTTP_HOST"]}/$headerBizLogo' $attributes>" :'';
}	

function preprocessTemplateMessage($message, $target, $template) {
	// NOTE: getClientSchedule, figureClientPrice, ccDescription are still in email-template-fetch.com
	if(strposAny($message, array('#LOGINID#', '#TEMPPASSWORD#')))
		$creds = templateLoginCreds($target);
	if(FALSE && strposAny($message, array('#CLIENTADDRESSONELINE#', '#CLIENTADDRESSTHREELINE#'))) {
		$oneLineClientAddress = str_replace("'", "&apos;", oneLineAddress($clientAddress));
		$threeLineClientAddress = str_replace("'", "&apos;", htmlFormattedAddress($clientAddress));
	}
	if(strpos($message, '#CREDITCARD#') !== FALSE)
		$cc = ccDescription($target);
	if($target['clientid'] && strpos($message, '#PETS#') !== FALSE) {
		require_once "pet-fns.php";
		if($_REQUEST['appointment']) $petnames = getAppointmentPetNames($_REQUEST['appointment'], $petnames=null, $englishList=true);
		else $petnames = getClientPetNames($target['clientid'], false, true);
	}
	if($target['clientid'] && strpos($message, '#ACCOUNTBALANCEORPAID#') !== FALSE) {
		require_once "invoice-fns.php";
		$accountBalance = getAccountBalance($target['clientid'], $includeCredits=false, $allBillables=false);
		$accountBalanceOrPaidSub = $accountBalance ? dollarAmount($accountBalance) : 'PAID';
	}
	$managerNickname = fetchRow0Col0(
		"SELECT value 
			FROM tbluserpref 
			WHERE userptr = {$_SESSION['auth_user_id']} AND property = 'managerNickname' LIMIT 1");
			
	$merges = array(
			'#RECIPIENT#' => "{$target['fname']} {$target['lname']}",
			'#FIRSTNAME#' => $target['fname'],
			'#LASTNAME#' => $target['lname'],
			// #EMBEDDEDLOGOSRC# is handled as an attachment in comm-composer and email-swiftmailer-fns.php
			'#EMBEDDEDLOGO#' => templateEmbeddedlogoIMG(),
			'#LOGO#' => templateLogoIMG(),
			'#BIZNAME#' => $_SESSION['preferences']['shortBizName'],
			'#BIZID#' => $_SESSION["bizptr"],
			'#BIZHOMEPAGE#' => $_SESSION['preferences']['bizHomePage'],
			'#BIZLOGINPAGE#' => "http://{$_SERVER["HTTP_HOST"]}/login-page.php?bizid={$_SESSION['bizptr']}",
			'#BIZEMAIL#' => $_SESSION['preferences']['bizEmail'],
			'#BIZPHONE#' => $_SESSION['preferences']['bizPhone'],
			'#MANAGER#' => ($managerNickname ? $managerNickname : $_SESSION["auth_username"]),
			'#CREDITCARD#' => $cc,
			'#LOGINID#' => $creds['loginid'],
			'#CLIENTID#' => $target['clientid'],
			'#ACCOUNTBALANCEORPAID#' => html_entity_decode(str_replace('&nbsp;', '', "$accountBalanceOrPaidSub")),
			'#TEMPPASSWORD#' => $creds['temppassword'],
			'#PETS#' => $petnames,
			'#EMAIL#' => $target['email'],
			'#DATE#' => longDayAndDate(strtotime($target['received'])), // prospect request
			'#ADMINONLY#' => '', // for MANAGERS/DISPATCHERS
			//'#CLIENTADDRESSONELINE#' => $oneLineClientAddress,
			//'#CLIENTADDRESSTHREELINE#' => $threeLineClientAddress,

			'##FullName##' => $corrName,
			'##FirstName##' => $correspondent['fname'],
			'##LastName##' => $correspondent['lname'],
			'##Provider##' => $corrName,
			'##BizName##' => $_SESSION['bizname']			
			);
	if($_REQUEST['clientrequest']) {
		require_once "request-fns.php";
		$request = fetchFirstAssoc("SELECT * FROM tblclientrequest WHERE requestid = '{$_REQUEST['clientrequest']}' LIMIT 1", 1);
		$merges['#REQUESTTYPE#'] = $requestTypes[$request['requesttype']];
		$merges['#DATE#'] = longDayAndDate(strtotime($request['received']));
		
		/*if(in_array($request['requesttype'], array('cancel', 'uncancel'))) {
			//day_2012-03-05 	sole_136405
			$requestSummary = "You requested that we {$request['requesttype']} ";
			$scope = $request['scope'];
			if(strpos($scope, 'day_') === 0) 
				$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
			else {
				$appt = 
					fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
				$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
			}
		}*/

// #######################################################

		if(in_array($request['requesttype'], array('cancel', 'uncancel'))) {
			//day_2012-03-05 	sole_136405
			$requestSummary = "You requested that we {$request['requesttype']} ";
			$scope = $request['scope'];
			if(strpos($scope, 'day_') === 0) 
				$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
			else {
				$appt = 
					fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
				$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
			}
		}

		if($request['requesttype'] == 'change') {
			//day_2012-03-05 	sole_136405
			$requestSummary = "You requested that we change ";
			$scope = $request['scope'];
			if(strpos($scope, 'day_') === 0) 
				$requestSummary .= "all visits on ".longDayAndDate(strtotime(substr($scope, strlen('day_'))));
			else {
				$appt = 
					fetchFirstAssoc("SELECT date, timeofday FROM tblappointment WHERE appointmentid = ".substr($scope, strlen('sole_'))." LIMIT 1", 1);
				$requestSummary .= "a visit on ".longDayAndDate(strtotime($appt['date']))." at {$appt['timeofday']}";
			}
			$requestSummary .= ".<p>You wrote:<p>".(trim($request['note']) ? "<i>".trim($request['note'])."</i>" : "<i>You gave us no instructions.</i>");
		}

// #######################################################

		
		$merges['#REQUESTSUMMARY#'] = $requestSummary;
	}
	


	$message = mailMerge($message, $merges);
}
	$hasHtml = strpos($message, '<') !== FALSE;
	if($hasHtml) {
		$message = str_replace("\r", "", $message);
		$message = str_replace("\n\n", "<p>", $message);
		$message = str_replace("\n", "<br>", $message);
	}
	if(FALSE && strpos($message, '#SCHEDULE#') !== FALSE)
		$message = mergeUpcomingSchedule($message, $target);
	return $message;
}

if(!function_exists('mailMerge')) {
function mailMerge($message, $values) {
	if($message)
		foreach($values as $key => $sub) 
			if($key) $message = str_replace($key, $sub, $message);
	return $message;
}
}

function processAdHocSubstitutions($message) {
	// $message may contain a list of substitutions in the following form.
	// read that form, remove it from $message, and then perform the substitutions.
	// #ADHOCSUBS# pattern1|sub1 || pattern2|sub2... #ENDADHOCSUBS#
	if(($start = strpos($message, '#ADHOCSUBS#')) === FALSE
			|| ($end = strpos($message, '#ENDADHOCSUBS#')) === FALSE) 
		return $message;
	$block = substr($message, $start, $end+strlen( '#ENDADHOCSUBS#') - $start);
	$message = str_replace($block, '', $message);
	$adhocsubs = substr($block, strlen( '#ADHOCSUBS#'), strpos($block, '#ENDADHOCSUBS#') - strlen( '#ADHOCSUBS#'));
	$adhocsubs = explode('||', $adhocsubs);
	foreach($adhocsubs as $sub) {
		$parts = explode('|', trim($sub));
		if($parts[0])
			$message = str_replace($parts[0], $parts[1], $message);
	}
	return $message;
}

if(!function_exists('strposAny')) {
	function strposAny($str, $list) {
		foreach((array)$list as $candidate)
			if(strpos($str, $candidate) !== FALSE)
				return true;
	}
}

function templateLoginCreds($target) {
	if(!$target['userid']) return array('loginid'=>'NO LOGIN ID FOUND FOR USER', 'temppassword'=>'NO TEMP PASSWORD FOUND FOR USER');
	global $dbhost, $db, $dbuser, $dbpass;
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$creds = fetchFirstAssoc("SELECT loginid, temppassword, userid FROM tbluser WHERE userid = {$target['userid']} LIMIT 1");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1, true);
	return $creds;
}

function templateEmbeddedlogoIMG($attributes='') {
	// #EMBEDDEDLOGOSRC# is handled as an attachment in comm-composer and email-swiftmailer-fns.php
	$headerBizLogo = getHeaderBizLogo($_SESSION["bizfiledirectory"]);
	return $headerBizLogo ? "<img src='#EMBEDDEDLOGOSRC#' $attributes>" :'';
}	

