<?
// unassigned-visits-board-fns.php

/*
CREATE TABLE IF NOT EXISTS `tblunassignedboard` (
  `uvbid` int(11) NOT NULL AUTO_INCREMENT,
  `appointmentptr` int(11) DEFAULT NULL,
  `packageptr` int(4) DEFAULT NULL,
  `clientptr` int(4) DEFAULT NULL,
  `uvbdate` date NOT NULL,
  `uvbtod` varchar(40) DEFAULT NULL,
  `uvbnote` text,
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `createdby` int(11) NOT NULL,
  `modifiedby` int(11) DEFAULT NULL,
  PRIMARY KEY (`uvbid`),
  UNIQUE KEY `appointmentid` (`appointmentptr`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8811 ;

*/

function setupUVBTableIfNecessary() {
	$sql =
		"CREATE TABLE IF NOT EXISTS `tblunassignedboard` (
			`uvbid` int(11) NOT NULL AUTO_INCREMENT,
			`appointmentptr` int(11) DEFAULT NULL,
			`packageptr` int(4) DEFAULT NULL,
			`clientptr` int(4) DEFAULT NULL,
			`uvbdate` date NOT NULL,
			`uvbtod` varchar(40) DEFAULT NULL,
			`uvbnote` text,
			`created` datetime NOT NULL,
			`modified` datetime DEFAULT NULL,
			`createdby` int(11) NOT NULL,
			`modifiedby` int(11) DEFAULT NULL,
			PRIMARY KEY (`uvbid`),
			UNIQUE KEY `appointmentid` (`appointmentptr`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=8811 ;";
	doQuery($sql, 1);
}

function cleanUpUVB() {
	$today = date('Y-m-d');
	$toDelete = fetchCol0($sql =
		"SELECT uvbid
			FROM tblunassignedboard
			LEFT JOIN tblappointment ON appointmentid = appointmentptr
			WHERE (date AND date < '$today') 
				OR (uvbdate AND uvbdate < '$today')
				OR (canceled IS NOT NULL)
				OR (completed IS NOT NULL)
				OR (appointmentptr IS NOT NULL AND appointmentid IS NULL)");
	if($toDelete) deleteTable('tblunassignedboard', "uvbid IN (".join(',', $toDelete).")");
}


function fetchUVBEntryForNRPackage($packageid) {
	$package = is_array($packageid) ? $packageid : getCurrentNRPackage($id);
	$history = findPackageIdHistory($package['packageid'], $package['clientptr'], ($package['enddate'] ? 0 : 1));
	$history = join(',', $history);
	return fetchFirstAssoc("SELECT * FROM tblunassignedboard WHERE packageptr IN ($history) LIMIT 1");
}

function countUVBAppts($appts) {
	$today = date('Y-m-d');
	foreach($appts as $i => $appt) {
		if(!$appt['canceled'] && strcmp($appt['date'], $today) >= 0 && !$appt['providerptr']) $count++;
	}
	return $count;
}

function validUVBEntryActionForNRPackage($packageid) {
	$package = is_array($packageid) ? $packageid : getCurrentNRPackage($id);
	$apptCount = countUVBAppts(fetchAllAppointmentsForNRPackage($package, $clientptr=null));
	if($apptCount > 0) {
		if($uvb = fetchUVBEntryForNRPackage($packageid)) return 'update';
		else return 'add';
	}
}