# coreERP-ce
Community Edition of coreERP

This is the community edition of coreERP. It is a free to use edition with support only on community forum.

Quick Setup Guide
=================

This is a quick setup guide to install and setup coreERP. The following dependencies are required on the linux box or a docker instance of linux. 
Current list of commands mentioned, have been tested on Ubuntu 18.04. If you plan to use any other distribution, the paths or commands may change accordingly.

External Dependencies
---------------------
1. Postgresql Server 10. (detailed instructions at http://help.coreerp.net/docs/setup-guide/server-setup.html#install-postgresql)
2. Apache2 (proxy_fcgi, mpm_event are optional) (detailed instructions at http://help.coreerp.net/docs/setup-guide/server-setup.html#install-apache2)
3. PHP 7.3 or higher ::
    ```$ sudo apt-get install php7.3 php7.3-fpm php7.3-mbstring php7.3-xml php7.3-pgsql```
4. Java 1.8 (required for rendering reports) ::
    ```$ sudo apt-get install openjdk-8-jre-headless```
5. Liberation Fonts ::
    ```$ sudo apt-get install fonts-liberation2```

Dependent Repositories
----------------------
1. coreERP-vendor (https://github.com/vishwayon/coreERP-vendor). Create symbolic link to the vendor folder in coreERP-ce.
2. coreERP-rsv (https://github.com/vishwayon/coreERP-rsv). This is an optional repo. You may download only the release tar.gz for rendering reports. 
Follow instructions in README to complete report server installation.

Setup Steps
-----------
1. Create a link in /var/www/html to the coreERP-ce/web folder and publish it as *core-erp* (or any name of your preference).
2. Make the following folders writable
    - runtime
    - web/assets
3. Create cwfconfig.php. Refer http://help.coreerp.net/docs/setup-guide/install-coreerp.html#editing-the-config-file
4. Create initial database. Refer http://help.coreerp.net/docs/setup-guide/install-coreerp.html#setting-up-the-database

Verify Installation
-------------------

If everything went off successfully, you should be able to open coreERP login screen using the following link:
    http://localhost/core-erp

Proceed to the topic Getting Started (http://help.coreerp.net/docs/setup-guide/getting-started.html)