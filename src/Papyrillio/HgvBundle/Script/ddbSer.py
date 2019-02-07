# encoding=utf8

import sys
import pyodbc
import codecs

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


crsr = conn.cursor()
rows = crsr.execute("select TMNr_plus_texLett, ddbSerIDP, ddbSer, ddbVol, ddbDoc from Hauptregister").fetchall()
fields = ['TMNr_plus_texLett', 'ddbSerIDP', 'ddbSer', 'ddbVol', 'ddbDoc']
fmpDoc = FileMakerFmpDoc(fields, rows)

#print fmpDoc.toXml()

#f = open('/Users/Admin/hgv.dev/src/Papyrillio/HgvBundle/Data/ddbser.xml', 'w')
#f.write(fmpDoc.toXml())


file.write(fmpDoc.toXml().decode('cp1252'))
file.close()

crsr.close()
conn.close()
