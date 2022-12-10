<? // performance-filter.php
// opened in a light box
require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "appointment-fns.php";
require_once "gui-fns.php";

require_once "frame-bannerless.php";

$filter = $_REQUEST['filter'];
if($filter) $filter = json_decode($filter, 'assoc');
$helpButton = " <img src='art/help.jpg' onclick='showHelp()' height='20' width='20'>";
?>
<h2>Filter the Visits Shown on This Page <?= $helpButton ?></h2>
<style>
td,th, div {font-size: 1.2em;}
.helpContent td,th, div {font-size: 1.0em;}
.chosenoption {width: 25px; height: 25px; border:solid grey 0px;}
.unchosenoption {width: 15px; height: 15px;}
.groupdiv {vertical-align:center;height:30px;margin:3px;background:#E9B05F;padding:5px;}
</style>
<?
/*$filter = array(
	'arrived'=> arrived/notarrived/both|early,ontime,laggy,late,all|away,nodata,normal,all
	'completed'=> completed/notcompleted/both|early,ontime,laggy,late,all|away,nodata,normal,all
	'short'=> short/normal/all*/
	
/* arrivalmode, arrivaltime, arrivalloc	*/

function picButton($pic, $group, $id, $selected, $action, $title, $echo=false) {
	$selected = $selected ? 'chosenoption' : 'unchosenoption';
	$img = "\n<img src='$pic' id='$id' group='$group' class='$group $selected' onclick='$action' title='$title'>";
	if($echo) echo $img;
	else return $img;
}

	
$labels = "arrived|Arrived||notarrived|Not Arrived||both|Both"
					."||early|Early||ontime|On Time||laggy|A Bit Late||late|Quite Late||all|All"
					."||completed|Complete||notcomplete|Not Complete"
					."||short|Short||normal|Normal";

?>
<script type="text/javascript" src="jquery-1.7.1.min.js"></script>
<link rel="stylesheet" href="colorbox/example1/colorbox.css" type="text/css" /> 
<script type="text/javascript" src="colorbox/version1.3.19/jquery.colorbox-min.js"></script>
<script language='javascript'>
function choosePic(idOrEl) {
	var el = typeof idOrEl == 'string' ? document.getElementById(idOrEl) : idOrEl;
	var group = el.getAttribute('group');
	$('.'+group).removeClass('chosenoption');
	$('.'+group).addClass('unchosenoption');
	$('#'+el.id).removeClass('unchosenoption');
	$('#'+el.id).addClass('chosenoption');
}

function updateTooShort(idOrEl) {
	var el = typeof idOrEl == 'string' ? document.getElementById(idOrEl) : idOrEl;
	var elId = el.id;
	if(elId == 'shortvisit') {
		togglePic(el);
		if($('#shortvisit').hasClass('chosenoption')) {
			$('#notarrived').removeClass('chosenoption');
			$('#notarrived').addClass('unchosenoption');
			$('#arrived').addClass('chosenoption');
			$('#arrived').removeClass('unchosenoption');
			
			$('#notcomplete').removeClass('chosenoption');
			$('#notcomplete').addClass('unchosenoption');
			$('#complete').addClass('chosenoption');
			$('#complete').removeClass('unchosenoption');
		}
	}
	else {
		if($('#shortvisit').hasClass('chosenoption')
			&& (!($('#arrived').hasClass('chosenoption') 
						 && $('#complete').hasClass('chosenoption'))
						|| $('#notarrived').hasClass('chosenoption')
						|| $('#notcomplete').hasClass('chosenoption')))
			updateTooShort(document.getElementById('shortvisit'));
	}
	describeFilter();
}

function updateLocation(idOrEl) {
	var el = typeof idOrEl == 'string' ? document.getElementById(idOrEl) : idOrEl;
	var elId = el.id;
	if(elId == 'away' || elId == 'nodata')
		togglePic(el);
	return;
	if(elId == 'away' || elId == 'nodata') {
		togglePic(el);
		if($('#'+elId).hasClass('chosenoption')) {
			$('#notcomplete').removeClass('chosenoption');
			$('#notcomplete').addClass('unchosenoption');
			$('#complete').addClass('chosenoption');
			$('#complete').removeClass('unchosenoption');
		}
	}
	else {
		if(($('#away').hasClass('chosenoption')
				|| $('#nodata').hasClass('chosenoption'))
			&& (!$('#complete').hasClass('chosenoption')
					|| $('#notcomplete').hasClass('chosenoption'))) {
			updateLocation('away');
			updateLocation('nodata');
		}
	}
	describeFilter();
}

function buildFilter() {
	/*
	arrivals: '' | arrivedonly | unarrivedonly
	arrivaltimes: '' | arrived_early, arrived_ontime, arrived_laggy, arrived_late
	completions: '' | completedonly | incompleteonly
	completiontimes: '' | completed_early, completed_ontime, completed_laggy, completed_late
	shortvisitsonly: '' | shortvisitsonly
	locations: '' | shortvisitsonly | away | nodata
	*/
	var filter = {};
	var arrivals = $('.arrivalstatus.chosenoption').length;
	filter.arrivals = arrivals == 2 || arrivals == 0 ? '' : (
							$('#arrived.chosenoption').length == 1 ? 'arrivedonly' : (
							$('#notarrived.chosenoption').length == 1 ? 'unarrivedonly' : ''));
	var arrivaltimes = [];
	$('.arrivaltime.chosenoption').each(function(index,el) {arrivaltimes.push(el.id);});
	filter.arrivaltimes = arrivaltimes.join(',');
	
	var completions = $('.completionstatus.chosenoption').length;
	filter.completions = completions == 2 || completions == 0 ? '' : (
							$('#complete.chosenoption').length == 1 ? 'completedonly' : (
							$('#notcomplete.chosenoption').length == 1 ? 'incompleteonly' : ''));
	var completiontimes = [];
	$('.completiontime.chosenoption').each(function(index,el) {completiontimes.push(el.id);});
	filter.completiontimes = completiontimes.join(',');
	 
	filter.shortvisitsonly = $('.shortvisit.chosenoption').length == 1 ? 'shortvisitsonly' : '';
	
	locations = [];
	if($('#away.chosenoption').length == 1) locations.push('away');
	if($('#nodata.chosenoption').length == 1) locations.push('nodata');
	filter.locations = locations.length == 0 ? '' : locations.join(',');
	return JSON.stringify(filter);
}



function describeFilter() {
	var arrivals = $('.arrivalstatus.chosenoption').length;
	arrivals = arrivals == 2 || arrivals == 0 ? 'any visits' : (
							$('#arrived.chosenoption').length == 1 ? 'only arrived visits' : (
							$('#notarrived.chosenoption').length == 1 ? 'only unarrived visits' : ''));
	var options = [];
	$('.arrivaltime.chosenoption').each(function(index,el) {options.push(el.id.substring('arrived_'.length))});
	if(options.length == 0) options = 'at all times';
	else if(options.length == 1) options = 'that are '+options[0];
	else if(options.length > 1) {
		var last = options.pop();
		options = 'that are '+(options.length == 0 ? '' : options.join(', '))+' or '+last;
	}
	var arrivalOptions = options;
	
	var completions = $('.completionstatus.chosenoption').length;
	completions = completions == 2 || completions == 0 ? 'any visits' : (
							$('#complete.chosenoption').length == 1 ? 'only complete visits' : (
							$('#notcomplete.chosenoption').length == 1 ? 'only incomplete visits' : ''));
	options = [];
		$('.completiontime.chosenoption').each(function(index,el) {options.push(el.id.substring('completed_'.length))});
		if(options.length == 0) options = 'at all times';
		else if(options.length == 1) options = 'that are '+options[0];
		else if(options.length > 1) {
			var last = options.pop();
			options = 'that are '+(options.length == 0 ? '' : options.join(', '))+' or '+last;
		}
	var completionOptions = options;
	
	var shortvisitsonly = $('.shortvisit.chosenoption').length == 1 ? '<br>Show only visits that were too short.' : '';
	
	locations = [];
	if($('#away.chosenoption').length == 1) locations.push('marked AWAY from client home');
	if($('#nodata.chosenoption').length == 1) locations.push('LACKING location information');
	locations = locations.length == 0 ? '' : '<br>Show only visits '+locations.join(' or ', locations)+'.';
	
	var description = 
		'Arrivals: show '+arrivals+' '+arrivalOptions+"."
			+'<br>Completions: show '+completions+' '+completionOptions+"."
			+shortvisitsonly // may be blank
			+locations; // may be blank
	description = description.replace(/late/g, 'very late');
	description = description.replace(/laggy/g, 'a little late');
	
	$('#json').html(buildFilter());
	$('#description').html(description);
}

function togglePic(idOrEl) {
	var el = typeof idOrEl == 'string' ? document.getElementById(idOrEl) : idOrEl;
	var isChosen = $('#'+el.id).hasClass('chosenoption');
	var drop = isChosen ? 'chosenoption' : 'unchosenoption';
	var add = !isChosen ? 'chosenoption' : 'unchosenoption';
	$('#'+el.id).removeClass(drop);
	$('#'+el.id).addClass(add);
	describeFilter();
}

	
</script>
<?

//print_r($filter);
echo "<table border=0>";

$options = explodePairsLine('arrived|Arrived||notarrived|Not Arrived||all|All');
$options = array_flip($options);

function filterHas($val) {
	global $filter;
	return in_array($val, (array)$filter);
}

function filterKeyIs($key, $val) {
	global $filter;
	return $filter[$key] == $val;
}

function filterKeyHas($key, $val) {
	global $filter;
	$vals = explode(',', $filter[$key]);
	return in_array($val, $vals);
}

//echo "<hr>".filterKeyIs('arrivals', 'arrivedonly');
//echo "<hr>{$filter['arrivals']}";
echo "<tr><td>Arrivals</td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/arrivalicons';
	picButton("$dir/arrived.png", 'arrivalstatus', 'arrived', filterKeyIs('arrivals', 'arrivedonly'), "togglePic(this);updateTooShort(this);", "Include visits marked arrived", $echo=true);
	picButton("$dir/notarrived.png", 'arrivalstatus', 'notarrived', filterKeyIs('arrivals', 'unarrivedonly'), "togglePic(this);updateTooShort(this);", "Include visits NOT marked arrived", $echo=true);
echo "</div></td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/arrivalicons';
	picButton("$dir/earlycolor.png", 'arrivaltime', 'arrived_early', filterKeyHas('arrivaltimes', 'arrived_early'), "togglePic(this)", "Include early visits", $echo=true);
	picButton("$dir/arrivecolor.png", 'arrivaltime', 'arrived_ontime', filterKeyHas('arrivaltimes', 'arrived_ontime'), "togglePic(this)", "Include on time visits", $echo=true);
	picButton("$dir/laggycolor.png", 'arrivaltime', 'arrived_laggy', filterKeyHas('arrivaltimes', 'arrived_laggy'), "togglePic(this)", "Include slightly late visits", $echo=true);
	picButton("$dir/latecolor.png", 'arrivaltime', 'arrived_late', filterKeyHas('arrivaltimes', 'arrived_late'), "togglePic(this)", "Include definitely late visits", $echo=true);
echo "</div></td>";
echo "</tr>";


echo "<tr><td>Completions</td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/arrivalicons';
	picButton("$dir/complete.png", 'completionstatus', 'complete', filterKeyIs('completions', 'completedonly'), "togglePic(this);updateTooShort(this);updateLocation(this);", "Include visits marked complete", $echo=true);
	picButton("$dir/notcomplete.png", 'completionstatus', 'notcomplete', filterKeyIs('completions', 'incompleteonly'), "togglePic(this);updateTooShort(this);updateLocation(this);", "Include visits NOT marked complete", $echo=true);
echo "</div></td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/arrivalicons';
	picButton("$dir/earlycolor.png", 'completiontime', 'completed_early', filterKeyHas('completiontimes', 'completed_early'), "togglePic(this)", "Include early visits", $echo=true);
	picButton("$dir/completecolor.png", 'completiontime', 'completed_ontime', filterKeyHas('completiontimes', 'completed_ontime'), "togglePic(this)", "Include on time visits", $echo=true);
	picButton("$dir/laggycolor.png", 'completiontime', 'completed_laggy', filterKeyHas('completiontimes', 'completed_laggy'), "togglePic(this)", "Include slightly late visits", $echo=true);
	picButton("$dir/latecolor.png", 'completiontime', 'completed_late', filterKeyHas('completiontimes', 'completed_late'), "togglePic(this)", "Include definitely late visits", $echo=true);
echo "</div></td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/completionicons';
	picButton("$dir/timewarning.png", 'shortvisit', 'shortvisit', filterHas('shortvisitsonly'), "updateTooShort(this);", "Include ONLY visits that were too short.", $echo=true);
echo "</div></td>";
echo "</tr>";

echo "<tr><td>Location info</td>";
echo "<td><div class='groupdiv'>";
	$dir = 'art/arrivalicons';
	picButton("$dir/away.png", 'location', 'away', filterKeyHas('locations', 'away'), "updateLocation(this);", "Include visits marked AWAY from client home.", $echo=true);
	picButton("$dir/nodata.png", 'location', 'nodata', filterKeyHas('locations', 'nodata'), "updateLocation(this);", "Include visits LACKING location info", $echo=true);
echo "</div></td>";
echo "</tr>";

echo "</table>";
	
echo "<div id='description' style='padding-top:10px;padding-bottom:10px;'>{$filter['description']}</div>";
echoButton(null, 'Apply Filter', 'applyFilter()');
echo "<img src='art/spacer.gif' width=20>";
echoButton(null, 'Quit', 'window.parent.$.fn.colorbox.close()');

echo "<div id='json' style='padding-top:20px;background:yellow;display:none;'></div>";

require_once "visit-performance-fns.php";
echo "<div style='display:none' id='helptext'>".performanceFilterHelp()."</div>";

?>
<script language='javascript'>
function applyFilter() {
	var filter = $('#json').html();
	filter = filter.length > 0 ? JSON.parse(filter) : '';
	filter.description = $('#description').html();
	window.parent.update('filterChoice', filter);
	window.parent.$.fn.colorbox.close();
}

function showHelp(el) {$.fn.colorbox({html:$('#helptext').html(), width:"635", height:"400", scrolling: true, opacity: "0.3"});}

</script>