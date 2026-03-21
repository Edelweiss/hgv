(:~
 : HGV XQuery Library Module
 :
 : Provides functions for querying HGV and DDB EpiDoc XML databases
 : and cross-referencing between them.
 :
 : @author Aquila / HGV Project
 :)
module namespace hgv = "http://papyri.info/ns/hgv";

declare namespace tei = "http://www.tei-c.org/ns/1.0";

(:~
 : Get an HGV document by its filename identifier (= TM number).
 : @param $hgv  the HGV filename / TM number, e.g. "1" or "85874"
 : @return the TEI element, or empty sequence
 :)
declare function hgv:get($hgv as xs:string) as element(tei:TEI)? {
  db:get('hgv')//tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='filename'] = $hgv
  ]
};

(:~
 : Get HGV document(s) by TM number.
 : A single TM number may correspond to multiple HGV records
 : (distinguished by texLett / xml:id suffix).
 : @param $tm  the TM number
 : @return sequence of TEI elements
 :)
declare function hgv:get-by-tm($tm as xs:string) as element(tei:TEI)* {
  db:get('hgv')//tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='TM'] = $tm
  ]
};

(:~
 : Get HGV document by ddb-hybrid identifier.
 : @param $ddb  e.g. "p.adl;;G2" or "p.koeln;8;355"
 : @return the TEI element, or empty sequence
 :)
declare function hgv:get-by-ddb($ddb as xs:string) as element(tei:TEI)? {
  db:get('hgv')//tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='ddb-hybrid'] = $ddb
  ]
};

(:~
 : Get the corresponding DDB document for a given HGV document.
 : Uses the ddb-hybrid identifier to link from HGV → DDB.
 : @param $hgv-doc  an HGV TEI element
 : @return the DDB TEI element, or empty sequence
 :)
declare function hgv:get-ddb-for-hgv($hgv-doc as element(tei:TEI)) as element(tei:TEI)? {
  let $hybrid := $hgv-doc/tei:teiHeader/tei:fileDesc/tei:publicationStmt
                   /tei:idno[@type='ddb-hybrid']/text()
  return
    if ($hybrid)
    then db:get('ddb')//tei:TEI[
           tei:teiHeader/tei:fileDesc/tei:publicationStmt
             /tei:idno[@type='ddb-hybrid'] = $hybrid
         ]
    else ()
};

(:~
 : Get the corresponding HGV document(s) for a given DDB document.
 : Uses the HGV idno to link from DDB → HGV.
 : @param $ddb-doc  a DDB TEI element
 : @return sequence of HGV TEI elements (may be multiple if multi-text)
 :)
declare function hgv:get-hgv-for-ddb($ddb-doc as element(tei:TEI)) as element(tei:TEI)* {
  let $hgv-ids := $ddb-doc/tei:teiHeader/tei:fileDesc/tei:publicationStmt
                    /tei:idno[@type='HGV']/text()
  for $id in tokenize($hgv-ids, '\s+')
  return hgv:get($id)
};

(:~
 : Get a DDB document by its ddb-hybrid identifier.
 : @param $hybrid  e.g. "p.adl;;G2"
 : @return the TEI element, or empty sequence
 :)
declare function hgv:get-ddb($ddb as xs:string) as element(tei:TEI)? {
  db:get('ddb')//tei:TEI[
    tei:teiHeader/tei:fileDesc/tei:publicationStmt
      /tei:idno[@type='ddb-hybrid'] = $ddb
  ]
};

(:~
 : Extract standard metadata from an HGV TEI document as a map.
 : This mirrors the fields in the current Hgv entity.
 : @param $doc  an HGV TEI element
 : @return map with string keys
 :)
declare function hgv:extract-metadata($doc as element(tei:TEI)) as map(*) {
  let $pub     := $doc//tei:publicationStmt
  let $bibl    := $doc//tei:bibl[@type='publication'][@subtype='principal']
  let $origin  := $doc//tei:history/tei:origin
  let $date    := $origin/tei:origDate
  let $ms      := $doc//tei:msIdentifier
  return map {
    'hgv':            string($pub/tei:idno[@type='filename']),
    'tm':             string($pub/tei:idno[@type='TM']),
    'ddb':            string($pub/tei:idno[@type='ddb-hybrid']),
    'ddbFilename':    string($pub/tei:idno[@type='ddb-filename']),

    'publication':    string($bibl/tei:title[@type='abbreviated']),
    'volume':         string($bibl/tei:biblScope[@type='volume']),
    'fascicle':       string($bibl/tei:biblScope[@type='fascicle']),
    'numbers':        string($bibl/tei:biblScope[@type='numbers']),
    'side':           string($bibl/tei:biblScope[@type='side']),

    'lines':          string($bibl/tei:biblScope[@type='lines']),
    'pages':          string($bibl/tei:biblScope[@type='pages']),
    'fragments':      string($bibl/tei:biblScope[@type='fragments']),
    'folio':          string($bibl/tei:biblScope[@type='folio']),
    'inventory':      string($bibl/tei:biblScope[@type='inventory']),
    'number':         string($bibl/tei:biblScope[@type='number']),
    'columns':        string($bibl/tei:biblScope[@type='columns']),
    'generic':        string($bibl/tei:biblScope[@type='generic']),

    'material':       string($doc//tei:support/tei:material),
    'place':          string($origin/tei:origPlace),
    'date':           string($date),
    'notBefore':      string($date/@notBefore),
    'notAfter':       string($date/@notAfter),
    'when':           string($date/@when),
    'precision':      string($date/@precision),

    'settlement':     string($ms/tei:placeName/tei:settlement),
    'collection':     string($ms/tei:collection),
    'invNo':          string($ms/tei:idno[@type='invNo']),

    'keywords':       string-join($doc//tei:keywords[@scheme='hgv']/tei:term/text(), '; '),
    'title':          string($doc//tei:titleStmt/tei:title),

    'provenance':     string-join(
                        $doc//tei:provenance[@type='located']//tei:placeName[@type='ancient']/text(),
                        ' - '
                      ),

    'otherPublication': string-join(
                          $doc//tei:bibl[@type='publication'][@subtype='other']
                            /tei:title[@type='abbreviated']/text(),
                          '; '
                        ),

    'illustrations':  string-join(
                        $doc//tei:div[@type='bibliography'][@subtype='illustrations']
                          //tei:bibl[@type='illustration']/text(),
                        '; '
                      ),

    'figureUrls':     array {
                        for $url in $doc//tei:figure/tei:graphic/@url/string()
                        return $url
                      },

    'translations':   string-join(
                        $doc//tei:div[@type='bibliography'][@subtype='translations']
                          //tei:bibl/text(),
                        '; '
                      ),

    'commentary':     string($doc//tei:div[@type='commentary'][@subtype='general']/tei:p),

    'mentionedDates': array {
                        for $md in $doc//tei:div[@type='commentary'][@subtype='mentionedDates']
                                     //tei:list/tei:item
                        return map {
                          'date': string($md/tei:ref),
                          'cert': string($md/@xml:id)
                        }
                      }
  }
};

(:~
 : Count all HGV documents.
 : @return total document count
 :)
declare function hgv:count-all() as xs:integer {
  count(db:get('hgv')//tei:TEI)
};

(:~
 : Count all DDB documents.
 : @return total document count
 :)
declare function hgv:count-ddb-all() as xs:integer {
  count(db:get('ddb')//tei:TEI)
};
