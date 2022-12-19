import requests
import os
import sys
from datetime import datetime
##from filelock import Timeout, FileLock
import ssl

##log_file = './cc-test/cclog.txt'
##lock_file = './cc-test/cclog.lock'
##lock = FileLock(lock_file, timeout = 2)

## TSYS TRANSACTION EXPRES CC AND ACH CONSOLIDATED PLATFORM
cc_endpoint = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardSale'
cc_void_endpoint = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardVoid'
cc_refund_endpoint = 'https://post.transactionexpress.com/PostMerchantService.svc/CreditCardRefund'
ach_endpoint = 'https://post.transactionexpress.com/PostMerchantService.svc/ACHSale'
ach_void_refund_endpoint = 'https://post.transactionexpress.com/PostMerchantService.svc/ACHRefundOrVoid'

## SOLVERAS AND TSYS SEPARATE CC AND ACH
cc_endpoint_nmi = 'https://secure.nmi.com/api/v2/three-step/'
cc_void_endpoint_nmi = 'https://secure.nmi.com/api/v2/three-step/'

authorize_net_cc_gateway = 'https://secure2.authorize.net/gateway/transact.dll'

def writeTransactionLog(serverResponse):
	print (serverResponse)
	'''
	timestamp_now = str(datetime.now())
	try:
		with lock.acquire(timeout=10): 
			with open(log_file_, "a") as f:
				f.write(serverResponse)	
	except Timeout:
		print('FAILED TO OPEN FILE')
		'''
def handleResponse(transact_response, transact_data):
	print ('HANDLE RESPONSE: ' +transact_response)
	details = transact_response.split('&')
	for detail in details:
		core = detail[1:len(detail)-1]
		k,v = core.split('=')

		if k == 'ResponseCode' and v == '00':
			print ('SUCCESS')
			writeTransactionLog('SUCCESS: ' + transact_response + '\n' + transact_data + '\n')
		elif k == 'ErrorCode':
			print ('FAIL')
			writeTransactionLog('FAIL: ' + transact_response + '\n' + transact_data + '\n')

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

def processTransaction(transact_data, content_type, gateway_url):
	## transact = requests.post(gateway_url,headers = {"Content-Type" : content_type}, transact_data)
	## handleResponse(transact.text, transact_data)
	
	'''
	if type == 'CreditCardSale':
		transact = requests.post(cc_endpoint, headers={"Content-Type" : content_type}, data=transact_data)
		handleResponse(transact.text)
	elif type == 'CreditCardVoid':
		transact = requests.post(cc_void_endpoint, headers={"Content-Type" : content_type}, data=transact_data)
		handleResponse(transact.text)
	elif type == 'CreditCardRefund':
		transact = requests.post(cc_refund_endpoint, headers={"Content-Type" : content_type}, data=transact_data)
		handleResponse(transact.text)
	elif type == 'ACHSale':
		transact = requests.post(ach_endpoint, headers={"Content-Type" : content_type}, data=transact_data)
		handleResponse(transact.text)
	elif type == 'ACHSaleOrVoid':
		transact = requests.post(ach_endpoint, headers={"Content-Type" : content_type}, data=transact_data)
		handleResponse(transact.text)
	'''
	##handleResponse("ResponseCode=00&tranNr=004441059122&PostDate=2022-11-17T10:15:08.000&Amount=000000000100&AmtDueRemaining=0&CardBalance=&Auth=F2FE40&AVSCode=Y&CVV2Response=M&CAVVResultCode=")

if __name__ == '__main__':

	content_type = sys.argv[1]
	gateway_URL = sys.argv[2]
	msgdic = sys.argv[3]

	print ('ARGV 1: ' + content_type)
	print ('ARGV 2: ' + gateway_URL)
	print ('ARGV 3: ' + post_request)
	
	processTransaction(post_request, content_type, gateway_URL)

	'''
	if transaction_type == 'CreditCardSale':
		transact = requests.post(cc_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
		print (transact.text)
	elif transaction_type == 'CreditCardVoid':
		transact = requests.post(cc_void_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
		print (transact.text)
	elif transaction_type == 'CreditCardRefund':
		transact = requests.post(cc_refund_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
		print (transact.text)
	elif transaction_type == 'ACHSale':
		transact = requests.post(ach_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
		print (transact.text)
	elif transaction_type == 'ACHSaleOrVoid':
		transact = requests.post(ach_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
		print (transact.text)
	'''	
	'''
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
	content_type = 'application/x-www-form-urlencoded'
	transact = requests.post(cc_endpoint, headers={"Content-Type" : content_type}, data=msgdic)
	print (transact.text)
	'''

'''
python3 cc-bridge.py "application/x-www-form-urlencoded" "CreditCardRefund" "GatewayID=9242093875&RegKey=E33TXDB8AAT4ZHJ6&IindustryCode=2&AccountNumber=5268760045342198&CVV2=665&ExpirationDate=2505&Amount=100&FullName=Edwardq Hooban&Address1=601 N Buchanan St&City=Arlington&State=VA&Zip=22203&PhoneNumber=571-317-4584"
'''



