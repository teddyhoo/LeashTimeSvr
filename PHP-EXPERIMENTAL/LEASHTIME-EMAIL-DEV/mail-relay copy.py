from smtplib import SMTP
from smtplib import SMTPException
from email.message import EmailMessage
from email.mime.multipart import MIMEMultipart
import email.utils
import os
import sys
import ssl
import re
from datetime import datetime
from filelock import Timeout, FileLock

smtp_port = 587
log_file_ = './logtest/sentlog-py.txt'
lock_file = './logtest/sentlog-py.lock'
lock = FileLock(lock_file, timeout = 2)

def convertHMTL(htmltext):
	## &lt\; BECOMES <
	htmltext = re.sub(r'\x26lt\x5C\x3B', '\x3C', htmltext)
	## &gt\; BECOMES >
	htmltext = re.sub(r'\x26gt\x5C\x3B', '\x3E', htmltext)
	## &quot\; BECOMES "
	htmltext = re.sub(r'\x26quot\x5C\x3B', '\x22', htmltext)
	## maps to &#039\; BECOMES '
	htmltext = re.sub(r'\x26\x23039\x5C\x3B', '\x27', htmltext)
	## regex maps to \; 
	htmltext = re.sub(r'\x5C\x3B', '\x3B', htmltext)
	## regex maps to \!
	htmltext = re.sub(r'\\x21', '!', htmltext)
	## regex maps to \$
	htmltext = re.sub(r'\\x24','$',htmltext)
	return htmltext

def addCC(cc_recipients, message):
	message['Cc'] = cc_recipients
	return message
def blindCC(bcc_recipients, message):
	message['BCC'] = bcc_recipients
	return message
def addReplyTo(replyTo, message):
	message['ReplyTo'] = replyTo
	return message
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
	html_message = EmailMessage()
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
	for var in enumerate(html_message.items()):
		print (var)
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

def sendServer(smtp_addr, username, password, message, recipient):
	context = ssl.create_default_context()
	try:
		server = SMTP(smtp_addr, smtp_port)	
		server.set_debuglevel(2)
		server.ehlo()
		server.starttls(context = context)
		server.ehlo()
		server.login(username,password)
		server.sendmail(username, recipient, message.as_string())
		server.quit()
		writeLog(message, 'success')
		print('OK');
	except :
		##raised_exception  = type(sys.exc_info()[0])
		exception_text = sys.exc_info()[0]
		writeLog(message, 'fail', exception_text)
		print(exception_text, 'fail')

if __name__ == '__main__':
	msgdic = {}
	msgdic['sender'] = sys.argv[1]
	msgdic['replyTo'] = sys.argv[2]
	msgdic['receiver'] = sys.argv[3]
	msgdic['provider'] = sys.argv[4]
	msgdic['username'] = sys.argv[5]
	msgdic['password'] = sys.argv[6]
	msgdic['subject'] = sys.argv[7]
	msgdic['body'] = sys.argv[8]
	msgdic['body'] = convertHMTL(msgdic['body'])
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

	if msgdic['html'] == 'html':
		msg = createHTMLMessage(msgdic['replyTo'], msgdic['username'], msgdic['subject'], msgdic['receiver'], msgdic['body'],msgdic['cc'], msgdic['bcc'])
		sendServer(msgdic['provider'], msgdic['username'], msgdic['password'], msg, msgdic['receiver'])
	else: 
		msg = createSinglePartMIME(msgdic['replyTo'], msgdic['username'], msgdic['subject'], msgdic['receiver'],  msgdic['body'], msgdic['cc'], msgdic['bcc'])
		sendServer(msgdic['provider'], msgdic['username'], msgdic['password'], msg, msgdic['receiver'])
'''
	python3 mail-relay.py  notice@leashtime.com "Ted Hooban" teddyhoo@hotmail.com smtp.1and1.com  notice@leashtime.com  not11ce "TESTING THE EMAIL SUBJECT" "<HTML><P>testing the email body" html  ted@leashtime.com ted@leashtime.com
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
