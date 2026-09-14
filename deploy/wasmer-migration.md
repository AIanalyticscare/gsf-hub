# Wasmer Migration Steps

This project already has the theme code and uploads in Git. To make the existing
Wasmer app at `https://wordpress-fn2te.wasmer.app/` match local, migrate the
WordPress database and then rewrite URLs from local to Wasmer.

## 1. Export the local database

Run from the repo root:

```bash
docker compose exec -T db mysqldump --single-transaction -uwordpress -pwordpress wordpress > deploy/gsf-hub.sql
```

Verify the dump exists:

```bash
ls -lh deploy/gsf-hub.sql
```

## 2. Optional: back up the current Wasmer database first

In the Wasmer dashboard for `wordpress-fn2te`, open the **Databases** tab and
copy the database values into these shell variables:

```bash
export WASMER_DB_HOST='YOUR_DB_HOST'
export WASMER_DB_PORT='YOUR_DB_PORT'
export WASMER_DB_NAME='YOUR_DB_NAME'
export WASMER_DB_USERNAME='YOUR_DB_USERNAME'
export WASMER_DB_PASSWORD='YOUR_DB_PASSWORD'
```

Back up the current hosted database:

```bash
docker run --rm mariadb:11 mysqldump \
  -h"$WASMER_DB_HOST" \
  -P"$WASMER_DB_PORT" \
  -u"$WASMER_DB_USERNAME" \
  -p"$WASMER_DB_PASSWORD" \
  "$WASMER_DB_NAME" > deploy/wordpress-fn2te-preimport.sql
```

## 3. Import the local dump into Wasmer

```bash
docker run --rm -i mariadb:11 mariadb \
  -h"$WASMER_DB_HOST" \
  -P"$WASMER_DB_PORT" \
  -u"$WASMER_DB_USERNAME" \
  -p"$WASMER_DB_PASSWORD" \
  "$WASMER_DB_NAME" < deploy/gsf-hub.sql
```

## 4. Rewrite the site URL on Wasmer

Local WordPress currently uses:

```text
home    = http://localhost:8090
siteurl = http://localhost:8090
```

In the Wasmer app dashboard, open the **SSH** tab and copy the SSH command for
the `wordpress-fn2te` app. After you connect, run:

```bash
wp search-replace 'http://localhost:8090' 'https://wordpress-fn2te.wasmer.app' --all-tables --skip-columns=guid
wp option update home 'https://wordpress-fn2te.wasmer.app'
wp option update siteurl 'https://wordpress-fn2te.wasmer.app'
wp theme activate gsf-hub-sunrise
wp rewrite flush --hard
wp cache flush
wp option get blogname
wp option get home
wp option get siteurl
```

Expected values after the rewrite:

```text
blogname = GSF Hub
home     = https://wordpress-fn2te.wasmer.app
siteurl  = https://wordpress-fn2te.wasmer.app
```

## 5. Verify the hosted site

Check:

- homepage content matches local
- header brand reads `GSF Hub`
- Sunrise child theme is active
- media assets load
- login works at `https://wordpress-fn2te.wasmer.app/wp-admin`

## 6. Persistence note for uploads

Wasmer's docs note that only data inside mounted volumes persists. If you plan
to keep uploading new media on Wasmer, configure a persistent volume for
`/app/wp-content/uploads` before treating the hosted site as production-ready.
