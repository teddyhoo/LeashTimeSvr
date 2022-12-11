// mobile-buttons.js

function expandButton(el, factor) {
	if(el.style.width) el = el.style;
	start = (el.width+' X '+el.height);
	el.width = parseInt(el.width * factor);
	//el.height = parseInt(el.height * factor);
	//alert(start + " * 2 = "+el.width+' X '+el.height);
}

function shrinkButton(el, factor) {
	expandButton(el, 1 / factor);
}
