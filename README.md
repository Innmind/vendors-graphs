# vendors-graphs

[![Build Status](https://github.com/innmind/vendors-graphs/workflows/CI/badge.svg?branch=main)](https://github.com/innmind/vendors-graphs/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/innmind/vendors-graphs/branch/develop/graph/badge.svg)](https://codecov.io/gh/innmind/vendors-graphs)
[![Type Coverage](https://shepherd.dev/github/innmind/vendors-graphs/coverage.svg)](https://shepherd.dev/github/innmind/vendors-graphs)

Description

## Installation

### On Debian

```sh
sudo apt update
sudo apt upgrade
sudo apt install lsb-release ca-certificates curl
sudo curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
sudo sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
sudo apt update
sudo apt install php8.2-cli php8.2-common php8.2-dom php8.2-curl php8.2-zip
sudo apt install vim
sudo apt install graphviz
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
sudo apt install git
git clone https://github.com/Innmind/vendors-graphs.git
cd vendors-graphs/
composer install
./bin/console add innmind
./bin/console add formal
./bin/console add symfony
./bin/console add laravel
./bin/console add illuminate
./bin/console add league
./bin/console add bref
./bin/console add phpunit
./bin/console add vimeo
./bin/console render
sudo apt install supervisor
vi /etc/supervisor/conf.d/http-server.conf
```

Inject this content in `http-server.conf`:

```
[program:http-server]
user=root
command=php bin/http-server --port=80 --no-output --allow-anyone
directory=/root/vendors-graphs
autostart=false
autorestart=true

```

```sh
sudo service supervisor restart
supervisorctl start http-server
sudo apt install cron
crontab -e
```

Add the following crontab:

```
0 4,13,18 * * * cd /root/vendors-graphs && ./bin/console update-vendors && ./bin/console render-vendor
```

## Usage

Todo

## Known issues

When this app is deployed on a server and exposed with the built-in http server, the pages are cut in the middle of SVGs. Even though the server sends the complete html content, the browser doesn't display it.

For some reason when the svg is accessed via a `img` tag (so as a dedicated http call), then the whole content is rendered correctly.

The problem with this approach is that now the links in SVGs no longer work.
