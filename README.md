# WFO Plant List - Publication Platform

This is an application to serve the twice yearly data releases of the World Flora Online Plant List through an API and simple UI for name matching.

It also provides an API that allows descriptive content to be updated associated SOLR index and to federate data between application instances.

You can access the live, normative, version of the API here:

https://list.worldfloraonline.org/

Although this is primarily designed to be hosted by the WFO as part of its online offering should also be feasible to install and run all or part of it locally if needed. You are encouraged to install a local instance if your intended usage might adversely affect the performance of the main server e.g. you are name matching over a large corpus of text.

Forks, ports and suggestions for improvements are very welcome!

## Dependencies

* SOLR Index - This is the same SOLR instance that is used to power the WFO public facing website. A detailed description of how to install it is given in the README file of the [website repository](https://github.com/worldflora/wfo-p2)
* PHP 8.*
* Composer packages.
  * "easyrdf/easyrdf": "*",
  * "ml/json-ld": "*",
  * "webonyx/graphql-php": "*"

### OS Software

Default platform tested here is __Ubuntu Server 26.04.1 LTS__ but other OS setups would probably work.

- Starting with a fresh install of __Ubuntu Server 26.04.1 LTS__.
- `sudo apt install net-tools` - for convenience.
- `sudo apt install zip` - required.

### Apache and PHP

Install Apache2 and PHP. The list of modules includes a few that may not be in use now but are likely to be used with code updates in the future.

```
sudo apt install apache2
sudo apt install php libapache2-mod-php
sudo apt install php-bz2 php-curl php-dom php-gd php-mbstring php-simplexml php-xml php-xmlreader php-xmlwriter php-xsl php-zip php-bcmath php-sqlite3
sudo a2enmod headers rewrite proxy proxy_http
sudo systemctl restart apache2
```

#### Install code

We assume the application will live in `/var/wfo-home` alongside any other WFO infrastructure applications that may be placed on the same machine. Having a consistent approach is helpful even if they are on separate machines.

Ownership of the wfo-home/ directory should not be a user or a group could be established for the purpose if multiple users will be administering the code. This is a sysadmin decision.

```
cd /var/
sudo mkdir wfo-home/
sudo chown -R <user>:root wfo-home/
cd wfo-home
git clone https://github.com/worldflora/wfo-plant-list.git
cd ..
cp wfo-plant-list/wfo_secrets.php .
nano wfo_secrets.php
```

### Composer

The application needs some packages that are managed using Composer. Install Composer following the instructions here https://getcomposer.org/download/ 



Then run `php composer.phar update` to install dependencies.







