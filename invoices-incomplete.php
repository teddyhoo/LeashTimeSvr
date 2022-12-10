<? // invoices-incomplete.php
$listInvoices=1;
$tab = 'incomplete';
include "invoices-top.php";
?>
<script language='javascript' src='incomplete-appts.js'></script>
<p>
<div class='bluebar'>Incomplete Visits</div>
<p>
<form name='incompleteform'>
<?
$end = shortDate(strtotime($asOfDate));
calendarSet('Starting:', 'incompletestart', shortDate(strtotime("-7 days", strtotime($end))), null, null, true, 'incompleteend');
calendarSet('ending:', 'incompleteend', $end);
echo " ";
echoButton('', 'Show Incomplete', 'showIncomplete()');
echo " ";
echoButton('', 'Show All Incomplete', 'showAllIncomplete()');
?>
<div id='incomplete_list'></div>
</form>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
showIncomplete();
function selectAllIncomplete(onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('appt_') == 0)
			els[i].checked = onoff;
}

</script>
<?
// ***************************************************************************
include "invoices-bottom.php";
