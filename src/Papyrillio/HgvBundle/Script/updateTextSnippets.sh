#!/usr/bin/env bash

# Daemon
# ~/Library/LaunchAgents/papyrillio.hgv.idpdata.plist
# launchctl list | grep papy
# Log
# tail -fn 100 ~/hgv.dev/src/Papyrillio/HgvBundle/Script/updateTextSnippets.log

xsl="$HOME/navigator/aquila/pn-xslt/MakeAquila.xsl"
log="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Script/updateTextSnippets.log"
htm="$HOME/hgv.dev/src/Papyrillio/HgvBundle/Script/updateTextSnippets.html"

date > $log
echo $xsl >> $log
echo $log >> $log

# Update navigator and EpiDoc stylesheets
# =======================================

cd ~/navigator/master >> $log 2>&1
git fetch >> $log 2>&1
git merge origin/master >> $log 2>&1
cd epidoc-xslt >> $log 2>&1
svn up >> $log 2>&1

# Update idp.data
# ===============

cd ~/idp.data/aquila >> $log 2>&1
git fetch >> $log 2>&1
git merge origin/master >> $log 2>&1

# Generate html snippets
# ======================

java net.sf.saxon.Transform -o:$htm -it:GENERATE-HTML-SNIPPETS -xsl:$xsl collection=ddbdp line-inc=5 >> $log 2>&1

date >> $log

exit 0
