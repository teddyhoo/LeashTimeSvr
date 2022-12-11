/* lightbox-layer-featherlight.js 
This is a library that isolates LeashTime from the chosen lightbox implementation.

Currently that implementation is featherlight.

Hint: parent.window.$.featherlight.current().close(); 

*/

function lightBoxIFrame(url, width, height, maxwidth) {
	if(typeof maxwidth == undefined) maxwidth = '80%';
	$.featherlight({iframe: url, maxwidth: '80%', iframeWidth: width,		iframeHeight: height});
//$.featherlight({iframe: 'editor.html', iframeMaxWidth: '80%', iframeWidth: 500, iframeHeight: 300});
}

function lightBoxText(str, width, height) {
	$.featherlight("<div style='width:"+width+"px; height: "+height+"'>"+str+"</div>");
}

function lightBoxHTML(html, width, height) {
	$.featherlight("<div style='width:"+width+"px; height: "+height+"'>"+html+"</div>", {type: 'html'});
}

function lightBoxIFrameClose() {
	parent.window.$.featherlight.current().close(); 
}