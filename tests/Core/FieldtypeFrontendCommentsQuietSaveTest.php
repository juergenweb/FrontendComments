<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\HookEvent;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression tests for FieldtypeFrontendComments::quietPageEditSaveForCommentsOnly() - a hook on
 * Pages::save() that closes a real gap in "quiet_save" (input_fc_quiet_save): editing a comment
 * directly on the standard Page Edit screen (InputfieldFrontendComments, used whenever the
 * "Comments Manager" option is disabled) saves the page through ProcessWire's normal Pages::save(),
 * which - per ProcessWire core - unconditionally bumps the page's own "modified"/"modified_users_id"
 * columns unless $options['quiet'] is explicitly true. Without this hook, quiet_save had no effect
 * at all on that particular backend path, even though it already worked correctly on the frontend
 * (FrontendCommentForm::render() checks it directly) - a user-reported gap found while auditing
 * quiet_save end to end.
 *
 * This hook forces $options['quiet'] = true only when comment activity happened AND every affected
 * comments field has quiet_save enabled AND nothing else on the page changed - editing the page's
 * title (or any other field) alongside a comment must still bump "modified" as usual.
 */
final class FieldtypeFrontendCommentsQuietSaveTest extends TestCase
{
    private function newModule(): FieldtypeFrontendComments
    {
        return $this->newWithoutConstructor(FieldtypeFrontendComments::class);
    }

    /** A FrontendComments field, registered in the global Fields registry, with the given quiet_save setting. */
    private function newCommentsField(FieldtypeFrontendComments $module, string $name, bool $quietSave): Field
    {
        $field = $this->newField(1, $name);
        $field->type = $module; // a real comments field: its type IS the Fieldtype instance
        $field->set('input_fc_quiet_save', $quietSave ? 1 : 0);
        TestServices::get('fields')->add($field);
        return $field;
    }

    private function callHook(FieldtypeFrontendComments $module, Page $page, array $options = []): array
    {
        $event = new HookEvent($module, [$page, $options]);
        $this->callMethod($module, 'quietPageEditSaveForCommentsOnly', [$event]);
        return $event->arguments(1);
    }

    public function testQuietIsNotForcedWhenNoCommentsFieldChangedAtAll(): void
    {
        $module = $this->newModule();
        $field = $this->newCommentsField($module, 'comments', true);

        $page = new Page();
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $page->comments = $comments; // present, but untouched - no trackChange() call at all

        $options = $this->callHook($module, $page);

        self::assertArrayNotHasKey('quiet', $options, 'a save with no comment activity at all must not be forced quiet');
    }

    public function testQuietIsForcedWhenOnlyAQuietSaveCommentsFieldChanged(): void
    {
        $module = $this->newModule();
        $field = $this->newCommentsField($module, 'comments', true);

        $page = new Page();
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->trackChange('statuschange'); // mirrors InputfieldFrontendComments::___processInput()
        $page->comments = $comments;
        $page->trackChange('comments'); // mirrors what ProcessWire itself tracks for a changed field

        $options = $this->callHook($module, $page);

        self::assertTrue($options['quiet'] ?? false, 'pure comment activity on a quiet_save field must force a quiet save');
    }

    public function testQuietIsNotForcedWhenTheChangedFieldDoesNotHaveQuietSaveEnabled(): void
    {
        $module = $this->newModule();
        $this->newCommentsField($module, 'comments', false);

        $page = new Page();
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->trackChange('statuschange');
        $page->comments = $comments;
        $page->trackChange('comments');

        $options = $this->callHook($module, $page);

        self::assertArrayNotHasKey('quiet', $options, 'a field that has quiet_save disabled must keep the normal "modified" behavior');
    }

    public function testQuietIsNotForcedWhenAnotherFieldAlsoChanged(): void
    {
        $module = $this->newModule();
        $this->newCommentsField($module, 'comments', true);

        // an unrelated field (e.g. "title") also changed in the same save
        $titleField = $this->newField(2, 'title');
        $titleField->type = new class {
        };
        TestServices::get('fields')->add($titleField);

        $page = new Page();
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->trackChange('statuschange');
        $page->comments = $comments;
        $page->trackChange('comments');
        $page->trackChange('title'); // the admin also edited the page title in this same save

        $options = $this->callHook($module, $page);

        self::assertArrayNotHasKey('quiet', $options, 'editing another field alongside a comment must still bump "modified" as usual');
    }

    public function testQuietIsLeftAloneWhenAlreadyQuiet(): void
    {
        $module = $this->newModule();
        $this->newCommentsField($module, 'comments', false); // even a non-quiet field must not un-quiet an already-quiet save

        $page = new Page();

        $options = $this->callHook($module, $page, ['quiet' => true]);

        self::assertTrue($options['quiet']);
    }
}
