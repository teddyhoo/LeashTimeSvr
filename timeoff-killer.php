<? // timeoff-killer.php
// Deletes a timeoff instance or a timeoff pattern
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "request-fns.php";
require_once "provider-fns.php";


locked('o-');
extract(extractVars('pattern,instance', $_REQUEST));
echo "pattern: $pattern, instance: $instance";
}
if($pattern) {
	$doomed = fetchFirstAssoc("SELECT * FROM tbltimeoffpattern WHERE patternid = $pattern LIMIT 1", 1);
	if($doomed) {
		deleteTable('tbltimeoffinstance', "patternptr = {$doomed['patternid']}", 1);
		deleteTable('tbltimeoffpattern', "patternid = {$doomed['patternid']}", 1);
		unwipeAppointments($doomed['providerptr']);
		$descr = changeLogStart($doomed).'|'.patternDescription($doomed, $full=true);
		logChange($doomed['patternid'], 'tbltimeoffpattern', 'd', $descr);
	}
}
else if($instance) {
	$doomed = fetchFirstAssoc("SELECT * FROM tbltimeoffinstance WHERE timeoffid = $instance LIMIT 1", 1);
	deleteTable('tbltimeoffinstance', "timeoffid = $instance", 1);
	unwipeAppointments($doomed['providerptr']);
	logChange($instance, 'tbltimeoffinstance', 'd', changeLogStart($doomed));
}

function changeLogStart($changedObj) {
	return $changedObj['providerptr']
				.'|'.$changedObj['date']
				.'|'.($changedObj['timeofday'] ? "{$changedObj['timeofday']}" : "[All Day]");
}

