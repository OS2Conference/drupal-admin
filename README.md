# OS2ConTicki Drupal admin

An “[admin](https://github.com/OS2ConTicki/OS2ConTicki#implementations)”
implementation for [OS2ConTicki](https://github.com/OS2ConTicki/OS2ConTicki)
built in Drupal 9.

Contains content types for all the [OS2ConTicki
entities](https://github.com/OS2ConTicki/OS2ConTicki) and roles and permissions
for managing them.

All entities (apart from conferences) are created in the context of a
conference, belong to this conference and can only refer to other entities
within the same conference.

This means that a conference has to be created by a “conference administrator”
before events, locations and speakers etc. can be created by a “conference
editor”. An editor can create everything apart from conferences, tags and
themes.

## Installation

Create local settings file with database connection:

```sh
cat <<'EOF' > web/sites/default/settings.local.php
<?php
$databases['default']['default'] = [
 'database' => getenv('DATABASE_DATABASE') ?: 'db',
 'username' => getenv('DATABASE_USERNAME') ?: 'db',
 'password' => getenv('DATABASE_PASSWORD') ?: 'db',
 'host' => getenv('DATABASE_HOST') ?: 'mariadb',
 'port' => getenv('DATABASE_PORT') ?: '',
 'driver' => getenv('DATABASE_DRIVER') ?: 'mysql',
 'prefix' => '',
];
EOF
```

```sh
docker-compose up -d
docker-compose exec phpfpm composer install
docker-compose exec phpfpm vendor/bin/drush --yes site:install minimal --config-dir=../config/sync
# Get the site url
echo "http://$(docker-compose port nginx 80)"
# Get admin sign in url
docker-compose exec phpfpm vendor/bin/drush --yes --uri="http://$(docker-compose port nginx 80)" user:login
```

### Using `symfony` binary

```sh
docker-compose up -d
symfony composer install
symfony php vendor/bin/drush --yes site:install minimal --config-dir=../config/sync
# Start the server
symfony local:server:start --port=8887 --daemon --allow-http
# Get the site url
echo "http://127.0.0.1:8887"
# Get admin sign in url
symfony php vendor/bin/drush --uri=http://127.0.0.1:8887 user:login
```

## Configuration

See
[web/modules/custom/os2conticki_app/README.md](web/modules/custom/os2conticki_app/README.md).

## Fixtures

```sh
symfony php vendor/bin/drush --yes pm:enable os2conticki_fixtures
symfony php vendor/bin/drush --yes pm:uninstall entity_reference_integrity
symfony php vendor/bin/drush content-fixtures:load
symfony php vendor/bin/drush --yes pm:enable entity_reference_integrity
symfony php vendor/bin/drush --yes pm:uninstall content_fixtures
```

After loading user fixtures you can sign in as a user without super-user
privileges:

```sh
symfony php vendor/bin/drush --uri=https://127.0.0.1:8887 user:login administrator@example.com
symfony php vendor/bin/drush --uri=https://127.0.0.1:8887 user:login organizer@example.com
```

## Conference API

```sh
https://127.0.0.1:8887/api
```

## Coding standards

```sh
composer coding-standards-check
composer coding-standards-apply
```

See also
[web/modules/custom/os2conticki_content/README.md#coding-standards](web/modules/custom/os2conticki_content/README.md#coding-standards).

## Custom conference urls

For custom conference urls to work proxies must be set up. In this example we
use `https://my-conference.example.com` as custom url for the conference app
`https://conticki.example.com/app/1/`.

Assuming DNS configuration has been made to make the domain
`my-conference.example.com` point to the server hosting ConTicki admin
(`conticki.example.com`) something along the lines of the following server
proxies must be set up:

### nginx

```nginx
server {
  listen 443 ssl;
  server_name my-conference.example.com;

  location / {
    # Note the trailing slash.
    proxy_pass https://conticki.example.com/app/1/;
  }
}
```

### Apache

```apache
<VirtualHost _default_:443>
  ServerName my-conference.example.com

  SSLProxyEngine on

  <Location />
    # Note the trailing slash.
    ProxyPass https://conticki.example.com/app/1/
    ProxyPassReverse https://conticki.example.com/app/1/
  </Location>
</VirtualHost>
```

### Cross-Origin Resource Sharing (CORS)

Finally, the main server must add proper
[CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS) headers to
support custom app urls:

#### Nginx

```nginx
# We need this to properly support custom app urls.
add_header 'access-control-allow-origin' '*';
add_header 'access-control-allow-methods' 'GET';
```

#### Apache

```apache
Header set access-control-allow-origin "*"
Header set access-control-allow-methods "GET"
```
