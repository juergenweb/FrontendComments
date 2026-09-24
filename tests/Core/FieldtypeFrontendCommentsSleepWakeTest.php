<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Unit tests for FieldtypeFrontendComments.module's value-marshalling helpers: sanitizeValue(),
 * getBlankValue(), ___sleepValue() (prepare comment data for storage) and the small getConfigValue()
 * / queueTableHasClaimedColumn() helpers. ___wakeupValue() (loading comments back FROM the database)
 * is deliberately not covered here - it constructs real FrontendComment objects via that class's
 * full constructor (avatar/vote/website-link building etc.), which every other test in this suite
 * has consistently sidestepped via newWithoutConstructor() because faithfully stubbing everything
 * that constructor touches is out of proportion to the risk in what is mostly object wiring.
 */
final class FieldtypeFrontendCommentsSleepWakeTest extends TestCase
{
    private function newModule(): FieldtypeFrontendComments
    {
        return $this->newWithoutConstructor(FieldtypeFrontendComments::class);
    }

    // -----------------------------------------------------------------
    // sanitizeValue()
    // -----------------------------------------------------------------

    public function testSanitizeValuePassesThroughAnExistingFrontendCommentArrayUnchanged(): void
    {
        $module = $this->newModule();
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);

        self::assertSame($array, $module->sanitizeValue(new Page(), $this->newField(1, 'comments'), $array));
    }

    public function testSanitizeValueReplacesAnythingElseWithABlankArray(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');

        $result = $module->sanitizeValue($page, $field, 'not a comment array at all');

        self::assertInstanceOf(FrontendCommentArray::class, $result);
        self::assertSame(0, $result->count());
    }

    // -----------------------------------------------------------------
    // getBlankValue()
    // -----------------------------------------------------------------

    public function testGetBlankValueReturnsAnEmptyArrayWiredToTheGivenPageAndField(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 42;
        $field = $this->newField(7, 'comments');

        $result = $module->getBlankValue($page, $field);

        self::assertSame(0, $result->count());
        self::assertSame($page, $result->getPage());
        self::assertSame($field, $result->getField());
    }

    // -----------------------------------------------------------------
    // ___sleepValue()
    // -----------------------------------------------------------------

    private function newComment(array $values): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $defaults = [
            'id' => 1, 'status' => FieldtypeFrontendComments::approved, 'text' => 'Hello',
            'author' => 'Alice', 'email' => 'a@example.com', 'website' => '',
            'user_id' => 0, 'parent_id' => 0, 'ip' => '127.0.0.1', 'user_agent' => 'UA',
            'code' => '', 'remote_flag' => 0, 'notification' => 0, 'notification_confirmed' => 0,
            'created' => 1000, 'stars' => 0, 'upvotes' => 0, 'downvotes' => 0, 'spam_update' => 0,
            'moderation_feedback' => '',
        ];
        foreach (array_merge($defaults, $values) as $key => $value) {
            $comment->set($key, $value);
        }
        return $comment;
    }

    public function testSleepValueReturnsAnEmptyArrayForAnythingThatIsNotAWireArray(): void
    {
        $module = $this->newModule();

        self::assertSame([], $module->___sleepValue(new Page(), $this->newField(1, 'comments'), 'not an array'));
    }

    public function testSleepValueConvertsEachCommentToAPlainAssociativeArray(): void
    {
        $module = $this->newModule();
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $array->add($this->newComment(['id' => 5, 'author' => 'Bob']));

        $result = $module->___sleepValue(new Page(), $this->newField(1, 'comments'), $array);

        self::assertCount(1, $result);
        self::assertSame(5, $result[0]['id']);
        self::assertSame('Bob', $result[0]['author']);
        self::assertArrayHasKey('data', $result[0], 'the comment text is stored under the "data" key, not "text"');
        self::assertSame('Hello', $result[0]['data']);
    }

    public function testSleepValuePersistsTheNotificationConfirmedColumn(): void
    {
        // Regression test for a real bug found in this session: this column (added for the double
        // opt-in feature) was missing from ___sleepValue()'s output array entirely. Without it,
        // FrontendCommentArray::confirmNotificationRemote() setting notification_confirmed=1 in
        // memory had no effect on the database at all - savePageField() rebuilds every comment's row
        // from exactly this array on every save, so any property not listed here is silently
        // dropped and the confirmation is lost again on the very next save.
        $module = $this->newModule();
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $array->add($this->newComment(['notification_confirmed' => 1]));

        $result = $module->___sleepValue(new Page(), $this->newField(1, 'comments'), $array);

        self::assertArrayHasKey('notification_confirmed', $result[0]);
        self::assertSame(1, $result[0]['notification_confirmed']);
    }

    public function testSleepValueKeepsSafeHtmlInTheModerationFeedbackButStripsScripts(): void
    {
        // Regression test for the fix documented directly at this call site: moderation_feedback
        // is edited via a rich-text (CKEditor) field offering bold/italic/lists/links - purify()
        // must keep that safe subset, unlike a plain strip-everything sanitizer, while still never
        // letting a <script> tag through.
        $module = $this->newModule();
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $array->add($this->newComment([
            'moderation_feedback' => '<p>Please <b>stay on topic</b>.</p><script>alert(1)</script>',
        ]));

        $result = $module->___sleepValue(new Page(), $this->newField(1, 'comments'), $array);

        self::assertStringContainsString('<b>stay on topic</b>', $result[0]['moderation_feedback'], 'safe formatting tags from the rich-text editor must survive');
        self::assertStringNotContainsString('<script>', $result[0]['moderation_feedback'], 'a script tag must never survive, safe subset or not');
    }

    // -----------------------------------------------------------------
    // getConfigValue()
    // -----------------------------------------------------------------

    public function testGetConfigValueReturnsTheFieldsOwnValueWhenSet(): void
    {
        $module = $this->newModule();
        $field = $this->newField(1, 'comments')->setArray(['input_fc_moderate' => 2]);
        $inputfield = (object)['name' => 'input_fc_moderate'];

        self::assertSame(2, $this->callMethod($module, 'getConfigValue', [$field, $inputfield]));
    }

    public function testGetConfigValueFallsBackToTheModuleDefaultWhenNotSetOnTheField(): void
    {
        $module = $this->newModule();
        $field = $this->newField(1, 'comments'); // input_fc_moderate never set
        $inputfield = (object)['name' => 'input_fc_moderate'];

        self::assertSame(
            FieldtypeFrontendComments::getDefaultData()['input_fc_moderate'],
            $this->callMethod($module, 'getConfigValue', [$field, $inputfield])
        );
    }

    // -----------------------------------------------------------------
    // queueTableHasClaimedColumn()
    // -----------------------------------------------------------------

    public function testQueueTableHasClaimedColumnReflectsWhetherTheColumnExists(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(1); // column exists -> 1 row
        self::assertTrue($this->callMethod($module, 'queueTableHasClaimedColumn'));
    }

    public function testQueueTableHasClaimedColumnIsFalseWhenTheColumnIsMissing(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(0); // no matching column -> 0 rows
        self::assertFalse($this->callMethod($module, 'queueTableHasClaimedColumn'));
    }

    // -----------------------------------------------------------------
    // fieldTableHasNotificationConfirmedColumn()
    // -----------------------------------------------------------------

    public function testFieldTableHasNotificationConfirmedColumnReflectsWhetherTheColumnExists(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(1); // column exists -> 1 row
        self::assertTrue($this->callMethod($module, 'fieldTableHasNotificationConfirmedColumn', ['field_comments']));
    }

    public function testFieldTableHasNotificationConfirmedColumnIsFalseWhenTheColumnIsMissing(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(0); // no matching column -> 0 rows
        self::assertFalse($this->callMethod($module, 'fieldTableHasNotificationConfirmedColumn', ['field_comments']));
    }

    // -----------------------------------------------------------------
    // ___upgrade(): 1.0.8 migration of the "notification_confirmed" column onto every EXISTING
    // comment field's own table (a brand new field gets the column straight from
    // getDatabaseSchema(), so this migration only matters for fields whose table predates it - see
    // the comment directly on ___upgrade() for the full "why" of this whole block)
    // -----------------------------------------------------------------

    public function testUpgradeAddsTheNotificationConfirmedColumnToEveryExistingCommentField(): void
    {
        // Regression test for the real-world bug this migration fixes: a site that updated the
        // module code without this migration got "Unknown column 'field_comments.
        // notification_confirmed'" as soon as anything (e.g. deleting a comment, which re-saves the
        // page field and can retrigger addCommentToQueueTable() for other still-published comments)
        // queried the new column against a table that was never actually altered.
        $module = $this->newModule();
        $db = \ProcessWire\TestServices::get('database');
        $fields = \ProcessWire\TestServices::get('fields');

        $commentsField = $this->newField(1, 'comments');
        $commentsField->table = 'field_comments';
        $commentsField->type = $module; // a real comment field: its type IS this Fieldtype instance
        $fields->add($commentsField);

        // an unrelated field of a different type must be skipped entirely - it has no such table
        $textField = $this->newField(2, 'title');
        $textField->table = 'field_title';
        $textField->type = new class {
        };
        $fields->add($textField);

        $db->tableExistsOverrides['field_comments'] = true;
        $db->queueResult(1); // "SHOW COLUMNS ... claimed" for the (unrelated) queue-table check -> already present, skip
        $db->queueResult(0); // "SHOW COLUMNS FROM field_comments LIKE 'notification_confirmed'" -> missing
        // out of scope for this test (which is only about the 1.0.8 migration): the 1.0.9
        // "reminder_sent" migration runs unconditionally too (see ___upgrade()'s own inline comment
        // - it isn't actually gated by $fromVersion/$toVersion), so it needs its own queued "already
        // present" result here to keep this test focused on notification_confirmed alone.
        $db->queueResult(1); // "SHOW COLUMNS FROM field_comments LIKE 'reminder_sent'" -> already present, skip

        $this->callMethod($module, '___upgrade', ['1.0.7', '1.0.8']);

        self::assertCount(1, $db->executed, 'exactly one ALTER TABLE must have been run, for the comment field only');
        self::assertStringContainsString('ALTER TABLE', $db->executed[0]);
        self::assertStringContainsString('field_comments', $db->executed[0]);
        self::assertStringContainsString('notification_confirmed', $db->executed[0]);
    }

    public function testUpgradeDoesNotAlterAFieldsTableThatAlreadyHasTheColumn(): void
    {
        $module = $this->newModule();
        $db = \ProcessWire\TestServices::get('database');
        $fields = \ProcessWire\TestServices::get('fields');

        $commentsField = $this->newField(1, 'comments');
        $commentsField->table = 'field_comments';
        $commentsField->type = $module;
        $fields->add($commentsField);

        $db->tableExistsOverrides['field_comments'] = true;
        $db->queueResult(1); // queue-table "claimed" column check -> already present, skip
        $db->queueResult(1); // notification_confirmed already present -> 1 row
        // see the matching comment in testUpgradeAddsTheNotificationConfirmedColumnToEveryExistingCommentField()
        $db->queueResult(1); // reminder_sent already present -> 1 row (out of scope for this test)

        $this->callMethod($module, '___upgrade', ['1.0.7', '1.0.8']);

        self::assertCount(0, $db->executed, 'a field that already has the column must not be altered again');
    }

    public function testUpgradeSkipsAFieldWhoseTableDoesNotExistYet(): void
    {
        // Defensive guard against a stale/orphaned Field record pointing at a table that was never
        // created (or already dropped) - must never attempt to ALTER a table that isn't there.
        $module = $this->newModule();
        $db = \ProcessWire\TestServices::get('database');
        $fields = \ProcessWire\TestServices::get('fields');

        $commentsField = $this->newField(1, 'comments');
        $commentsField->table = 'field_comments';
        $commentsField->type = $module;
        $fields->add($commentsField);

        $db->tableExistsOverrides['field_comments'] = false;
        $db->queueResult(1); // queue-table "claimed" column check -> already present, skip

        $this->callMethod($module, '___upgrade', ['1.0.7', '1.0.8']);

        self::assertCount(0, $db->executed);
    }

    // -----------------------------------------------------------------
    // fieldTableHasReminderSentColumn()
    // -----------------------------------------------------------------

    public function testFieldTableHasReminderSentColumnReflectsWhetherTheColumnExists(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(1); // column exists -> 1 row
        self::assertTrue($this->callMethod($module, 'fieldTableHasReminderSentColumn', ['field_comments']));
    }

    public function testFieldTableHasReminderSentColumnIsFalseWhenTheColumnIsMissing(): void
    {
        $module = $this->newModule();

        \ProcessWire\TestServices::get('database')->queueResult(0); // no matching column -> 0 rows
        self::assertFalse($this->callMethod($module, 'fieldTableHasReminderSentColumn', ['field_comments']));
    }

    // -----------------------------------------------------------------
    // ___upgrade(): 1.0.9 migration of the "reminder_sent" column onto every EXISTING comment
    // field's own table - same reasoning and mechanism as the 1.0.8 "notification_confirmed"
    // migration tested above (see FieldtypeFrontendCommentsPendingReminderTest.php for the feature
    // this column supports).
    // -----------------------------------------------------------------

    public function testUpgradeAddsTheReminderSentColumnToEveryExistingCommentField(): void
    {
        $module = $this->newModule();
        $db = \ProcessWire\TestServices::get('database');
        $fields = \ProcessWire\TestServices::get('fields');

        $commentsField = $this->newField(1, 'comments');
        $commentsField->table = 'field_comments';
        $commentsField->type = $module;
        $fields->add($commentsField);

        $db->tableExistsOverrides['field_comments'] = true;
        $db->queueResult(1); // "claimed" column check (unrelated) -> already present, skip
        $db->queueResult(1); // notification_confirmed already present -> skip that migration
        $db->queueResult(0); // "SHOW COLUMNS FROM field_comments LIKE 'reminder_sent'" -> missing

        $this->callMethod($module, '___upgrade', ['1.0.8', '1.0.9']);

        self::assertCount(1, $db->executed, 'exactly one ALTER TABLE must have been run, for the comment field only');
        self::assertStringContainsString('ALTER TABLE', $db->executed[0]);
        self::assertStringContainsString('field_comments', $db->executed[0]);
        self::assertStringContainsString('reminder_sent', $db->executed[0]);
    }

    public function testUpgradeDoesNotAlterAFieldsTableThatAlreadyHasTheReminderSentColumn(): void
    {
        $module = $this->newModule();
        $db = \ProcessWire\TestServices::get('database');
        $fields = \ProcessWire\TestServices::get('fields');

        $commentsField = $this->newField(1, 'comments');
        $commentsField->table = 'field_comments';
        $commentsField->type = $module;
        $fields->add($commentsField);

        $db->tableExistsOverrides['field_comments'] = true;
        $db->queueResult(1); // "claimed" column check (unrelated) -> already present, skip
        $db->queueResult(1); // notification_confirmed already present -> skip that migration
        $db->queueResult(1); // reminder_sent already present -> 1 row

        $this->callMethod($module, '___upgrade', ['1.0.8', '1.0.9']);

        self::assertCount(0, $db->executed, 'a field that already has the column must not be altered again');
    }
}
