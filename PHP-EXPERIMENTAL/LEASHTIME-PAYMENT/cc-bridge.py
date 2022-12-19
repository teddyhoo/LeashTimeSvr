import requests
import os
import sys
from datetime import datetime
from filelock import Timeout, FileLock
import ssl
import re

log_file = './cctest/cclog.txt'
lock_file = './cctest/cclog.lock'
lock = FileLock(lock_file, timeout = 2)
gateway = 'TXP'

def writeTransactionLog(serverResponse):
	timestamp_now = str(datetime.now())
	try:
		with lock.acquire(timeout=10): 
			with open(log_file, "a") as f:
				f.write(serverResponse)	
	except Timeout:
		print('FAILED TO OPEN FILE')
	
def handleResponse(transact_response, transact_data):
	details = '' 
	if gateway == 'AUTHORIZE':
		details = transact_response.text.split('|')
		for detail in details:
			if detail == 'This transaction has been approved.':
				writeTransactionLog('AUTHORIZE SUCCESS: ' + transact_response.text + '\n' +transact_data + '\n\n' )
			else:
				decline_check = re.match('The transaction has been declined', detail)
				if decline_check != None:
					writeTransactionLog('AUTHORIZE FAIL: ' + transact_response.text  + '\n' +transact_data + '\n\n')
	
	elif gateway == 'TRANSFIRST':
		writeTransactionLog('TRANSFIRST REPLY: ' + transact_response.text + '\n' +transact_data + '\n\n' )
	elif gateway == 'SOLVERAS':
		writeTransactionLog('SOLVERAS REPLY: ' + transact_response.text + '\n' +transact_data + '\n\n' )
		##for detail in details:
			##if detail == 'This transaction has been approved.':
			##	writeTransactionLog('AUTHORIZE SUCCESS: ' + transact_response.text + '\n' +transact_data + '\n\n' )
			##else:
			##	decline_check = re.match('The transaction has been declined', detail)
			##	if decline_check != None:
			##		writeTransactionLog('AUTHORIZE FAIL: ' + transact_response.text  + '\n' +transact_data + '\n\n')			
	else:
		details = transact_response.text.split('&')
		for detail in details:
			core = re.sub(r'\"','',detail)
			try:
				k,v = core.split('=')
				if k == 'ResponseCode':
					writeTransactionLog('TSYS SUCCESS: ' + transact_response.text + '\n' +transact_data + '\n\n' )
				elif k == 'ErrorCode':
					writeTransactionLog('TSYS FAIL: ' + transact_response.text  + '\n' +transact_data + '\n\n')
			except:
				noval = k

	passvar = transact_response.text
	print (passvar)

def processTransaction(content_type, gateway_endpoint,transact_data):
	transact = requests.post(gateway_endpoint,headers={"Content-Type" : content_type}, data=transact_data)
	handleResponse(transact, transact_data)
	
if __name__ == '__main__':
	if sys.argv[4] != None:
		gateway = sys.argv[4]

	processTransaction(sys.argv[1], sys.argv[2], sys.argv[3])

	'''
	processTransaction('application/x-www-form-urlencoded','https://secure2.authorize.net/gateway/transact.dll','x_login=2D7Dn9Vjj&x_tran_key=4WZ7adDf8Dz53Q5u&x_type=AUTH_CAPTURE&x_relay_response=0&x_delim_data=1&x_delim_char=|&x_duplicate_window=120&x_amount=0.01&x_exp_date=05/2025&x_card_num=5268760045342198&x_card_code=665&x_first_name=Edwardq&x_last_name=AAAAAAAA&x_address=601+N+Buchanan+St&x_city=Arlington&x_state=VA&x_zip=33333&x_country=USA&x_phone=5713174584')
	python3 cc-bridge-test.py application/x-www-form-urlencoded https://secure2.authorize.net/gateway/transact.dll "x_login=2D7Dn9Vjj&x_tran_key=4WZ7adDf8Dz53Q5u&x_type=AUTH_CAPTURE&x_relay_response=0&x_delim_data=1&x_delim_char=|&x_duplicate_window=120&x_amount=0.01&x_exp_date=05/2025&x_card_num=5268760045342198&x_card_code=665&x_first_name=Edwardq&x_last_name=Hooban&x_address=601+N+Buchanan+St&x_city=Arlington&x_state=VA&x_zip=22203&x_country=USA&x_phone=5713174584" AUTHORIZE
	python3 cc-bridge-test.py application/x-www-form-urlencoded https://post.transactionexpress.com/PostMerchantService.svc/CreditCardSale "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&t&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=5713174584" TSYS

	DROP IN TEXT SCENARIO 
	msgdic ={}
	msgdic['GatewayID'] = '9242093875'
	msgdic['RegKey'] = 'E33TXDB8AAT4ZHJ6'
	msgdic['IindustryCode'] = '2'
	msgdic['AccountNumber'] = '5268760045342198'
	msgdic['CVV2'] = '665'
	msgdic['ExpirationDate'] = '2505'
	msgdic['Amount'] = '100'
	msgdic['FullName'] = 'Edwardq Hooban'
	msgdic['Address1'] = '601 N Buchanan St'
	msgdic['City'] = 'Arlington'
	msgdic['State'] = 'VA'
	msgdic['Zip'] = '22203'
	msgdic['PhoneNumber'] = '571-317-4584'
	python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"
	'''
	##handleResponse("ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=")
	## EXAMPLE RESPONSE
	##ResponseCode=00&
	##tranNr=004441059122&
	##PostDate=2022-11-17T10:15:08.000&
	##Amount=000000000100&
	##AmtDueRemaining=0&
	##CardBalance=&
	##Auth=F2FE40&
	##AVSCode=Y&
	##CVV2Response=M&
	##CAVVResultCode=



