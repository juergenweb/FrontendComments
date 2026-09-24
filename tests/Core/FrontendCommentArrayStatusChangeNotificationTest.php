<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression test for a real, user-reported bug: checking "Spam" (value "2") in the field's
 * "input_fc_status_change_notification[]" checkboxes config had no effect at all - no notification
 * email was ever sent to the commenter when a moderator set their comment's status to spam via the
 * remote link, even though checking that exact box is what is supposed to enable exactly this
 * notification. Only checking "approved" (value "1") ever worked.
 *
 * Root cause, in FrontendCommentArray::saveStatusRemote(): the switch on $status that decides
 * whether to send the notification email had a real case for FieldtypeFrontendComments::approved
 * (checking `in_array('1', $statusChangeNotification)`), but the FieldtypeFrontendComments::spam
 * case fell straight through to `default` and hardcoded `$send = false` - completely ignoring
 * whatever was actually configured in $statusChangeNotification for the "2" (spam) checkbox.
 *
 * Fix: the spam case now checks `in_array('2', $statusChangeNotification)`, mirroring the approved
 * case's own check for "1".
 *
 * A second, related bug was introduced and then corrected within this same fix: the first version
 * additionally set $redirectLink = true for the spam case (for apparent consistency with the
 * approved case), which caused the "View the comment" jump-to-comment link to appear in the
 * moderator's success alert even when the new status was spam. A spam comment is not something a
 * moderator should be sent to view, so this link must only ever appear for "approved", never for
 * "spam" - regardless of whether a notification email was actually sent.
 */
final class FrontendCommentArrayStatusChangeNotificationTest extends TestCase
{
    /** A code of the exact real-world length (see FrontendCommentArrayRemoteLinkLookupTest.php for
     *  why this must be full-length, not a short placeholder). */
    private function fullLengthCode(): string
    {
        return str_repeat('a1B2c3D4e5', 12); // 120 characters
    }

    private function newArrayWithComment(array $statusChangeNotification): FrontendCommentArray
    {
        // sendStatusChangeEmail() resolves the field's own "input_fc_emailTemplate" setting of
        // "inherit" against this FrontendForms-level config - not otherwise touched by this test,
        // but must exist for that resolution to have something to read.
        TestServices::set('modules', (new \ProcessWire\Modules())->setConfig('FrontendForms', [
            'input_framework' => 'none.php',
            'input_emailTemplate' => 'template_2.html',
        ]));

        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $field = $this->newField(1, 'comments');
        $field->set('input_fc_status_change_notification', $statusChangeNotification);
        $this->setProp($array, 'field', $field);
        // The real constructor never ran (see newWithoutConstructor() above), so this stays at its
        // declared default ([]) unless set here - saveStatusRemote() passes it straight through to
        // Notifications::sendStatusChangeEmail(), which needs the "input_emailTemplate" key set above.
        $this->setProp($array, 'frontendFormsConfig', \ProcessWire\FieldtypeFrontendComments::getFrontendFormsConfigValues());

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $this->setProp($array, 'page', $page);

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', 42);
        $comment->set('code', $this->fullLengthCode());
        $comment->set('remote_flag', 0);
        $comment->set('email', 'commenter@example.com');
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);
        $this->setProp($comment, 'comments', $array);
        $array->add($comment);

        return $array;
    }

    public function testEmailIsSentWhenSpamCheckboxIsConfigured(): void
    {
        // The exact reported config: only "2" (Spam) checked, "1" (approved) left unchecked.
        $array = $this->newArrayWithComment(['2']);
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '2']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringContainsString('email was sent', $result['alert_successClass'], 'checking the "Spam" notification checkbox must actually cause an email to be sent when a comment is set to spam');
    }

    public function testNoEmailIsSentWhenSpamCheckboxIsNotConfigured(): void
    {
        // Only "approved" is checked - spam notifications must stay off, exactly as before.
        $array = $this->newArrayWithComment(['1']);
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '2']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringNotContainsString('email was sent', $result['alert_successClass'], 'no email must be sent for a spam status change when the "Spam" checkbox is not checked');
    }

    public function testApprovedNotificationStillWorksUnaffectedByTheSpamFix(): void
    {
        // Control case: the pre-existing "approved" behaviour must be completely unaffected.
        $array = $this->newArrayWithComment(['1']);
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '1']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringContainsString('email was sent', $result['alert_successClass']);
    }

    public function testNoViewTheCommentLinkAppearsWhenStatusIsSetToSpam(): void
    {
        // Real bug: an earlier version of the spam-notification fix above also set $redirectLink =
        // true for the spam case (for apparent consistency with "approved"), which made the "View
        // the comment" jump-to-comment link appear in the alert even for a spam status change - a
        // spam comment is not something a moderator should be sent to view. Email notification is
        // configured here (as in testEmailIsSentWhenSpamCheckboxIsConfigured above) specifically to
        // prove the link's absence is not simply a side effect of no email having been sent.
        $array = $this->newArrayWithComment(['2']);
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '2']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringContainsString('email was sent', $result['alert_successClass'], 'sanity check: the email must actually have been sent for this test to prove anything');
        self::assertStringNotContainsString('View the comment', $result['alert_successClass'], 'the "View the comment" link must never appear in the alert for a spam status change');
    }

    public function testViewTheCommentLinkStillAppearsWhenStatusIsSetToApproved(): void
    {
        // Control case, mirrored against the spam test above: the redirect link must still appear
        // for "approved", exactly as before this fix.
        $array = $this->newArrayWithComment(['1']);
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '1']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringContainsString('View the comment', $result['alert_successClass'], 'the "View the comment" link must still appear in the alert for an approved status change');
    }

    public function testViewTheCommentLinkAppearsForApprovedEvenWhenNoNotificationIsConfigured(): void
    {
        // Real bug, reported with a screenshot: when a comment is approved via the remote moderator
        // link but the "approved" notification checkbox (value "1") is NOT checked in the field
        // config, the alert showed only the plain "status has been updated" text - no "View the
        // comment" link, even though the status change itself succeeded. The link must appear for
        // "approved" independently of whether an email notification was actually sent.
        $array = $this->newArrayWithComment([]); // no notification checkboxes configured at all
        TestServices::get('input')->setGet(['code' => $this->fullLengthCode(), 'status' => '1']);

        $result = $this->callMethod($array, 'saveStatusRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertStringNotContainsString('email was sent', $result['alert_successClass'], 'sanity check: no email should have been sent in this scenario');
        self::assertStringContainsString('View the comment', $result['alert_successClass'], 'the "View the comment" link must appear for an approved status change even when no notification email was sent');
    }
}
