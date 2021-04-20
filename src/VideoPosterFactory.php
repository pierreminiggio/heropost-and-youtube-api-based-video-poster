<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster;

use PierreMiniggio\GoogleTokenRefresher\AccessTokenProvider;
use PierreMiniggio\HeropostYoutubePosting\JSExecutor;
use PierreMiniggio\HeropostYoutubePosting\Poster;
use PierreMiniggio\YoutubeThumbnailUploader\ThumbnailUploader;
use PierreMiniggio\YoutubeVideoUpdater\VideoUpdater;
use Psr\Log\LoggerInterface;

class VideoPosterFactory
{
    
    public function make(LoggerInterface $logger): VideoPoster
    {
        return new VideoPoster(
            $logger,
            new Poster(new JSExecutor()),
            new AccessTokenProvider(),
            new VideoUpdater(),
            new ThumbnailUploader()
        );
    }
}
