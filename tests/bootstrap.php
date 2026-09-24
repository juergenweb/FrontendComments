<?php

/**
 * PHPUnit bootstrap for the FrontendComments unit test suite.
 *
 * Loads, in order:
 *   1) Lightweight stand-ins for the ProcessWire core and FrontendForms classes this module
 *      depends on (see tests/Support/*.php for what these do and do not claim to replicate).
 *   2) The REAL, unmodified module source files, so tests exercise the actual production code
 *      through reflection - never a reimplementation of it.
 *
 * This does not require a ProcessWire installation, a database, or Composer - just PHP and
 * PHPUnit. Run with:
 *   phpunit --bootstrap tests/bootstrap.php tests
 * or via the included phpunit.xml:
 *   phpunit
 */

declare(strict_types=1);

$root = dirname(__DIR__);

// FrontendCommentArray's real constructor reads this directly (for $this->userdata['user_agent']);
// PHP's CLI SAPI never sets it, so without a default any test that runs that real constructor
// would hit an undefined-array-key warning.
$_SERVER['HTTP_USER_AGENT'] ??= 'PHPUnit';

require $root . '/tests/Support/ProcessWireStubs.php';
require $root . '/tests/Support/FrontendFormsStubs.php';

// Real module source, in dependency order (parents/base classes before subclasses).
require $root . '/FrontendCommentForm.php';
require $root . '/FrontendCommentArray.php';
require $root . '/FrontendComment.php';
require $root . '/FrontendComments.php';
require $root . '/FrontendCommentPagination.php';
require $root . '/tests/Support/FakeFrameworkPagination.php';
require $root . '/Notifications.php';
require $root . '/FieldtypeFrontendComments.module';
require $root . '/InputfieldFrontendComments.module';
require $root . '/FrontendCommentsManager.module';

require $root . '/tests/Support/TestCase.php';
