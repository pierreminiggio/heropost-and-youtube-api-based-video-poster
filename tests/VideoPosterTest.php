<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPosterTest;

use PHPUnit\Framework\TestCase;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPoster;
use PierreMiniggio\HeropostYoutubePosting\Exception\AccountNotSetupOrQuotaExceededException;
use PierreMiniggio\HeropostYoutubePosting\Poster;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;
use PierreMiniggio\YoutubeThumbnailUploader\ThumbnailUploader;
use PierreMiniggio\YoutubeVideoUpdater\VideoUpdater;
use Psr\Log\LoggerInterface;

class VideoPosterTest extends TestCase
{

    public function testBrokenUpload(): void
    {

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('emergency');

        $heropostPoster = $this->createMock(Poster::class);
        $heropostPoster
            ->expects(self::once())
            ->method('post')
            ->willThrowException(new AccountNotSetupOrQuotaExceededException())
        ;

        $videoUpdater = $this->createMock(VideoUpdater::class);
        $videoUpdater->expects(self::never())->method(self::anything());

        $thumbnailUploader = $this->createMock(ThumbnailUploader::class);
        $thumbnailUploader->expects(self::never())->method(self::anything());

        $poster = new VideoPoster(
            $logger,
            $heropostPoster,
            $videoUpdater,
            $thumbnailUploader
        );

        self::assertSame(null, $poster->postUsingAccessToken(
            'login',
            'password',
            'youtubeChannelId',
            new Video(
                new YoutubeVideo(
                    'title',
                    'description',
                    YoutubeCategoriesEnum::EDUCATION
                ),
                ['tag1', 'tag2', 'tag3'],
                false,
                'video.mp4',
                'thumbnail.png'
            ),
            'accessToken'
        ));
    }
}
