<!-- client-sched-visit-payload.js -->

function getVisitPayload() {
	var payload = {};

	var els = document.getElementsByTagName('select');
	for(var i=0; i < els.length; i++) {
		if(els[i].id.indexOf('servicecode_') == 0 && els[i].selectedIndex != 0) {
			var visitid = els[i].id.substring('servicecode_'.length);
			payload['servicecode_'+visitid] = els[i].options[els[i].selectedIndex].value;
			payload['charge_'+visitid] = document.getElementById('charge_'+visitid).value;
			payload['timeofday_'+visitid] = document.getElementById('timeofday_'+visitid).value;
			payload['pets_'+visitid] = document.getElementById('pets_'+visitid).value;
		}
	}
	if(document.getElementById('note')) payload['note'] = document.getElementById('note').value;
	return JSON.stringify(payload);
}
