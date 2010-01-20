Invoice Module
==================
To install the Invoice module, download version 0.6.6 of Seagull,
export the invoice module from the repository and run the installer.

This module requires that data/imports be aliased to the network share of the OCR system's export folder with delete permissions (777).
(This is currently at /POD2/Exports on morpheus).
A cron job will also need to be configured to run every 10 minutes, see usage for cron command.

Dependencies
============
Seagull 0.6.6, with CMS, media, emailer2 and emailqueue modules installed.
PEAR Libraries DB_DataObject, System, Pager
Garick Libraries GarickDataAccessStrategy & XMLParsingStrategy

Homepage
========
N/A

Usage
=====

The ImportMgr is CLI-only. It should be run via cron as follows:
su - www-data -c "php /var/www/vhosts/vendors/seagull-0.6.5/www/index.php  --moduleName=invoice --managerName=import --action=list" > /dev/null 2>&1

Bugs
====
Please notify developers of any bugs at clay.hinson@garick.com

-- Clay Hinson
