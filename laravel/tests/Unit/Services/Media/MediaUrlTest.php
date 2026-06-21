<?php

namespace Tests\Unit\Services\Media;

use App\Services\Media\MediaUrl;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_to_absolute_prefixes_relative_url()
    {
        $root = rtrim(url('/'), '/');

        $this->assertEquals($root.'/storage/file.mp3', MediaUrl::toAbsolute('/storage/file.mp3'));
    }

    public function test_to_absolute_leaves_already_absolute_url_untouched()
    {
        $absolute = 'https://pub-abc.r2.dev/games/find-the-wrong/levels/4/image.jpg';

        $this->assertEquals($absolute, MediaUrl::toAbsolute($absolute));
    }

    public function test_normalize_path_trims_leading_slash()
    {
        $this->assertSame('games/foo.png', MediaUrl::normalizePath('/games/foo.png'));
    }

    public function test_normalize_path_returns_empty_for_non_string()
    {
        $this->assertSame('', MediaUrl::normalizePath(null));
    }
}
