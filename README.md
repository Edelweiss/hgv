# Aquila — Heidelberger Gesamtverzeichnis (HGV)

Web application for browsing, searching and displaying the **Heidelberger
Gesamtverzeichnis der griechischen Papyrusurkunden Ägyptens** — a catalogue of
metadata about Greek (and related) papyrus documents from Egypt, together with
the corresponding edition texts from the Duke Databank of Documentary Papyri
(DDB).

## What it is

- **Frontend:** Symfony 5.4 / Twig server-rendered HTML, jQuery + DataTables on
  the browse view, multilingual UI (de/en).
- **Data store:** [BaseX](https://basex.org) XML database (REST API) holding
  three databases:
  - `hgv` — HGV_meta_EpiDoc TEI files (≈ 66 000 documents)
  - `ddb` — DDB_EpiDoc_XML edition files (≈ 70 000 documents)
  - `keywords` — translations of the HGV keyword vocabulary into
    French/English/Spanish/Italian (≈ 4 200 entries; built from
    [`data/keywords.csv`](data/keywords.csv))
- **No relational database** is used at runtime (Doctrine/MariaDB was removed
  in mid-2025); only file-system caches and BaseX.
- Source data lives in `idp.data/`, a symlink to a local git checkout of the
  papyri.info `idp.data` repository.

## Quick start

```bash
# 1. PHP deps
composer install

# 2. configure the BaseX endpoint and idp.data path
cp .env .env.local      # then edit .env.local — see docs/operations.md
                        # for BASEX_URL / BASEX_USER / BASEX_PASSWORD / IDP_DATA_PATH

# 3. make sure BaseX is running and idp.data is checked out, then build the
#    three BaseX databases (one-off; ~10 min total)
bin/console app:basex:create-db

# 4. dev server
symfony serve   # or: php -S 0.0.0.0:8000 -t public
```

Open <http://localhost:8000/>.

## Operations & maintenance

All runbook material — installing & running BaseX, rebuilding individual
databases, updating `idp.data`, refreshing the keyword translations, the
publication cache, the text-snippets cron, troubleshooting — lives in:

**→ [docs/operations.md](docs/operations.md)**

## Repository layout

```
.env                    Default config (BaseX endpoint, idp.data path)
basex/                  Standalone BaseX setup script + XQuery module
bin/console             Symfony CLI entry point
config/                 Symfony config, routes, services
data/keywords.csv       Source of truth for keyword translations (de→fr/en/es/it)
docs/operations.md      Operations / runbook
idp.data/               Symlink to the HGV/DDB EpiDoc data (papyri.info repo)
public/                 Web root (index.php, CSS, JS, static text snippets)
script/                 Maintenance scripts (snippet generation, keyword
                        helper, DDB id diff, ...)
src/                    PHP code
  Command/                  app:basex:create-db
  Controller/               Browse, Publication, Shortcut, ...
  Dto/HgvRecord.php         Value object for one HGV record
  Service/HgvXmlService.php XQuery layer against BaseX (hgv + ddb + keywords)
  Service/KeywordTranslator EBNF-parser-based translator built on keywords.csv
  Service/PublicationService Lazy file cache for publication browse
templates/              Twig templates
translations/           messages.de.yaml, messages.en.yaml
var/cache/              Symfony cache + publication_index.json
```

## License

See [LICENSE](LICENSE).
