Install using composer :
```
composer require pierreminiggio/heropost-and-youtube-api-based-video-poster
```

```php
use PierreMiniggio\HeropostAndYoutubeAPIBasedVideoPoster\VideoPoster;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$uploader = new VideoPoster();
$uploader->update(
    'accessToken',
    'videoId',
    'title',
    'description',
    ['tag1', 'tag2', 'tag3'],
    27,
    false
);
```