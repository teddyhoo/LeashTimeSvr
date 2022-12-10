<? // holidays-future.php

/*
$holidays = array(
'Columbus Day' =>'2010-10-11,2011-10-10,2012-10-08,2013-10-14,2014-10-13,2015-10-12,2016-10-10,2017-10-09',
'Memorial Day'	=>'2010-05-31,2011-05-30,2012-05-28,2013-05-27,2014-05-26,2015-05-25,2016-05-30,2017-05-29',
'Labor Day'	=>'2010-09-06,2011-09-05,2012-09-03,2013-09-02,2014-09-01,2015-09-07,2016-09-05,2017-09-04',
'Easter'	=>'2010-04-04,2011-04-24,2012-04-08,2013-03-31,2014-04-20,2015-04-05,2016-03-27,2017-04-08',
'Orthodox Easter'=>'2010-04-04,2011-04-24,2012-04-15,2013-05-05,2014-04-20,2015-04-12,2016-05-01,2017-04-16',
'MLK Day'=>'2010-01-18,2011-01-17,2012-01-16,2013-01-21,2014-01-20,2015-01-19,2016-01-18,2017-01-16',
'Yom Kippur'	=>'2010-09-18,2011-10-08,2012-09-26,2013-09-14,2014-10-04,2015-09-23,2016-09-12,2017-09-30',
'Passover'	=>'2010-03-30,2011-04-19,2012-04-07,2013-03-26,2014-04-15,2015-04-04,2016-04-23,2017-04-17',
'Rosh Hashanah'	=>'2010-09-10,2011-09-29,2012-09-17,2013-09-05,2014-09-25,2015-09-14,2016-09-04,2016-09-21',
'Thanksgiving'	=>'2010-11-25,2011-11-24,2012-11-22,2013-11-28,2014-11-27,2015-11-26,2016-11-24,2017-11-23',
'Day After Thanksgiving'	=>'2010-11-26,2011-11-25,2012-11-23,2013-11-29,2014-11-28,2015-11-27,2016-11-25,2017-11-24',
'Christmas'	=>'2010-12-25,2011-12-25,2012-12-25,2013-12-25,2014-12-25,2015-12-25,2016-12-25,2017-12-25',
'Christmas Eve'	=>'2010-12-24,2011-12-24,2012-12-24,2013-12-24,2014-12-24,2015-12-24,2016-12-24,2017-12-24',
"New Year's Eve"	=>'2010-12-31,2011-12-31,2012-12-31,2013-12-31,2014-12-31,2015-12-31,2016-12-31,2017-12-31',
"New Year's Day"	=>'2010-01-01,2011-01-01,2012-01-01,2013-01-01,2014-01-01,2015-01-01,2016-01-01,2017-01-01',
'Independence Day'	=>'2010-07-04,2011-07-04,2012-07-04,2013-07-04,2014-07-04,2015-07-04,2016-07-04,2017-07-04'
);
*/
$i18n = getI18NProperties();
$holidays = $i18n['Holidays'];

foreach($holidays as $k => $v) $holidays[$k] = explode(',',$v);

function isStandardHoliday($holiday) {
	global $holidays;
	return isset($holidays[$holiday]);
}

function yearPicker($holiday, $holidayEl) {
	global $holidays;
	$options = array('-- No Change --'=>'');
	foreach($holidays[$holiday] as $date)
		$options[date('Y - F j', strtotime($date))] = date('n/j', strtotime($date));
	$options['-- Pick a Year --'] ='';
	return "<span id='$holidayEl"."_picker' style='display:none;'>"
				.selectElement('', 'unused', $value=null, $options, $onChange="setHoliday(\"$holidayEl\", this)", '', '', 1)
				."</span>";
}

function upcomingHolidays($exactLookahead = false, $lookahead=null) {
	// if $exactLookahead, return holidays ONLY if one falls on the lookahead limit date
	if($_SESSION) $prefs = $_SESSION['preferences'];
	else {
		require_once "preference-fns.php";
		$prefs = fetchPreferences();
	}
	 
	$lookahead = $lookahead ? $lookahead : $prefs['holidayVisitLookaheadPeriod'];
	$lookahead = $lookahead == null ? 30 : $lookahead;
	$thisYear = date('Y');
	$today  = strtotime(date('Y-m-d'));
	$lastDay = strtotime(date('Y-m-d', strtotime("+ $lookahead days")));
	foreach(getSurchargeTypes("date IS NOT NULL AND active = 1 and automatic = 1") as $type) {
		$date = $thisYear.substr($type['date'], 4);
		if(strcmp(strtotime($date), $today) < 0)
			$date = (1+$thisYear).substr($type['date'], 4);
			
		if(($exactLookahead  && strtotime($date) == $lastDay) ||
				(!$exactLookahead  && strtotime($date) <= $lastDay)) {
			$type['date'] = $date;
			$holidays[] = $type;
		}
	}
	return $holidays;
}

function upcomingHolidayRecurringApptsTable($appts) {
	$holiday = '';
	$columns = explodePairsLine('cb| ||client|Client||timeofday|Time||service|Service||price|Price||provider|Sitter||surcharges|Surcharges'); //pets|Pets||
	require_once "preference-fns.php";
	$omitRevenue = getUserPreference($_SESSION["auth_user_id"], 'suppressRevenueDisplay');
	if($omitRevenue) unset($columns['price']);
	$numCols = count($columns);
	foreach($appts as $i => $appt) {
		if($holiday != $appt['holiday']) {
			$holiday = $appt['holiday'];
			$label = $appt['holiday']." - ".longDayAndDate(strtotime($appt['date']));
			$rows[] = array('date'=>$appt['date'], '#CUSTOM_ROW#'=>"<tr><td style='background:yellow;font-weight:bold;text-align:center;' class='sortableListCell' colspan=$numCols>$label</td></tr>");
			$rowClasses[] = '';
		}
		$row = $appt;
		$cbclass = $row['canceled'] ? "class='canceled'" : "";
		$row['cb'] = "<input type='checkbox' id='appt_{$appt['appointmentid']}' name='appt_{$appt['appointmentid']}' $cbclass>";
		$row['client'] = "{$row['client']}";
		if(!$row['email'] || !$row['userid']) {
			$drawback = array();
			if(!$row['email']) $drawback[] = "email address";
			if(!$row['userid']) $drawback[] = "system login";
			$drawback = "Has no ".join(' or ', $drawback);
			$row['client'] = "<img src='art/notsomuch.gif' title='$drawback.' height=15> {$row['client']}";
		}
		$row['client'] = "<label for='appt_{$appt['appointmentid']}'>{$row['client']}</label>";
		$row['service'] = holidayServiceLink($appt);
		//$row['date'] = date('m/d/Y', strtotime($appt['date']));
		$row['price'] = dollarAmount($appt['charge']+$appt['adjustment']);

		if($row['surcharges']) {
			$surcharges = array();
			foreach($row['surcharges'] as $surcharge) $surcharges[] = holidaySurchargeLink($surcharge);
			$row['surcharges'] = join(', ', $surcharges);
		}
		$row['surcharges'] = $row['surcharges']."<img align=right src='art/add-surcharge.gif' onclick=\"addSurcharge({$row['appointmentid']})\" title='Add a Surcharge';>";
		$rows[] = $row;
		$rowClasses[] = $row['canceled'] ? 'canceledtask' : 'futuretask';
	}
	$colClasses = array('price'=>'dollaramountheader');
	tableFrom($columns, $rows, 'width=100%', $class=null, $headerClass='sortableListHeader', $headerRowClass=null, $dataCellClass='sortableListCell', $columnSorts=null, $rowClasses, $colClasses);
}

function holidayServiceLink($appt) {
	$petsTitle = $appt['pets'] 
	  ? htmlentities("Pets: {$appt['pets']}", ENT_QUOTES)
	  : "No Pets specified.";
	$targetPage = 'appointment-edit.php';
	return fauxLink($_SESSION['servicenames'][$appt['servicecode']],
	       "openConsoleWindow(\"editappt\", \"$targetPage?id={$appt['appointmentid']}\",{$_SESSION['dims']['appointment-edit']})",
	       1,
	       $petsTitle);
}
		
function holidaySurchargeLink($surcharge)  {
	global $providerNames;
	$providerNames = $providerNames ? $providerNames : getProviderShortNames();
	$surchargesByType = getSurchargeTypesById();
	$title = $providerNames[$surcharge['providerptr']] ? $providerNames[$surcharge['providerptr']] : 'Unassigned' ;
	$title = "Sitter: $title ".dollarAmount($surcharge['charge']);
	$roDispatcher = userRole() == 'd' && !strpos($_SESSION['rights'], '#ev');
	$editScript = "surcharge-edit.php?id={$surcharge['surchargeid']}";
	$chargeLabel = $surchargesByType[$surcharge['surchargecode']];
	if($roDispatcher)
		return fauxLink($chargeLabel, "", 'noEcho', $title);
	else
		return 
			fauxLink($chargeLabel,
										"openConsoleWindow(\"editsurcharge\", \"$editScript\",530,450 )",
										'noEcho',
										$title);
}
	

function upcomingHolidayRecurringAppts($exactLookahead=false, $lookahead=null) {
	$holidays = upcomingHolidays($exactLookahead, $lookahead);
//if(mattOnlyTEST()) echo "H ". print_r($holidays, 1);	
	if(!$holidays) return array();
	foreach($holidays as $holiday) {
		$dates[] = $holiday['date'];
		$dayNames[$holiday['date']] = $holiday['label'];
	}
	$dates = "'".join("','", $dates)."'";
	$appts = fetchAssociationsKeyedBy(
		"SELECT * 
		 FROM tblappointment
		 WHERE recurringpackage = 1 
		 	AND date IN ($dates)", 'appointmentid');
	if(!$appts) return array();
	
	$surcharges = fetchAssociations(
		"SELECT tblsurcharge.*, label 
		 FROM tblsurcharge
		 LEFT JOIN tblsurchargetype ON surchargetypeid = surchargecode
		 WHERE appointmentptr IN (".join(',', array_keys($appts)).") AND CANCELED IS NULL");
		 
	foreach($surcharges as $surcharge) 
		$appts[$surcharge['appointmentptr']]['surcharges'][] = $surcharge;
	
	
	$providerNames = getProviderShortNames();
	foreach($appts as $i => $appt) {
		$clients[] = $appt['clientptr'];
		$appts[$i]['provider'] = $providerNames[$appt['providerptr']];
		$appts[$i]['holiday'] = $dayNames[$appt['date']];
		//$appts[$i]['surcharges'] = $appt['surcharges'] ? join(', ', $appt['surcharges']) : null;
	}
	$clients = getClientDetails($clients, array('sortname', 'email', 'userid'));
	foreach($appts as $i => $appt) {
		$appts[$i]['client'] = $clients[$appt['clientptr']]['clientname'];
		$appts[$i]['sortname'] = $clients[$appt['clientptr']]['sortname'];
		$appts[$i]['email'] = $clients[$appt['clientptr']]['email'];
		$appts[$i]['userid'] = $clients[$appt['clientptr']]['userid'];
	}
	
	usort($appts, 'holidayDateSort');
	return $appts;
}
	
function holidayDateSort($a, $b) {
	$result = strcmp($a['date'], $b['date']);
	if(!$result) {
		$result = strcmp($a['sortname'], $b['sortname']);
	}
	if(!$result) {
		$result = strcmp($a['starttime'], $b['starttime']);
	}
	return $result;
}

function issueSystemHolidayNotice($exactLookahead) {
	require_once "surcharge-fns.php";
	require_once "client-fns.php";
	require_once "provider-fns.php";
	require_once "gui-fns.php";
	require_once "appointment-fns.php";
	require_once "request-fns.php";
	$appts = upcomingHolidayRecurringAppts($exactLookahead);
	if($appts) {  // if there are any appts on the exact holiday at the end of the lookeahead period...
		$appts = upcomingHolidayRecurringAppts($exactLookahead=false); // gather all upcoming holiday recurring appts

		foreach(upcomingHolidays() as $holiday) $holidays[$holiday['date']] = "{$holiday['label']} (".longDayAndDate(strtotime($holiday['date'])).")";
		$apptHolidays = array();
		foreach($appts as $appt) if($holidays[$appt['date']]) $apptHolidays[] = $holidays[$appt['date']];
		$apptHolidays = array_unique($apptHolidays);
		$msg = "There are ".count($appts)." recurring visits on the following upcoming holidays:<p>".join(', ',$apptHolidays)."<p>";
		$url = globalURL('holidays-recurring.php');
		$link = "<a href='$url'>Upcoming Holiday Recurring Visits</a>";
		$scriptlink = "<a href='#' dest = '$url' onclick='parentSwitch(this)'>Upcoming Holiday Recurring Visits</a>";
		$msg .= "Please review them on the "
						."<noscript>$link</noscript>"
						."<script language='javascript'>document.write(\"$scriptlink\");</script>"
						." page.";
		$request = array();
		$request['subject'] = "Upcoming Holiday Recurring Visits (".count($appts).")";
		$request['requesttype'] = 'SystemNotification';
		$request['note'] = $msg;
//echo print_r(upcomingHolidays(),1).'<p>'.$msg;exit;		
		global $db; echo date('m/d/Y H:i:s')." (local time) Generated Holiday Request for $db\n";
		//saveNewClientRequest($request, $notify=true);
		saveNewSystemNotificationRequest($request['subject'], $msg, $extraFields = null);
	}
		/*ob_start();
		ob_implicit_flush(0);
		upcomingHolidayRecurringApptsTable($appts);
		$msg .= ob_get_contents();
		ob_end_clean();*/
	
}

function findHolidayProblems($year) {
	require_once "gui-fns.php";
	$datePattern = "/([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))/";
	$countries = explodePairsLine('US|US||Canada|CA||Australia|AU||UK|UK'); // ||New Zealand|NZ
	foreach($countries as $label => $country) {
		$pairs = array();
		$i18n = getI18NProperties($country);
		$NO_SESSION['i18n'] = $i18n;
		$holidays = $i18n['Holidays'];
		asort($holidays);
		foreach($holidays as $holidaylabel => $dates) {
			$found = false;
			foreach(explode(',', $dates) as $date)
				if(strpos($date, "$year") === 0) $found = true;
			if(!$found) {
				echo "$label (i18n-EN-$country.txt) $holidaylabel in year $year is not set.<br>";
				$error = 1;
			}
			else if(!preg_match($datePattern, "$date")) {
				echo "$label (i18n-EN-$country.txt) $holidaylabel in year $year is not a valid date [$date].<br>";
				$error = 1;
			}
		}
	}
	return $error;
}

function holidayTables($year) {
	$_SESSION = null;
	global $NO_SESSION;
	require_once "gui-fns.php";
	$countries = explodePairsLine('US|US||Canada|CA||Australia|AU||UK|UK'); //||New Zealand|NZ
	
	echo "<div style=\"width:670px;overflow:scroll\">\n\n<table style=\"font-size:0.8em;margin-top:0px;\"></tr>";
	
	foreach($countries as $label => $country) {
		$pairs = array();
		$i18n = getI18NProperties($country);
		$NO_SESSION['i18n'] = $i18n;
		$holidays = $i18n['Holidays'];
		asort($holidays);
//echo "$country<hr>".print_r($holidays,1);		
		foreach($holidays as $holidaylabel => $dates) {
//echo "<hr>$country $holidaylabel ==> ".print_r($dates,1);					
			foreach(explode(',', $dates) as $date) {
//echo "<br>$holidaylabel($year) ==> |$date| pos:".(strpos($date, "$year"));					
				if(strpos($date, "$year") === 0) {
					$pairs[$holidaylabel] = $date;
					$allHolidays[$holidaylabel][$country] = $date;
				}
			}
		}
		echo "<td valign=top><b>$label Holidays</b><br><table border=1 bordercolor=black style='font-size:0.8em;margin-top:0px;width:180px;'>";
		foreach($pairs as $label => $date) {
			$date = str_replace(' ', '&nbsp;', trim(month3Date(strtotime($date))));
			$label = str_replace(' ', '&nbsp;', trim($label));
			echo "<tr><td>$label</td><td>$date</td></tr>";
		}
		echo "</table></td>";
	}
	echo "</table>\n\n</td>";
	echo "</tr></div>";
	
	
	echo "\n\n<hr>\n\n";
	echo '<div style="width:670px;overflow:scroll"><table style="font-size:0.8em;margin-top:0px;"><table border=1 bordercolor=black style="font-size:0.8em;margin-top:0px;width:100%;"></tr>';
	echo "<tr><td></&nbsp;</td><th>".join('</th><th>', array_keys($countries))."</th></tr>";
	foreach($allHolidays as $holidaylabel => $countryEntries) {
		echo "<tr><td>$holidaylabel</td>";
		foreach($countries as $label => $country) {
			$label = str_replace(' ', '&nbsp;', trim($label));
			$date = $countryEntries[$country] ? $date = str_replace(' ', '&nbsp;', trim(month3Date(strtotime($countryEntries[$country])))) : '&nbsp;';
			echo "<td>$date</td>";
		}
		echo "</tr>";
	}
		echo "</table></td>";
		
}