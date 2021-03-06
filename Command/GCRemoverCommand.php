<?php

namespace Pumukit\GCImporterBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;

class GCRemoverCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('pumukit:gcimporter:remove')
            ->setDescription('Remove Galicaster videos which belong to deleted MultimediaObjects')
            ->addArgument('shared_path', InputArgument::REQUIRED, 'Pumukit shared path')
            ->addOption('id', 'id', InputOption::VALUE_OPTIONAL, 'MediaPackage ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $trackservice = $this->getContainer()->get('pumukitschema.track');
        $multimediaobjectsRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $sharedPath = $input->getArgument('shared_path');
        $sharedPath = ('/' == substr($sharedPath, -1)) ? $sharedPath : $sharedPath.'/';
        if ($input->getOption('id')) {
            $multimediaobject = $multimediaobjectsRepo->findOneBy(array('properties.galicaster' => $input->getOption('id')));
            $this->check($multimediaobject, $sharedPath.$input->getOption('id'));
        } else {
            $dirs = scandir($sharedPath);
            foreach ($dirs as $dir) {
                if ($dir == '.' || $dir == '..') {
                    continue;
                }
                $multimediaobject = $multimediaobjectsRepo->findOneBy(array('properties.galicaster' => $dir));
                $this->check($multimediaobject, $sharedPath.$dir);
            }
        }
    }

    private function check($multimediaobject, $path)
    {
        if ($multimediaobject || !is_dir($path)) {
            return;
        }
        $this->remove($path);
    }

    private function remove($path)
    {
        $files = glob($path.'/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->remove($file) : unlink($file);
        }
        rmdir($path);

        return;
    }
}
