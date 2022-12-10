<? // service-recurring-note-edit.php
// open a light box editor onto a receurring schedule's note
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "gui-fns.php";
locked('o-');

if($_POST && adequateRights('#ev')) {
	$packageid = $_POST['packageid'];
	$mods = array('notes'=>leashtime_real_escape_string($_POST['notes']));
	updateTable('tblrecurringpackage', withModificationFields($mods), "packageid = {$_POST['packageid']}", 1);
	logChange($_POST['packageid'], 'tblrecurringpackage', 'm', $chnote='Package note changed.');
	echo "<script language='javascript'>if(window.parent) window.parent.$.fn.colorbox.close(); else window.close();</script>";
	exit;
}

$package = fetchFirstAssoc("SELECT packageid, notes FROM tblrecurringpackage WHERE packageid = '{$_REQUEST['packageid']}' LIMIT 1", 1);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html><!-- html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" -->
<head> 
  <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
  <title><?= $windowTitle ?></title> 
	<meta http-equiv="X-UA-Compatible" content="IE=EmulateIE8" />
<body>
  <link rel="stylesheet" href="style.css" type="text/css" /> 
  <link rel="stylesheet" href="pet.css" type="text/css" />
	<form method='POST' name='editnote'>
	<h3>Ongoing Schedule Note</h3>
	<input type='hidden' id='packageid' name='packageid' value=<?= $_REQUEST['packageid'] ?>>
	<!-- input type='submit' value='Save' -->
	<? echoButton('', 'Save', 'document.editnote.submit()'); ?>
	<p>
	<textarea class="fontSize1_3em" id='notes' name='notes' style='width: 90%; height:150px;'><?= $package['notes'] ?></textarea>
	</form>
</body>
	