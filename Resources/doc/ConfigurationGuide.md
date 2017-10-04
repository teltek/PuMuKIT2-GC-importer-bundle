# GCImporterBundle configuration

This is the GalicasterPRO Importer  Bundle Configuration Guide. Check our [README](../../README.md) to learn more about this bundle.

## Index

1. [Parameters](#1-parameters)
2. [Cron tool](#2-cron-tool)

## 1. Parameters

Add your GC Web Panel configuration to your `app/config/parameters_deploy.yml` file:

```
pumukit_gc_importer:
    host: 'http://web_panel_url.com'
    username: 'web_panel_username'
    password: 'web_panel_password'
```
Mandatory:
   - `host` is the GC Web Panel server URL.
   - `username` is the name of the account used to operate the GC Web Panel.
   - `password` is the password for the account used to operate the GC Web.

Opcional:
   - `legacy` If false, MMObjects are shown paginated (Default: false). Only use `legacy: false` if your GC PRO is in 2.x

## 2. Cron tool

List of PuMuKIT commands that must be configured with the cron tool.

### 2.1. Import

The `pumukit:gcimporter:import` console command allows to import all Galicaster videos that are linked to PuMuKIT's MultimediaObjects.
If you want to download the GC videos of only one PuMuKIT's MultimediaObject use the option `--id` followed by the GC mediapackage id.
Additionally, there is a mandatory argument that you must pass to the command: the share path where you would like to store the videos.

The recommendation for its use is to configure the cron tool on the PuMuKIT system, to execute this command periodically.
All videos already imported will be skipped.

Configure cron to synchronize PuMuKIT with GC. To do that, you need to add one of the following commands to the crontab file.

```
sudo crontab -e
```

The recommendation on a development environment is to run commands every minute.
The recommendation on a production environment is to run commands every day, e.g.: every day at time 23:40.

```
40 23 * * *     /usr/bin/php /var/www/html/pumukit2/app/console pumukit:gcimporter:import /mnt/matternhorn --env=prod
```

### 2.2. Remove

The `pumukit:gcimporter:remove` console command allows to remove all Galicaster videos after the deletion of any PuMuKIT's MultimediaObject.
If you want to remove the GC videos of only one PuMuKIT's MultimediaObject use the option `--id` followed by the GC mediapackage id.
Additionally, there is a mandatory argument that you must pass to the command: the share path where the videos are stored.

The recommendation for its use is to configure the cron tool on the PuMuKIT system, to execute this command periodically.
All videos already imported will be skipped.

Configure cron to synchronize PuMuKIT with GC. To do that, you need to add one of the following commands to the crontab file.

```
sudo crontab -e
```

The recommendation on a development environment is to run commands every minute.
The recommendation on a production environment is to run commands every day, e.g.: every day at time 23:40.

```
40 23 * * *     /usr/bin/php /var/www/html/pumukit2/app/console pumukit:gcimporter:remove /mnt/matternhorn --env=prod
```
