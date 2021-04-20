<?php

namespace PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPosterTest;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VideoPosterTest extends TestCase
{

    public function __construct(private LoggerInterface $logger)
    {
    }
}
