<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\Notifications;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for Notifications.php helper methods that don't already have dedicated coverage
 * elsewhere (NotificationsEscapingTest.php covers the escaping fixes in the render*Body() /
 * renderButton() methods).
 *
 * sendNotificationConfirmationMail() itself (like sendNotificationAboutNewReply()/
 * sendStatusChangeEmail()/sendModerationNotificationMail()) is deliberately not covered here: the
 * WireMail stub's __call() returns $this from every call including send(), which would violate
 * this method's "int" return type under this file's declare(strict_types=1) if actually invoked -
 * only its body-rendering half (renderNotificationConfirmationBody(), below) is testable.
 */
final class NotificationsTest extends TestCase
{
    private function newNotifications(): Notifications
    {
        return $this->newWithoutConstructor(Notifications::class);
    }

    // -----------------------------------------------------------------
    // replaceKey() / replaceValue() - pure array helpers used to reshape submitted form values
    // before they are handed to the moderation-notification mail.
    // -----------------------------------------------------------------

    public function testReplaceKeyRenamesAnExistingKeyWhilePreservingOrderAndValues(): void
    {
        $notifications = $this->newNotifications();
        $arr = ['author' => 'Alice', 'text' => 'Hello', 'email' => 'a@example.com'];

        $result = $this->callMethod($notifications, 'replaceKey', [$arr, 'text', 'comment']);

        self::assertSame(['author' => 'Alice', 'comment' => 'Hello', 'email' => 'a@example.com'], $result);
    }

    public function testReplaceKeyLeavesTheArrayUnchangedWhenTheOldKeyDoesNotExist(): void
    {
        $notifications = $this->newNotifications();
        $arr = ['author' => 'Alice'];

        $result = $this->callMethod($notifications, 'replaceKey', [$arr, 'missing', 'renamed']);

        self::assertSame(['author' => 'Alice'], $result);
    }

    public function testReplaceValueOverwritesOrAddsTheGivenKey(): void
    {
        $notifications = $this->newNotifications();

        self::assertSame(
            ['author' => 'Bob'],
            $this->callMethod($notifications, 'replaceValue', [['author' => 'Alice'], 'author', 'Bob'])
        );
        self::assertSame(
            ['author' => 'Alice', 'new' => 'X'],
            $this->callMethod($notifications, 'replaceValue', [['author' => 'Alice'], 'new', 'X']),
            'a key that does not exist yet must be added'
        );
    }

    // -----------------------------------------------------------------
    // getSenderName()
    // -----------------------------------------------------------------

    public function testGetSenderNameReturnsTheConfiguredFromNameForASingleLanguageSite(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray(['input_fc_from_name' => 'Support Team']));

        self::assertSame('Support Team', $this->callMethod($notifications, 'getSenderName'));
    }

    public function testGetSenderNameReturnsAnEmptyStringWhenNoFromNameIsConfigured(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments'));

        self::assertSame('', $this->callMethod($notifications, 'getSenderName'));
    }

    // -----------------------------------------------------------------
    // getCommunityGuidelinesURL()
    // -----------------------------------------------------------------

    public function testGetCommunityGuidelinesUrlReturnsNullWhenDisabled(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray(['input_guidelines_type' => 0]));

        self::assertNull($this->callMethod($notifications, 'getCommunityGuidelinesURL'));
    }

    public function testGetCommunityGuidelinesUrlUsesTheInternalPagesHttpUrl(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 1,
            'input_fc_internalPage' => [42],
        ]));
        TestServices::set('pages', new class {
            public function get($id) {
                $page = new Page();
                $page->id = (int)$id;
                $page->httpUrl = 'https://example.com/imprint/guidelines/';
                return $page;
            }
        });

        self::assertSame(
            'https://example.com/imprint/guidelines/',
            $this->callMethod($notifications, 'getCommunityGuidelinesURL')
        );
    }

    public function testGetCommunityGuidelinesUrlUsesTheExternalUrlForASingleLanguageSite(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 2,
            'input_fc_externalPage' => 'https://example.com/guidelines',
        ]));

        self::assertSame(
            'https://example.com/guidelines',
            $this->callMethod($notifications, 'getCommunityGuidelinesURL')
        );
    }

    public function testGetCommunityGuidelinesUrlPrefersTheLanguageSpecificExternalUrlForANonDefaultLanguage(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 2,
            'input_fc_externalPage' => 'https://example.com/guidelines',
            'input_fc_externalPage3' => 'https://example.com/de/richtlinien',
        ]));

        TestServices::set('languages', [1, 2, 3]); // more than one language installed

        $language = new class {
            public int $id = 3;
            public function isDefault(): bool { return false; }
        };
        /** @var \ProcessWire\User $user */
        $user = TestServices::get('user');
        $user->set('language', $language);

        self::assertSame(
            'https://example.com/de/richtlinien',
            $this->callMethod($notifications, 'getCommunityGuidelinesURL')
        );
    }

    public function testGetCommunityGuidelinesUrlFallsBackToTheDefaultExternalUrlWhenNoLanguageSpecificOneIsSet(): void
    {
        $notifications = $this->newNotifications();
        $this->setProp($notifications, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 2,
            'input_fc_externalPage' => 'https://example.com/guidelines',
            // no 'input_fc_externalPage3' set this time
        ]));

        TestServices::set('languages', [1, 2, 3]);

        $language = new class {
            public int $id = 3;
            public function isDefault(): bool { return false; }
        };
        /** @var \ProcessWire\User $user */
        $user = TestServices::get('user');
        $user->set('language', $language);

        self::assertSame(
            'https://example.com/guidelines',
            $this->callMethod($notifications, 'getCommunityGuidelinesURL')
        );
    }

    // -----------------------------------------------------------------
    // renderNotificationConfirmationBody() - the double opt-in confirmation email
    // -----------------------------------------------------------------

    public function testRenderNotificationConfirmationBodyBuildsTheConfirmLinkFromThePageAndCode(): void
    {
        $notifications = $this->newNotifications();

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('page', $page);
        $comment->set('code', 'abc123');

        $body = $this->callMethod($notifications, 'renderNotificationConfirmationBody', [$comment]);

        // the URL is embedded via renderButton(), which HTML-escapes it for the href attribute
        // context (see renderButton()'s own docblock/comment) - "&" becomes "&amp;" there
        self::assertStringContainsString(
            'https://example.com/test-page/?code=abc123&amp;confirmnotification=1#remote-change',
            $body
        );
    }

    public function testRenderNotificationConfirmationBodyDoesNotRevealAnyOfTheCommentsOwnContent(): void
    {
        // deliberate design choice: whoever receives this mail might not be the person who wrote
        // the comment at all (that is exactly the scenario double opt-in guards against) - so the
        // mail must not quote back the comment's author, text, or the email address it was sent to
        $notifications = $this->newNotifications();

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';

        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('page', $page);
        $comment->set('code', 'abc123');
        $comment->set('author', 'Someone Else');
        $comment->set('text', 'a very private comment text');
        $comment->set('email', 'victim@example.com');

        $body = $this->callMethod($notifications, 'renderNotificationConfirmationBody', [$comment]);

        self::assertStringNotContainsString('Someone Else', $body);
        self::assertStringNotContainsString('a very private comment text', $body);
        self::assertStringNotContainsString('victim@example.com', $body);
    }
}
