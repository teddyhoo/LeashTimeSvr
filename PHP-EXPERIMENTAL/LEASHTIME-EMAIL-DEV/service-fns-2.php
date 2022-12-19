<?
// service-fns.php
require_once "provider-fns.php";
require_once "client-fns.php";
require_once "appointment-fns-2.php";
require_once "surcharge-fns.php";
$serviceLineLabel = 'Service';

$raw = explode(',', 'daysofweek,Days of Week,timeofday,Time of Day,providerptr,Service Provider,pets,Pets,servicecode,Service Type,'.
                    'charge,Charge,adjustment,Adjustment,rate,Rate,bonus,Bonus,surchargenote,Surcharge Note');
for($i=0;$i < count($raw) - 1; $i+=2) $serviceFields[$raw[$i]] = $raw[$i+1];

$repeatingPackageFields = explode(',', 'startdate,weeklyadjustment,suspenddate,cancellationdate,resumedate,'.
																	'cancellationreason,notes,previousversionptr,current,effectivedate,prepaid,weeks,firstsunday');

$monthlyPackageFields = array_merge($repeatingPackageFields, explode(',', 'totalprice'));


$nonrepeatingPackageFields = explode(',', 'startdate,enddate,packageprice,departuredate,cancellationdate,returndate,'.
																	'cancellationreason,notes,previousversionptr,current,howtocontact,preemptrecurringappts,'.
																	'onedaypackage,prepaid,irregular,billingreminders');

$irregularPackageFields = explode(',', 'startdate,enddate,packageprice,departuredate,cancellationdate,returndate,'.
																	'cancellationreason,notes,previousversionptr,current,howtocontact,preemptrecurringappts,'.
																	'prepaid,irregular,billingreminders');
																	



function packageSMSDescription($package, $clientName=null, $alsoExclude='', $exclude='packageid,clientptr,previousversionptr,preemptrecurringappts,current,onedaypackage,irregular,prepaid,weeklyadjustment,') {
	// packageDescriptionHTML is simply formatted (except for the services list) so we can strip tags and change
	// <p> tags to \n's.  But for the services section we must tell packageDescriptionHTML to call
	// packageServiceSMSDescription instead of packageServiceDescription via $htmlFormatted=false
	// since packageServiceDescription is more complicated in format
	$descr = strip_tags(str_replace('<p>', "\n", packageDescriptionHTML($package, $clientName, $alsoExclude, $exclude, $htmlFormatted=false)));
	return $descr;
}

function packageDescriptionHTML($package, $clientName=null, $alsoExclude='', $exclude='packageid,clientptr,previousversionptr,preemptrecurringappts,current,onedaypackage,irregular,prepaid,weeklyadjustment,', $htmlFormatted=true) {
	// $htmlFormatted - this will fart out HTML in any case, but $htmlFormatted controls whether
	// packageServiceDescription or packageServiceSMSDescription is called at the end
	$exclude = array_merge(explode(',', $exclude), explode(',', $alsoExclude));
	 
	$labels = isset($package['enddate'])
		? explodePairsLine('startdate|Start Date||enddate|End Date||packageprice|Package Price||departuredate|Departing'
												.'||cancellationdate|Canceled||returndate|Returning||cancellationreason|Canceled because||notes|Notes'
												.'||previousversionptr|previousversionptr'
												.'||current|current||howtocontact|How to Contact||preemptrecurringappts|preemptrecurringappts'
												.'||onedaypackage|onedaypackage||prepaid|Prepaid||irregular|EZ-Schedule')
		: explodePairsLine('startdate|Start Date||weeklyadjustment|nada||suspenddate|Suspended||cancellationdate|Canceled'
												.'||resumedate|Resumed||cancellationreason|Canceled because||notes|Notes'
												.'||previousversionptr|previousversionptr||current|current||effectivedate|Changes Effective'
												.'||prepaid|Prepaid||totalprice|Package Price');
	$tbldescr = fetchAssociations(isset($package['onedaypackage']) ? "DESCRIBE tblservicepackage" : "DESCRIBE tblrecurringpackage");
	foreach($tbldescr as $fld) $table[$fld['Field']] = $fld['Type'];
	$type = $package['monthly'] ? 'Fixed Price Monthly' : (
					$package['onedaypackage'] ? 'One Day' : (
					$package['irregular'] ? 'EZ' : (
					isset($package['enddate']) ? 'Short Term' : 
					'Weekly Recurring')));
	$clientName = $clientName ? " for $clientName" : '';
	$descr = "<b>$type Schedule$clientName</b><p>";
	$descr .= fieldDescription($table, 'startdate', $package, $labels);
	if(shouldInclude('enddate', $package, $exclude) && !$package['onedaypackage']) 
		$descr .= " ".fieldDescription($table, 'enddate', $package, $labels);
	$descr .= "<p>";
	if($package['effectivedate'] && $package['effectivedate'] < date('Y-m-d')) $package['effectivedate'] = date('Y-m-d');
	if(shouldInclude('effectivedate', $package, $exclude))
		$descr .= fieldDescription($table, 'effectivedate', $package, $labels).'<p>';
	if(shouldInclude('departuredate', $package, $exclude))
		$descr .= fieldDescription($table, 'departuredate', $package, $labels)." ".fieldDescription($table, 'returndate', $package, $labels).'<p>';
	if(shouldInclude('cancellationdate', $package, $exclude))
		$descr .= fieldDescription($table, 'cancellationdate', $package, $labels)." ".fieldDescription($table, 'cancellationreason', $package, $labels).'<p>';
	if(shouldInclude('suspenddate', $package, $exclude))
		$descr .= fieldDescription($table, 'suspenddate', $package, $labels)." ".fieldDescription($table, 'resumedate', $package, $labels).'<p>';
	if(shouldInclude('howtocontact', $package, $exclude))
		$descr .= fieldDescription($table, 'howtocontact', $package, $labels).'<p>';
	if(shouldInclude('preemptrecurringappts', $package, $exclude, true))
		$descr .= fieldDescription($table, 'preemptrecurringappts', $package, $labels).'<p>';
	if(shouldInclude('onedaypackage', $package, $exclude, true))
		$descr .= fieldDescription($table, 'onedaypackage', $package, $labels).'<p>';
	if(shouldInclude('prepaid', $package, $exclude, true))
		$descr .= fieldDescription($table, 'prepaid', $package, $labels).'<p>';
	if(shouldInclude('irregular', $package, $exclude, true))
		$descr .= fieldDescription($table, 'irregular', $package, $labels).'<p>';
	if(shouldInclude('packageprice', $package, $exclude))
		$descr .= fieldDescription($table, 'packageprice', $package, $labels).'<p>';
	if(shouldInclude('totalprice', $package, $exclude))
		$descr .= fieldDescription($table, 'totalprice', $package, $labels).'<p>';
	if(shouldInclude('notes', $package, $exclude))
		$descr .= fieldDescription($table, 'notes', $package, $labels).'<p>';
	return $htmlFormatted ? $descr.packageServiceDescription($package) : packageServiceSMSDescription($package);
}

function packageServiceSMSDescription($package) {
	$services = fetchAssociations(
							"SELECT tblservice.*, tblservicetype.label, CONCAT_WS(' ', fname, lname) as providername,
									if(firstLastOrBetween IS NULL, 0,
										if(firstLastOrBetween = 'first', 1,
											if(firstLastOrBetween = 'last', 3, 2))) as days,
								STR_TO_DATE(LEFT(timeofday, LOCATE('-', timeofday)-1), '%h:%i %p')	as starttime
								FROM tblservice
								LEFT JOIN tblservicetype ON servicetypeid = servicecode
								LEFT JOIN tblprovider ON providerid = providerptr
								WHERE packageptr = {$package['packageid']}
								ORDER BY days, starttime");
	if(!$services) return '';
	$descr = "\n\nServices\n";
	$lastDay = 0;
	$days = array('','First Day','Days In Between', 'Last Day');
	foreach($services as $service) {
		if($service['days'] != $lastDay) {
			$lastDay = $service['days'];
			$descr .= "{$days[$lastDay]}\n";
		}
		$dow = $service['daysofweek'] ? $service['daysofweek'] : '';
		$descr .= "$dow {$service['timeofday']} {$service['label']} {$service['providername']}\n";
	}
	return $descr;
}
function packageServiceDescription($package) {
	$services = fetchAssociations(
							"SELECT tblservice.*, tblservicetype.label, CONCAT_WS(' ', fname, lname) as providername,
									if(firstLastOrBetween IS NULL, 0,
										if(firstLastOrBetween = 'first', 1,
											if(firstLastOrBetween = 'last', 3, 2))) as days,
								STR_TO_DATE(LEFT(timeofday, LOCATE('-', timeofday)-1), '%h:%i %p')	as starttime
								FROM tblservice
								LEFT JOIN tblservicetype ON servicetypeid = servicecode
								LEFT JOIN tblprovider ON providerid = providerptr
								WHERE packageptr = {$package['packageid']}
								ORDER BY days, starttime");
	$descr = "<b>Services</b><p>";
	$lastDay = 0;
	$days = array('','First Day','Days In Between', 'Last Day');
	$descr = "<table style='width:100%'>";
	foreach($services as $service) {
		if($service['days'] != $lastDay) {
			$lastDay = $service['days'];
			$descr .= "<tr colspan=5><td><u>{$days[$lastDay]}</u></td></tr>\n";
		}
		$dow = $service['daysofweek'] ? $service['daysofweek'] : '&nbsp;';
		$descr .= "<tr><td>$dow</td><td>{$service['timeofday']}</td><td>{$service['label']}</td><td>{$service['providername']}</td></tr>\n";
	}
	return $descr . "</table>";
}
function shouldInclude($field, $package, $exclude, $ignoreValue=false) {
	if(in_array($field, $exclude)) return false;
	if(!isset($package[$field]) || (!$package[$field]  && !$ignoreValue)) return false;
	return true;
}
function fieldDescription(&$table, $field, $package, &$labels) {
	$val = $package[$field];
	if($table[$field] == 'date') $val = $val && $val[0] ? shortDate(strtotime($val)) : '';
	if($table[$field] == 'datetime') $val = $val && $val[0] ? shortDateAndTime(strtotime($val)) : '';
	if($table[$field] == 'tinyint(1)') $val = $val ? 'Yes' : 'No';
	$label = $labels[$field];
	if($table[$field] == 'text') $label = '<br>'.$label;
	return '<b>'.$label.': </b>'.$val;
}
function oneDayRecurringServiceTable(&$services, &$activeProviders, &$serviceSelections) {
	hiddenElement("onedaypackage", 1);
	nonRecurringServiceTable($services, $activeProviders, $serviceSelections, 'first', 'oneDay');
}
function nonRecurringServiceTabs(&$services, &$activeProviders, &$serviceSelections/*, $daysofweekrequired=true*/) {
	// set up three tabs, each with a service table
	$labelAndIds = array("firstday"=>'First Day', "daysinbetween"=>'Days in between', "lastday"=>'Last Day', 'notes'=>'Notes');
	$initialSelection = $tab ? $tab : 'firstday';
	$boxHeight = 100;

	startTabBox("100%", $labelAndIds, $initialSelection, 120);
	
	startFixedHeightTabPage('firstday', $initialSelection, $labelAndIds, $boxHeight);
	nonRecurringServiceTable($services, $activeProviders, $serviceSelections, 'first');
	endTabPage('firstday', $labelAndIds, null, null, null, true);
	
	startFixedHeightTabPage('daysinbetween', $initialSelection, $labelAndIds, $boxHeight);
	echo "<center>";
	echoButton('','Copy the First Day Visits Here','copyServices("first_", "between_")');
	echo "</center>";
	nonRecurringServiceTable($services, $activeProviders, $serviceSelections, "between");
	endTabPage('daysinbetween', $labelAndIds, null, null, null, true);
	
	startFixedHeightTabPage('lastday', $initialSelection, $labelAndIds, $boxHeight);
	echo "<center>";
	echoButton('','Copy the First Day Visits Here','copyServices("first_", "last_")');
	echo " ";
	echoButton('','Copy the In Between Day Visits Here','copyServices("between_", "last_")');
	echo "</center>";
	nonRecurringServiceTable($services, $activeProviders, $serviceSelections, 'last');
	endTabPage('lastday', $labelAndIds, null, null, null, true);

	startFixedHeightTabPage('notes', $initialSelection, $labelAndIds, $boxHeight);
	nonRecurringNotesTab();
	endTabPage('notes', $labelAndIds, null, null, null, true);
	
	
	endTabBox();
}
function nonRecurringNotesTab() {
	global $package, $packageid;
  $display = $packageid && !$package['cancellationdate'] ? "style=\"{$_SESSION['tableRowDisplayMode']}\"" : 'style="display:none;"';
	echo "
<table width=100%'>
<tr><td colspan=9>Notes:</td></tr>
<tr><td colspan=9><textarea id='notes' name='notes' cols=60 rows=3>{$package['notes']}</textarea></tr>
<tr><td colspan=9>How to Contact:</td></tr>
<tr><td colspan=9><textarea id='howtocontact' name='howtocontact' cols=60 rows=2>{$package['howtocontact']}</textarea></tr>
<tr>
  <td colspan=3>
";  
  calendarSet('Departure Date:', 'departuredate', $package['departuredate']);
  echo "
  <td colspan=6 name='CancellationDetails' $display>";
  calendarSet('Cancel Service On:', 'cancellationdate', $package['cancellationdate']);
  
  echo "
  </tr>
<tr>
  <td colspan=3>
";  
  
  calendarSet('Return Date:', 'returndate', $package['returndate']);
  echo "\n  <td colspan=6 name='CancellationDetails' $display>&nbsp;&nbsp;&nbsp;&nbsp;";
  labeledInput('Reason:', 'cancellationreason', $package['cancellationreason'], null, 'emailInput');
	echo "</tr>\n</table>";
}
function nonRecurringServiceTable(&$allServices, &$activeProviders, &$serviceSelections, $firstLastOrBetween) {
	global $serviceLineLabel;
	$daysofweekrequired = $firstLastOrBetween == 'between';
	$extraServices = 10;
	$services = array();
	foreach($allServices as $service) 
	  if($service['firstLastOrBetween'] == $firstLastOrBetween)
	    $services[] = $service;
	$prefix = $firstLastOrBetween.'_';
	echo "<table>\n";
	$colHeads = explode(',',($daysofweekrequired ? 'Days of Week,' : '')."Time of Day,Service Type,Sitter,Pets,Charge,Adjust,Rate,Bonus,");
	//echo '<tr><th>&nbsp;</th><th>'.join('</th><th>', $colHeads).'</th></tr>';
	echo '<tr><th>&nbsp;</th>';

  $onState = 	$_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
	$chargeRateStyle = $_SESSION['preferences']['showChargeAndRateInScheduleEditorsByDefault'] ? $onState : "style='display:none'";
	foreach($colHeads as $label)
		if(in_array($label, array('Charge','Adjust','Rate','Bonus'))) 
			echo "<th id='".$prefix."$label"."_header' style='display:$chargeRateStyle'>$label</th>";
			else echo "<th>$label</th>";
	echo '</tr>';

	$visibleSections = max(1, count($services));

	// add a section for each line + five extra sections
	for($i=1; $i <= count($services)+$extraServices; $i++) {
		if($i <= count($services)) {
			$service = current($services);
			next($services); 
		}
		else $service = array('active'=>1, 'daysofweek'=>'Every Day');
//echo '['.print_r($service, 1).']';	exit;
    $addAnother = $i < count($services)+5 ? 'button' : 'final';
	
		addServiceLine($i, $service, $visibleSections, $addAnother, $activeProviders, $serviceSelections, $daysofweekrequired, $prefix);
	}
	echo "\n</table> <!-- services -->\n";
	hiddenElement($prefix."services_visible", $visibleSections);
	echo "\n";
}
function serviceTable(&$services, &$activeProviders, &$serviceSelections, $daysofweekrequired=true, $prefix=null) {
	global $serviceLineLabel;
	echo "<table>\n";
	$dowLabel = $daysofweekrequired ? 'Days of Week,' : '';
	$colHeads = explode(',',$dowLabel."Time of Day,Service Type,Sitter,Pets,Charge,Adjust,Rate,Bonus,");
	//echo '<tr><th>&nbsp;</th><th>'.join('</th><th>', $colHeads).'</th></tr>';
	echo '<tr><th>&nbsp;</th>';
  $onState = 	$_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
	$chargeRateStyle = $_SESSION['preferences']['showChargeAndRateInScheduleEditorsByDefault'] ? $onState : "style='display:none'";
	foreach($colHeads as $label)
		if(in_array($label, array('Charge','Adjust','Rate','Bonus'))) 
			echo "<th id='".$prefix."$label"."_header' style='display:$chargeRateStyle'>$label</th>";
			else echo "<th>$label</th>";
	echo '</tr>';

	$visibleSections = max(1, count($services));
	hiddenElement("services_visible", $visibleSections);

	// add a section for each line + five extra sections
	for($i=1; $i <= count($services)+5; $i++) {
		if($i <= count($services)) {
			$service = current($services);
			next($services); 
		}
		else $service = array('active'=>1);
//echo '['.print_r($service, 1).']';	exit;
    $addAnother = $i < count($services)+5 ? 'button' : 'final';
//echo "<tr><td colspan=5>i: $i Num Services: ".count($services)." - addAnother: ".($i >= count($services) && $i < count($services)+5)."<tr>";	
		addServiceLine($i, $service, $visibleSections, $addAnother, $activeProviders, $serviceSelections, $daysofweekrequired);
	}
	if(!$daysofweekrequired) echo "\n</table> <!-- services -->\n";
}
function addServiceLine($number, $service, $visibleSections, $addAnother=false, &$activeProviders, &$serviceSelections, $daysofweekrequired=true, $prefix='') {
	global $serviceFields, $serviceLineLabel, $clientDetails, $serviceLineFields, $serviceLineConstraints, $allPetNames;
	$defaultTimeFrame = isset($_SESSION['preferences']['defaultTimeFrame']) ? $_SESSION['preferences']['defaultTimeFrame'] : '';
//echo ($defaultTimeFrame);exit;
	$hidden = $number > $visibleSections;
	$initialDisplay = $hidden ? 'none' : $_SESSION['tableRowDisplayMode'];
	echo "\n"; hiddenElement($prefix."service_$number", $service['serviceid']);
	echo "\n<tr id='$prefix"."service_row_$number' style='display:$initialDisplay'>\n";
	echo "<td style='width:22px;padding-right:0px;' title='Delete this line.'>".
	      "<img src='art/delete.gif' height=22 width=22 border=0 onClick=\"deleteLine('$prefix','$number')\"></td>\n";
	if($daysofweekrequired) {
		echo "\n<td style='padding:2px;width:100px;'>";
	  buttonDiv($prefix."div_daysofweek_$number",$prefix."daysofweek_$number","showWeekdayGridInContentDiv(event, \"".$prefix."div_daysofweek_$number\")",
	          ($service['daysofweek'] ? $service['daysofweek'] : ''));
	  echo "</td>";
	}
	echo "\n<td style='padding:2px;width:110px;'>";
	buttonDiv($prefix."div_timeofday_$number", $prefix."timeofday_$number", "showTimeFramerInContentDiv(event, \"".$prefix."div_timeofday_$number\")",
	          ($service['timeofday'] ? $service['timeofday'] : $defaultTimeFrame));
	echo "</td>";
	
	echo "<td>";
	$maxLen = 0;
	foreach($serviceSelections as $label => $code) $maxLen = max(strlen($label), $maxLen);
	$servSelectClass = $maxLen > 35 ? 'fontSize0_9em' : 'standardInput';
	selectElement('', $prefix."servicecode_$number", $service['servicecode'], $serviceSelections, "updateServiceVals(this, $number, \"$prefix\")", null, $servSelectClass);
	echo "</td>";
	
	echo "<td>";
	$activeProviders = array_merge(array('--Unassigned--' => 0), $activeProviders);
		//echo "[".$service['providerptr']."]";exit;  providerInArray($clientDetails['defaultproviderptr'], $activeProviders)
	if(!$service['providerptr'] && !providerInArray($clientDetails['defaultproviderptr'], $activeProviders)) {
		$deadProvider = providerShortName(getProvider($clientDetails['defaultproviderptr']));
	  $deadProviderVisibility = 'display:inline;';
	  $showIt = 'X';
	}
	else {
		$deadProviderVisibility = 'display:none;';
		$showIt = '';
	}
	echo "<label id='".$prefix."providerwarning_$number' style='font-weight: bold;font-size: 1.1em;color:red;$deadProviderVisibility' title='Default sitter $deadProvider is inactive!'>$showIt</label>";
	$servprovider = $service['providerptr'];// ? $service['providerptr'] : ($clientDetails['defaultproviderptr'] ? $clientDetails['defaultproviderptr'] : '');
	if(!$servprovider && !$service['serviceid']) $servprovider = $clientDetails['defaultproviderptr'] ? $clientDetails['defaultproviderptr'] : 0;
  selectElement('', $prefix."providerptr_$number", $servprovider, $activeProviders, "providerChanged(this, $number, \"$prefix\")");
	echo "</td>";
	echo "\n<td style='padding:2px;'>";
	$defaultPet = !$allPetNames || strpos($allPetNames,',') ? 'All Pets' : $allPetNames; // if 1 pet, use that pet
	$petsTitle = getActiveClientPetsTip($clientDetails['clientid'], $service['pets']);
	buttonDiv($prefix."div_pets_$number", $prefix."pets_$number", "showPetGridInContentDiv(event, \"".$prefix."div_pets_$number\")",
	           ($service['pets'] ? $service['pets'] : $defaultPet), '', '', $petsTitle);
	echo "</td>";

  $onBlur = "onBlur='displayTotals()'";
  
	$surchargeNoteId = $prefix."surchargenote_$number";
	hiddenElement($surchargeNoteId, $service['surchargenote']);
  $onChange = "onChange='editSurcharge(\"$surchargeNoteId\", 0)'";
  
  $surchargeButton = "<img style='cursor:pointer;' onClick='editSurcharge(\"$surchargeNoteId\", 1)' width=15 height=15 src='art/surcharge-button.gif'>";

  $openChargeRateButtonId = $prefix."cell_ChargeRate_$number";
  
  $onState = 	$_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';

  $chargeRateStyle = $_SESSION['preferences']['showChargeAndRateInScheduleEditorsByDefault'] ? $onState : "style='display:none'";
	echo "<td id='$openChargeRateButtonId"."_charge' $chargeRateStyle><input id='".$prefix."charge_$number' type='hidden' name='".$prefix."charge_$number' size=2 value='{$service['charge']}'>
	          <div id='".$prefix."div_charge"."_$number'>{$service['charge']}</div></td>
	      <td id='$openChargeRateButtonId"."_adj' $chargeRateStyle><input name='".$prefix."adjustment_$number' id='".$prefix."adjustment_$number' size=2 value='{$service['adjustment']}' $onChange $onBlur autocomplete='off'></td>
	      <td id='$openChargeRateButtonId"."_rate' $chargeRateStyle><input id='".$prefix."rate_$number' type='hidden' name='".$prefix."rate_$number' size=2 value='{$service['rate']}'>
	          <div id='".$prefix."div_rate_$number'>{$service['rate']}</div></td>
	      <td id='$openChargeRateButtonId"."_bonus' $chargeRateStyle><input name='".$prefix."bonus_$number' id='".$prefix."bonus_$number' size=2 value='{$service['bonus']}' $onChange $onBlur autocomplete='off'></td>
	      <td  id='$openChargeRateButtonId"."_surcharge' $chargeRateStyle>$surchargeButton</td>";
	$chargeButtonTitle = "Charge: \${$service['charge']}"
												. ($service['adjustment'] ? "+\${$service['adjustment']}" : '')
												. " ~ Rate: \${$service['rate']}"
												. ($service['bonus'] ? "+\${$service['bonus']}" : '')
												;
	$chargeButtonTitle = "title='$chargeButtonTitle'";
  $openChargeRateButton = "<img id='$openChargeRateButtonId"."_button' style='cursor:pointer;' onClick='toggleCharges(\"$openChargeRateButtonId\", 1)' width=15 height=15 src='art/fatdollarsign.gif' $chargeButtonTitle>";
	echo "<td id='$openChargeRateButtonId'>$openChargeRateButton</td>";
//     
	$serviceLineConstraints .= !$serviceLineConstraints ? '[[' : "],\n[";
	$first = true;
	foreach(array('bonus','adjustment',/*'providerptr',*/'daysofweek','timeofday') as $field) {
		if(!$daysofweekrequired && ($field == 'daysofweek')) continue
		$serviceLineFields .= $serviceLineFields ? ',' : '';
		$serviceLineFields .= $field."_$number,{$serviceFields[$field]},";
		$serviceLineConstraints .= $first ? "'$prefix"."servicecode_$number', " :',';
		$first = false;
		if(in_array($field, array('bonus','adjustment')))
	    $serviceLineConstraints .= "'$prefix"."$field"."_$number','','FLOAT'\n";
	  else if($field == 'providerptr')
			$serviceLineConstraints .= "'$prefix"."$field"."_$number','','R'\n";
	  else if($field == 'daysofweek')
			$serviceLineConstraints .= "'$prefix"."$field"."_$number','','R'\n";
	  else if($field == 'timeofday')
			$serviceLineConstraints .= "'$prefix"."$field"."_$number','','R'\n";
	  else if($field == 'pets')
			$serviceLineConstraints .= "'$prefix"."$field"."_$number','','R'\n";
	}

  echo "</tr>";
  if(!in_array($service['servicecode'], $serviceSelections)) {
		$allServiceNames = getAllServiceNamesById();
		$nblank = $daysofweekrequired ? 3 : 2;
		echo "<tr><td colspan=$nblank style='padding-top:0px;'>&nbsp;</td><td colspan=3 class='tiplooks' style='text-align:left;padding-top:0px;'>{$allServiceNames[$service['servicecode']]}</td";
	}
	if($addAnother == 'button') {
		$next = $number+1;
		$initialDisplay = $number < $visibleSections || $hidden ? 'none' : $_SESSION['tableRowDisplayMode'];
		echo "\n<tr id='$prefix"."addAnother_$number' style='display:$initialDisplay;'><td colspan=9 style='padding-top:3px;'>";
		echoButton(null, "Add another $serviceLineLabel", "addAnotherButtonAction($number, \"$prefix\")");
		//echo "<span class='tiplooks'> To drop a $serviceLineLabel, simply leave its Service Type field blank.</span>";
    echo "</td></tr>";
	}
	else if($addAnother == 'final')
	  echo "\n<tr id='$prefix"."addAnother_$number' style='display:$initialDisplay;'>
	    <td colspan=9 class='tiplooks'>To add more services, please Save Changes first and reopen this editor.</td></tr>";
}
function buttonDiv($divid, $formelementid, $onClick, $label, $value='', $extraStyle=null, $title=null, $class=null) {
	$title = $title ? "title = '$title'" : '';
	$class = $class ? "class = '$class'" : '';
	echo 
	  "\n<div id='$divid' $class style='cursor:pointer;border: solid darkgrey 1px;height:15px;padding-left: 2px;overflow:hidden;$extraStyle' 
	onClick='$onClick' $title>$label</div>".
	  hiddenElement($formelementid, $value);
}
function getServiceSelections() {
	return fetchKeyValuePairs("SELECT label, servicetypeid FROM tblservicetype WHERE active=1 ORDER BY active desc, menuorder, label");
}
function getServicesForPackage($packageid, $recurring=NULL) {
	return fetchAssociations("SELECT * FROM tblservice WHERE packageptr = $packageid");
}
function recurringPackageSummary($packageid, $showCharges=null) {
  $package = getRecurringPackage($packageid);
  $numWeeks = $package['weeks'];
  $services = getPackageServices($packageid);
	$columns = explodePairsLine('daysofweek|Days of Week||timeofday|Time of Day||servicetype|Service Type||provider|Sitter||pets|Pets||status|Status');
	if($showCharges) {
		$columns['charge'] = 'Charge';
		$columns['customcharge'] = 'Custom';
	}
	$providers = getProviderShortNames();
	$today = date('Y-m-d');
	if($package['cancellationdate']) {
		$status = 'Canceled as of: '.shortDate(strtotime($package['cancellationdate']));
	}
	if(!$status && $package['suspenddate']) {
		if($package['suspenddate'] <= $today && $package['resumedate'] > $today)
			$status = 'Suspended.  Resumes on '.shortDate(strtotime($package['resumedate']));
	}
	foreach($services as $service) {
		$row = array();
		$row['daysofweek'] = $service['daysofweek'];
		$row['timeofday'] = $service['timeofday'];
		$row['provider'] = $service['providerptr'] ? $providers[$service['providerptr']] : '<font color=red>Unassigned</font>';
		$row['pets'] = strip_tags($service['pets']);
		$row['servicetype'] = $_SESSION['servicenames'][$service['servicecode']];
		$row['status'] = $status ? $status : 'Active';
			// ACTIVE, SUSPENDED WILL RESUME ON MM DD YY, CANCELLED
		if($showCharges) {
			$row['charge'] = $service['charge'];
			$row['customcharge'] = fetchRow0Col0(
				"SELECT charge 
				FROM relclientcharge
				WHERE clientptr = {$service['clientptr']} AND servicetypeptr = {$service['servicecode']}");
			if(!$row['customcharge']) {
				$row['customcharge'] = '<i>'.fetchRow0Col0(
					"SELECT defaultcharge 
					FROM tblservicetype
					WHERE servicetypeid = {$service['servicecode']}").'</i>';
			}
			if(round($row['customcharge']*100) != round($row['charge']*100)) 
				$row['customcharge'] = "<font color=red>{$row['customcharge']}</font>";
		}
		if($numWeeks > 1) {
			$thisWeekNumber = weekNumber(date('Y-m-d'), $package)+1;
			$leftArrow = '&#65513;'; // halfwidth leftwards arrow
			$leftArrow = '&#9664;'; // BLACK LEFT POINTING TRIANGLE
			$thisWeekNumberText = "<span class='boldfont'> $leftArrow THIS WEEK</span>";;
			
			$colCount = count($row);
			$week = $service['week'] + 1;
			if($week != $lastWeek) {
				if($week - $lastWeek > 1) {
					for($i=$lastWeek+1; $i < $week; $i++) {
						$INDICATOR = $i == $thisWeekNumber ? $thisWeekNumberText : '';
						$rows[] = array('#CUSTOM_ROW#' => "<tr><td style='text-decoration:underline' colspan='$colCount'>Week $i$INDICATOR</td></tr>");
						$rows[] = array('#CUSTOM_ROW#' => "<tr><td style='font-stye:italic' colspan='$colCount'>-- No Services Scheduled --</td></tr>");
					}
				}
				$INDICATOR = $week == $thisWeekNumber ? $thisWeekNumberText : '';
				$rows[] = array('#CUSTOM_ROW#' => "<tr><td style='text-decoration:underline' colspan='$colCount'>Week $week$INDICATOR</td></tr>");
			}
			$lastWeek = $week;
		}
		$rows[] = $row;
  }
	if($numWeeks > 1) {
		for($i=$lastWeek+1; $i < $numWeeks+1; $i++) {
			$INDICATOR = $i == $thisWeekNumber ? $thisWeekNumberText : '';
			$rows[] = array('#CUSTOM_ROW#' => "<tr><td style='text-decoration:underline' colspan='$colCount'>Week $i$INDICATOR</td></tr>");
			$rows[] = array('#CUSTOM_ROW#' => "<tr><td style='font-stye:italic' colspan='$colCount'>-- No Services Scheduled --</td></tr>");
		}
	}
	tableFrom($columns, $rows, "width=100%'");
	
}
function getAllServiceNamesById($refresh=0, $noInactiveLabel=false, $setGlobalVar=true) {
	if($_SESSION['allservicenames'] && !$refresh) return $_SESSION['allservicenames'];
	$names = fetchAssociationsKeyedBy("SELECT servicetypeid, label, active  FROM tblservicetype ORDER BY active desc, menuorder, label", servicetypeid);
	foreach($names as $id => $serv)
		$labels[$id] = ($noInactiveLabel || $serv['active'] ? '' : '(inactive) ').$serv['label'];
	if($_SESSION && $setGlobalVar) $_SESSION['allservicenames'] = $labels;
	return $labels;
}
function getServiceNamesById($refresh=0) {
	if($_SESSION['servicenames'] && !$refresh) return $_SESSION['servicenames'];
	getAllServiceNamesById(true); // make sure the complete list is refreshed whenever the active list is refreshed
	$names = fetchKeyValuePairs("SELECT servicetypeid, label  FROM tblservicetype WHERE active=1 ORDER BY active desc, menuorder, label");
	if($_SESSION) $_SESSION['servicenames'] = $names;
	return $names;
}
function getStandardRateDollarsJSArray() {
	//[serviceid,rate,serviceid,rate,serviceid,rate,serviceid,rate,]
	return getServicRateDollarsJSArray(getStandardRatesValues());
}
function getClientChargesJSArray($client) {
	return getServicRateDollarsJSArray(getClientChargeDollars($client));
}
function getClientChargeDollars($client) {
	$charges = getClientCharges($client);
	if(!$charges) return array();
	foreach($charges as $service => $charge) {
	  $charge['value'] = $charge['charge'];
	  $charges[$service] = $charge;
	}
	return $charges;
}
function getAllActiveProviderRateDollarsJSArray() {
	//[provider,[serviceid,rate,serviceid,rate,serviceid,rate,serviceid,rate,...],
	// provider,[serviceid,rate,serviceid,rate,serviceid,rate,serviceid,rate,...],...]
  foreach(getAllActiveProviderRateDollars() as $prov => $rates) {
    if($str) $str .= ",";
    $str .= "\n$prov,\n".getServicRateDollarsJSArray($rates);
	}
	return "[$str]";
}
function getServicRateDollarsJSArray($rates) {
	//[serviceid,rate,serviceid,rate,serviceid,rate,serviceid,rate,]
  foreach($rates as $serv => $rate) {
    if($str) $str .= ", ";
    $ispercentage = isset($rate['ispercentage']) ? $rate['ispercentage'] : '0';
    $str .= "$serv,{$rate['value']},$ispercentage";
	}
	return "[$str]";
}
function getStandardRatesValues() {  
	$rates = getStandardRates(); //servicetypeid, label, descr, defaultrate, ispercentage, defaultcharge
  foreach($rates as $key => $rate) {
		$row = array('ispercentage'=>$rate['ispercentage']);
		$row['value'] = $rate['defaultrate'];
		$rates[$key] = $row;
	}
  return $rates;
}
function getStandardExtraPetRatesValues() {  
	$rates = getStandardRates(); //servicetypeid, label, descr, defaultrate, ispercentage, defaultcharge
  foreach($rates as $key => $rate) {
		$row = array('ispercentage'=>$rate['ispercentage']);
		$row['value'] = $rate['extrapetrate'] ? $rate['extrapetrate'] : '0';
		$rates[$key] = $row;
	}
  return $rates;
}
function getAllActiveProviderRateDollars() {
  if(!($result = doQuery("SELECT * FROM relproviderrate"))) return array(); //providerptr, servicetypeptr, rate, ispercentage, note
	$standardRates = getStandardRates(); 
  $rates = array();
  while($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
		$row['value'] = $row['rate'];
		$rates[$row['providerptr']][$row['servicetypeptr']] = $row;
	}
	return $rates;
}
function getStandardRates() {
	return fetchAssociationsKeyedBy("SELECT * FROM tblservicetype ORDER BY active desc, menuorder, label", 'servicetypeid');
}
function getPackage($packageid, $R_or_N_orNull=null) {
	if(!$R_or_N_orNull)
		$table = fetchRow0Col0("SELECT tablename FROM tblpackageid WHERE packageid = $packageid LIMIT 1");
	else $table = $R_or_N_orNull == 'R' ? 'tblrecurringpackage' : ($R_or_N_orNull == 'N' ? 'tblservicepackage' : '');
	$recurring = $table == 'tblrecurringpackage' ? 1 : '0';
	if($table) return fetchFirstAssoc("SELECT *, '$recurring' as recurring FROM $table WHERE packageid=$packageid LIMIT 1");
	// should not happen,,,
	$package = fetchFirstAssoc("SELECT * FROM tblrecurringpackage WHERE packageid=$packageid LIMIT 1");
	if($package) return $package;
	$package = fetchFirstAssoc("SELECT * FROM tblservicepackage WHERE packageid=$packageid LIMIT 1");
	if($package) return $package;
	
}
function getPackageServices($packageid) {
	return fetchAssociationsKeyedBy("SELECT * FROM tblservice WHERE packageptr=$packageid", 'serviceid');
}
function getRecurringPackage($packageid) {
	return fetchFirstAssoc("SELECT * FROM tblrecurringpackage WHERE packageid=$packageid LIMIT 1");
}
function getNonrecurringPackage($packageid) {
	return fetchFirstAssoc("SELECT * FROM tblservicepackage WHERE packageid=$packageid LIMIT 1");
}
function getCurrentClientPackages($clientptr, $table) {
	$orderBy = $table == 'tblrecurringpackage' ? 'packageid DESC LIMIT 1' : 'startdate';
	return fetchAssociations("SELECT * FROM $table WHERE current = 1 AND clientptr = $clientptr ORDER BY $orderBy");
}
function getStandardChargeDollarsJSArray() {
	//[serviceid,charge,serviceid,charge,serviceid,charge,serviceid,charge,]
	return getServicRateDollarsJSArray(getStandardChargeDollars());
}
function getStandardChargeDollars() {
	$charges = getStandardRates(); //servicetypeid, label, descr, defaultrate, ispercentage, defaultcharge
  foreach($charges as $key => $charge) 
		$charges[$key] = array('value'=>$charge['defaultcharge']);
  return $charges;
}
function getStandardExtraPetChargeDollars() {
	$charges = getStandardRates(); //servicetypeid, label, descr, defaultrate, ispercentage, defaultcharge
  foreach($charges as $key => $charge) 
		$charges[$key] = array('value'=>($charge['extrapetcharge'] ? $charge['extrapetcharge'] : '0'));
  return $charges;
}

//#########################################
function dumpServiceRateJSV2() {
	global $petpickerOptionPrefix, $allPetNames, $weeksNotice;
  $standardRates = getStandardRateDollarsJSArray();
  $providerRates = getAllActiveProviderRateDollarsJSArray();
  $standardCharges = getStandardChargeDollarsJSArray();
  $extraPetCharges = getServicRateDollarsJSArray(getStandardExtraPetChargeDollars());
  $extraPetRates = getServicRateDollarsJSArray(getStandardExtraPetRatesValues());
  $allPetNames = addslashes($allPetNames);
  $maxWeeks = multiWeeksMax();
  
//$debuGG = /*staffOnlyTEST() TRUE ||*/ $_SESSION['preferences']['useNewRateCalculations'] ? 1 : '0';  
	echo <<<FUNC
var newRateCalc = true; //$debuGG;	
var weeksNotice = "$weeksNotice";

function weeksChanged(sel) {
	if(sel == null) return; // support older version, which was not multiweek
	var weeks = sel.options[sel.selectedIndex].value;
	if(weeks > 1 && weeksNotice) {
		alert(weeksNotice);
		weeksNotice = "";
	}
	for(var w=0; w < $maxWeeks; w++) {
		if(w < weeks) {
			$(".week_"+w).show();
		}
		else {
			$(".week_"+w).hide();
			continue;
		}
		var services_visible = $("#"+w+"_services_visible").val();
		var atLeastOneRow = false;
		for(var i=1; document.getElementById(w+'_servicecode_'+i); i++) {
			atLeastOneRow = true;
			var serviceRowId = w+"_service_row_"+i;
			var addAnotherRowId = w+"_addAnother_"+i;
			if(w < weeks) {
				if(i <= services_visible) {
					$("#"+serviceRowId).show();
					if(i == services_visible)$("#"+addAnotherRowId).show();
					else $("#"+addAnotherRowId).hide();
				}
				else {
					$("#"+serviceRowId).hide();
					$("#"+addAnotherRowId).hide();
				}
			}
			else {
				$("#"+serviceRowId).hide();
				$("#"+addAnotherRowId).hide();
			}
		}
		if(services_visible > 0) {
			$("#"+w+"_addAnother_0").hide();
			$("#"+w+"_headers").show();
		}
		else if(w < weeks) {
			$("#"+w+"_addAnother_0").show();
			$("#"+w+"_headers").hide();
		}
	}
	if(weeks == 1) $("#weekOneLabelRow").hide();
	else $("#weekOneLabelRow").show();
	if(updateEffectiveDateWeek) updateEffectiveDateWeek();
	$("#weekchartlink").css('display', (weeks > 1 ? 'inline' : 'none'));
}

function getEffectiveDate(nullOnFailure) {
	var effectivedate = $('#effectivedate').val();
	var mdyarray = mdy(effectivedate);
	if(!mdyarray) {
		if(nullOnFailure == 1 || nullOnFailure == true) return;
		var currentdate = new Date(); 
		var effectivedate = currentdate.getDate() + "/" + (currentdate.getMonth()+1)  + "/" + currentdate.getFullYear();
	}
	return effectivedate;
}

function updateEffectiveDateWeek() {
//alert('boop!');	
	if(!$('#packageid').val()) return;
	var weeks = $('#weeks').val();
	if(weeks == 1) {
		$('#effectivedateweek').css('display', 'none');
		return;
	}
	$('#effectivedateweek').css('display', 'inline');
	var effectivedate = getEffectiveDate();
	var startdate = $('#startdate').val();
	var weeks = $('#weeks').val();
	var url = 'multi-week-chart.php?justweek=1&date='+effectivedate+'&startdate='+startdate+'&weeks='+weeks;
	//alert(url);
	//multi-week-chart.php justweek, date, startdate, weeks - print the weeknumber for package
	$.ajax({
			type: 'GET',
			url: url,
	    dataType: 'json', // what we expect in response. comment this out to see script errors in the console
	    //contentType: 'application/json', // what we are sending
			//data: JSON.stringify(obj),
	    processData: false,
			success: function(data) {
				// status: error
				if(data['status'] == 'error') {
					let errorSummary = 
						"ERRORS: "
						+data.join(", ");
					alert(errorSummary);
				}
				// status: success
				else { 
					//alert("SUCCESS: "+JSON.stringify(data));
					//alert("SUCCESS: "+JSON.stringify(data['destination']));
					let leftArrow = '&#9664;'; // BLACK LEFT POINTING TRIANGLE

					$('#effectivedateweek').html(leftArrow+' Week '+(data.weeknumber+1));
				}
			},
			failure: function(data) {
					alert(JSON.stringify(data))
			}
	});
}

function showWeekChart(numweeks) {
	//https://leashtime.com/multi-week-chart.php?weeks=2&startdate=9/5/2019&starton=9/24/2019&numweeks=4
	var weeks = $('#weeks').val();
	var startdate = $('#startdate').val();
	var displayedEffectiveDate = $('#effectivedate').val();
	var today = null;
	var starton = getEffectiveDate(nullOnFailure=true);
	if(!starton || starton == '') {
		today = new Date();
		var dd = String(today.getDate()).padStart(2, '0');
		var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
		var yyyy = today.getFullYear();
		var starton = mm + '/' + dd + '/' + yyyy;
	}
	var title = 
		encodeURI("Week Chart, starting with "
		+(today ? 'today' : 'the effective date ('+displayedEffectiveDate+')'));
	var url = "multi-week-chart.php?weeks="+weeks+"&startdate="+startdate+"&starton="+starton+"&numweeks="+numweeks
								+'&title='+title;
	$.fn.colorbox({href:url, width:"400", height:"480", scrolling: true, opacity: "0.3"});
}



function providerChanged(el, number, prefix) {
	var warningX = document.getElementById(prefix+'providerwarning_'+number);
	if(el.selectedIndex) warningX.style.display = 'none';
	else warningX.style.display = 'inline';
  updateServiceVals(this, number, prefix);
}	
	
function updateAllServiceVals() {
	var prefixes = [];
	if(document.getElementById('multiweek'))
		for(var w=0; w < $maxWeeks; w++) prefixes.push(w+'_');
	else prefixes = 
		document.getElementById('first_services_visible') 
			? ['first_','between_','last_'] 
			: [''];
	for(i=0;i<prefixes.length;i++) {
		//alert(prefixes[i]+'services_visible');
		if(!document.getElementById(prefixes[i]+'services_visible')) continue;
	  for(var number = 1; number <= document.getElementById(prefixes[i]+'services_visible').value; number++)
	    updateServiceVals(null, number, prefixes[i]);
	}
}

function copyServices(fromprefix, toprefix) {
	var fields = ['timeofday','providerptr','pets','servicecode','charge','adjustment','rate','bonus', 'surchargenote'];
	var visibleRows = 0;
	for(var i=1; document.getElementById(fromprefix+'servicecode_'+i) && document.getElementById(toprefix+'servicecode_'+i); i++) {
	  for(var f=0;f<fields.length;f++) {
			var field = fields[f];
			if(field == 'timeofday' || field == 'pets' || field == 'charge' || field == 'rate') {
//alert(toprefix+'div_'+field+"_"+i);
			  document.getElementById(toprefix+'div_'+field+"_"+i).innerHTML =
			    document.getElementById(fromprefix+'div_'+field+"_"+i).innerHTML;
			}
			else document.getElementById(toprefix+field+"_"+i).value =
					document.getElementById(fromprefix+field+"_"+i).value;
		}
		updateServiceVals(null, i, toprefix);
		var row = document.getElementById(fromprefix+"service_row_"+i);
		document.getElementById(toprefix+"service_row_"+i).style.display =
		  row.style.display;
		visibleRows += row.style.display == 'none' ? 0 : 1;
		if(document.getElementById(toprefix+"addAnother_"+i))
		document.getElementById(toprefix+"addAnother_"+i).style.display =
		  document.getElementById(fromprefix+"addAnother_"+i).style.display;
  }
	document.getElementById(toprefix+"services_visible").value = visibleRows;
	setButtonDivElements(toprefix);
	displayTotals();
}

function petsUpdated(petsDivId) {
	var parts = petsDivId.split('_');
	var prefix = '';
	if(parts.length == 4) prefix = parts[0];
	if(prefix) prefix += '_';
	var number = prefix ? parts[3] : parts[2];
	updateServiceVals(null, number, prefix);
}
	
function updateServiceVals(element, number, prefix) {
	var service = document.getElementById(prefix+'servicecode_'+number);
	if(!service) alert('Element: ['+prefix+'servicecode_'+number+'] not found.');
	service = service.value;
	var provider = document.getElementById(prefix+'providerptr_'+number).value;
	var client = document.getElementById('client').value;
	if(service == 0) {
		document.getElementById(prefix+'rate_'+number).value = '';
		document.getElementById(prefix+'div_rate_'+number).innerHTML = '';
		document.getElementById(prefix+'charge_'+number).value = '';
		document.getElementById(prefix+'div_charge_'+number).innerHTML = '';
		document.getElementById(prefix+'adjustment_'+number).value = '';
		document.getElementById(prefix+'bonus_'+number).value = '';
		document.getElementById(prefix+'surchargenote_'+number).value = '';
	}
	else {
		// look up rate and charge
		var charge = lookUpClientServiceCharge(service, client);
		var allPets = '$allPetNames'.split(',');
		var pets = document.getElementById(prefix+'div_pets_'+number).innerHTML;
if(newRateCalc) {
		var rate = NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets); // this function does its own multipet calc
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		if(numPets > 1) {
			var extrapetcharge = lookUpExtraPetCharge(service, client);
			charge += (numPets - 1) * extrapetcharge;
		}
}
else {
		var rate = lookUpProviderServiceRate(service, provider, charge);
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		if(numPets > 1) {
			var extrapetcharge = lookUpExtraPetCharge(service, client);
			charge += (numPets - 1) * extrapetcharge;
//if(newRateCalc) alert(service+','+provider+','+lookUpExtraPetProviderRate(service, provider, extrapetcharge));
			rate += (numPets - 1) * lookUpExtraPetProviderRate(service, provider, extrapetcharge);
		}
}		
		// set values at rate_number and charge_number
		document.getElementById(prefix+'rate_'+number).value = parseFloat(rate).toFixed(2);
		document.getElementById(prefix+'div_rate_'+number).innerHTML = parseFloat(rate).toFixed(2);
		document.getElementById(prefix+'charge_'+number).value = parseFloat(charge).toFixed(2);
		document.getElementById(prefix+'div_charge_'+number).innerHTML = parseFloat(charge).toFixed(2);
	}
	var adjEl = document.getElementById(prefix+'adjustment_'+number);
	var bonusEl = document.getElementById(prefix+'bonus_'+number);
	var newTitle = "Charge: $"+document.getElementById(prefix+'charge_'+number).value
									+(adjEl.value ? "+$"+adjEl.value : "")
									+ " ~ Rate: $"+document.getElementById(prefix+'rate_'+number).value
									+(bonusEl.value ? "+$"+bonusEl.value : "");
	document.getElementById(prefix+'cell_ChargeRate_'+number+'_button').title=newTitle;
	
	displayTotals();
}

function lookUpProviderServiceRate(service, provider, charge) {
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2)
		  if(providerRates[i] == provider) { 
				var rate = lookUpServiceRate(service, providerRates[i+1]);
		    rate =  rate != -1 ? rate : lookUpServiceRate(service, standardRates);
		  }
		if(!rate || rate == -1) rate = lookUpServiceRate(service, standardRates);
	}
	else rate = lookUpServiceRate(service, standardRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * charge;			/* percentage */
}

function NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets) {
	// charge is the raw service charge for this service type (and this client)
	var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
	var numExtraPets = numPets - 1;
	var standardRate = lookUpServiceRate(service, standardRates);
	var extrapetchargePerPet = lookUpExtraPetCharge(service);
	var extraPetCharge = numExtraPets * extrapetchargePerPet;
	var baseCharge = Number(charge);
	charge += extraPetCharge;
	var extraPetRate = lookUpServiceRate(service, extraPetRates);
	var extraPetRateDollars = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0] / 100 * extrapetchargePerPet
		: extraPetRate[0];
	var extraPetRatePercent = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0]
		: (extraPetCharge == 0 ? 0 : extraPetRateDollars / extrapetchargePerPet);
		
	var rate = -999;

	var customRate = -999;
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2) {
		  if(providerRates[i] == provider) { 
				var customRate = lookUpServiceRate(service, providerRates[i+1]);
		  }
		}
	// IF custom sitter rate:
		if(customRate[0] >= 0) {
			// IF rate is percentage:
			if(customRate[1] == 1) {
				// IF extra pet rate percentage > custom rate
				if(extraPetRatePercent > customRate[0]) rate = customRate[0] / 100 * baseCharge + extraPetRateDollars * numExtraPets;
				else rate = customRate[0] / 100 * (0+baseCharge + extraPetCharge);
			}
			// ELSE flat rate
			else rate = customRate[0] + extraPetRateDollars * numExtraPets;
		}
	}
	// ELSE IF no custom sitter rate:
	if(rate == -999) {
		// IF standard rate is a percentage
		if(standardRate[1]) 
			rate = baseCharge * standardRate[0] / 100 + extraPetCharge * extraPetRatePercent / 100;
		else rate =standardRate[0] + extraPetRateDollars * numExtraPets;
	}
	return rate;
}

function alertall() {
	var s = new Array();
	for (i=0; i<(alertall.arguments.length); i++) 
		s[s.length] = alertall.arguments[i];
	alert(s.join(', '));
}


function lookUpExtraPetProviderRate(service, provider, extrapetcharge) {
	//if(provider) {
	//}
	var rate = lookUpServiceRate(service, extraPetRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * extrapetcharge;			/* percentage */
}

function lookUpClientServiceCharge(service, client) {
	if(client) {
		var rate = lookUpServiceRate(service, clientCharges);
		if(rate != -1) return rate[0];
	}
	rate = lookUpServiceRate(service, standardCharges);
	return rate[0];
}

function lookUpExtraPetCharge(service, client) {
	//if(client) {
	//	var rate = lookUpServiceRate(service, clientCharges);
	//	if(rate != -1) return rate[0];
	//}
	rate = lookUpServiceRate(service, extraPetCharges);
	return rate[0];
}

function lookUpServiceRate(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}

function deleteLine(prefix, number) {
	number = parseInt(number);
	//if(!lineIsActive(prefix, number+1)) return;
	// Clear line values
	clearLine(prefix, number);
	// for number while servicecode_number(number + 1) copy line (number + 1) to number
	var lastvisibleline = number;
	for(var i=number; document.getElementById(prefix+"service_row_"+(i+1)); i++) {
		if(document.getElementById(prefix+"service_row_"+(i+1)).style.display != 'none')
		  lastvisibleline = i+1;
		copyNextLine(prefix, i);
	}
	// if number is < last line then clear last line 
	if(number < lastvisibleline) clearLine(prefix, lastvisibleline);
	// set last line invisible
	var numVisible = parseInt(document.getElementById(prefix+'services_visible').value);
	var minLinesVisible = document.getElementById('multiweek') ? 0 : 1;
	if(numVisible > minLinesVisible) {
	  document.getElementById(prefix+'services_visible').value = numVisible - 1;
		document.getElementById(prefix+"service_row_"+lastvisibleline).style.display='none';
		document.getElementById(prefix+'addAnother_'+(lastvisibleline)).style.display='none';
		lastvisibleline = lastvisibleline - 1;
		document.getElementById(prefix+'addAnother_'+(lastvisibleline)).style.display='{$_SESSION['tableRowDisplayMode']}';
	}
	displayTotals();
	weeksChanged(document.getElementById('weeks'));

}

function lineIsActive(prefix, number) {
	return document.getElementById(prefix+"service_row_"+number) &&
	       document.getElementById(prefix+"service_row_"+number).style.display != 'none';
}

function copyNextLine(prefix, number) {  // copy line number+1 to line number
  copyNextButtonDiv(prefix,'daysofweek_',number);
  copyNextButtonDiv(prefix,'timeofday_',number);
	copyNextVal(prefix,'providerptr_',number);
  copyNextButtonDiv(prefix,'pets_',number);
	copyNextVal(prefix,'servicecode_',number);
	copyNextVal(prefix,'adjustment_',number);
	copyNextVal(prefix,'bonus_',number);
	copyNextDiv(prefix,'charge_',number);
	copyNextDiv(prefix,'rate_',number);
}

function clearLine(prefix, number) {
	if(document.getElementById(prefix+"service_row_"+number)) {
		clearButtonDiv(prefix, 'daysofweek_', number);
		clearButtonDiv(prefix, 'timeofday_', number);
		document.getElementById(prefix+'providerptr_'+number).value = '';
		clearButtonDiv(prefix, 'pets_', number);
		document.getElementById(prefix+'servicecode_'+number).value = 0;
		clearButtonDiv(prefix, 'charge_', number);
		document.getElementById(prefix+'adjustment_'+number).value = '';
		clearButtonDiv(prefix, 'rate_', number);
		document.getElementById(prefix+'bonus_'+number).value = '';
  }
}

function clearButtonDiv(prefix, nm, number) {
	if(document.getElementById(prefix+nm+number)) {
		document.getElementById(prefix+nm+number).value = '';
		document.getElementById(prefix+"div_"+nm+number).innerHTML = '';
	}
}

function copyNextDiv(prefix, nm, number) {
	var next = number+1;
	if(document.getElementById(prefix+nm+number)) {
		document.getElementById(prefix+nm+number).value = document.getElementById(prefix+nm+next).value;
		document.getElementById(prefix+"div_"+nm+number).innerHTML = document.getElementById(prefix+"div_"+nm+next).innerHTML;
	}
}

function copyNextButtonDiv(prefix, nm, number) {
	if(document.getElementById(prefix+nm+number)) {
		copyNextVal(prefix, nm, number);
		document.getElementById(prefix+"div_"+nm+number).innerHTML = document.getElementById(prefix+"div_"+nm+(number+1)).innerHTML;
	}
}

function copyNextVal(prefix, nm, number) {
	document.getElementById(prefix+nm+number).value = document.getElementById(prefix+nm+(number+1)).value;
}


function setButtonDivElements(prefix) {
	var names = ['daysofweek','timeofday','pets'];
	var firstNameIndex = (prefix == 'first_' || prefix == 'last_') ? 1 : 0;  // ignore daysofweek when prefix (representing a day tab) is specified
	for(var i=1; i <= document.getElementById(prefix+'services_visible').value; i++)
		for(var n=firstNameIndex; n < names.length; n++) {
//alert("n: "+n+" name: "+names[n]);
//alert('i: '+i+' n: '+n+' - '+prefix+'div_'+names[n]+'_'+i+': ['+document.getElementById(prefix+'div_'+names[n]+'_'+i)+']');
		  document.getElementById(prefix+names[n]+'_'+i).value = 
		    document.getElementById(prefix+'div_'+names[n]+'_'+i).innerHTML;
//alert('hidden id ['+prefix+names[n]+'_'+i+'] ['+document.getElementById(prefix+names[n]+'_'+i).value+']');
		}
}
		
function totalLineRatesAndCharges(numWeekDays, prefix) {
	var totals = new Array();
	totals[0] = 0;
	totals[1] = 0;
	
	//document.getElementById(prefix+"service_row_"+lastvisibleline).style.display='none'	
	var row;
	for(var i=1;row = document.getElementById(prefix+"service_row_"+i); i++) {
		if(row.style.display =='none') continue; 
	//for(var i=1;i <= document.getElementById(prefix+"services_visible").value; i++) 
		var linetotal = lineRateAndCharge(i, numWeekDays, prefix);
		totals[0] += linetotal[0];
		totals[1] += linetotal[1];
	}
	return totals;
}
	
function monthlyRatesAndCharges(weekspermonth) {
	var totals = new Array();
	totals[0] = 0;
	totals[1] = 0;
	if(document.getElementById('multiweek')) {
		for(var w=0; w < $maxWeeks; w++) {
			var sv = document.getElementById(w+"_services_visible");
			if(sv) {
				for(var i=1;i <= sv.value; i++) {
					var linetotal = lineRateAndCharge(i, '', w+'_');
					totals[0] += linetotal[0] * weekspermonth;
					totals[1] += linetotal[1] * weekspermonth;
				}
			}
		}
	}
	else {
		for(var i=1;i <= document.getElementById("services_visible").value; i++) {
			var linetotal = lineRateAndCharge(i, '', '');
			totals[0] += linetotal[0] * weekspermonth;
			totals[1] += linetotal[1] * weekspermonth;
		}
	}
	return totals;
}
	
	
function lineRateAndCharge(i, numWeekDays, prefix) {
	var weekdays = numWeekDays ? numWeekDays : countWeekDaysSelected(document.getElementById(prefix+'div_daysofweek_'+i));
	if(weekdays &&
		 document.getElementById(prefix+'servicecode_'+i).options[document.getElementById(prefix+'servicecode_'+i).selectedIndex].value &&
		 document.getElementById(prefix+'charge_'+i).value &&
		 document.getElementById(prefix+'rate_'+i).value) {			 
			 var bonus = document.getElementById(prefix+'bonus_'+i).value;
			 //isNaN(document.getElementById('bonus_'+i).value) ? 1 : document.getElementById('bonus_'+i).value;
			 bonus = parseFloat(bonus)!= bonus-0 ? 0 : parseFloat(bonus);

			 var adjust = document.getElementById(prefix+'adjustment_'+i).value;
			 //isNaN(document.getElementById('adjust_'+i).value) ? 1 : document.getElementById('adjust_'+i).value;
			 adjust = parseFloat(adjust)!= adjust-0 ? 0 : parseFloat(adjust);

			 return [weekdays * (parseFloat(document.getElementById(prefix+'rate_'+i).value)+parseFloat(bonus)),
			         weekdays * (parseFloat(document.getElementById(prefix+'charge_'+i).value)+parseFloat(adjust))];
	}
  return [0,0];
}

function addAnotherButtonAction(number, prefix) {
	var isIE6 = navigator.userAgent.toLowerCase().indexOf("msie") != -1;
	var displayStyle = isIE6 ? 'block' : 'table-row';
	var next = number+1;
	document.getElementById(prefix+"services_visible").value = 1+parseInt(document.getElementById(prefix+"services_visible").value);
	document.getElementById(prefix+"service_row_"+next).style.display=displayStyle;
	if(document.getElementById(prefix+"addAnother_"+next)) document.getElementById(prefix+"addAnother_"+next).style.display=displayStyle;
	document.getElementById(prefix+"addAnother_"+number).style.display="none";
}

function updatePets() {
	ajaxGet("petpick-update-ajax.php?client="+document.getElementById('client').value+"&petpickerOptionPrefix=$petpickerOptionPrefix", 
	         'petpickerbox');
	var allDIVs = document.getElementsByTagName('div');
	for(var i=0;i<allDIVs.length;i++)
	  if(allDIVs[i].id.indexOf('div_pets_') == 0)
	    allDIVs[i].innerHTML = 'All Pets';
	         
}

function setClientChargesAndDisplayTotals(charges) {
	clientCharges = eval(charges);
	updateAllServiceVals();
	displayTotals();
}
	
function setPrimaryProvider(primarysel, notabs) {
	var provider = primarysel.options[primarysel.selectedIndex].value;
	var rows = document.getElementsByTagName('tr');
	for(var i=0; i < rows.length; i++) {
		var rowid = rows[i].id;
		if(rowid && rowid.indexOf(notabs ? 'service_row_' : '_service_row_') > -1) {
			var tab = notabs ? '' : rowid.substring(0, rowid.indexOf('_')+1);  // e.g., first
			// kludge override for multiweek
			if(notabs && rowid.indexOf('service_row_') > 0) tab = rowid.substring(0, rowid.indexOf('_')+1);
			var rownum = rowid.substring(rowid.lastIndexOf('_')); // e.g., _2
			if(true || !document.getElementById(tab+'servicecode'+rownum).selectedIndex) {
				var pselect = document.getElementById(tab+'providerptr'+rownum);
				if(!pselect) alert("setPrimaryProvider: "+tab+'providerptr'+rownum+" not found.");
				for(var o=0; o < pselect.options.length; o++)
					pselect.options[o].selected = pselect.options[o].value == provider;
			}
		}
	}
	updateAllServiceVals();
}	

function uncancelPackage() {
	document.getElementById('uncanceldiv').style.display='none';
	if(document.getElementById('resumedate')) {
		document.getElementById('suspenddate').value='';
		document.getElementById('resumedate').value='';
	}
	document.getElementById('cancellationreason').value='';
	document.getElementById('cancellationdate').value='';
	var tags = document.getElementsByName('CancellationDetails');
	for(var i=0;i<tags.length;i++) tags[i].style.display = '{$_SESSION['tableRowDisplayMode']}';
	if(confirm("This package will remain canceled until or unless you save your changes.\\nWant to save changes now?"))
		checkAndSubmit(0);
}

function hidePackage() {
	if(confirm('You are about to delete this package entirely.  Proceed?')) {
		document.getElementById('hidepackage').value='1';
		checkAndSubmit(0);
	}
}

	
var standardRates = $standardRates;
var providerRates = $providerRates;
var standardCharges = $standardCharges;
var extraPetCharges = $extraPetCharges;
var extraPetRates = $extraPetRates;
FUNC;
}

//#########################################
function dumpServiceRateJS() {
	global $petpickerOptionPrefix, $allPetNames;
  $standardRates = getStandardRateDollarsJSArray();
  $providerRates = getAllActiveProviderRateDollarsJSArray();
  $standardCharges = getStandardChargeDollarsJSArray();
  $extraPetCharges = getServicRateDollarsJSArray(getStandardExtraPetChargeDollars());
  $extraPetRates = getServicRateDollarsJSArray(getStandardExtraPetRatesValues());
  $allPetNames = addslashes($allPetNames);
//$debuGG = /*staffOnlyTEST() TRUE ||*/ $_SESSION['preferences']['useNewRateCalculations'] ? 1 : '0';  
	echo <<<FUNC
var newRateCalc = true; //$debuGG;	
function providerChanged(el, number, prefix) {
	var warningX = document.getElementById(prefix+'providerwarning_'+number);
	if(el.selectedIndex) warningX.style.display = 'none';
	else warningX.style.display = 'inline';
  updateServiceVals(this, number, prefix);
}	
	
function updateAllServiceVals() {
	var prefixes = document.getElementById('first_services_visible') ? ['first_','between_','last_'] : [''];
	for(i=0;i<prefixes.length;i++) {
		//alert(prefixes[i]+'services_visible');
		if(!document.getElementById(prefixes[i]+'services_visible')) continue;
	  for(var number = 1; number <= document.getElementById(prefixes[i]+'services_visible').value; number++)
	    updateServiceVals(null, number, prefixes[i]);
	}
}

function copyServices(fromprefix, toprefix) {
	var fields = ['timeofday','providerptr','pets','servicecode','charge','adjustment','rate','bonus', 'surchargenote'];
	var visibleRows = 0;
	for(var i=1; document.getElementById(fromprefix+'servicecode_'+i) && document.getElementById(toprefix+'servicecode_'+i); i++) {
	  for(var f=0;f<fields.length;f++) {
			var field = fields[f];
			if(field == 'timeofday' || field == 'pets' || field == 'charge' || field == 'rate') {
//alert(toprefix+'div_'+field+"_"+i);
			  document.getElementById(toprefix+'div_'+field+"_"+i).innerHTML =
			    document.getElementById(fromprefix+'div_'+field+"_"+i).innerHTML;
			}
			else document.getElementById(toprefix+field+"_"+i).value =
					document.getElementById(fromprefix+field+"_"+i).value;
		}
		updateServiceVals(null, i, toprefix);
		var row = document.getElementById(fromprefix+"service_row_"+i);
		document.getElementById(toprefix+"service_row_"+i).style.display =
		  row.style.display;
		visibleRows += row.style.display == 'none' ? 0 : 1;
		if(document.getElementById(toprefix+"addAnother_"+i))
		document.getElementById(toprefix+"addAnother_"+i).style.display =
		  document.getElementById(fromprefix+"addAnother_"+i).style.display;
  }
	document.getElementById(toprefix+"services_visible").value = visibleRows;
	setButtonDivElements(toprefix);
	displayTotals();
}

function petsUpdated(petsDivId) {
	var parts = petsDivId.split('_');
	var prefix = '';
	if(parts.length == 4) prefix = parts[0];
	if(prefix) prefix += '_';
	var number = prefix ? parts[3] : parts[2];
	updateServiceVals(null, number, prefix);
}
	
function updateServiceVals(element, number, prefix) {
	var service = document.getElementById(prefix+'servicecode_'+number);
	if(!service) alert('Element: ['+prefix+'servicecode_'+number+'] not found.');
	service = service.value;
	var provider = document.getElementById(prefix+'providerptr_'+number).value;
	var client = document.getElementById('client').value;
	if(service == 0) {
		document.getElementById(prefix+'rate_'+number).value = '';
		document.getElementById(prefix+'div_rate_'+number).innerHTML = '';
		document.getElementById(prefix+'charge_'+number).value = '';
		document.getElementById(prefix+'div_charge_'+number).innerHTML = '';
		document.getElementById(prefix+'adjustment_'+number).value = '';
		document.getElementById(prefix+'bonus_'+number).value = '';
		document.getElementById(prefix+'surchargenote_'+number).value = '';
	}
	else {
		// look up rate and charge
		var charge = lookUpClientServiceCharge(service, client);
		var allPets = '$allPetNames'.split(',');
		var pets = document.getElementById(prefix+'div_pets_'+number).innerHTML;
if(newRateCalc) {
		var rate = NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets); // this function does its own multipet calc
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		if(numPets > 1) {
			var extrapetcharge = lookUpExtraPetCharge(service, client);
			charge += (numPets - 1) * extrapetcharge;
		}
}
else {
		var rate = lookUpProviderServiceRate(service, provider, charge);
		var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
		if(numPets > 1) {
			var extrapetcharge = lookUpExtraPetCharge(service, client);
			charge += (numPets - 1) * extrapetcharge;
//if(newRateCalc) alert(service+','+provider+','+lookUpExtraPetProviderRate(service, provider, extrapetcharge));
			rate += (numPets - 1) * lookUpExtraPetProviderRate(service, provider, extrapetcharge);
		}
}		
		// set values at rate_number and charge_number
		document.getElementById(prefix+'rate_'+number).value = parseFloat(rate).toFixed(2);
		document.getElementById(prefix+'div_rate_'+number).innerHTML = parseFloat(rate).toFixed(2);
		document.getElementById(prefix+'charge_'+number).value = parseFloat(charge).toFixed(2);
		document.getElementById(prefix+'div_charge_'+number).innerHTML = parseFloat(charge).toFixed(2);
	}
	var adjEl = document.getElementById(prefix+'adjustment_'+number);
	var bonusEl = document.getElementById(prefix+'bonus_'+number);
	var newTitle = "Charge: $"+document.getElementById(prefix+'charge_'+number).value
									+(adjEl.value ? "+$"+adjEl.value : "")
									+ " ~ Rate: $"+document.getElementById(prefix+'rate_'+number).value
									+(bonusEl.value ? "+$"+bonusEl.value : "");
	document.getElementById(prefix+'cell_ChargeRate_'+number+'_button').title=newTitle;
	
	displayTotals();
}

function lookUpProviderServiceRate(service, provider, charge) {
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2)
		  if(providerRates[i] == provider) { 
				var rate = lookUpServiceRate(service, providerRates[i+1]);
		    rate =  rate != -1 ? rate : lookUpServiceRate(service, standardRates);
		  }
		if(!rate || rate == -1) rate = lookUpServiceRate(service, standardRates);
	}
	else rate = lookUpServiceRate(service, standardRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * charge;			/* percentage */
}

function NEWlookUpProviderServiceRate(service, provider, charge, pets, allPets) {
	// charge is the raw service charge for this service type (and this client)
	var numPets = pets == 'All Pets' ? Math.max(1, allPets.length) : pets.split(',').length;
	var numExtraPets = numPets - 1;
	var standardRate = lookUpServiceRate(service, standardRates);
	var extrapetchargePerPet = lookUpExtraPetCharge(service);
	var extraPetCharge = numExtraPets * extrapetchargePerPet;
	var baseCharge = Number(charge);
	charge += extraPetCharge;
	var extraPetRate = lookUpServiceRate(service, extraPetRates);
	var extraPetRateDollars = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0] / 100 * extrapetchargePerPet
		: extraPetRate[0];
	var extraPetRatePercent = standardRate[1] // if standard rate is a percentage
		? extraPetRate[0]
		: (extraPetCharge == 0 ? 0 : extraPetRateDollars / extrapetchargePerPet);
		
	var rate = -999;

	var customRate = -999;
	if(provider) {
		for(var i=0;i<providerRates.length;i+=2) {
		  if(providerRates[i] == provider) { 
				var customRate = lookUpServiceRate(service, providerRates[i+1]);
		  }
		}
	// IF custom sitter rate:
		if(customRate[0] >= 0) {
			// IF rate is percentage:
			if(customRate[1] == 1) {
				// IF extra pet rate percentage > custom rate
				if(extraPetRatePercent > customRate[0]) rate = customRate[0] / 100 * baseCharge + extraPetRateDollars * numExtraPets;
				else rate = customRate[0] / 100 * (0+baseCharge + extraPetCharge);
			}
			// ELSE flat rate
			else rate = customRate[0] + extraPetRateDollars * numExtraPets;
		}
	}
	// ELSE IF no custom sitter rate:
	if(rate == -999) {
		// IF standard rate is a percentage
		if(standardRate[1]) 
			rate = baseCharge * standardRate[0] / 100 + extraPetCharge * extraPetRatePercent / 100;
		else rate =standardRate[0] + extraPetRateDollars * numExtraPets;
	}
	return rate;
}

function alertall() {
	var s = new Array();
	for (i=0; i<(alertall.arguments.length); i++) 
		s[s.length] = alertall.arguments[i];
	alert(s.join(', '));
}


function lookUpExtraPetProviderRate(service, provider, extrapetcharge) {
	//if(provider) {
	//}
	var rate = lookUpServiceRate(service, extraPetRates);
	return rate[1] == 0 /* flat rate */
					? rate[0]
					: rate[0] / 100 * extrapetcharge;			/* percentage */
}

function lookUpClientServiceCharge(service, client) {
	if(client) {
		var rate = lookUpServiceRate(service, clientCharges);
		if(rate != -1) return rate[0];
	}
	rate = lookUpServiceRate(service, standardCharges);
	return rate[0];
}

function lookUpExtraPetCharge(service, client) {
	//if(client) {
	//	var rate = lookUpServiceRate(service, clientCharges);
	//	if(rate != -1) return rate[0];
	//}
	rate = lookUpServiceRate(service, extraPetCharges);
	return rate[0];
}

function lookUpServiceRate(service, rates) {  // return [value, ispercentage]
	for(var i=0;i<rates.length;i+=3)  // servicetype,value,ispercentage
	  if(rates[i] == service)
	    return [rates[i+1],rates[i+2]];
	return -1;
}

function deleteLine(prefix, number) {
	number = parseInt(number);
	//if(!lineIsActive(prefix, number+1)) return;
	// Clear line values
	clearLine(prefix, number);
	// for number while servicecode_number(number + 1) copy line (number + 1) to number
	var lastvisibleline = number;
	for(var i=number; document.getElementById(prefix+"service_row_"+(i+1)); i++) {
		if(document.getElementById(prefix+"service_row_"+(i+1)).style.display != 'none')
		  lastvisibleline = i+1;
		copyNextLine(prefix, i);
	}
	// if number is < last line then clear last line 
	if(number < lastvisibleline) clearLine(prefix, lastvisibleline);
	// set last line invisible
	var numVisible = parseInt(document.getElementById(prefix+'services_visible').value);
	if(numVisible > 1) {
	  document.getElementById(prefix+'services_visible').value = numVisible - 1;
		document.getElementById(prefix+"service_row_"+lastvisibleline).style.display='none';
		document.getElementById(prefix+'addAnother_'+(lastvisibleline)).style.display='none';
		lastvisibleline = lastvisibleline - 1;
		document.getElementById(prefix+'addAnother_'+(lastvisibleline)).style.display='{$_SESSION['tableRowDisplayMode']}';
	}
	displayTotals();
}

function lineIsActive(prefix, number) {
	return document.getElementById(prefix+"service_row_"+number) &&
	       document.getElementById(prefix+"service_row_"+number).style.display != 'none';
}

function copyNextLine(prefix, number) {  // copy line number+1 to line number
  copyNextButtonDiv(prefix,'daysofweek_',number);
  copyNextButtonDiv(prefix,'timeofday_',number);
	copyNextVal(prefix,'providerptr_',number);
  copyNextButtonDiv(prefix,'pets_',number);
	copyNextVal(prefix,'servicecode_',number);
	copyNextVal(prefix,'adjustment_',number);
	copyNextVal(prefix,'bonus_',number);
	copyNextDiv(prefix,'charge_',number);
	copyNextDiv(prefix,'rate_',number);
}

function clearLine(prefix, number) {
	if(document.getElementById(prefix+"service_row_"+number)) {
		clearButtonDiv(prefix, 'daysofweek_', number);
		clearButtonDiv(prefix, 'timeofday_', number);
		document.getElementById(prefix+'providerptr_'+number).value = '';
		clearButtonDiv(prefix, 'pets_', number);
		document.getElementById(prefix+'servicecode_'+number).value = 0;
		clearButtonDiv(prefix, 'charge_', number);
		document.getElementById(prefix+'adjustment_'+number).value = '';
		clearButtonDiv(prefix, 'rate_', number);
		document.getElementById(prefix+'bonus_'+number).value = '';
  }
}

function clearButtonDiv(prefix, nm, number) {
	if(document.getElementById(prefix+nm+number)) {
		document.getElementById(prefix+nm+number).value = '';
		document.getElementById(prefix+"div_"+nm+number).innerHTML = '';
	}
}

function copyNextDiv(prefix, nm, number) {
	var next = number+1;
	if(document.getElementById(prefix+nm+number)) {
		document.getElementById(prefix+nm+number).value = document.getElementById(prefix+nm+next).value;
		document.getElementById(prefix+"div_"+nm+number).innerHTML = document.getElementById(prefix+"div_"+nm+next).innerHTML;
	}
}

function copyNextButtonDiv(prefix, nm, number) {
	if(document.getElementById(prefix+nm+number)) {
		copyNextVal(prefix, nm, number);
		document.getElementById(prefix+"div_"+nm+number).innerHTML = document.getElementById(prefix+"div_"+nm+(number+1)).innerHTML;
	}
}

function copyNextVal(prefix, nm, number) {
	document.getElementById(prefix+nm+number).value = document.getElementById(prefix+nm+(number+1)).value;
}


function setButtonDivElements(prefix) {
	var names = ['daysofweek','timeofday','pets'];
	var firstNameIndex = (prefix == '' || prefix == 'between_') ? 0 : 1;  // ignore daysofweek when prefix (representing a day tab) is specified
	for(var i=1; i <= document.getElementById(prefix+'services_visible').value; i++)
		for(var n=firstNameIndex; n < names.length; n++) {
//alert('i: '+i+' n: '+n+' - '+prefix+'div_'+names[n]+'_'+i+': ['+document.getElementById(prefix+'div_'+names[n]+'_'+i)+']');
		  document.getElementById(prefix+names[n]+'_'+i).value = 
		    document.getElementById(prefix+'div_'+names[n]+'_'+i).innerHTML;
		}
}
		
function totalLineRatesAndCharges(numWeekDays, prefix) {
	var totals = new Array();
	totals[0] = 0;
	totals[1] = 0;
	
	//document.getElementById(prefix+"service_row_"+lastvisibleline).style.display='none'	
	var row;
	for(var i=1;row = document.getElementById(prefix+"service_row_"+i); i++) {
		if(row.style.display =='none') continue; 
	//for(var i=1;i <= document.getElementById(prefix+"services_visible").value; i++)
		var linetotal = lineRateAndCharge(i, numWeekDays, prefix);
		totals[0] += linetotal[0];
		totals[1] += linetotal[1];
	}
	return totals;
}
	
function monthlyRatesAndCharges(weekspermonth) {
	var totals = new Array();
	totals[0] = 0;
	totals[1] = 0;
	for(var i=1;i <= document.getElementById("services_visible").value; i++) {
		var linetotal = lineRateAndCharge(i, '', '');
		totals[0] += linetotal[0] * weekspermonth;
		totals[1] += linetotal[1] * weekspermonth;
	}
	return totals;
}
	
	
function lineRateAndCharge(i, numWeekDays, prefix) {
	var weekdays = numWeekDays ? numWeekDays : countWeekDaysSelected(document.getElementById('div_daysofweek_'+i));
	if(weekdays &&
		 document.getElementById(prefix+'servicecode_'+i).options[document.getElementById(prefix+'servicecode_'+i).selectedIndex].value &&
		 document.getElementById(prefix+'charge_'+i).value &&
		 document.getElementById(prefix+'rate_'+i).value) {
			 var bonus = document.getElementById(prefix+'bonus_'+i).value;
			 //isNaN(document.getElementById('bonus_'+i).value) ? 1 : document.getElementById('bonus_'+i).value;
			 bonus = parseFloat(bonus)!= bonus-0 ? 0 : parseFloat(bonus);

			 var adjust = document.getElementById(prefix+'adjustment_'+i).value;
			 //isNaN(document.getElementById('adjust_'+i).value) ? 1 : document.getElementById('adjust_'+i).value;
			 adjust = parseFloat(adjust)!= adjust-0 ? 0 : parseFloat(adjust);

			 return [weekdays * (parseFloat(document.getElementById(prefix+'rate_'+i).value)+parseFloat(bonus)),
			         weekdays * (parseFloat(document.getElementById(prefix+'charge_'+i).value)+parseFloat(adjust))];
	}
  return [0,0];
}

function addAnotherButtonAction(number, prefix) {
	var isIE6 = navigator.userAgent.toLowerCase().indexOf("msie") != -1;
	var displayStyle = isIE6 ? 'block' : 'table-row';
	var next = number+1;
	document.getElementById(prefix+"services_visible").value = 1+parseInt(document.getElementById(prefix+"services_visible").value);
	document.getElementById(prefix+"service_row_"+next).style.display=displayStyle;
	if(document.getElementById(prefix+"addAnother_"+next)) document.getElementById(prefix+"addAnother_"+next).style.display=displayStyle;
	document.getElementById(prefix+"addAnother_"+number).style.display="none";
}

function updatePets() {
	ajaxGet("petpick-update-ajax.php?client="+document.getElementById('client').value+"&petpickerOptionPrefix=$petpickerOptionPrefix", 
	         'petpickerbox');
	var allDIVs = document.getElementsByTagName('div');
	for(var i=0;i<allDIVs.length;i++)
	  if(allDIVs[i].id.indexOf('div_pets_') == 0)
	    allDIVs[i].innerHTML = 'All Pets';
	         
}

function setClientChargesAndDisplayTotals(charges) {
	clientCharges = eval(charges);
	updateAllServiceVals();
	displayTotals();
}
	
function setPrimaryProvider(primarysel, notabs) {
	var provider = primarysel.options[primarysel.selectedIndex].value;
	var rows = document.getElementsByTagName('tr');
	for(var i=0; i < rows.length; i++) {
		var rowid = rows[i].id;
		if(rowid && rowid.indexOf(notabs ? 'service_row_' : '_service_row_') > -1) {
			var tab = notabs ? '' : rowid.substring(0, rowid.indexOf('_')+1);  // e.g., first
			var rownum = rowid.substring(rowid.lastIndexOf('_')); // e.g., _2
			if(true || !document.getElementById(tab+'servicecode'+rownum).selectedIndex) {
				var pselect = document.getElementById(tab+'providerptr'+rownum);
				if(!pselect) alert("setPrimaryProvider: "+tab+'providerptr'+rownum+" not found.");
				for(var o=0; o < pselect.options.length; o++)
					pselect.options[o].selected = pselect.options[o].value == provider;
			}
		}
	}
	updateAllServiceVals();
}	

function uncancelPackage() {
	document.getElementById('uncanceldiv').style.display='none';
	if(document.getElementById('resumedate')) {
		document.getElementById('suspenddate').value='';
		document.getElementById('resumedate').value='';
	}
	document.getElementById('cancellationreason').value='';
	document.getElementById('cancellationdate').value='';
	var tags = document.getElementsByName('CancellationDetails');
	for(var i=0;i<tags.length;i++) tags[i].style.display = '{$_SESSION['tableRowDisplayMode']}';
	if(confirm("This package will remain canceled until or unless you save your changes.\\nWant to save changes now?"))
		checkAndSubmit(0);
}

function hidePackage() {
	if(confirm('You are about to delete this package entirely.  Proceed?')) {
		document.getElementById('hidepackage').value='1';
		checkAndSubmit(0);
	}
}

	
var standardRates = $standardRates;
var providerRates = $providerRates;
var standardCharges = $standardCharges;
var extraPetCharges = $extraPetCharges;
var extraPetRates = $extraPetRates;
FUNC;
}

function newPackage($table, &$outData, $showerrors) {
  $packageid = insertTable('tblpackageid', array('tablename'=>$table), $showerrors);
  $outData['packageid'] = $packageid;
  addCreationFields($outData);
  insertTable($table, $outData, $showerrors);
  $description = $outData['previousversionptr'] ? "Changed ({$outData['previousversionptr']}) " : "New ";
  $description .= 
  	($table == 'tblrecurringpackage') ?
  		($outData['monthly'] ? 'Monthly' : 'Weekly')
  		: ($outData['onedaypackage'] ? 'One-Day' : 'Nonrecurring');
	$description .= " for client: [{$outData['clientptr']}]";
 
  logChange($packageid, $table, 'c', $description);
  return $packageid;
}	

function saveNewRepeatingPackage() {
	//print_r($_POST);
	if($recurring && (requestIsJSON() || array_key_exists("0_services_visible", $_REQUEST))) // allow for POST[''] or JSON
		return saveServicesJSON($packageid, $recurring, $simulation=null);
  $outData = requestIsJSON() ? getJSONRequestInput() : array_merge($_POST);
  preprocessRepeatingPackage($outData);
  $packageid = newPackage('tblrecurringpackage', $outData, 1);
  $services = saveServices($packageid, true);
//function createScheduleAppointments($package, $services, $recurring, $intervalToIgnore=null, $simulation=null, $allowRetroactiveAppointments=false, $preexistingAppointments=null, $preexistingAppointmentMatch='appointmentSignaturesEqual') {
  $allowRetroactive = $_SESSION['staffuser'];
  createScheduleAppointments($outData, $services, true, null, null, $allowRetroactive);
  notifyStaffOfScheduleChange($packageid, true);
  return $packageid;
}

function saveNewMonthlyPackage() {
	//print_r($_POST);
  $outData = array_merge($_POST);
  $outData['monthly'] = 1;
  preprocessMonthlyPackage($outData);
  $packageid = newPackage('tblrecurringpackage', $outData, 1);
  $services = saveServices($packageid, true);
  $allowRetroactive = $_SESSION['staffuser'];
  createScheduleAppointments($outData, $services, true, null, null, $allowRetroactive);
  notifyStaffOfScheduleChange($packageid, true);
  return $packageid;
}

function saveNewNonrepeatingPackage() {
	//print_r($_POST);
  $outData = array_merge($_POST);
  $outData['preemptrecurringappts'] = isset($outData['preemptrecurringappts']) ? $outData['preemptrecurringappts'] : 0;
  preprocessNonrepeatingPackage($outData);
  $packageid = newPackage('tblservicepackage', $outData, 1);
  $services = saveServices($packageid, false);
  $allowRetroactiveAppointments = $outData['onedaypackage'];
  createScheduleAppointments($outData, $services, false, null, null, $allowRetroactiveAppointments);
  applyPreemptionPreference(null, $outData);  // affects ONLY future appointments
	if(!$outData['onedaypackage']) labelFirstAndLastVisitsForNRPackage($packageid, $outData['clientptr'], $justClear=!$_POST['markStartFinish']);  
  notifyStaffOfScheduleChange($packageid, true);
  return $packageid;
}

function saveNewIrregularPackage($outData=null) {
  $outData = $outData ? $outData : array_merge($_POST);
  $outData['irregular'] = $outData['irregular'] ? $outData['irregular'] : 1;
  $outData['preemptrecurringappts'] = isset($outData['preemptrecurringappts']) ? $outData['preemptrecurringappts'] : 0;
  preprocessNonrepeatingPackage($outData);
  
  $packageid = newPackage('tblservicepackage', $outData, 1);
  applyPreemptionPreference(null, $outData);  // affects ONLY future appointments
  return $packageid;
}



function preprocessPackage(&$package, &$packageFields) {
	$clientptr = $package['clientptr'] ? $package['clientptr'] : $package['client'];
  foreach($package as $field => $value) {
    if(!in_array($field, $packageFields)) 
      unset($package[$field]);
    else if(strpos($field, 'date'))
      $package[$field] = $package[$field] ? date("Y-m-d", strtotime($package[$field])) : '';
    else if($field == 'current') $package[$field] = $value ? 1 : 0;
	}
  $package['clientptr'] = $clientptr;
}

function preprocessRepeatingPackage(&$package) {
	global $repeatingPackageFields;
	$package['prepaid'] = $package['prepaid'] ? 1 : 0;
	if($package['monthly']) preprocessMonthlyPackage($package);
	else preprocessPackage($package, $repeatingPackageFields);
}

function preprocessMonthlyPackage(&$package) {
	global $monthlyPackageFields;
	$package['prepaid'] = $package['prepaid'] ? 1 : 0;
	preprocessPackage($package, array_merge($monthlyPackageFields, array('monthly')));
}

function preprocessNonrepeatingPackage(&$package) {
	global $nonrepeatingPackageFields;
	
	preprocessPackage($package, $nonrepeatingPackageFields);
	
	// 'irregular' is no longer strictly boolean. 1 == EZ Schedule.  2 == Meeting
	$package['irregular'] = $package['irregular'] ? $package['irregular'] : 0;
	
	$booleans = array('preemptrecurringappts', 'onedaypackage', 'billingreminders', 'prepaid');
	
	foreach($package as $field => $value) {
    if(in_array($field, $booleans)) $package[$field] = $value ? 1 : 0;
	}
}

function reapplySuspensionAndCancellation($packageid, $newPackage) {
	// delete each incomplete appointment that falls on a day before the startdate
	// delete each incomplete appointment that falls on or after the cancellation date
  $oldPackage = getRecurringPackage($packageid);
	$history = findPackageIdHistory($packageid, $oldPackage['clientptr'], ($oldPackage['enddate'] ? 0 : 1));
	$history = join(',',$history);
	$today = date('Y-m-d');
	$sql = "SELECT birthmark, date, timeofday, clientptr, servicecode FROM tblappointment 
						WHERE date >= '$today' AND packageptr IN ($history)";
	if($appts = fetchAssociations($sql))
		$preexistingAppointments = array_map('getAppointmentSignature', $appts);
		
//echo "HISTORY: $history<p>";	
//print_r($appts);
//echo "<p>PRE-EXIST: ".join('<br>',$preexistingAppointments);
//echo "HISTORY: $history";exit;	
	// history includes $packageid
	deleteAppointments("completed IS NULL AND packageptr IN ($history) AND date < '{$newPackage['startdate']}'");
	if($_SESSION['surchargesenabled']) 
		dropAutoSurchargesWhere("completed IS NULL AND packageptr IN ($history) AND date < '{$newPackage['startdate']}'");
	if($newPackage['cancellationdate']) {
	  deleteAppointments("completed IS NULL AND packageptr IN ($history) AND date >= '{$newPackage['cancellationdate']}'");
		if($_SESSION['surchargesenabled']) 
			dropAutoSurchargesWhere("completed IS NULL AND packageptr IN ($history) AND date >= '{$newPackage['cancellationdate']}'");
	}
	  
  // uncancel all appointments in old suspension period
  // Q: should appts of all versions be uncanceled?
  if($oldPackage['suspenddate']) {
		$ids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE completed IS NULL AND 
	          packageptr = {$oldPackage['packageid']} AND date >= '$today' AND date >= '{$oldPackage['suspenddate']}' AND date < '{$oldPackage['resumedate']}'");
	  if($ids) {
			doQuery("UPDATE tblappointment SET canceled=null, cancellationreason=null 
	          WHERE appointmentid IN (".join(',', $ids).")");
	    setAppointmentDiscounts($ids, true);
			foreach($ids as $id)
				logAppointmentStatusChange(array('appointmentid'=>$id), "Undoing old suspension period");
		}
	}


	updateRepeatingPackage($packageid, $newPackage);

	// set up appointments for new package dates.  Ignore existing package dates.
	$existingAppointmentInterval[] = fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr = $packageid ORDER BY date ASC LIMIT 1");
	$existingAppointmentInterval[] = fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr = $packageid ORDER BY date DESC LIMIT 1");
//createScheduleAppointments($package, $services, $recurring, $intervalToIgnore=null, $simulation=null, $allowRetroactiveAppointments=false, $preexistingAppointments=null, $preexistingAppointmentMatch='appointmentSignaturesEqual') {


	createScheduleAppointments($newPackage, getPackageServices($packageid), true, $existingAppointmentInterval, false, false, $preexistingAppointments);
	// Cancel all appointments in suspension period
  if($newPackage['suspenddate']) {
		$ids = fetchCol0("SELECT appointmentid FROM tblappointment WHERE packageptr = $packageid AND completed IS NULL AND date >= '$today' 
	               AND date >= '{$newPackage['suspenddate']}' AND date < '{$newPackage['resumedate']}'");
	  if($ids) {
			doQuery("UPDATE tblappointment SET canceled=NOW(), cancellationreason='Canceled: Planned suspension' 
							WHERE appointmentid IN (".join(',', $ids).")");
      setAppointmentDiscounts($ids, false);							
			foreach($ids as $id)
				logAppointmentStatusChange(array('appointmentid'=>$id, 'canceled'=>1), "Planned suspension");
		}
	}	               
	
}

function reapplyDateLimits(&$oldPackage, $changes) {
	// delete each incomplete appointment that falls on a day before the startdate
	// delete each incomplete appointment that falls on or after the end date
	// delete each incomplete appointment that falls on or after the cancellation date
	$packageid = $oldPackage['packageid'];
	$packageHistory = findPackageHistories($oldPackage['clientptr']);
	$packageHistory = join(',', $packageHistory[$packageid]);
	

	updateNonrepeatingPackage($packageid, $changes);
  $newPackage = getNonrecurringPackage($packageid);
	deleteAppointments("completed IS NULL AND packageptr in ($packageHistory) AND date < '{$newPackage['startdate']}'");
	if($_SESSION['surchargesenabled']) 
		dropAutoSurchargesWhere("completed IS NULL AND packageptr in ($packageHistory) AND date < '{$newPackage['startdate']}'");	
	deleteAppointments("completed IS NULL AND packageptr in ($packageHistory) AND date > '{$newPackage['enddate']}'");
	if($_SESSION['surchargesenabled']) 
		dropAutoSurchargesWhere("completed IS NULL AND packageptr in ($packageHistory) AND date > '{$newPackage['enddate']}'");	

	if($newPackage['cancellationdate']) {
		$cancellationDate = date('Y-m-d', $newPackage['cancellationdate']);
	  deleteAppointments("completed IS NULL AND packageptr in ($packageHistory) AND date >= '$cancellationDate'");
		if($_SESSION['surchargesenabled']) 
			dropAutoSurchargesWhere("completed IS NULL AND packageptr in ($packageHistory) AND date >= '$cancellationDate'");	
	  // Un-cancel any preempted appointments from the cancellationdate to the (old) end of the package
	  if(isset($oldPackage['preemptrecurringappts']) && $oldPackage['preemptrecurringappts']) {
			$ids = fetchCol0(
						"SELECT appointmentid FROM tblappointment 
							WHERE canceled IS NOT NULL AND clientptr = {$newPackage['clientptr']}
	  					AND recurringpackage = 1 AND date >= '$cancellationDate' AND date <= '{$newPackage['enddate']}'
	  					AND cancellationreason LIKE 'Preempted by%'");
			if($ids) {
				doQuery("UPDATE tblappointment SET canceled=null, cancellationreason=null
								WHERE appointmentid IN (".join(',', $ids).")");
				setAppointmentDiscounts($appts, true);
				foreach($ids as $id)
					logAppointmentStatusChange(array('appointmentid'=>$id), "Preemption undone (reapplyDateLimits).");
			}
		}
	}
	            


	// set up appointments for new package dates.  Ignore existing package dates.
	$existingAppointmentInterval[] = fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr = $packageid ORDER BY date ASC LIMIT 1");
	$existingAppointmentInterval[] = fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr = $packageid ORDER BY date DESC LIMIT 1");

	createScheduleAppointments(getNonrecurringPackage($packageid), getPackageServices($packageid), false, $existingAppointmentInterval);
}

function rolloverRecurringSchedules() {
	global $biz; // set in cron-recurring-schedule-rollover.php
	$bizdb = $biz ? $biz['db'] : 'unspecified database';
	$prefs = isset($_SESSION['preferences']) ? $_SESSION['preferences']
				: fetchKeyValuePairs("SELECT property, value FROM tblpreference");  // will not be set for cron job

	if($prefs['rolloverdisabled']) return;
	$schedules = fetchAssociations(tzAdjustedSql("SELECT * FROM tblrecurringpackage WHERE current = 1 
																			AND (cancellationdate IS NULL || cancellationdate > CURDATE())
																			AND startdate <= CURDATE()"));
}
	$histories = findPackageHistories(null, 'R');
				
	$recurringLookaheadDays = $prefs['recurringScheduleWindow'] ? $prefs['recurringScheduleWindow'] : 30;
//$recurringLookaheadDays += 2; 	
	$lastDay = date('Y-m-d', strtotime("+ $recurringLookaheadDays days"));
	$today = date('Y-m-d');
	$createdAppts = 0;
	foreach($schedules as $schedule) {
//echo "SCHEDULE: start [{$schedule['startdate']}]".print_r($schedule, 1).'<p>';		
		$packageid = $schedule['packageid'];
		$ids = isset($histories[$packageid]) ? join(',',$histories[$packageid]) : $packageid;
		// Collect all appointments for all versions of schedule for the next N days.  Include canceled appointments.
		
		$sql = "SELECT birthmark, date, timeofday, clientptr, servicecode FROM tblappointment 
							WHERE date >= '$today' AND date <= '$lastDay' AND packageptr IN ($ids)";
				
		if($appts = fetchAssociations($sql))
				
			$apptSignatures = array_map('getAppointmentSignature', $appts);
//if($schedule['packageid'] == 430) {print_r($apptSignatures);exit;}		
		/*$existingAppointmentInterval[] = 
			fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr IN ($ids) ORDER BY date ASC LIMIT 1");
		$existingAppointmentInterval[] = 
			fetchRow0Col0("SELECT date FROM tblappointment WHERE  packageptr IN ($ids) ORDER BY date DESC LIMIT 1");*/
		$createdAppts += count(
				createScheduleAppointments($schedule, getPackageServices($packageid), true, 
																		$existingAppointmentInterval, null, null, $apptSignatures)
			);
				
	}		
	
	/* TBD: Replace much of this with:	
	foreach($schedules as $schedule) {
		$createdAppts += count(
				rolloverRecurringSchedule($scheduleOrPackageId, $histories, $recurringLookaheadDays)	
				);
	}
	*/
	
	
	logChange(0, 'tblrecurringpackage', 'c', "ROLLOVER FINISHED: $createdAppts created in $bizdb.");
	return $createdAppts;
}

function rolloverRecurringSchedule($scheduleOrPackageId, $histories=null, $recurringLookaheadDays=null) {
	$schedule = is_array($scheduleOrPackageId) ? $scheduleOrPackageId
							: getPackage($scheduleOrPackageId, 'R');
	$histories = $histories ? $histories : findPackageHistories($schedule['clientptr'], 'R');
	$recurringLookaheadDays = $recurringLookaheadDays ? $recurringLookaheadDays 
			: $_SESSION['preferences']['recurringScheduleWindow'];
	$lastDay = date('Y-m-d', strtotime("+ $recurringLookaheadDays days"));
	$today = date('Y-m-d');
	$packageid = $schedule['packageid'];
	$ids = isset($histories[$packageid]) ? join(',',$histories[$packageid]) : $packageid;
	// Collect all appointments for all versions of schedule for the next N days.  Include canceled appointments.
	$sql = "SELECT birthmark, date, timeofday, clientptr, servicecode FROM tblappointment 
						WHERE date >= '$today' AND date <= '$lastDay' AND packageptr IN ($ids)";
	if($appts = fetchAssociations($sql))
		$apptSignatures = array_map('getAppointmentSignature', $appts);
	$createdAppts = count(
			createScheduleAppointments($schedule, getPackageServices($packageid), true, 
																	$existingAppointmentInterval, null, null, $apptSignatures)
		);
	return $createdAppts;
}

function uncancelRecurringSchedule($packageid) {
	updateTable('tblrecurringpackage', 
		array('cancellationdate' => null, 
						'modified'=>date('Y-m-d H:i:s'),
						'modifiedby'=>$_SESSION['auth_user_id']), 
					"packageid = $packageid", 1);
	rolloverRecurringSchedule($packageid);
}

function cancelRecurringSchedule($packageid, $date) {
	$package = getPackage($packageid, 'R');
	if(!$package) {
		echo "ERROR: package $packageid is not a recurring package.";
		return;
	}
	$packageHistory = findPackageIdHistory($packageid, $package['clientptr'], true);
	$cancellationdate = date('Y-m-d', strtotime($date));
	deleteTable('tblappointment', 
							"completed IS NULL AND packageptr IN (".join(',', $packageHistory).") AND "
							."date >= '$cancellationdate'", 1);
	updateTable('tblrecurringpackage', 
		array('cancellationdate' => $cancellationdate, 
						'modified'=>date('Y-m-d H:i:s'),
						'modifiedby'=>$_SESSION['auth_user_id']), 
					"packageid = $packageid", 1);
}
	
	

function applyPreemptionPreference($oldPackage, &$newPackage) {
	$recPackIds = join(',',fetchCol0("SELECT packageid FROM tblrecurringpackage WHERE clientptr = {$newPackage['clientptr']}"));
	if(!$recPackIds) return;
	
	$today = date('Y-m-d');
	$preemptionNote = 'Preempted by another service package.';
	$oldPref = $oldPackage && isset($oldPackage['preemptrecurringappts']) && $oldPackage['preemptrecurringappts'] ? 1 : 0;
	$newPref = isset($newPackage['preemptrecurringappts']) && $newPackage['preemptrecurringappts'] ? 1 : 0;
	
	// restore appointments in old range
	if($oldPref) {
	  $oldRange = array(strtotime($oldPackage['startdate']), strtotime($oldPackage['enddate']));
		$cancellation = $oldPackage['cancellationdate'] ? strtotime($oldPackage['cancellationdate']) : '';
		if($cancellation) $oldRange[1] = min($cancellation, $oldRange[1]);
	  $oldRange = array(date('Y-m-d',$oldRange[0]), date('Y-m-d',$oldRange[1]));
		$ids = fetchCol0("SELECT appointmentid FROM tblappointment 
						WHERE completed IS NULL AND canceled IS NOT NULL AND clientptr = {$newPackage['clientptr']} AND 
						date >= '$today' AND date >= '{$oldRange[0]}' AND date <= '{$oldRange[1]}' AND
						packageptr IN ($recPackIds) AND cancellationreason = '$preemptionNote'");
		if($ids) {
			doQuery("UPDATE tblappointment SET canceled=null, cancellationreason=null WHERE appointmentid IN (".join(',', $ids).")");
			setAppointmentDiscounts($appts, false);  // appts are not canceled, but not complete
			foreach($ids as $id)
				logAppointmentStatusChange(array('appointmentid'=>$id), "Preemption undone (applyPreemptionPreference).");
		}
	}
	  // cancel appointments in new range						
	if($newPref) {
		$newRange = array(strtotime($newPackage['startdate']), strtotime($newPackage['enddate']));
		$cancellation = $newPackage['cancellationdate'] ? strtotime("-1 day", strtotime($newPackage['cancellationdate'])) : '';
		if($cancellation) $newRange[1] = min($cancellation, $newRange[1]);
		$newRange = array(date('Y-m-d',$newRange[0]), date('Y-m-d',$newRange[1]));
		$ids = fetchCol0("SELECT appointmentid FROM tblappointment 
							WHERE completed IS NULL AND clientptr = {$newPackage['clientptr']} AND 
							date >= '$today' AND date >= '{$newRange[0]}' AND date <= '{$newRange[1]}' AND
							packageptr IN ($recPackIds)");
		if($ids) {
			doQuery("UPDATE tblappointment
							SET completed = null, canceled = '$today', cancellationreason = '$preemptionNote' WHERE appointmentid IN (".join(',', $ids).")");
			setAppointmentDiscounts($appts, false);
			foreach($ids as $id)
				logAppointmentStatusChange(array('appointmentid'=>$id), "Preempted. (applyPreemptionPreference)");
		}
	}
	
}

function updateNonrepeatingPackage($packageid, $data) {	
	preprocessNonrepeatingPackage($data);
	updateTable('tblservicepackage', withModificationFields($data), "packageid = $packageid", 1);
	logChange($packageid, 'tblservicepackage', 'm', ($data['onedaypackage'] ? 'One-Day ' : '')." Nonrecurring package modified for client [{$data['clientptr']}].");

}

function updateRepeatingPackage($packageid, $data) {
	if(isset($data['monthly']) && $data['monthly']) preprocessMonthlyPackage($data);
	else preprocessRepeatingPackage($data);
	updateTable('tblrecurringpackage', withModificationFields($data), "packageid = $packageid", 1);
	logChange($packageid, 'tblservicepackage', 'm', ($data['monthly'] ? 'Monthly ' : 'Weekly')." Recurring package modified for client [{$data['clientptr']}].");
}

function saveNonrepeatingPackage($packageid) {
  // Assess changes to the schedule and its line items.  
  $oldPackage = getNonrecurringPackage($packageid);
	$changes = nonrepeatingPackageChanges($oldPackage);
	if(!$changes) return;
  //0. Assess changes to the schedule and its line items.  If only change is to: suspension, cancellation, notes, start, weekly adjustment the follow "Apply Suspension" procedure.
	// If only changes are to suspension or cancellation dates, then update and follow "reapplyDateLimits" procedure.
//$nonrepeatingPackageFields = explode(',', 'startdate,enddate,packageprice,departuredate,cancellationdate,returndate,cancellationreason,notes,howtocontact');

	if(count(array_diff($changes, array('departuredate','returndate','cancellationdate', 'cancellationreason', 
																				'notes', 'howtocontact'.'preemptrecurringappts', 'previousversionptr', 'current'))) == 0) {
		$outData['clientptr'] = $_POST['client'];
    $outData['preemptrecurringappts'] = isset($_POST['preemptrecurringappts']) ? $_POST['preemptrecurringappts'] : 0;
	  $dateFields = array('startdate','cancellationdate', 'enddate');
	  $aDateChanged = false;
	  foreach($changes as $field) {
			if(in_array($field, $dateFields)) 
				$aDateChanged = true;
			$outData[$field] = $_POST[$field];
		}
		foreach($dateFields as $field)
			if($_POST[$field] && in_array($field, $changes)) 
				$outData[$field] = date('Y-m-d', strtotime($_POST[$field]));
		if($aDateChanged) {
			reapplyDateLimits($oldPackage, $outData);
	  }
	  else updateNonrepeatingPackage($packageid, $outData);
	  
		// If this package is preemptive, Cancel all future, incomplete recurring appointments in the period
		applyPreemptionPreference($oldPackage, getNonrecurringPackage($packageid));
	  return;
	}
  
	$packageHistory = findPackageIdHistory($packageid, $_POST['client'], false);	
  
  //1. findUnassignableDates

  $unassignableDates = findUnassignableDates();  // $time => $lineItem (service)

  //2. Collect any incomplete, uncanceled custom appointments for the current schedule

  $deletableAppointments = getDeletableCustomAppointments($packageid, false);
  //3. Delete all future incomplete, non-custom, uncanceled appointments associated with this schedule
  deleteAllIncomplete($packageHistory, false, false);
  //3.5  If oneday and retroactive, invalidateRetroactiveOneDayScheduleAppointments
	$allowRetroactiveAppointments = $_POST['onedaypackage'];
	if($allowRetroactiveAppointments && strtotime($_POST['startdate']) < strtotime(date('Y-m-d')))
  	invalidateRetroactiveOneDayScheduleAppointments($packageHistory, $_POST['client']);
  //4. Mark the existing schedule and its line items as non-current
  markPackageNoncurrent($packageid, 'tblservicepackage');
  //5. Create a new schedule which refers to the old schedule as its previous version
  $outData = array_merge($_POST);
  $outData['previousversionptr'] = $packageid;
  $outData['preemptrecurringappts'] = isset($outData['preemptrecurringappts']) ? $outData['preemptrecurringappts'] : 0;
  preprocessNonrepeatingPackage($outData);
  $newpackageid = newPackage('tblservicepackage', $outData, 1);
  
  $outData['packageid'] = $newpackageid;
//echo "OUTDATA: [".print_r($outData, 1)."]";  exit;
  //6. Create new line items for the new schedule
  $services = saveServices($newpackageid, false);
  //7. Create new appointments for the line items (see Create Appt, below)
  createScheduleAppointments($outData, $services, false, null, null, $allowRetroactiveAppointments);

	//8. If this package is preemptive, Cancel all future, incomplete recurring appointments in the period
	applyPreemptionPreference($oldPackage, $outData);

	if(!$oldPackage['onedaypackage']) 
		labelFirstAndLastVisitsForNRPackage($newpackageid, $outData['clientptr'], $justClear=!$_POST['markStartFinish']);  

  //9. Find all appointments from other schedules that overlap this schedule's appointments in date and timeframe (time Conflicts)
  global $conflicts;
  $conflicts = findConflicts($_POST['client'], $newpackageid, $recurring=0,
  														$_POST['startdate'], 
  														($_POST['onedaypackage'] ? $_POST['startdate'] : $_POST['enddate']));
  /*
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $_POST['client']);
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid, false);
  $dateRange = array($_POST['startdate'], ($_POST['onedaypackage'] ? $_POST['startdate'] : $_POST['enddate']));
  $conflicts['customConflicts'] = findCustomConflicts($packageid, $_POST['client'], $dateRange[0], $dateRange[1]);
//print_r($conflicts);exit;  
  foreach($conflicts as $cat => $rows) if(!$rows) unset($conflicts[$cat]);
  */
//echo "<hr>CONFLICTS: ".print_r($conflicts, 1);  exit;
  //10. Show user a list of appointments that are:
  //  a. new and unassigned
  //  b. old and present time conflicts
  //  c. old and custom
  //11. Allow user to mark any appointment for deletion
  // Do #10 and #11 in another method
  notifyStaffOfScheduleChange($newpackageid, false);
	return $outData;
}

function findConflicts($clientptr, $newpackageid, $recurring, $start=null, $end=null) {
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $clientptr);
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid, $recurring);
  //$dateRange = array($_POST['startdate'], ($_POST['onedaypackage'] ? $_POST['startdate'] : $_POST['enddate']));
  $conflicts['customConflicts'] = findCustomConflicts($newpackageid, $clientptr, $recurring, $start, $end);
//print_r($conflicts);exit;  
  foreach($conflicts as $cat => $rows) if(!$rows) unset($conflicts[$cat]);
  return $conflicts;
}

function notifyStaffOfScheduleChange($packageOrId, $new=false) {
	require_once "comm-fns-2.php";
	require_once "event-email-fns-2.php";
	require_once "client-fns.php";
	$package = is_array($packageOrId) ? $packageOrId : getPackage($packageOrId);
	$packageid = $package['packageid'];
	
	$packageCode = !$package ? 'UNK' : ($package['monthly'] ? 'MON' :
								 ($package['onedaypackage'] ? 'ONE' :
								 ($package['irregular'] == 1 ? 'IRREG' :
								 ($package['irregular'] == 2 ? 'MEETING' :
								 ($package['enddate'] ? 'NONREC' : 'REC')))));
	$packageLabels = array('UNK'=>'Unknown', 'MON'=>'Fixed Price Monthly','ONE'=>'One Day','NONREC'=>'Short Term','REC'=>'Recurring','IRREG'=>'EZ Schedule', 'MEETING'=>'Meeting');
	$packageType = $packageLabels[$packageCode];
								 
	
	$client = getOneClientsDetails($package['clientptr'], array('fullname'));
	$note = packageDescriptionHTML($package, $client['clientname']);

	$userName = $_SESSION["auth_username"];

	if($new) {
		$subject = "New $packageType schedule";
		$note = "created a schedule for {$client['clientname']}:<p>$note";
	}
	else {
		$subject = "$packageType Schedule change";
		$note = "$userName has changed {$client['clientname']}'s schedule:<p>$note";
	}
	notifyStaff('s', "$subject: client {$client['lname']}", $note);
}

function saveIrregularPackage($packageid) {

//screenLogPageTime("up to START saveIrregularPackage($packageid) [$newpackageid] run time: ");
	
  // Assess changes to the schedule and its line items.  
  $oldPackage = getNonrecurringPackage($packageid);
	$changes = irregularPackageChanges($oldPackage);
	if(!$changes) return;
  //0. Assess changes to the schedule and its line items.  If only change is to: suspension, cancellation, notes, start, weekly adjustment the follow "Apply Suspension" procedure.
	// If only changes are to suspension or cancellation dates, then update and follow "reapplyDateLimits" procedure.
//$nonrepeatingPackageFields = explode(',', 'startdate,enddate,packageprice,departuredate,cancellationdate,returndate,cancellationreason,notes,howtocontact');

	if(count(array_diff($changes, array('departuredate','returndate','cancellationdate', 'cancellationreason', 
																				'notes', 'howtocontact'.'preemptrecurringappts', 'previousversionptr', 'current', 
																				'packageprice'))) == 0) {
		$outData['clientptr'] = $_POST['client'];
		$outData['irregular'] = $oldPackage['irregular'];
    $outData['preemptrecurringappts'] = isset($_POST['preemptrecurringappts']) ? $_POST['preemptrecurringappts'] : 0;
	  $dateFields = array('startdate','cancellationdate', 'enddate');
	  $aDateChanged = false;
	  foreach($changes as $field) {
			if(in_array($field, $dateFields)) 
				$aDateChanged = true;
			$outData[$field] = $_POST[$field];
		}
		foreach($dateFields as $field)
			if($_POST[$field] && in_array($field, $changes)) 
				$outData[$field] = date('Y-m-d', strtotime($_POST[$field]));
		if($aDateChanged) {
			reapplyDateLimits($oldPackage, $outData);
	  }
	  else updateNonrepeatingPackage($packageid, $outData);
	  
		// If this package is preemptive, Cancel all future, incomplete recurring appointments in the period
		applyPreemptionPreference($oldPackage, getNonrecurringPackage($packageid));
		labelFirstAndLastVisitsForNRPackage($packageid, $oldPackage['clientptr'], $justClear=!$_POST['markStartFinish']);

	  return;
	}
	// DOES THIS NEXT SECTION APPLY TO IRREGULARS?
  
	$packageHistory = findPackageIdHistory($packageid, $_POST['client'], false);	
  
  //4. Mark the existing schedule and its line items as non-current
  markPackageNoncurrent($packageid, 'tblservicepackage');
  //5. Create a new schedule which refers to the old schedule as its previous version
  $outData = array_merge($_POST);
	$outData['irregular'] = $oldPackage['irregular'];
  $outData['previousversionptr'] = $packageid;
  $outData['preemptrecurringappts'] = isset($outData['preemptrecurringappts']) ? $outData['preemptrecurringappts'] : 0;
  preprocessNonrepeatingPackage($outData);
  $newpackageid = newPackage('tblservicepackage', $outData, 1);
  
  $outData['packageid'] = $newpackageid;
//echo "OUTDATA: [".print_r($outData, 1)."]";  exit;

	//8. If this package is preemptive, Cancel all future, incomplete recurring appointments in the period
	applyPreemptionPreference($oldPackage, $outData);

  //9. Find all appointments from other schedules that overlap this schedule's appointments in date and timeframe (time Conflicts)
  global $conflicts;
//screenLogPageTime("pre findTimeConflicts($packageid) [$newpackageid] run time: ");
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $_POST['client']);
//screenLogPageTime("POST findTimeConflicts($packageid) [$newpackageid] run time: ");
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid, false);
  //$conflicts['customConflicts'] = findCustomConflicts($packageid, $_POST['client']);
  foreach($conflicts as $cat => $rows) if(!$rows) unset($conflicts[$cat]);
//echo "<hr>CONFLICTS: ".print_r($conflicts, 1);  exit;
  //10. Show user a list of appointments that are:
  //  a. new and unassigned
  //  b. old and present time conflicts
  //  c. old and custom
  //11. Allow user to mark any appointment for deletion
  // Do #10 and #11 in another method
	labelFirstAndLastVisitsForNRPackage($packageid, $oldPackage['clientptr'], $justClear=!$_POST['markStartFinish']);
//screenLogPageTime("END saveIrregularPackage($packageid) [$newpackageid] run time: ");
	return $outData;
}

function invalidateRetroactiveOneDayScheduleAppointments($packageHistory, $clientptr) {
	// for each of the uncanceled appointments in a one-day schedule
	//   if it has a billable and the billable has been invoiced or paid cancel the appointment and supersede its billable 
	//	 else  delete the appointment
	$appts = fetchAssociations(
			"SELECT appointmentid, billableid, paid, invoiceptr
				FROM tblappointment 
				LEFT JOIN tblbillable ON itemptr = appointmentid
				WHERE itemtable = 'tblappointment' AND packageptr IN (".join(',',$packageHistory).")");
	foreach($appts as $appt) {
			if($appt['invoiceptr'] || ($appt['paid'] && $appt['paid'] > 0.00)) {
				updateTable('tblappointment', withModificationFields(array('canceled'=>date('Y-m-d H:i:s'), 'completed'=>null)), "appointmentid = {$appt['appointmentid']}", 1);
				setAppointmentDiscounts(array($appt['appointmentid']), false);
				logAppointmentStatusChange($appt, 'One-day schedule changed retroactively.');
				supersedeBillable($appt['billableid']);
			}
			else {
				deleteAppointments("appointmentid = {$appt['appointmentid']}");
				if($appt['billableid']) deleteTable('tblbillable', "billableid = {$appt['billableid']}", 1);
			}
	}
}

function saveRepeatingPackage($packageid) {
	global $monthlyPackageFields, $repeatingPackageFields;
  // Assess changes to the schedule and its line items.  
  $oldPackage = getRecurringPackage($packageid);
	$outData['clientptr'] = $_POST['client'];
	$outData['prepaid'] = $_POST['prepaid'];
	$outData['monthly'] = $oldPackage['monthly'];
	$changes = repeatingPackageChanges($oldPackage);
	
	if(!$changes) return;
  //0. Assess changes to the schedule and its line items.  If only change is to: suspension, cancellation, notes, start, weekly adjustment then
  //       there's no need to delete and recreate all appointments.
	// If only changes are to startdate, suspension, or cancellation dates, then update and follow "reapplySuspensionAndCancellation" procedure.
	$relevantFields = $oldPackage['monthly'] ? $monthlyPackageFields : $repeatingPackageFields;
	$relevantFields = array_diff($relevantFields, array('previousversionptr','current', 'effectivedate'));
	if(count(array_diff($changes, $relevantFields)) == 0) {
//echo "Changes: ";print_r($changes);
//echo "<br>relevantFields: ";print_r($relevantFields);
//echo "<br>diff: ";print_r(array_diff($changes, $relevantFields));
	  //print_r($changes);echo '<p>';
	  //print_r(array_diff($changes, array('suspenddate','resumedate','cancellationdate', 'notes', 'weeklyadjustment')));echo '<p>';
	  $dateFields = array('startdate','cancellationdate','suspenddate','resumedate');
	  $aDateChanged = false;
	  foreach($changes as $field) {
			if(in_array($field, $dateFields)) $aDateChanged = true;
			$outData[$field] = $_POST[$field];
		}
		foreach($dateFields as $field)
			if($_POST[$field]) 
				$outData[$field] = date('Y-m-d', strtotime($_POST[$field]));
		if($aDateChanged) {
			reapplySuspensionAndCancellation($packageid, $outData);
	  }
	  else {
			updateRepeatingPackage($packageid, $outData);
		}
	  return;
	}
	
	$packageHistory = findPackageIdHistory($packageid, $_POST['client'], true);	
	
  //1. findUnassignableDates
  $unassignableDates = findUnassignableDates();  // $time => $lineItem (service)
  //2. Collect any incomplete, uncanceled custom appointments for the current schedule
  $deletableAppointments = getDeletableCustomAppointments($packageid, true, $_POST['effectivedate']);
  //3. Delete all future incomplete, non-custom, uncanceled appointments associated with this schedule
  deleteAllIncomplete($packageHistory, true, false, $_POST['effectivedate']);
  //4. Mark the existing schedule and its line items as non-current
  markPackageNoncurrent($packageid, 'tblrecurringpackage');
  //5. Create a new schedule which refers to the old schedule as its previous version
  //$outData = array_merge($_POST);
	$outData = requestIsJSON() ? getJSONRequestInput() : array_merge($_POST);

	$outData['monthly'] = $oldPackage['monthly'];
  $outData['previousversionptr'] = $packageid;
  preprocessRepeatingPackage($outData);
	$newpackageid = newPackage('tblrecurringpackage', $outData, 1);

  $outData['packageid'] = $newpackageid;
//echo "OUTDATA: [".print_r($outData, 1)."]";  exit;
  //6. Create new line items for the new schedule
  
  $services = saveServices($newpackageid, true);
  //7. Create new appointments for the line items (see Create Appt, below)
	createScheduleAppointments($outData, $services, true);
//exit;
  //8. Find all appointments from other schedules that overlap this schedule's appointments in date and timeframe (time Conflicts)
  global $conflicts;
	if($outData['effectivedate']) {
		$findConflictsStarting = date('Y-m-d', max(time(), strtotime($outData['effectivedate'])));
		}
	}

  $conflicts = findConflicts($_POST['client'], $newpackageid, $recurring=1, $findConflictsStarting);
  
  /*
  $conflicts['timeConflicts'] = findTimeConflicts($newpackageid, $_POST['client']);
  $conflicts['unassignedAppointments'] = findUnassignedAppointments($newpackageid, true);
  $conflicts['customConflicts'] = findCustomConflicts($packageid, $_POST['client']);
  foreach($conflicts as $cat => $rows) if(!$rows) unset($conflicts[$cat]);
	*/

//echo "<hr>CONFLICTS: ".print_r($conflicts, 1);  exit;
  //9. Show user a list of appointments that are:
  //  a. new and unassigned
  //  b. old and present time conflicts
  //  c. old and custom
  //10. Allow user to mark any appointment for deletion
  // Do #9 and #10 in another method
  notifyStaffOfScheduleChange($newpackageid, false);
	return $outData;
}

function appointmentsOverlap($a, $b) {
	return ($a['starttime'] >= $b['starttime'] && $a['starttime'] <= $b['endtime']) ||
	   ($b['starttime'] >= $a['starttime'] && $b['starttime'] <= $a['endtime']);
}

function getAllTBDAppointments($packageid, $recurring) {
	$recurring = $recurring ? 1 : 0;
	$appts = array();
	$futureAppointmentRows = fetchAssociations(tzAdjustedSql(
	  "SELECT * FROM tblappointment 
	    WHERE completed IS NULL AND canceled IS NULL AND packageptr = $packageid AND recurringpackage = $recurring
	      AND(date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))
	    ORDER BY date, starttime"));
  foreach($futureAppointmentRows as $row)
		$appts[$row['date']][] = $row;
	return $appts;
}

function getAllScheduledAppointments($packageid, $where='') {
	$package = getPackage($packageid);
	$recurring = isset($package['monthly']) ? '1' : '0';
	$history = findPackageIdHistory($packageid, $package['clientptr'], $recurring);
	$history = join(',', $history);
	$appts = array();
	$where = $where ? "AND $where" : '';
	return fetchAssociationsKeyedBy(
	  "SELECT * FROM tblappointment 
	    WHERE packageptr IN ($history) AND recurringpackage = $recurring $where
	    ORDER BY date, starttime", 'appointmentid');
}

function findUnassignedAppointments($packageid, $recurring) {
	$recurring = $recurring ? 1 : 0;
	$customConflicts = array();
	$futureAppointmentRows = fetchAssociations(tzAdjustedSql(
	  "SELECT * FROM tblappointment 
	    WHERE completed IS NULL AND canceled IS NULL AND packageptr = $packageid AND recurringpackage = $recurring AND providerptr = 0
	      AND(date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"));
  foreach($futureAppointmentRows as $row)
		$customConflicts[$row['date']][] = $row;
	return $customConflicts;
}

function findPackageIdHistory($packageid, $clientptr, $recurring) {
	$histories = findPackageHistories($clientptr, $recurring ? 'R' : 'N');
	return isset($histories[$packageid]) ? $histories[$packageid] : array();
}

function findCurrentPackageVersion($packageid, $clientptr, $recurring, $lastestIfNoneActive=false) {
	$table = $recurring ? 'tblrecurringpackage' : 'tblservicepackage';
	$currentPackages = fetchCol0("SELECT packageid FROM $table WHERE clientptr = $clientptr and current = 1");
	$histories = findPackageHistories($clientptr, $recurring ? 'R' : 'N');
	foreach($histories as $version => $history)
		if(in_array($packageid, $history) && in_array($version, $currentPackages))
			return $version;
	if(!$lastestIfNoneActive) return;
	// if no active version was found and $lastestIfNoneActive
	$histories = findPackageHistories($clientptr, $recurring ? 'R' : 'N');
	foreach($histories as $version => $history)
		if(in_array($packageid, $history))
			return $version;
	
}
	
function findLatestPackageVersion($packageid, $histories) {
	//$histories = findPackageHistories($clientptr, $recurring ? 'R' : 'N');
	// find version with $packageid with longest history
	foreach($histories as $version => $history)
		if(in_array($packageid, $history))
			$versions[$version] = count($history);
	asort($versions);
	
	return array_pop(array_keys($versions));
}
	
function findPackageHistories($clientptr, $RorNorNull=null, $currentOnly=false) {
	//$client = $clientptr ?  "AND clientptr = $clientptr" : '';
	//$sql = "SELECT packageid, previousversionptr FROM XXX WHERE previousversionptr $client ORDER BY packageid DESC";
	// return array (package1 => array(previous versions), package2 => array(previous versions), ...)
	$client = $clientptr ?  "clientptr = $clientptr" : '1=1';
	$sql = "SELECT packageid, previousversionptr, current FROM XXX WHERE $client ORDER BY packageid DESC";
	$packs = array();
	if(!$RorNorNull || $RorNorNull == 'R')
		foreach(fetchAssociationsKeyedBy(($test = str_replace('XXX', 'tblrecurringpackage', $sql)), 'packageid') as $key => $val)
            $packs[$key] = $val;
	if(!$RorNorNull || $RorNorNull == 'N')
		foreach(fetchAssociationsKeyedBy(($test = str_replace('XXX', 'tblservicepackage', $sql)), 'packageid')
							as $key => $val)
				$packs[$key] = $val;
	foreach($packs as $package) 
		$pairs[$package['packageid']] = $package['previousversionptr'];
	$history = array();
}
	foreach((array)$pairs as $pack =>$prev) {
		if($currentOnly && !$packs[$pack]['current']) continue;
}
		//$history[$pack] = buildPackageHistory($pairs, $pack);
	}
	return $history;
}


function buildPackageHistory(&$pairs, $pack) {
	if(!($prev = $pairs[$pack]))
        return array($pack);
	else {
        if(mattOnlyTEST()) {
            echo "<hr><hr>pack: [$pack]<hr>prev: [$prev]".print_r($pairs, 1);
        }
		return array_merge(buildPackageHistory($pairs, $prev), array($pack));
	}
}

function findCustomConflicts($newpackageid, $clientptr, $recurring, $startDate=null, $endDate=null) {
	// Find visits from prior versions of this schedule which still exists because they have been modified
	$customConflicts = array();
	$history = findPackageIdHistory($newpackageid, $clientptr, $recurring);
	foreach($history as $i => $id) if($id == $newpackageid) unset($history[$i]);
	$history = $history ? join(',', $history): $history;
	$historyCheck = $history ? "AND packageptr IN ($history)" : '';
	$futureAppointmentRows = fetchAssociations(tzAdjustedSql(
	  "SELECT * FROM tblappointment
	    WHERE clientptr = $clientptr AND custom AND completed IS NULL AND canceled IS NULL $historyCheck
	      AND(date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"
	      .($startDate && $endDate ? "AND date >= '$startDate' AND date <= '$endDate'" : (
					$startDate ? "AND date >= '$startDate'" : ''))
	      ));
  foreach($futureAppointmentRows as $row) 
		$customConflicts[$row['date']][] = $row;
	return $customConflicts;
}

function findTimeConflicts($packageid, $clientptr) {  // TBD: Test this
	$timeConflicts = array();
	$futureAppointments = array();
	$result = doQuery(tzAdjustedSql(   //AND canceled IS NULL 
	  "SELECT * FROM tblappointment 
	    WHERE completed IS NULL AND clientptr = $clientptr
	      AND(date > CURDATE() OR (date = CURDATE() AND starttime > CURTIME()))"));
  while($row = mysqli_fetch_assoc($result))
		$futureAppointments[$row['date']][$row['packageptr']][] = $row;

//if($futureAppointments['2010-01-26']) print_r($futureAppointments['2010-01-26']);exit;
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') { print_r($futureAppointments);}
	
	foreach($futureAppointments as $date => $packages) { // for each day...
		if(isset($packages[$packageid]) && (count($packages) > 1)) // if there are appts for this package AND other packages
		  foreach($packages[$packageid] as $newAppt) {
				foreach($packages as $otherPackageId => $package) {
					if($otherPackageId == $packageid) continue;
					foreach($package as $oldAppt)
					  if(appointmentsOverlap($newAppt, $oldAppt)) {
					    $timeConflicts[$date]['old'][$oldAppt['appointmentid']] = $oldAppt;
					    //$timeConflicts[$date]['new'][$newAppt['appointmentid']] = $newAppt;
					  }
				}
			}
		}
//echo "<p>futureAppointments: ".print_r($futureAppointments		,1)."<p>timeConflicts: ".print_r($timeConflicts		,1);
	return $timeConflicts;
}

function findUnassignableDates() {
	$start = max(postFieldTime('startdate'), strtotime(date("Y-m-d")));
	$stop = postFieldTime('cancellationdate');
	$endTime = postFieldTime('enddate'); // for non-recurring
	if($stop && $endTime) $stop = min($endTime, $stop, strtotime("+ {$_SESSION['preferences']['recurringScheduleWindow']} days"));
	$suspensionInterval = array(postFieldTime('suspenddate'), postFieldTime('resumedate'));
	if(!$suspensionInterval[0] || !$suspensionInterval[1]) $suspensionInterval = null;
	$unassignableDates = array();
	for($i=1;$i<=$services_visible;$i++) {
		if($_POST["servicecode_$i"])
		   findUnassignableServiceDates($i, $start, $stop, $suspensionInterval, $unassignableDates);
	}
	return $unassignableDates;
}

function findUnassignableServiceDates($number, $start, $stop, $suspensionInterval, &$unassignableDates) {
	// Don't worry about this until we implement provider time off
	return array();
	$timeOff = getTimeOffDates($_POST["providerptr_$number"]);
	$unassignableDates = array();
	if(!$timeOff) return null;
	$serviceDaysOfWeek = daysOfWeekArray($_POST["daysofweek_$number"]);
	foreach($timeOff as $time) {
		if($start && ($time < $start)) continue; // time off is before service dates, no problem
		if($stop && ($time > $stop)) continue; // time off is after service dates, no problem
		if($suspensionInterval &&  // time off is during suspensionInterval, no problem
		   ($suspensionInterval[0] <= $time) &&
		   ($time < $suspensionInterval[1])) continue;
		if(in_array(dayOfWeek($time), $serviceDaysOfWeek)) 
		  $unassignableDates[$time][] = $_POST["service_$number"];
	}
	return $unassignableDates;
}

function getTimeOffDates($provider) {
	$daysOff = array();
  $breaks = getProviderTimeOff($provider);
  foreach($breaks as $break) {
		$lastDay = strtotime($break['lastdayoff']);
    for($i = strtotime($break['firstdayoff']); $i <= $lastDay; $i = strtotime("+1 day", $i))
      $daysOff[] = $i;
	}
	return $daysOff;
}

function dayOfWeek($time) {
	$dayMap = array('M','Tu','W','Th','F','Sa','Su');
	return $dayMap[date("N",$time)-1];
}

function daysOfWeekArray($daysOfWeek) {
	if($daysOfWeek == 'Every Day') return explode(',','M,Tu,W,Th,F,Sa,Su');
	else if($daysOfWeek == 'Weekdays') return explode(',','M,Tu,W,Th,F');
	else if($daysOfWeek == 'Weekends') return explode(',','Sa,Su');
	else return explode(',',$daysOfWeek);
	/*return ($daysOfWeek == 'Every Day') ? explode(',','M,Tu,W,Th,F,Sa,Su')
						: (($daysOfWeek == 'Weekdays') ? explode(',','M,Tu,W,Th,F')
						: ($daysOfWeek == 'Weekends') ? explode(',','Sa,Su')
						: explode(',',$daysOfWeek)); */
}

function postFieldTime($field) {
	return isset($_POST[$field]) && $_POST[$field] ? strtotime($_POST[$field]) : 0;
}

function saveServices($packageid, $recurring, $simulation=null) {
	//requestIsJSON() ... getJSONRequestInput()
	if($recurring && (requestIsJSON() || array_key_exists("0_services_visible", $_REQUEST))) // allow for POST[''] or JSON
		return saveServicesJSON($packageid, $recurring, $simulation=null);
	$newServices = array();
	$prefixes = $recurring ? array('') : array('first_','between_','last_');
	foreach($prefixes as $prefix) {
		$services_visible = $_REQUEST[$prefix."services_visible"];
		// save every visible service with <<<NO! a serviceid>>> or a servicecode
		for($i=1;$i<=$services_visible;$i++) {
			if($_REQUEST[$prefix."servicecode_$i"])
				$newServices[] = saveService($i, $_REQUEST["client"], $packageid, $recurring, $prefix, $simulation);
			else if($_REQUEST[$prefix."serviceid_$i"])  ;  // We do NOT save unidentified services
		}
	}
	return $newServices;
}

function saveService($number, $clientId, $packageid, $recurring, $prefix, $simulation=null) {
	global $serviceFields;
	$divFields = array('daysofweek','timeofday','pets');
	$serviceId = $_REQUEST[$prefix."service_$number"];
  $service = array('packageptr'=>$packageid, 'clientptr'=>$clientId);
  $fieldNames = array_keys($serviceFields);
  foreach($fieldNames as $field)
	  $service[$field] = $_REQUEST[$prefix.$field."_$number"];
	$service['recurring'] = $recurring ? 1 : 0;
	if(!$service['pets']) $service['pets'] = '--';
	if($prefix) $service['firstLastOrBetween'] = substr($prefix, 0, strpos($prefix, '_'));
	//if(!$service['daysofweek']) $service['daysofweek'] = '';  // non-recurring
  if(!$simulation) {
		insertTable('tblservice', $service, 1);
	  $service['serviceid'] = mysqli_insert_id();
	}
	return $service;
	// SERVICES ARE NEVER UPDATED
	//else {
	//  updateTable('tblpet', $pet, "petid=$petId", 1);
	//}
}

function nonrepeatingPackageChanges($oldPackage) {
	global $nonrepeatingPackageFields;
	$packageid = $oldPackage['packageid'];
	$changes = changedPackageFields($oldPackage, $nonrepeatingPackageFields);
	$existingServices = getPackageServices($packageid);
	$servicesToSave = 0;
	foreach(array('first_','between_','last_') as $prefix) {
		$services_visible = $_POST[$prefix."services_visible"];
		// COUNT services with NON-EMPTY service types and use that instead of $services_visible
		for($i=1;$i<=$services_visible;$i++) {
			if($_POST[$prefix."servicecode_$i"] || $_POST[$prefix."service_$i"]) {
				$servicesToSave++;
				$serviceChange = changedServiceFields($prefix, $i, $existingServices);
				if($serviceChange) $changes['serviceChanges'][] = $serviceChange;
			}
		}
	}
	if($servicesToSave > count($existingServices))
	  $changes['addedServices'] = $servicesToSave - count($existingServices);
	else if($servicesToSave < count($existingServices))
	  $changes['droppedServices'] = count($existingServices) - $servicesToSave;
	return $changes;
}
		

function irregularPackageChanges($oldPackage) {
	global $irregularPackageFields;
	$packageid = $oldPackage['packageid'];
	$changes = changedPackageFields($oldPackage, $irregularPackageFields);
	return $changes;
}
		

function repeatingPackageChanges($oldPackage) {
	global $repeatingPackageFields, $monthlyPackageFields;
	$packageid = $oldPackage['packageid'];
	$relevantFields = $oldPackage['monthly'] ? $monthlyPackageFields : $repeatingPackageFields;
	$relevantFields = array_diff($relevantFields, array('previousversionptr','current'));	
	$changes = changedPackageFields(getRecurringPackage($packageid), $relevantFields);
	$existingServices = getPackageServices($packageid);
	$services_visible = $_POST["services_visible"];
	$servicesToSave = 0;
	// COUNT services with NON-EMPTY service types and use that instead of $services_visible
  for($i=1;$i<=$services_visible;$i++) {
		if($_POST["servicecode_$i"] || $_POST["service_$i"]) {
	    $servicesToSave++;
		  $serviceChange = changedServiceFields('', $i, $existingServices);
		  if($serviceChange) $changes['serviceChanges'][] = $serviceChange;
		}
	}
	if($servicesToSave > count($existingServices))
	  $changes['addedServices'] = $servicesToSave - count($existingServices);
	else if($servicesToSave < count($existingServices))
	  $changes['droppedServices'] = count($existingServices) - $servicesToSave;
	return $changes;
}
		

function changedPackageFields($package, $fields) {
	// if service is new, return array("new"=>true);
	$changes = array();
	foreach($fields as $key) {
		if(in_array($key, array('previousversionptr','current'))) continue;
		else if(strpos($key,'date') !== false) 
		  $match = strtotime($package[$key]) == strtotime($_POST[$key]);
		else if($key == 'preemptrecurringappts') 
		  $match = $package[$key] == (isset($_POST[$key]) && $_POST[$key] ? 1 : 0);
	  else $match = ($package[$key] == $_POST[$key]);
	  if(!$match)
	    $changes[] = $key;
	}
	return $changes;
}

function changedServiceFields($prefix, $number, $services) {
	global $serviceFields;
	// if service is new, return array("new"=>true);
	$serviceId = $_POST[$prefix."service_$number"];
	if(!array_key_exists($serviceId, $services)) return array("new");
	// else fetch the service and compare the fields with $_POST
	$service = $services[$serviceId];
	foreach($serviceFields as $key => $unused) {
	  if($service[$key] != $_POST[$prefix.$key."_$number"])
	    $changes[] = $key;
		}
	return $changes;
}

function markPackageNoncurrent($packageid, $table) {
	doQuery("UPDATE $table SET current = 0 WHERE packageid = $packageid", 1);
	$recurring = $table == 'tblrecurringpackage' ? 1 : 0;
	doQuery("UPDATE tblservice SET current = 0 WHERE packageptr = $packageid AND recurring = $recurring", 1);
}

function getPackageSummaries($clients, $excludePast=null) {
	$packageSummaries = array();
	if(!$clients) return $packageSummaries;
	foreach($clients as $client) $clientIds[] = $client['clientid'];
	$clientIds = join(',',$clientIds);
	$sql = "SELECT clientptr, if(monthly, 'Monthly', 'Weekly') as kind
					FROM tblrecurringpackage WHERE current and cancellationdate is null and clientptr IN ($clientIds)";
	$packageSummaries = fetchAssociationsKeyedBy($sql, 'clientptr');
	foreach($packageSummaries as $client => $pckg) $packageSummaries[$client] = $packageSummaries[$client]['kind'];
	$packageSummaries = $packageSummaries ? $packageSummaries : array();

	$pastExclusion = $excludePast ? 'AND ((onedaypackage = 0 AND enddate >= CURDATE()) OR (onedaypackage = 1 AND startdate >= CURDATE()))' : '';
	$sql = "SELECT clientptr, count(*)
					FROM tblservicepackage WHERE current AND cancellationdate IS NULL $pastExclusion AND clientptr IN ($clientIds)
					GROUP BY clientptr";
	foreach(fetchAssociationsKeyedBy(tzAdjustedSql($sql), 'clientptr') as $client => $pckg)
		$packageSummaries[$client] = $packageSummaries[$client]  
			? $packageSummaries[$client].' and Short Term'
			: 'Short Term';
	return $packageSummaries;
}

function countCurrentIncompleteClientAppointmentsWithProvider($clientid, $provider) {
	$sql = "SELECT count(*) FROM tblappointment 
					WHERE clientptr = $clientid AND providerptr = $provider AND completed IS NULL
							AND (date > CURDATE() OR (date = CURDATE() AND starttime >= CURTIME()))";
	return fetchRow0Col0(tzAdjustedSql($sql));						
}

function countCurrentClientPackagesWithProvider($clientid, $provider) {
	return array_sum(getCountsForCurrentClientPackagesWithProvider($clientid, $provider));						
}

function getCountsForCurrentClientPackagesWithProvider($clientid, $provider) {
	$sql = "SELECT count(distinct packageptr) FROM `tblservice` 
		LEFT JOIN tblservicepackage ON packageid = packageptr
		WHERE tblservice.clientptr = $clientid AND tblservice.providerptr = $provider 
		AND recurring = 0 and tblservice.current AND enddate >= curdate()";
//echo "SQL: 		$sql<p>";exit;
	$counts['short-term'] = fetchRow0Col0($sql);
	$sql = "SELECT count(distinct packageptr) FROM `tblservice` 
		LEFT JOIN tblrecurringpackage ON packageid = packageptr
		WHERE tblservice.clientptr = $clientid AND tblservice.providerptr = $provider 
		AND recurring = 1 and (cancellationdate IS NULL OR cancellationdate >= curdate())";
	$counts['recurring'] = fetchRow0Col0($sql);
	return $counts;
}

function calculateWeeklyCharge($package) { // weekly recurring
	$dows = explodePairsLine('Mon|M||Tue|Tu||Wed|W||Thu|Th||Fri|F||Sat|Sa||Sun|Su');
	$services = getPackageServices($package['packageid']);
	foreach($services as $service) {
		$days = $service['daysofweek'];
		if($days == 'Weekends') $days = 'Sa,Su';
		else if($days == 'Weekdays') $days = 'M,Tu,W,Th,F';
		else if($days == 'Every Day') $days = 'M,Tu,W,Th,F,Sa,Su';
		foreach(explode(',',$days) as $d)
			$monthServices[$d][] = $service;
	}
	foreach($monthServices as $dow => $services)
		foreach($services as $service)
			$serviceTotal += $service['charge'] + $service['adjustment'];
	return $serviceTotal;
}


function calculateServiceCharge($client, $service, $pets, $allPets, $clientCharges=null, $standardCharges=null) {
	if($clientCharges === null)  
		$clientCharges = getClientCharges($client);
	if($standardCharges === null) $standardCharges = getStandardCharges();
	if(isset($clientCharges[$service])) $charge = $clientCharges[$service]['charge'];
	else $charge = $standardCharges[$service]['defaultcharge'];
	$charge += countAdditionalPets($pets, $allPets) * $standardCharges[$service]['extrapetcharge'];
	return $charge;
}

function countAdditionalPets($pets, $allPets) {
	if($pets == 'All Pets') $pets = $allPets;
	return max(0, count(explode(',', "$pets"))-1);
}

function NEWcalculateServiceRate($provider, $service, $pets, $allPets, $baseCharge, $providerRates=null, $standardRates=null) {
	return serviceRateExplanation($provider, $service, $pets, $allPets, $baseCharge, $providerRates, $standardRates, $returnRate=true);
}

function serviceRateExplanation($provider, $service, $pets, $allPets, $totalCharge, $providerRates=null, $standardRates=null, $returnRate=false) {
	// totalCharge should include additional pet charge, if any
	// $provider is a providerid
	// $service is the service type code
	// $providerRates are the custom rates for provider
	// $standardRates are the standard default rates for
/*
IF custom sitter rate:
	extra pet rate dollars = extra pet rate is percentage ? extra pet rate * extra pet charge : extra pet rate
	extra pet rate percentage  = extra pet rate is percentage ? extra pet rate : extra pet rate / extra pet charge
	IF custom rate is a percentage
		IF extra pet rate percentage > custom rate
			RATE =(custom rate * base charge) + (extra pet rate dollars * num extra pets))
		ELSE 
			RATE = custom rate * (base charge + (extra pet charge * num extra pets))
	ELSE if custom rate is a flat rate
		RATE = custom rate + (extra pet rate dollars * num extra pets)
ELSE IF no custom sitter rate:
	IF standard rate is a percentage
		RATE = (base charge * rate) + ((extra pet charge * extra pet rate) * num extra pets)
	ELSE if custom rate is a flat rate
		RATE = base rate + (extra pet rate * num extra pets)

*/	

	$serviceName = $service ? fetchRow0Col0("SELECT label FROM tblservicetype WHERE servicetypeid = $service")
													: 'Unspecified service';
	if($standardRates === null) $standardRates = getStandardRates();
	$standardRate = $standardRates[$service];
	$extraPetRateDollars = $standardRate['ispercentage'] 
		? $standardRate['extrapetrate'] / 100 * $standardRate['extrapetcharge']
		: $standardRate['extrapetrate'];
	$extraPetRatePercent = $standardRate['ispercentage'] 
		? $standardRate['extrapetrate']
		: ($standardRate['extrapetcharge'] == 0 ? 0 : $standardRate['extrapetrate'] / (float)$standardRate['extrapetcharge'] * 100);
	$numExtraPets = countAdditionalPets($pets, $allPets);
	$extraPetCharge = $numExtraPets * $standardRate['extrapetcharge'];
	$baseCharge = $totalCharge - $extraPetCharge;
	$explain = !$returnRate;
	if($explain) $explanation = 
		array('baseCharge' => "Base Charge: ".dollarAmount($baseCharge)
														." (".($baseCharge == $standardRate['charge'] ? 'standard' : 'custom').")",
					'extraPetChargePerPet' => "Extra pet charge per pet: ".dollarAmount($standardRate['extrapetcharge']),
					'extraPets' => "Extra pets: $numExtraPets",
					'rawNumExtraPets'=>$numExtraPets,
					'totalCharge' => "Total Charge: ".dollarAmount($totalCharge)
					);
					
	if($explain) $explanation['standardExtraPetRate'] = 
		"Standard Extra Pet Rate per pet: "
		.($standardRate['ispercentage'] ? "$extraPetRatePercent% X ".dollarAmount($standardRate['extrapetcharge']) : '')
		." = ".dollarAmount($extraPetRateDollars);
	
	if($provider) {
		if($explain) {
			if($provider > 0) $name = fetchRow0Col0("SELECT CONCAT_WS(' ', fname, lname) FROM tblprovider WHERE providerid = $provider LIMIT 1");
			$name = $name ? $name : 'Sitter';
		}
		if($providerRates === null) $providerRates = getProviderRates($provider);
		if(isset($providerRates[$service])) {
			$customRate = $providerRates[$service];
			if($customRate['ispercentage']) {
				if($explain) $explanation['rateApplied'] = 
					"$name's custom rate for $serviceName: {$customRate['rate']} X "
					.dollarAmount($baseCharge)." = ".dollarAmount($baseCharge * $customRate['rate']);
				if($extraPetRatePercent > $customRate['rate']) {
					$rate = $customRate['rate'] / 100 * $baseCharge + $extraPetRateDollars * $numExtraPets;
					if($explain) {
						$explanation['rateFinal'][] = "Custom Rate = ".dollarAmount($customRate['rate'] / 100 * $baseCharge);
						$explanation['rateFinal'][] = "Rate = Custom Rate + (Standard extra pet rate  X Number of extra pets)";
						$explanation['rateFinal'][] = "Rate = ".dollarAmount($customRate['rate'] / 100 * $baseCharge)
																						." + (".dollarAmount($extraPetRateDollars)." X $numExtraPets)";
						$explanation['rateFinal'][] = "Rate = ".dollarAmount($rate);
					}
				}
				else {
					$rate = $customRate['rate'] / 100 * ($baseCharge + $extraPetCharge);
					if($explain) {
						$explanation['rateApplied'] = 
							"$name's custom rate for $serviceName: "							.$customRate['rate'].' %';
						$explanation['customExtraPetRate'] = 
							"Custom Extra Pet Rate per pet: {$customRate['rate']}%  X ".dollarAmount($standardRate['extrapetcharge'], true, '$0')
							." = ".dollarAmount($customRate['rate'] * $standardRate['extrapetcharge'] / 100);
						$explanation['rateFinal'][] = "Rate = Custom Rate + (Custom rate percentage X Extra pets charge)";
						$explanation['rateFinal'][] = "Rate = ".dollarAmount($customRate['rate'] / 100 * $baseCharge)
																						." + (".$customRate['rate']."% X "
																						.dollarAmount($standardRate['extrapetcharge'], true, '$0').")";
						$explanation['rateFinal'][] = "Rate = ".dollarAmount($rate);
					}
					
				}
			}
			else {/*flat rate */
				$rate = $customRate['rate'] + $extraPetRateDollars * $numExtraPets;
				if($explain) $explanation['rateApplied'] = 
					"$name's custom rate for $serviceName: ".dollarAmount($customRate['rate']);
				$explanation['rateFinal'][] = "Rate = Custom Rate + Extra Pet Dollars * Num Extra Pets = ".dollarAmount($rate);
			}
		}
	}
	if(!isset($rate)) { // either no sitter specified or no sitter-specific rate 
		if($standardRate['ispercentage']) {
			$rate = $baseCharge * $standardRate['defaultrate'] / 100 + $extraPetCharge * $extraPetRatePercent / 100;

//echo "[[[".($baseCharge * $standardRate['defaultrate'] / 100)."]]]	+ 		$extraPetCharge";
			if($explain) $explanation['rateApplied'] = 
				"Standard rate for $serviceName: {$standardRate['defaultrate']}% X "
				.dollarAmount($baseCharge)
				." = ".dollarAmount($baseCharge * $standardRate['defaultrate'] / 100);
		}
		else {
			$rate =$standardRate['defaultrate'] + $extraPetRateDollars * $numExtraPets;
			if($explain) $explanation['rateApplied'] = 
				"Standard rate for $serviceName: ".dollarAmount($standardRate['defaultrate']);
		}
		if($explain) $explanation['rateFinal'][] = "Rate = Standard Rate + (Extra Pet Rate X Extra pets)";
		if($explain) $explanation['rateFinal'][] = "Rate = ".dollarAmount($rate - $extraPetRateDollars * $numExtraPets)." + ".dollarAmount($extraPetRateDollars * $numExtraPets);
		if($explain) $explanation['rateFinal'][] = "Rate = ".dollarAmount($rate);
	}
	return $explain ? $explanation : $rate;
}



function calculateServiceRate($provider, $service, $pets, $allPets, $baseCharge, $providerRates=null, $standardRates=null) {
	//if(/*staffOnlyTEST() ||TRUE || */$_SESSION['preferences']['useNewRateCalculations'])
	if(TRUE)
		return NEWcalculateServiceRate($provider, $service, $pets, $allPets, $baseCharge, $providerRates, $standardRates);
	// baseCharge should include additional pet rate, if any
	// if rate is a dollar amount (and so does not reference the charge), then we need to add in extra pet rate
	// $provider is a providerid
	if($provider) {
		if($providerRates === null) $providerRates = getProviderRates($provider);
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "service: $service<p>".print_r($providerRates, 1);exit;}			
		
		if(isset($providerRates[$service])) {
			$rate = $providerRates[$service];
//if($_SERVER['REMOTE_ADDR'] == '68.225.89.173') {echo "service: $service<br>base: $baseCharge<br>rate: ".print_r($rate);exit;}			
			
			if($rate['ispercentage']) $rate = $baseCharge * $rate['rate'] / 100;
			else $rate = $rate['rate'] + countAdditionalPets($pets, $allPets) * $rate['extrapetrate'];
		}
	}
	if(!isset($rate)) {
		if($standardRates === null) $standardRates = getStandardRates();
		$rate = $standardRates[$service];
		if($rate['ispercentage']) $rate = $baseCharge * $rate['defaultrate'] / 100;  // extrapetrate is already figured into charge
		else $rate = $rate['defaultrate'] + countAdditionalPets($pets, $allPets) * $rate['extrapetrate'];
	}
	return $rate;
}

function effectiveDate($package) {
	return validDateOrNull($package, 'effectivedate') ;
}

function validDateOrNull($arr, $key) {
	if(!$arr) return null;
	if(!$arr[$key] || strpos($arr[$key], '0000') === 0) return null;
	if(!($time = strtotime($arr[$key])))  return null;
	return shortDate($time);
}

function finalNonRecurringSchedules($clientptr, $recurring) {
	$histories = findPackageHistories($clientptr, ($recurring ? 'R' : 'N'));
	foreach($histories as $id => $hist)
		foreach($histories as $id2 => $hist2)
			if(in_array($id, $hist2) && $hist2[count($hist2)-1] != $id)
				unset($histories[$id]);

	foreach($histories as $packageid=>$hist) {
		$packs[] = getPackage($packageid);
	}
	if($packs) usort($packs, 'cmpPackStarts');
	return $packs;
}

function cmpPackStarts($a, $b) {
	$a = $a['startdate'];
	$b = $b['startdate'];
	return strcmp($a, $b);
}

function dumpCancellationNotice($package) {
	$iCancellation = strtotime($package['cancellationdate']);
	if($iCancellation <= strtotime(date('Y-m-d'))) {
		$labelColor = 'red';
		$verbModifier = '';
	}
	else {
		$labelColor = 'blue';
		$verbModifier = 'will be';
	}
	$reason = $package['cancellationreason'] ? "({$package['cancellationreason']})" : '';
	echo "<div style='display:inline;' id='uncanceldiv'>";
	echo "<span style='color:$labelColor;font-size:1.5em;'>Service $verbModifier Canceled on ".shortNaturalDate($iCancellation)." $reason</span> ";
	echoButton('', "UNCANCEL", "uncancelPackage()");
	if(staffOnlyTEST()) {
		echo "  ";
		echoButton('', "HIDE Package", "hidePackage()", '', '', false, 'STAFF USE ONLY.  Use with care.');
		//echoButton($id, $label, $onClick='', $class='', $downClass='', $noEcho=false, $title=null)
	}
	echo "</div>";
}


function deleteNRPackage($id, $descndents=false, $ancestors=false) {
	// WARNING -- USE THIS VERY CAREFULLY
	$package = getPackage($id);
	$clientid = $package['clientptr'];
	$history = findPackageIdHistory($id, $clientid, false);
	$history[] = $id;
	$history = join(',', $history);
	
	deleteTable('tblservicepackage', "packageid IN ($history)");
	$serviceIds = fetchCol0("SELECT serviceid FROM tblservice WHERE packageptr IN ($history)");
	deleteTable('tblservice', "packageptr  IN ($history)");
	$apptIds = fetchCol0("SELECT appointmentid FROM tblappointment WHERE packageptr  IN ($history)");
	if($apptIds) deleteTable('tblappointment', "appointmentid =  IN (".join(',', $apptIds).")");
	//deleteTable('tblappointment', "packageptr =  IN ($history)");
	if($apptIds && $_SESSION['discountsenabled']) deleteTable('relapptdiscount', "appointmentptr IN (".join(',', $apptIds).")");
	if($_SESSION['surchargesenabled']) {
		require_once "invoice-fns.php";
		require_once "surcharge-fns.php";
		dropSurchargesWhere("packageptr  IN ($history)");
	}
}

function dumpServiceDiscountEditor($client, $includeLabel=true, $currDiscount=null, $clientDefault=true) {
	// 	see: global $scheduleDiscount in discountAppointment.  This var is used when setting appt discounts
	require_once "discount-fns.php";
	$currDiscount = $currDiscount ? $currDiscount : ($clientDefault && $client['clientid'] ? getCurrentClientDiscount($client['clientid']) : '0');
	$discounts = array('No Discount'=>-1);
	$discountVal = -1;
	foreach(getDiscounts(1) as $row) {
		$discounts[$row['label']] = $row['discountid'].'|'.$row['memberidrequired'];
		if($row['discountid'] == $currDiscount['discountptr']) {
			$discountVal = $row['discountid'].'|'.$row['memberidrequired'];
			$memberIdDisplayMode = $row['memberidrequired'] ? 'inline' : 'none';
		}
	}
	foreach($discounts as $label => $optionVal) {
		$style = $optionVal == $discountVal ? "style='font-weight:bold;'" : '';
		$checked = $optionVal == $discountVal ? "SELECTED" : "";
		$options[] = "<OPTION value='$optionVal' $style $checked>$label</OPTION>";
	}
	$options = join("\n", $options);
	
	selectElement(($includeLabel ? 'Discount:' : ''), 'discount', $discountVal, $options, 'discountChanged(this)');
	//inputRow($label, $name, $value=null, $labelClass=null, $inputClass=null, $rowId=null,  $rowStyle=null, $onBlur=null)
	echo " <span id='memberidrow' style='display:$memberIdDisplayMode;'>";
	labeledInput('<font color="red">*</font> Member ID:', 'memberid', $currDiscount['memberid']);
	echo "</span>";
}

function getPackageAppointments($packageid, $clientid, $filter='') {
	$history = findPackageIdHistory($packageid, $clientid, false);
	$history[] = $packageid;
	$history = join(',', $history);
	$filter = $filter ? "AND $filter" : '';
	return fetchAssociations("SELECT * FROM tblappointment WHERE packageptr IN ($history) $filter ORDER BY date, starttime");
}

function dumpRecurringSchedule($schedule=null, $clientid=null, $noEdit=false) {
	if(!$schedule) {
		$schedules = getCurrentClientPackages($clientid, 'tblrecurringpackage');
		$schedule = $schedules[0];
	}
	if(!$schedule) return null;
	$services = fetchAssociations("SELECT servicecode, charge, adjustment, daysofweek FROM tblservice WHERE packageptr = {$schedule['packageid']}");
	if($schedule['monthly']) {
		$charge = $schedule['totalprice'];
		//foreach($services as $service)
		//	$serviceNames[] = getServiceName($service['servicecode']);
		$page = 'service-monthly.php';
		$conversionPage = 'service-repeating.php';
		$other = 'Regular Per-visit'; 
		$billing = 'Fixed Monthly Price';
	}
	else {
		$charge = $schedule['weeklyadjustment'] ? $schedule['weeklyadjustment'] : 0;
//echo "SELECT servicecode, charge, adjustment FROM tblservice WHERE packageptr = {$schedule['packageid']}<p>[[".print_r($services,1)."]]";	
		foreach($services as $service) {
			//$serviceNames[] = getServiceName($service['servicecode']);
			$charge += count(daysOfWeekArray($service['daysofweek']))*($service['charge'] + $service['adjustment']);
		}
		$page = 'service-repeating.php';
		$conversionPage = 'service-monthly.php';
		$other = 'Fixed Monthly Price'; 
		$billing = 'Regular Per-visit';
	}
	$charge = dollarAmount($charge);
	//sort($serviceNames);
	//$serviceNames = join(', ',array_unique($serviceNames));
	if(!$noEdit) {
		$editButton = echoButton('', 'Edit Schedule', "saveAndRedirect(\"$page?packageid={$schedule['packageid']}\")", null, null, 'noEcho');
		$staffOnlyTitle = staffOnlyTEST() ? ' Enabled for staff and select DBs (service-fns).' : '';
		if(dbTEST('sarahsits')) $editNoteButton = "<img title=\"Edit the schedule note.$staffOnlyTitle\"  src=\"art/note-edit.png\" style=\"cursor:pointer;margin-left:7px;\" onclick=\"editRecurringNote({$schedule['packageid']})\">";

	}
/* for video prep puposes only */ 
	if(!$readOnlyVisits && !$noEdit) {
		if(strpos($other,'Monthly') == FALSE || $_SESSION['preferences']['monthlyServicesPrepaid'])
			$convertButton = echoButton('', "Convert to $other", "saveAndRedirect(\"$conversionPage?convert=1&packageid={$schedule['packageid']}\")"
																, null, null, 'noEcho');
	}
	if($schedule['packageid'] && $_SESSION['staffuser'] && !$noEdit) {
		$cancelButton = $schedule['cancellationdate']
				? echoButton('', "Uncancel", 
										"this.value=\"Please wait...\";this.disabled=1;ajaxGetAndCallWith(\"cancel-recurring-schedule.php?uncancel={$schedule['packageid']}\", update, \"appointments\")",
										null, null, 'noEcho')
				: echoButton('', "Cancel", 
										"$.fn.colorbox({href:\"cancel-recurring-schedule.php?packageid={$schedule['packageid']}\", width:\"450\", height:\"300\", iframe: true, scrolling: true, opacity: \"0.3\"});"
																	, 'HotButton', 'HotButtonDown', 'noEcho');
	}
	$cancelButton = $cancelButton ? "<td>$cancelButton</td>" : '';
	$start = shortDate(strtotime($schedule['startdate']));
	$pricingInterval = $schedule['monthly'] ? 'Monthly' : 'Weekly';
	echo "<table id='recurringscheduletable'  width='100%'><tr><td>Start Date: $start</td>".
			 "<td>$editButton $editNoteButton</td>".
			 "<td>".scheduleNotificationLink($schedule['packageid'], $height=15, $width=25)."</td>".
			 "<td>Billing: <b>$billing</b></td><td>$pricingInterval Charge: $charge</td><td>$convertButton</td>$cancelButton</tr></table>";
//		     "<td>Services: <a href='$page?packageid={$schedule['packageid']}'>$serviceNames</a></td></tr>".
	recurringPackageSummary($schedule['packageid']);		     
}


function dumpStaffAnalysisLink($packageid) {
	if(!staffOnlyTEST()) return;
	echo " <a href='package-analysis.php?id=$packageid' target=analysis>Analyze</a>";
}

/* BEGIN Nonrecurring schedule list display: IMPROVED VERSION.  Speedier when schedules are numerous */
function dumpNonRecurringSchedules2($clientid, $maxnumber=20) {
$time0 = microtime(true);
	$schedules = getCurrentClientPackages($clientid, 'tblservicepackage');

//screenLog("getPackages: ".(microtime(true) - $time0));
	$schedules = array_reverse($schedules);
	if($schedules) {
		$allServiceNames = getAllServiceNamesById();
//$time = microtime(true);
}
		//  WTF?? $histories = findPackageHistories($client['clientid']);
		$histories = findPackageHistories($clientid);
$ACCELERATED = true; //mattOnlyTEST();
if($ACCELERATED) {
	foreach($schedules as $schedule) {
		$currentHistories[$schedule['packageid']] = $histories[$schedule['packageid']];
		foreach($histories[$schedule['packageid']] as $pckid)
			$allScheduleids[] = $pckid;
	}
	$allDatePacks = fetchAssociations(
		"SELECT date, packageptr, providerptr
		FROM tblappointment 
		WHERE clientptr = $clientid 
			AND canceled IS NULL 
			AND packageptr IN(".join(',', $allScheduleids).")");
	foreach($allDatePacks as $datePack) {
		foreach($currentHistories as $currid => $hist) {
			if(in_array($datePack['packageptr'], $hist)) {
				$allDates[$currid][] = $datePack['date'];
				$allSitters[$currid][] = $datePack['providerptr'];
				break;
			}
		}
	}
}
//screenLog("findPackageHistories: ".(microtime(true) - $time));
		$nrstotshow = 10;
		if($maxnumber < 0) {
			$showInParent = 1;
			$maxnumber = 999999;
			$nrstotshow = 999999;
		}
		// need to work out a mechanism to download the rest of the schedules
		$moredisplay = "style='display:{$_SESSION['tableRowDisplayMode']}'";
		$i = 0;
		$totalcount = 0;
		$nrsection = 0;
		//echo "<table width='100%'><tr><th>Dates</th><th>Services</th><th width=100>Package Price</th><th>Duration</th></tr>\n";
		foreach($schedules as $schedule) {
			if($totalcount == $maxnumber) break;
			$totalcount += 1;
			if($i == $nrstotshow) {
				$nrsection++;
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr $moredisplay class='nrservicemorelink_$nrsection'><td>".fauxLink('more...', "showNRServicesChunk($nrsection)", 1)."</td></tr>");
				$rowClasses[] = null;
				$moredisplay = "style='display:none'";
				$i = 0;
			}
			$i++;
			if($showInParent) $rowClasses[] = 'futuretask';
			else $rowClasses[] = $nrsection ? "nrs nrservicesection_$nrsection" : null;
			$services = fetchAssociations("SELECT servicecode, charge, adjustment, daysofweek FROM tblservice 
																			WHERE packageptr = {$schedule['packageid']} AND recurring = 0");
			$serviceNames = array();
			foreach($services as $service) {
				$serviceNames[] = $allServiceNames[$service['servicecode']];
				if(!$serviceNames[count($serviceNames)-1]) $serviceNames[count($serviceNames)-1] = "Service #{$service['servicecode']}";
			}
			sort($serviceNames);
			
//if($schedule['onedaypackage']) {echo "[$serviceNames] ".print_r($services);exit;}
			$title = $schedule['notes'] ? "title=\"".safeValue(truncatedLabel($schedule['notes'], 100))."\"" : '';

			if($schedule['onedaypackage']) {
				$duration = 1;
				$appts = count($services);
			}
			else {
//$time = microtime(true);
				if($ACCELERATED) {
					$appts = (array)$allDates[$schedule['packageid']]; 
					if($schedule['irregular'] == 2 /* Meeting */) {
						$sitters = array_unique((array)($allSitters[$schedule['packageid']]));
						if(!$sitters) $title = "title='No Sitters'";
						else {
							$sitters = join(', ', 
														fetchCOl0("SELECT IFNULL(nickname, CONCAT_WS(' ', fname, lname)) 
																				FROM tblprovider
																				WHERE providerid IN (".join(',', $sitters).")"));
							$title = safeValue(truncatedLabel("Staff: $sitters.".($schedule['notes'] ? " Note: {$schedule['notes']}" : ''), 120));
							$title = "title='$title'";
						}
					}
				}
				else 
					$appts = fetchCol0("SELECT date FROM tblappointment WHERE clientptr = $clientid AND canceled IS NULL AND packageptr IN(".join(',', $histories[$schedule['packageid']]).")");
//screenLog("appts: ".(microtime(true) - $time));		
//if($histories[$schedule['packageid']]) screenLog(join(',', $histories[$schedule['packageid']]).',');
				$duration = count(array_unique($appts));
				$appts = count($appts);
			}
			
			//$duration = $schedule['onedaypackage'] ? 1
			//       : fetchRow0Col0("SELECT count(distinct date) FROM tblappointment WHERE packageptr IN(".join(',', $histories[$schedule['packageid']]).")");
			$duration = "$duration day".($duration == 1 ? '' : 's');
			$appts = "$appts visit".($appts == 1 ? '' : 's');
			$charge = $schedule['packageprice'] ? dollarAmount($schedule['packageprice']) : '--';
			$serviceNames = $serviceNames ? join(', ',array_unique($serviceNames)) : ($schedule['irregular'] == 1 ? 'EZ Schedule' : 'Meeting');
			$page = $schedule['onedaypackage'] ? 'service-oneday.php' : ($schedule['irregular'] ? 'service-irregular.php' : 'service-nonrepeating.php');
			$serviceNames = 
				nonRecurringCalendarLink2($schedule['packageid'], $schedule)
				.($showInParent ? 
						" ".fauxLink($serviceNames, "editNRScheduleInParentWindow(\"{$page}?packageid={$schedule['packageid']}\")", 1)
				  :" <a href='$page?packageid={$schedule['packageid']}' $title>$serviceNames</a>");
			$start = shortDate(strtotime($schedule['startdate'])).
											($schedule['onedaypackage'] ? '' : ' - '.shortDate(strtotime($schedule['enddate'])));
											
			$status = null;
			if($schedule['cancellationdate']) {
				$status = 'Canceled effective: '.shortDate(strtotime($schedule['cancellationdate']));
			}
			else if($schedule['enddate'] < $today) $status = 'Ended '.shortDate(strtotime($schedule['enddate']));
			$status = $status ? $status : 'Active';
			
			$status .= scheduleNRNotificationLink($schedule['packageid']);
			
			if(adequateRights('#ac')) {
				$day1 = $schedule['startdate'];
				$day2 = $schedule['onedaypackage'] ? $schedule['startdate'] : $schedule['enddate'];
				if($_SESSION['preferences']['betaBilling2Enabled']) {
					$status .= "<!-- look in service-fns.php --><img width=16 height=10 src='art/email-invoice.gif' title='Email abilling statement for these dates.' style='cursor:pointer;margin-left:5px;' onclick='viewNonRecurringServicesStatement(\"$day1\", \"$day2\", {$schedule['packageid']})'>";
				}
				else {			
					$status .= "<!-- look in service-fns.php --><img width=16 height=10 src='art/email-invoice.gif' title='Email an invoice for these dates.' style='cursor:pointer;margin-left:5px;' onclick='viewServicesInvoice(\"$day1\", \"$day2\", 1)'>";
					if(/*!mattOnlyTEST()  &&*/ staffOnlyTEST() || $_SESSION['preferences']['betaBillingEnabled'] )
						$status .= "<!-- look in service-fns.php --><img width=16 height=10 src='art/email-invoice-BETA.gif' title='Email a BETA invoice for this schedule alone.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='viewNonRecurringServicesInvoice(\"$day1\", \"$day2\", {$schedule['packageid']})'>";
				}
			}
			
			if(!$_SESSION['preferences']['homeSafeSuppressed'] && (TRUE || staffOnlyTEST() || dbTEST('azcreaturecomforts'))) { // azcreaturecomforts -- ENABLED FOR ALL 10/22/2019
				$homeSafeAction = "if(openConsoleWindow) openConsoleWindow(\"homesafe\", \"comm-home-safe-composer2.php?packageptr={$schedule['packageid']}\", 650,600);";
				$house = '&#8962;'; // alt: '&#127968;'
				$house = '&#127968;'; // alt: '&#8962;'
				$status .= /* house */ 
//fauxLink($label, $onClick, $noEcho=false, $title=null, $id=null, $class=null, $style=null)				
					' '.fauxLink($house, $homeSafeAction, 'noEcho', 'Send a Home Safe Request', null, 'fontSize1_1em ', 'font-weight:bold;');
			}
			

			$data[] = array('start'=>$start,'services'=>$serviceNames,'price'=>$charge,'duration'=>"$duration / $appts", 'status'=>$status);
		}
		if($maxnumber < count($schedules)) {
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr><td>".fauxLink("View All (".count($schedules)." schedules)", 
															"openShortTermScheduleHistory($clientid)", 1)."</td></tr>");
				$rowClasses[] = null;
		}
		//echo "</table>";
	}
	dumpNRSectionStyle();
	$columns = array('start'=>'Dates','services'=>'Services','price'=>'Schedule Price','duration'=>'Duration','status'=>'Status');
	$colClasses = array('price'=>'dollaramountheader');
	echo "<div style='padding:0px' id='nonrecurringschedulesdiv'>";
	if($data)	
	  tableFrom($columns, $data, "id='nonrecurringschedulestable' width='100%'", null, 'sortableListHeader', null, 'sortableListCell', null, $rowClasses, $colClasses);
	else echo "<table id='nonrecurringschedulestable'></table>";
screenLog("dumpNonRecurringSchedules2 Total: ".(microtime(true) - $time0));
	echo "</div>";
}

function nonRecurringCalendarLink2($packageid, $package) {
	$irreg = $package['irregular'] ? 1 : '0';
	return "<img class='nrcalicon' onclick='viewNRCalendar($packageid, $irreg)'>";
}

function scheduleNRNotificationLink($packageid) {
	global $scheduleUpdatesAccepted;
	if($scheduleUpdatesAccepted)
		return " <img class='nrtinyemail' onclick='notifyUserOfScheduleChange($packageid)'>";
}



function dumpNRSectionStyle() {
?>
<style>
.nrinvicon {width:16px;height:10px;cursor:pointer;margin-left:5px;}
.nrcalicon {width:16px;height:12px;cursor:pointer;}
.nrtinyemail {width:12px;height:9px;;cursor:pointer;}
</style>
<?
}

function dumpNRSectionJS($doThisFirst='') {
	dumpUpdateNRIconsJS();
?>
// NR Section JS
$(document).ready(updateNRIcons);

<?
}

function dumpUpdateNRIconsJS() {
?>
	function updateNRIcons() {
		$('.nrcalicon').attr("src", "art/popcalendar.gif");
		$('.nrcalicon').parent().attr("title", "View these visits in a calendar.");
		$('.nrtinyemail').attr("src", "art/tiny-email-message.gif");
		$('.nrtinyemail').attr("title", "Notify client about changes");
		$('.nrinvicon.inv').attr("src", "art/email-invoice.gif");
		$('.nrinvicon.inv').attr("title", "Email an invoice for these dates.");
		$('.nrinvicon.invb').attr("src", "art/email-invoice-BETA.gif");
		$('.nrinvicon.invb').attr("title", "Email a BETA invoice for this schedule alone.");
	}
<?
}
/* END Nonrecurring schedule list display: IMPROVED VERSION.  Speedier when schedules are numerous */


/* BEGIN Nonrecurring schedule list display: First Cut.  Slow when schedules are numerous */
function dumpNonRecurringSchedules($clientid) {
	$schedules = getCurrentClientPackages($clientid, 'tblservicepackage');
	$schedules = array_reverse($schedules);
	if($schedules) {
		$allServiceNames = getAllServiceNamesById();
		$histories = findPackageHistories($clientid); // $client['clientid']);
		$nrstotshow = 10;
		$moredisplay = "style='display:{$_SESSION['tableRowDisplayMode']}'";
		$i = 0;
		$nrsection = 0;
		//echo "<table width='100%'><tr><th>Dates</th><th>Services</th><th width=100>Package Price</th><th>Duration</th></tr>\n";
		foreach($schedules as $schedule) {
			if($i == $nrstotshow) {
				$nrsection++;
				$data[] = array('#CUSTOM_ROW#'=> 
					"<tr $moredisplay class='nrservicemorelink_$nrsection'><td>".fauxLink('more...', "showNRServicesChunk($nrsection)", 1)."</td></tr>");
				$rowClasses[] = null;
				$moredisplay = "style='display:none'";
				$i = 0;
			}
			$i++;
			$rowClasses[] = $nrsection ? "nrs nrservicesection_$nrsection" : null;
			$services = fetchAssociations("SELECT servicecode, charge, adjustment, daysofweek FROM tblservice 
																			WHERE packageptr = {$schedule['packageid']} AND recurring = 0");
			$serviceNames = array();
			foreach($services as $service) {
				$serviceNames[] = $allServiceNames[$service['servicecode']];
				if(!$serviceNames[count($serviceNames)-1]) $serviceNames[count($serviceNames)-1] = "Service #{$service['servicecode']}";
			}
			sort($serviceNames);
			
//if($schedule['onedaypackage']) {echo "[$serviceNames] ".print_r($services);exit;}
			if($schedule['onedaypackage']) {
				$duration = 1;
				$appts = count($services);
			}
			else {
				$appts = fetchCol0("SELECT date FROM tblappointment WHERE canceled IS NULL AND packageptr IN(".join(',', $histories[$schedule['packageid']]).")");
				$duration = count(array_unique($appts));
				$appts = count($appts);
			}
			//$duration = $schedule['onedaypackage'] ? 1
			//       : fetchRow0Col0("SELECT count(distinct date) FROM tblappointment WHERE packageptr IN(".join(',', $histories[$schedule['packageid']]).")");
			$duration = "$duration day".($duration == 1 ? '' : 's');
			$appts = "$appts visit".($appts == 1 ? '' : 's');
			$charge = $schedule['packageprice'] ? dollarAmount($schedule['packageprice']) : '--';
			$isEZSchedule = !$serviceNames;
			$serviceNames = $serviceNames ? join(', ',array_unique($serviceNames)) : ($schedule['irregular'] == 1 ? 'EZ Schedule' : 'Meeting');
			$page = $schedule['onedaypackage'] ? 'service-oneday.php' : ($schedule['irregular'] ? 'service-irregular.php' : 'service-nonrepeating.php');
			$title = $schedule['notes'] ? "title=\"".safeValue(truncatedLabel($schedule['notes'], 100))."\"" : '';
			$editLink = "<a href='$page?packageid={$schedule['packageid']}' $title>$serviceNames</a>";
			$serviceNames = ($isEZSchedule ? '' : nonRecurringCalendarLink($schedule['packageid'], $schedule))." $editLink";
			$start = shortDate(strtotime($schedule['startdate'])).
											($schedule['onedaypackage'] ? '' : ' - '.shortDate(strtotime($schedule['enddate'])));
											
			$status = null;
			if($schedule['cancellationdate']) {
				$status = 'Canceled effective: '.shortDate(strtotime($schedule['cancellationdate']));
			}
			else if($schedule['enddate'] < $today) $status = 'Ended '.shortDate(strtotime($schedule['enddate']));
			$status = $status ? $status : 'Active';
			
			$status .= scheduleNotificationLink($schedule['packageid']);
			
			if(adequateRights('#ac')) {
				$day1 = $schedule['startdate'];
				$day2 = $schedule['onedaypackage'] ? $schedule['startdate'] : $schedule['enddate'];
				$status .= "<!-- look in service-fns.php --><img width=16 height=10 src='art/email-invoice.gif' title='Email an invoice for these dates.' style='cursor:pointer;margin-left:5px;' onclick='viewServicesInvoice(\"$day1\", \"$day2\", 1)'>";
				if(/*!mattOnlyTEST()  &&*/ staffOnlyTEST() || $_SESSION['preferences']['betaBillingEnabled'] )
					$status .= "<!-- look in service-fns.php --><img width=16 height=10 src='art/email-invoice-BETA.gif' title='Email a BETA invoice for this schedule alone.' style='cursor:pointer;margin-left:5px;;margin-right:10px;' onclick='viewNonRecurringServicesInvoice(\"$day1\", \"$day2\", {$schedule['packageid']})'>";
			}

			$data[] = array('start'=>$start,'services'=>$serviceNames,'price'=>$charge,'duration'=>"$duration / $appts", 'status'=>$status);
		}
		//echo "</table>";
	}
	$columns = array('start'=>'Dates','services'=>'Services','price'=>'Schedule Price','duration'=>'Duration','status'=>'Status');
	$colClasses = array('price'=>'dollaramountheader');
	echo "<div style='padding:0px' id='nonrecurringschedulesdiv'>";
	if($data)	
	  tableFrom($columns, $data, "id='nonrecurringschedulestable' width='100%'", null, 'sortableListHeader', null, 'sortableListCell', null, $rowClasses, $colClasses);
	else echo "<table id='nonrecurringschedulestable'></table>";
	echo "</div>";
}

function nonRecurringCalendarLink($packageid, $package) {
	$url = $package['irregular'] ? 'calendar-package-irregular.php' : 'calendar-package-nr.php';
	return fauxLink(
					"<img src='art/popcalendar.gif' width=16 height=12>",
					"openConsoleWindow(\"viewcalendar\", \"$url?packageid=$packageid\", 900, 700)",
					true,
					"View these visits in a calendar.");
}

/* END Nonrecurring schedule list display: First Cut.  Slow when schedules are numerous */


function calculateNonRecurringPackagePrice($packageid, $clientid) {
	$history = findPackageIdHistory($packageid, $clientid, false);
	$history[] = $packageid;
	$history = join(',', $history);
	$appts = fetchAssociationsKeyedBy(
		"SELECT appointmentid, charge, adjustment 
			FROM tblappointment 
			WHERE packageptr IN ($history) AND canceled IS NULL ORDER BY date, starttime",
			'appointmentid');
	foreach($appts as $appt) $price += $appt['charge']+$appt['adjustment'];
	if($appts) {
		$sql = "SELECT sum(amount) FROM relapptdiscount WHERE appointmentptr IN (".join(',', array_keys($appts)).")";
		$price -= fetchRow0Col0($sql);
	}
	$appts = fetchAssociationsKeyedBy(
		"SELECT surchargeid, charge 
			FROM tblsurcharge 
			WHERE packageptr IN ($history) AND canceled IS NULL ORDER BY date, starttime",
			'surchargeid');
	foreach($appts as $appt) $price += $appt['charge'];
	return $price;
}

function scheduleNotificationLink($packageid, $height=9, $width=12) {
	global $scheduleUpdatesAccepted;
	if($scheduleUpdatesAccepted)
		return " <img src='art/tiny-email-message.gif' title='Notify client about changes' height=$height width=$width onclick='notifyUserOfScheduleChange($packageid)'>";
}

function serviceDetails($serviceCode, $clientId=null, $provId = null) {
	$details['standard'] = fetchFirstAssoc("SELECT * FROM tblservicetype WHERE servicetypeid = $serviceCode LIMIT 1");
	if($provId) $details['provider'] = fetchFirstAssoc("SELECT * FROM relproviderrate WHERE servicetypeptr = $serviceCode  AND providerptr = $provId LIMIT 1");
	if($clientId) $details['client'] = fetchFirstAssoc("SELECT * FROM relclientcharge WHERE servicetypeptr = $serviceCode  AND clientptr = $clientId LIMIT 1");
	return $details;
}

function fetchAllAppointmentsForNRPackage($packageidOrCurrentPackage, $clientptr=null) {
	$thisPackage = is_array($packageidOrCurrentPackage) 
									? $packageidOrCurrentPackage 
									: getCurrentNRPackage($packageidOrCurrentPackage, $clientptr);
									
	if(!$thisPackage) return null; // no current version of the package!								
	if(!$clientptr) {
		$clientptr = $thisPackage['clientptr'];
	}
	
	
	if(!$thisPackage['current']) {
		$packageid = findCurrentPackageVersion($packageid, $clientptr, false);
	}
	else $packageid = $thisPackage['packageid'];
		
	$history = findPackageIdHistory($packageid, $clientptr, false);
	$history = join(',', $history);
//	echo "ID: $packageid, CLIENT: $clientptr HIST: $history";exit;
	return fetchAssociations(
		"SELECT * 
			FROM tblappointment
			WHERE packageptr IN ($history)
			ORDER BY date, starttime");
}

function fetchAllSurchargesForNRPackage($packageidOrCurrentPackage, $clientptr=null) {
	$thisPackage = is_array($packageidOrCurrentPackage) 
									? $packageidOrCurrentPackage 
									: getCurrentNRPackage($packageidOrCurrentPackage, $clientptr);
									
	if(!$thisPackage) return null; // no current version of the package!								
	if(!$clientptr) {
		$clientptr = $thisPackage['clientptr'];
	}
	
	
	if(!$thisPackage['current']) {
		$packageid = findCurrentPackageVersion($packageid, $clientptr, false);
	}
	else $packageid = $thisPackage['packageid'];
		
	$history = findPackageIdHistory($packageid, $clientptr, false);
	$history = join(',', $history);
//	echo "ID: $packageid, CLIENT: $clientptr HIST: $history";exit;
	return fetchAssociations(
		"SELECT * 
			FROM tblsurcharge
			WHERE packageptr IN ($history)
			ORDER BY date, starttime");
}

function getCurrentNRPackage($packageid, $clientptr=null) {
	$thisPackage = getNonrecurringPackage($packageid);
	if(!$clientptr) {
		$clientptr = $thisPackage['clientptr'];
	}
	if(!$thisPackage['current']) {
		$packageid = findCurrentPackageVersion($packageid, $clientptr, false);
	
		if($packageid) $thisPackage = getNonrecurringPackage($packageid);
		else $thisPackage = null;
		//$thisPackage = $packageid ? null : getNonrecurringPackage($packageid); // DID NOT WORK WITH NULL packageid!
	
	}
	return $thisPackage;
}

function labelFirstAndLastVisitsForNRPackage($packageid, $clientptr=null, $justClear=false) {
	//if(!$_SESSION['preferences']['labelFirstAndLastVisits']) return;
	$thisPackage = getCurrentNRPackage($packageid, $clientptr);
	$appts = fetchAllAppointmentsForNRPackage($thisPackage, $thisPackage['clientptr']);
	$startLabel = "[START]";
	$endLabel = "[FINISH]";
	$startLabeled = false;
	foreach($appts as $i => $appt) {
		$note = (string)$appt['note'];
		$note = str_replace($startLabel, '', $note);
		$note = trim(str_replace($endLabel, '', $note));
		if(!$justClear) {
			if($appt['date'] <= $thisPackage['enddate'])
				if($i+1 == count($appts) || $appts[$i+1]['date'] > $thisPackage['enddate'])
					// if last visit or last visit in current date range...
					$note = "$endLabel\n$note";
			if(!$startLabeled && $appt['date'] >= $thisPackage['startdate']) {
				// if first visit in current date range...
				$note = "$startLabel\n$note";
				$startLabeled = true;
			}
		}
		if($note != $appt['note']) updateTable('tblappointment', array('note'=>$note), "appointmentid = {$appt['appointmentid']}", 1);
	}
}

function scheduleHistoryLink($packageid) {
	if(!$packageid) return;
	if(staffOnlyTEST() || dbTEST('familypetsitters')) fauxLink('History', "showPackageHistory($packageid)", false, 'Show schedule change history');
}

function dumpHistoryLinkJSFrag() {
	echo <<<FRAG
function showPackageHistory(packageid) {
	$.fn.colorbox({href:"package-history.php?id="+packageid, width:"600", height:"300", scrolling: true, opacity: "0.3"});
}
FRAG;
}

// #######################################################
// MULTI-WEEK RECURRING FUNCTIONS
function multiWeeksMax() {
	return 4;
}

function weekChart($package, $starting, $numweeks, $returnAsArray=false) {
	$weekStart = $starting;
	$dayIndex = date('N', strtotime($weekStart));
	if($dayIndex < 7) $weekStart = date('Y-m-d', strtotime("- $dayIndex days", strtotime($weekStart)));
	$starting = $weekStart;
	for($i=0; $i<$numweeks; $i++) {
		$endingTime = strtotime("+ 6 days", strtotime($starting));
		$weeks[shortDate(strtotime($starting)).' - '.shortDate($endingTime)] = weekNumber($starting, $package)+1;
		$starting = date('Y-m-d', strtotime("+ 7 days", strtotime($starting)));
	}
	if($returnAsArray) return $weeks;
	$result = "Week Chart<p><table border=1>";
	foreach($weeks as $dates=>$week)
		$result .= "<tr><td>$dates</td><td>Week $week</td></tr>";
	$result .= "</tr></table>";
	return $result;
}

function weekNumber($date, $package) { // THIS IS ZERO BASED!
	if(!$package['weeks']) return null;
//print_r($package); exit;	
	$firstWeekStart = $package['firstsunday'] ? $package['firstsunday'] : $package['startdate'];
	// N - 1 (for Monday) through 7 (for Sunday)
	$dayIndex = date('N', strtotime($firstWeekStart));
	if($dayIndex < 7) $firstWeekStart = date('Y-m-d', strtotime("- $dayIndex days", strtotime($firstWeekStart)));
	$firstsundayDateTime = new DateTime($firstWeekStart);
	$time = is_string($date) ? $date : date('Y-m-d', $date);
	$thisDateTime = new DateTime($time);
	$dateDiff =  $thisDateTime->diff($firstsundayDateTime)->format("%a");
	return ((int)($dateDiff / 7)) % $package['weeks'];
}

function saveServicesJSON($packageid, $recurring) {
	//requestIsJSON() ... getJSONRequestInput()
	$formData = requestIsJSON() ? getJSONRequestInput() : $formData;  // MOST LIKELY, requestIsJSON = true
	$newServices = array();
	$prefixes = $recurring ? array('') : array('first_','between_','last_');
	
	foreach($formData['services'] as $service) {
		$newServices[] = saveRecurringServiceJSON($formData["client"], $packageid, $service, $simulation=null);
	}
	return $newServices;
	/* format:
	{	client: "",
		prepaid: "",
		monthly: "",
		weeks: "",
		firstsunday: "",
		startdate: "",
		effectivedate: "",
		services: [
			{week: "", daysofweek:"", providerptr: "", timeofday: "", servicecode: "", pets: "", charge: "", adjustment: "", 
				rate: "", bonus: ""}
			{week: "", daysofweek:"", providerptr: "", timeofday: "", servicecode: "", pets: "", charge: "", adjustment: "", 
				rate: "", bonus: ""}
		]
		...
	}
	*/
}

function saveRecurringServiceJSON($clientId, $packageid, $service, $simulation=null) {
	global $serviceFields;
  $service['packageptr'] = $packageid;
  $service['clientptr'] = $clientId;
	$service['recurring'] = $recurring ? 1 : 0;
	if(!$service['pets']) $service['pets'] = '--';
  if(!$simulation) {
		insertTable('tblservice', $service, 1);
	  $service['serviceid'] = mysqli_insert_id();
	}
	return $service;
}


function recurringServiceTableV2($package, &$services, &$activeProviders, &$serviceSelections, $daysofweekrequired=true, $prefix=null) {
	global $serviceLineLabel;
	$weeks = multiWeeksMax();
	$visibleWeeks = $package['weeks'] ? $package['weeks'] : 1;
	$dowLabel = $daysofweekrequired ? 'Days of Week,' : '';
	$colHeads = explode(',',$dowLabel."Time of Day,Service Type,Sitter,Pets,Charge,Adjust,Rate,Bonus,");
  $onState = 	$_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';
	$chargeRateStyle = $_SESSION['preferences']['showChargeAndRateInScheduleEditorsByDefault'] ? $onState : "style='display:none'";

	
	foreach($services as $service) {
		$week = $service['week'] ? $service['week'] : '0'; 
		$servicesByWeek[$week][] = $service;
	}
//echo "[[".print_r($servicesByWeek, 1)."]]";	
	$services = null;

	hiddenElement('multiweek', 1);
	echo "\n\n<table border=0>\n";
	for($w=0; $w < $weeks; $w++) {
		$weekClass = "week_$w";
		$prefix = "{$w}_";
		$weekVisibility = $w >= $visibleWeeks;
		$services = $servicesByWeek[$w];
		$visibleSections = max(($w == 0 ? 1 : 0), count($services));
		hiddenElement("{$w}_services_visible", $visibleSections);
		echo "\n<tr><td colspan=12 style='border-bottom:solid #dddddd 1px;padding-top:20px;'></td></tr>";
		echo "\n<tr class='$weekClass' id='weekOneLabelRow'><th colspan=2 class='fontSize1_2em' style='padding-top:7px;'>Week ".($w+1)."</th>";
		
		
		// Add Service, for when NO services are visible
		$initialDisplay = $visibleSections ? 'none' : $_SESSION['tableRowDisplayMode'];
		echo "\n<tr class= '$weekClass' id='$prefix"."addAnother_0' style='display:$initialDisplay;'><td colspan=9 style='padding-top:3px;'>\n";
		echoButton(null, "Add a $serviceLineLabel", "addAnotherButtonAction(0, \"$prefix\")");
		echo "</td></tr>";
		
		echo "\n<tr class='$weekClass' id='{$prefix}headers'><th>&nbsp;</th>";
		foreach($colHeads as $label)
			if(in_array($label, array('Charge','Adjust','Rate','Bonus'))) 
				echo "<th id='".$prefix."$label"."_header' style='display:$chargeRateStyle'>$label</th>";
				else echo "<th>$label</th>";
		echo '</tr>';
		// add a section for each line + five extra sections
		for($i=1; $i <= count($services)+5; $i++) {
			if($i <= count($services)) {
				$service = current($services);
				next($services); 
			}
			else $service = array('active'=>1);
	//echo '['.print_r($service, 1).']';	exit;
			$addAnother = $i < count($services)+5 ? 'button' : 'final';
	//echo "<tr><td colspan=5>i: $i Num Services: ".count($services)." - addAnother: ".($i >= count($services) && $i < count($services)+5)."<tr>";	
			addServiceLineV2($w, $i, $weekClass, $service, $visibleSections, $addAnother, $activeProviders, $serviceSelections, $daysofweekrequired, $prefix);
		}
		echo "<tr class='$weekClass'><td colspan=9><div id='{$prefix}weeklytotals' style='display:inline;'></div></td></tr>";
		
	}
	if(!$daysofweekrequired) echo "\n</table> <!-- services -->\n";
}

function addServiceLineV2($week, $number, $weekClass, $service, $visibleSections, $addAnother=false, &$activeProviders, &$serviceSelections, $daysofweekrequired=true, $prefix='') {
	global $serviceFields, $serviceLineLabel, $clientDetails, $serviceLineFields, $serviceLineConstraints, $allPetNames;
	$defaultTimeFrame = isset($_SESSION['preferences']['defaultTimeFrame']) ? $_SESSION['preferences']['defaultTimeFrame'] : '';
//echo ($defaultTimeFrame);exit;
	$hidden = $number > $visibleSections;
	$initialDisplay = $hidden ? 'none' : $_SESSION['tableRowDisplayMode'];
	echo "\n"; hiddenElement($prefix."service_$week_$number", $service['serviceid']);
	echo "\n<tr class= '$weekClass' id='$prefix"."service_row_$week_$number' style='display:$initialDisplay'>\n";
	echo "<td style='width:22px;padding-right:0px;' title='Delete this line.'>".
	      "<img src='art/delete.gif' height=22 width=22 border=0 onClick=\"deleteLine('$prefix','$week_$number')\"></td>\n";
	if($daysofweekrequired) {
		echo "\n<td style='padding:2px;width:100px;'>";
	  buttonDiv($prefix."div_daysofweek_$number",$prefix."daysofweek_$week_$number","showWeekdayGridInContentDiv(event, \"".$prefix."div_daysofweek_$week_$number\")",
	          ($service['daysofweek'] ? $service['daysofweek'] : ''));
	  echo "</td>";
	}
	echo "\n<td style='padding:2px;width:110px;'>";
	buttonDiv($prefix."div_timeofday_$week_$number", $prefix."timeofday_$week_$number", "showTimeFramerInContentDiv(event, \"".$prefix."div_timeofday_$week_$number\")",
	          ($service['timeofday'] ? $service['timeofday'] : $defaultTimeFrame));
	echo "</td>";
	
	echo "<td>";
	$maxLen = 0;
	foreach($serviceSelections as $label => $code) $maxLen = max(strlen($label), $maxLen);
	$servSelectClass = $maxLen > 35 ? 'fontSize0_9em' : 'standardInput';
	selectElement('', $prefix."servicecode_$week_$number", $service['servicecode'], $serviceSelections, "updateServiceVals(this, \"$week_$number\", \"$prefix\")", null, $servSelectClass);
	echo "</td>";
	
	echo "<td>";
	$activeProviders = array_merge(array('--Unassigned--' => 0), $activeProviders);
		//echo "[".$service['providerptr']."]";exit;  providerInArray($clientDetails['defaultproviderptr'], $activeProviders)
	if(!$service['providerptr'] && !providerInArray($clientDetails['defaultproviderptr'], $activeProviders)) {
		$deadProvider = providerShortName(getProvider($clientDetails['defaultproviderptr']));
	  $deadProviderVisibility = 'display:inline;';
	  $showIt = 'X';
	}
	else {
		$deadProviderVisibility = 'display:none;';
		$showIt = '';
	}
	echo "<label id='".$prefix."providerwarning_$week_$number' style='font-weight: bold;font-size: 1.1em;color:red;$deadProviderVisibility' title='Default sitter $deadProvider is inactive!'>$showIt</label>";
	$servprovider = $service['providerptr'];// ? $service['providerptr'] : ($clientDetails['defaultproviderptr'] ? $clientDetails['defaultproviderptr'] : '');
	if(!$servprovider && !$service['serviceid']) $servprovider = $clientDetails['defaultproviderptr'] ? $clientDetails['defaultproviderptr'] : 0;
  selectElement('', $prefix."providerptr_$week_$number", $servprovider, $activeProviders, "providerChanged(this, \"$week_$number\", \"$prefix\")");
	echo "</td>";
	echo "\n<td style='padding:2px;'>";
	$defaultPet = !$allPetNames || strpos($allPetNames,',') ? 'All Pets' : $allPetNames; // if 1 pet, use that pet
	$petsTitle = getActiveClientPetsTip($clientDetails['clientid'], $service['pets']);
	buttonDiv($prefix."div_pets_$week_$number", $prefix."pets_$week_$number", "showPetGridInContentDiv(event, \"".$prefix."div_pets_$week_$number\")",
	           ($service['pets'] ? $service['pets'] : $defaultPet), '', '', $petsTitle);
	echo "</td>";

  $onBlur = "onBlur='displayTotals()'";
  
	$surchargeNoteId = $prefix."surchargenote_$week_$number";
	hiddenElement($surchargeNoteId, $service['surchargenote']);
  $onChange = "onChange='editSurcharge(\"$surchargeNoteId\", 0)'";
  
  $surchargeButton = "<img style='cursor:pointer;' onClick='editSurcharge(\"$surchargeNoteId\", 1)' width=15 height=15 src='art/surcharge-button.gif'>";

  $openChargeRateButtonId = $prefix."cell_ChargeRate_$week_$number";
  
  $onState = 	$_SESSION['tableRowDisplayMode'] == 'table-row' ? 'table-cell' : 'block';

  $chargeRateStyle = $_SESSION['preferences']['showChargeAndRateInScheduleEditorsByDefault'] ? $onState : "style='display:none'";
	echo "<td id='$openChargeRateButtonId"."_charge' $chargeRateStyle><input id='".$prefix."charge_$week_$number' type='hidden' name='".$prefix."charge_$week_$number' size=2 value='{$service['charge']}'>
	          <div id='".$prefix."div_charge"."_$week_$number'>{$service['charge']}</div></td>
	      <td id='$openChargeRateButtonId"."_adj' $chargeRateStyle><input name='".$prefix."adjustment_$week_$number' id='".$prefix."adjustment_$week_$number' size=2 value='{$service['adjustment']}' $onChange $onBlur autocomplete='off'></td>
	      <td id='$openChargeRateButtonId"."_rate' $chargeRateStyle><input id='".$prefix."rate_$week_$number' type='hidden' name='".$prefix."rate_$week_$number' size=2 value='{$service['rate']}'>
	          <div id='".$prefix."div_rate_$week_$number'>{$service['rate']}</div></td>
	      <td id='$openChargeRateButtonId"."_bonus' $chargeRateStyle><input name='".$prefix."bonus_$week_$number' id='".$prefix."bonus_$week_$number' size=2 value='{$service['bonus']}' $onChange $onBlur autocomplete='off'></td>
	      <td  id='$openChargeRateButtonId"."_surcharge' $chargeRateStyle>$surchargeButton</td>";
	$chargeButtonTitle = "Charge: \${$service['charge']}"
												. ($service['adjustment'] ? "+\${$service['adjustment']}" : '')
												. " ~ Rate: \${$service['rate']}"
												. ($service['bonus'] ? "+\${$service['bonus']}" : '')
												;
	$chargeButtonTitle = "title='$chargeButtonTitle'";
  $openChargeRateButton = "<img id='$openChargeRateButtonId"."_button' style='cursor:pointer;' onClick='toggleCharges(\"$openChargeRateButtonId\", 1)' width=15 height=15 src='art/fatdollarsign.gif' $chargeButtonTitle>";
	echo "<td id='$openChargeRateButtonId'>$openChargeRateButton</td>";
//     
	$serviceLineConstraints .= !$serviceLineConstraints ? '[[' : "],\n[";
	$first = true;
	foreach(array('bonus','adjustment',/*'providerptr',*/'daysofweek','timeofday') as $field) {
		if(!$daysofweekrequired && ($field == 'daysofweek')) continue
		$serviceLineFields .= $serviceLineFields ? ',' : '';
		$serviceLineFields .= $field."_$week_$number,{$serviceFields[$field]},";
		$serviceLineConstraints .= $first ? "'$prefix"."servicecode_$week_$number', " :',';
		$first = false;
		if(in_array($field, array('bonus','adjustment')))
	    $serviceLineConstraints .= "'$prefix"."$field"."_$week_$number','','FLOAT'\n";
	  else if($field == 'providerptr')
			$serviceLineConstraints .= "'$prefix"."$field"."_$week_$number','','R'\n";
	  else if($field == 'daysofweek')
			$serviceLineConstraints .= "'$prefix"."$field"."_$week_$number','','R'\n";
	  else if($field == 'timeofday')
			$serviceLineConstraints .= "'$prefix"."$field"."_$week_$number','','R'\n";
	  else if($field == 'pets')
			$serviceLineConstraints .= "'$prefix"."$field"."_$week_$number','','R'\n";
	}

  echo "</tr>";
  if(!in_array($service['servicecode'], $serviceSelections)) {
		$allServiceNames = getAllServiceNamesById();
		$nblank = $daysofweekrequired ? 3 : 2;
		echo "<tr class= '$weekClass'><td colspan=$nblank style='padding-top:0px;'>&nbsp;</td><td colspan=3 class='tiplooks' style='text-align:left;padding-top:0px;'>{$allServiceNames[$service['servicecode']]}</td";
	}
	if($addAnother == 'button') {
		$next = $number+1;
		$initialDisplay = $number < $visibleSections || $hidden ? 'none' : $_SESSION['tableRowDisplayMode'];
		echo "\n<tr class= '$weekClass' id='$prefix"."addAnother_$number' style='display:$initialDisplay;'><td colspan=9 style='padding-top:3px;'>\n";
		echoButton(null, "Add another $serviceLineLabel", "addAnotherButtonAction($number, \"$prefix\")");
		//echo "<span class='tiplooks'> To drop a $serviceLineLabel, simply leave its Service Type field blank.</span>";
    echo "</td></tr>";
	}
	else if($addAnother == 'final')
	  echo "\n<tr class= '$weekClass' id='$prefix"."addAnother_$number' style='display:$initialDisplay;'>
	    <td colspan=9 class='tiplooks'>To add more services, please Save Changes first and reopen this editor.</td></tr>";
}


// END MULTI-WEEK RECURRING FUNCTIONS
// #######################################################
	
/*

function discountChanged(el) {
	var displayMode = el.selectedIndex == 0
											|| el.options[el.selectedIndex].value.split('|')[1] == 0
										? 'none'
										: 'inline';
	document.getElementById('memberidrow').style.display = displayMode;
}
*/

/*
TO DO: 
Saving a Recurring Schedule:
0. Assess changes to the schedule and its line items.  If only change is to: suspension, cancellation, notes, start, weekly adjustment the follow "Apply Suspension" procedure.
1. findUnassignableDates
2. Collect any incomplete, uncanceled custom appointments for the current schedule
3. Delete all future incomplete, non-custom, uncanceled appointments associated with this schedule
4. Mark the existing schedule and its line items as non-current
5. Create a new schedule which refers to the old schedule as its previous version
6. Create new line items for the new schedule
7. Create new appointments for the line items (see Create Appt, below)
8. Find all appointments from other schedules that overlap this schedule's appointments in date and timeframe (time conflicts)
9. Show user a list of appointments that are:
  a. new and unsassigned
  b. old and present time conflicts
  c. old and custom
10. Allow user to mark any appointment for deletion


Create Appt(recurring, date, task)
1. if recurring
     if(date in suspended period) quit
     foreach(nonrecurringschedules)
       if (preemptrecurringappts and newappt date in nonrecurringschedule daterange) quit
       
Apply Suspension    
1. Collect any incomplete, uncanceled custom appointments for the current schedule that fall in the suspension interval or on/after cancellation
2. If any are found, display a list of appointments for the user to delete, or edit, or leave in place
3. If user does not complete step 2, do not update schedule
4. Delete all incomplete future appointments associated with this schedule, except for those marked for retention in step 2.
5. Calculate restoreDates = oldSuspensionInterval - newSuspensionInterval and truncate by cancellationDate
5. Create missing appointments..
6. update existing schedule

Avoiding Appointment Churn:

A Recurring package may be updated if:
a. Only changes made are Suspension Interval, Cancellation Date/Reason, Weekly Adjustment, or Notes have changed AND
b. No changes to Services have occurred.

*/

