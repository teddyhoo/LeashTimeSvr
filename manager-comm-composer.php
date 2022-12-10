<?
// manager-comm-composer.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

require_once "comm-fns.php";
require_once "client-fns.php";
require_once "provider-fns.php";
//require_once "email-template-fns.php";

$auxiliaryWindow = true; // prevent login from appearing here if session times out


$locked = locked('+ka,+#km');//locked('o-'); 

if(TRUE) {
	// FIRST find ADNMINONLY templates
	$templates = fetchKeyValuePairs(
		"SELECT templateid, label 
			FROM tblemailtemplate 
			WHERE targettype = 'provider'
				AND (body LIKE '%#ADMINONLY#%')", 1);
	if(!$templates) {
		$noAdminOnlyTemplatesFound = true;
		$templates = fetchKeyValuePairs(
			"SELECT templateid, label 
				FROM tblemailtemplate 
				WHERE targettype = 'provider'
					AND (body LIKE '%#TEMPPASSWORD#%')", 1);
	}
	if(!$templates) { // prepopulate Subject and Body
		$mgrs = getManagers();
		$target = $mgrs[$_REQUEST['user']];
		$subject = "Your LeashTime admin login credentials";
		$messageBody = <<<BODY
#LOGO##ADMINONLY#

Hi #FIRSTNAME#,

Here are your username and password for logging in as a staff member for #BIZNAME#. The password is temporary; the very next time you try to login (whether using this password or not), this password will be erased. If you login with this password, you will be asked to supply a new permanent password. Please save this email.<table cellspacing='10' align='center' bgcolor='lightblue'><tbody><tr><td>Username:</td><td bgcolor='white'><strong>#LOGINID#</strong></td><td>Temp Password:</td><td bgcolor='white'><strong>#TEMPPASSWORD#</strong></td></tr></tbody></table>

If your login attempt is not successful for any reason, you can obtain a new temporary password at our login page: <a href='https://leashtime.com/login-page.php?bizid=#BIZID#'>https://leashtime.com/login-page.php?bizid=#BIZID#</a> using the forgotten password link. To obtain a new password, you will need to supply your username (#LOGINID#) and this email address (#EMAIL#). Once you do, a new temporary password will be emailed immediately to that email address. Please contact us at #BIZEMAIL# or #BIZPHONE# if you have any questions.

Thank you,

#MANAGER#		
BODY;
	require_once "email-template-fns.php";
	$messageBody= preprocessTemplateMessage($messageBody, $target, $template);
	}
	//$templates = fetchKeyValuePairs("SELECT templateid, label FROM tblemailtemplate BODY LIKE label IN ('".join("','", $templates)."')", 1);
	foreach($templates as $k => $v) {
		if(strpos($v, '#STANDARD - ') === 0) $v = substr($v, strlen('#STANDARD - '));
		if(strpos($v, '#UNDELETABLE - ') === 0) $v = substr($v, strlen('#UNDELETABLE - '));
		$newtemplates[$v] = $k;
	}
	$specialTemplates = $newtemplates;
}

extract($_REQUEST);

include "comm-composer.php";

