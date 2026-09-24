<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendCommentArray::confirmNotificationRemote() - the double opt-in
 * confirmation handler for the "notify me about replies/new comments" feature.
 *
 * Background: a commenter's own "notify me" choice (the "notification" column) is recorded as
 * soon as they post a comment, but the email address they typed in was never verified to actually
 * belong to them - so FrontendComment::addCommentToQueueTable() additionally requires a new
 * "notification_confirmed" column (see FieldtypeFrontendComments::getDatabaseSchema()) to be 1
 * before that address is ever queued for a notification mail. This handler is what sets that
 * column to 1, when the owner of the address clicks the confirmation link sent by
 * Notifications::sendNotificationConfirmationMail().
 *
 * Structurally this mirrors saveStatusRemote()/saveReplyNotificationRemote() (both already covered
 * by FrontendCommentArraySelectorInjectionTest.php for the selector-injection angle - a dedicated
 * test for confirmNotificationRemote()'s own use of the same code=selectorValue() pattern is added
 * there too): read "code"+"confirmnotification" from the query string, look the comment up by
 * code, then confirm it unless there is nothing to confirm or it was already confirmed.
 */
final class FrontendCommentArrayNotificationConfirmationTest extends TestCase
{
    private function newArrayWithComment(int $notification, int $notificationConfirmed, string $code = 'abc123'): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $field = $this->newField(1, 'comments');
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', 42);
        $comment->set('code', $code);
        $comment->set('notification', $notification);
        $comment->set('notification_confirmed', $notificationConfirmed);
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);
        $this->setProp($comment, 'comments', $array);

        $array->add($comment);

        return $array;
    }

    public function testDoesNothingWhenTheConfirmationParameterIsMissing(): void
    {
        $array = $this->newArrayWithComment(FrontendComment::flagNotifyReply, 0);
        // "code" alone, without "confirmnotification", must not trigger anything - this same query
        // string is also used by other remote links (e.g. redirectToComment())
        TestServices::get('input')->setGet(['code' => 'abc123']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertSame([], $result);
        self::assertSame(0, $array->first()->get('notification_confirmed'));
    }

    public function testReturnsAWarningWhenNoMatchingCommentIsFound(): void
    {
        $array = $this->newArrayWithComment(FrontendComment::flagNotifyReply, 0, code: 'the-real-code');
        TestServices::get('input')->setGet(['code' => 'does-not-exist', 'confirmnotification' => '1']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertArrayHasKey('alert_warningClass', $result);
    }

    public function testReturnsAWarningWhenNoNotificationWasEverRequestedForThisComment(): void
    {
        $array = $this->newArrayWithComment(FrontendComment::flagNotifyNone, 0);
        TestServices::get('input')->setGet(['code' => 'abc123', 'confirmnotification' => '1']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertArrayHasKey('alert_warningClass', $result);
        self::assertSame(0, $array->first()->get('notification_confirmed'), 'must not confirm a request that was never made');
    }

    public function testReturnsAWarningWhenTheRequestWasAlreadyConfirmed(): void
    {
        $array = $this->newArrayWithComment(FrontendComment::flagNotifyReply, 1);
        TestServices::get('input')->setGet(['code' => 'abc123', 'confirmnotification' => '1']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertArrayHasKey('alert_warningClass', $result);
    }

    public function testConfirmsAPendingNotificationRequestAndSavesTheComment(): void
    {
        $array = $this->newArrayWithComment(FrontendComment::flagNotifyAll, 0);
        TestServices::get('input')->setGet(['code' => 'abc123', 'confirmnotification' => '1']);

        $result = $this->callMethod($array, 'confirmNotificationRemote');

        self::assertArrayHasKey('alert_successClass', $result);
        self::assertSame(1, $array->first()->get('notification_confirmed'), 'the comment must now be marked confirmed');
    }
}
