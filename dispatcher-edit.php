<? // dispatcher-edit.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "rights-maint-fns.php";
require_once "system-login-fns.php";



// **** WARNING - THIS IS NOT COMPLETE AND UNTESTED 2/11/2011  *********




// Determine access privs
$locked = locked('o-');

//extract($_REQUEST);
$id = $_REQUEST['id'];

$nameHashes = array();
foreach(fetchCol0("SELECT CONCAT_WS(' ', fname, lname) FROM tbldispatcher") as $name)
	$nameHashes[] = "'".md5($name)."'";

$pageTitle = "Dispatcher";
$dispatcher = $id ? fetchFirstAssoc("SELECT * FROM tbldispatcher WHERE dispatcherid = $id") : array('active'=>1);
if($id) $pageTitle .= ": {$dispatcher['fname']} {$dispatcher['lname']}";
else $pageTitle = "New $pageTitle";
$breadcrumbs = "<a href='dispatcher-list.php'>Dispatcher List</a>";
require "frame.html";

if($_POST) {
	extract(extractVars('company,lname,fname,active,notes,hiredate,terminationdate,branch,email', $_POST));
	$active = $active ? 1 : 0;
	$hiredate = $hiredate ? date('Y-m-d', strtotime($hiredate)) : null;
	$terminationdate = $terminationdate ? date('Y-m-d', strtotime($terminationdate)) : null;
	$dispatcher = array('company'=>$company, 'lname'=>$lname, 'fname'=>$fname, 'active'=>$active,
											'hiredate'=>$hiredate, 'terminationdate'=>$terminationdate, 'email'=>$email);
	if($id) {
		updateTable('tbldispatcher', $dispatcher, "dispatcherid = $id", 1); 
		if(mysqli_error()) $errors[] = mysqli_error();
		else {
			logChange($id, 'tbldispatcher', 'm' , '.');
			$message = "{$dispatcher['fname']} {$dispatcher['lname']} saved successfully.";
		}
		if($branch && !fetchRow0Col0("SELECT dispatcherptr FROM reldispatcheraccess WHERE dispatcherptr = $id AND branchptr = $branch")) {
			$newUser = createLeashtimeDispatcherUser($id, $branch);
			$relationship = array('branchptr'=>$branch, 'dispatcherptr'=>$id, 'userid'=>$newUser['userid'], 'rights'=>'d-');
			insertTable('reldispatcheraccess', $relationship, 1);
			$message .= "<br>{$dispatcher['fname']} {$dispatcher['lname']} assigned to "
								.(fetchRow0Col0("SELECT name FROM tblbranch WHERE branchid = $branch LIMIT 1")).".";
		}
		else { // update rights;
			updateAssignments($_POST);
		}
	}
	else {
		$dispatcher['creationdate'] = date('Y-m-d H:i:s');
		$id = insertTable('tbldispatcher', $dispatcher, 1);
		if($id) logChange($id, 'tbldispatcher', 'c' , '.');
		else $errors[] = mysqli_error();
		$message = "{$dispatcher['fname']} {$dispatcher['lname']} saved successfully.";
	}
}
$dispatcher = $id ? fetchFirstAssoc("SELECT * FROM tbldispatcher WHERE dispatcherid = $id") : array('active'=>1);


if($errors) {
	echo "<font color='red'>WARNING:<ul>";
	foreach($errors as $error) echo "<li>$error";
	echo "</ul></font>";
}

if($message) echo "<font color='green'>$message<p></font>";

?>
<form name='dispatchereditor' method='POST'>
<table>
<?
hiddenElement('id', $id);
hiddenElement('nextaction', $id);
countdownInputRow(20, 'First name:', 'fname', $dispatcher['fname'], null, 'VeryLongInput');
countdownInputRow(40, 'Last name:', 'lname', $dispatcher['lname'], null, 'VeryLongInput');
inputRow('Company:', 'company', $dispatcher['company'], null, 'VeryLongInput');
inputRow('Email:', 'email', $dispatcher['email'], null, 'VeryLongInput');
checkBoxRow('Active:', 'active', $dispatcher['active']);
calendarRow('Hire date:', 'hiredate', $dispatcher['hiredate']);
calendarRow('Termination date:', 'terminationdate', $dispatcher['terminationdate']);







$systemUser = $dispatcher['userid'] ? findSystemLogin($dispatcher['userid']) : null;
if(!is_array($systemUser)) $systemUser = null;

$args = array('roleid'=>$dispatcher['dispatcherid'], 'target'=>'systemLoginButton', 'lname'=>$dispatcher['lname'], 'fname'=>$dispatcher['fname'], 'email'=>$dispatcher['email']);
if($systemUser) $args['userid'] = $systemUser['userid'];
$args['role'] = 'dispatcher';
foreach($args as $k => $v)
	$argstring[] = "$k=".urlencode($v);
$argstring = join('&', $argstring);

$systemLoginEditButton = $systemUser ? $systemUser['loginid'] : 'No login information set';
echo "<tr><td>Edit System Login:</td><td>";
echoButton('systemLoginButton', $systemLoginEditButton, "editLoginInfo(\"$id\", \"$argstring\")", null, null);
echo "</td></tr>";










echo '<tr><td colspan=2>';
echoButton('', 'Save Changes', 'checkAndSubmit()', 'BigButton', 'BigButtonDown');
echo '</td></tr>';
?>
</table>
<?
$branches = $id 
		? fetchAssociations("SELECT reldispatcheraccess.*, name
													FROM reldispatcheraccess
													LEFT JOIN tblbranch ON branchid = branchptr
													WHERE dispatcherptr = $id
													ORDER BY name")
		: array();
?>


<h3>Branch Assignments (<?= count($branches) ?>)</h3>
<?
if(!$id) echo "Please save this Dispatcher before making any assignments.<br>";
else {
	echoButton('', 'Assign', 'checkAndSubmit()');
	availableBranchSelect($id, ' to another branch: ');
	echo "<p>";
	if(!$branches) echo "No assignments found.";
	else {
		$columns = explodePairsLine('cb| ||name| ');
		$rows = array();
		foreach($branches as $i => $branch) {
			$branchptr = $branch['branchptr'];
			$branch['cb'] = 
				labeledCheckbox('', "branch_$branchptr", $branch['active'], null, null, "branchClicked($branchptr)", false, $noEcho=true);
			$inactivedisplay = $branch['active'] ? 'none' : 'inline';
			$branch['name'] = "<label for='branch_$branchptr'>{$branch['name']} <span id='inactive_$branchptr' style='display:$inactivedisplay;color:red;'> (inactive)</span></label>";
			$branch['name'] = "{$branch['name']} <span id='inactive_$branchptr' style='display:$inactivedisplay;color:red;'> (inactive)</span>";
			$branch['#ROW_EXTRAS#'] = "style='font-weight:bold;'";
			$rows[] = $branch;
			$rights = $branch['rights'];
			if(strpos($rights, '-') == 1) $rights = substr($rights, 2);
			$rights = explode(',', $rights);
			$rows[] = array('#CUSTOM_ROW#'=> "<TR id='rights_$branchptr'><TD COLSPAN=2 class='sortableListCell' style='padding-top:0px;padding-left:20px;padding-bottom:10px;'>"
												. rightsTable($rights, $suffix=$branchptr, 'dispatcherOnly')."</TD></TR>\n");
		}
	}
	tableFrom($columns, $rows, "", $class='', $headerClass, $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts, $rowClasses, $colClasses);//, 'sortClick'
}


?>
</form>

<img src='../art/spacer.gif' height=300 width=1>
<script language='javascript' src='../check-form.js'></script>
<script language='javascript' src='../rsa.js'></script>
<script language='javascript'>
setPrettynames('company', 'Company', 'fname', 'Dispatcher First Name', 'lname', 'Dispatcher Last Name');
var nameHashes = new Array(<?= join(',', $nameHashes) ?>);
var originalHash = '<?= md5("{$dispatcher['fname']} {$dispatcher['lname']}") ?>';

function branchClicked(branchid) {
	var el = document.getElementById('branch_'+branchid);
	document.getElementById('rights_'+branchid).style.display= (el.checked ? '<?= $_SESSION['tableRowDisplayMode'] ?>' : 'none');
	document.getElementById('inactive_'+branchid).style.display = (el.checked ? 'none' : 'inline');
}

function checkAndSubmit(nextaction) {
	var nameHash = ""+hex_md5(document.getElementById('fname').value+' '+document.getElementById('lname').value);
	if(nameHash != originalHash)
		for(var i=0;i<nameHashes.length;i++)
			if(nameHashes[i] == nameHash)
				if(!confirm('There is already a dispatcher with is name.\nCreate a new dispatcher anyway?.'))
					return;
  if(MM_validateForm(
		'lname', '', 'R',
		'fname', '', 'R',
		'company', '', 'R'
		)) {
		document.getElementById('nextaction').value = nextaction;
		document.dispatchereditor.submit();
	}
}

</script>
<script language='javascript' src='../popcalendar.js'></script>
<script language='javascript'>

<?
dumpPopCalendarJS();
?>

var userid = '<?= $dispatcher['userid']; ?>';

function update(target, value) {	
	if(target == 'systemLoginButton') {
		value = value.split(',');
		document.getElementById('systemLoginButton').value=value[1];
		userid = value[0];
	}
}
function editLoginInfo(dispatcherid, argstring) {
	if(!dispatcherid) {
		if(!confirm("This dispatcher has not been saved, but must be saved\nbefore a system login can be set up.\n"+
	                      "Click OK to save the client and continue."))
	     return;
	  else {
			checkAndSubmit('systemloginsetup');
		}
	}
	else {
		if(userid != '' && (argstring.indexOf('userid') == -1)) argstring = argstring+"&userid="+userid;
		var url = "org-login-creds-edit.php?"+argstring;
		var w = window.open("",'systemlogineditor',
			'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+400+',height='+400);
		w.document.location.href=url;
		if(w) w.focus();
	}
}




</script>
<?require "frame-end.html";

function availableBranchSelect($id, $label) {
	$branches = fetchKeyValuePairs("SELECT name, branchid
													FROM tblbranch
													LEFT JOIN reldispatcheraccess ON branchptr = branchid AND dispatcherptr = $id
													WHERE dispatcherptr IS NULL 
													ORDER BY name");
	$options = array(($branches ? 'Choose a branch' : 'No more branches available')=>0);
	if($branches) $options['Available Branches'] = $branches;
	selectElement($label, 'branch', $value=null, $options);
}

function createLeashtimeDispatcherUser($id, $branch) {
	$branch = fetchFirstAssoc("SELECT * FROM tblbranch WHERE branchid = $branch");
	$user = array('bizptr'=>$branch['bizptr'], 'orgptr' => $_SESSION['orgptr'], 
								'loginid'=>dispatcherLoginId($id, $branch['bizptr']),
								'password'=>'',
								'rights'=>'d-',
								'active'=>1);
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$user['userid'] = insertTable('tbluser', $user, 1);
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	return $user['userid'] ? $user : null;
}

function dispatcherLoginId($id, $bizptr) {
	list($dbhost1, $db1, $dbuser1, $dbpass1) = array($dbhost, $db, $dbuser, $dbpass);
	require "common/init_db_common.php";
	$loginids = fetchCol0("SELECT loginid FROM tbluser");
	reconnectPetBizDB($db1, $dbhost1, $dbuser1, $dbpass1);
	$loginid = "disp_$id"."_$bizptr";
	while(in_array($loginid, $loginids)) {
		$n++;
		$loginid = "disp_$id"."_$bizptr"."_$n";
	}
	return $loginid;
}
		
function updateAssignments($post) {
	global $id;
	$rights = array(); // branchptr => rights
	$activeBranches = array();  // branchptr
	foreach($post as $key => $val) {
		if(strpos($key, 'branch_') === 0) $activeBranches[substr($key, strlen('branch_'))] = 1;
		else if(strpos($key, 'right_') === 0) {
			$rightParts = explode('_', $key);
			$rights[$rightParts[2]][] = $rightParts[1];
		}
	}
//echo "activeBranches: ".print_r($activeBranches, 1);exit;
	$allBranches = fetchKeyValuePairs("SELECT branchptr, userid
													FROM reldispatcheraccess
													WHERE dispatcherptr = $id");
	foreach($allBranches as $branch => $userid) {
		$drights = $rights[$branch] ? join(',', $rights[$branch]) : '';
		$changes = array('active'=>($activeBranches[$branch] ? 1 : '0'),
											'rights'=> "d-$drights");
		updateTable('reldispatcheraccess', $changes, "branchptr = $branch AND dispatcherptr = $id", 1);
		updateUserRights($userid, $changes);
	}
}
		