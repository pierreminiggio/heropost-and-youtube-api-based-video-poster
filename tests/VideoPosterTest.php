<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPosterTest;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PierreMiniggio\GoogleTokenRefresher\AccessTokenProvider;
use PierreMiniggio\GoogleTokenRefresher\AuthException;
use PierreMiniggio\GoogleTokenRefresher\GoogleClient;
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

                $poster = new VideoPoster(
                    $logger,
                    $this->createPosterMockReturnsVideoId($videoId),
                    $this->createTokenProviderMockReturnsToken('accessToken'),
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

    /**
     * @dataProvider provideThumbnailUploaderExceptions
     */
    public function testYoutubeVideoUploadSucceededAndVideoUpdateSucceededAndThumbnailUploadFailed(
        Exception $thumbnailUploaderException
    ): void
    {

        $videoId = 'yIucwdfnZIM';

        $poster = new VideoPoster(
            $this->createLoggerMockCalledOnceErrorAndNeverEmergency(),
            $this->createPosterMockReturnsVideoId($videoId),
            $this->createTokenProviderMockReturnsToken('accessToken'),
            $this->createMockMethodCalledOnce(VideoUpdater::class, 'update'),
            $this->createMockThrowsException(
                ThumbnailUploader::class,
                'upload',
                $thumbnailUploaderException
            )
        );

        $this->assertPosterReturnsVideoId($videoId, $poster);
    }

    public function testYoutubeVideoUploadSucceededAndVideoUpdateSucceededAndNoThumbnailUpload(): void
    {

        $videoId = 'yIucwdfnZIM';

        $poster = new VideoPoster(
            $this->createNeverCalledMock(LoggerInterface::class),
            $this->createPosterMockReturnsVideoId($videoId),
            $this->createTokenProviderMockReturnsToken('accessToken'),
            $this->createMockMethodCalledOnce(VideoUpdater::class, 'update'),
            $this->createNeverCalledMock(ThumbnailUploader::class, 'upload')
        );

        $this->assertPosterReturns($videoId, $poster, null);
    }

    /**
     * @dataProvider provideThumbnailUploaderExceptions
     */
    public function testYoutubeVideoUploadSucceededAndVideoUpdateFailedAndThumbnailUploadSucceeded(
        Exception $videoUpdaterExceptions
    ): void
    {

        $videoId = 'yIucwdfnZIM';

        $poster = new VideoPoster(
            $this->createLoggerMockCalledOnceErrorAndNeverEmergency(),
            $this->createPosterMockReturnsVideoId($videoId),
            $this->createTokenProviderMockReturnsToken('accessToken'),
            $this->createMockThrowsException(
                VideoUpdater::class,
                'update',
                $videoUpdaterExceptions
            ),
            $this->createMockMethodCalledOnce(ThumbnailUploader::class, 'upload'),
        );

        $this->assertPosterReturnsVideoId($videoId, $poster);
    }

    /**
     * @dataProvider provideTokenProviderExceptions
     */
    public function testNothingCalledAndIdReturnedIfProviderFailed(
        Exception $tokenProviderException
    ): void
    {
        $videoId = 'yIucwdfnZIM';

        $poster = new VideoPoster(
            $this->createMockMethodCalledOnce(LoggerInterface::class, 'error'),
            $this->createPosterMockReturnsVideoId($videoId),
            $this->createMockThrowsException(AccessTokenProvider::class, 'getFromClient', $tokenProviderException),
            $this->createNeverCalledMock(VideoUpdater::class),
            $this->createNeverCalledMock(ThumbnailUploader::class)
        );

        $this->assertPosterReturnsVideoId($videoId, $poster);
    }

    protected function assertYoutubeVideoUploadExpectionCallsLogger(Exception $exception, string $loggerMethod): void
    {

        $poster = new VideoPoster(
            $this->createMockMethodCalledOnce(LoggerInterface::class, $loggerMethod),
            $this->createMockThrowsException(Poster::class, 'post', $exception),
            $this->createNeverCalledMock(AccessTokenProvider::class),
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

    protected function assertPosterReturns(
        mixed $expected,
        VideoPoster $poster,
        ?string $thumbnail = 'thumbnail.png'
    ): void
    {
        self::assertSame($expected, $poster->post(
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
                $thumbnail
            ),
            new GoogleClient(
                'clientId',
                'clientSecret',
                'refreshToken'
            )
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

    protected function createMockMethodCalledOnce(
        string $originalClassName,
        string $methodName
    ): MockObject
    {
        $mock = $this->createMock($originalClassName);
        $mock->expects(self::once())->method($methodName);

        return $mock;
    }

    protected function createPosterMockReturnsVideoId(
        string $videoId
    ): Poster
    {
        return $this->createMockMethodCalledOnceWilLReturn(
            Poster::class,
            'post',
            $videoId
        );
    }

    protected function createTokenProviderMockReturnsToken(
        string $accessToken
    ): AccessTokenProvider
    {
        return $this->createMockMethodCalledOnceWilLReturn(
            AccessTokenProvider::class,
            'getFromClient',
            $accessToken
        );
    }

    protected function createMockMethodCalledOnceWilLReturn(
        string $originalClassName,
        string $methodName,
        mixed $value
    ): MockObject
    {
        $mock = $this->createMock($originalClassName);
        $mock
            ->expects(self::once())
            ->method($methodName)
            ->willReturn($value)
        ;

        return $mock;
    }

    protected function createLoggerMockCalledOnceErrorAndNeverEmergency(): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('emergency');
        $logger->expects(self::once())->method('error');

        return $logger;
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

    /**
     * @return Exception[][]
     */
    public function provideTokenProviderExceptions(): array
    {
        return [
            [new AuthException()],
            [new RuntimeException()]
        ];
    }
}
