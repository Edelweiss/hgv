# Aquila / HGV — Operations Manual

This document is the runbook for everything that runs **outside the request
cycle**: installing the dependencies, starting and updating the BaseX server,
(re-)building each BaseX database, refreshing the keyword translations and the
publication cache, regenerating HTML text snippets, clearing the Symfony
cache, and recovering from the failure modes we've actually hit.

Audience: someone with shell access to the deployment host (or a development
machine) and ordinary developer tooling (git, composer, PHP, …).

---

## Contents

1. [Architecture at a glance](#1-architecture-at-a-glance)
2. [Prerequisites](#2-prerequisites)
3. [Initial setup](#3-initial-setup)
4. [Configuration reference (`.env`)](#4-configuration-reference-env)
5. [BaseX server lifecycle](#5-basex-server-lifecycle)
6. [BaseX databases](#6-basex-databases)
   - [6.1 `hgv` — HGV_meta_EpiDoc](#61-hgv--hgv_meta_epidoc)
   - [6.2 `ddb` — DDB_EpiDoc_XML](#62-ddb--ddb_epidoc_xml)
   - [6.3 `keywords` — translated HGV keywords](#63-keywords--translated-hgv-keywords)
7. [Updating the source data](#7-updating-the-source-data)
   - [7.1 `idp.data` (HGV + DDB upstream)](#71-idpdata-hgv--ddb-upstream)
   - [7.2 `data/keywords.csv` (translation dictionary)](#72-datakeywordscsv-translation-dictionary)
8. [Publication cache (`var/cache/publication_index.json`)](#8-publication-cache-varcachepublication_indexjson)
9. [HTML text snippets (cron)](#9-html-text-snippets-cron)
10. [Symfony cache](#10-symfony-cache)
11. [Routine maintenance checklist](#11-routine-maintenance-checklist)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Architecture at a glance

```
                   ┌──────────────────────────────────────────┐
   Browser ───▶    │  Symfony 5.4 app (public/index.php)      │
                   │  Twig + DataTables                       │
                   └────────────┬─────────────────────────────┘
                                │ HTTP (BaseX REST)
                                ▼
                   ┌──────────────────────────────────────────┐
                   │  BaseX server  (default: :8080/rest)     │
                   │  databases: hgv • ddb • keywords         │
                   └────────────┬─────────────────────────────┘
                                │ on (re)build only
                                ▼
                   ┌──────────────────────────────────────────┐
                   │ idp.data/             (git checkout)     │
                   │   ├── HGV_meta_EpiDoc/   → BaseX `hgv`   │
                   │   └── DDB_EpiDoc_XML/    → BaseX `ddb`   │
                   │ data/keywords.csv        → BaseX         │
                   │                              `keywords`  │
                   └──────────────────────────────────────────┘

   Extra filesystem state:
     - var/cache/publication_index.json     publication browse cache (24h TTL)
     - public/texte/*.html                  HGV publication overview pages
     - HGV_trans_EpiDoc_HTML/<id>.html      pre-rendered translation snippets
                                            (cron-generated; see §9)
```

Aquila never writes back to BaseX in normal operation — the REST API is used
read-only. All rebuilds are explicit, run by an operator via `bin/console`.

---

## 2. Prerequisites

| Component | Version | Notes |
|---|---|---|
| PHP CLI | **≥ 8.2** | Project tested on 8.2.4. |
| Composer | 2.x | |
| BaseX | **12.x** (12.2 verified) | Installed system-wide or as a service. |
| Saxon HE/EE | 11.x | Only needed for the text-snippets cron (§9). |
| git | any recent | Needed to keep `idp.data/` and `navigator/` up to date. |
| Symfony CLI | optional | Convenient for `symfony serve` in dev. |

Disk: an `idp.data` checkout currently weighs ~3 GB and the three BaseX
databases together ~2 GB on disk. Build memory peak is well below 2 GB.

---

## 3. Initial setup

```bash
# Repo
git clone <repo-url> aquila
cd aquila
composer install

# Local config (never commit secrets)
cp .env .env.local
$EDITOR .env.local        # see §4

# idp.data checkout (anywhere on disk; example shown)
mkdir -p ~/papyri && cd ~/papyri
git clone https://github.com/papyri/idp.data
cd /path/to/aquila
ln -s ~/papyri/idp.data idp.data    # path must match $IDP_DATA_PATH

# Make sure BaseX is running (see §5), then build all three databases
bin/console app:basex:create-db

# Smoke-test
symfony serve -d
curl -s http://localhost:8000/hgv/381313 | grep -i 'inhalt'
```

---

## 4. Configuration reference (`.env`)

The committed `.env` holds defaults; **override per host in `.env.local`** (or
in real environment variables, which win over `.env*` files).

```env
###> symfony/framework-bundle ###
APP_ENV=dev                   # 'prod' on the server
APP_SECRET=<...>              # 32-char random hex; regenerate per env
###< symfony/framework-bundle ###

###> basex ###
# BaseX REST endpoint — BaseX's own default is :8984, our docker/dev uses :8080
BASEX_URL=http://localhost:8080/rest
BASEX_USER=admin
BASEX_PASSWORD=admin

# Where the HGV_meta_EpiDoc and DDB_EpiDoc_XML directories live.
# Relative paths are resolved against the project root, so a symlink at
# /path/to/aquila/idp.data is the conventional setup.
IDP_DATA_PATH=idp.data
###< basex ###
```

There is **no relational database** in use. The `DATABASE_URL` line in `.env`
is a leftover from an earlier Doctrine-based version and is unused.

---

## 5. BaseX server lifecycle

The Symfony app talks to BaseX only over HTTP (the REST API). The CLI rebuild
command (`app:basex:create-db`) shells out to the `basex` binary, which talks
to the **same** BaseX server via its admin client.

### Install

```bash
# macOS
brew install basex

# Debian / Ubuntu
sudo apt install basex     # or download the .zip from basex.org and unpack

# Verify
basex -V
basexhttp -V               # the HTTP/REST server
```

### Start / stop

The HTTP server is what serves `BASEX_URL`:

```bash
# Foreground (development, prints log to stdout)
basexhttp

# Background, with a different REST port (default 8984 → we use 8080)
basexhttp -h 8080 -d

# Stop a backgrounded server
basexhttp stop
```

Defaults BaseX listens on:

| Port | Purpose |
|---|---|
| 1984 | client/server protocol (used by `basex -c` and the standalone `basexclient`) |
| 8984 | HTTP/REST (override with `-h <port>`) |
| 8985 | HTTP stop port |

We run REST on **8080** (see `BASEX_URL`). If you change it, update `.env.local`
to match. Credentials are set with `basex -c "PASSWORD <user>"` or in
`<basex-home>/.basex` (see BaseX docs); they must match `BASEX_USER` /
`BASEX_PASSWORD`.

### Upgrade

```bash
# Stop the HTTP server
basexhttp stop

# Update (macOS shown — same idea on apt)
brew upgrade basex

# Restart
basexhttp -h 8080 -d

# Optimise existing databases for the new BaseX version (safe, idempotent)
basex -c "OPEN hgv; OPTIMIZE ALL"
basex -c "OPEN ddb; OPTIMIZE ALL"
basex -c "OPEN keywords; OPTIMIZE ALL"
```

A version bump rarely requires a rebuild — `OPTIMIZE ALL` is enough. If a
release note explicitly says the on-disk format changed (the BaseX changelog
flags this), rebuild from scratch with `bin/console app:basex:create-db`.

---

## 6. BaseX databases

The Symfony command [`app:basex:create-db`](../src/Command/BaseXCreateDatabaseCommand.php)
is the canonical way to build/rebuild any of the three databases. Each
database is **dropped and re-created** by the command; downtime is the
duration of the import (a few minutes per DB).

```text
Usage:
  bin/console app:basex:create-db [<idp-data-path>] [--database=hgv|ddb|keywords]

Examples:
  bin/console app:basex:create-db                       # build all three
  bin/console app:basex:create-db --database=hgv        # only HGV
  bin/console app:basex:create-db --database=keywords   # only translations
  bin/console app:basex:create-db /custom/idp.data      # custom source path
```

> **Note** The first positional argument is the **path to `idp.data`**, not a
> branch name or database name. `--database=` is the option for selecting which
> DB to (re)build.

### 6.1 `hgv` — HGV_meta_EpiDoc

```bash
bin/console app:basex:create-db --database=hgv
# Runs (against the BaseX server):
#   DROP DB hgv
#   CREATE DB hgv $IDP_DATA/HGV_meta_EpiDoc
#   OPTIMIZE ALL
#   CREATE INDEX fulltext
```

Indexes built: text, attribute, fulltext.

### 6.2 `ddb` — DDB_EpiDoc_XML

```bash
bin/console app:basex:create-db --database=ddb
# Same as above but from $IDP_DATA/DDB_EpiDoc_XML.
```

### 6.3 `keywords` — translated HGV keywords

This database is **derived** from the current contents of `hgv` plus
[`data/keywords.csv`](../data/keywords.csv). It powers the multi-language
keyword search (`/`, `/search`) and the translation toggle on the detail view
(`/hgv/{id}`).

Build pipeline:

1. Extract all distinct `tei:keywords[@scheme='hgv']/tei:term` values from
   the `hgv` database (≈ 28 500 distinct strings as of 2026-06).
2. Translate each via `App\Service\KeywordTranslator`, which uses an EBNF
   grammar plus the CSV dictionary to compose translations for compound
   keywords like `Vertrag (Darlehen, Geld)`.
3. Emit a single XML document with one `<kw de="…" fr="…" en="…" es="…" it="…"/>`
   element per German term that has at least one translation (≈ 4 200 rows).
4. Load into BaseX as the `keywords` DB.

```bash
bin/console app:basex:create-db --database=keywords
```

Expected output ends with:

```
[OK] Keywords database created.

Verification
------------
HGV documents: 66...
DDB documents: 70...
Keyword translations: 4...
```

A failed XML import keeps the generated temp file (path printed in the error
message) so you can inspect what was malformed before re-running.

**When to rebuild `keywords`:**

| Trigger | Run |
|---|---|
| You edited `data/keywords.csv` | `app:basex:create-db --database=keywords` |
| You rebuilt `hgv` (new upstream terms may have appeared) | `app:basex:create-db --database=keywords` |
| You only changed `KeywordTranslator` PHP | `app:basex:create-db --database=keywords` |

The `keywords` DB does **not** depend on `idp.data` directly — it depends on
the `hgv` BaseX DB. Always rebuild `hgv` first when chasing upstream changes.

---

## 7. Updating the source data

### 7.1 `idp.data` (HGV + DDB upstream)

`idp.data/` is a symlink to a local git checkout of
[papyri/idp.data](https://github.com/papyri/idp.data). The conventional
working branch is `master`.

```bash
# Pull upstream
git -C "$(readlink idp.data)" fetch
git -C "$(readlink idp.data)" merge --ff-only papyri/master

# Rebuild the affected BaseX databases
bin/console app:basex:create-db --database=hgv
bin/console app:basex:create-db --database=ddb
bin/console app:basex:create-db --database=keywords     # see §6.3
```

If the symlink is broken or you want to point at a different checkout, drop
the symlink and create a new one (or set `IDP_DATA_PATH` in `.env.local` to
an absolute path).

### 7.2 `data/keywords.csv` (translation dictionary)

Columns: `category,origin,de,fr,en,es,it`. The `de` column is the canonical
key; `fr/en/es/it` are the four target languages.

**Editing manually:**

1. Open in a CSV-aware editor (LibreOffice Calc, vscode-csv, …).
2. Add rows or fill blank cells. Leave any column blank if you can't translate;
   the translator will simply omit that language for that term.
3. Save as **UTF-8, comma-separated, double-quote escaping**.
4. Rebuild the `keywords` DB:
   ```bash
   bin/console app:basex:create-db --database=keywords
   ```

**Bulk regeneration (helper script):**

[`script/translate_keywords.py`](../script/translate_keywords.py) holds the
in-repo translation table that was used for the original mass fill of the
CSV. It can be re-run to fill any newly-empty cells without touching ones
that already have content:

```bash
python3 script/translate_keywords.py
# → writes back to data/keywords.csv
# → prints any German keys it didn't recognise
```

Re-run `app:basex:create-db --database=keywords` afterwards.

> Tip: keep a backup before mass edits — the script does not version-control
> what it overwrites. `cp data/keywords.csv data/keywords.csv.bak` first.

---

## 8. Publication cache (`var/cache/publication_index.json`)

[`App\Service\PublicationService`](../src/Service/PublicationService.php)
maintains a flat JSON index of all `(series, volume, number) → HGV-id`
mappings, built by walking `idp.data/DDB_EpiDoc_XML` once and following
`<relation type="reprint-in">` stubs. It powers the **Publikationen** browse
view (`/publication`, `/publication/load/numbers`, `/publication/load/records`).

- **Location:** `var/cache/publication_index.json`
- **Size:** ~10 MB
- **TTL:** **24 hours** (hard-coded). When older than that, the next incoming
  request rebuilds it; the rebuild scan takes ~30 s on a warm filesystem.
- **There is no CLI command** to rebuild it. To force a fresh build:

  ```bash
  rm var/cache/publication_index.json
  # then hit any /publication* URL once to trigger the rebuild
  curl -s http://localhost:8000/publication >/dev/null
  ```

If `IDP_DATA_PATH` can't be resolved to a real directory, the cache silently
falls back to an empty index — symptom: empty publication list. Check the
symlink first.

---

## 9. HTML text snippets (cron)

The DDB transcription text is fetched from BaseX live, but the
**translation** snippets shown on the detail view come from pre-rendered HTML
files generated by Saxon from the EpiDoc XSLT stylesheets. These are
generated by [`script/updateTextSnippets.sh`](../script/updateTextSnippets.sh)
(itself a symlink to the production location; the committed template is
[`script/updateTextSnippets.sh.template`](../script/updateTextSnippets.sh.template)).

What it does:

1. `git pull` in `navigator/`, `navigator/epidoc-xslt/`, and `idp.data/`.
2. Run Saxon `transform` with `MakeAquila.xsl` → writes per-record HTML files
   into `idp.data/papyri/aquila/HGV_trans_EpiDoc_HTML/<id>.html`.
3. Log to `…/updateTextSnippets.log` (previous run rotated to `.log.old`).

The Symfony app reads those files at request time via
[`HgvRecord::getHtmlTranslation()`](../src/Dto/HgvRecord.php) — it currently
hard-codes the path `/mnt/sds_cifs/idp.data/papyri/aquila/HGV_trans_EpiDoc_HTML/`,
which is the production NFS mount. On a dev machine without that path, the
detail view simply shows no translation snippet (not an error).

**Setting up the cron** (one-off, on the deploy host):

```bash
cp script/updateTextSnippets.sh.template /home/ubuntu/navigator/updateTextSnippets.sh
chmod +x /home/ubuntu/navigator/updateTextSnippets.sh

# crontab -e   (as user ubuntu)
0 3 * * *  /home/ubuntu/navigator/updateTextSnippets.sh
```

**Running it manually:**

```bash
sudo -u ubuntu /home/ubuntu/navigator/updateTextSnippets.sh
tail -f /home/ubuntu/updateTextSnippets.log
```

Note that the script does **not** trigger a BaseX rebuild — it only refreshes
the HTML translation files. If you want both fresh BaseX data *and* fresh
translation snippets, run §7.1 first and the cron script second.

---

## 10. Symfony cache

Clear after editing configuration, translations (`translations/*.yaml`),
routes, or service wiring:

```bash
bin/console cache:clear            # current APP_ENV
bin/console cache:clear --env=prod # explicit env
```

In dev mode (`APP_ENV=dev`) Symfony also auto-invalidates Twig/translation
caches on file change, so manual clearing is usually only needed for
config/service changes.

The publication cache (§8) is **not** touched by `cache:clear`.

---

## 11. Routine maintenance checklist

| Cadence | Action |
|---|---|
| On every `idp.data` pull | `app:basex:create-db --database=hgv && app:basex:create-db --database=ddb && app:basex:create-db --database=keywords` |
| On every `data/keywords.csv` edit | `app:basex:create-db --database=keywords` |
| On a config / translation / route change | `bin/console cache:clear` |
| Nightly (cron) | `updateTextSnippets.sh` (text-snippet regeneration) |
| On BaseX upgrade | `basexhttp stop && brew upgrade basex && basexhttp -h 8080 -d` then `OPTIMIZE ALL` per DB |
| Ad-hoc | `rm var/cache/publication_index.json` if publication browse looks stale |

---

## 12. Troubleshooting

**`[ERROR] idp.data directory not found: <something>`**
The first positional argument to `app:basex:create-db` is interpreted as the
path to `idp.data` — typing e.g. `master` makes it look for a directory named
`master` at the project root. To pick a specific database use the option:
`--database=hgv|ddb|keywords`. The default path comes from `IDP_DATA_PATH`.

**`Unknown command: declare` / `Unknown command: let`** while building the
`keywords` database
Multi-line XQuery being passed to the BaseX CLI line-by-line. Fixed in
[`BaseXCreateDatabaseCommand::flattenXQuery()`](../src/Command/BaseXCreateDatabaseCommand.php).
If you see it again, the XQuery in question needs to be single-line or wrapped
through that helper.

**XML parse error during keywords import** (`Element type "kw" must be
followed by …`)
A keyword value contains an unescaped `"`. The fix is to escape attribute
values with `ENT_XML1 | ENT_QUOTES`. If you see this on a future run, the
temp XML file is **kept** (path is printed in the error) — open it at the
reported line number to see the offending keyword.

**Search for a foreign-language keyword returns nothing**
The `keywords` BaseX database isn't built (or is stale). Run
`bin/console app:basex:create-db --database=keywords` and verify that the
final output shows a non-zero `Keyword translations` count. Then hit a
multi-language query to confirm:

```bash
curl -s "http://localhost:8000/?filter[keywords]=contrat" | grep -c row_
```

**Publication browse shows an empty list**
Either `IDP_DATA_PATH` doesn't resolve to a real directory (check the
symlink), or the cache is corrupt. Delete `var/cache/publication_index.json`
and re-hit a `/publication*` URL.

**Detail view shows no translation snippet**
Expected on dev machines — `HgvRecord::getHtmlTranslation()` reads from
`/mnt/sds_cifs/idp.data/papyri/aquila/HGV_trans_EpiDoc_HTML/<id>.html`, which
only exists on the production host. The DDB transcription on the same page is
unaffected (it comes from BaseX live).

**`HGV_meta_EpiDoc directory not found at: <path>`**
The `IDP_DATA_PATH` resolves but doesn't contain the expected sub-directories.
Verify with `ls "$(readlink idp.data)"` — you should see `HGV_meta_EpiDoc/`
and `DDB_EpiDoc_XML/` at the top level of whatever the symlink points to.
