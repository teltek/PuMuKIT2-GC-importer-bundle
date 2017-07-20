# GCImporter Bundle
With this Bundle your GalicasterPRO mediapackages can be imported and published into your PuMuKIT Web TV Portal.

In order to use it, it must be downloaded, configured and installed.

1. Install the bundle by executing the following command.
```bash
php app/console pumukit:install:bundle Pumukit/GCImporterBundle/PumukitGCImporterBundle
```
2. Update assets.
```bash
$ php app/console cache:clear
$ php app/console cache:clear --env=prod
$ php app/console assets:install
```