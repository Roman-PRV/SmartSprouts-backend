<?php

namespace Tests\Unit\Services\Tts;

use App\Services\Tts\TtsPathHash;
use PHPUnit\Framework\TestCase;

class TtsPathHashTest extends TestCase
{
    public function test_for_text_folds_locale_into_the_hash(): void
    {
        $this->assertNotSame(
            TtsPathHash::forText('Hello', 'en'),
            TtsPathHash::forText('Hello', 'uk'),
        );
        $this->assertSame(
            TtsPathHash::forText('Hello', 'en'),
            TtsPathHash::forText('Hello', 'en'),
        );
        $this->assertSame(TtsPathHash::LENGTH, strlen(TtsPathHash::forText('Hello', 'en')));
    }

    public function test_extract_from_path_reads_the_trailing_hash(): void
    {
        $hash = TtsPathHash::forText('Hello', 'en');

        $this->assertSame(
            $hash,
            TtsPathHash::extractFromPath("games/true_false_image/statements/5/en/statement_{$hash}.mp3"),
        );
    }

    public function test_extract_from_path_returns_null_when_absent(): void
    {
        $this->assertNull(TtsPathHash::extractFromPath(null));
        $this->assertNull(TtsPathHash::extractFromPath(''));
        $this->assertNull(TtsPathHash::extractFromPath('games/x/y/image.png'));
    }

    public function test_round_trip_detects_changed_text(): void
    {
        $stored = 'a/b/title_'.TtsPathHash::forText('Old', 'en').'.mp3';

        $this->assertSame(TtsPathHash::forText('Old', 'en'), TtsPathHash::extractFromPath($stored));
        $this->assertNotSame(TtsPathHash::forText('New', 'en'), TtsPathHash::extractFromPath($stored));
    }
}
