<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPosterTest;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPoster;
use PierreMiniggio\HeropostYoutubePosting\Exception\AccountNotSetupOrQuotaExceededException;
use PierreMiniggio\HeropostYoutubePosting\Exception\MaybeAlreadyPostedButScrapingException;
use PierreMiniggio\HeropostYoutubePosting\Exception\QuotaExceededException;
use PierreMiniggio\HeropostYoutubePosting\Exception\ScrapingException;
use PierreMiniggio\HeropostYoutubePosting\Exception\UnknownHeropostException;
use PierreMiniggio\HeropostYoutubePosting\Poster;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;
use PierreMiniggio\YoutubeThumbnailUploader\Exception\BadVideoIdException as ThumbnailUploaderBadVideoIdException;
use PierreMiniggio\YoutubeThumbnailUploader\Exception\ThumbnailFeatureNotAvailableException;
use PierreMiniggio\YoutubeThumbnailUploader\ThumbnailUploader;
use PierreMiniggio\YoutubeVideoUpdater\Exception\BadVideoIdException as VideoUpdaterBadVideoIdException;
use PierreMiniggio\YoutubeVideoUpdater\VideoUpdater;
use Psr\Log\LoggerInterface;
use RuntimeException;

class VideoPosterTest extends TestCase
{

    /**
     * @dataProvider provideBrokenYoutubeVideoUploadExceptions
     */
    public function testBrokenYoutubeVideoUpload(Exception $exception): void
    {
        $this->assertYoutubeVideoUploadExpectionCallsLogger($exception, 'emergency');
    }

    /**
     * @dataProvider provideMaybeBrokenYoutubeVideoUploadExceptions
     */
    public function testMaybeBrokenYoutubeVideoUpload(Exception $exception): void
    {
        $this->assertYoutubeVideoUploadExpectionCallsLogger($exception, 'error');
    }

    public function testYoutubeVideoUploadSucceededAndVideoUpdateFailedAndThumbnailUploadFailed(): void
    {
        foreach ($this->provideVideoUpdaterExceptions() as $videoUpdaterExceptions) {
            $videoUpdaterException = $videoUpdaterExceptions[0];

            foreach ($this->provideThumbnailUploaderExceptions() as $thumbnailUploaderExceptions) {
                $thumbnailUploaderException = $thumbnailUploaderExceptions[0];

                $logger = $this->createMock(LoggerInterface::class);
                $logger->expects(self::never())->method('emergency');
                $logger->expects(self::exactly(2))->method('error');

                $videoId = 'yIucwdfnZIM';
                $heropostPoster = $this->createMock(Poster::class);
                $heropostPoster
                    ->expects(self::once())
                    ->method('post')
                    ->willReturn($videoId)
                ;

                $poster = new VideoPoster(
                    $logger,
                    $heropostPoster,
                    $this->createMockThrowsException(
                        VideoUpdater::class,
                        'update',
                        $videoUpdaterException
                    ),
                    $this->createMockThrowsException(
                        ThumbnailUploader::class,
                        'upload',
                        $thumbnailUploaderException
                    )
                );

                $this->assertPosterReturnsVideoId($videoId, $poster);
            }
        }
    }

    protected function assertYoutubeVideoUploadExpectionCallsLogger(Exception $exception, string $loggerMethod): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method($loggerMethod);

        $poster = new VideoPoster(
            $logger,
            $this->createMockThrowsException(Poster::class, 'post', $exception),
            $this->createNeverCalledMock(VideoUpdater::class),
            $this->createNeverCalledMock(ThumbnailUploader::class)
        );

        $this->assertPosterReturnsNull($poster);
    }

    protected function assertPosterReturnsNull(VideoPoster $poster): void
    {
        $this->assertPosterReturns(null, $poster);
    }

    protected function assertPosterReturnsVideoId(string $videoId, VideoPoster $poster): void
    {
        $this->assertPosterReturns($videoId, $poster);
    }

    protected function assertPosterReturns(mixed $expected, VideoPoster $poster): void
    {
        self::assertSame($expected, $poster->postUsingAccessToken(
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

    protected function createNeverCalledMock(string $originalClassName): MockObject
    {
        $mock = $this->createMock($originalClassName);
        $mock->expects(self::never())->method(self::anything());

        return $mock;
    }

    protected function createMockThrowsException(
        string $originalClassName,
        string $methodName,
        Exception $exception
    ): MockObject
    {
        $mock = $this->createMock($originalClassName);
        $mock->expects(self::once())->method($methodName)->willThrowException($exception);

        return $mock;
    }

    /**
     * @return Exception[][]
     */
    public function provideBrokenYoutubeVideoUploadExceptions(): array
    {
        return [
            [new AccountNotSetupOrQuotaExceededException()],
            [new QuotaExceededException()],
            [new ScrapingException()],
            [new UnknownHeropostException()]
        ];
    }

    /**
     * @return Exception[][]
     */
    public function provideMaybeBrokenYoutubeVideoUploadExceptions(): array
    {
        return [
            [new MaybeAlreadyPostedButScrapingException()]
        ];
    }

    /**
     * @return Exception[][]
     */
    public function provideVideoUpdaterExceptions(): array
    {
        return [
            [new VideoUpdaterBadVideoIdException()],
            [new RuntimeException()]
        ];
    }

    /**
     * @return Exception[][]
     */
    public function provideThumbnailUploaderExceptions(): array
    {
        return [
            [new ThumbnailUploaderBadVideoIdException()],
            [new RuntimeException()],
            [new ThumbnailFeatureNotAvailableException()]
        ];
    }
}
