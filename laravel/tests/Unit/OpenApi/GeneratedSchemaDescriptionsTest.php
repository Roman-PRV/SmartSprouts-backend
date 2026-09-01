<?php

namespace Tests\Unit\OpenApi;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * swagger-php applies a docblock's free text to the first annotation inside the
 * block that carries no description of its own. Prose sharing a block with a
 * schema therefore lands in a field description in public/docs/api-docs.json,
 * where it reads as documentation and is invisible to review.
 *
 * This checks the generated file rather than the source. A rule about where
 * annotations may sit would flag every documented class that is in fact fine —
 * prose is only a problem once it reaches the published docs.
 */
class GeneratedSchemaDescriptionsTest extends TestCase
{
    /**
     * Only components.schemas: swagger-php feeds operation summaries from
     * docblocks by design, so a match under paths is the intended behaviour.
     */
    public function test_no_generated_schema_description_repeats_a_docblock(): void
    {
        $prose = $this->docblockProse();
        $offenders = [];

        foreach ($this->descriptionsIn($this->schemas(), 'schemas') as $path => $description) {
            $sources = $prose[$this->normalise($description)] ?? [];

            if ($sources !== []) {
                $offenders[] = "{$path} carries the docblock at ".implode(' or ', $sources);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A docblock leaked into the generated docs. Move the prose out of the block holding the @OA annotation — a second, separate docblock above it works, and the annotation must stay the last block.\n",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        $path = base_path('public/docs/api-docs.json');

        $this->assertFileExists($path, 'Run `php artisan l5-swagger:generate` first.');

        /** @var array<string, mixed> $document */
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $schemas */
        $schemas = data_get($document, 'components.schemas', []);

        return $schemas;
    }

    /**
     * Every description in the tree, keyed by where it sits, since a leak lands
     * as easily on a nested property as on the schema itself.
     *
     * @return array<string, string>
     */
    private function descriptionsIn(mixed $node, string $path): array
    {
        if (! is_array($node)) {
            return [];
        }

        $found = [];

        foreach ($node as $key => $child) {
            if ($key === 'description' && is_string($child)) {
                $found[$path] = $child;

                continue;
            }

            $found += $this->descriptionsIn($child, "{$path}.{$key}");
        }

        return $found;
    }

    /**
     * The text before the first tag of every docblock under app/ — that is the
     * part swagger-php hands to an annotation.
     *
     * Boilerplate such as "Transform the resource into an array." repeats across
     * files, so every location is kept: the generated text alone cannot say
     * which copy of it leaked.
     *
     * @return array<string, list<string>> Normalised prose => file:line list.
     */
    private function docblockProse(): array
    {
        $prose = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getRealPath());

            preg_match_all('#/\*\*(.*?)\*/#s', $source, $matches, PREG_OFFSET_CAPTURE);

            /** @var array<int, array{0: string, 1: int}> $blocks */
            $blocks = $matches[1];

            foreach ($blocks as [$block, $offset]) {
                $head = trim(explode('@', $this->stripStars($block), 2)[0]);

                if ($head === '') {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $prose[$this->normalise($head)][] = $file->getRelativePathname().':'.$line;
            }
        }

        return $prose;
    }

    private function stripStars(string $block): string
    {
        $lines = array_map(
            static fn (string $line): string => ltrim(ltrim($line), '*'),
            explode("\n", $block),
        );

        return trim(implode("\n", array_map('trim', $lines)));
    }

    /** Wrapping differs between the source and the generated JSON. */
    private function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
