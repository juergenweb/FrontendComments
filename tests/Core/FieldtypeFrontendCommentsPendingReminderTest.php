<?php

declare(strict_types=1);

namespace Tests\Core;

use ProcessWire\Database;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Modules;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression tests for FieldtypeFrontendComments::sendPendingReminders() (hooked to
 * LazyCron::everyDay).
 *
 * Real feature request: previously, a comment left sitting in "pending approval" status was
 * never followed up on again after the single, immediate new-comment notification email sent
 * when it was first posted - no matter how long it then stayed unmoderated. This adds a
 * once-per-comment reminder email to the moderator(s), sent after a configurable number of days
 * ("input_fc_pendingReminderDays" field setting, 0 = disabled, mirroring "input_fc_spam" for the
 * SPAM auto-deletion feature). Each comment is only ever reminded about once, tracked via the
 * "reminder_sent" database column (see FieldtypeFrontendCommentsHelpersTest.php's schema/upgrade
 * coverage and correctStatusValuesResetsReminderSentTest.php for the "reopened for moderation ->
 * reminder_sent reset back to 0" half of this feature).
 */
final class FieldtypeFrontendCommentsPendingReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // sendPendingReminders() builds a REAL Notifications instance (unlike most of this suite,
        // which uses newWithoutConstructor()), so its constructor really does read the FrontendForms
        // module config via FieldtypeFrontendComments::getFrontendFormsConfigValues() - and
        // renderPendingReminderBody() really does read 'input_dateformat'/'input_timeformat' from
        // it, exactly as the real, installed FrontendForms module would provide. The default test
        // config (TestCase::setUp()) only sets 'input_framework', so tests that reach a real
        // Notifications send need these two keys added on top of it.
        TestServices::set('modules', (new Modules())->setConfig('FrontendForms', [
            'input_framework' => 'none.php',
            'input_dateformat' => 'd.m.Y',
            'input_timeformat' => 'H:i',
        ]));
    }

    private function pendingRow(array $overrides = []): array
    {
        return array_merge([
            'id' => 42,
            'pages_id' => 1,
            'status' => FieldtypeFrontendComments::pendingApproval,
            'author' => 'Alice',
            'email' => 'alice@example.com',
            'website' => '',
            'data' => 'This is my comment text.',
            'created' => time() - (10 * 86400),
            'sort' => 0,
            'user_id' => 40,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'parent_id' => 0,
            'code' => str_repeat('a1B2c3D4e5', 12), // full-length code, see FrontendCommentArrayRemoteLinkLookupTest.php
            'remote_flag' => 0,
            'notification' => 0,
            'notification_confirmed' => 0,
            'reminder_sent' => 0,
            'stars' => null,
            'upvotes' => 0,
            'downvotes' => 0,
            'spam_update' => null,
            'moderation_feedback' => '',
        ], $overrides);
    }

    private function fieldWithModerationEmail(int $days, string $email = 'moderator@example.com')
    {
        $field = $this->newField(10, 'fc_comments');
        $field->table = 'field_fc_comments';
        $field->data['input_fc_pendingReminderDays'] = $days;
        $field->data['input_fc_emailtype'] = 'text';
        $field->data['input_fc_default_to'] = $email;
        TestServices::get('fields')->add($field);
        return $field;
    }

    public function testNoQueryIsIssuedWhenTheReminderIsDisabled(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $this->fieldWithModerationEmail(0); // disabled

        $this->callMethod($fieldtype, 'sendPendingReminders');

        /** @var Database $db */
        $db = TestServices::get('database');
        self::assertCount(0, $db->prepared, 'no SELECT should be issued when the reminder is disabled (days=0)');
    }

    public function testGeneratedSelectStatementUsesTheConfiguredDaysAndPendingStatus(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $this->fieldWithModerationEmail(7);
        // no rows queued for the SELECT -> fetchAll() returns [], loop body never runs; only the
        // generated SELECT statement itself is under test here

        $this->callMethod($fieldtype, 'sendPendingReminders');

        /** @var Database $db */
        $db = TestServices::get('database');
        self::assertCount(1, $db->prepared);
        $sql = $db->prepared[0]->sql;
        self::assertStringContainsString('SELECT * FROM field_fc_comments', $sql);
        self::assertStringContainsString('status=' . FieldtypeFrontendComments::pendingApproval, $sql);
        self::assertStringContainsString('reminder_sent=0', $sql);
        self::assertStringContainsString('INTERVAL 7 DAY', $sql);
    }

    public function testPendingCommentIsMarkedAsRemindedAfterProcessing(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $this->fieldWithModerationEmail(5);

        /** @var Database $db */
        $db = TestServices::get('database');
        $db->queueResult(1, true, [$this->pendingRow()]); // result of the SELECT

        $this->callMethod($fieldtype, 'sendPendingReminders');

        self::assertCount(2, $db->prepared, 'a SELECT and then an UPDATE should have been prepared');
        $updateSql = $db->prepared[1]->sql;
        self::assertStringContainsString('UPDATE field_fc_comments SET reminder_sent=1', $updateSql);
        self::assertStringContainsString('WHERE id=:id', $updateSql);
        self::assertSame(42, $db->prepared[1]->bound[':id']);
    }

    public function testCommentIsStillMarkedAsRemindedWhenNoModerationEmailIsConfigured(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $field = $this->newField(10, 'fc_comments');
        $field->table = 'field_fc_comments';
        $field->data['input_fc_pendingReminderDays'] = 5;
        $field->data['input_fc_emailtype'] = 'custom';
        // 'mod_emails' deliberately left unset -> getModerationEmail() returns a falsy value
        TestServices::get('fields')->add($field);

        /** @var Database $db */
        $db = TestServices::get('database');
        $db->queueResult(1, true, [$this->pendingRow()]);

        $this->callMethod($fieldtype, 'sendPendingReminders');

        self::assertCount(2, $db->prepared, 'the row must still be marked as handled even without a moderator to notify, so it does not get re-selected on every future run');
        self::assertStringContainsString('UPDATE field_fc_comments SET reminder_sent=1', $db->prepared[1]->sql);
    }

    public function testCommentIsMarkedAsRemindedEvenWhenItsPageNoLongerExists(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $this->fieldWithModerationEmail(5);

        /** @var Database $db */
        $db = TestServices::get('database');
        // pages_id=0 -> the test "pages" stub's get() returns a Page with id=0 ("not found")
        $db->queueResult(1, true, [$this->pendingRow(['pages_id' => 0])]);

        $this->callMethod($fieldtype, 'sendPendingReminders');

        self::assertCount(2, $db->prepared, 'an orphaned row (page deleted) must still be marked as handled so it does not get re-selected forever');
        self::assertStringContainsString('UPDATE field_fc_comments SET reminder_sent=1', $db->prepared[1]->sql);
    }

    public function testMultiplePendingCommentsEachGetTheirOwnUpdate(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);
        $this->fieldWithModerationEmail(5);

        /** @var Database $db */
        $db = TestServices::get('database');
        $db->queueResult(2, true, [
            $this->pendingRow(['id' => 1]),
            $this->pendingRow(['id' => 2]),
        ]);

        $this->callMethod($fieldtype, 'sendPendingReminders');

        self::assertCount(3, $db->prepared, 'one SELECT plus one UPDATE per pending comment row');
        self::assertSame(1, $db->prepared[1]->bound[':id']);
        self::assertSame(2, $db->prepared[2]->bound[':id']);
    }

    public function testFieldsWithReminderDisabledAreSkippedWhileOthersAreStillProcessed(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $disabledField = $this->newField(10, 'fc_comments_a');
        $disabledField->table = 'field_fc_comments_a';
        $disabledField->data['input_fc_pendingReminderDays'] = 0;
        TestServices::get('fields')->add($disabledField);

        // newField()/fieldWithModerationEmail() both add to the same shared TestServices('fields')
        // registry, keyed by id - use a distinct id/name/table for the second field
        $enabledField = $this->newField(20, 'fc_comments_b');
        $enabledField->table = 'field_fc_comments_b';
        $enabledField->data['input_fc_pendingReminderDays'] = 3;
        $enabledField->data['input_fc_emailtype'] = 'text';
        $enabledField->data['input_fc_default_to'] = 'moderator@example.com';
        TestServices::get('fields')->add($enabledField);

        $this->callMethod($fieldtype, 'sendPendingReminders');

        /** @var Database $db */
        $db = TestServices::get('database');
        self::assertCount(1, $db->prepared, 'only the enabled field should have triggered a SELECT');
        self::assertStringContainsString('SELECT * FROM field_fc_comments_b', $db->prepared[0]->sql);
    }
}
