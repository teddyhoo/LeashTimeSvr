// gratuity-fns.js - included for use with  gratuity-fns.php

function updateGratuityAmounts(el) {
	if('gratuity' == el.id) {
		var gratuity = document.getElementById('gratuity').value;
		gratuity = Math.round(parseFloat(gratuity)*100)/100.0;
		document.getElementById('gratuityTotal').value = gratuity; // update the hidden field
		for(var i = 1; i <= 5; i++) updatePortion(i);
		if(totalPaymentField) { // a global
			var paymentTotal = document.getElementById(totalPaymentField).value;
			document.getElementById('gratuitySummary').innerHTML =
				"Out of a total payment of "+currencyMark+paymentTotal+",<br>"+currencyMark+gratuity+" is to be paid out in gratuities and <br>$"+(Math.round(100*Math.max(0, (paymentTotal-gratuity)))/100)+" will pay for services rendered.<p>";
			if(paymentTotal < gratuity) document.getElementById('status').innerHTML = 
				'<span style="color:red;">The gratuity cannot be more than the total payment.</span>';
		}
	}
	else {
		var i = el.id.split('_');
		if(i.length < 2) return;
		updatePortion(i[1]);
	}
	updateStatus();
}

function setGratuityProvider(el) {
	var me = el.selectedIndex;
	if(me > 0) {
		var dups = 0;
		for(var i = 1; i <= 5; i++) 
			if(document.getElementById('gratuityProvider_'+i).selectedIndex == me)
				dups++;
		if(dups > 1) {
			alert('You have already chosen '+el.options[me].innerHTML);
			el.selectedIndex = 0;
			return;
		}
	}
	updateGratuityAmounts(el);
}
	
function percentage(num, den) {
	if(isNaN(den)) return "";
	return (Math.round(num/den*10000) / 100)+" %";
}

function updatePortion(index) {
	var sel = document.getElementById('gratuityProvider_'+index);
	if(!sel) alert('Index: '+index);
	var total = parseFloat(document.getElementById('gratuity').value);
	var portion = document.getElementById('dollar_'+index).value;
	var wholeHog = -1;
	var portions = new Array();
	var numPortionsFilledIn = 0;
	
	if(sel.options[sel.selectedIndex].value != 0) { // -1 = Unassigned, 0 = no selection
		var numPortions = 0;
		for(var i = 1; i <= 5; i++) 
			if(document.getElementById('gratuityProvider_'+i).value != 0 && document.getElementById('gratuityProvider_'+i).value != '') {
				numPortions+=1;
				var portionI = document.getElementById('dollar_'+i).value;
//alert(i+': '+portionI+' nan: '+isNaN(portionI));			
				//if(parseFloat(portionI) != 0)  numPortionsFilledIn += 1;
				if(!isNaN(portionI) && portionI != 0)  numPortionsFilledIn += 1;
				if(document.getElementById('gratuityProvider_'+i).value != 0) wholeHog = i;
				portions[portions.length] = i;
			}
		var allocatedDollars = totalDollarsPresent();
		if(allocatedDollars == 0) document.getElementById('dollar_'+index).value = (isNaN(total) && isNaN(portion) ? '' : portion);
		else if(isNaN(total)) {
			total = isNaN(portion) ? 0 : portion;
			document.getElementById('gratuity').value = total;
		}
		else if(allocatedDollars == total) {
			if(numPortions == 2 && numPortionsFilledIn < 2) {
				portion = total / 2.0;
				document.getElementById('dollar_'+portions[0]).value = portion;
				document.getElementById('dollar_'+portions[1]).value = portion;
			}
		}
		var percent = '';
		if(!isNaN(total) && (!portion || isNaN(portion)) && allocatedDollars < total) {
			portion = total - allocatedDollars;
			document.getElementById('dollar_'+index).value = portion;
		}
		/*if(portion && (percent = percentage(portion, total))) percent = ' = '+percent;
		if(numPortions == 1 && !isNaN(total)) {
			var portion = parseFloat(document.getElementById('dollar_'+wholeHog).value);
			if(portion) percent = ' = '+percentage(portion, total);
			document.getElementById('info_'+wholeHog).innerHTML = percent;
		}*/
	}
	//else percent = '';
	//document.getElementById('info_'+index).innerHTML = percent;
	for(var i = 1; i <= 5; i++) {
		var val = 0+document.getElementById('dollar_'+i).value;
		if(isNaN(val) || val <= 0) document.getElementById('info_'+i).innerHTML = '';
		else document.getElementById('info_'+i).innerHTML = ' = '+percentage(val, total);
	}
}

function dollarsAllocated() {
	var total = 0;
	for(var i = 1; i <= 5; i++) {
		var dollar = parseFloat(document.getElementById('dollar_'+i).value);
		dollar = Math.round(dollar * 100)/100;
		if(document.getElementById('info_'+i).innerHTML) {
			total += dollar;
		}
	}
	return total;
}

function cents(dollars) {
	return Math.ceil(Math.round(parseFloat(dollars)*100));
}

function dollars(amount) {
	return amount.toFixed(2);
}

function totalDollarsPresent() {
	var total = 0;
	for(var i = 1; i <= 5; i++) {
		var dollar = parseFloat(document.getElementById('dollar_'+i).value);
		if(!isNaN(dollar)) total += dollar;
	}
	return total;
}	

function updateStatus() {
	var total = dollarsAllocated();
	if(total == 0 || isNaN(total)) {
		if(document.getElementById('status').innerHTML) document.getElementById('status').innerHTML = '';
		return;
	}
	else if(cents(total) > cents(document.getElementById('gratuity').value) )
		status = '<span style="color:red;">You have allocated more than the total value of the gratuity: '+currencyMark+dollars(total)+'.</span>';
	else if(cents(total) == cents(document.getElementById('gratuity').value)) status = 'You have allocated all of the gratuity.';
	else status = 'You have allocated '+currencyMark+dollars(total)+' of the gratuity.';
	document.getElementById('status').innerHTML = status;
}
	
function incorrectAllocationMessage() {
	var gratuity = document.getElementById('gratuity').value;
	var allocated = dollarsAllocated();
	return cents(allocated) < cents(gratuity)
		? 'You must allocate all of the gratuity among the selected sitters.' 
		: (cents(allocated) > cents(gratuity) ? 'You have allocated to the selected sitters more than the total gratuity.' : null);
}

function nonZeroGratuityMessage() {
	return cents(parseFloat(document.getElementById('gratuity').value)) == 0 ? 'Please supply a non-zero gratuity.' : '';
}

function noProvidersSelected() {
	var total = 0;
	for(var i = 1; i <= 5; i++) total += parseInt(document.getElementById('gratuityProvider_'+i).value);
	return total == 0 ? 'You must select at least one sitter to receive the gratuity.' : '';
}

function allOrNothing(index) {
	return (document.getElementById('gratuityProvider_'+index).value != 0 && 
					document.getElementById('dollar_'+index).value &&
					parseFloat(document.getElementById('dollar_'+index).value)  > 0) 
					||
				 (document.getElementById('gratuityProvider_'+index).value == 0 && 
					(!document.getElementById('dollar_'+index).value ||
					 parseFloat(document.getElementById('dollar_'+index).value)  == 0))
					? ''
					: "Either sitter #"+index+" and sitter # "+index+"'s share must both be supplied or neither.";
}
