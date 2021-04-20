<?php

use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\Video;
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPosterFactory;
use PierreMiniggio\HeropostYoutubePosting\YoutubeCategoriesEnum;
use PierreMiniggio\HeropostYoutubePosting\YoutubeVideo;
use Psr\Log\LoggerInterface;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

class TestLogger implements LoggerInterface
{

    public function emergency($message, array $context = [])
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = [])
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = [])
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = [])
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = [])
    {
        var_dump($level, $message, $context);
    }
}

$poster = (new VideoPosterFactory())->make(new TestLogger());
$poster->post(
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
);
