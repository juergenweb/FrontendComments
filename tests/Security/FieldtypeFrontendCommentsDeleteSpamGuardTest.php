<?php

declare(strict_types=1);

namespace Tests\Security;

use ProcessWire\Database;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression test for FieldtypeFrontendComments::deleteSpam() (FieldtypeFrontendComments.module).
 *
 * The LazyCron spam-cleanup job used to physically DELETE every old spam-marked comment
 * unconditionally, including ones that still had replies attached via parent_id - orphaning
 * those replies and destroying the tombstone placeholder that keeps them reachable in the
 * rendered tree (see the "spam parent + approved children" scenario debugged earlier in this
 * project). The fix adds a NOT EXISTS guard so a comment referenced by any reply is skipped,
 * consistent with FrontendCommentArray::deleteComment()'s existing "any reply blocks deletion"
 * rule.
 *
 * This asserts on the *shape* of the generated SQL (the actual DELETE-with-guard behavior was
 * additionally verified once, by hand, against a real MariaDB instance with representative rows -
 * see the engagement notes; that kind of end-to-end DB behavior is what an integration test
 * suite, not this stub-based unit suite, should cover going forward).
 */
final class FieldtypeFrontendCommentsDeleteSpamGuardTest extends TestCase
{
    public function testGeneratedDeleteStatementGuardsAgainstRowsWithReplies(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $field = $this->newField(10, 'fc_comments');
        $field->table = 'field_fc_comments';
        $field->data['input_fc_spam'] = 30; // delete spam older than 30 days

        /** @var \ProcessWire\Fields $fields */
        $fields = TestServices::get('fields');
        $fields->add($field);

        $this->callMethod($fieldtype, 'deleteSpam');

        /** @var Database $db */
        $db = TestServices::get('database');
        self::assertCount(1, $db->prepared, 'exactly one DELETE statement should have been prepared for the one field');

        $sql = $db->prepared[0]->sql;
        self::assertStringContainsString('DELETE FROM field_fc_comments', $sql);
        self::assertStringContainsString('status=2', $sql);
        self::assertStringContainsString('INTERVAL 30 DAY', $sql);
        self::assertStringContainsString('NOT EXISTS', $sql, 'the DELETE must not run unconditionally - it must skip rows that still have replies');
        self::assertStringContainsString('parent_id', $sql);
    }

    public function testSpamIsNeverDeletedWhenTheFieldHasNoRetentionPeriodConfigured(): void
    {
        $fieldtype = $this->newWithoutConstructor(FieldtypeFrontendComments::class);

        $field = $this->newField(10, 'fc_comments');
        $field->table = 'field_fc_comments';
        $field->data['input_fc_spam'] = 0; // disabled

        /** @var \ProcessWire\Fields $fields */
        $fields = TestServices::get('fields');
        $fields->add($field);

        $this->callMethod($fieldtype, 'deleteSpam');

        /** @var Database $db */
        $db = TestServices::get('database');
        self::assertCount(0, $db->prepared, 'no DELETE should be issued when spam retention is disabled (0)');
    }
}
