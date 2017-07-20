<?php
namespace Pumukit\GCImporterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pagerfanta\Adapter\FixedAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
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
        $client = $this->get('pumukit_gcimporter.client');
        $mp = $client->getMediaPackages();

        $criteria = $this->getCriteria($request);
        if (isset($criteria['name'])) {
            $mp =
                array_filter($mp, function ($e) use ($criteria) {
                return (strpos($e['title'], $criteria['name']->regex) !== false);
            });
        }
        $adapter = new FixedAdapter(count($mp), $mp);
        $pagerfanta = new Pagerfanta($adapter);

        //$pagerfanta->setAllowOutOfRangePages(false);
        $pagerfanta->setMaxPerPage(1);
        $pagerfanta->setCurrentPage($request->get('page', 1));
        $repository_multimediaobjects = $this->get('doctrine_mongodb')->getRepository('PumukitSchemaBundle:MultimediaObject');
        $currentPageGalicasterIds = array();
        foreach ($mp as $mediaPackage) {
            $currentPageGalicasterIds[] = $mediaPackage['id'];
        }
        $repo = $repository_multimediaobjects->createQueryBuilder()
            ->field('properties.opencast')->exists(true)
            ->field('properties.opencast')->in($currentPageGalicasterIds)
            ->getQuery()
            ->execute();
        return array('mediaPackages' => $pagerfanta, 'multimediaObjects' => $repo);
    }

    /**
     * @Route("/gcimporter/import/{id}", name="pumukit_gcimporter_import")
     * @Template()
     */
    public function importAction($id, $invert = false)
    {
        $importService = $this->get('pumukit_gcimporter.import');
        $importService->importRecording($id, $invert);
        $this->importFiles($id);
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
        }
        elseif ($criteria) {
            $this->get('session')->set('admin/gcimporter/criteria', $criteria);
        }
        $criteria = $this->get('session')->get('admin/gcimporter/criteria', array());

        $new_criteria = array();

        foreach ($criteria as $property => $value) {
            //preg_match('/^\/.*?\/[imxlsu]*$/i', $e)
            if ('' !== $value) {
                $new_criteria[$property] = new \MongoRegex('/' . $value . '/i');
            }
        }

        return $new_criteria;
    }

    private function importFiles($id)
    {
        $kernel = $this->get('kernel');
        dump('su apache -c /usr/bin/php ' . $kernel->getRootDir() . '/console pumukit:gcimporter:import ' . $id);
        $process = new Process('su apache -C /usr/bin/php ' . $kernel->getRootDir() . '/console pumukit:gcimporter:import ' . $id);
        $process->start();
    }
}
