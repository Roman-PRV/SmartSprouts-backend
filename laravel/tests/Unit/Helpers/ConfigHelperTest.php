<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ConfigHelper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
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
     * Test getInt method.
     */
    public function test_get_int_returns_value_if_int()
    {
        Config::set('test.int_key', 123);

        $result = ConfigHelper::getInt('test.int_key');

        $this->assertEquals(123, $result);
    }

    public function test_get_int_returns_value_if_numeric_string()
    {
        Config::set('test.numeric_string_key', '456');

        $result = ConfigHelper::getInt('test.numeric_string_key');

        $this->assertEquals(456, $result);
    }

    public function test_get_int_returns_default_if_not_numeric()
    {
        Config::set('test.string_key', 'not numeric');
        Config::set('test.array_key', []);

        $this->assertEquals(789, ConfigHelper::getInt('test.string_key', 789));
        $this->assertEquals(789, ConfigHelper::getInt('test.array_key', 789));
    }

    public function test_get_int_accepts_negative_integer_string()
    {
        Config::set('test.negative_int', '-456');

        $result = ConfigHelper::getInt('test.negative_int');

        $this->assertSame(-456, $result);
    }

    public function test_get_int_rejects_float_string_and_logs_warning()
    {
        Config::set('test.float_string', '12.5');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Invalid integer value in config')
                    && $context['key'] === 'test.float_string'
                    && $context['value'] === '12.5';
            });

        $result = ConfigHelper::getInt('test.float_string', 99);

        $this->assertSame(99, $result);
    }

    public function test_get_int_rejects_scientific_notation_and_logs_warning()
    {
        Config::set('test.scientific', '1e3');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Invalid integer value in config')
                    && $context['key'] === 'test.scientific'
                    && $context['value'] === '1e3';
            });

        $result = ConfigHelper::getInt('test.scientific', 0);

        $this->assertSame(0, $result);
    }

    public function test_get_int_rejects_hex_notation_and_logs_warning()
    {
        Config::set('test.hex', '0xFF');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Invalid integer value in config')
                    && $context['key'] === 'test.hex'
                    && $context['value'] === '0xFF';
            });

        $result = ConfigHelper::getInt('test.hex', 10);

        $this->assertSame(10, $result);
    }

    public function test_get_int_rejects_float_value_and_logs_warning()
    {
        Config::set('test.float_value', 12.5);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Invalid integer value in config')
                    && $context['key'] === 'test.float_value'
                    && $context['value'] === 12.5;
            });

        $result = ConfigHelper::getInt('test.float_value', 0);

        $this->assertSame(0, $result);
    }

    public function test_get_int_returns_default_for_null_without_logging()
    {
        Config::set('test.null_int', null);

        Log::shouldReceive('warning')->never();

        $result = ConfigHelper::getInt('test.null_int', 100);

        $this->assertSame(100, $result);
    }

    /**
     * Test getBool method.
     */
    public function test_get_bool_returns_value_if_bool()
    {
        Config::set('test.bool_true', true);
        Config::set('test.bool_false', false);

        $this->assertTrue(ConfigHelper::getBool('test.bool_true'));
        $this->assertFalse(ConfigHelper::getBool('test.bool_false'));
    }

    public function test_get_bool_handles_string_representations()
    {
        Config::set('test.string_true', 'true');
        Config::set('test.string_false', 'false');
        Config::set('test.string_1', '1');
        Config::set('test.string_0', '0');
        Config::set('test.string_yes', 'yes');
        Config::set('test.string_no', 'no');
        Config::set('test.string_on', 'on');
        Config::set('test.string_off', 'off');

        $this->assertTrue(ConfigHelper::getBool('test.string_true'));
        $this->assertFalse(ConfigHelper::getBool('test.string_false'));
        $this->assertTrue(ConfigHelper::getBool('test.string_1'));
        $this->assertFalse(ConfigHelper::getBool('test.string_0'));
        $this->assertTrue(ConfigHelper::getBool('test.string_yes'));
        $this->assertFalse(ConfigHelper::getBool('test.string_no'));
        $this->assertTrue(ConfigHelper::getBool('test.string_on'));
        $this->assertFalse(ConfigHelper::getBool('test.string_off'));
    }

    public function test_get_bool_returns_default_if_not_boolean_representation()
    {
        Config::set('test.not_bool', 'maybe');
        Config::set('test.array', []);

        $this->assertTrue(ConfigHelper::getBool('test.not_bool', true));
        $this->assertFalse(ConfigHelper::getBool('test.array', false));
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

        $result = ConfigHelper::getStringMap('test.invalid_key_map', ['default_key' => 'default_value']);

        $this->assertSame(['default_key' => 'default_value'], $result);
    }

    public function test_get_string_map_returns_default_if_value_not_string()
    {
        $invalidMap = ['key' => 123]; // Integer value
        Config::set('test.invalid_value_map', $invalidMap);

        $result = ConfigHelper::getStringMap('test.invalid_value_map', ['default_key' => 'default_value']);

        $this->assertSame(['default_key' => 'default_value'], $result);
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
        $associativeArray = ['key1' => 'value1', 'key2' => 'value2'];
        Config::set('test.assoc_list_key', $associativeArray);

        $result = ConfigHelper::getStringList('test.assoc_list_key');

        $this->assertSame(['value1', 'value2'], $result);
    }
}
