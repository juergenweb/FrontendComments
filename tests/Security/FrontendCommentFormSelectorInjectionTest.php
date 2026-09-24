<?php

declare(strict_types=1);

namespace Tests\Security;

use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendCommentForm;
use ProcessWire\WireArray;
use Tests\Support\TestCase;

/**
 * Regression test for the selector-injection fix in FrontendCommentForm::getCommentStatusForStorage()
 * (FrontendCommentForm.php) - see FrontendCommentArraySelectorInjectionTest for the background on
 * why a raw comma in a user-controlled value is dangerous here, and why this suite tests the
 * constructed selector string rather than simulating ProcessWire's real selector matching.
 *
 * The real-world stake here specifically: this check decides whether a brand-new commenter's
 * email "has already posted an approved comment before" (input_fc_moderate = 2, "only new
 * commenters need moderation"). A successful injection could let a first-time commenter's own
 * submission be auto-published by manipulating what the selector matches.
 */
final class FrontendCommentFormSelectorInjectionTest extends TestCase
{
    public function testEmailIsWrappedInSelectorValueBeforeQuerying(): void
    {
        $form = $this->newWithoutConstructor(FrontendCommentForm::class);

        $field = $this->newField(10, 'comments');
        $field->set('input_fc_moderate', 2); // "only new commenters need moderation"
        $this->setProp($form, 'field', $field);

        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($form, 'comments', $comments);

        $maliciousEmail = 'attacker@example.com,status=1';
        $result = $this->callMethod($form, 'getCommentStatusForStorage', [$maliciousEmail]);

        $calls = WireArray::$calls;
        self::assertNotEmpty($calls, 'getCommentStatusForStorage() should have queried via find()');
        $selector = $calls[0]['selector'];

        self::assertMatchesRegularExpression(
            '/^email="[^"]*,[^"]*",status=1$/',
            $selector,
            "expected the malicious email to be wrapped in quotes by selectorValue(), got selector: $selector"
        );
        // with the (stubbed) query never matching anything, a brand-new commenter must stay
        // unpublished (status 0), not be waved through as "already approved before"
        self::assertSame(0, $result);
    }
}
