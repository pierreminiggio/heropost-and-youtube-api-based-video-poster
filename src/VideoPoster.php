<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster;

use Exception;
use Illuminate\Support\Str;
use PierreMiniggio\GoogleTokenRefresher\AccessTokenProvider;
use PierreMiniggio\GoogleTokenRefresher\GoogleClient;
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
        private AccessTokenProvider $tokenProvider,
        private VideoUpdater $videoUpdater,
        private ThumbnailUploader $thumbnailUploader,
    )
    {
    }

    public function post(
        string $heropostLogin,
        string $herpostPassword,
        string $youtubeChannelId,
        Video $video,
        GoogleClient $client
    ): ?string
    {

        $youtubeVideo = $video->youtubeVideo;

        try {
            $videoWithoutDescription = clone $youtubeVideo;
            $videoWithoutDescription->description = 'Une epique description';
            $videoWithoutDescription->title = Str::slug($videoWithoutDescription->title, ' ');
            $youtubeVideoId = $this->heropostPoster->post(
                $heropostLogin,
                $herpostPassword,
                $youtubeChannelId,
                $videoWithoutDescription,
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
            $accessToken = $this->tokenProvider->getFromClient($client);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());

            return $youtubeVideoId;
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

        if ($video->thumbnailFilePath === null) {
            return $youtubeVideoId;
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
