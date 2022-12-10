<? // heartbeat.php
// invoked by a remote stethoscope or to test stethoscope activity
// parameters 
//   last - when supplied, show datetime of last stehoscope call
$t0 = microtime(1);
require_once "common/init_session.php";
require_once "common/init_db_common.php";
if($_REQUEST['last']) echo fetchRow0Col0("SELECT CONCAT(time, ' ', note) FROM tblchangelog WHERE itemtable = 'heartbeat' ORDER BY time desc LIMIT 1");
else if(array_key_exists('args',$_REQUEST) || array_key_exists('usage',$_REQUEST)) 
	echo "Usage:
				<ul><li><a href='heartbeat.php?last=1'>last=1</a>: show time of last check
						<li>test=voice|number,number...|message - message optional.
						<li>test=text|number,number...|message - message optional.</ul>
				Admin: http://192.168.1.18/admin.php";
else if($_REQUEST['status']) {  // "voice|703-242-1964"  "voice|703-242-1964|this is a test of the leashtime heartbeat monitor"
	$status = $heartbeattest = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = -999 AND property = 'heartbeattest'");
	echo "Status: ".($status ? $status : 'normal');
}
else if($_REQUEST['test']) {  // "voice|703-242-1964"  "voice|703-242-1964|this is a test of the leashtime heartbeat monitor"
	replaceTable('tbluserpref', array('userptr'=>-999, 'property'=>'heartbeattest', 'value'=>$_REQUEST['test']), 1);
	echo "Test triggered at ".date('m/d/Y H:i:s');
}
else if($heartbeattest = fetchRow0Col0("SELECT value FROM tbluserpref WHERE userptr = -999 AND property = 'heartbeattest'")) {
	deleteTable('tbluserpref', "userptr = -999 AND property = 'heartbeattest'", 1);
	echo "TEST|$heartbeattest";
}
else {
	$cronRun = $_REQUEST['cron'] ? 'cron:' : '';
	logChange(-999, 'heartbeat', 'c', $note=$cronRun.(microtime(1)-$t0));
	echo $note;
}
