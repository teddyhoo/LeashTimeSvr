<? // reports-visit-distribution.php
//plot manager logins for a business 
require_once "common/init_session.php";
require_once "common/init_db_common.php";

extract($_GET);

$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE bizid=$bizid LIMIT 1");
//$biz = fetchFirstAssoc("SELECT * FROM tblpetbiz WHERE db='savinggrace' LIMIT 1");

$mgrloginids = fetchCol0("SELECT loginid 
														FROM tbluser 
														WHERE bizptr = $bizid 
															AND ltstaffuserid = 0 
															AND (rights like 'o-%'  OR rights like 'd-%')");
if(!$mgrloginids) {
	echo "No managers found for {$biz['bizName']}";
	exit;
}
$mgrlogins = fetchKeyValuePairs("SELECT UNIX_TIMESTAMP(substring(LastUpdateDate, 1, 10))*1000 as ltime, count(*) 
																	FROM tbllogin 
																	WHERE success 
																			AND LastUpdateDate >= '$start' AND LastUpdateDate <= '$end'
																			AND loginid IN ('".join("','", $mgrloginids)."')
																	GROUP BY ltime
																	ORDER BY ltime");
if(!$mgrlogins) {
	echo "No logins found for {$biz['bizName']} between $start and $end";
	exit;
}

$data = array();
foreach($mgrlogins as $time => $count) $data[] = "[$time, $count]";
$data = "[".join(', ', $data)."]";
$title = $biz['bizname']." Logins";
$start = date('F j, Y', strtotime($start));
$end = date('F j, Y', strtotime($end));
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
 <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title><?= $title ?></title>
		<link rel="stylesheet" href="style.css" type="text/css" /> 
    <!--[if lte IE 8]><script language="javascript" type="text/javascript" src="../excanvas.min.js"></script><![endif]-->
    <script language="javascript" type="text/javascript" src="jquery_1.3.2_jquery.min.js"></script>
    <script language="javascript" type="text/javascript" src="jquery.flot.js"></script>

    <script language="javascript" type="text/javascript" src="jquery.flot.selection.js"></script>
 </head>
    <body style='background:white;padding:10px;'>
    <h1><?= $title ?></h1>
    <h2><?= "$start - $end" ?></h2>

    <div id="placeholder" style="width:600px;height:300px;"></div>

    <p>Manager and dispatcher logins per day. Weekends are colored. Try zooming.
      The plot below shows an overview.</p>

    <div id="overview" style="margin-left:50px;margin-top:20px;width:400px;height:50px"></div>

<script id="source">
$(function () {
    var d = <?= $data ?>;

    // first correct the timestamps - they are recorded as the daily
    // midnights in UTC+0100, but Flot always displays dates in UTC
    // so we have to add one hour to hit the midnights in the plot
    for (var i = 0; i < d.length; ++i)
      d[i][0] += -5 * 60 * 60 * 1000;

    // helper for returning the weekends in a period
    function weekendAreas(axes) {
        var markings = [];
        var d = new Date(axes.xaxis.min);
        // go to the first Saturday
        d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7))
        d.setUTCSeconds(0);
        d.setUTCMinutes(0);
        d.setUTCHours(0);
        var i = d.getTime();
        do {
            // when we don't set yaxis, the rectangle automatically
            // extends to infinity upwards and downwards
            markings.push({ xaxis: { from: i, to: i + 2 * 24 * 60 * 60 * 1000 } });
            i += 7 * 24 * 60 * 60 * 1000;
        } while (i < axes.xaxis.max);

        return markings;
    }
    
    var options = {
        xaxis: { mode: "time", tickLength: 5 },
        points: { show: true },
        lines: { show: true },
        selection: { mode: "x" },
        grid: { markings: weekendAreas }
    };
    
    var plot = $.plot($("#placeholder"), [d], options);
    
    var overview = $.plot($("#overview"), [d], {
        series: {
            lines: { show: true, lineWidth: 1 },
            shadowSize: 0
        },
        xaxis: { ticks: [], mode: "time" },
        yaxis: { ticks: [], min: 0, autoscaleMargin: 0.1 },
        selection: { mode: "x" }
    });

    // now connect the two
    
    $("#placeholder").bind("plotselected", function (event, ranges) {
        // do the zooming
        plot = $.plot($("#placeholder"), [d],
                      $.extend(true, {}, options, {
                          xaxis: { min: ranges.xaxis.from, max: ranges.xaxis.to }
                      }));

        // don't fire event on the overview to prevent eternal loop
        overview.setSelection(ranges, true);
    });
    
    $("#overview").bind("plotselected", function (event, ranges) {
        plot.setSelection(ranges);
    });
});
</script>

 </body>
</html>
