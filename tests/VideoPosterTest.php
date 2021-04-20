<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPosterTest;

use Exception;
use PHPUnit\Framework\TestCase;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPoster;
use PierreMiniggio\HeropostYoutubePosting\Exception\AccountNotSetupOrQuotaExceededException;
use PierreMiniggio\HeropostYoutubePosting\Exception\QuotaExceededException;
use PierreMiniggio\HeropostYoutubePosting\Exception\ScrapingException;
use PierreMiniggio\HeropostYoutubePosting\Exception\UnknownHeropostException;
use PierreMiniggio\HeropostYoutubePosting\Poster;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;
use PierreMiniggio\YoutubeThumbnailUploader\ThumbnailUploader;
use PierreMiniggio\YoutubeVideoUpdater\VideoUpdater;
use Psr\Log\LoggerInterface;

class VideoPosterTest extends TestCase
{

    /**
     * @dataProvider provideVideoUploadExceptions
     */
    public function testBrokenVideoUpload(Exception $exception): void
    {

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('emergency');

        $heropostPoster = $this->createMock(Poster::class);
        $heropostPoster
            ->expects(self::once())
            ->method('post')
            ->willThrowException($exception)
        ;

        $poster = new VideoPoster(
            $logger,
            $heropostPoster,
            $this->createNeverCalledMock(VideoUpdater::class),
            $this->createNeverCalledMock(ThumbnailUploader::class)
        );

        $this->assertPosterReturnsNull($poster);
    }

    protected function assertPosterReturnsNull(VideoPoster $poster): void
    {
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

    protected function createNeverCalledMock(string $originalClassName)
    {
        $mock = $this->createMock($originalClassName);
        $mock->expects(self::never())->method(self::anything());

        return $mock;
    }

    /**
     * @return Exception[][]
     */
    public function provideVideoUploadExceptions(): array
    {
        return [
            [new AccountNotSetupOrQuotaExceededException()],
            [new QuotaExceededException()],
            [new ScrapingException()],
            [new UnknownHeropostException()]
        ];
    }
}
