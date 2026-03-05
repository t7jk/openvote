<?php

namespace Openvote\Tests\Unit;

use Brain\Monkey;
use Mockery;
use OpenvoteTestConfig;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base class for unit tests.
 *
 * Sets up Brain Monkey (WP-function stubs) and Mockery, and tears them down
 * cleanly after every test.  Also resets OpenvoteTestConfig and $GLOBALS['wpdb'].
 */
abstract class TestCase extends PhpUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Mockery::close();
        Monkey\tearDown();
        OpenvoteTestConfig::reset();
        if (isset($GLOBALS['wpdb'])) {
            unset($GLOBALS['wpdb']);
        }
        parent::tearDown();
    }
}
