<?
// maint-link-fns.php

function getLinkSiblings() {
	$linkgroup = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = '{$_SESSION['auth_user_id']}' AND property = 'linkgroup' LIMIT 1", 1);

	if($linkgroup) $siblings = fetchCol0(
		"SELECT userptr 
			FROM tbluserpref 
			WHERE property = 'linkgroup' AND value ='$linkgroup' AND userptr != '{$_SESSION['auth_user_id']}'");
	return $siblings;
}

function getMenuColorboxContent() {
	$siblings = getLinkSiblings();
	if(count($siblings) < 1) $error = "No linked accounts.";
	$otherBizzes = fetchAssociations(
		"SELECT bizptr, loginid, temppassword, userid, rights, active, 
					lname, fname, email, db, bizname, isowner, activebiz
		FROM tbluser
		LEFT JOIN tblpetbiz ON bizid = bizptr
		WHERE userid IN (".join(',', $siblings).")
		ORDER BY bizname, loginid");
	require_once "gui-fns.php";
	ob_start();
	ob_implicit_flush(0);
	echo "<link rel=\"stylesheet\" href=\"style.css\" type=\"text/css\" /> 
  <link rel=\"stylesheet\" href=\"pet.css\" type=\"text/css\" />\n";

	echo "<span style='font-size:1.3em'> Login to:<ul>";
	foreach($otherBizzes as $biz)
		echo "<li>"
			.fauxLink("{$biz['bizname']} (as {$biz['loginid']})", 
			$onClick="tryLogin({$biz['userid']})", 
			$noEcho=true, $title="Switch to {$biz['bizname']}", $id=null, $class=null, $style=null);
	echo "</ul></span>";
?>
	<script language='javascript' src='ajax_fns.js'></script>
	<script language='javascript'>
	function tryLogin(userid) {
		ajaxGetAndCallWith('maint-link-login.php?userid='+userid, loginResponse, 'argument');
	}

	function loginResponse(aspect, response) {
		if(response == 'SUCCESS') window.parent.location.href = "index.php";
		else alert(response);
	}
	</script>
<?
	$content = ob_get_contents();
	//echo 'XXX: '.ob_get_contents();exit;
	ob_end_clean();
	return $content;
}

