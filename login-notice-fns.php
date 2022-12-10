<? // login-notice-fns.php

// UNUSED -- SUPERSEDED BY user-notice-fns.php and Colorbox
// Although, it is stil included in frame-end.html(40): dumpNoticesJS();

function getLoginNotices() {
	$orgptr = $_SESSION['orgptr'] ? $_SESSION['orgptr'] : '-999';
	$bizptr = $_SESSION['bizptr'] ? $_SESSION['bizptr'] : '-999';
	$sql = "SELECT body, html, role FROM tblloginnotice
		WHERE start <= NOW() AND end >= NOW() AND
			publish = 1 AND
			(orgptr IS NULL OR orgptr = $orgptr) AND
			(bizptr IS NULL OR bizptr = $bizptr)";
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$rows = fetchAssociations($sql);
//print_r($rows);echo '<p>';	
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	foreach($rows as $row) {
		if(!$row['role']) $notices[] = $row; // c,p,d,o,x,s(taff) null=all
		else {
			$userRole = userRole();
			$noticeRole = $row['role'];
//echo "RIGHTS: [{$_SESSION['rights']}]<p>$noticeRole == $userRole<p>";			
			if($noticeRole == $userRole
					|| ($noticeRole == 's' && in_array($userRole, array('p','o','d','x')))) /* staff */
				$notices[] = $row;
		}
	}
	return $notices;
}

function dumpNoticesJS() {
	if(!$_SESSION) return;
	$notices = $_SESSION["notices"];
	unset($_SESSION["notices"]);
	if(!$notices) return;
	echo <<<HTML
<div id='noticediv' style="border:THIN solid black;background:#B36200;width:600px;position:absolute;left:100px;top:250px;padding:20px;/*brown*/">
<table border=0 width=100%; style='background:#B3FBFF;border: solid #3DC5CC 5px;/*lightblue center, darkerblue border*/'>
<tr><td><span style="font: 12px Arial, Verdana; color:red;cursor:pointer;"  onClick=hideNotice()>[x] Close</span>
<td align=right><span style="font:12px Arial, Verdana;cursor:pointer;"  onClick=lastNotice()>&lt;
							Previous</span>&nbsp;&nbsp;<span style="font:  12px Arial, Verdana;cursor:pointer;"  onClick=nextNotice()>Next ></span>
<tr><td style='text-align:center;font: bold 14px Arial, Verdana; color:black;' colspan=2>NOTICES</td></tr>
<tr><td colspan=2 style="font:10pt NewTimesRoman;background:white;" id='noticetext'></td></tr>
</td></tr></table>
</div>
HTML;
	echo "<script language=javascript>\nvar notices = new Array();\n";
	foreach($notices as $notice) {
		$body = $notice['body'];
		if($notice['html']) $body = str_replace("\n", ' ', str_replace("\r", '', $body));
		else $body = str_replace("\n", '<br>', str_replace("\n\n", '<p>', str_replace("\r", '', $body)));
		$body = addcslashes($body, '"');
		echo "notices[notices.length] = \"$body\";\n";
	}
	echo "</script>\n<script language=javascript src='login-notice.js'></script>";
}
				