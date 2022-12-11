// appointment-calendar-fns.js
function cancelAppt(appt, cancelFlg, requestOnly) {
	var operation = cancelFlg ? 'cancel' : 'uncancel';
	if(requestOnly) openConsoleWindow('editappt', "client-request-appointment.php?id="+appt+"&operation="+operation,530,450);
	else {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("appointment-cancel.php?cancel="+cancelFlg+"&id="+appt, update, '');
	}
}

function cancelSurcharge(surcharge, cancelFlg, requestOnly) {
	var operation = cancelFlg ? 'cancel' : 'uncancel';
	if(!requestOnly) {
		$('.BlockContent-body').busy("busy");
		ajaxGetAndCallWith("surcharge-cancel.php?cancel="+cancelFlg+"&id="+surcharge, update, '');
	}
}

function update(aspect, message) {
//alert("aspect: ["+aspect+"] message: ["+message+"]");
	refresh();
	if(aspect == 'appointments' && typeof apptSelectionCache != 'undefined') {
		reselectApptOrSurcharges(apptSelectionCache);
		reselectApptOrSurcharges(surchargeSelectionCache);
	}
//alert("ASPECT: "+aspect+" SELS: "+sels+" MSG: "+message+" selectionCache: "+selectionCache);
	if(window.opener && window.opener.update) {
		window.opener.update('appointments', 'refresh');
	}
	if(message && message.indexOf('MESSAGE:') == 0) alert(message.substring('MESSAGE:'.length));
}

function reselectApptOrSurcharges(globalSels) {
	var sels = '';
	sels = (typeof globalSels != 'undefined') && globalSels ? globalSels.split(',') : null;
	if(sels) for(var i=0;i<sels.length;i++) {
//if(!document.getElementById("appt_"+sels[i])) alert("SEL: appt_"+sels[i]	);	
		document.getElementById("appt_"+sels[i]).value = 1;
		//selectionStyle(document.getElementById("appttd_"+sels[i]).style, 1);
		var style = document.getElementById("appttd_"+sels[i]).style;
		style.borderColor= 'red';
		style.borderWidth= 3;
		
	}
}

function deleteAppt(appt) {
	$('.BlockContent-body').busy("busy");
	ajaxGetAndCallWith("appointment-delete.php?id="+appt, update, '');
}

function deleteSurcharge(surcharge) {
	$('.BlockContent-body').busy("busy");
	ajaxGetAndCallWith("surcharge-delete.php?id="+surcharge, update, '');
}

function selectionStyle(style, onOff) {
	onOff = (onOff == 'off' || onOff == 0) ? false : onOff;
	style.borderColor= onOff ? 'red' : 'black';;
	style.borderWidth= onOff ? 'thick' : 'thin';
}
