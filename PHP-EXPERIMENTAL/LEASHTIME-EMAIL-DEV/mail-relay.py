from smtplib import SMTP
from smtplib import SMTPException
from email.message import EmailMessage
from email.mime.multipart import MIMEMultipart
import email.policy
import email.utils
import os
from codecs import StreamRecoder
import codecs

import sys
import ssl
import re
from datetime import datetime
from filelock import Timeout, FileLock
from html import unescape
from html import escape
from html.parser import HTMLParser

smtp_port = 587
log_file_ = './logtest/sentlog-py.txt'
lock_file = './logtest/sentlog-py.lock'
lock = FileLock(lock_file, timeout = 2)
##unescape = HTMLParser().unescape

def addCC(cc_recipients, message):
	message['Cc'] = cc_recipients
	return message
def blindCC(bcc_recipients, message):
	message['BCC'] = bcc_recipients
	return message
def addReplyTo(replyTo, message):
	message['ReplyTo'] = replyTo
	return message
def cleanQuotes(decodetext):
	decodetext = re.sub(r'\\\'','&quot;',decodetext)
	decodetext = re.sub(r'\\"','&quot;',decodetext)
	return decodetext
def cleanEscapeCharacter(decodetext, type=None):
	##decodetext = re.sub(r'&amp;#36;','$',decodetext) ## $
	##decodetext = re.sub(r'&amp;nbsp;','',decodetext)
	##decodetext = re.sub(r'&amp;amp;','&amp;',decodetext)
	##ecodetext = re.sub(r'&nbsp;','',decodetext)
	##decodetext = re.sub(r'&quot;','"',decodetext)
	##decodetext = re.sub(r'&Acirc;','',decodetext)
	##decodetext = re.sub(r'&lt;','<', decodetext)
	##decodetext = re.sub(r'&gt;','>', decodetext)
	decodetext = re.sub(r'&\\#36\\;','$',decodetext) ## $
	decodetext = re.sub(r'&\\#33\\;','!',decodetext)   ## !
	decodetext = re.sub(r'&\\#34\\;','\"',decodetext) ## "
	decodetext = re.sub(r'&\\#35\\;','#',decodetext)## #
	##decodetext = re.sub(r'&\\#37\\;','%',decodetext) ## %
	decodetext = re.sub(r'&\\#38\\;','&',decodetext) ## &
	decodetext = re.sub(r'&\\#39\\;','\'',decodetext) ## '
	decodetext = re.sub(r'&\\#43\\;','+',decodetext) ## +
	decodetext = re.sub(r'&\\#59\\;',';',decodetext) ## ;
	decodetext = re.sub(r'&\\#60\\;','<',decodetext) ## <
	decodetext = re.sub(r'&\\#62\\;','>',decodetext) ## >
	decodetext = re.sub(r'&\\#63\\;','?',decodetext) ## ?
	decodetext = re.sub(r'&\\#64\\;','@',decodetext) ## @	
	decodetext = re.sub(r'&\\#94\\;','^',decodetext) ## ^
	return decodetext
def convertPassword(passwordgiven):
	passwordgiven = re.sub(r'&\\#33\\;','!',passwordgiven)   ## !
	passwordgiven = re.sub(r'&\\#36\\;','$',passwordgiven) ## $
	passwordgiven = re.sub(r'&\\#63\\;','?',passwordgiven) ## ?
	passwordgiven = re.sub(r'&\\#38\\;','&',passwordgiven)
	passwordgiven = re.sub(r'&\\#35\\;','#',passwordgiven) ##   #
	passwordgiven = re.sub(r'&\\#37\\;','%',passwordgiven) ## %
	passwordgiven = re.sub(r'&\\#64\\;','@',passwordgiven) ## @
	passwordgiven = re.sub(r'&\\#61\\;','=',passwordgiven)  ## =
	passwordgiven = re.sub(r'&\\#43\\;','+',passwordgiven) ## +
	passwordgiven = re.sub(r'&\\#42\\;','*',passwordgiven) ## *
	passwordgiven = re.sub(r'&\\#94\\;','^',passwordgiven)  ## ^
	passwordgiven = re.sub(r'&\\#59\\;',';',passwordgiven)  ## ;
	passwordgiven = re.sub(r'&\\#62\\;','>',passwordgiven) ## >
	passwordgiven = re.sub(r'&\\#60\\;','<',passwordgiven)  ## <
	passwordgiven = re.sub(r'&\\#46\\;','.',passwordgiven)  ## .
	passwordgiven = re.sub(r'&\\#58\\;',':',passwordgiven)  ## :
	passwordgiven = re.sub(r'&\\#34\\;','\"',passwordgiven)  ## "
	passwordgiven = re.sub(r'&\\#39\\;','\'',passwordgiven)  ## ''
	return passwordgiven
def createMultiPartMIME(receiver, subject, message):
	msg = MIMEMultipart('alternative')
	msg['Subject'] = subject
	msg['From'] = username
	msg['To'] = receiver
	msg['Message-ID'] = email.utils.make_msgid()
	msg['Date'] = email.utils.formatdate(localtime=1)
def createSinglePartMIME(replyto, username, subject, receiver, message):
	msg = EmailMessage()
	msg.set_content(message)
	msg['Subject'] = subject
	msg['Reply-to'] = replyto
	msg['From'] = username
	msg['To'] = receiver
	msg['Message-ID'] = email.utils.make_msgid()
	msg['Date'] = email.utils.formatdate(localtime=1)
	return msg
def createHTMLMessage(replyto, username, subject, receiver,htmlmessage, cc=None, bcc=None):
	html_message = EmailMessage(email.policy.SMTP)
	html_message['Subject'] = subject
	html_message['From'] = username
	html_message['Reply-to'] = replyto
	html_message['To'] = receiver
	if cc != None:
		html_message['cc'] = cc
	if bcc != None:
		html_message['bcc'] = bcc
	html_message['Message-ID'] = email.utils.make_msgid()
	html_message['Date'] = email.utils.formatdate(localtime = 1)
	html_message.set_content(htmlmessage, subtype='html')
	return html_message
def writeLog(message, status, exception_text = None):
	timestamp_now = str(datetime.now())
	write_string = ''
	if status == 'success':
		write_string = 'SUCCESS: ' + message['Message-ID'] + '\nTIMESTAMP:    ' + timestamp_now	 + '\nSENT: '  + message['Date'] + '\nFROM:                '  + message['From'] + '\nTO:                       '  + message['To'] + '\nSUBJECT:         ' + message['Subject'] + '\n'
		write_string = write_string + '\n' + str(message.items()) + '\n'
		write_string = write_string + '\n' + message.get_content() + '\n'
	elif status == 'fail':
		write_string = 'FAIL: ' + message['Message-ID'] + '\nTIMESTAMP:    ' + timestamp_now	 + '     SENT: '  + message['Date'] + '\nFROM:                '  + message['From'] + '\nTO:                       '  + message['To'] + '\nSUBJECT:         ' + message['Subject'] + '\n'
		write_string = write_string + '\n' + str(exception_text) + '\n'
		write_string = write_string + '\n' + str(message.items()) + '\n'
		write_string  = write_string + '\n' + message.get_content() + '\n'
	try:
		with lock.acquire(timeout=10): 
			with open(log_file_, "a") as f:
				f.write(write_string)	
	except Timeout:
		print('FAILED TO OPEN FILE')
def writeDebug(msgdic):
	timestamp_now = str(datetime.now())
	write_string = 	'\nSENDER: ' +msgdic['sender'] + '\nREPLY-TO: ' + msgdic['replyTo'] + '\nRECEIVER: ' + msgdic['receiver'] + '\nPROVIDER: ' + msgdic['provider'] +'\nUSERNAME: ' + msgdic['username'] +' \nPASSWORD: ' + msgdic['password'] +' \nSUBJECT: ' + msgdic['subject'] +'\nBODY:' + msgdic['body'] + ' \nTYPE: ' + msgdic['html'] + '\n'
	try:
		with lock.acquire(timeout=10): 
			with open(log_file_, "a") as f:
				f.write(write_string)	
	except Timeout:
		print('FAILED TO OPEN FILE')
def removeTag(message):
	print (message.get_content())
	message = re.sub(r'=3D','=',message)
	return message
def sendServer(smtp_addr, username, password, message, recipient):
	context = ssl.create_default_context()
	try:
		server = SMTP(smtp_addr, smtp_port)	
		##server.set_debuglevel(1)
		server.ehlo()
		server.starttls(context = context)
		server.ehlo()
		server.login(username,password)
		server.sendmail(username, recipient, message.as_string())
		server.quit()
		writeLog(message, 'success')
		print('OK');
	except :
		exception_text = sys.exc_info()[0]
		writeLog(message, 'fail', exception_text)
		print(exception_text, 'fail')

def handleBodyFormat(body):
	##codecs.encode(body, encoding='utf-8')
	body = re.sub(r'\\";','\'', body)  ## ''
	body = re.sub(r'\\"', '\'', body)
	escape(body)
	return body

if __name__ == '__main__':
	msgdic = {}
	msgdic['sender'] = sys.argv[1]
	msgdic['replyTo'] = sys.argv[2]
	msgdic['receiver'] = cleanEscapeCharacter(sys.argv[3])
	msgdic['provider'] = sys.argv[4]
	msgdic['username'] = sys.argv[5]
	msgdic['password'] = convertPassword(sys.argv[6])
	msgdic['subject'] = convertPassword(sys.argv[7])
	msgdic['body'] = handleBodyFormat(sys.argv[8])
	if sys.argv[9] == 'html':
		msgdic['html'] = 'html'
	elif sys.argv[9] == 'multipart':
		msgdic['html'] = 'multipart'
	else:
		msgdic['html'] = 'html'

	num_param  = len(sys.argv)
	if num_param > 10:
		msgdic['cc'] = sys.argv[10]
	else:
		msgdic['cc'] = None
	if num_param > 11:
		msgdic['bcc'] = sys.argv[11]
	else:
		msgdic['bcc'] = None

	##print ('\nPYTHON REQUEST PARMS\n' + sys.argv[0] + '\n' + sys.argv[1] + '\n' + sys.argv[2] + ' \n' + sys.argv[3] +' \n' + sys.argv[5] +' \n\n' + sys.argv[6] +'\n\n' + sys.argv[7] +' \n' + sys.argv[8] + '\n ' + sys.argv[9] + '\n' )
	##print('PYTHON\n\n')
	##print ('\nSENDER: ' +msgdic['sender'] + '\nREPLY-TO: ' + msgdic['replyTo'] + '\nRECEIVER: ' + msgdic['receiver'] + '\nPROVIDER: ' + msgdic['provider'] +'\nUSERNAME: ' + msgdic['username'] +' \nPASSWORD: ' + msgdic['password'] +' \nSUBJECT: ' + msgdic['subject'] +'\nBODY:' + msgdic['body'] + ' \nTYPE: ' + msgdic['html'])
	
	if msgdic['html'] == 'html':
		msg = createHTMLMessage(msgdic['replyTo'], msgdic['username'], msgdic['subject'], msgdic['receiver'], msgdic['body'],msgdic['cc'], msgdic['bcc'])
		sendServer(msgdic['provider'], msgdic['username'], msgdic['password'], msg, msgdic['receiver'])
	else: 
		msg = createSinglePartMIME(msgdic['replyTo'], msgdic['username'], msgdic['subject'], msgdic['receiver'],  msgdic['body'], msgdic['cc'], msgdic['bcc'])
		sendServer(msgdic['provider'], msgdic['username'], msgdic['password'], msg, msgdic['receiver'])
	












'''
	SAMPLE TEST


python3 mail-relay.py

-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

python3 mail-relay.py "Ted Hooban" "hello@sarahsits.com" "ted@leashtime.com" secure.emailsrvr.com hello@sarahsits.com pumaS998$$ "Upcoming Schedule" "DO NOT RESPOND TO THIS EMAIL.  THIS MAILBOX IS NOT MONITORED.&lt\;p&gt\;Dear Lisa Loving,&lt\;p&gt\;Here is your upcoming schedule:&lt\;p&gt\;#SCHEDULE#&lt\;p&gt\;&lt\;p&gt\;&lt\;br&gt\;Shameless Canine Loving Care&lt\;br&gt\;Sarah Sits-N-Stays, Inc.&lt\;br&gt\;www.sarahsits.com&lt\;br&gt\;https://www.facebook.com/pages/Sarah-Sits-N-Stays/120952741262860?ref=hl
" html
----------------------------------------BASIC-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------

python3 mail-relay.py  notice@leashtime.com "Ted Hooban" teddyhoo@hotmail.com smtp.1and1.com  notice@leashtime.com  not11ce "TESTING THE EMAIL SUBJECT" "<HTML><P>testing the email body" html  ted@leashtime.com ted@leashtime.com
 
-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
  		## 0 - script name
		## 1 - sender
		## 2 - replyTo
		## 3 - receiver
		## 4 - smtp host provider 
		## 5  - user name
		## 6 - password
		## 7 - subject
		## 8 - body
		## 9 - html
		## 10 - cc
		## 11 - bcc
		## 12 - ExtraHeaders
		## 13 - Attachment Info
'''
'''
print (str(len(sys.argv)))
print ('1: '  + sys.argv[1])
print ('2: '  + sys.argv[2])
print ('3: '  + sys.argv[3])
print ('4: '  + sys.argv[4])
print ('5: '  + sys.argv[5])
print ('6: '  + sys.argv[6])
print ('7: '  + sys.argv[7])
print ('8: '  + sys.argv[8])
print ('9: '  + sys.argv[9])
'''