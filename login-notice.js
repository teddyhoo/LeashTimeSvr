// login-notice.js
// var notices
var noticeNum = 0;

function nextNotice() {
  noticeNum++;
  if(noticeNum >= notices.length) noticeNum=0;
  showNotice();
}

function lastNotice() {
  noticeNum--;
  if(noticeNum < 0) noticeNum=notices.length-1;
  showNotice();
}

function showNotice() {
  document.getElementById('noticetext').innerHTML=notices[noticeNum];
  document.getElementById('noticediv').style.display = 'block';
}

function hideNotice() {
  document.getElementById('noticediv').style.display = 'none';
}


showNotice();
