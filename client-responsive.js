// client-responsive.js

function getMenuItems() {
	$.ajax({
				url: 'menu-client.php?ajax=1',
				dataType: 'json', // comment this out to see script errors in the console
				type: 'post',
				//contentType: 'application/json',
				//data: JSON.stringify(data),
				processData: false,
				success: populateMenuItems,
				error: menuItemFetchFailed
				});
}

function populateMenuItems(data, textStatus, jQxhr) {
	// data are menuitems that may include some or all of the keys listed in icons
  // {home: {label: "HOME", target: "index.php"}, schedule: {label: "REQUEST VISITS", target: "client-sched-makerV2.php"}, ...}
	var mainmenuKeys = 'home,schedule,profile,account,messages,contactus,clientdocs'.split(',');
	var identitymenuKeys = 'password,creditcard,contactus,logout'.split(',');
	var othermenuKeys = 'clientdocs'.split(','); // TBD: decide where to put clientdocs.  Footer?
	var icons = {
		home: 'md md-home',
		schedule: 'fa fa-calendar',
		profile: 'fa fa-paw',
		account: 'fa fa-credit-card',
		messages: 'fa fa-inbox',
		contactus: 'fa fa-fw fa-envelope',
		clientdocs: 'fa fa-fw fa-info-circle', //fa-newspaper-o
		password: 'fa fa-fw fa-shield text-center',
		creditcard: 'fa fa-credit-card',
		logout: 'fa fa-fw fa-power-off text-danger'
	};
	mainmenuKeys.forEach(function(key, index) {
		if(data[key]) {
			$("#main-menu").append('<li>\
                                <a href="'+data[key].target+'" class="white-text">\
                                    <div class="gui-icon  white-text"><i class="'+icons[key]+'"></i></div>\
                                    <span class="title white-text">'+data[key].label+'</span>\
                                </a>\
                            </li>');
		}
	});
	
	identitymenuKeys.forEach(function(key, index) {
		if(data[key]) {
			$("#identity-menu").append(
				'<li><a href="'+data[key].target+'"><i class="'+icons[key]+'"></i> '+data[key].label+'</a></li>');
			$("#identity-menu0").append(
				'<li><a href="'+data[key].target+'"><i class="'+icons[key]+'"></i> '+data[key].label+'</a></li>');
		}
	});
	
}

function menuItemFetchFailed(jqXhr, textStatus, errorThrown) {
	let message = 'Error encountered:<br>'+errorThrown;
	console.log(message );
}