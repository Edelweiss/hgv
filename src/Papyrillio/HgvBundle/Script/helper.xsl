<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet exclude-result-prefixes="#all" version="2.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:saxon="http://saxon.sf.net/"
    xmlns:papy="Papyrillio"
    xmlns:xs="http://www.w3.org/2001/XMLSchema"
    xmlns:fm="http://www.filemaker.com/fmpxmlresult"
    xmlns:tei="http://www.tei-c.org/ns/1.0"
    xmlns:date="http://exslt.org/dates-and-times"
    xmlns="http://www.tei-c.org/ns/1.0">
    
    <!-- CSV -->
    
    <xsl:template name="csvLine">
        <xsl:param name="data"/>
        
        <xsl:for-each select="$data">
            <xsl:call-template name="csvData">
                <xsl:with-param name="data" select="."/>
                <xsl:with-param name="last" select="position() = last()"/>
            </xsl:call-template>
        </xsl:for-each>
        
    </xsl:template>
    
    <xsl:template name="csvData">
        <xsl:param name="data"/>
        <xsl:param name="last"/>
        <xsl:param name="delim" select="','" />
        <xsl:param name="quote" select="'&quot;'" />
        <xsl:param name="break" select="'&#xA;'" />
        <xsl:param name="biblioIndex" select="83344" />
        
        <xsl:value-of select="concat($quote, replace(normalize-space(string($data)), $quote, concat($quote, $quote)), $quote)"/>
        <xsl:choose>
            <xsl:when test="$last = true()">
                <xsl:value-of select="$break" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$delim" />
            </xsl:otherwise>
        </xsl:choose>
        
    </xsl:template>
    
    <!-- FileMaker -->

    <xsl:function name="papy:getPosition" as="xs:integer">
        <xsl:param name="namae"/>
        <xsl:param name="docu"/>
        <xsl:choose>
            <xsl:when test="$docu/fm:FMPXMLRESULT/fm:METADATA/fm:FIELD[@NAME=$namae]/count(preceding-sibling::fm:FIELD)+1">
                <xsl:value-of select="$docu/fm:FMPXMLRESULT/fm:METADATA/fm:FIELD[@NAME=$namae]/count(preceding-sibling::fm:FIELD)+1"/>
            </xsl:when>
            <xsl:otherwise>0</xsl:otherwise>
        </xsl:choose>
    </xsl:function>

    <!-- HGV -->

    <xsl:function name="papy:getFolder1000">
        <xsl:param name="file"/>
        <xsl:value-of select="ceiling(number($file) div 1000)"/>
    </xsl:function>

    <xsl:function name="papy:getXpath">
        <xsl:param name="node"/>
        
        <xsl:for-each select="$node/ancestor-or-self::*">
            <xsl:value-of select="concat('/', name())"/>
            <xsl:for-each select="@*">
                <xsl:value-of select="concat('[@', name(), if(not(name() = ('xml:id', 'corresp', 'n', 'when', 'from', 'to', 'notBefore', 'notAfter', 'who', 'target', 'form', 'columns', 'writtenLines', 'ref', 'url'))) then concat('=', data(.)) else (), ']')"/>
            </xsl:for-each>
        </xsl:for-each>
        
    </xsl:function>

</xsl:stylesheet>
