<?php

namespace Pumukit\GCImporterBundle\Services;

use Pumukit\NewAdminBundle\Menu\ItemInterface;

class SeriesButtonService implements ItemInterface
{
    public function getName()
    {
        return 'GC-Importer';
    }

    public function getUri()
    {
        return 'pumukit_gcimporter';
    }

    public function getAccessRole()
    {
        return 'ROLE_ACCESS_GC_IMPORTER';
    }
}
