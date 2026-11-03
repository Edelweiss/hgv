# Aquila server cheat sheet (dev)

Kurz-Doku für den bestehenden Server-Checkout in:

- `/var/www/aquila_dev`

und BaseX unter:

- `/opt/basex`

---

## 1) BaseX + Java Setup (einmalig)

```bash
sudo apt update
sudo apt install -y openjdk-21-jre-headless unzip curl
```

```bash
cd /opt
sudo curl -L -o basex.zip https://files.basex.org/releases/12.2/BaseX122.zip
sudo unzip -q basex.zip
sudo ln -sfn /opt/BaseX122 /opt/basex
sudo ln -sfn /opt/basex/bin/basex /usr/local/bin/basex
sudo ln -sfn /opt/basex/bin/basexhttp /usr/local/bin/basexhttp
```

Wichtig (schreibbare BaseX-Dateien, da portable Layout):

```bash
sudo mkdir -p /opt/basex/data /opt/basex/.logs
sudo touch /opt/basex/.basex
sudo chown -R www-data:www-data /opt/basex/data /opt/basex/.logs /opt/basex/.basex
sudo chmod 775 /opt/basex/data /opt/basex/.logs
sudo chmod 664 /opt/basex/.basex
```

---

## 2) BaseX Service starten

`/etc/systemd/system/basexhttp.service`:

```ini
[Unit]
Description=BaseX HTTP Server
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/aquila_dev
ExecStart=/opt/basex/bin/basexhttp -h 8080
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

Aktivieren:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now basexhttp
sudo systemctl status basexhttp --no-pager
```

REST-Check (BaseX nutzt Digest Auth):

```bash
curl --digest -u admin:admin http://127.0.0.1:8080/rest | head
```

---

## 3) Dev-Kickstart nach `git pull`

```bash
cd /var/www/aquila_dev
git pull
sudo -u www-data composer install
```

Optional (bei seltsamen Cache-Fehlern nach Upgrades):

```bash
cd /var/www/aquila_dev
rm -rf var/cache/dev/* var/cache/prod/*
```

---

## 4) BaseX-Datenbanken neu bauen

Immer als `www-data` ausführen:

```bash
cd /var/www/aquila_dev
sudo -u www-data APP_ENV=dev APP_DEBUG=1 php bin/console app:basex:create-db --database=hgv
sudo -u www-data APP_ENV=dev APP_DEBUG=1 php bin/console app:basex:create-db --database=ddb
sudo -u www-data APP_ENV=dev APP_DEBUG=1 php bin/console app:basex:create-db --database=keywords
```

Alle auf einmal:

```bash
cd /var/www/aquila_dev
sudo -u www-data APP_ENV=dev APP_DEBUG=1 php bin/console app:basex:create-db
```

---

## 5) Prüfen, ob alles läuft

Symfony:

```bash
cd /var/www/aquila_dev
php bin/console --version
php bin/console about
```

BaseX service:

```bash
sudo systemctl status basexhttp --no-pager
```

BaseX DB-Liste:

```bash
sudo -u www-data /opt/basex/bin/basex -c "LIST"
```

Erwartet: `hgv`, `ddb`, `keywords`

Publication-Index neu erzwingen:

```bash
cd /var/www/aquila_dev
rm -f var/cache/publication_index.json
```

---

## 6) Häufige Probleme

### `Class "WebProfilerBundle" not found`
`APP_ENV=dev`, aber Dependencies wurden mit `--no-dev` installiert.

Fix:

```bash
cd /var/www/aquila_dev
sudo -u www-data composer install
```

### `/.basex could not be written` oder `Resource "/opt/basex/data/.../tbl.basex" not found`
Rechte/Dateien in `/opt/basex` fehlen.

Fix: Abschnitt **1) BaseX + Java Setup (einmalig)** erneut ausführen.
