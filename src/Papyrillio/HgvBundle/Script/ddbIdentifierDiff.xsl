<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet exclude-result-prefixes="#all" version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:papy="Papyrillio"
    xmlns:xs="http://www.w3.org/2001/XMLSchema"
    xmlns:date="http://exslt.org/dates-and-times"
    xmlns:fm="http://www.filemaker.com/fmpxmlresult"
    xmlns:tei="http://www.tei-c.org/ns/1.0"
    xmlns:fn="http://www.xsltfunctions.com/"
    xmlns:functx="http://www.functx.com"
    xmlns="http://www.tei-c.org/ns/1.0">
    
    <!--
    java -Xms512m -Xmx1536m net.sf.saxon.Transform -o:040_ddbSerAlert.csv -it:FIX -xsl:040_ddbSerAlert.xsl > 040_ddbSerAlert 2>&1
-->
    <xsl:include href="helper.xsl"/>
    <xsl:output method="text" media-type="text/csv" />
    
    <xsl:param name="idpData"/>
    <xsl:param name="aquilaXml"/>
    <!-- 
    <ROW xmlns="http://www.filemaker.com/fmpxmlresult" MODID="17" RECORDID="2326">
            <COL>
                <DATA>16137</DATA>
                <DATA/>
                <DATA/>
                <DATA/>
                <DATA/>
                <DATA/>
                <DATA/>
            </COL>
            <COL>
                <DATA>16137</DATA>
            </COL>
            <COL>
                <DATA>bgu</DATA>
            </COL>
            <COL>
                <DATA>12</DATA>
            </COL>
            <COL>
                <DATA>2201</DATA>
            </COL>
            <COL>
                <DATA>bgu</DATA>
            </COL>
        </ROW>
    -->

    <xsl:variable name="hgvAquila" select="doc($aquilaXml)"/>
    <xsl:variable name="series_position" select="papy:getPosition('ddbSer', $hgvAquila)"/>
    <xsl:variable name="volume_position" select="papy:getPosition('ddbVol', $hgvAquila)"/>
    <xsl:variable name="document_position" select="papy:getPosition('ddbDoc', $hgvAquila)"/>
    <xsl:variable name="seriesIdp_position" select="papy:getPosition('ddbSerIDP', $hgvAquila)"/>
    <xsl:variable name="tm_position" select="papy:getPosition('TMNr_plus_texLett', $hgvAquila)"/>

    <!--
        <item ddb="zpe;202;246" hgv="699546" file="file:/Users/elemmire/projects/idp.data/idp.data/papyri/master/DDB_EpiDoc_XML/zpe/zpe.202/zpe.202.246.xml"/>
        <item ddb="zpe;202;247" hgv="699554" file="file:/Users/elemmire/projects/idp.data/idp.data/papyri/master/DDB_EpiDoc_XML/zpe/zpe.202/zpe.202.247.xml"/>

        ... 63486 Stück        
    -->

    <xsl:variable name="ddbList">
        <list xmlns="">
            <xsl:for-each select="collection(concat($idpData, '/DDB_EpiDoc_XML?select=*.xml;recurse=yes'))[.//tei:idno[@type='HGV']][string(.//tei:div[@type='edition'])]">
                <item ddb="{.//tei:idno[@type='ddb-hybrid']}" hgv="{.//tei:idno[@type='HGV']}" tm="{.//tei:idno[@type='TM']}" file="{document-uri(.)}" />
            </xsl:for-each>
        </list>
    </xsl:variable>

    <xsl:template name="FIX">
        <xsl:call-template name="csvLine">
            <xsl:with-param name="data" select="('tm', 'hgv', 'ddb', 'series', 'volume', 'document', 'aquila ddbSerIDP', 'aquila ddbSer', 'aquila ddbVol', 'aquila ddbDoc')"/>
        </xsl:call-template>

        <xsl:for-each select="$ddbList//item">
            <xsl:variable name="hgv" select="string(@hgv)"/>
            <xsl:variable name="tm" select="string(@tm)"/>
            <xsl:variable name="ddb" select="string(@ddb)"/>
            <xsl:variable name="ddbSeries" select="tokenize($ddb, ';')[1]"/>
            <xsl:variable name="ddbVolume" select="tokenize($ddb, ';')[2]"/>
            <xsl:variable name="ddbDocument" select="tokenize($ddb, ';')[3]"/>

            <xsl:variable name="aquila" select="($hgvAquila//fm:ROW[fm:COL[$tm_position]/fm:DATA[1] = $hgv])[1]"/>
            <xsl:variable name="aquila_ddbSeries" select="string($aquila//fm:COL[$series_position]/fm:DATA)"/>

            <xsl:if test="$aquila and not($ddbSeries = $aquila_ddbSeries)">                
                <xsl:message select="concat(position(), ' ---- ', $hgv, '/', $ddb, ' ----')"/>
                <xsl:message select="concat($ddbSeries, ' / ',  $aquila_ddbSeries)"/>
                
                <xsl:variable name="aquila_ddbVolume" select="string($aquila//fm:COL[$volume_position]/fm:DATA)"/>
                <xsl:variable name="aquila_ddbDocument" select="string($aquila//fm:COL[$document_position]/fm:DATA)"/>
                <xsl:variable name="aquila_ddbSeriesIdp" select="string($aquila//fm:COL[$seriesIdp_position]/fm:DATA)"/>
                
                <xsl:call-template name="csvLine">
                    <xsl:with-param name="data" select="($tm, $hgv, $ddb, $ddbSeries, $ddbVolume, $ddbDocument, $aquila_ddbSeriesIdp, $aquila_ddbSeries, $aquila_ddbVolume, $aquila_ddbDocument)"/>
                </xsl:call-template>
            </xsl:if>

        </xsl:for-each>
    </xsl:template>

</xsl:stylesheet>