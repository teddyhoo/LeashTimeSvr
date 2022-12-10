<? // chartist-box.php
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "year-over-year-fns.php";

// echo a chartist.js chart
// stats arg should be:
// 		yoy-visits-ytd-month - returns double-bar visit counts for year-over-year, year to date, by month 
//			ex: https://leashtime.com/year-over-year-ajax.php?stats=yoy-visits-ytd-month
// 		yoy-visits-ytd-month and baseYear - returns double-bar visit counts for year-over-year, year to date, by month 
//			ex: https://leashtime.com/year-over-year-ajax.php?stats=yoy-visits-ytd-month&baseYear=2015

// Determine access privs
$locked = locked('#vr');
extract(extractVars('chart,width,height,spec,baseYear,title', $_REQUEST));
/*
var options = {
  width: 300,
  height: 200
};
*/
$options = array('width'=>$width, 'height'=>$height);
if($chart == 'yoy-visits-ytd-month') {
	/*
	var data = {
		labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
			series: [
			[5, 4, 3, 7, 5, 10, 3, 4, 8, 10, 6, 8],
			[3, 2, 9, 5, 4, 6, 4, 6, 7, 8, 7, 4]
		]
	};
	*/
	$spec = 'wholeMonths=1';
	$yearLabel = $baseYear ? $baseYear : date('Y'); 
	$data['series'][] = 
		array('name'=>$yearLabel-1, 'data'=> (array)yearToDateMonthlyVisitCounts($lastYear=true, $baseYear, $spec));
	$data['series'][] = 
		array('name'=>$yearLabel, 'data'=>	(array)yearToDateMonthlyVisitCounts($lastYear=false, $baseYear, $spec));
	$data['labels'] = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	completeSeriesDataKeys($data['series'][0]['data'], $data['series'][1]['data']);
	$type = 'Bar';
}
else if($chart == 'yoy-rev-ytd-month') {
	$spec = 'wholeMonths=1';
	$yearLabel = $baseYear ? $baseYear : date('Y'); 
	$data['series'][] = 
		array('name'=>$yearLabel-1, 'data'=> (array)yearToDateMonthlyRevenue($lastYear=true, $baseYear, $spec));
	$data['series'][] = 
		array('name'=>$yearLabel, 'data'=> (array)yearToDateMonthlyRevenue($lastYear=false, $baseYear, $spec));
	$data['labels'] = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	completeSeriesDataKeys($data['series'][0]['data'], $data['series'][1]['data']);
	$type = 'Bar';
}
else if(strpos($chart, 'col') === 0) { // e.g., "col|$sectionKey|$globalDays|$start|$yearLabel|$label" = "col|visits|30|2016-11-30|lastyear|sitterrev"
	$parts = explode('|', $chart);
	$sectionKey = $parts[1];
	$days = $parts[2];
	$start = $parts[3];
	$yearLabel = $parts[4];
	$label = $parts[5];
	$lastYear = $yearLabel == 'lastyear';
	compileStats($yearLabel, $start, $days, $lastYear);
	
	if(strpos($sectionKey, 'itter') !== FALSE) {
		require_once "provider-fns.php";
		$allLabels = getProviderNames();
		$allLabels[] = 'Unassigned';
	}
	else if(strpos($sectionKey, 'ervice') !== FALSE) {
		$allLabels = fetchKeyValuePairs("SELECT servicetypeid, label FROM tblservicetype");
	}
	else if(strpos($sectionKey, 'ip') !== FALSE) {
		foreach(array_keys($stats[$yearLabel][$sectionKey]) as $z) 
			$allLabels[$z] = $z;
		$allLabels[] = 'No ZIP';
	}

	$labels = array();
	foreach($stats[$yearLabel][$sectionKey] as $id => $val) {
		$vals[] = $val;
		$labels[] = $allLabels[$id];
	}
	
	$data['labels'] = $labels;
	//$data['series'][] = $vals;
	for($i=0; $i<count($vals); $i++) $data['series'][] = array('data'=>array($vals[$i]), 'name'=>$labels[$i]);
	//	array('data'=>$vals);
//echo "LABELS: [".join(', ', $stats[$yearLabel][$sectionKey])."]<p>";
	$type = 'Bar';
}

if($title) echo "<h2>$title</h2>";
chartDoc($data, $options, $type);
function chartDoc($data, $options, $type) {
	if(is_array($data)) $data = json_encode($data);
	if(is_array($options)) $options = json_encode($options);

	echo <<<HTML
<html>
  <head>
    <!-- link rel="stylesheet" href="./chartist/chartist.min.css" -->
    <link rel="stylesheet" href="./chartist/chartist2.css">
  </head>
  <body>
    <div class="ct-chart ct-perfect-fourth"></div>
    <script src="./chartist/chartist.min.js"></script>
    <!-- script src="./chartist/chartist.js"></script -->
    <script src="./chartist/chartist-plugin-legend.js"></script>
    <script>
		var data = $data;

		// As options we currently only set a static size of 300x200 px. We can also omit this and use aspect ratio containers
		// as you saw in the previous example
		var options = $options;
		options.plugins = [Chartist.plugins.legend()];

		// Create a new line chart object where as first parameter we pass in a selector
		// that is resolving to our chart container element. The Second parameter
		// is the actual data object. As a third parameter we pass in our custom options.
		new Chartist.$type('.ct-chart', data, options);
		</script>
  </body>
</html>
HTML;
}