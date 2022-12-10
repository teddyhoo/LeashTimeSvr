<? // invoices-bottom.php
// ***************************************************************************
include "frame-end.html";
//echo date('H:i:s');
include "refresh.inc";				

?>
<script language='javascript' src='ajax_fns.js'></script>
<script language='javascript' src='popcalendar.js'></script>
<script language='javascript' src='check-form.js'></script>
<script language='javascript'>
<? dumpPopCalendarJS(); ?>

function changeAsOfDate(lastInitial) {
	var extraArgs = "&linitial="+(lastInitial ? lastInitial : '<?= $linitial ?>');
	
	var showAllClients = document.getElementById('showAllClients').checked ? 1 : 0;
	if(MM_validateForm('asOfDate','','isDate')) {
		var ymd = mdy(document.getElementById('asOfDate').value);
		ymd = formatInteger(ymd[2], 4)+'-'+formatInteger(ymd[0], 2)+'-'+formatInteger(ymd[1], 2);
		document.location.href='<?= $SERVER['SCRIPT_NAME'] ?>?asOfDate='+ymd+'&showAllClients='+showAllClients+extraArgs;
	}
}

function formatInteger(num, length) {

    return (num / Math.pow(10, length)).toFixed(length).substr(2);
}



function viewInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-view.php?id='+invoiceid+'&email='+email, 800, 800);
}

function payInvoice(invoiceid, email) {
	openConsoleWindow('invoiceview', 'invoice-payment.php?invoiceid='+invoiceid, 600, 400);
}

function editInvoice(clientptr) {
	openConsoleWindow('invoiceview', 'invoice-edit.php?client='+clientptr+'&asOfDate='+'<?= date('Y-m-d', $throughDateInt) ?>', 800, 800);
}

function viewClient(clientid) {
	openConsoleWindow('clientview', 'client-view.php?id='+clientid, 700, 500);
}

function viewRecent(clientid) {
	openConsoleWindow('recentview', 'invoices-recent.php?client='+clientid, 700, 500);
}

function restripe(row) {
	var rows = row.parentNode.childNodes;
	var className = "futuretaskEVEN";
	for(var i = 0; i < rows.length; i++) {
		var row = rows[i];
		if(row.nodeType == 1 /* ELEMENT_NODE */ && row.tagName.toUpperCase() == 'TR' && row.style.display != 'none') {
			row.className = (className = className == "futuretaskEVEN" ? "futuretask" : "futuretaskEVEN");
		}
	}
}
	
function openConsoleWindow(windowname, url,wide,high) {
  var w = window.open("",windowname,
    'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
	if(w && typeof w != 'undefined') {
		w.document.location.href=url;
		w.focus();
	}
}

function selectAll(group, onoff) {
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf(group+'_') == 0)
			els[i].checked = onoff;
}

function printSelectedInvoices() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') >= 0 && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more invoices to print.');
		return;
	}
	openConsoleWindow('invoiceprint', 'invoice-generate.php?clients='+sels+'&target=mail&asOfDate=<?= date('Y-m-d', strtotime($asOfDate)) ?>', 700, 500);
}

function emailSelectedInvoices() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more invoices to email.');
		return;
	}
	ajaxGetAndCallWith('invoice-generate.php?clients='+sels+'&target=email&asOfDate=<?= date('Y-m-d', strtotime($asOfDate)) ?>', reportEmailSuccess, null);
}

function emailSelectedPreviews() {
	var sels = new Array();
	var els = document.getElementsByTagName('input');
	for(var i = 0; i < els.length; i++) 
		if(els[i].type == 'checkbox' && els[i].id.indexOf('_') && els[i].checked) 
			sels[sels.length] = els[i].id.substring(els[i].id.indexOf('_')+1);
	sels = sels.join(',');
	if(sels.length == 0) {
		alert('Please select one or more invoice previews to email.');
		return;
	}
	ajaxGetAndCallWith('invoice-preview-generate.php?clients='+sels+'&target=email&asOfDate=<?= date('Y-m-d', strtotime($asOfDate)) ?>', reportPreviewEmailSuccess, null);
}

function reportPreviewEmailSuccess(argument, txt) {
	alert(txt);
	update();
}

function reportEmailSuccess(argument, txt) {
	alert(txt);
	update();
}

function update(target, aspect) {
//alert('target: '+target+' aspect: '+aspect+' row: '+"clientrow_"+aspect);
	if(aspect > 0 && target == 'account') {
		// find a tr whose id starts with "clientrow_"+aspect
		var rowid;
		var trs = document.getElementsByTagName('tr');
//alert('rows: '+trs.length);		
		for(var i = 0; i < trs.length; i++)
			if(trs[i].id.indexOf("clientrow_"+aspect+"_") != -1)
				rowid = trs[i].id;
//alert('rowid: '+rowid+' asOfDate: '+document.getElementById('asOfDate').value);
		// date('Y-m-d', strtotime('tomorrow')
		ajaxGetAndCallWith("invoice-get-info.php?id="+aspect
												+"&asOfDate=<?= date('Y-m-d', $throughDateInt) ?>", updateClientRowCallback, rowid)  // through today
	}
	else refresh();
}

function updateClientRowCallback(rowid, data) {
//alert('row: '+rowid+' data: '+data);	
	//data: acountbalance|$ 237.00|invoice|200|invoicelabel|LT0200|paid|0|throughdate|12/04/2009|currinv|$ 612.00|amountdue|$ 612.00|uninvoiced|$ 375.00|incompleteJobs|1
	// $cols = array_flip(explode(',', 'cb,clientname,acountbalance,invoice,throughdate,currinv,amountdue,uninvoiced'));
	
	// error: [Kim Thomas] [239] [PAID] [LT102] [7/30/2009] [52] [52]
	document.getElementById('totalacctbal').innerHTML = getValue(data, 'totalacctbal');
	var row = document.getElementById(rowid);
	var parts = rowid.split('_');
	var client = parts[1];
	
	
	if((getValue(data, 'acountbalance') == 0)
			&& (!getValue(data, 'uninvoiced') || (getValue(data, 'uninvoiced') == 0))
			&& (!getValue(data, 'incompleteJobs') || (getValue(data, 'incompleteJobs') == 0))
			&& !document.getElementById('showAllClients').checked) {
		row.style.display = 'none';
		restripe(row);
		return;
	}
	
	var acctbalLabel = getValue(data, 'acountbalance') != 0 ? getValue(data, 'acountbalance') : "PAID";
//alert('row: '+row+' td: '+getTD(row, 'acountbalance'));
//alert(rowid+': '+document.getElementById(rowid).innerHTML);
//alert(describeRow(document.getElementById(rowid)));
	getTD(row, 'acountbalance').innerHTML = "<a class='fauxlink' onclick='viewRecent("+client+")' title='Review recent invoices'>"
																					+acctbalLabel+"</a>";
	var invoice = getValue(data, 'invoice');
	if(invoice) {
		var invoiceId = getValue(data, 'invoicelabel');
		invoiceId = "<a class='fauxlink' onclick='viewInvoice("+invoice+", 0)' title='View this invoice'>"
								+invoiceId+"</a>";
		if(getValue(data, 'paid') == 0) 
			invoiceId = invoiceId
									+" <a class='fauxlink' onclick='payInvoice("+invoice+")' title='Pay this invoice'>(Pay)</a>";
		getTD(row, 'invoice').innerHTML = invoiceId;
	}
	getTD(row, 'throughdate').innerHTML = getValue(data, 'throughdate');
	getTD(row, 'currinv').innerHTML = getValue(data, 'currinv');
	getTD(row, 'amountdue').innerHTML = getValue(data, 'amountdue');
	var uninvoiced = getValue(data, 'uninvoiced');
	if(uninvoiced != 0 
			|| getValue(data, 'incompleteJobs')) {
		if(uninvoiced == 0) uninvoiced = "$ 0.00";
		uninvoiced = "<a class='fauxlink' onclick='editInvoice("+client+")' title='Review and send a new invoice'>"+uninvoiced+"</a>"
	}
	else if(uninvoiced == 0) uninvoiced = "$ 0.00";
	getTD(row, 'uninvoiced').innerHTML = uninvoiced;
}

function getValue(data, key) {
	var arr = data.split('|');
	for(var i=0;i < arr.length - 1; i+= 2)
		if(arr[i] == key)
			return arr[i+1];
}

function getTD(row, key) {
	var arr = 'placeholder,cb,clientname,acountbalance,invoice,throughdate,currinv,amountdue,uninvoiced,totalacctbal'.split(',');
	for(var i=0;i < arr.length; i++)
		if(arr[i] == key)
			return row.childNodes[i];
}

function describeRow(row) {  // diagnostic
	var s = '';
	for(var i=0;i < row.childNodes.length; i++)
		s += '\n' + row.childNodes[i].innerHTML;
	return s;
}




</script>
