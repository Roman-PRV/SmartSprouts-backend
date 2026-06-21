<?php

namespace Tests\Unit\Helpers;

use App\Helpers\MediaHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaHelperTest extends TestCase
{
    public function test_get_url_returns_absolute_url()
    {
        // Mock storage disk
        Storage::fake('public');

        // Ensure APP_URL is set (default in test environment usually is)
        Config::set('app.url', 'http://test-url.com');
        config(['ai.tts.storage.disk' => 'public']);

        $path = 'test-file.mp3';
        $url = MediaHelper::getUrl($path, 'ai.tts.storage.disk', 'public');

        $expectedRoot = rtrim(url('/'), '/');
        $this->assertEquals($expectedRoot.'/storage/test-file.mp3', $url);
    }

    public function test_get_url_returns_null_for_null_path()
    {
        $this->assertNull(MediaHelper::getUrl(null));
    }

    public function test_to_absolute_prefixes_relative_url()
    {
        $root = rtrim(url('/'), '/');

        $this->assertEquals($root.'/storage/file.mp3', MediaHelper::toAbsolute('/storage/file.mp3'));
    }

    public function test_to_absolute_leaves_already_absolute_url_untouched()
    {
        $absolute = 'https://pub-abc.r2.dev/games/find-the-wrong/levels/4/image.jpg';

        $this->assertEquals($absolute, MediaHelper::toAbsolute($absolute));
    }
}
