# BaseX Database Setup for Aquila / HGV

This directory contains the BaseX configuration and XQuery modules for the
Aquila papyrological database application.

## Databases

- **hgv** – HGV_meta_EpiDoc XML files (metadata about papyri)
- **ddb** – DDB_EpiDoc_XML files (papyri text editions)

## Cross-referencing

The two databases are linked via:

- **HGV → DDB**: `//tei:idno[@type='ddb-hybrid']` in HGV files matches
  `//tei:idno[@type='ddb-hybrid']` in DDB files.
- **DDB → HGV**: `//tei:idno[@type='HGV']` in DDB files matches
  `//tei:idno[@type='filename']` in HGV files.

## Setup

```bash
# Create databases
bin/console app:basex:create-db

# Or via shell script
bash basex/setup.sh
```

## XQuery modules

- `repo/hgv.xqm` – Library module for querying HGV metadata
