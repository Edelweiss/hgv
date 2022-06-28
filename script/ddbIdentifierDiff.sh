#!/usr/bin/env bash

export SAXONC_HOME=/usr/lib
#export PATH=$PATH:/usr/local/bin:/usr/local/git/bin
#export CLASSPATH=$CLASSPATH:$HOME/Library/saxon/saxon9he.jar

# tail -fn 100 /var/www/aquila_dev/script/ddbIdentifierDiff.log

git="/var/www/aquila_dev/idp.data"
cnf="/var/www/aquila_dev/.env"
fmp="/var/www/aquila_dev/data/ddbser.xml"
exp="/var/www/aquila_dev/script/ddbSer.py"
csv="/var/www/aquila_dev/data/ddbIdentifierDiff.csv"
xsl="/var/www/aquila_dev/script/ddbIdentifierDiff.xsl"
log="/var/www/aquila_dev/script/ddbIdentifierDiff.log"

date > $log
echo $xsl >> $log
echo $log >> $log

# Update idp.data
# ===============

echo "-------- (1) Update idp.data --------" >> $log
cd $git >> $log 2>&1
git fetch >> $log 2>&1
git merge origin/master >> $log 2>&1

# Export from FileMaker
# =====================

echo "-------- (2) Export from FileMaker --------" >> $log
#python $exp > $fmp 2> $log
python $exp $cnf $fmp >> $log 2>&1

# Generate Alert Log
# ==================

echo "-------- (3) Generate Alert Log --------" >> $log
java -Xms512m -Xmx1536m net.sf.saxon.Transform -o:$csv -it:FIX -xsl:$xsl idpData=$git aquilaXml=$fmp >> $log 2>&1

date >> $log

exit 0
