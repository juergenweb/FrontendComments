<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\InputfieldFrontendComments;
use ProcessWire\TestServices;
use ProcessWire\WireInputData;
use Tests\Support\TestCase;

/**
 * Unit tests for InputfieldFrontendComments.module's ___processInput() validation logic - this
 * class previously had zero test coverage. Two branches are of particular interest and documented
 * directly in the module's own NOTE comments: an invalid email or website value must be rejected
 * (error() + a session flag) WITHOUT ever being written back onto the comment, because
 * Inputfield::error() alone does not stop the page from saving and nothing downstream re-validates
 * the format - so silently persisting the invalid value here was a real bug.
 *
 * ___render() (pure backend markup construction across a dozen different Inputfield subtypes) is
 * deliberately not covered here - the effort of stubbing every Inputfield type it uses is out of
 * proportion to the business-logic risk in what is a straight property-assignment/render method.
 */
final class InputfieldFrontendCommentsTest extends TestCase
{
    private function newComment(int $id, array $values): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        foreach ($values as $key => $value) {
            $comment->set($key, $value);
        }
        return $comment;
    }

    /**
     * @param FrontendComment[] $comments
     */
    private function newInputfield(array $comments, array $postedValues): InputfieldFrontendComments
    {
        $inputfield = $this->newWithoutConstructor(InputfieldFrontendComments::class);
        $inputfield->set('name', 'comments');

        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        foreach ($comments as $comment) {
            $array->add($comment);
        }
        $inputfield->set('value', $array);

        return $inputfield;
    }

    private function fieldKey(string $field, int $commentId): string
    {
        return 'comments_' . $field . '_' . $commentId;
    }

    // -----------------------------------------------------------------
    // required fields: text / email / author must not be emptied out, only changed
    // -----------------------------------------------------------------

    public function testEmptyingTheCommentTextIsRefusedWithAWarningAndLeavesTheTextUnchanged(): void
    {
        $comment = $this->newComment(1, ['text' => 'Original text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([$this->fieldKey('text', 1) => '']);

        $inputfield->___processInput($input);

        self::assertSame('Original text', $comment->get('text'));
        self::assertNotEmpty(array_filter($inputfield->notices, fn($n) => $n['type'] === 'warning'));
    }

    public function testChangingTheCommentTextSanitizesAndStoresTheNewValue(): void
    {
        $comment = $this->newComment(1, ['text' => 'Original text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([$this->fieldKey('text', 1) => 'Updated text']);

        $inputfield->___processInput($input);

        self::assertSame('Updated text', $comment->get('text'));
    }

    // -----------------------------------------------------------------
    // email: invalid values must never be persisted (see the module's own NOTE at this call site)
    // -----------------------------------------------------------------

    public function testAnInvalidEmailIsRejectedAndTheOriginalValueIsKept(): void
    {
        $comment = $this->newComment(5, ['text' => 'Text', 'email' => 'original@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 5) => 'Text',
            $this->fieldKey('email', 5) => 'not-an-email',
            $this->fieldKey('author', 5) => 'Alice',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('original@example.com', $comment->get('email'), 'an invalid email must never overwrite the previously saved (valid) one');
        self::assertNotEmpty(array_filter($inputfield->notices, fn($n) => $n['type'] === 'error'), 'an error must be recorded');
        self::assertSame([5], TestServices::get('session')->get('failed'), 'the comment id must be recorded as failed so the admin UI keeps it expanded');
    }

    public function testAValidChangedEmailIsSanitizedAndStored(): void
    {
        $comment = $this->newComment(5, ['text' => 'Text', 'email' => 'original@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 5) => 'Text',
            $this->fieldKey('email', 5) => 'new@example.com',
            $this->fieldKey('author', 5) => 'Alice',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('new@example.com', $comment->get('email'));
    }

    // -----------------------------------------------------------------
    // website: same "never persist an invalid value" guarantee as email above
    // -----------------------------------------------------------------

    public function testAnInvalidWebsiteUrlIsRejectedAndTheOriginalValueIsKept(): void
    {
        $comment = $this->newComment(7, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => 'https://original.example.com', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 7) => 'Text',
            $this->fieldKey('email', 7) => 'a@example.com',
            $this->fieldKey('author', 7) => 'Alice',
            $this->fieldKey('website', 7) => 'not a url at all!! ',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('https://original.example.com', $comment->get('website'), 'an invalid URL must never overwrite the previously saved one');
        self::assertSame([7], TestServices::get('session')->get('failed'));
    }

    public function testAValidChangedWebsiteIsStored(): void
    {
        $comment = $this->newComment(7, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => 'https://original.example.com', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 7) => 'Text',
            $this->fieldKey('email', 7) => 'a@example.com',
            $this->fieldKey('author', 7) => 'Alice',
            $this->fieldKey('website', 7) => 'https://updated.example.com',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('https://updated.example.com', $comment->get('website'));
    }

    public function testAnEmptyWebsiteIsAllowedSinceItIsNotARequiredField(): void
    {
        $comment = $this->newComment(7, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => 'https://original.example.com', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 7) => 'Text',
            $this->fieldKey('email', 7) => 'a@example.com',
            $this->fieldKey('author', 7) => 'Alice',
            $this->fieldKey('website', 7) => '',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('', $comment->get('website'), 'unlike email/text/author, website may legitimately be cleared out');
    }

    // -----------------------------------------------------------------
    // status: only a whitelisted value may be applied
    // -----------------------------------------------------------------

    public function testAnOutOfRangeStatusValueIsRejectedAndLeftUnchanged(): void
    {
        $comment = $this->newComment(9, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 9) => 'Text',
            $this->fieldKey('email', 9) => 'a@example.com',
            $this->fieldKey('author', 9) => 'Alice',
            $this->fieldKey('status', 9) => '99',
        ]);

        $inputfield->___processInput($input);

        self::assertSame(FieldtypeFrontendComments::approved, $comment->get('status'));
        self::assertNotEmpty(array_filter($inputfield->notices, fn($n) => $n['type'] === 'error'));
    }

    public function testAValidStatusChangeIsApplied(): void
    {
        $comment = $this->newComment(9, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::pendingApproval, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 9) => 'Text',
            $this->fieldKey('email', 9) => 'a@example.com',
            $this->fieldKey('author', 9) => 'Alice',
            $this->fieldKey('status', 9) => (string)FieldtypeFrontendComments::approved,
        ]);

        $inputfield->___processInput($input);

        self::assertSame(FieldtypeFrontendComments::approved, $comment->get('status'));
    }

    public function testModerationFeedbackIsFreelyUpdatableSinceItIsNotRequired(): void
    {
        $comment = $this->newComment(9, ['text' => 'Text', 'email' => 'a@example.com', 'author' => 'Alice', 'status' => FieldtypeFrontendComments::approved, 'website' => '', 'moderation_feedback' => '']);
        $inputfield = $this->newInputfield([$comment], []);
        $input = new WireInputData([
            $this->fieldKey('text', 9) => 'Text',
            $this->fieldKey('email', 9) => 'a@example.com',
            $this->fieldKey('author', 9) => 'Alice',
            $this->fieldKey('moderation_feedback', 9) => 'Please stay on topic.',
        ]);

        $inputfield->___processInput($input);

        self::assertSame('Please stay on topic.', $comment->get('moderation_feedback'));
    }
}
