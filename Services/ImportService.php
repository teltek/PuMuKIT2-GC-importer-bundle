<?php
namespace Pumukit\GCImporterBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Services\FactoryService;
use Pumukit\SchemaBundle\Services\TrackService;
use Pumukit\SchemaBundle\Services\TagService;
use Pumukit\SchemaBundle\Services\MultimediaObjectService;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Track;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\InspectionBundle\Services\InspectionServiceInterface;

class ImportService
{
    private $client;
    private $dm;
    private $factoryService;
    private $trackService;
    private $tagService;
    private $mmsService;
    private $inspectionService;
    private $otherLocales;

    public function __construct(DocumentManager $documentManager, FactoryService $factoryService, TrackService $trackService, TagService $tagService, MultimediaObjectService $mmsService, ClientService $client, InspectionServiceInterface $inspectionService, array $otherLocales = array())
    {
        $this->client = $client;
        $this->dm = $documentManager;
        $this->factoryService = $factoryService;
        $this->trackService = $trackService;
        $this->tagService = $tagService;
        $this->mmsService = $mmsService;
        $this->inspectionService = $inspectionService;
        $this->otherLocales = $otherLocales;
    }

    /**
     * Import recording.
     *
     * Given a media package id
     * create a multimedia object
     * with the media package metadata
     *
     * @param string    $id
     * @param bool      $invert
     */
    public function importRecording($id, $invert = false)
    {
        $mediaPackage = $this->client->getMediaPackage($id);

        $seriesRepo = $this->dm->getRepository('PumukitSchemaBundle:Series');

        $seriesGalicasterId = $this->getMediaPackageField($mediaPackage, 'series');
        if ($seriesGalicasterId) {
            $series = $seriesRepo->findOneBy(array('properties.opencast' => $seriesGalicasterId));
        }
        else {
            $series = $seriesRepo->findOneBy(array('properties.opencast' => 'default'));
        }
        if (!$series) {
            $series = $this->importSeries($mediaPackage);
        }

        $onemultimediaobjects = null;
        $multimediaobjectsRepo = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mediaPackageId = $this->getMediaPackageField($mediaPackage, 'id');
        if ($mediaPackageId) {
            $onemultimediaobjects = $multimediaobjectsRepo->findOneBy(array('properties.galicaster' => $mediaPackageId));
        }

        if (null == $onemultimediaobjects) {
            $multimediaObject = $this->factoryService->createMultimediaObject($series, true);
            $multimediaObject->setSeries($series);

            $title = $this->getMediaPackageField($mediaPackage, 'title');
            if ($title) {
                $multimediaObject->setTitle($title);
            }

            $properties = $this->getMediaPackageField($mediaPackage, 'id');
            if ($properties) {
                $multimediaObject->setProperty('galicaster', $properties);

            }
            $multimediaObject->setProperty('opencastinvert', boolval($invert));

            $recDate = $this->getMediaPackageField($mediaPackage, 'start');
            if ($recDate) {
                $multimediaObject->setRecordDate($recDate);
            }

            $language = $this->getMediaPackageField($mediaPackage, 'language');
            if ($language) {
                $multimediaObject->setProperty('opencastlanguage', strtolower($language));
            }
            else {
                $multimediaObject->setProperty('opencastlanguage', \Locale::getDefault());
            }

            foreach ($this->otherLocales as $locale) {
                $multimediaObject->setTitle($title, $locale);
            }

            $media = $this->getMediaPackageField($mediaPackage, 'media');
            $tracks = $this->getMediaPackageField($media, 'track');
            if (isset($tracks[0])) {
                // NOTE: Multiple tracks
                for ($i = 0; $i < count($tracks); ++$i) {
                    $track = $this->createTrackFromMediaPackage($mediaPackage, $multimediaObject, $i);
                }
            }
            else {
                // NOTE: Single track
                $track = $this->createTrackFromMediaPackage($mediaPackage, $multimediaObject);
            }

            $attachments = $this->getMediaPackageField($mediaPackage, 'attachments');
            $attachment = $this->getMediaPackageField($attachments, 'attachment');
            if (isset($attachment[0])) {
                for ($j = 0; $j < count($attachment); ++$j) {
                    $multimediaObject = $this->createPicFromAttachment($attachment, $multimediaObject, $j);
                }
            }
            else {
                $multimediaObject = $this->createPicFromAttachment($attachment, $multimediaObject);
            }

            $tagRepo = $this->dm->getRepository('PumukitSchemaBundle:Tag');
            $opencastTag = $tagRepo->findOneByCod('TECHOPENCAST');
            if ($opencastTag) {
                $tagService = $this->tagService;
                $tagAdded = $tagService->addTagToMultimediaObject($multimediaObject, $opencastTag->getId());
            }

            $multimediaObject = $this->mmsService->updateMultimediaObject($multimediaObject);

        }
    }

    private function importSeries($mediaPackage)
    {
        $publicDate = new \DateTime('now');

        $seriesGalicasterId = $this->getMediaPackageField($mediaPackage, 'series');
        if ($seriesGalicasterId) {
            $title = $this->getMediaPackageField($mediaPackage, 'seriestitle');
            $properties = $seriesGalicasterId;
        }
        else {
            $title = 'MediaPackages without series';
            $properties = 'default';
        }

        $series = $this->factoryService->createSeries();
        $series->setPublicDate($publicDate);
        $series->setTitle($title);
        foreach ($this->otherLocales as $locale) {
            $series->setTitle($title, $locale);
        }

        $series->setProperty('opencast', $properties);

        $this->dm->persist($series);
        $this->dm->flush();

        return $series;
    }


    public function getMediaPackageField($mediaFields = array(), $field = '')
    {
        if ($mediaFields && $field) {
            if (isset($mediaFields[$field])) {
                return $mediaFields[$field];
            }
        }

        return null;
    }

    public function createTrackFromMediaPackage($mediaPackage, MultimediaObject $multimediaObject, $index = null)
    {
        $media = $this->getMediaPackageField($mediaPackage, 'media');
        $tracks = $this->getMediaPackageField($media, 'track');
        if ($tracks) {
            if (null === $index) {
                $galicasterTrack = $tracks;
            }
            else {
                $galicasterTrack = $tracks[$index];
            }
        }
        else {
            return null;
        }

        $track = new Track();

        $language = $this->getMediaPackageField($mediaPackage, 'language');
        if ($language) {
            $track->setLanguage(strtolower($language));
        }

        $tagsArray = $this->getMediaPackageField($galicasterTrack, 'tags');
        $tags = $this->getMediaPackageField($tagsArray, 'tag');
        if (isset($tags[0])) {
            // NOTE: Multiple tags
            for ($i = 0; $i < count($tags); ++$i) {
                $track = $this->addTagToTrack($tags, $track, $i);
            }
        }
        else {
            // NOTE: Single tag
            $track = $this->addTagToTrack($tags, $track);
        }

        $url = $this->getMediaPackageField($galicasterTrack, 'url');
        if ($url) {
            $track->setUrl($this->client->getHost() . $url);
            //$track->setPath($this->client->getHost() . $url);


        }

        $mime = $this->getMediaPackageField($galicasterTrack, 'mimetype');
        if ($mime) {
            $track->setMimeType($mime);
        }

        $duration = $this->getMediaPackageField($galicasterTrack, 'duration');
        if ($duration) {
            $track->setDuration($duration / 1000);
        }

        $audio = $this->getMediaPackageField($galicasterTrack, 'audio');
        $encoder = $this->getMediaPackageField($audio, 'encoder');
        $acodec = $this->getMediaPackageField($encoder, 'type');
        if ($acodec) {
            $track->setAcodec($acodec);
        }

        $video = $this->getMediaPackageField($galicasterTrack, 'video');
        $encoder = $this->getMediaPackageField($video, 'encoder');
        $vcodec = $this->getMediaPackageField($encoder, 'type');
        if ($vcodec) {
            $track->setVcodec($vcodec);
        }

        $framerate = $this->getMediaPackageField($video, 'framerate');
        if ($framerate) {
            $track->setFramerate($framerate);
        }

        if (!$track->getVcodec() && $track->getAcodec()) {
            $track->setOnlyAudio(true);
        }
        else {
            $track->setOnlyAudio(false);
        }

        $track->addTag('opencast');
        $track->addTag('todownload');

        $type = $this->getMediaPackageField($galicasterTrack, 'type');
        if ($type) {
            $track->addTag($galicasterTrack['type']);
        }

        if ($track->getPath()) {
            $this->inspectionService->autocompleteTrack($track);
        }

        $multimediaObject->setDuration($track->getDuration());

        $this->trackService->addTrackToMultimediaObject($multimediaObject, $track, false);

        return $track;
    }

    private function createPicFromAttachment($attachment, MultimediaObject $multimediaObject, $index = null)
    {
        if ($attachment) {
            if (null === $index) {
                $itemAttachment = $attachment;
            }
            else {
                $itemAttachment = $attachment[$index];
            }
            $type = $this->getMediaPackageField($itemAttachment, 'type');
            if ($type == 'presenter/search+preview') {
                $tags = $this->getMediaPackageField($itemAttachment, 'tags');
                $url = $this->getMediaPackageField($itemAttachment, 'url');
                $url = $this->client->getHost() . $url;
                if ($tags || $url) {
                    $pic = new Pic();
                    if ($tags) {
                        foreach ($tags as $tag) {
                            $pic->addTag($tag);
                        }
                    }
                    if ($url) {
                        $pic->setUrl($url);
                    }
                    $multimediaObject->addPic($pic);
                }
            }
        }

        return $multimediaObject;
    }

    private function addTagToTrack($tags, Track $track, $index = null)
    {
        if ($tags) {
            if (null === $index) {
                $tag = $tags;
            }
            else {
                $tag = $tags[$index];
            }
            if (!$track->containsTag($tag)) {
                $track->addTag($tag);
            }
        }

        return $track;
    }
}
