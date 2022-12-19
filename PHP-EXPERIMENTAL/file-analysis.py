import re
import os
import subprocess
from bs4 import BeautifulSoup
import soupsieve as sv

## regex require include etc
## (require|include)(\_once)? \"(.*)NEW(.*)\" 
## REGEX TEMPLATE:   (require|include)(_once)? \"(.*)test

directories_only 		= []
php_files 					= []
html_files 					= []
css_files 						= []
js_files 							= []
mysqli_statements = []
php_includes 			= []
php_inline  					= []
no_match_files 		= []
script_ref 					= []
php_file_no_depend  = []
echostatements = []

file_out = open('inline-js.txt', 'w')
tags_out = open('inline-tags.txt', 'w')
php_out = open('php-depend.txt', 'w')
style_out = open('inline-style.txt','w')
migration_out =open('migration.txt','w')


global_var_re = re.compile('(.*)\(\$\_[A-Z]+')
global_var_exclude = re.compile('if\(\$\_[A-Z]+(.*)\)')
clean_punct_re = re.compile('(.*)\"\;')
file_kind_php_re = re.compile('(.*)\.(php|css|js|html)')
dependency_re = re.compile('(require|include)(\_once)?\s\"(.*)\"' )
dependency_sub_re = re.compile('(.*)(require|include)(\_once)? \"\.\.\/(.*)\"')
parse_dir_re = re.compile('(.*)(require|include)(\_once)? \"(.*)')
dependency_in_func = re.compile('function (.*)')
echo_re = re.compile('echo \"(.*)\"')

parse_inline_php_re = re.compile('(.*)<\?(.*)<\?>')
parse_inline_php_begin_re = re.compile('(.*)<\?(.*)')
parse_inline_php_end_re = re.compile('(.*)\?>')
parse_script_ref_re = re.compile('<script (type=\"text/javascript\")? src=\"(.*)\.js\"')
parse_script_ref_no_type_re = re.compile('<script lang=\"(.*)\.js\"')
parse_inline_script_re = re.compile('<script language=\'javascript\'>(.*)</javascript>')
parse_inline_script_re_end = re.compile('<\/script>')

parse_mysql = re.compile('(.*) mysql\_(.*)')
parse_do_query = re.compile('(.*)doQuery\((.*)\)\;')
parse_fetchAssociations = re.compile('(.*)fetchAssociations\((.*)\)\;')
parse_fetchAssociationsGroupedBy = re.compile('(.*)fetchAssociationsGroupedBy\((.*)\)\;')
parse_fetchAssociationsKeyedBy = re.compile('(.*)fetchAssociationsKeyedBy\((.*)\)\;')
parse_fetchAssociationsIntoHierarchy = re.compile('(.*)fetchAssociationsIntoHierarchy\((.*)\)\;')
parse_fetchColN = re.compile('(.*)fetchColN\((.*)\)\;')
parse_fetchFirstAssoc = re.compile('(.*)fetchFirstAssoc\((.*)\)\;')
parse_fetchKeyValuePairs = re.compile('(.*)fetchKeyValuePairs\((.*)\)\;')
parse_fetchRows = re.compile('(.*)fetchRows\((.*)\)\;')
parse_fetchRow0Col0	 = re.compile('(.*)fetchRow0Col0\((.*)\)\;')

def write_file_header(fileoutname):
	file_out.write('\n////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')
	file_out.write('//////////////     ' + fileoutname + '     ////////////// ')
	file_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')

	tags_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')
	tags_out.write('//////////////     ' + fileoutname + '    //////////////')
	tags_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')

	php_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')
	php_out.write('//////////////     ' + fileoutname + '    ////////////// ')
	php_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')

	style_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')
	style_out.write('//////////////     ' + fileoutname + '    ////////////// ')
	style_out.write('\n///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////\n')
def determineFileType(full_file_name):
	match_file = file_kind_php_re.match(full_file_name)
	if match_file != None:
		file_type =  full_file_name.split('.')
		if file_type[1] == 'php':
			php_files.append(file_type[0]+'.php')
		elif file_type[1] == 'css':
			css_files.append(file_type[0]+'.css')
		elif file_type[1] == 'js':
			js_files.append(file_type[0]+'.js')
		elif file_type[1] == 'html':
			html_files.append(file_type[0]+'.html')
		else:
			no_match_files.append(file_type[0])
	else:
		no_match_files.append(full_file_name)
def process_list_entries():
	path = os.getcwd()
	##print (path)
	with os.scandir(path) as listOfEntries:
		for entry in listOfEntries:
			if entry.is_file():
				type_file = determineFileType(entry.name)
			elif entry.is_dir():
				##print (entry.name)
				with os.scandir(entry.name) as listOfSub:
					for sub in listOfSub:
						print (' --> ' + sub.name)
def parse_files_in_directories():
	for name_dir in directories_only:
		##print ('DIRECTORY: ' + name_dir)
		with os.scandir(os.getcwd()+'/'+name_dir) as listOfEntries:
			for entry in listOfEntries:
				if entry.is_file():
					type_file = determineFileType(entry.name)
				##elif  entry.is_dir():
					##print ('SUBDIRECTORY: ' + name_dir + '/' + entry.name)

def print_mysqli_statement_by_file(mysqli_by_file):
	mysqli_statement_count = 0
	for mysqli_by_filename in mysqli_by_file:
		print ('\n')
		print('-------------------------------')
		print (mysqli_by_filename)
		print('-------------------------------')
		array_of_references = mysqli_by_file[mysqli_by_filename]
		for reference in array_of_references:
			print (reference['line_num'] + '    ' + reference['statement'])
		mysqli_statement_count = mysqli_statement_count + 1

		print ('\n\n\n')
		print('-------------------------------')
		print ('TOTAL NUMBER STATEMENTS: ' + str(mysqli_statement_count))
		print('-------------------------------')
		print ('\n\n\n')
		print('-------------------------------')
		print('CSS FILES  ' + str(len( css_files) ))
		print('-------------------------------')
		for cssfile in css_files:
			print (cssfile)

		print('-------------------------------')
		print('HTML FILES  ' + str(len(html_files)))
		print('-------------------------------')
		for htmlfile in html_files:
			print(htmlfile)

		print('-------------------------------')
		print('JAVASCRIPT FILES  ' + str(len( js_files)))
		print('-------------------------------')
		for javascriptfile in js_files:
			print(javascriptfile)

		print('-------------------------------')
		print('NO NAME FILES  ' + str(len(no_match_files)))
		print('-------------------------------')
		for no_name_file in no_match_files:
			print(no_name_file)

def domtraverse(soup):
	if soup.name is not None:
		dom_dictionary = {}
		dom_dictionary['name'] = soup.name
		dom_dictionary['type'] = soup.contents
		dom_dictionary['children'] = [domtraverse(child) for child in soup.children if child.name is not None]
		dom_dictionary ['contents'] = soup.contents
		##for content in soup.contents:
			##content_string = str(content)
			##php_out.write(str(content)+'\n')
		return dom_dictionary

def output_tags_recursive(dom_list, level):
	level_string = '  '
	level = level + 1
	for i in range(level):
		level_string = level_string + '     '
	for tag_dict in dom_list:
		##php_out.write(str(level)  + level_string + '<'+tag_dict['name'] + '>\n')
		##tag_att = tag_dict.attrs
		##print (str(tag_att))
		##php_out.write(str(tag_dict['type']))
		if tag_dict['children'] != None:
			output_tags_recursive(tag_dict['children'], level)
		else:
			return

def output_tags(dom_dic, level):
	dom_children  = dom_dic['children']
	for child in dom_children:
		if child != None:
			output_tags_recursive(child['children'], level)

def findalltags(soupitem):
	string_indent = '  '
	alltags = soupitem.find_all(True)
	dom_dic = domtraverse(soupitem)
	dom_keys = dom_dic.keys()
	output_tags(dom_dic, 1)

def findscriptref(soupitem):
	tag = soupitem.find_all('script')
	for tagitem in tag:
		file_out.write(str(tagitem) + '\n\n')

def findstyleref(soupitem):
	els = soupitem.find_all(True)
	for el in els:
		elattr = el.attrs
		if el.has_attr('style'):
			style_out.write('--------' + el.name + '---------\n')
			for elemattrib in elattr:
				style_out.write('< ' +elemattrib + '>  ' )
				style_out.write(str(el[elemattrib] )+ '\n')

	htmlstyle = soupitem.find_all('style')
	for htmlitem in htmlstyle:
		style_out.write(str(htmlitem))
		for styleitem in htmlitem:
			style_out.write(styleitem)
			style_out.write('\n')

def parsefiles():
	for root_filename in html_files:
		with open(root_filename, errors = 'ignore') as f:
			write_file_header(root_filename)
			contents = f.read()
			soup = BeautifulSoup(contents, 'html.parser')
			soup2 = BeautifulSoup(contents,'html5lib')
			findstyleref(soup2)
			findalltags(soup)
			findscriptref(soup)

def createoutputfile(filename):
	return open('OUT-' + filename, 'w', encoding='utf8')

def writephpmysqli(file, line):
	file.write(line)

def findechostatements(line, file, line_num):
	echomatch = echo_re.match(line)
	if echomatch != None:
		echo_dic = {}
		echo_dic['filename'] = file
		echo_dic['echo'] = str(echomatch[1])
		echo_dic['linenum'] = str(line_num)
		echostatements.append(echo_dic)
		##print (str(echomatch))

def writephp():
	mysqli_by_file = {}
	for statement in mysqli_statements:
		mysqli_filename = statement['filename']
		if mysqli_filename in mysqli_by_file:
			array_of_references = mysqli_by_file[mysqli_filename]
			array_of_references.append(statement)
			mysqli_by_file[mysqli_filename] = array_of_references
		else:
			array_of_references =[]
			array_of_references.append(statement)
			mysqli_by_file[mysqli_filename] = array_of_references

	mysqli_file_keys = mysqli_by_file.keys()
	for mkey in mysqli_file_keys:
		php_out.write('\n>>>>>>>>>>>> ' + mkey + ' <<<<<<<<<<<<<<<\n')
		statements_array = mysqli_by_file[mkey]
		php_out.write('\n---------------INCLUDE / REQUIRE------------------\n')
		for dependdic in php_includes:
			if dependdic['filename'] == mkey:
				php_out.write(dependdic['includeitem']+'\n')
		php_out.write('\n------------ECHO STATEMENTS---------------------\n')
		for echodic in echostatements:
			if echodic['filename'] == mkey:
				php_out.write(echodic['linenum'] + '   ' + echodic['echo'] + '\n')
		php_out.write('\n------------MYSQL---------------------\n')
		for name_file in statements_array:
			php_out.write(name_file['line_num'] + '   ')
			php_out.write('mysqli_' + name_file['statement'] + '\n')

def check_dependency(filename, line, line_num):
	depend_sub_match = dependency_sub_re.match(line)
	depend_match = dependency_re.match(line)
	if depend_match != None:
		depend_dict = {}
		depend_dict['filename'] = filename
		depend_dict['line'] = line
		depend_dict['includeitem'] = depend_match[3]
		php_includes.append(depend_dict)

def check_global_var(filename, line, line_num):
	gexclude = global_var_exclude.match(line)
	if gexclude == None:
		global_match = global_var_re.match(line)
		if global_match != None:
			print ('---------- ' + filename + ' ------------')
			print (str(line_num) + '>>  ' +line)

def check_inline_php_tag(line):
	inlinephpmatch = parse_inline_php_re.match(line)
	parse_end_match_php_inline = parse_inline_php_end_re.match(line)
	parse_begin = parse_inline_php_begin_re.match(line)

	if inlinephpmatch != None:
		##print ('//  ' + line)
		return True
	elif parse_begin != None:
		##print ('-- ' + line)
		return True

	if parse_end_match_php_inline != None:
		return False

def match_mysqli_statement(phpfile, line, line_count):
	mysqli_match = parse_mysql.match(line)
	if mysqli_match != None:
		statement_dic = {}
		statement_dic['filename'] = phpfile
		statement_dic['line_num'] = str(line_count)
		statement_dic['statement'] = str(mysqli_match[2])
		mysqli_statements.append(statement_dic)

process_list_entries()
parse_files_in_directories()
parsefiles()

for htmlfile in html_files:
	php_inline_dic = {}
	php_inline_dic['filename'] = htmlfile
	print ('-----HTML file examine: ' + htmlfile + '----------')
	with open(htmlfile, errors = 'ignore') as hfile:
		lines = hfile.readlines()
		current_function = ''
		line_count = 0
		php_tag_flag = False

		for line in lines:
			line_count = line_count + 1
			check_dependency(hfile, line, line_count)
			php_tag_flag_check  = check_inline_php_tag(line)
			if php_tag_flag == True:
				##print (line)
				php_inline_dic['line_count'] = line
			if php_tag_flag_check == True and php_tag_flag == False:
				php_tag_flag = True
				##print (line)
			elif php_tag_flag_check == False and php_tag_flag == True:
				php_tag_flag = False
				##print ('PHP TAG FLAG IS FALSE')

for phpfile in php_files:
	php_inline_dic ={}
	php_inline_dic['filename'] = phpfile 
	##php_mysqli_file_out = createoutputfile(phpfile)

	with open(phpfile, errors='ignore') as pfile:
		lines = pfile.readlines()
		current_function = ''
		line_count = 0
		php_tag_flag = False

		for line in lines:
			##php_mysqli_file_out.write(line)
			line_count  = line_count + 1

			match_mysqli_statement(phpfile, line, line_count)
			check_dependency(phpfile, line, line_count)
			php_tag_flag_check = check_inline_php_tag(line)
			findechostatements(line, phpfile, line_count)
			check_global_var(phpfile, line, line_count)

			if php_tag_flag == True:
				php_inline_dic['line_count'] = line_count
			if php_tag_flag_check == True and php_tag_flag == False:
				php_tag_flag = True
			elif php_tag_flag_check == False and php_tag_flag == True:
				php_tag_flag = False

	##php_mysqli_file_out.close()

  

##for phpdic in php_includes:
	##print ('---- FILE:  ' + str(phpdic['filename']) + ' ----------' )



writephp()

			