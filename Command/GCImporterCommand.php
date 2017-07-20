<?php
namespace Pumukit\GCImporterBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;

class GCImporterCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('pumukit:gcimporter:import')
            ->setDescription('Import Galicaster videos to shared folder')
            ->addArgument('id', InputArgument::OPTIONAL, 'MediaPackage ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dm = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $trackservice = $this->getContainer()->get('pumukitschema.track');
        $multimediaobjectsRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        if ($input->getArgument('id')) {
            $multimediaobjects = array($multimediaobjectsRepo->findOneBy(array('properties.galicaster' => $input->getArgument('id'))));
        }
        else {
            $multimediaobjects = $multimediaobjectsRepo->findBy(array('tracks.tags' => 'todownload'));
        }
        if ($multimediaobjects && $multimediaobjects[0]) {
            foreach ($multimediaobjects as $multimediaobject) {
                foreach ($multimediaobject->getTracks() as $track) {
                    if ($track->containsTag('todownload') && $track->getUrl()) {
                        $this->import($track, '/mnt/matternhorn/' . $multimediaobject->getProperty('galicaster') . '/' . $track->getId(), $output);
                        $trackservice->updateTrackInMultimediaObject($multimediaobject, $track);
                    }
                }
            }
        }
    }
    private function import($track, $path, $output)
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, True);
        }
        $this->download($track->getUrl(), $path . '/' . basename($track->getUrl()), $output);
        $track->setPath($path . '/' . basename($track->getUrl()));
        $track->removeTag('todownload');
    }
    private function download($src, $target, $output)
    {
        $output->writeln('Downloading multimedia files to init the database:');
        $progress = new \Symfony\Component\Console\Helper\ProgressBar($output, 100);
        $progress->start();

        $ch = curl_init($src);
        $targetFile = fopen($target, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $targetFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($c, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($progress) {
            $percentage = ($downloaded > 0 && $downloadSize > 0 ? round($downloaded / $downloadSize, 2) : 0.0);
            $progress->setProgress($percentage * 100);
        });
        curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($targetFile);
        curl_close($ch);
        $progress->finish();
        $output->writeln('');
        return 200 == $statusCode;
    }
}
