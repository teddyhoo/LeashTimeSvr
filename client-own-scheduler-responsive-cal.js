// client-own-scheduler-responsive-cal.js

var testdata = 
	{"start":"07/24/2019","end":"08/27/2019","servicecode":"29",
	 "prettypets":"Apple, Bubbles","pets":"Apple,Bubbles",
	 "visits":[{"date":"07/24/2019","servicecode":"29","timeofday":"5:00 pm-7:00 pm","pets":"Apple,Bubbles"},
		 {"date":"7/25/2019","servicecode":"29","timeofday":"9:00 am-11:00 am","pets":"Apple,Bubbles"},
		 {"date":"7/25/2019","servicecode":"29","timeofday":"3:00 pm-5:00 pm","pets":"Apple,Bubbles"},
		 {"date":"7/25/2019","servicecode":"29","timeofday":"7:00 pm-9:00 pm","pets":"Apple,Bubbles"},
		 {"date":"7/26/2019","servicecode":"29","timeofday":"9:00 am-11:00 am","pets":"Apple,Bubbles"},
		 {"date":"7/26/2019","servicecode":"29","timeofday":"3:00 pm-5:00 pm","pets":"Apple,Bubbles"},
		 {"date":"7/26/2019","servicecode":"29","timeofday":"7:00 pm-9:00 pm","pets":"Apple,Bubbles"},
		 {"date":"07/27/2019","servicecode":"29","timeofday":"6:00 am-9:00 am","pets":"Apple,Bubbles"}],
	 "note":"Please be on time.","totaldays":4,"visitdays":4};
	 
function displayCalendarForJSONData(json) {
	displayCalendarForPacket(JSON.parse(json))
}
	 
function displayCalendarForPacket(packet) {
	 document.getElementById("calendar").innerHTML=calendarsForPacket(packet).join('');
}
	
function calendarsForPacket(packet) {
	var calContent = [];
	calContent.push(displayCalendar(packet.start, packet));
	let startDate = new Date(Date.parse(packet.start));
	let endDate = new Date(Date.parse(packet.end));
	if(startDate.getMonth()+'/'+startDate.getFullYear()
			!= endDate.getMonth()+'/'+endDate.getFullYear()) {
		calContent.push(displayCalendar(packet.end, packet));
	}
	return calContent;
}

function getCalendarDayVisits(packet, mdy) {
	// mdy: 12/31/2019
	var seconds = Date.parse(mdy);
	var visits = [];
	packet.visits.forEach(function(visit, num, arr) {
		if(Date.parse(visit.date) == seconds)
			visits.push(visit);
	});
	//if(visits.length) alert(mdy+": "+visits.length+' visits');
	return visits;
}
	 
function displayCalendar(dateString, packet){
 
 
 var htmlContent ="";
 var FebNumberOfDays ="";
 var counter = 1;
 
 var dateNow = new Date();
 var today = ((dateNow.getMonth())+1)+'/'+dateNow.getDate()+'/'+dateNow.getFullYear(); //alert(today);

 
 var forThisDate = new Date(Date.parse(dateString));
 var month = forThisDate.getMonth();  // REMINDER: month is zero-based

 var nextMonth = month+1; //+1; //Used to match up the current month with the correct start date.
 var prevMonth = month -1;
 var day = forThisDate.getDate();
 var year = forThisDate.getFullYear();
 
 //alert(year+'/'+month+'/'+day);
 
 //Determing if February (28,or 29)  
 if (month == 1){
    if ( (year%100!=0) && (year%4==0) || (year%400==0)){
      FebNumberOfDays = 29;
    }else{
      FebNumberOfDays = 28;
    }
 }
 
 
 // names of months and week days.
 var monthNames = ["January","February","March","April","May","June","July","August","September","October","November", "December"];
 //var dayNames = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday", "Saturday"];
 var dayNames = ["Su","M","Tu","W","Th","F", "Sa"];
 var dayPerMonth = ["31", ""+FebNumberOfDays+"","31","30","31","30","31","31","30","31","30","31"]
 
 
 // days in previous month and next one , and day of week.
 var nextDate = new Date(nextMonth +' 1 ,'+year);
 var weekdays= nextDate.getDay();
 var weekdays2 = weekdays
 var numOfDays = dayPerMonth[month];
     
 
 
 
 // this leave a white space for days of pervious month.
 while (weekdays>0){
    htmlContent += "<td class='monthPre'></td>";
 
 // used in next loop.
     weekdays--;
 }
 
 // loop to build the calander body.
while (counter <= numOfDays){
 
     // When to start new line.
    if (weekdays2 > 6){
        weekdays2 = 0;
        htmlContent += "</tr>\n<tr>";
    }
 
 
 
    // if counter is current day.
    // highlight current day using the CSS defined in header.
    let mouseOut = ''; //" onMouseOut='this.style.background=\"\"'";// background=\"#FFFFFF\"
    let mouseOver = ''; //" onMouseOver='this.style.background=\"#FF0000\"'";
    let tdClass = "monthNow";
    let title = [];
    let clickAction = '';
    
    //if (counter == day){
		let thisDate = (month+1)+'/'+counter+'/'+year;
    if (thisDate == today){
				mouseOut = " onMouseOut='this.style.background=\"\"; this.style.color=\"#00FF00\"'"; // background=\"#FFFFFF\"
				//mouseOver = " onMouseOver='this.style.background=\"#FF0000\"; this.style.color=\"#FFFFFF\"'";
     		tdClass = "dayNow";
     		title.push('This is today.');
  	}
  	let visits = getCalendarDayVisits(packet, thisDate);
  	if(visits.length) {
			tdClass += ' visitsDay';
			title.push('Visits requested: '+visits.length);
			//clickAction = " onclick='showVisits(\""+thisDate+"\")'";
		}
		clickAction = " onclick='showVisits(\""+thisDate+"\")'";

  	htmlContent += "<td title='"+title.join(' ')+"' class='"+tdClass+"'"+mouseOver+mouseOut+clickAction+">"+counter+"</td>";
    
    weekdays2++;
    counter++;
 }
 
 
 
 // building the calendar html body.
 var calendarBody = "<table class='calendar'><tbody> \n<tr class='monthNow'><th colspan='7'>"
 +monthNames[month]+" "+ year +"</th></tr>";
 //calendarBody +="<tr class='dayNames'>  <td>Sun</td>  <td>Mon</td> <td>Tues</td>"+
 //"<td>Wed</td> <td>Thurs</td> <td>Fri</td> <td>Sat</td> </tr>";
 calendarBody +="\n<tr class='dayNames'>";
 dayNames.forEach(function(nm, num, arr) {
	  calendarBody +="<td>"+nm+"</td>";
	});
 calendarBody +="</tr>";

 calendarBody += "\n<tr>";
 calendarBody += htmlContent;
 calendarBody += "</tr>\n</tbody></table>";
 return calendarBody;
}

function showVisits(thisDate) { // temporary
alert(thisDate);
}
