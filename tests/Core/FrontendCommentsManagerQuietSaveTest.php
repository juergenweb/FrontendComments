<?php

declare(strict_types=1);

namespace Tests\Core;

use ProcessWire\Database;
use ProcessWire\FrontendCommentsManager;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Regression tests for FrontendCommentsManager::updatePageModifiedUnlessQuiet() - a fix for a gap
 * found while auditing "quiet_save" (input_fc_quiet_save) end to end: saving or deleting a comment
 * through this Comments Manager went through FrontendCommentArray::saveComment()/deleteComment() ->
 * Fieldtype::savePageField() directly, which never calls ProcessWire's Pages::save() and therefore
 * never touched the page's own "modified"/"modified_users_id" columns AT ALL - regardless of
 * quiet_save. That meant quiet_save = off (the setting that is SUPPOSED to keep "modified" current)
 * had no effect here, unlike the frontend comment form, which already updated it correctly.
 *
 * The fix mirrors FrontendCommentForm::render()'s own "update the pages table" block exactly: run
 * the same UPDATE unless the given field has quiet_save enabled. It's called from every place in
 * this module that saves or deletes a comment (processEditForm() and the bulk ___processInput()),
 * but only on actual success (a forbidden delete, e.g. a comment with replies, must not touch
 * "modified" at all).
 */
final class FrontendCommentsManagerQuietSaveTest extends TestCase
{
    private function newManager(): FrontendCommentsManager
    {
        $manager = $this->newWithoutConstructor(FrontendCommentsManager::class);
        $this->setProp($manager, 'database', new Database());
        return $manager;
    }

    private function database(FrontendCommentsManager $manager): Database
    {
        return $this->getProp($manager, 'database');
    }

    public function testDoesNothingWhenQuietSaveIsEnabled(): void
    {
        $manager = $this->newManager();
        $field = $this->newField(1, 'comments')->setArray(['input_fc_quiet_save' => 1]);
        $page = new Page();
        $page->id = 42;

        $this->callMethod($manager, 'updatePageModifiedUnlessQuiet', [$page, $field]);

        self::assertCount(0, $this->database($manager)->prepared, 'a quiet_save field must never touch "modified"');
    }

    public function testUpdatesModifiedWhenQuietSaveIsDisabled(): void
    {
        $manager = $this->newManager();
        $field = $this->newField(1, 'comments')->setArray(['input_fc_quiet_save' => 0]);
        $page = new Page();
        $page->id = 42;

        $this->callMethod($manager, 'updatePageModifiedUnlessQuiet', [$page, $field]);

        $prepared = $this->database($manager)->prepared;
        self::assertCount(1, $prepared, 'a non-quiet field must update "modified" exactly once');
        self::assertStringContainsString('UPDATE pages', $prepared[0]->sql);
        self::assertStringContainsString('modified', $prepared[0]->sql);
        self::assertSame(42, $prepared[0]->bound[':id']);
    }

    public function testUpdatesModifiedWhenQuietSaveIsNotSetAtAll(): void
    {
        // a field that has never had the checkbox saved at all (e.g. created before quiet_save
        // existed) must behave like "off", not silently suppress the update
        $manager = $this->newManager();
        $field = $this->newField(1, 'comments');
        $page = new Page();
        $page->id = 7;

        $this->callMethod($manager, 'updatePageModifiedUnlessQuiet', [$page, $field]);

        self::assertCount(1, $this->database($manager)->prepared);
    }
}
