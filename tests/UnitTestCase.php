<?php

namespace LinkedData\SPARQL\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Fast unit test case that doesn't boot Laravel.
 * Use this for tests that don't need database connections or Laravel features.
 */
abstract class UnitTestCase extends BaseTestCase
{
    // No Laravel overhead!
}
