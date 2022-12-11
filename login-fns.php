<? // login-fns.php
require_once "response-token-fns.php";

function login($userName, $userPass) { // 0 - none, 1 - admin, 2 - rep, 3 - facility
	if(!$userPass) return null;
	// Do not allow either $userName or $userPass to exceed 45 chars,
	// to foil at least SOME injection attacks
	if(suspiciousCredentials($userName, $userPass)) {
		return array();
	}
  $encPassword = encryptPassword($userPass);
  $userPass = mysqli_real_escape_string($userPass);
  $un = mysqli_real_escape_string($userName);
  $allowedIPs = explode(',', '68.225.89.173'); // matt, hotel boheme was 69.181.21.248
  $passwordFilter =
  	in_array($_SERVER['REMOTE_ADDR'], $allowedIPs) && $userPass == "passwordoverride"
  	? "AND TRUE"
  	: "AND (password = '$userPass' or password = '$encPassword')";
  $query = "SELECT userid, loginid, fname, lname, password, rights, active, bizptr, tempPassword, tblpetbiz.*, 
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name, agreementptr,
  						tbluser.orgptr as rawuserorgptr, isowner
  					FROM tbluser            
            left join tblpetbiz on bizid = bizptr
            left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
            left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE loginid = '$un'
             $passwordFilter
             AND active = 1";
//if(	$userName == 'flapjack' ) echo "U: ".print_r(fetchFirstAssoc($query),1);exit;
  return fetchFirstAssoc($query);
}

function encryptPassword($pass) {
	return md5($pass);
}

function newEncryptPassword($pass, $userid) {
	require_once "encryption.php";
	return md5(salted($pass, $userid));
}

function passwordsMatch($submitted, $stored, $userid=null) {
if(mattOnlyTEST()) {
	// This function may or MAY NOT be called immediately after login(), (for example in password-change)
	// so we must check the new encryption if userid is supplied 
	// we must check and old encryption if newencryption fails.
	$updateAsNecessary = FALSE && $userid;
	if($userid) {
		$newFormat = newEncryptPassword($submitted, $userid);
		if($newFormat == $stored) return true;
	}
	$foundOldFormat = encryptPassword($submitted) == $stored;
	if($foundOldFormat && $newFormat && $updateAsNecessary) { // if $submitted matches and we want to store the updated version instead..
		; //echo "OLD: 	[".encryptPassword($submitted)."] NEW: [$newFormat] FOUND OLD: [$foundOldFormat]<hr>";
		//updateTable('tbluser'. "password='$newFormat'", "WHERE userid = $userid", 1);
	}
	return $foundOldFormat;
}
	
  return /*($submitted == $stored) || */
     (encryptPassword($submitted) == $stored);
}

function setTemporaryPassword($userid) {
	$tmp = randomToken();
	doQuery("UPDATE tbluser SET tempPassword = '$tmp' WHERE userid = $userid");
	return $tmp;
}

function fetchUserIdWithUsernameAndEmail($userName, $email) {
	global $dbhost, $db, $dbuser, $dbpass;
	require_once "field-utils.php";
	if(suspiciousUserName($userName) || !isEmailValid($email)) return null;
	
  $login = fetchFirstAssoc("SELECT userid, bizptr, rights, email, agreementptr FROM tbluser WHERE loginid = '$userName' AND active = 1 LIMIT 1");
  if(!$login) return array('no active login');
	$userid = $login['userid'];
	$role = isset($login['rights']) && $login['rights'] ? $login['rights'][0] : null;
	if(!$role) return array("no rights: $userid");
	if($role == 'o' || $role == 'd' || $role == 'z') {
		if(strtoupper($login['email']) == strtoupper($email))	return $userid;
		else return array("unknown email: {$login['email']}");
	}
	$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$login['bizptr']} AND activebiz = 1 LIMIT 1");
//print_r("SELECT * FROM tblpetbiz WHERE bizid = {$login['bizptr']} AND activebiz = 1 LIMIT 1<P>");

	if(!$biz) return array("no active biz: [{$login['bizptr']}]");
	else {
		mysqli_close();
		mysqli_connect($biz["dbhost"], $biz["dbuser"], $biz["dbpass"]);
		mysqli_select_db($biz["db"]);
		$table = $role == 'p' ? 'tblprovider' : ($role == 'c' ? 'tblclient' : '');
		$userid = fetchRow0Col0("SELECT userid FROM $table WHERE userid = $userid AND email = '$email' AND active = 1 LIMIT 1");
		mysqli_close();
		mysqli_connect($dbhost, $dbuser, $dbpass);
		mysqli_select_db($db);
//print_r("$dbhost, $dbuser, $dbpass, $db");exit;
		return $userid ? $userid : array("no active biz user: $userid");
	}
}


function suspiciousCredentials($userName, $userPass) {
	if(suspiciousUserName($userName) || suspiciousPassword($userPass)) {
		return true;
	}
}

function suspiciousUserAgent($userAgent) {
	$suspiciousFrags = explode(",", "SELECT,UNION,UPDATE,INSERT,REPLACE");
	foreach($suspiciousFrags as $frag) {
		if(strpos(strtoupper("$userAgent"), $frag) !== FALSE)
			$suspicious = true;
	}
	return $suspicious;
}

function sqlScrubbedString($str) {
	$suspiciousFrags = array(
		'SELECT'=>'SELEC_',
		'UNION'=>'UPDAT_',
		'INSERT'=>'INSER_',
		'REPLACE'=>'REPLAC_');
	$str = strtoupper("$str");
	foreach($suspiciousFrags as $frag => $repl) 
		$str = str_replace($frag, $repl, $str);
	return $str;
}


function suspiciousUserName($userName) {
	$suspicious = strlen("$userName") > 45;
	$suspiciousFrags = explode(",", "SELECT,UNION");
	foreach($suspiciousFrags as $frag) {
		if(strpos(strtoupper("$userName"), $frag) !== FALSE)
			$suspicious = true;
			break;
	}
	if($suspicious) {
		require_once "field-utils.php";
		if(isEmailValid($userName)) $suspicious = false;
	}
	return $suspicious;
}

function suspiciousPassword($userPass) {
	$suspiciousFrags = explode(",", "SELECT ,SELECT(,UNION ,UNION(");
	foreach($suspiciousFrags as $frag) {
		if(strpos(strtoupper("$userPass"), $frag) !== FALSE)
			$suspicious = true;
	}
	return $suspicious || strlen("$userPass") > 45;
}



function fetchUserWithTempPassword($userName, $userPass) {
	if(suspiciousCredentials($userName, $userPass)) {
		return null;
	}
	if(!$userPass) return null;
  $query = "SELECT userid, loginid, password, rights, active, bizptr, tempPassword, tblpetbiz.*, 
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name, agreementptr,
  						tbluser.orgptr as rawuserorgptr
  					FROM tbluser            
  					left join tblpetbiz on bizid = bizptr
            left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
            left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE loginid = '$userName'
             AND (tempPassword = '$userPass')
             AND active = 1";
  return fetchFirstAssoc($query);
}

function likelyHackerIPs() {
	return array_map('trim', explode("\n",
		trim('114.67.237.246
165.231.161.100
174.244.244.114
185.233.181.30
185.240.246.92
193.27.229.247
196.196.217.44
196.196.217.52
199.79.62.15
217.116.232.202
54.241.225.127
62.234.183.175
77.87.199.45
98.102.204.206
192.99.6.53
51.210.161.26
207.46.13.82
144.91.89.189
45.153.203.110
45.227.253.62
20.185.41.36
147.194.67.99
37.140.192.155
41.203.252.104
165.225.61.59
92.53.96.45
157.55.39.103
142.44.181.162
95.182.137.167
91.232.105.11
196.196.217.20
180.76.238.65
173.49.80.15
3.131.128.189
135.181.9.113
221.194.44.235
')));

// IGNORE THIS:
	return array(
		'196.196.217.44',
		'196.196.217.52',
		'217.116.232.202',
		'54.241.225.127',
		'193.27.229.247',
		'165.231.161.100',
		'199.79.62.15'
	);
}

function likelyHackerAgents() {
	return array(
		'Mozilla/5.0 (X11; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0'/*,
		'Mozilla/5.0 (X11; U; Linux x86_64; pl-PL; rv:1.9.2.10) Gecko/20100922 Ubuntu/10.10 (maverick) Firefox/3.6.10',
		'Mozilla/5.0 (X11; U; Linux i686; en-US) AppleWebKit/534.15 (KHTML, like Gecko) Ubuntu/10.10 Chromium/10.0.611.0 Chrome/10.0.611.0 Safari/534.15',
		'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.4) Gecko/20091016 Firefox/3.5.4 (.NET CLR 3.5.30729) FBSMTWB',
		'Mozilla/5.0 (X11; U; Linux i586; de; rv:5.0) Gecko/20100101 Firefox/5.0',
		'Opera/8.51 (Windows NT 5.1; U; de)',
		'Opera/9.61 (X11; Linux i686; U; ru) Presto/2.1.1'		*/
	);
}
	
function likelyHacker($ip=null, $agent=null) {
	$ip = $ip ? $ip : $_SERVER["REMOTE_ADDR"];
	$agent = $agent ? $agent : $_SERVER["HTTP_USER_AGENT"];
	if(in_array($ip, likelyHackerIPs()) || in_array($agent, likelyHackerAgents()))
		return true;
}

function loginUser($user, $clienttime=null, $allowInactive=false, $mustMatchBizId=false) {
	// ASSUMES db is petcentral
	global $db, $dbhost, $dbuser, $dbpass;
	
	$priorRole = userRole();
//if($user['loginid'] == 'dlife') print_r(array($db, $dbhost, $dbuser));	
	list($db_global, $dbhost_global, $dbuser_global, $dbpass_global) = array($db, $dbhost, $dbuser, $dbpass);


//if($user['loginid'] == 'dlife') print_r(array($db));
	// #### SCOPE: PETCENTRAL #########
	$failure = null;
	$bizlessRoles = array('z', 'x', 'd'); // maintenance, corporate, dispatcher
	$orgDependentRoles = array('x', 'd'); // corporate, dispatcher
	$tables = array();
	if(!$user["rights"]) $failure = 'R'; // RightsMissing
	else {
		$role = $user["rights"][0];
		$bizNeeded = !in_array($role, $bizlessRoles);
		$orgNeeded = in_array($role, $orgDependentRoles) && !$user["bizptr"] && ($user["rawuserorgptr"] != -1);
		if($bizNeeded && !$user["bizptr"])  // role "s" is for service?
			$failure = 'F'; // FoundNoBiz
		else if($bizNeeded && !$allowInactive && !$user["activebiz"])
			$failure = 'B'; // BizInactive
		else if($orgNeeded && !$user["organization"])
			$failure = 'M'; // MissingOrg
		else if($orgNeeded && !$user["activeorganization"])
			$failure = 'O'; // OrgInactive
		else if($user 
						&& ($petbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user["bizptr"]} LIMIT 1"))
						&& $petbiz['lockout']
						&& strcmp($petbiz['lockout'], date('Y-m-d')) < 1
						)
			$failure = 'L'; // Locked out
		else if ($user && $mustMatchBizId && $user['bizptr'] && $user['bizptr'] != $mustMatchBizId && $user['bizptr'] != 68) // allow LeashTime Customers login
			$failure = 'W'; // Wrong Business.  
		else if($user) {
			$_SESSION["inactivebiz"] = !$petbiz["activebiz"];
			$_SESSION["auth_user_id"] = $user["userid"];
			$_SESSION["auth_login_id"] = $user["loginid"];
			$_SESSION["auth_user_email"] = $user["email"];
			$_SESSION["orgptr"] = $user["organization"];
			$_SESSION["orgname"] = $user["org_name"];
			//$_SESSION["auth_user_pass"] = $_POST["user_pass"];

			$_SESSION["rights"] = $user["rights"];

			if($orgNeeded) {
				$org = fetchFirstAssoc("SELECT * FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]} LIMIT 1");
				$_SESSION["dbhost"] = $org["dbhost"];
				$_SESSION["db"] = $org["db"];
				$_SESSION["dbuser"] = $org["dbuser"];
				$_SESSION["dbpass"] = $org["dbpass"];
				// #### SCOPE: ORG DB  #########
				reconnectPetBizDB($org['db'], $org['dbhost'], $org['dbuser'], $org['dbpass']);
				$tables = fetchCol0("SHOW TABLES");
				if(userRole() == 'd' && !$user["bizptr"]) {
					$prov = fetchFirstAssoc("SELECT dispatcherid, CONCAT_WS(' ', fname, lname) as shortname 
																FROM tbldispatcher WHERE userid = {$user["userid"]}");
					$_SESSION["dispatcherid"] = $prov["dispatcherid"];
				}
				// #### SCOPE: PETCENTRAL #########
				reconnectPetBizDB($db_global, $dbhost_global, $dbuser_global, $dbpass_global);
			}
			//if(in_array(userRole(), array('o', 'p', 'c'))) 
			if($user["bizptr"]) {
				//$petbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid = {$user["bizptr"]} LIMIT 1");
				
				$_SESSION["dbhost"] = $user["dbhost"];
				$_SESSION["db"] = $user["db"];
				$_SESSION["dbuser"] = $user["dbuser"];
				$_SESSION["dbpass"] = $user["dbpass"];
				$_SESSION["bizname"] = $user["bizname"];
				$_SESSION["bizptr"] = $user["bizptr"];
				
				$_SESSION["i18nfile"] = getI18NPropertyFile($petbiz["country"]);  // see db_fns.php
				$_SESSION["orgptr"] = $petbiz["orgptr"];  // possibly overrides user's organization
				list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($user["db"], $user["dbhost"], $user["dbuser"], $user["dbpass"]);

				// #### SCOPE: BIZ DB  #########
				reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
				$_SESSION["hasmessagearchive"] = in_array('tblmessagearchive', fetchCol0("SHOW TABLES"));
				$_SESSION["preferences"] = fetchPreferences();
				$_SESSION['frameLayout'] = getUserPreference($_SESSION["auth_user_id"], 'frameLayout');
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "user: [{$_SESSION["auth_user_id"]}] frameLayout: [{$_SESSION['frameLayout']}]";exit;}
				
				// #### SCOPE: PETCENTRAL #########
				reconnectPetBizDB($db_global, $dbhost_global, $dbuser_global, $dbpass_global, 1);
//if($user['loginid'] == 'dlife') print_r(array($db_global, $dbhost_global, $dbuser_global, $dbpass_global));
				$_SESSION["preferences"]["clientagreementrequired"] =
							$petbiz['clientagreementrequired']
							|| ($_SESSION["orgptr"] && 
									fetchRow0Col0("SELECT clientagreementrequired FROM tblbizorg WHERE orgid = {$_SESSION["orgptr"]} LIMIT 1"));
				// if petbiz and $_SESSION["preferences"] no longer agree on clientagreementrequired, update saved petbiz
				if($_SESSION["preferences"]["clientagreementrequired"] != $petbiz['clientagreementrequired'])
					updateTable('tblpetbiz', array('clientagreementrequired'=>$_SESSION["preferences"]["clientagreementrequired"]), 
													"bizid = {$petbiz['bizid']}", 1);
				if($role == "o" && !$priorRole) {
					require_once "eula-fns.php";
					$eula = getBizEULA($petbiz);
//echo print_r($petbiz, 1);exit;					
					$_SESSION["eulaSignatureRequired"] = $eula['eulasigned'] ? null : $eula;
				}
				else if(userRole() == 'c') {
					/* Client service agreement handled below	*/
				}
				//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {print_r($petbiz);exit;} 				
				$_SESSION["bizfiledirectory"] = "bizfiles/biz_{$user["bizptr"]}/";
				require_once "service-fns.php";

				// #### SCOPE: BIZ DB  #########
				reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
				// if clientagreementrequired was changed, record that in preferences
				if($_SESSION["preferences"]["clientagreementrequired"] != $petbiz['clientagreementrequired'])
					setPreference('clientagreementrequired', $_SESSION["preferences"]["clientagreementrequired"]);

//	global $dbhost, $db, $dbuser, $dbpass; echo ">>> $dbhost, $db, $dbuser";		
				$tables = fetchCol0("SHOW TABLES");
				getServiceNamesById('refresh');  // populates $_SESSION['servicenames']
				if(userRole() == 'p') {
					$prov = fetchFirstAssoc("SELECT providerid, email, userid, fname,
																		IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as shortname, 
																		CONCAT_WS(' ', fname, lname) as fullname 
																	FROM tblprovider WHERE userid = {$user["userid"]}");
					if(!$prov || $prov["userid"] != $user["userid"]) {
						$failure = 'R'; // login role/rights mismatch
					}
					$_SESSION["providerid"] = $prov["providerid"];
					$_SESSION["shortname"] = $prov["shortname"];
					$_SESSION["fullname"] = $prov["fullname"];
					$_SESSION["provider_email"] = $prov["email"];
					$_SESSION["auth_userfname"] = $prov["fname"];
					
				}
				else if(userRole() == 'c') {
					require_once "agreement-fns.php";
					$currentVersion = getCurrentServiceAgreement();
					$currentVersion = $currentVersion ? $currentVersion['agreementid'] : 0;
					$clientSignedCurrentVersion = $user["agreementptr"] || !$currentVersion; // Don't require sig when no agreement exists
					if($_SESSION['preferences']['latestAgreementVersionRequired']) $clientSignedCurrentVersion = $user["agreementptr"] == $currentVersion;
}			
					if($_SESSION["preferences"]["clientagreementrequired"] && !$clientSignedCurrentVersion) 
						$_SESSION["clientAgreementRequired"] = true;
}			
}			
					
//					require_once "agreement-fns.php";
//					if($_SESSION["clientAgreementRequired"] && (!($agr = getCurrentServiceAgreement()) || $agr['agreementid'] == 0)) 
//						$_SESSION["clientAgreementRequired"] = true;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "AGREEMENT REQ? [{$_SESSION["clientAgreementRequired"]}]".print_r(getCurrentServiceAgreement(), 1); exit;}
					$client = fetchFirstAssoc("SELECT clientid, fname, CONCAT_WS(' ', fname, lname) as name, email, userid FROM tblclient WHERE userid = {$user["userid"]}");
					if(!$client || $client["userid"] != $client["userid"]) {
						$failure = 'R'; // login role mismatch
					}
					if($_SESSION["preferences"]["disableAllClientLogins"]) $failure = 'D'; // ||D|Logins disabled for this role
					$_SESSION["clientid"] = $client["clientid"];
					$_SESSION["clientname"] = $client["name"];
					$_SESSION["auth_userfname"] = $client["fname"];
										
					$_SESSION["clientemail"] = $client["email"];
					$_SESSION["uidirectory"] = "{$_SESSION["bizfiledirectory"]}clientui/";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "{$_SESSION["uidirectory"]}style.css exists:[".file_exists($_SESSION["uidirectory"].'style.css').']'; exit;}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "bizfiles/biz_60/ exists:[".file_exists("bizfiles/biz_60/".'style.css').']'; exit;}
					$_SESSION["creditCardIsRequired"] = $_SESSION['preferences']['clientCreditCardRequired'];
					$_SESSION["creditCardIsRequired"] = $_SESSION["creditCardIsRequired"]
						&& !getClientPreference($_SESSION["clientid"], 'noCreditCardRequired')
						&& !fetchRow0Col0("SELECT ccid FROM tblcreditcard WHERE clientptr = {$_SESSION["clientid"]} AND active = 1");
					//$_SESSION["creditCardIsRequired"] = $_SESSION["creditCardIsRequired"] && $_SERVER['REMOTE_ADDR'] == '68.225.89.173';
					$_SESSION["responsiveClient"] = 
						$clientUIVersion = $_SESSION['preferences']['clientUIVersion']
						 && !$_GET['dev']
						 && ($_SESSION['preferences']['version2TestClients'] == 'PUBLIC'
						 			|| in_array($_SESSION["clientid"], 
																explode(',', $_SESSION['preferences']['version2TestClients'])));
				}
				else if(userRole() == 'o' || userRole() == 'd') {
					$_SESSION["isowner"] = $user["isowner"];
					$_SESSION["auth_username"] = "{$user["fname"]} {$user["lname"]}";
					$_SESSION["auth_userfname"] = $user["fname"];
					//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { echo "[[{$_SESSION["auth_username"]}]]"; }
					checkPaymentStatus($_SESSION["bizptr"], $user);
				}
				
			}
			$_SESSION['displayErrorsLoginSetting'] =  mattOnlyTEST();
			
			// To enable flags for staffuser, change lt-staff-login.php
			$_SESSION['custom_pet_fields_enabled'] = in_array('relpetcustomfield', $tables);
			$_SESSION["flags_enabled"] = true;
			$_SESSION['ccenabled'] = in_array('tblcreditcard', $tables);//$_SESSION['auth_login_id'] == 'dlife'; 
			$_SESSION['emailtemplateenabled'] = in_array('tblemailtemplate', $tables);
			$_SESSION['serviceagreementsenabled'] = in_array('tblserviceagreement', $tables);
			$_SESSION['surchargesenabled'] = in_array('tblsurcharge', $tables);
			$_SESSION['referralsenabled'] = in_array('tblreferralcat', $tables);
			$_SESSION['discountsenabled'] = in_array('tbldiscount', $tables);
			$_SESSION['providerterritoriesenabled'] = in_array('relproviderzip', $tables);
			$_SESSION['historicaldatapresent'] = in_array('tblhistoricaldata', $tables);
			
			$_SESSION['secureKeyEnabled'] = isset($_SESSION["preferences"]['mod_securekey']) && $_SESSION["preferences"]['mod_securekey'];
			$_SESSION['userAgent'] = $_SERVER["HTTP_USER_AGENT"] ? $_SERVER["HTTP_USER_AGENT"] : $_REQUEST['jsuseragent'];
			$_SESSION['tableRowDisplayMode'] = strpos($_SESSION['userAgent'], 'MSIE') !== false ? 'block' : 'table-row'; //blockDisplayMode
			require_once "login-notice-fns.php";
			$_SESSION["notices"] = getLoginNotices();
			$_SESSION['dims']['appointment-edit'] = '530,580';
			$_SESSION["mobiledevice"] = isMobileUserAgent();
			$_SESSION["tabletdevice"] = agentIsATablet();
			$_SESSION["isipad"] = isIPad();
			$_SESSION["isiphone"] = isIPhone();
			
			if($_SESSION["bizptr"]) {
				$_SESSION['mobileVersionPreferred'] = getUserPreference($user['userid'], 'mobileVersionPreferred');
					//fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = {$user['userid']} AND property = 'mobileVersionPreferred' LIMIT 1");
				$_SESSION["mobileVersionOverride"] = getUserPreference($user['userid'], 'mobileVersionOverride');
					//fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = {$user['userid']} AND property = 'mobileVersionOverride' LIMIT 1");
				$_SESSION['webUIOnMobileDisabled'] = getUserPreference($user['userid'], 'webUIOnMobileDisabled');
			}
			if(TRUE || $clienttime) {
				// Changes on 8/29/2020 
				// - made this block independent of $clienttime
				// - made mobile_time_offset equal to ZERO if !$clienttime
//echo "CLIENTTIME: $clienttime";exit;				
				$_SESSION['mobile_private_zone_timeout_interval'] = 
					$_SESSION["preferences"]["mobile_private_zone_timeout_interval"] 
						? $_SESSION["preferences"]["mobile_private_zone_timeout_interval"] 
						: 300;
				$_SESSION['mobile_time_offset'] = $clienttime ? time() - $clienttime : 0;
				$_SESSION['mobile_private_zone_timeout'] = time() + $_SESSION['mobile_private_zone_timeout_interval'];
				if(userRole() == 'p') $_SESSION['showVisitCount'] = getUserPreference($_SESSION['auth_user_id'], 'showVisitCount');

			}
		}
	}
	if(!$failure) $_SESSION['justloggedin'] = 1;  // should be unset or cleared by frame or notice tester
	// #### SCOPE: PETCENTRAL #########
	reconnectPetBizDB($db_global, $dbhost_global, $dbuser_global, $dbpass_global);
	return $failure;
}

function checkPaymentStatus($bizptr, $user) {
	//if(!$user['ltstaffuserid']) return;
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	require  "common/init_db_common.php";
	$custbiz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db = 'leashtimecustomers' LIMIT 1", 1);
	if($custbiz) {
		reconnectPetBizDB($custbiz['db'], $custbiz['dbhost'], $custbiz['dbuser'], $custbiz['dbpass'], 1);
		$clientid = fetchRow0Col0("SELECT clientid FROM tblclient WHERE garagegatecode = '$bizptr' LIMIT 1", 1);
		if($clientid) 
			$unpaidbillables = fetchRow0Col0(
				"SELECT COUNT(*) 
					FROM tblbillable
					WHERE superseded = 0
						AND clientptr = $clientid
						AND paid < charge", 1);
//echo "unpaidbillables: $unpaidbillables";		


		if($unpaidbillables > 1)
			$_SESSION['lockoutwarning'] = $unpaidbillables;
		else if($unpaidbillables == 1 && date('j') > 20 && date('j') < date('t') && date('Y-m-d') > '2020-02-29')
			$_SESSION['lockoutwarning'] = $unpaidbillables;
		else unset($_SESSION['lockoutwarning']);
	}
	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local, 1);
//echo "unpaidbillables: {$_SESSION['lockoutwarning']}";		
}

function impersonate($providerid) {
	global $dbhost, $db, $dbuser, $dbpass;
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);

	//if(!in_array(userRole(), array('s','o'))) return;  // service or owner only
	if(!adequateRights('#es')) return;  // service or owner only
	$prov = fetchFirstAssoc("SELECT userid, IFNULL(nickname, CONCAT_WS(' ', fname, lname)) as providername FROM tblprovider WHERE providerid = $providerid LIMIT 1");
	if(!$prov) return "Sitter not found.";
	else if(!($userid = $prov['userid']))  return "No login found for sitter: {$prov['providername']}";
	
	// need to capture petcentral's host and db info in $_SESSION for use here
	include "common/init_db_common.php";

  $query = "SELECT userid, loginid, password, email, rights, active, bizptr, tempPassword, tblpetbiz.* FROM tbluser
            left join tblpetbiz on bizid = bizptr
            WHERE userid = $userid";
  $user = fetchFirstAssoc($query);

	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);


	if(!$user) return "Login not found for sitter: {$prov['providername']}({$prov['userid']})";
	$impersonator = $_SESSION["auth_user_id"];
	$impersonatorLoginId = $_SESSION["auth_login_id"];
	require "common/init_db_common.php";
	loginUser($user);
	
	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	$_SESSION["impersonator"] = $impersonator;
	$_SESSION["impersonatorLoginId"] = $impersonatorLoginId;
	logChange($user['userid'], $impersonator, 'i' /* impersonation */, '.');
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "BANG!";print_r($_SESSION["preferences"]);exit;}
}

function clearSessionButLeave($keys) {
	foreach(array_keys($_SESSION) as $k) {
		if(!in_array($k, $keys))
			unset($_SESSION[$k]);
	}
}

function clearSession() {
	unset($_SESSION["dbhost"]);
	unset($_SESSION["db"]);
	unset($_SESSION["dbuser"]);
	unset($_SESSION["dbpass"]);
	unset($_SESSION['bannerLogo']);
	unset($_SESSION["bizfiledirectory"]);
	unset($_SESSION["preferences"]);
	unset($_SESSION["bizptr"]);
	unset($_SESSION["providerid"]);
	unset($_SESSION["clientid"]);
	unset($_SESSION['trainingMode']);
	unset($_SESSION['servicenames']);
	unset($_SESSION['allservicenames']);
	unset($_SESSION['surchargetypes']);
	unset($_SESSION['custom_pet_fields_enabled']);
	unset($_SESSION['flags_enabled']);
	unset($_SESSION['user_notice']);
	unset($_SESSION['fullname']);
	unset($_SESSION['shortname']);
	unset($_SESSION['provider_email']);
	unset($_SESSION['homePageMode']);
	unset($_SESSION['inactivebiz']);
	unset($_SESSION['suppressChat']);
	unset($_SESSION['lockoutwarning']);
	unset($_SESSION['sessionpreferences']);
	//unset($_SESSION['lastcheckdate']); -- interferes with list of recent bizzes!
}

function endImpersonation() {
	global $db, $dbhost, $dbuser, $dbpass;
	if(!isset($_SESSION["impersonator"])) return;
	$impersonator = $_SESSION["impersonator"];
	unset($_SESSION["impersonator"]);
	unset($_SESSION["impersonatorLoginId"]);
	$impersonation = $_SESSION["auth_user_id"]; // huh?  Session was cleared above.
	clearSession();
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";

  $query = "SELECT userid, loginid, password, rights, active, bizptr, tempPassword, tbluser.orgptr as rawuserorgptr, tblpetbiz.*,
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name
  					FROM tbluser            
							left join tblpetbiz on bizid = bizptr
							left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
							left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE userid = $impersonator";
  $user = fetchFirstAssoc($query);
  
	
	if(!$user) return "Login not found for sitter: {$prov['providername']}({$prov['userid']})";
	require "common/init_db_common.php";
//if($user['loginid'] == 'dlife') echo "1: ".print_r(array($db, $dbhost, $dbuser),1).'<p>';	
	$failure = loginUser($user);
	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	logChange($impersonation, $impersonator, 'e' /* end impersonation */, '.');
}
	
function branchLogout() {
	global $db, $dbhost, $dbuser, $dbpass;
	if(!isset($_SESSION["corporateuser"])) return 'No corporate user found.';
	$corporateUser = $_SESSION["corporateuser"];
	unset($_SESSION["corporateuser"]);
	clearSession();
	
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";

  $query = "SELECT userid, loginid, password, rights, active, bizptr, tempPassword, tblpetbiz.*,
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name
  					FROM tbluser            
							left join tblpetbiz on bizid = bizptr
							left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
							left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE userid = $corporateUser";
  $user = fetchFirstAssoc($query);
  
	// record result of logout attempt locally
	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	if($user) logChange($_SESSION["auth_user_id"], 'branchlogout', 'b' , $corporateUser);
	else {
		logChange($_SESSION["auth_user_id"], 'branchlogout', 'f' , $corporateUser);
		$failure = "Login not found for corporate user: ($corporateuser)";
	}

//echo "User: ".print_r($user,1)." $failure";exit;
	if($user) {		
		include "common/init_db_common.php";
		loginUser($user);
		if($user['db']) {
			reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
			logChange($corporateUser, 'branchlogout', 'b' , $_SESSION["branchid"]);
		}
	}
	return $failure;
}

function staffLogout() {
	global $db, $dbhost, $dbuser, $dbpass;
	if(!isset($_SESSION["staffuser"])) return 'No staffuser user found.';
	$staffuser = $_SESSION["staffuser"];
	unset($_SESSION["staffuser"]);
	clearSession();
	
	list($db_local, $dbhost_local, $dbuser_local, $dbpass_local) = array($db, $dbhost, $dbuser, $dbpass);
	include "common/init_db_common.php";

  $query = "SELECT userid, loginid, password, rights, active, bizptr, tempPassword, tblpetbiz.*,
  						IFNULL(bizorg.orgid, userorg.orgid) as organization,
  						IF(bizorg.orgid, bizorg.activeorg, userorg.activeorg) as activeorganization,
  						IF(bizorg.orgid, bizorg.orgname, userorg.orgname) as org_name
  					FROM tbluser            
							left join tblpetbiz on bizid = bizptr
							left join tblbizorg bizorg on bizorg.orgid = tblpetbiz.orgptr
							left join tblbizorg userorg on userorg.orgid = tbluser.orgptr
            WHERE userid = $staffuser";
  $user = fetchFirstAssoc($query);
  
	// record result of logout attempt locally
	reconnectPetBizDB($db_local, $dbhost_local, $dbuser_local, $dbpass_local);
	if($user) logChange($_SESSION["auth_user_id"], 'stafflogout', 'b' , $staffuser);
	else {
		logChange($_SESSION["auth_user_id"], 'stafflogout', 'f' , $staffuser);
		$failure = "Login not found for staff user: ($staffuser)";
	}
	if($user) {		
		include "common/init_db_common.php";
		loginUser($user);
		if($user['db']) {
			reconnectPetBizDB($user['db'], $user['dbhost'], $user['dbuser'], $user['dbpass']);
		}
	}
	return $failure;

	return $failure;
}

function setLoginCookies() {
	$duration = time()+60*60*24*30;
	setcookie('LEASHTIMEROLE', userRole(), $duration);
	setcookie('LEASHTIMEBIZPTR', $_SESSION["bizptr"], $duration);
}

function loginFailureExplanation($code) {
	$raw = <<<CODES
	P - bad password
	U - unknown user
	I - inactive user
	F - No Business Found
	B - Business Inactive
	M - Missing Organization
	O - Organization inactive
	R - rights are missing or mismatched
	C - No cookie
	L - account locked
	S - not enableNativeSitterAppAccess
	X - user is not expected role
	T - Temp password was presented
	E - empty parameter list supplied
	W - username not associated with this business
	D - Logins disabled for this role
	H - suspected hack attempt
CODES;
	$lines = explode("\n", $raw);
	foreach($lines as $line) {
		$pair = explode(' - ', trim($line));
		$codes[$pair[0]] = $pair[1];
	}
	return $codes[$code];
}