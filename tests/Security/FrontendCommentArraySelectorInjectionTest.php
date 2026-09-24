<?php

declare(strict_types=1);

namespace Tests\Security;

use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\WireArray;
use ProcessWire\WireInput;
use Tests\Support\TestCase;

/**
 * Regression tests for the selector-injection fixes in FrontendCommentArray::saveStatusRemote(),
 * ::saveReplyNotificationRemote() and ::confirmNotificationRemote() (FrontendCommentArray.php).
 *
 * All three methods used to (or, for confirmNotificationRemote(), were written from the start to)
 * concatenate an unauthenticated, attacker-controlled query-string value (the "code" or "email" GET
 * parameter from a remote moderation/notification link) directly into a ProcessWire selector
 * string, e.g. `'code=' . $code`. A comma in that value is the selector language's AND-clause
 * separator, so a crafted value could inject an extra, unintended selector clause. The fix wraps
 * all three values in $sanitizer->selectorValue() first.
 *
 * These tests do not attempt to reproduce ProcessWire's selector-to-SQL matching (that needs a
 * real installation + database, deliberately out of scope for this stub-based suite - see the
 * WireArray stub's docblock). Instead they assert on the exact selector STRING the real,
 * unmodified method constructs and hands to get()/find(): a comma from the malicious input must
 * never appear un-quoted in that string. That is precisely the invariant the fix guarantees, and
 * exactly what would regress if a future change accidentally dropped the selectorValue() call.
 */
final class FrontendCommentArraySelectorInjectionTest extends TestCase
{
    private function newArray(): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(10, 'comments');
        $array->setPage($page);
        $array->setField($field);
        return $array;
    }

    public function testSaveStatusRemoteWrapsCodeInSelectorValueBeforeQuerying(): void
    {
        $array = $this->newArray();

        // crafted "code" trying to inject a second, unintended selector clause via a comma
        $maliciousCode = 'anything,status=1';
        $status = (string)FieldtypeFrontendComments::approved;

        \ProcessWire\TestServices::get('input')->setGet(['code' => $maliciousCode, 'status' => $status]);

        $this->callMethod($array, 'saveStatusRemote');

        $calls = WireArray::$calls;
        self::assertNotEmpty($calls, 'saveStatusRemote() should have queried via get()');
        $selector = $calls[0]['selector'];

        self::assertSame('get', $calls[0]['method']);
        // the raw comma must never appear un-quoted - selectorValue() either quotes the whole
        // value (comma is in its "always quote" list) or removes the injected structure
        self::assertMatchesRegularExpression(
            '/^code="[^"]*,[^"]*"$/',
            $selector,
            "expected the malicious code to be wrapped in quotes by selectorValue(), got selector: $selector"
        );
        self::assertStringNotContainsString('status=1', $selector, 'the injected clause must not appear as a live, unquoted selector clause');
    }

    public function testSaveStatusRemoteRejectsOutOfRangeStatusBeforeTouchingTheDatabase(): void
    {
        $array = $this->newArray();
        \ProcessWire\TestServices::get('input')->setGet(['code' => 'abc123', 'status' => '99']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_warningClass', $result);
        self::assertEmpty(WireArray::$calls, 'an out-of-range status must be rejected before any selector is even built');
    }

    public function testSaveReplyNotificationRemoteWrapsEmailInSelectorValueBeforeQuerying(): void
    {
        $array = $this->newArray();

        $maliciousEmail = 'attacker@example.com,notification=0';
        \ProcessWire\TestServices::get('input')->setGet([
            'email' => $maliciousEmail,
            'page' => '1',
            'notification' => '0',
        ]);

        $this->callMethod($array, 'saveReplyNotificationRemote');

        $calls = WireArray::$calls;
        self::assertNotEmpty($calls, 'saveReplyNotificationRemote() should have queried via find()');
        $selector = $calls[0]['selector'];

        self::assertSame('find', $calls[0]['method']);
        self::assertMatchesRegularExpression(
            '/^email="[^"]*,[^"]*", pages_id=1$/',
            $selector,
            "expected the malicious email to be wrapped in quotes by selectorValue(), got selector: $selector"
        );
    }

    public function testConfirmNotificationRemoteWrapsCodeInSelectorValueBeforeQuerying(): void
    {
        $array = $this->newArray();

        // crafted "code" trying to inject a second, unintended selector clause via a comma
        $maliciousCode = 'anything,notification_confirmed=1';

        \ProcessWire\TestServices::get('input')->setGet(['code' => $maliciousCode, 'confirmnotification' => '1']);

        $this->callMethod($array, 'confirmNotificationRemote');

        $calls = WireArray::$calls;
        self::assertNotEmpty($calls, 'confirmNotificationRemote() should have queried via get()');
        $selector = $calls[0]['selector'];

        self::assertSame('get', $calls[0]['method']);
        self::assertMatchesRegularExpression(
            '/^code="[^"]*,[^"]*"$/',
            $selector,
            "expected the malicious code to be wrapped in quotes by selectorValue(), got selector: $selector"
        );
        self::assertStringNotContainsString('notification_confirmed=1', $selector, 'the injected clause must not appear as a live, unquoted selector clause');
    }
}
