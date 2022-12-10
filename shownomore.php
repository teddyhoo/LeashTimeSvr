<? // shownomore.php
require_once "common/init_session.php";
require "common/init_db_common.php";

if($_REQUEST['id']) {
	//if(mattOnlyTEST()) replaceTable('relusernotice', array('shownomore'=>1, 'noticeptr'=>$_REQUEST['id'], 'userptr'=>$_SESSION['auth_user_id']), 1);
	updateTable('relusernotice', array('shownomore'=>1), "noticeptr = {$_REQUEST['id']} AND userptr = {$_SESSION['auth_user_id']}", 1);
	//if(mattOnlyTEST()) logError("noticeptr = {$_REQUEST['id']} userptr = {$_SESSION['auth_user_id']}");
}
