<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendComments;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendComments.php's headline construction/rendering logic
 * (FrontendComments::getCommentListArray() - the tree-building/filtering logic - is already
 * covered in depth by TombstoneRenderingTest.php).
 *
 * These exercise the REAL constructor (not newWithoutConstructor()) on purpose: the headline
 * behavior under test - in particular the "none" case - lives entirely inside __construct(), as
 * documented directly on the $headlineSuppressed property: a comment there explains that
 * headlineSuppressed exists specifically so renderCommentsHeadline() doesn't have to guess from an
 * empty-content TextElements object whether the headline was suppressed or simply never set.
 */
final class FrontendCommentsTest extends TestCase
{
    private function newFrontendComments(?string $headlineConfig): FrontendComments
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $field = $this->newField(1, 'comments')->setArray([
            'input_fc_pagnumber' => 10,
            'input_fc_comments_tag_headline' => 'h2',
            'input_fc_comments_headline' => $headlineConfig,
        ]);
        $page = new Page();
        $page->id = 1;
        $this->setProp($array, 'field', $field);
        $this->setProp($array, 'page', $page);

        return new FrontendComments($array);
    }

    public function testExplicitHeadlineTextIsUsedAsIs(): void
    {
        $comments = $this->newFrontendComments('Latest Discussion');

        self::assertSame('Latest Discussion', $comments->getCommentsHeadline()->getContent());
        self::assertStringContainsString('Latest Discussion', $this->callMethod($comments, 'renderCommentsHeadline'));
    }

    public function testEmptyHeadlineConfigFallsBackToTheDefaultText(): void
    {
        $comments = $this->newFrontendComments('');

        self::assertSame('Comments', $comments->getCommentsHeadline()->getContent());
        self::assertStringContainsString('Comments', $this->callMethod($comments, 'renderCommentsHeadline'));
    }

    public function testNullHeadlineConfigFallsBackToTheDefaultTextToo(): void
    {
        $comments = $this->newFrontendComments(null);

        self::assertSame('Comments', $comments->getCommentsHeadline()->getContent());
    }

    public function testExplicitNoneSuppressesTheHeadlineEntirely(): void
    {
        $comments = $this->newFrontendComments('none');

        self::assertSame('', $this->callMethod($comments, 'renderCommentsHeadline'), 'renderCommentsHeadline() must render nothing at all when explicitly disabled via "none"');
        self::assertSame('', $comments->getCommentsHeadline()->getContent(), 'no default text must have been set either');
    }
}
