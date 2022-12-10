<? // schedule-plan-fns.php

function getGlobalSchedulePlanSelectOptions($includeNull=true, $nullLabel='') {
	$pairs = fetchKeyValuePairs("SELECT name, planid FROM tblscheduleplan");
	if($includeNull) $pairs = array_merge(array($nullLabel => 0), $pairs);
	return $pairs;
}

function schedulePlanServiceTabs(&$services, &$activeProviders, &$serviceSelections/*, $daysofweekrequired=true*/) {
	// set up three tabs, each with a service table
	$labelAndIds = array("firstday"=>'First Day', "daysinbetween"=>'Days in between', "lastday"=>'Last Day', 'notes'=>'Notes');
	$initialSelection = $tab ? $tab : 'firstday';
	$boxHeight = 100;

	startTabBox("100%", $labelAndIds, $initialSelection, 120);
	
	startFixedHeightTabPage('firstday', $initialSelection, $labelAndIds, $boxHeight);
	schedulePlanServiceTable($services, $activeProviders, $serviceSelections, 'first');
	endTabPage('firstday', $labelAndIds, null, null, null, true);
	
	startFixedHeightTabPage('daysinbetween', $initialSelection, $labelAndIds, $boxHeight);
	echo "<center>";
	echoButton('','Copy the First Day Visits Here','copyServices("first_", "between_")');
	echo "</center>";
	schedulePlanServiceTable($services, $activeProviders, $serviceSelections, "between");
	endTabPage('daysinbetween', $labelAndIds, null, null, null, true);
	
	startFixedHeightTabPage('lastday', $initialSelection, $labelAndIds, $boxHeight);
	echo "<center>";
	echoButton('','Copy the First Day Visits Here','copyServices("first_", "last_")');
	echo " ";
	echoButton('','Copy the In Between Day Visits Here','copyServices("between_", "last_")');
	echo "</center>";
	schedulePlanServiceTable($services, $activeProviders, $serviceSelections, 'last');
	endTabPage('lastday', $labelAndIds, null, null, null, true);

	startFixedHeightTabPage('notes', $initialSelection, $labelAndIds, $boxHeight);
	schedulePlanNotesTab();
	endTabPage('notes', $labelAndIds, null, null, null, true);
	
	
	endTabBox();
}

function schedulePlanNotesTab() {
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
	

function schedulePlanServiceTable(&$allServices, &$activeProviders, &$serviceSelections, $firstLastOrBetween) {
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
	foreach($colHeads as $label)
		if(in_array($label, array('Charge','Adjust','Rate','Bonus'))) 
			echo "<th id='".$prefix."$label"."_header' style='display:none'>$label</th>";
			else echo "<th>$label</th>";
	echo '</tr>';

	$visibleSections = max(1, count($services));
	hiddenElement($prefix."services_visible", $visibleSections);

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
}

function getPlanServices($planid) {
	return fetchAssociationsKeyedBy("SELECT * FROM tblscheduleplanservice WHERE planptr=$planid", 'serviceid');
}

