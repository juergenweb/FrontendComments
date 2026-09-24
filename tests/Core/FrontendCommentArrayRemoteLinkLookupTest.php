<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression tests for a real, previously undiscovered bug found in this session: every remote
 * link that looks a comment up by its 120-character "code" property (saveStatusRemote(),
 * confirmNotificationRemote()) - and, similarly, saveReplyNotificationRemote()'s lookup by email -
 * built its selector via `$this->wire('sanitizer')->selectorValue($value)` WITHOUT raising that
 * method's own "maxLength" option, which defaults to 100. A comment's code
 * (FrontendCommentForm.php: `$random->alphanumeric(120)`) is 120 characters - 20 more than that
 * default - so selectorValue() silently truncated it to the first 100 characters before it ever
 * reached the selector string. The resulting selector then asked for a comment whose code equals
 * that 100-character PREFIX, which never equals the real, stored 120-character code - so every one
 * of these lookups failed with "no matching comment was found for this code", regardless of
 * whether the code in the link actually, exactly matched the one in the database. This was real,
 * user-reported, in-production behavior - confirmed against a genuine test site - not a
 * theoretical concern, and it affected the pre-existing saveStatusRemote() moderator links too, not
 * just the newer confirmNotificationRemote(). The fix passes an explicit, large-enough maxLength
 * (120 for codes, 255 for emails - matching each column's own varchar length in
 * getDatabaseSchema()) at each of the three call sites.
 *
 * These tests use a REALISTIC, full-length 120-character code/long email specifically because the
 * existing FrontendCommentArraySelectorInjectionTest.php only ever exercises short (well under 100
 * character) values - which is exactly why that bug was never caught by this suite before: a short
 * malicious code truncates to itself and still matches fine, silently missing this whole class of
 * bug entirely.
 */
final class FrontendCommentArrayRemoteLinkLookupTest extends TestCase
{
    /** A code of the exact real-world length (see FrontendCommentForm.php) - deliberately NOT a
     *  round number like 100 so a maxLength that is merely "large enough by coincidence" would not
     *  quietly pass this test. */
    private function fullLengthCode(): string
    {
        return str_repeat('a1B2c3D4e5', 12); // 10 * 12 = 120 characters, alphanumeric like the real thing
    }

    private function newArrayWithComment(array $commentValues): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $field = $this->newField(1, 'comments');
        // saveStatusRemote()'s "approved" branch reads this via in_array() unconditionally - must
        // never be left null/unset, same reasoning as elsewhere in this suite for similar config
        // reads.
        $field->set('input_fc_status_change_notification', []);
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', 42);
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);
        $this->setProp($comment, 'comments', $array);
        foreach ($commentValues as $key => $value) {
            $comment->set($key, $value);
        }

        $array->add($comment);

        return $array;
    }

    public function testSaveStatusRemoteFindsACommentByItsFullLengthOneHundredTwentyCharacterCode(): void
    {
        $code = $this->fullLengthCode();
        $array = $this->newArrayWithComment(['code' => $code, 'remote_flag' => 0]);
        TestServices::get('input')->setGet(['code' => $code, 'status' => '1']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result, 'a full-length code must be matched, not truncated away into a false "not found"');
    }

    public function testConfirmNotificationRemoteFindsACommentByItsFullLengthOneHundredTwentyCharacterCode(): void
    {
        $code = $this->fullLengthCode();
        $array = $this->newArrayWithComment([
            'code' => $code,
            'notification' => FrontendComment::flagNotifyAll,
            'notification_confirmed' => 0,
        ]);
        TestServices::get('input')->setGet(['code' => $code, 'confirmnotification' => '1']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertArrayHasKey('alert_successClass', $result, 'a full-length code must be matched, not truncated away into a false "not found"');
    }

    public function testSaveReplyNotificationRemoteFindsACommentByALongEmailAddress(): void
    {
        // A real email is very unlikely to reach 100+ characters, but the "email" column allows up
        // to 255 (see getDatabaseSchema()) - the fix here mirrors the code-length fix above so this
        // lookup doesn't silently fail for an unusually long, but valid, address either.
        $longEmail = str_repeat('somewhatlonglocalpart', 5) . '@example.com'; // well over 100 chars
        $array = $this->newArrayWithComment(['email' => $longEmail, 'notification' => 2, 'pages_id' => 1]);
        TestServices::get('input')->setGet(['email' => $longEmail, 'page' => '1', 'notification' => '0']);

        $result = $this->callMethod($array, 'saveReplyNotificationRemote');

        self::assertArrayHasKey('alert_successClass', $result, 'a long-but-valid email must be matched, not truncated away into "no comments found"');
    }

    /**
     * Real, user-reported bug: on a field where "input_fc_status_change_notification" (the
     * checkboxes config for "send notification email on status change") has never been explicitly
     * saved - e.g. a field created before this setting existed - Field::get() returns null for it,
     * not the configured default of [] (that default only applies inside
     * FieldtypeFrontendComments::getConfigValue(), which is used to render the admin config form,
     * not by this direct read). saveStatusRemote()'s "approved" branch fed that null straight into
     * in_array() as the $haystack, which has required an array since PHP 8.0:
     *   in_array(): Argument #2 ($haystack) must be of type array, null given
     * ...fatally breaking every "approve via remote link" click for such a field. Every OTHER test
     * in this file deliberately pre-sets this field to [] in newArrayWithComment() (see its own
     * comment), which is exactly why this bug was never caught by the rest of the suite - this test
     * uses a field where the setting was never touched at all, matching the real-world report.
     */
    public function testSaveStatusRemoteApprovingDoesNotFatalWhenStatusChangeNotificationWasNeverConfigured(): void
    {
        $code = $this->fullLengthCode();
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $field = $this->newField(1, 'comments'); // deliberately NOT setting input_fc_status_change_notification
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', 42);
        $comment->set('code', $code);
        $comment->set('remote_flag', 0);
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);
        $this->setProp($comment, 'comments', $array);
        $array->add($comment);

        TestServices::get('input')->setGet(['code' => $code, 'status' => '1']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result, 'an unconfigured notification setting must be treated as "send to nobody", not fatal');
    }
}
