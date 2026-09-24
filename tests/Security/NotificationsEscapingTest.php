<?php

declare(strict_types=1);

namespace Tests\Security;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendCommentForm;
use FrontendComments\Notifications;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Regression tests for the HTML-injection fixes in Notifications.php: moderator- and
 * commenter-facing notification emails used to embed several visitor-controlled values directly
 * into the HTML mail body / a link href with no escaping at all
 * (renderNotificationAboutNewCommentBody(), renderButton(), renderNotificationAboutNewReplyBody(),
 * renderStatusChangeBody()). A crafted comment could inject arbitrary HTML/script into whatever
 * mail client opens the notification.
 */
final class NotificationsEscapingTest extends TestCase
{
    private function newNotifications(Page $page, Field $field): Notifications
    {
        $notifications = $this->newWithoutConstructor(Notifications::class);

        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->setPage($page);
        $comments->setField($field);

        $this->setProp($notifications, 'comments', $comments);
        $this->setProp($notifications, 'field', $field);
        $this->setProp($notifications, 'page', $page);

        return $notifications;
    }

    public function testNewCommentNotificationEscapesSubmittedFormValues(): void
    {
        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $field = $this->newField(10, 'comments');

        $notifications = $this->newNotifications($page, $field);

        $form = $this->newWithoutConstructor(FrontendCommentForm::class);

        $newComment = $this->newWithoutConstructor(FrontendComment::class);
        $newComment->set('status', FieldtypeFrontendComments::approved);
        $newComment->set('code', 'abc123');

        $values = [
            'testform-text' => '<script>alert(1)</script>',
        ];

        $body = $this->callMethod($notifications, 'renderNotificationAboutNewCommentBody', [$values, $newComment, $form]);

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testRenderButtonEscapesTheUrlAttribute(): void
    {
        $maliciousUrl = 'https://example.com/?x="><script>alert(1)</script>';

        // renderButton() is public and static - no reflection needed
        $html = Notifications::renderButton('Click me', '#000', '#fff', '#000', $maliciousUrl);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNewReplyNotificationEscapesAuthorAndText(): void
    {
        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $field = $this->newField(10, 'comments');

        $notifications = $this->newNotifications($page, $field);

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('author', '<img src=x onerror=alert(1)>');
        $comment->set('text', '<script>alert(2)</script>');
        $comment->set('id', 7);
        $comment->set('page', $page);
        $comment->set('field', $field);
        $comment->set('email', 'victim@example.com');

        $body = $this->callMethod($notifications, 'renderNotificationAboutNewReplyBody', [$comment]);

        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);
        self::assertStringNotContainsString('<script>alert(2)</script>', $body);
        self::assertStringContainsString('&lt;img', $body);
        self::assertStringContainsString('&lt;script&gt;alert(2)', $body);
    }

    public function testReplyNotificationUnsubscribeLinkUrlEncodesTheEmail(): void
    {
        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $field = $this->newField(10, 'comments');

        $notifications = $this->newNotifications($page, $field);

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('author', 'Jane');
        $comment->set('text', 'hello');
        $comment->set('id', 7);
        $comment->set('page', $page);
        $comment->set('field', $field);
        // an email containing "&" must not be able to inject extra query-string parameters
        // into the unsubscribe link
        $comment->set('email', 'victim+notify=0&admin=1@example.com');

        $body = $this->callMethod($notifications, 'renderNotificationAboutNewReplyBody', [$comment]);

        self::assertStringContainsString(urlencode('victim+notify=0&admin=1@example.com'), $body);
        self::assertStringNotContainsString('&admin=1@example.com&page=', $body, 'the raw "&" must not have created a bare, unencoded query-string separator');
    }

    public function testStatusChangeNotificationEscapesCommentText(): void
    {
        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $field = $this->newField(10, 'comments');

        $notifications = $this->newNotifications($page, $field);

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('text', '<script>alert(3)</script>');

        $body = $notifications->renderStatusChangeBody($comment, FieldtypeFrontendComments::spam);

        self::assertStringNotContainsString('<script>alert(3)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(3)', $body);
    }
}
