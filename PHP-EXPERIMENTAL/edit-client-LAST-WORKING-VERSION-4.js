function supplyLocationInfo(cityState,addressGroupId,noconfirmation) {
  if(cityState == 'NO_CITIES' || cityState.indexOf('||') > 0) {
      supplyMultiCityInfo(cityState,addressGroupId);
      return;
  }
  var cityState = cityState.split('|');
  if(cityState[0] && cityState[1]) {
      if(cityState[1].length == 1) 
        cityState[1] = ''; // for UK database, which supplies "-" for state
      var city = document.getElementById(addressGroupId+'city');
      var state = document.getElementById(addressGroupId+'state');
      var needConfirmation = false;
      if(city.type == 'text' && noconfirmation != true) {
        needConfirmation = needConfirmation || (city.value.length > 0 && (city.value.toUpperCase() != cityState[0].toUpperCase()));
        needConfirmation = needConfirmation || (state.value.length > 0 && (state.value.toUpperCase() != cityState[1].toUpperCase()));
      }
      if(!needConfirmation || confirm("Overwrite city and state with "+cityState[0]+(ltrim(cityState[1]) != '' ? ", "+cityState[1] : '')+"?")) {
        if(city.value.toUpperCase() != cityState[0].toUpperCase()) 
          city.value = cityState[0];
        if(state.value.toUpperCase() != cityState[1].toUpperCase()) 
          state.value = cityState[1];
        if(document.getElementById('label_'+addressGroupId+'city')) 
          document.getElementById('label_'+addressGroupId+'city').innerHTML = cityState[0]; 
        if(document.getElementById('label_'+addressGroupId+'state')) 
          document.getElementById('label_'+addressGroupId+'state').innerHTML = cityState[1]; 
      }
  }
}
function ensureOneKey() {
    var arr = ['locklocation', 'description', 'bin'];
    var found = false;
    for(var i=0;i<arr.length;i++)
      if(document.getElementById(arr[i]).value.length != 0) found = true;
    if(found && document.getElementById('copies').value == 0)
      document.getElementById('copies').value = 1;
}
function printKeyLabels() {
    var sel = document.getElementById('copies');
    var num = sel.options[sel.selectedIndex].value;
    if(false /* num == 0*/) {
      alert("There are no copies of this key registered.\nPlease register at least one copy before\nyou try to print labels.");
      return;
    }
    
    if(!confirm("This client must be saved before you print key labels.\n "+
                        "Click OK to save the client and continue."))
       return;
    else {
      checkAndSubmit('keyedit');
    }
}
function viewBillingServicesInvoice(starting, ending, exclude) {  // BETA BILLING 1
    var email = document.getElementById('email').value;
    var clientid = document.getElementById('client').value;
    var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
    if(exclude == undefined) exclude = 0;
    if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
    if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
    var args = '&excludePriorUnpaidBillables='+exclude+'&firstDay='+starting+'&lastDay='+ending
                  +"&invoiceby=email&email="+email
                  +(origincludeall ? '&includeall=1' : '')
                  +"&literal=1";

    openConsoleWindow('invoiceview', 'billing-invoice-view.php?id='+clientid+args, 800, 800);
}
function viewBillingStatement(starting, ending) {  // BETA BILLING 2
    var exclude = !confirm("Click OK to include any prior unpaid items.");
    var email = document.getElementById('email').value;
    var clientid = document.getElementById('client').value;
    var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
    if(exclude == undefined) exclude = 0;
    if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
    if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
    var args = '&excludePriorUnpaid='+(exclude ? 1 : 0)+'&firstDay='+starting+'&lastDay='+ending
                  +"&invoiceby=email&email="+email
                  +(origincludeall ? '&includeall=1' : '')
                  +"&literal=1";

    openConsoleWindow('invoiceview', 'billing-statement-view.php?id='+clientid+args, 800, 800);
}
function viewNonRecurringServicesStatement(starting, ending, packageptr) { // BETA 2 EZ SCHEDULE INVOICE
  var email = document.getElementById('email').value;
  var clientid = document.getElementById('client').value;
  if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
  if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
  var args = '&excludePriorUnpaid=1&literal=1'+"&invoiceby=email&email="+email+"&packageptr="+packageptr;
  openConsoleWindow('invoiceview', 'billing-statement-view.php?id='+clientid+args, 800, 800);
}
function editLoginInfo(clientid, argstring) {
  if(!clientid) {
    if(!confirm("This client has not been saved, but must be saved\nbefore a system login can be set up.\n"+ "Click OK to save the client and continue."))
         return;
    else {
       checkAndSubmit('systemloginsetup');
    }
  }
  else {
    if(userid != '' && (argstring.indexOf('userid') == -1)) argstring = argstring+"&userid="+userid;
    var url = "login-creds-edit.php?"+argstring;
    var w = window.open("",'systemlogineditor','toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+400+',height='+400);
    w.document.location.href=url;
    if(w) w.focus();
  }
}
function saveAndRedirect(redirectUrl) {
  document.clienteditor.rd.value=redirectUrl;
  if(!checkAndSubmit()) document.clienteditor.rd.value='';
}
function findTransactionByID() {
  $.fn.colorbox({href:"find-transaction.php", iframe:true,  width:"590", height:"470", scrolling: true, opacity: "0.3"});
}
function update(target, value) {
  if(value && (typeof value == 'string') && value.indexOf('alert') != -1) alert(value); 
  
  if(target.indexOf('breed:') == 0) {
    target = target.substring('breed:'.length);
    document.getElementById(target).value = value;
  }
  else updateForSavedClient(target, value); 
}
function chooseBreed(breedfld, typefld) {
  var pettype = document.getElementById(typefld);
  pettype = pettype.options[pettype.selectedIndex].value;
  $.fn.colorbox({href:"breeds.php?pettype="+pettype+"&target="+breedfld, iframe:true,  width:"490", height:"470", scrolling: true, opacity: "0.3"});
}
function quickEdit(id) {
    ajaxGet('appointment-quickedit.php?id='+id, 'editor_'+id);
    document.getElementById('editor_'+id).parentNode.style.display='';
    return true;
}
function discountChanged(el) {
  var displayMode = el.selectedIndex == 0 || el.options[el.selectedIndex].value.split('|')[1] == 0 ? 'none': 'none';
  document.getElementById('memberidrow').style.display = displayMode;
}

function emailCalendar() {
  // the email icon appears only when there are visits
  var range = "&starting="+safeDate(document.getElementById('starting').value)
              +"&ending="+safeDate(document.getElementById('ending').value);
  openConsoleWindow('emailcomposer', 'comm-visits-composer.php?client='+document.getElementById('client').value+range,640,500);
}

function checkEmail(addressField) {
  var addr;
  if(!(addr = jstrim(document.getElementById(addressField).value))) alert('Please supply an email address first.');
  else if(!validEmail(addr))  alert('The format of this email address is not valid.');
  else ajaxGetAndCallWith("ajax-email-check.php?email="+addr, postEmailCheck, addressField);  
}

function postEmailCheck(addressField, response) {
  alert(response);
}

function viewNonRecurringServicesInvoice(starting, ending, packageptr) {
  var email = document.getElementById('email').value;
  var clientid = document.getElementById('client').value;
  if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
  if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
  //var lookahead = document.getElementById('lookahead').value;
  var args = '&excludePriorUnpaidBillables=1&firstDay='+starting+'&lastDay='+ending
                +"&invoiceby=email&email="+email+"&packageptr="+packageptr+"&packageptr="+packageptr
                +"&literal=1";
  openConsoleWindow('invoiceview', 'billing-invoice-view.php?id='+clientid+args, 800, 800);
}


function viewServicesInvoice(starting, ending, exclude) {  // BILLING 0 (prepayments)
  var email = document.getElementById('email').value;
  var clientid = document.getElementById('client').value;
  var origincludeall = ''; //document.getElementById('origincludeall') && document.getElementById('origincludeall').value;
  if(exclude == undefined) exclude = 0;
  if(typeof starting == 'undefined') starting = escape(document.getElementById('starting').value);
  if(typeof ending == 'undefined')  ending = escape(document.getElementById('ending').value);
  //var lookahead = document.getElementById('lookahead').value;
  var args = '&excludePriorUnpaidBillables='+exclude+'&firstDay='+starting+'&lastDay='+ending
                +"&invoiceby=email&email="+email
                +(origincludeall ? '&includeall=1' : '');
  openConsoleWindow('invoiceview', 'prepayment-invoice-view.php?id='+clientid+args, 800, 800);
}



  
  function validateLogin() {
    $.fn.colorbox({href:"validate-system-login.php?role=client&roleid=<?= $id ?>", width:"500", height:"250", iframe: true, scrolling: true, opacity: "0.3"});
  }
  function checkForDups() {
    var fname = jstrim(document.getElementById('fname').value);
    var lname = jstrim(document.getElementById('lname').value);
    if(fname && lname.length > 1) {
      ajaxGetAndCallWith('possible-duplicate-clients.php?justNames=1&fname='+escape(fname)+'&lname='+escape(lname), 
                          showDups, 0)
    }
  }
  function showDups(unused, content) {
    var divtitle = '';
    if(content) {
      content = content.split('|');
      divtitle = content.join(', ');
      var plural = content.length == 1 ? '' : 's';
      content = content.length+' similar name'+plural+' found.';
    }
    document.getElementById('dupnames').innerHTML = content;
    document.getElementById('dupnames').title = divtitle;
  }
function updateAppointmentVals(appt) {
    var p, t, s;
    p = document.getElementById('providerptr_'+appt);
    p = p.options[p.selectedIndex].value;
    t = document.getElementById('div_timeofday_'+appt).innerHTML;
    s = document.getElementById('servicecode_'+appt);
    s = s.options[s.selectedIndex].value;
    ajaxGetAndCallWith('appointment-quickedit.php?save=1&id='+appt+'&p='+p+'&t='+t+'&s='+s, update, 'appointments');  // must update all 
    document.getElementById('editor_'+appt).parentNode.style.display = 'none';
  }

  function notifyUserOfScheduleChange(packageid, silentDenial) {
    var acceptsEmail = '<?= $scheduleUpdatesAccepted ?>';
      if(acceptsEmail) {
        var url ="notify-schedule.php?packageid="+packageid+"&clientid=<?= $id ?>&newPackage=0&offerConfirmationLink=1";
        openConsoleWindow('notificationcomposer', url, 600, 600);
      }
      else if(!silentDenial) alert("Client declines to receive schedule notifications by email.");
  }

function viewClinic(vet) {
    if(vet) {
      var el = document.getElementById('vetptr');
      if(el.selectedIndex == 0) alert('Please select a vet to view');
      else openConsoleWindow('clinic', 'viewVet.php?id='+el.options[el.selectedIndex].value,700,500);
    }
    else {
      var el = document.getElementById('clinicptr');
      if(el.selectedIndex == 0) alert('Please select a clinic to view');
      else openConsoleWindow('clinic', 'viewClinic.php?id='+el.options[el.selectedIndex].value,700,500);
    }
  }
  function openConsoleWindow(windowname, url,wide,high) {  //NOT USED WHEN common.js is loaded
    var w = window.open("",windowname,
      'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width='+wide+',height='+high);
    if(w && typeof w != 'undefined') {
      w.document.location.href=url;
      w.focus();
    }
  }


  
  function primaryClicked(el) {
    var choice = el.value.substring(el.value.indexOf('_')+1);
    ajaxGetAndCallWith("cc-primary-set.php?id=<?= $id ?>&choice="+choice, function(x, text) {
      alert(text);}, 'primary!')
  }

  function supplyMultiCityInfo(cityStates,addressGroupId) {
    var listhtml = "<span class='fauxlink' onclick='displayBlockOrNone(this.parentNode, null)'>(close list)</span><p>";;
    if(cityStates != 'NO_CITIES') {
      var cityStates = cityStates.split('||');
      var choices = '';
      for(var i = 0; i < cityStates.length; i++) {
        var pair = cityStates[i].split('|');
        choices += "<span class='fauxlink' citystate='"+cityStates[i]+"' addressgroupid='"
                  +addressGroupId+"' onclick='chooseCity(this)'>"+pair[0]+(ltrim(pair[1]) != '' ? ", "+pair[1] : '')
                  +"</span><br>";
      }
      listhtml += choices;
    }
    document.getElementById(addressGroupId+'_citychoices').innerHTML = listhtml;
    document.getElementById(addressGroupId+'_citychoices').style.display = 'block';
  }
function editInvoice(clientptr) {
  openConsoleWindow('invoiceview', 'invoice-edit.php?client='+clientptr+'&asOfDate=', 800, 800);
}
function editCharge(chargeid) {

  var url = 'charge-edit.php?id='+chargeid;
  openConsoleWindow('editcharge', url, 600, 260);
}
function addMiscellaneousCharge(client) {
  var winDims = [600,480];
  openConsoleWindow('editcharge', 'charge-edit.php?client='+client, winDims[0], winDims[1]);
}

function mailToHomeClicked(el, prefix) {
  if(typeof el == "string") el = document.getElementById(el);
  var keys = new Array('zip','street1','street2','city','state');
  for(var i = 0 ; i < keys.length; i++)
    document.getElementById(prefix+keys[i]).disabled = el.checked;
}

function switchToClientSitters() {
  var url = "client-providers.php?id=<?= $id ?>";
  if(confirm('Click OK to save changes before you leave this page.')) {
    if(checkAndSubmit(false, 'justcheck')) {
      document.getElementById('rd').value=url;
    }
    checkAndSubmit();
  }
  else document.location.href=url;
}

function editRecurringNote(packageid) {
  var url = "service-recurring-note-edit.php?packageid="+packageid;
  $.fn.colorbox({href:url, width:"500px", height:"300px", scrolling: true, opacity: "0.3", iframe: true});
  
}
function addNoteToAppointments() {
  if(!MM_validateForm(
      'starting', '', 'isDate',
      'ending', '', 'isDate')) return;
  var client = document.getElementById('client').value;
  var starting = document.getElementById('starting').value;
  var ending = document.getElementById('ending').value;
  if(starting) starting = '&starting='+safeDate(starting);
  if(ending) ending = '&ending='+safeDate(ending);
  $.fn.colorbox({href:"visit-notes-editor.php?client="+client+starting+ending, width:"700", height:"650", iframe: true, scrolling: true, opacity: "0.3"});
  
}

function optionsAction(el) {
  var action = el.options[el.selectedIndex].value;
  el.selectedIndex = 0;
  if(action == 'viewDiscounts') document.location.href="discounted-visits.php?client=<?= $id ?>";
  if(action == 'visitDetails') openConsoleWindow("visits", "visits-detail-viewer.php?id=<?= $id ?>", 900, 900);
  if(action == 'printVisitSheet') printVisitSheets();
  if(action == 'setVisitListPrefs') document.location.href="client-schedule-prefs.php?client=<?= $id ?>";
  if(action == 'historicalData') document.location.href="historical-data.php?client=<?= $id ?>";
  if(action == 'visitsList') searchForAppointments(false);
  if(action == 'visitsCalendar') searchForAppointments(true);
  if(action == 'addNotes') addNoteToAppointments();
  if(action == 'arrangeMeeting') document.location.href='client-meeting.php?clientptr=<?= $id ?>';
  if(action == 'clientChangeHistory') openConsoleWindow("changeHistory", "client-change-history.php?id=<?= $id ?>", 900, 900);
  if(action == 'printIntakeForm') openConsoleWindow("changeHistory", "intake-form-launcher.php?clientid=<?= $id ?>", 900, 900);
  if(action == 'staffVisitChangeHistory') {
    var starting = document.getElementById('starting').value;
    var ending = document.getElementById('ending').value;
    if(starting) starting = '&starting='+safeDate(starting);
    if(ending) ending = '&ending='+safeDate(ending);
    openConsoleWindow("staffVisitChangeHistory", "staff-visit-details.php?id=<?= $id ?>"+starting+ending, 900, 900);
  }
  if(action == 'staffProvidersWhoHaveServed') {
    $.fn.colorbox({href:"reports-client-sitter-visits.php?clientptr=<?= $id ?>", width:"550", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
  }
  if(action == 'providersWhoWillNotServe') {
    $.fn.colorbox({href:"reports-client-do-not-serve.php?clientptr=<?= $id ?>", width:"550", height:"470", iframe: true, scrolling: true, opacity: "0.3"});
  }
  
  if(action == 'staffMonthlyBillables') {
    openConsoleWindow("staffMonthlyBillables", "staff-monthly-billables.php?id=<?= $id ?>", 600, 600);
  }
}

function displayBlockOrNone(el, block) {
  el.style.display = block ? 'block' : 'none';
}

function chooseCity(el, block) {
  supplyLocationInfo(el.getAttribute('citystate'), el.getAttribute('addressgroupid'), true);
  displayBlockOrNone(el.parentNode.parentNode, null);
}

function viewOfficeNotesLog(id, targetnote) {
  $.fn.colorbox({href:"logbook-editor.php?itemtable=client-office&itemptr="+id+"&updateaspect=officenotes&&printable=1&targetid="+targetnote +"&title=Office Notes", width:"800", height:"650", iframe: true, scrolling: true, opacity: "0.3"});
}

function mailToHomeClicked(el, prefix) {
  if(typeof el == "string") el = document.getElementById(el);
  var keys = new Array('zip','street1','street2','city','state');
  for(var i = 0 ; i < keys.length; i++)
    document.getElementById(prefix+keys[i]).disabled = el.checked;
}

function openCityChooser(prefix) {
  document.getElementById(prefix+'_citychoices').style.display='block';
}

function toggleDate(rowId) {
  var el = document.getElementById(rowId+'_headers');
  el.style.display = 'none';
  var el = document.getElementById(rowId+'_row');
  el.style.display = 'none';
  var n = rowId.split('_');
  n = n[1];
  document.getElementById('day-shrink-'+n).src = (el.style.display == 'none' ? 'art/down-black.gif' : 'art/up-black.gif');
}


function toggleNoticeDiv(linkSpan, forceoff) {
  forceoff = typeof forceoff == 'undefined' ? false : forceoff;
  var children = linkSpan.parentNode.childNodes;
  for (var i = 1; i < children.length; i++) {
    var el = children[i];
    if (el.tagName) {
      var show = el.tagName.toLowerCase() == 'div' ? 'block' : 'inline';
      el.style.display = forceoff ? 'none' : (el.style.display == 'none' ? show : 'none');
    }
  }
}
function highlightNoticeItem(item) {
  var wasOff = item.parentNode.childNodes[1].style.display == 'none';
  var parent = item.parentNode;
  while (parent != null && (!parent.tagName || (parent.tagName != 'OL' && parent.tagName != 'UL'))) {
    parent = parent.parentNode;
  }
  if (parent.tagName.toLowerCase != 'ol' && parent.tagName.toLowerCase != 'ul') {
    var children = parent.childNodes;
    for (var k = 0; k < children.length; k++) {
      if (typeof children[k].childNodes[0] != 'undefined' && children[k].childNodes[0].tagName == 'SPAN') toggleNoticeDiv(children[k].childNodes[0], true);
    }
  }
  if (wasOff) toggleNoticeDiv(item);
}
function initSearch(start) {
  document.getElementById('searchbox').value = start;
  document.getElementById('searchbox').focus();
}
function showFrameMsg(msg) {
  document.getElementById('framemsg').innerHTML = msg;
  document.getElementById('framemsg').parentNode.style.display = 'inline';
}
function showMatches(element, test) {
  if (element.value.length < 2) return;
  var pat = escape(element.value).replace('+', '%2B');
  ajaxGetAndCallWith('getSearchMatches.php?pat=' + pat, rebuildMenu, element);
}
function rebuildMenu(element, content) {
  if (!content) {
    showmenu(element, '');
    return;
  }
  if (content.indexOf('GOTO:') == 0) {
    document.location.href = content.substring(5);
    return;
  } else if (content.indexOf('POP:') == 0) {
    var parts = content.substring('POP:'.length).split(':');
    var w = window.open("", parts[1], 'toolbar=0,location=0,directories=0,status=0,resizable=yes,menubar=0,scrollbars=yes,width=' + 800 + ',height=' + 800);
    w.document.location.href = parts[2];
    if (w) w.focus();
    return;
  }
  var url = 'client-edit.php?tab=services&id=';
  if (content.indexOf('PROVIDERS:') == 0) {
    url = 'provider-edit.php?id=';
    content = content.substring('PROVIDERS:'.length);
  } else if (content.indexOf('KEYS:') == 0) {
    url = 'key-edit.php?client=';
    content = content.substring('KEYS:'.length);
  }
  var html = '';
  var arr = content.split('||');
  for (var i = 0; i < arr.length; i++) {
    if (arr[i] == '--') html += ' < hr > ';
    else if (arr[i] == '-+-') html += ' < hr style = "border: 0;color: #9E9E9E;background-color: #9E9E9E;height: 1px;" > ';
    else {
      var line = arr[i].split('|');
      html += ' < a href = \'' + url + line[0] + "\' onFocus='this.className=\"popitfocus\"' onBlur='this.className=\"popitmenu\"'>" + line[1] + "" + '</a>';
    }
  }
  showmenu(element, html);
}
function showNotificationNoMore(i, closebox) {
  ajaxGet("shownomore.php?id="+i, null);
  if(typeof closebox != 'undefined' && closebox)
    $.fn.colorbox.close();
}



























