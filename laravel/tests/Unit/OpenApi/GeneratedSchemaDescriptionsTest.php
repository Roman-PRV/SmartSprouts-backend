<?php

namespace Tests\Unit\OpenApi;

use Illuminate\Support\Facades\File;
use OpenApi\Generator;
use Tests\TestCase;

/**
 * swagger-php applies a docblock's free text to the first annotation inside the
 * block that carries no description of its own. Prose sharing a block with a
 * schema therefore lands in a field description in the generated spec, where
 * it reads as documentation and is invisible to review.
 *
 * This checks a freshly generated spec, not the committed public/docs/api-docs.json:
 * that file is only ever updated by a manual `l5-swagger:generate` run
 * (config/l5-swagger.php: generate_always is false), so checking it would let a
 * leak sit invisible until someone else's unrelated change happens to
 * regenerate the file.
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
            "A docblock leaked into the generated docs. The block attached to an @OA annotation must carry no prose — either move the annotation onto a method whose own docblock has none (toArray(), rules()), or split the class docblock into two separate blocks with the annotation last. A description on the schema itself does not help: every nested property is checked on its own and leaks independently if it has no description of its own.\n",
        );
    }

    /**
     * Scans the same path l5-swagger does (config/l5-swagger.php), matching
     * the technique SwaggerCategoryEnumTest already uses.
     *
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        /** @var array<string, mixed> $document */
        $document = json_decode(Generator::scan([app_path()])->toJson(), true, 512, JSON_THROW_ON_ERROR);

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
                $stripped = $this->stripStars($block);
                $head = preg_split('/(^|\n)\s*@\w+/', $stripped, 2)[0];
                $head = trim($head);

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
