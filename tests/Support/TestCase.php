<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ProcessWire\Config;
use ProcessWire\Database;
use ProcessWire\Field;
use ProcessWire\Fields;
use ProcessWire\Page;
use ProcessWire\Session;
use ProcessWire\TestServices;
use ProcessWire\User;
use ProcessWire\WireArray;
use ProcessWire\WireInput;

/**
 * Shared reflection helpers and a fresh, wired-up ProcessWire service registry for every test.
 *
 * The module classes under test are instantiated via ReflectionClass::newInstanceWithoutConstructor()
 * throughout this suite - their real constructors pull in far more of ProcessWire (and, via
 * FieldtypeFrontendComments::getFrontendFormsConfigValues(), the FrontendForms module) than these
 * unit tests need. Skipping the constructor and setting only the handful of properties a given
 * method actually reads keeps each test focused on the fix being regression-tested.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestServices::reset();

        TestServices::set('sanitizer', new \ProcessWire\Sanitizer());
        TestServices::set('input', new WireInput());
        TestServices::set('session', new Session());
        TestServices::set('user', new User());
        TestServices::set('config', new Config());
        TestServices::set('database', new Database());
        TestServices::set('fields', new Fields());
        TestServices::set('fieldtypes', new \ProcessWire\Fieldtypes());
        TestServices::set('datetime', new \ProcessWire\WireDateTime());
        // Sensible single-language default: real code guards multi-language branches with
        // count(wire('languages')) > 1, so this must be something count() accepts (never left
        // unset/null) even for tests that never touch multi-language behavior at all.
        TestServices::set('languages', [1]);
        TestServices::set('modules', (new \ProcessWire\Modules())->setConfig('FrontendForms', ['input_framework' => 'none.php']));

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        // Sensible default: real code (e.g. FieldtypeFrontendComments::correctStatusValues()) reads
        // wire('page')->rootParent->get('id') to detect "being edited from within the admin tree"
        // (root parent id 2) - default to a plain, non-admin rootParent (id 1) so any test that
        // exercises such code without deliberately testing the admin-tree branch doesn't fail on a
        // null rootParent.
        $rootParent = new Page();
        $rootParent->id = 1;
        $page->rootParent = $rootParent;
        TestServices::set('page', $page);
        TestServices::set('pages', new class {
            public function get($id) {
                $p = new Page();
                $p->id = is_numeric($id) ? (int)$id : 0;
                return $p;
            }
        });
    }

    /**
     * Build an instance of $class without running its (heavy, ProcessWire-coupled) constructor.
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    protected function newWithoutConstructor(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /** Set a protected/private property via reflection. */
    protected function setProp(object $obj, string $prop, $value): void
    {
        $ref = new \ReflectionProperty($obj, $prop);
        $ref->setAccessible(true);
        $ref->setValue($obj, $value);
    }

    /** Read a protected/private property via reflection. */
    protected function getProp(object $obj, string $prop)
    {
        $ref = new \ReflectionProperty($obj, $prop);
        $ref->setAccessible(true);
        return $ref->getValue($obj);
    }

    /** Invoke a protected/private method via reflection. */
    protected function callMethod(object $obj, string $method, array $args = [])
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($obj, $args);
    }

    /** Capture everything a callable echo()s, returning it as a string. */
    protected function captureOutput(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $result = ob_get_clean();
        }
        return $result;
    }

    protected function newField(int $id, string $name): Field
    {
        $field = new Field();
        $field->id = $id;
        $field->name = $name;
        return $field;
    }
}
