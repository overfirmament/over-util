<?php

namespace Overfirmament\OverUtils\Tests;

use Overfirmament\OverUtils\ToolBox\HelperUtil;

class HelperUtilTest extends TestCase
{
    public function test_is_json_accepts_valid_json_strings(): void
    {
        $this->assertTrue(HelperUtil::isJson('{"a":1}'));
        $this->assertTrue(HelperUtil::isJson('[1,2,3]'));
        $this->assertTrue(HelperUtil::isJson('"text"'));
        $this->assertTrue(HelperUtil::isJson('123'));
        $this->assertTrue(HelperUtil::isJson('null'));
        $this->assertTrue(HelperUtil::isJson('true'));
        $this->assertTrue(HelperUtil::isJson('{}'));
        $this->assertTrue(HelperUtil::isJson('[]'));
    }

    public function test_is_json_rejects_invalid_payloads(): void
    {
        $this->assertFalse(HelperUtil::isJson(''));
        $this->assertFalse(HelperUtil::isJson('   '));
        $this->assertFalse(HelperUtil::isJson('not json'));
        $this->assertFalse(HelperUtil::isJson('{"a":}'));
        $this->assertFalse(HelperUtil::isJson("{'a':1}"));
    }

    public function test_is_json_returns_false_for_non_strings(): void
    {
        $this->assertFalse(HelperUtil::isJson(null));
        $this->assertFalse(HelperUtil::isJson(123));
        $this->assertFalse(HelperUtil::isJson(1.5));
        $this->assertFalse(HelperUtil::isJson(true));
        $this->assertFalse(HelperUtil::isJson(['a' => 1]));
        $this->assertFalse(HelperUtil::isJson(new \stdClass));
    }
}
