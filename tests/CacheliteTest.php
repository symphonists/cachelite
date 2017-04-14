<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../extension.driver.php';

/**
 * @covers Extension_cachelite
 */
final class CacheliteTest extends TestCase
{
    public function testRules()
    {
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms', '*ms'),
            '*ms excludes cms'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms', 'cm*'),
            'cm* excludes cms'
        );
        $this->assertFalse(
            Extension_cachelite::doesRuleExcludesPath('cms', 'dms'),
            'dms does not excludes cms'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms', '*m*'),
            '*m* excludes cms'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms/test', '*m*'),
            '*m* excludes cms/test'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms/test', 'cm*'),
            'cm* excludes cms/test'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms/test', '*test'),
            '*test excludes cms/test'
        );
        $this->assertFalse(
            Extension_cachelite::doesRuleExcludesPath('cms/test', '*test*'),
            '*test* does not excludes cms/test'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('atestb', '*test*'),
            '*test* does atestb'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('cms/test', 'cms/test'),
            'cms/test excludes cms/test'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('', '*'),
            '* excludes ""'
        );
        $this->assertTrue(
            Extension_cachelite::doesRuleExcludesPath('sasasa', '*'),
            '* excludes sasasa'
        );
        $this->assertFalse(
            Extension_cachelite::doesRuleExcludesPath('', 'sasa'),
            'sasa does not excludes ""'
        );
    }
}
