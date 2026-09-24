<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for two FrontendCommentArray.php lookup helpers that had no coverage yet:
 * getLastID() (a small DB query with a documented int-cast subtlety) and getCommentPage() (works
 * out which pagination page a given comment falls on - real, non-trivial arithmetic that depends
 * on FrontendComments::getCommentListArray()'s tree-building, already exercised in depth by
 * TombstoneRenderingTest.php).
 */
final class FrontendCommentArrayLookupsTest extends TestCase
{
    // -----------------------------------------------------------------
    // getLastID()
    // -----------------------------------------------------------------

    public function testGetLastIdCastsThePdoStringResultToAnInt(): void
    {
        // Documented directly on the method: PDO (emulated prepares) returns numeric columns as
        // strings, not ints - this locks in that the explicit (int) cast is still there.
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('field', $this->newField(1, 'comments')->setArray(['table' => 'field_comments']));
        $comment->set('pages_id', 5);

        TestServices::get('database')->queueResult(1, true, [['lastid' => '42']]);

        $result = $array->getLastID($comment);

        self::assertSame(42, $result);
        self::assertIsInt($result);
    }

    public function testGetLastIdReturnsNullWhenTheTableIsEmpty(): void
    {
        // MAX() over zero matching rows still returns one row, with lastid = NULL
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('field', $this->newField(1, 'comments')->setArray(['table' => 'field_comments']));
        $comment->set('pages_id', 5);

        TestServices::get('database')->queueResult(1, true, [['lastid' => null]]);

        self::assertNull($array->getLastID($comment));
    }

    public function testGetLastIdReturnsNullWhenTheQueryThrows(): void
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('field', $this->newField(1, 'comments')->setArray(['table' => 'field_comments']));
        $comment->set('pages_id', 5);

        TestServices::get('database')->queueResult(0, false); // execute() fails

        self::assertNull($array->getLastID($comment));
    }

    // -----------------------------------------------------------------
    // getCommentPage()
    // -----------------------------------------------------------------

    private function newMasterArray(int $commentsPerPage, int $reverse = 0): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($array, 'field', $this->newField(1, 'comments')->setArray([
            'input_fc_pagnumber' => $commentsPerPage,
            'input_fc_sort' => $reverse,
        ]));
        return $array;
    }

    private function addComment(FrontendCommentArray $array, int $id): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        $comment->set('parent_id', 0);
        $comment->set('status', FieldtypeFrontendComments::approved);
        $comment->set('sort', $id);
        $array->add($comment);
    }

    public function testGetCommentPageFindsTheFirstPage(): void
    {
        $array = $this->newMasterArray(commentsPerPage: 10);
        foreach (range(1, 25) as $id) {
            $this->addComment($array, $id);
        }

        self::assertSame(1, $this->callMethod($array, 'getCommentPage', [1]));
        self::assertSame(1, $this->callMethod($array, 'getCommentPage', [10]));
    }

    public function testGetCommentPageFindsALaterPage(): void
    {
        $array = $this->newMasterArray(commentsPerPage: 10);
        foreach (range(1, 25) as $id) {
            $this->addComment($array, $id);
        }

        self::assertSame(2, $this->callMethod($array, 'getCommentPage', [11]));
        self::assertSame(3, $this->callMethod($array, 'getCommentPage', [25]));
    }

    public function testGetCommentPageReturnsOneWhenPaginationIsDisabled(): void
    {
        $array = $this->newMasterArray(commentsPerPage: 0);
        foreach (range(1, 25) as $id) {
            $this->addComment($array, $id);
        }

        self::assertSame(1, $this->callMethod($array, 'getCommentPage', [20]), 'with pagination disabled (0 per page), everything is "page 1"');
    }
}
