<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendCommentForm;
use FrontendForms\Link;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendCommentForm.php helper methods that don't already have dedicated coverage
 * elsewhere (FrontendCommentFormSelectorInjectionTest.php covers the getCommentStatusForStorage()
 * escaping fix).
 */
final class FrontendCommentFormTest extends TestCase
{
    private function newForm(): FrontendCommentForm
    {
        return $this->newWithoutConstructor(FrontendCommentForm::class);
    }

    // -----------------------------------------------------------------
    // notificationAlreadyConfirmedForEmail()
    //
    // Regression tests for the "send the double opt-in confirmation mail only once per
    // field+page" change: a repeat commenter whose email address already confirmed a
    // notification request for a PREVIOUS comment on this exact field on this exact page must
    // not be asked to confirm the same address again with every new comment. $this->comments
    // only ever holds the comments already stored for THIS field on THIS page (see
    // Fieldtype::loadPageField()), so this naturally re-requires confirmation whenever the same
    // field is used on a different page (a different $comments instance there).
    // -----------------------------------------------------------------

    private function newCommentWithValues(array $values): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        foreach ($values as $key => $value) {
            $comment->set($key, $value);
        }
        return $comment;
    }

    public function testNotificationAlreadyConfirmedForEmailReturnsFalseWhenThereAreNoCommentsYet(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'comments', $this->newWithoutConstructor(FrontendCommentArray::class));

        self::assertFalse($this->callMethod($form, 'notificationAlreadyConfirmedForEmail', ['jane@example.com']));
    }

    public function testNotificationAlreadyConfirmedForEmailReturnsFalseWhenTheEmailWasNeverConfirmedBefore(): void
    {
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->add($this->newCommentWithValues(['email' => 'jane@example.com', 'notification_confirmed' => 0]));
        $form = $this->newForm();
        $this->setProp($form, 'comments', $comments);

        self::assertFalse($this->callMethod($form, 'notificationAlreadyConfirmedForEmail', ['jane@example.com']));
    }

    public function testNotificationAlreadyConfirmedForEmailReturnsFalseForADifferentEmailAddress(): void
    {
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->add($this->newCommentWithValues(['email' => 'someoneelse@example.com', 'notification_confirmed' => 1]));
        $form = $this->newForm();
        $this->setProp($form, 'comments', $comments);

        self::assertFalse($this->callMethod($form, 'notificationAlreadyConfirmedForEmail', ['jane@example.com']));
    }

    public function testNotificationAlreadyConfirmedForEmailReturnsTrueWhenTheSameEmailWasConfirmedOnAPreviousComment(): void
    {
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->add($this->newCommentWithValues(['email' => 'jane@example.com', 'notification_confirmed' => 1]));
        $form = $this->newForm();
        $this->setProp($form, 'comments', $comments);

        self::assertTrue($this->callMethod($form, 'notificationAlreadyConfirmedForEmail', ['jane@example.com']));
    }

    public function testNotificationAlreadyConfirmedForEmailReturnsFalseForAnEmptyEmailAddress(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'comments', $this->newWithoutConstructor(FrontendCommentArray::class));

        self::assertFalse($this->callMethod($form, 'notificationAlreadyConfirmedForEmail', ['']));
    }

    // -----------------------------------------------------------------
    // notificationConfirmationCanBeSkipped()
    // -----------------------------------------------------------------

    public function testNotificationConfirmationCanBeSkippedIsTrueForALoggedInUserEvenOnTheirVeryFirstComment(): void
    {
        TestServices::get('user')->setLoggedIn(true);
        $form = $this->newForm();
        $this->setProp($form, 'comments', $this->newWithoutConstructor(FrontendCommentArray::class));
        $newComment = $this->newCommentWithValues(['email' => 'jane@example.com']);

        self::assertTrue($this->callMethod($form, 'notificationConfirmationCanBeSkipped', [$newComment]));
    }

    public function testNotificationConfirmationCanBeSkippedIsFalseForAGuestPostingForTheFirstTime(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'comments', $this->newWithoutConstructor(FrontendCommentArray::class));
        $newComment = $this->newCommentWithValues(['email' => 'jane@example.com']);

        self::assertFalse($this->callMethod($form, 'notificationConfirmationCanBeSkipped', [$newComment]));
    }

    public function testNotificationConfirmationCanBeSkippedIsTrueForAGuestWhoAlreadyConfirmedOnThisSameFieldAndPageBefore(): void
    {
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comments->add($this->newCommentWithValues(['email' => 'jane@example.com', 'notification_confirmed' => 1]));
        $form = $this->newForm();
        $this->setProp($form, 'comments', $comments);
        $newComment = $this->newCommentWithValues(['email' => 'jane@example.com']);

        self::assertTrue($this->callMethod($form, 'notificationConfirmationCanBeSkipped', [$newComment]));
    }

    // -----------------------------------------------------------------
    // getCommunityGuidelinesURL()
    // -----------------------------------------------------------------

    public function testGetCommunityGuidelinesUrlReturnsFalseWhenGuidelinesAreDisabled(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'field', $this->newField(1, 'comments')->setArray(['input_guidelines_type' => 0]));

        self::assertFalse($this->callMethod($form, 'getCommunityGuidelinesURL', [new Link()]));
    }

    public function testGetCommunityGuidelinesUrlResolvesAnInternalPageByItsUrl(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 1,
            'input_fc_internalPage' => [42],
        ]));

        TestServices::set('pages', new class {
            public function get($id) {
                $page = new Page();
                $page->id = (int)$id;
                $page->url = '/imprint/community-guidelines/';
                return $page;
            }
        });

        $result = $this->callMethod($form, 'getCommunityGuidelinesURL', [new Link()]);

        self::assertSame('/imprint/community-guidelines/', $result);
    }

    public function testGetCommunityGuidelinesUrlUsesTheExternalUrlAndMarksTheLinkNofollow(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 2,
            'input_fc_externalPage' => 'https://example.com/guidelines',
        ]));
        $link = new Link();

        $result = $this->callMethod($form, 'getCommunityGuidelinesURL', [$link]);

        self::assertSame('https://example.com/guidelines', $result);
        self::assertSame('nofollow', $this->getProp($link, 'attributes')['rel'] ?? null, 'an external guidelines link must be marked rel="nofollow"');
    }

    public function testGetCommunityGuidelinesUrlDoesNotMarkAnInternalLinkNofollow(): void
    {
        $form = $this->newForm();
        $this->setProp($form, 'field', $this->newField(1, 'comments')->setArray([
            'input_guidelines_type' => 1,
            'input_fc_internalPage' => [42],
        ]));
        TestServices::set('pages', new class {
            public function get($id) {
                $page = new Page();
                $page->id = (int)$id;
                $page->url = '/imprint/community-guidelines/';
                return $page;
            }
        });
        $link = new Link();

        $this->callMethod($form, 'getCommunityGuidelinesURL', [$link]);

        self::assertArrayNotHasKey('rel', $this->getProp($link, 'attributes'), 'nofollow must only be added for external URLs, not internal pages');
    }
}
