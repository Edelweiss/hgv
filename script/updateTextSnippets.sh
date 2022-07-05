#!/usr/bin/env bash

#export PATH=$PATH:/usr/local/bin:/usr/local/git/bin
export SAXONC_HOME=/usr/lib

navigator="/home/ubuntu/navigator/paplio"
idp="/home/ubuntu/idp.data/papyri/aquila"
xsl="/home/ubuntu/navigator/paplio/pn-xslt/MakeAquila.xsl"
log="/var/www/aquila/script/updateTextSnippets.log"
htm="/var/www/aquila/script/updateTextSnippets.html"

if [[ -f $log ]]; then mv $log $log.old; fi
date > $log
echo $xsl >> $log
echo $log >> $log

# Update navigator and EpiDoc stylesheets
# =======================================

cd $navigator >> $log 2>&1
git fetch >> $log 2>&1
git merge papyri/paplio >> $log 2>&1
git merge papyri/master >> $log 2>&1
cd epidoc-xslt >> $log 2>&1
git fetch >> $log 2>&1
git merge papyri/master >> $log 2>&1

# Update idp.data
# ===============

cd $idp >> $log 2>&1
git fetch >> $log 2>&1
git merge papyri/master >> $log 2>&1

# Generate html snippets
# ======================

/home/ubuntu/tmp/libsaxon-HEC-11.3/command/transform -o:$htm -it:GENERATE-HTML-SNIPPETS -xsl:$xsl collection=ddbdp line-inc=5 >> $log 2>&1

date >> $log

exit 0
