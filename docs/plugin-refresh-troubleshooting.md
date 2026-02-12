# Troubleshooting `bin/console plugin:refresh`

If `plugin:refresh` fails with:

- `SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for database failed`

then the configured database hostname cannot be resolved from the environment where you execute `bin/console`.

## 1) Verify where you run the command

- **On host machine** (outside Docker): use `localhost` (or `127.0.0.1`) in `DATABASE_URL`.
- **Inside the `app` container**: use the Compose service name `database` in `DATABASE_URL`.

Examples:

```dotenv
# host execution
DATABASE_URL=mysql://root:root@localhost/shopware

# inside container execution
DATABASE_URL=mysql://root:root@database/shopware
```

## 2) Check the active `DATABASE_URL`

```bash
php -r 'require "vendor/autoload.php"; echo ($_SERVER["DATABASE_URL"] ?? $_ENV["DATABASE_URL"] ?? "<not-set>") . PHP_EOL;'
```

Also verify `.env`, `.env.local`, and exported shell variables. Symfony loads the most specific value, and a stale `.env.local` often overrides `.env`.

## 3) If you use Docker Compose

Start services and run the command in the app container:

```bash
docker compose up -d database app

docker compose exec app bin/console plugin:refresh
```


## Fix for your exact setup (`localhost` in PhpStorm)

If your DB is reachable at `localhost:3306` from your host machine (as in your screenshot), then run `bin/console` with:

```dotenv
DATABASE_URL=mysql://root:root@localhost:3306/shopware
```

and keep `database:3306` **only** for commands executed inside Docker containers.

Quick host-side one-shot command:

```bash
DATABASE_URL=mysql://root:root@localhost:3306/shopware bin/console plugin:refresh
```

## 4) Pickware class-scan warnings

Warnings such as:

- `Could not scan for classes inside .../custom/plugins/Pickware/vendor/...`

usually mean the plugin dependencies are incomplete. Reinstall vendor packages in that plugin:

```bash
composer install -d custom/plugins/Pickware
```

or reinstall/update the plugin package so that its `vendor/` tree is complete.

## 5) Composer warning for `shopware/core` constraint `*`

For custom plugins, replace unbound constraints like `"shopware/core": "*"` with a bounded version range (example: `"~6.6.0 || ~6.7.0"`) to avoid refresh warnings.
