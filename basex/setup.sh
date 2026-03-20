#!/usr/bin/env bash
#
# setup.sh – Create and index BaseX databases for HGV and DDB EpiDoc XML
#
# Usage:
#   bash basex/setup.sh [/path/to/idp.data]
#
# The script expects the idp.data directory as first argument, or falls back
# to the IDP_DATA_PATH from the .env / .env.local files, or finally tries
# the default relative path  ipd.data/  inside the project root.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ── resolve idp.data path ───────────────────────────────────────────
if [[ -n "${1:-}" ]]; then
    IDP_DATA="$1"
elif [[ -n "${IDP_DATA_PATH:-}" ]]; then
    IDP_DATA="$IDP_DATA_PATH"
elif [[ -f "$PROJECT_DIR/.env.local" ]] && grep -q '^IDP_DATA_PATH=' "$PROJECT_DIR/.env.local"; then
    IDP_DATA=$(grep '^IDP_DATA_PATH=' "$PROJECT_DIR/.env.local" | cut -d= -f2- | tr -d '"' | tr -d "'")
elif [[ -f "$PROJECT_DIR/.env" ]] && grep -q '^IDP_DATA_PATH=' "$PROJECT_DIR/.env"; then
    IDP_DATA=$(grep '^IDP_DATA_PATH=' "$PROJECT_DIR/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
else
    IDP_DATA="$PROJECT_DIR/idp.data"
fi

HGV_DIR="$IDP_DATA/HGV_meta_EpiDoc"
DDB_DIR="$IDP_DATA/DDB_EpiDoc_XML"

# ── sanity checks ───────────────────────────────────────────────────
if [[ ! -d "$HGV_DIR" ]]; then
    echo "ERROR: HGV_meta_EpiDoc directory not found at $HGV_DIR" >&2
    exit 1
fi
if [[ ! -d "$DDB_DIR" ]]; then
    echo "ERROR: DDB_EpiDoc_XML directory not found at $DDB_DIR" >&2
    exit 1
fi

command -v basex >/dev/null 2>&1 || { echo "ERROR: basex command not found. Install BaseX first." >&2; exit 1; }

echo "=== BaseX Database Setup ==="
echo "HGV source: $HGV_DIR"
echo "DDB source: $DDB_DIR"
echo ""

# ── create HGV database ─────────────────────────────────────────────
echo "Creating database 'hgv' from HGV_meta_EpiDoc ..."
basex -c "
  SET INTPARSE true
  SET DTD false
  SET STRIPWS false
  CREATE DB hgv $HGV_DIR
  OPTIMIZE ALL
  CREATE INDEX fulltext
  INFO DB
"
echo "Database 'hgv' created."
echo ""

# ── create DDB database ─────────────────────────────────────────────
echo "Creating database 'ddb' from DDB_EpiDoc_XML ..."
basex -c "
  SET INTPARSE true
  SET DTD false
  SET STRIPWS false
  CREATE DB ddb $DDB_DIR
  OPTIMIZE ALL
  CREATE INDEX fulltext
  INFO DB
"
echo "Database 'ddb' created."
echo ""

# ── install XQuery module ───────────────────────────────────────────
REPO_DIR="$SCRIPT_DIR/repo"
if [[ -d "$REPO_DIR" ]]; then
    echo "Installing XQuery modules from $REPO_DIR ..."
    for xqm in "$REPO_DIR"/*.xqm; do
        if [[ -f "$xqm" ]]; then
            echo "  Installing $(basename "$xqm") ..."
            basex -c "REPO INSTALL $xqm"
        fi
    done
    echo ""
fi

# ── verify cross-references ─────────────────────────────────────────
echo "Verifying cross-references ..."

VERIFY_XQ=$(mktemp)
cat > "$VERIFY_XQ" << 'XQUERY'
declare namespace tei = 'http://www.tei-c.org/ns/1.0';
let $hgv-count := count(db:get('hgv')//tei:TEI)
let $ddb-count := count(db:get('ddb')//tei:TEI)
let $sample-hybrid := db:get('hgv')//tei:TEI[tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='filename'] = '1']//tei:idno[@type='ddb-hybrid']/text()
let $linked := if ($sample-hybrid) then db:get('ddb')//tei:TEI[tei:teiHeader/tei:fileDesc/tei:publicationStmt/tei:idno[@type='ddb-hybrid'] = $sample-hybrid]//tei:idno[@type='filename']/text() else 'none'
return 'HGV documents: ' || $hgv-count || '&#10;'
    || 'DDB documents: ' || $ddb-count || '&#10;'
    || 'Sample cross-ref (HGV 1 -> DDB): ' || $linked
XQUERY

basex -c "RUN $VERIFY_XQ"
rm -f "$VERIFY_XQ"

echo ""
echo "=== Setup complete ==="
