<?php

declare(strict_types=1);

namespace Tests\Core;

use ProcessWire\Config;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Modules;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/** Dummy themed class for checkForClass() below - matches the "<classname><Framework>" naming
 *  convention the real method looks for (e.g. "PaginationBootstrap5"). */
class DummyThemedWidgetBootstrap5
{
}

/**
 * Unit tests for FieldtypeFrontendComments.module's pure/static helper methods that don't already
 * have dedicated coverage elsewhere (FieldtypeFrontendCommentsDeleteSpamGuardTest.php and
 * FieldtypeFrontendCommentsPermissionGuardTest.php cover the security fixes in deleteSpam() and
 * noEMailWarning()).
 */
final class FieldtypeFrontendCommentsHelpersTest extends TestCase
{
    private function setFramework(string $filename): void
    {
        TestServices::set('modules', (new Modules())->setConfig('FrontendForms', ['input_framework' => $filename]));
    }

    // -----------------------------------------------------------------
    // statusTexts()
    //
    // Locks in the public contract between the numeric status codes stored in the database and
    // their human-readable labels - a typo or reordering here would silently mislabel comments
    // throughout the admin UI.
    // -----------------------------------------------------------------

    public function testStatusTextsMapsEveryKnownStatusCodeToItsLabel(): void
    {
        self::assertSame([
            0 => 'pending approval',
            1 => 'approved',
            2 => 'SPAM',
            4 => 'featured',
        ], FieldtypeFrontendComments::statusTexts());
    }

    // -----------------------------------------------------------------
    // groupBy()
    // -----------------------------------------------------------------

    public function testGroupByBucketsRowsByTheGivenKey(): void
    {
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $rows = [
            ['status' => 1, 'id' => 10],
            ['status' => 2, 'id' => 11],
            ['status' => 1, 'id' => 12],
        ];

        $result = $this->callMethod($module, 'groupBy', [$rows, 'status']);

        self::assertSame([
            1 => [['status' => 1, 'id' => 10], ['status' => 1, 'id' => 12]],
            2 => [['status' => 2, 'id' => 11]],
        ], $result);
    }

    // -----------------------------------------------------------------
    // sanitizeMultilineTextarea()
    // -----------------------------------------------------------------

    public function testSanitizeMultilineTextareaReturnsAnEmptyArrayForNull(): void
    {
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        self::assertSame([], $this->callMethod($module, 'sanitizeMultilineTextarea', [null]));
    }

    public function testSanitizeMultilineTextareaKeepsOnlyTheFirstWhitespaceSeparatedTokenPerLine(): void
    {
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $result = $this->callMethod($module, 'sanitizeMultilineTextarea', ["a@example.com some note\nb@example.com"]);

        self::assertSame(['a@example.com', 'b@example.com'], $result);
    }

    public function testSanitizeMultilineTextareaProducesAnEmptyItemForALineStartingWithASpace(): void
    {
        // documents current, easy-to-miss behavior: trim() is only applied to the [0] element
        // AFTER explode(" ", ...) has already split on the leading space, so a line starting with
        // a space produces an empty string rather than the trimmed email.
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $result = $this->callMethod($module, 'sanitizeMultilineTextarea', [" a@example.com"]);

        self::assertSame([''], $result);
    }

    // -----------------------------------------------------------------
    // findParents()
    //
    // Documented directly on the method itself as currently unable to climb beyond the single id
    // it is given (no $pages/$comments lookup available) - this locks in that current, limited
    // behavior so a future change to it is a deliberate decision, not an accidental regression.
    // -----------------------------------------------------------------

    public function testFindParentsOfTheRootReturnsJustZero(): void
    {
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        self::assertSame([0], $module->findParents(0));
    }

    public function testFindParentsOfAnyOtherIdReturnsOnlyThatIdItself(): void
    {
        $module = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        self::assertSame([5], $module->findParents(5), 'without a data source to look up further ancestors, it cannot climb past the given id');
    }

    // -----------------------------------------------------------------
    // getFrameWork() / checkForClass()
    // -----------------------------------------------------------------

    public function testGetFrameWorkTitleCasesTheConfiguredThemeFilename(): void
    {
        $this->setFramework('bootstrap5.php');
        self::assertSame('Bootstrap5', FieldtypeFrontendComments::getFrameWork());

        $this->setFramework('uikit3.php');
        self::assertSame('Uikit3', FieldtypeFrontendComments::getFrameWork());

        $this->setFramework('none.php');
        self::assertSame('None', FieldtypeFrontendComments::getFrameWork());
    }

    public function testCheckForClassAppendsNoSuffixWhenNoFrameworkIsActive(): void
    {
        $this->setFramework('none.php');

        self::assertSame('SomeWidget', FieldtypeFrontendComments::checkForClass('SomeWidget'));
    }

    public function testCheckForClassFallsBackToTheUnthemedClassWhenTheThemedOneDoesNotExist(): void
    {
        $this->setFramework('bootstrap5.php');

        self::assertSame(
            '\Tests\Core\NoSuchWidget',
            FieldtypeFrontendComments::checkForClass('NoSuchWidget', 'Tests\Core')
        );
    }

    public function testCheckForClassUsesTheThemedClassWhenItActuallyExists(): void
    {
        $this->setFramework('bootstrap5.php');

        self::assertSame(
            '\Tests\Core\DummyThemedWidgetBootstrap5',
            FieldtypeFrontendComments::checkForClass('DummyThemedWidget', 'Tests\Core')
        );
    }

    // -----------------------------------------------------------------
    // resolveTemplateFile()
    // -----------------------------------------------------------------

    public function testResolveTemplateFileFallsBackToTheModuleDefaultWhenNoOverrideExists(): void
    {
        $assetsDir = sys_get_temp_dir() . '/fcm-test-assets-empty-' . uniqid() . '/';
        mkdir($assetsDir, 0777, true);
        /** @var Config $config */
        $config = TestServices::get('config');
        $config->paths->assets = $assetsDir;
        $this->setFramework('bootstrap5.php');

        try {
            self::assertSame(
                '/module/default/comment.php',
                FieldtypeFrontendComments::resolveTemplateFile('comment.php', '/module/default/comment.php')
            );
        } finally {
            rmdir($assetsDir);
        }
    }

    public function testResolveTemplateFileUsesASiteLevelOverrideWhenOneExists(): void
    {
        $assetsDir = sys_get_temp_dir() . '/fcm-test-assets-' . uniqid() . '/';
        $themeDir = $assetsDir . 'FrontendComments/templates/Bootstrap5/';
        mkdir($themeDir, 0777, true);
        $overrideFile = $themeDir . 'comment.php';
        file_put_contents($overrideFile, '<?php // override');

        /** @var Config $config */
        $config = TestServices::get('config');
        $config->paths->assets = $assetsDir;
        $this->setFramework('bootstrap5.php');

        try {
            self::assertSame(
                $overrideFile,
                FieldtypeFrontendComments::resolveTemplateFile('comment.php', '/module/default/comment.php')
            );
        } finally {
            unlink($overrideFile);
            rmdir($themeDir);
            rmdir($assetsDir . 'FrontendComments/templates/');
            rmdir($assetsDir . 'FrontendComments/');
            rmdir($assetsDir);
        }
    }
}
