<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster;

use Exception;
use PierreMiniggio\HeropostYoutubePosting\Exception\HeropostConfigurationException;
use PierreMiniggio\HeropostYoutubePosting\Exception\MaybeAlreadyPostedButScrapingException;
use PierreMiniggio\HeropostYoutubePosting\Exception\ScrapingException;
use PierreMiniggio\HeropostYoutubePosting\Exception\UnknownHeropostException;
use PierreMiniggio\HeropostYoutubePosting\Poster;
use PierreMiniggio\YoutubeThumbnailUploader\ThumbnailUploader;
use PierreMiniggio\YoutubeVideoUpdater\VideoUpdater;
use Psr\Log\LoggerInterface;

class VideoPoster
{
    
    public function __construct(
        private LoggerInterface $logger,
        private Poster $heropostPoster,
        private VideoUpdater $videoUpdater,
        private ThumbnailUploader $thumbnailUploader,
    )
    {
    }

    public function postUsingAccessToken(
        string $heropostLogin,
        string $herpostPassword,
        string $youtubeChannelId,
        Video $video,
        string $accessToken
    ): ?string
    {

        $youtubeVideo = $video->youtubeVideo;

        try {
            $youtubeVideoId = $this->heropostPoster->post(
                $heropostLogin,
                $herpostPassword,
                $youtubeChannelId,
                $youtubeVideo,
                $video->videoFilePath
            );
        } catch (
            HeropostConfigurationException | UnknownHeropostException | ScrapingException $e
        ) {
            $this->logger->emergency($e->getMessage(), $e->getTrace());

            return null;
        } catch (MaybeAlreadyPostedButScrapingException $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());

            return null;
        }

        try {
            $this->videoUpdater->update(
                $accessToken,
                $youtubeVideoId,
                $youtubeVideo->title,
                $youtubeVideo->description,
                $video->tags,
                $youtubeVideo->categoryId,
                $video->selfDeclaredMadeForKids
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }
        

        try {
            $this->thumbnailUploader->upload(
                $accessToken,
                $youtubeVideoId,
                $video->thumbnailFilePath
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }

        return $youtubeVideoId;
    }
}
