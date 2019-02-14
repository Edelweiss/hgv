# encoding=utf8

import sys
import pyodbc
import codecs
import re

reload(sys)  
sys.setdefaultencoding('utf8')

class FileMakerFmpDoc:
  meta = ''
  data = []
  def __init__(self, meta, data):
    self.meta = fmpMeta = FileMakerFmpMeta(meta)
    for record in data:
      self.data.append(FileMakerFmpRow(record))

  def toXml(self):
    xml = '<?xml version="1.0" encoding="UTF-8" ?><FMPXMLRESULT xmlns="http://www.filemaker.com/fmpxmlresult">' + self.meta.toXml() + '<RESULTSET>'
    for row in self.data:
      xml = xml + row.toXml()
    xml = xml + '</RESULTSET></FMPXMLRESULT>'
    return xml

class FileMakerFmpMeta:
  meta = []
  def __init__(self, metaFields):
    self.meta = metaFields
  def toXml(self):
    xml = ''
    for field in self.meta:
      xml = xml + '<FIELD NAME="' + field + '"/>' 
    return '<METADATA>' + xml + '</METADATA>'

class FileMakerFmpRow:
  row = []
  def __init__(self, rowData):
    self.row = rowData
  def toXml(self):
    xml = ''
    for col in self.row:
      value = col
      if value == None:
        value = ''
      xml = xml + '<COL><DATA>' + value + '</DATA></COL>' 
    return '<ROW>' + xml + '</ROW>'

if len(sys.argv == 3):
  configFile = sys.argv[1]
  resultFile = sys.argv[2]

  config = open(configFile, 'r').read()
  dsn = re.search('filemaker_database *= *(.+)', config).group(1)
  uid = re.search('filemaker_username *= *(.+)', config).group(1)
  pwd = re.search('filemaker_password *= *(.+)', config).group(1)
  con = 'DSN='+ dsn +';UID='+ uid +';PWD='+ pwd

  conn = pyodbc.connect(con) # the DSN value should be the name of the entry in odbc.ini, not freetds.conf
  crsr = conn.cursor()
  rows = crsr.execute("select TMNr_plus_texLett, ddbSerIDP, ddbSer, ddbVol, ddbDoc from Hauptregister").fetchall()
  fields = ['TMNr_plus_texLett', 'ddbSerIDP', 'ddbSer', 'ddbVol', 'ddbDoc']
  fmpDoc = FileMakerFmpDoc(fields, rows)

  print fmpDoc.toXml()

  file = codecs.open(resultFile, "w", "utf-8")
  file.write(fmpDoc.toXml().decode('cp1252'))
  file.close()

  crsr.close()
  conn.close()
