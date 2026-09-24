<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendCommentPagination;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Regression tests for a real, user-reported bug: "the pagination for comments is not shown at
 * all". Root cause - on a comment field that has never had its Pagination fieldset explicitly
 * saved (e.g. created before "input_fc_pagnumber"/"input_fc_pagorientation" existed, or simply
 * never opened in the field editor since a module upgrade introduced them), Field::get() returns
 * null for both, not the configured defaults of 10 / 'center' (see
 * FieldtypeFrontendComments::getDefaultData()) - those defaults only apply inside getConfigValue(),
 * used to render the admin config form, never by a direct field->get() read.
 *
 * This broke TWO different places, both fixed here:
 *
 * 1) FrontendCommentPagination::__construct() assigns numCommentsPage()'s and the raw
 *    input_fc_pagorientation read's return values directly into non-nullable `int $numCommentsPage`
 *    and `string $alignment` properties. Assigning null to either throws a TypeError - and since the
 *    constructor runs this on every single pagination instantiation, this fataled EVERY comment
 *    render for such a field (FrontendCommentArray::render() concatenates renderPagination() right
 *    after renderComments() with no try/catch, so the whole comments+pagination output was lost).
 *    Unlike every other existing FrontendCommentPaginationTest.php test, these tests deliberately
 *    run the REAL constructor (not newWithoutConstructor()) - that is the only way this class of bug
 *    can be caught at all.
 *
 * 2) Even once the constructor no longer throws, ___render()'s own gate re-read
 *    $this->field->get('input_fc_pagnumber') directly (bypassing the just-fixed, already-defaulted
 *    $this->numCommentsPage) and compared it with "< 1" - "null < 1" is true in PHP, so pagination
 *    was still silently suppressed even for a field with plenty of comments.
 */
final class FrontendCommentPaginationConstructionTest extends TestCase
{
    /** A field that deliberately never had ANY pagination setting explicitly saved. */
    private function unconfiguredField(): \ProcessWire\Field
    {
        return $this->newField(1, 'comments'); // no input_fc_pagnumber / input_fc_pagorientation set
    }

    private function newArrayWithComments(\ProcessWire\Field $field, int $commentCount): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $page->url = '/test-page/';
        $this->setProp($array, 'page', $page);

        for ($i = 0; $i < $commentCount; $i++) {
            $comment = $this->newWithoutConstructor(FrontendComment::class);
            $comment->set('id', $i + 1);
            $array->add($comment);
        }

        return $array;
    }

    public function testConstructorDoesNotFatalWhenPaginationWasNeverConfigured(): void
    {
        $array = $this->newArrayWithComments($this->unconfiguredField(), 15);

        // The real constructor - this must not throw a TypeError.
        $pagination = new FrontendCommentPagination($array);

        self::assertSame(10, $this->getProp($pagination, 'numCommentsPage'), 'an unconfigured field must fall back to the documented default of 10 comments per page');
        self::assertSame('center', $this->getProp($pagination, 'alignment'), 'an unconfigured field must fall back to the documented default alignment of "center"');
    }

    public function testRenderProducesPaginationMarkupWhenPaginationWasNeverConfigured(): void
    {
        // 15 comments with the default of 10 per page means 2 pages - pagination must be shown.
        $array = $this->newArrayWithComments($this->unconfiguredField(), 15);
        $pagination = new FrontendCommentPagination($array);

        $markup = $pagination->___render();

        self::assertNotSame('', $markup, 'pagination must be rendered for an unconfigured field with more comments than fit on one page, not silently stay empty');
        self::assertStringContainsString('pagination', $markup);
    }

    public function testRenderStillReturnsEmptyWhenExplicitlyDisabled(): void
    {
        // A field where pagination was explicitly turned off (0 = show all comments on one page,
        // per the admin field's own description) must keep behaving exactly like that - the fix
        // must only change the "never configured" (null) case, not this legitimate "off" case.
        $field = $this->newField(1, 'comments')->setArray([
            'input_fc_pagnumber' => 0,
            'input_fc_pagorientation' => 'center',
        ]);
        $array = $this->newArrayWithComments($field, 15);
        $pagination = new FrontendCommentPagination($array);

        self::assertSame('', $pagination->___render());
    }

    /**
     * Real, separate bug found while investigating: the framework-specific subclass name was built
     * as '<Framework>Pagination' (e.g. "Bootstrap5Pagination"), which never matches any real class -
     * the actual classes are named 'FrontendCommentPagination<Framework>' (e.g.
     * "FrontendCommentPaginationBootstrap5", see frameworks/*.php). class_exists() therefore always
     * returned false, silently falling back to the base (unthemed) markup regardless of the
     * configured framework. tests/Support/FakeFrameworkPagination.php stands in for a real framework
     * subclass here (avoiding the need to load one of the real ones, which use markup methods this
     * suite doesn't otherwise stub) - it overrides ___renderPaginationMarkup() with a distinct
     * sentinel string, so this test can prove ___render() actually resolves and delegates to it by
     * name, not just that it doesn't crash.
     */
    public function testRenderDelegatesToTheConfiguredFrameworksSubclass(): void
    {
        // getFrameWork() (used by the fixed class-name resolution, matching the pattern already
        // used elsewhere, e.g. FrontendCommentArray::getPagination()) reads the active framework from
        // the global FrontendForms module config, not from the pagination instance's own (merely
        // cached) $frontendFormsConfig property - so the framework must be configured here.
        TestServices::set('modules', (new \ProcessWire\Modules())->setConfig('FrontendForms', ['input_framework' => 'testframework.php']));

        $array = $this->newArrayWithComments($this->unconfiguredField(), 15);
        $pagination = new FrontendCommentPagination($array);

        self::assertSame('FAKE-FRAMEWORK-MARKUP', $pagination->___render());
    }
}
