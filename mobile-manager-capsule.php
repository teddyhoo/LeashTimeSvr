<? // mobile-manager-capsule.php
// data dump for manager standalone app
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "field-utils.php";
require_once "request-fns.php";

$locked = locked('o-');

// return visits by starttime/provider/client
// return servicetypes by id
// return providers by id
// return (pertinent) clients by id



$date = date('Y-m-d', ($_REQUEST['date'] ? strtotime($_REQUEST['date']) : time()));


// ############################################################
ob_start();
ob_implicit_flush(0);
// ############################################################

echo "<day date=\"".date('Y-m-d', strtotime($date))."\" fetched=\"".date('Y-m-d H:i:s')."\">";
// return unresolved requests
dumpRequests();
// return visits by starttime/provider/client
dumpVisits($date);
echo "</day>";


// ############################################################
$descr = ob_get_contents();
ob_end_clean();
echo htmlentities($descr)."<hr>";
$xml = simplexml_load_string($descr);
pretty($xml);
// ############################################################


function dumpVisits($date, $orderBy=null) {
	$orderBy = $orderBy == null ? 'starttime, providerptr, clientptr';
	$visits = fetchAssociations(
		"SELECT * 
		 FROM tblappointment 
		 WHERE date = '$date'
		 ORDER BY $orderBy");
	echo "<visits>";
	foreach($visits as $appt) {
		echo "<appt id=\"{$appt['requestid']}\" clid=\"{$appt['clientptr']}\" ";
		foreach(
		echo "<req id=\"{$req['requestid']}\" clid=\"{$req['clientptr']}\" date=\"$showDate\">";
		echo "</appt>";
	}
}


/*

CREATE TABLE IF NOT EXISTS `tblappointment` (
  `appointmentid` int(10) unsigned NOT NULL auto_increment,
  `birthmark` varchar(30) default NULL COMMENT 'timeofday_servicecode',
  `serviceptr` int(10) unsigned NOT NULL default '0',
  `packageptr` int(10) unsigned NOT NULL default '0',
  `recurringpackage` tinyint(1) NOT NULL default '0',
  `completed` datetime default NULL,
  `timeofday` varchar(45) NOT NULL default '0',
  `providerptr` int(10) unsigned NOT NULL default '0',
  `servicecode` int(10) unsigned NOT NULL default '0',
  `pets` varchar(45) NOT NULL default '',
  `charge` float(5,2) NOT NULL default '0.00',
  `adjustment` float(5,2) default NULL,
  `rate` float(5,2) NOT NULL default '0.00',
  `bonus` float(5,2) default NULL,
  `surchargenote` varchar(40) default NULL,
  `date` date NOT NULL default '0000-00-00',
  `clientptr` int(10) unsigned NOT NULL default '0',
  `canceled` datetime default NULL,
  `custom` tinyint(1) default NULL COMMENT 'Modified since creation',
  `starttime` time NOT NULL default '00:00:00',
  `endtime` time NOT NULL default '00:00:00',
  `highpriority` tinyint(1) default NULL,
  `note` text,
  `cancellationreason` varchar(100) default NULL,
  `pendingchange` int(11) default NULL COMMENT 'Negative for cancel.  (abs) = requestid',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `createdby` int(11) default NULL,
  `modifiedby` int(11) default NULL,
  PRIMARY KEY  (`appointmentid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=136435 ;

*/

function dumpRequests() {
	echo "<reqs>"; 
	$requests = getClientRequests('unresolvedOnly', 0, 20, $filterParams=null);
	foreach($requests as $req) {
		// requestid, clientptr, date, subject
		$rtime =  strtotime($req['received']);
		$dateFrame = dateFrame(date('Y-m-d', $rtime));
		if(date('Y-m-d', $rtime) == date('Y-m-d', strtotime('yesterday'))) $dateFrame = 'yesterday';
		$showDate = $dateFrame == 'today' ? date('g:i a', $rtime) : (
								$dateFrame == 'yesterday' ? 'yesterday '.date('g:i a', $rtime) : (
								$dateFrame == 'this' ? date('D', $rtime).date('g:i a', $rtime) : 
								month3Date($rtime)
									));
		echo "<req id=\"{$req['requestid']}\" clid=\"{$req['clientptr']} pid=\"{$req['clientptr']}\" date=\"$showDate\">";
		$subject = requestShortLabel($req);
		echo "<subj><![CDATA[".$subject."]]></subj>";
		echo "<cname><![CDATA[{$req['fname']} {$req['lname']}]]></cname>";
		foreach(explode(',', 'address,street1,street2,city,state,zip,phone,email,pets,note,officenotes,whentocall,requesttype,scope,received') as $fld) {
			if($req[$fld]) echo "<$fld><![CDATA[{$req[$fld]}]]></$fld>";
		}
		if($req['extrafields']) echo $req['extrafields'];
		echo "</req>"; 
	}
	echo "</reqs>"; 
}

function pretty($obj, $level=0) {
	$obj = is_string($obj) ? simplexml_load_string($obj) : $onj;
	$pix = (int)$level * 10;
	echo "<div style='margin-left:{$pix}px;'><u>".$obj->getName()."</u>";
	foreach($obj->attributes() as $a => $b) echo " [$a]=$b";
	echo "<div style='color:darkgrey;margin-left:3px;'>".(string)$obj."</div>";
	foreach ($obj->children() as $child) pretty($child, $level+1);
	echo "</div>";
}
