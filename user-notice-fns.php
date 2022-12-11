<? // user-notice-fns.php
/*
tblusernotice
noticeid
usertypes - o,p,c,d,x,z,- NOT NULL
innerhtml - text NOT NULL
premieres - datetime NULL
expires - datetime NULL
showonce - boolean default: T
logintimeonly - boolean default: T
targetpagepattern - varchar NULL
urgency - 10,5,1 (high. medium, low) - NOT NULL
bizptr - NULL
orgptr - NULL

relusernotice
noticeptr - NOT NULL
userptr - NOT NULL
date - NOT NULL
shownomore - NOT NULL

tblnewestnoticedate 
newest -datetime (one record)

checkForNotices

*/
require_once "common/db_fns.php";

$urgencyLabels = array(1=>'', 5=>'<b>Important</b>', 10=>"<span style='color:red;font-weight:bold;font-variant:small-caps;'>Urgent</span>");

function checkForNotices($force=false) {
	// Check for notices if 
	// a) force=true or
	// b) notices have been added since the last check time
	global $db;
	if(!$db) return;
	if(!$force) {
		if(!$_SESSION['lastcheckdate']) $retrieveNow = true;
		else {
			$retrieveNow = fetchRow0Col0("SELECT * FROM petcentral.tblnewestnoticedate WHERE newest > '{$_SESSION['lastcheckdate']}'");
			$newNoticeThreshold = $_SESSION['lastcheckdate'];
		}
		$_SESSION['lastcheckdate'] = date('Y-m-d H:i:s');
	}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("[$force] [$retrieveNow]"); }
	if($force || $retrieveNow) 
		return retrieveNotices($newNoticeThreshold);
}

function retrieveNotices($newNoticeThreshold) {
	if(!$newNoticeThreshold) $newNoticeThreshold = '2010-01-01 00:00:00';
	$user = $_SESSION['auth_user_id'];
	$role = $user ? userRole() : '-';
	
		
	$notices = fetchAssociationsKeyedBy($sql = 
		"SELECT *, if(added > '$newNoticeThreshold', 1, 0) as newlyadded
			FROM petcentral.tblusernotice
			WHERE expires > NOW() AND premieres <= NOW() AND usertypes LIKE '%$role%'
			ORDER BY urgency DESC, premieres
		", 
		'noticeid');
		
	if(!$notices) return;
	if($user) {
		$alreadyNoticed = fetchKeyValuePairs($sql = "SELECT noticeptr, shownomore FROM petcentral.relusernotice WHERE userptr = $user AND noticeptr IN (".join(',', array_keys($notices)).")");
		
		foreach($alreadyNoticed as $id => $showNoMore) {
			$notices[$id]['shown'] = 1;
			$notices[$id]['shownomore'] = $showNoMore;
		}
	}
//echo "ZZZZZZZZZ: ".print_r($notices,1);
	
	$_SESSION['user_notices'] = $notices;
}

function buildNoticeText() {  // to be called in frame.html
	global $urgencyLabels;
	//if(!isset($_SESSION) && $_SESSION) return;
//echo "<!-- USR0 ";print_r($_SESSION['user_notices']);	echo "-->";
	checkForNotices();
//echo "<!-- USR ";print_r($_SESSION['user_notices']);	echo "-->";
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($_SESSION['user_notices']); }

	if(!$_SESSION['user_notices']) return;
	foreach($_SESSION['user_notices'] as $i => $notice) {
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "\nNOTE: ".print_r($notice,1);}	
		if($notice['shown'] && ($notice['showonce'] || $notice['shownomore'])) continue;
		if($notice['staffonly'] && !$_SESSION['staffuser']) continue;
		if($notice['bizptr'] && $notice['bizptr'] != $_SESSION["bizptr"]) continue; // bizptr (-1) == nobody
		if($notice['orgptr'] && $notice['orgptr'] != $_SESSION["orgptr"]) continue;
		if($notice['targetpagepattern'] && (strpos($_SERVER["REQUEST_URI"], $notice['targetpagepattern']) === FALSE))
			continue;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { screenLog("LOG[{$notice['logintimeonly']}] JUST[{$_SESSION['justloggedin']}] NEW[{$notice['newlyadded']}]"); }
		if($notice['logintimeonly'] && !($_SESSION['justloggedin'] || $notice['newlyadded'])) {
			continue;
		}
		$_SESSION['user_notices'][$i]['newlyadded'] = null;
		$date = date('Y-m-d H:i:s');
		$toBeDisplayed[] = $notice;
		if($_SESSION['auth_user_id']) {
			if(!$notice['shown']) $toBeMarked[] = array('noticeptr'=>$notice['noticeid'], 'userptr'=>$_SESSION['auth_user_id'], 'date'=>$date);
		}
	}
	if($toBeDisplayed) {
		foreach((array)$toBeMarked as $row)
			replaceTable('petcentral.relusernotice', $row, 1);
		//checkForNotices('force');
		foreach($toBeDisplayed as $notice) {
			if($block) $block .= '<hr>';
			if($notice['urgency'] > 1) $block .= $urgencyLabels[$notice['urgency']].'<p>';
			$noParagraphs = strpos($notice['innerhtml'], '<p') === FALSE;
			$innerHTML = str_replace("\r", "", $notice['innerhtml']);
			if($noParagraphs) {
				$innerHTML = str_replace("\n\n", "<p>", $innerHTML);
				$innerHTML = str_replace("\n", "<br>", $innerHTML);
			}
			else $innerHTML = str_replace("\n", " ", $innerHTML);
			$innerHTML = str_replace('"', '&quot;', $innerHTML);
			$block .= $innerHTML; //$notice['innerhtml']);
			$block = "<div class='noticeblock'>$block</div>";
		}
		$_SESSION['user_notice'] = $block;
	}
	$_SESSION['justloggedin'] = false;  // should probably move this to end of frame-end.html
}