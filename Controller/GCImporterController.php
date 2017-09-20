<?php

namespace Pumukit\GCImporterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/admin")
 */
class GCImporterController extends Controller
{
    /**
     * @Route("/gcimporter", name="pumukit_gcimporter")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        if (!$this->has('pumukit_gcimporter.client')) {
            throw new \Exception('GCImporterBundle not configured');
        }
        $limit = 10;
        $page = $request->get('page', 1);

        $client = $this->get('pumukit_gcimporter.client');
        $this->get('session')->set('gchost', $client->getHost());
        $criteria = $this->getCriteria($request);
        $mp = $client->getMediaPackages((isset($criteria['name'])) ? $criteria['name']->regex : '', $limit, $limit * ($page - 1));

        $adapter = new FixedAdapter($mp[0], array_slice($mp, 1));
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage($limit);
        $pagerfanta->setCurrentPage($page);
        $repository_multimediaobjects = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $currentPageGalicasterIds = array();
        foreach ($mp as $mediaPackage) {
            $currentPageGalicasterIds[] = $mediaPackage['id'];
        }
        $repo = $repository_multimediaobjects->createQueryBuilder()
            ->field('properties.galicaster')->exists(true)
            ->field('properties.galicaster')->in($currentPageGalicasterIds)
            ->getQuery()
            ->execute();

        return array('mediaPackages' => $pagerfanta, 'multimediaObjects' => $repo);
    }

    /**
     * @Route("/gcimporter/import/{id}", name="pumukit_gcimporter_import")
     * @Template()
     */
    public function importAction($id, Request $request)
    {
        $importService = $this->get('pumukit_gcimporter.import');
        if (!$importService->importRecording($id, $request->get('invert'))) {
            throw new \Exception(sprintf('Error Importing MediaPackage %s', $id));
        }

        return $this->redirectToRoute('pumukit_gcimporter');
    }

    /**
     * Gets the criteria values.
     */
    public function getCriteria($request)
    {
        $criteria = $request->get('criteria', array());

        if (array_key_exists('reset', $criteria)) {
            $this->get('session')->remove('admin/gcimporter/criteria');
        } elseif ($criteria) {
            $this->get('session')->set('admin/gcimporter/criteria', $criteria);
        }
        $criteria = $this->get('session')->get('admin/gcimporter/criteria', array());

        $new_criteria = array();

        foreach ($criteria as $property => $value) {
            //preg_match('/^\/.*?\/[imxlsu]*$/i', $e)
            if ('' !== $value) {
                $new_criteria[$property] = new \MongoRegex('/'.$value.'/i');
            }
        }

        return $new_criteria;
    }
}
