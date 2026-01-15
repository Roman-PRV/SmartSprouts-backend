<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ConfigHelper;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ConfigHelperTest extends TestCase
{
    /**
     * Test getString method.
     */
    public function test_get_string_returns_value_if_string()
    {
        Config::set('test.string_key', 'test_value');

        $result = ConfigHelper::getString('test.string_key');

        $this->assertEquals('test_value', $result);
    }

    public function test_get_string_returns_default_if_null()
    {
        Config::set('test.null_key', null);

        $result = ConfigHelper::getString('test.null_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_get_string_returns_default_if_not_string()
    {
        Config::set('test.array_key', ['some' => 'array']);
        Config::set('test.int_key', 123);
        Config::set('test.bool_key', true);

        $this->assertEquals('default', ConfigHelper::getString('test.array_key', 'default'));
        $this->assertEquals('default', ConfigHelper::getString('test.int_key', 'default'));
        $this->assertEquals('default', ConfigHelper::getString('test.bool_key', 'default'));
    }

    /**
     * Test getStringMap method.
     */
    public function test_get_string_map_returns_array_if_valid()
    {
        $validMap = ['key1' => 'value1', 'key2' => 'value2'];
        Config::set('test.map_key', $validMap);

        $result = ConfigHelper::getStringMap('test.map_key');

        $this->assertSame($validMap, $result);
    }

    public function test_get_string_map_returns_default_if_not_array()
    {
        Config::set('test.string_key', 'not an array');

        $result = ConfigHelper::getStringMap('test.string_key', ['default' => 'map']);

        $this->assertSame(['default' => 'map'], $result);
    }

    public function test_get_string_map_returns_default_if_key_not_string()
    {
        // PHP arrays automatically cast integer-like string keys to integers,
        // so ['1' => 'a'] becomes [1 => 'a'].
        // We need to test if ConfigHelper enforces strictly string keys if that is the requirement.
        // Looking at ConfigHelper::getStringMap implementation:
        // if (! is_string($k) || ! is_string($v)) { return $default; }
        // So yes, it enforces string keys.

        $invalidMap = [1 => 'value']; // Integer key
        Config::set('test.invalid_key_map', $invalidMap);

        $result = ConfigHelper::getStringMap('test.invalid_key_map', ['default']);

        $this->assertSame(['default'], $result);
    }

    public function test_get_string_map_returns_default_if_value_not_string()
    {
        $invalidMap = ['key' => 123]; // Integer value
        Config::set('test.invalid_value_map', $invalidMap);

        $result = ConfigHelper::getStringMap('test.invalid_value_map', ['default']);

        $this->assertSame(['default'], $result);
    }

    /**
     * Test getStringList method.
     */
    public function test_get_string_list_returns_values_if_valid()
    {
        $validList = ['value1', 'value2', 'value3'];
        Config::set('test.list_key', $validList);

        $result = ConfigHelper::getStringList('test.list_key');

        $this->assertSame($validList, $result);
    }

    public function test_get_string_list_returns_default_if_not_array()
    {
        Config::set('test.string_key', 'not an array');

        $result = ConfigHelper::getStringList('test.string_key', ['default']);

        $this->assertSame(['default'], $result);
    }

    public function test_get_string_list_returns_default_if_value_not_string()
    {
        $invalidList = ['value1', 123, 'value3'];
        Config::set('test.invalid_list_key', $invalidList);

        $result = ConfigHelper::getStringList('test.invalid_list_key', ['default']);

        $this->assertSame(['default'], $result);
    }

    public function test_get_string_list_ignores_keys_and_returns_values()
    {
        // ConfigHelper::getStringList implementation:
        // foreach ($value as $v) { ... } (keys ignored)
        // return array_values($value); (keys reset)

        $associativeArray = ['key1' => 'value1', 'key2' => 'value2'];
        Config::set('test.assoc_list_key', $associativeArray);

        $result = ConfigHelper::getStringList('test.assoc_list_key');

        $this->assertSame(['value1', 'value2'], $result);
    }
}
