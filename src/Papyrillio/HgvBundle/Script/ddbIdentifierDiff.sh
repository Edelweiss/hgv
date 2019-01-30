#!/usr/bin/env bash

export PATH=$PATH:/usr/local/bin:/usr/local/git/bin
export CLASSPATH=$CLASSPATH:$HOME/Library/saxon/saxon9he.jar

# Daemon
# ~/Library/LaunchAgents/papyrillio.hgv.ddbser.plist
# launchctl list | grep papy
# Log
# tail -fn 100 ~/hgv.dev/src/Papyrillio/HgvBundle/Script/ddbIdentifierDiff.log

git="$HOME/idp.data/aquila"
fmp="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Data/ddbser.xml"
csv="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Script/ddbIdentifierDiff.csv"
xsl="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Script/ddbIdentifierDiff.xsl"
log="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Script/ddbIdentifierDiff.log"

date > $log
echo $xsl >> $log
echo $log >> $log

# Update idp.data
# ===============

cd $git >> $log 2>&1
git fetch >> $log 2>&1
git merge origin/master >> $log 2>&1

# Generate Alert Log
# ==================

java -Xms512m -Xmx1536m net.sf.saxon.Transform -o:$csv -it:FIX -xsl:$xsl idpData=$git aquilaXml=$fmp >> $log 2>&1

date >> $log

exit 0
