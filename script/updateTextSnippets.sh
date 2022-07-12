#!/usr/bin/env bash
# Authors: Carmen Maria Lanz et. al.
# (Generierung der Transkription und Übersetzungen im HTML-Format für HGV)
set -e

# export PATH=$PATH:/usr/local/bin:/usr/local/git/bin
export SAXONC_HOME=/usr/lib

navigator="/home/ubuntu/navigator/paplio"
idp="/home/ubuntu/idp.data/papyri/aquila"
xsl="/home/ubuntu/navigator/paplio/pn-xslt/MakeAquila.xsl"
log="/var/www/aquila/script/updateTextSnippets.log"
htm="/var/www/aquila/script/updateTextSnippets.html"

if [[ -f $log ]]; then mv $log $log.old; fi  ### Keep one old log by renaming, if it exists.
date --iso=s > $log
echo "--- This is $0 running as $USER in $PWD on $(hostname -f)"
echo "--- With navigator=$navigator,  idp=$idp,  xsl=$xsl,  log=$log and  htm=$htm"  >> $log

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

date --iso=s >> $log

exit 0
