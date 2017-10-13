<?php

namespace Pumukit\GCImporterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     * @Security("is_granted('ROLE_ACCESS_GC_IMPORTER')")
     */
    public function indexAction(Request $request)
    {
        if (!$this->has('pumukit_gcimporter.client')) {
            throw new \Exception('GCImporterBundle not configured');
        }
        $limit = 10;
        $page = $request->get('page', 1);

        try {
            $client = $this->get('pumukit_gcimporter.client');
        } catch (\Exception $exception) {
            return array('error' => $exception->getMessage());
        }

        $this->get('session')->set('gchost', $client->getHost());
        $criteria = $this->getCriteria($request);

        try {
            $mp = $client->getMediaPackages((isset($criteria['name'])) ? $criteria['name']->regex : '', $limit, $limit * ($page - 1));
        } catch (\Exception $exception) {
            return array('error' => $exception->getMessage());
        }

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

        $series = $request->get('series', null);
        $seriesArray = array();
        if ($series) {
            $seriesRepo = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:Series');
            array_push($seriesArray, $series); // Series ID
            array_push($seriesArray, $seriesRepo->find($series)->getTitle()); // Series Title
        }

        return array('mediaPackages' => $pagerfanta, 'multimediaObjects' => $repo, 'series' => $seriesArray);
    }

    /**
     * @Route("/gcimporter/import/{id}", name="pumukit_gcimporter_import")
     * @Template()
     * @Security("is_granted('ROLE_ACCESS_GC_IMPORTER')")
     */
    public function importAction($id, Request $request)
    {
        $importService = $this->get('pumukit_gcimporter.import');
        if (!$importService->importRecording($id, $request->get('invert'), $request->get('series', null))) {
            throw new \Exception(sprintf('Error Importing MediaPackage %s', $id));
        }

        return $this->redirectToRoute('pumukit_gcimporter', array('series' => $request->get('series', null)));
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
