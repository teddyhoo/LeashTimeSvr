<? // impersonate.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "login-fns.php";
require_once "preference-fns.php";

// Security check performed by impersonate().  None necessary for endImpersonation().

if(isset($_REQUEST['end'])) {
	endImpersonation();
	//$installationSettings = null;
	header("Location: ".globalURL("provider-list.php?alertMessage=$result"));
	exit;
}
else if(isset($_REQUEST['branchout'])) {
	branchLogout();
	//$installationSettings = null;
	header("Location: ".globalURL("corp"));
	exit;
}
else if(isset($_REQUEST['staffout'])) {
	staffLogout();
	//$installationSettings = null;
	header("Location: ".globalURL("maint-dbs.php"));
	exit;
}
else if(isset($_REQUEST['provider'])) {
	$result = impersonate($_REQUEST['provider']);
	if(is_string($result)) {
		header("Location: ".globalURL("provider-list.php?alertMessage=$result"));
		exit;
	}
}
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "BANG!";print_r($_SESSION["preferences"]);exit;}

header("Location: $mein_host$this_dir/index.php");
exit;
