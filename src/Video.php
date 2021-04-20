<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster;

use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;

class Video
{

    /**
     * @param string[] $tags
     */
    public function __construct(
        public YoutubeVideo $youtubeVideo,
        public array $tags,
        public bool $selfDeclaredMadeForKids,
        public string $videoFilePath,
        public string $thumbnailFilePath
    )
    {
    }
}
