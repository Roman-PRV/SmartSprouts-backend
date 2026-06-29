<?php

namespace Tests\Feature\Documentation;

use App\Enums\GameCategory;
use Illuminate\Support\Collection;
use OpenApi\Generator;
use Tests\TestCase;

class SwaggerCategoryEnumTest extends TestCase
{
    /**
     * The category list lives in three places: the GameCategory enum (source of
     * truth) plus two hand-written `@OA` enum literals — Game.categories items
     * and the `category` query parameter on the games index. swagger-php 4.x in
     * docblock mode cannot dereference the enum class, so those literals are kept
     * by hand; this guard fails loudly the moment they drift from the enum.
     */
    public function test_documented_category_enums_match_the_enum(): void
    {
        $expected = GameCategory::values();
        sort($expected);

        // Scan the whole app dir (as l5-swagger does) so the required @OA\Info is
        // present; a narrower scan trips swagger-php's "missing @OA\Info" warning.
        $spec = json_decode(Generator::scan([app_path()])->toJson(), true);

        $itemsEnum = $spec['components']['schemas']['Game']['properties']['categories']['items']['enum'];
        sort($itemsEnum);

        $this->assertSame($expected, $itemsEnum, 'Game.categories items enum drifted from GameCategory.');

        $parameter = (new Collection($spec['paths']['/api/games']['get']['parameters']))
            ->firstWhere('name', 'category');
        $parameterEnum = $parameter['schema']['enum'];
        sort($parameterEnum);

        $this->assertSame($expected, $parameterEnum, 'category query parameter enum drifted from GameCategory.');
    }
}
