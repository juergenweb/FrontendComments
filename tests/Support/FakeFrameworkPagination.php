<?php

declare(strict_types=1);

namespace FrontendComments;

/**
 * A minimal stand-in for a real framework-specific pagination class (e.g.
 * FrontendCommentPaginationBootstrap5) - used only to prove that
 * FrontendCommentPagination::___render() resolves and delegates to the configured framework's
 * subclass by name, without needing to load a real one (those call methods like removeAttribute()
 * that this test suite doesn't otherwise need to stub). Naming it
 * "FrontendCommentPaginationTestframework" mirrors the real naming convention exactly:
 * 'FrontendCommentPagination' . FieldtypeFrontendComments::getFrameWork(), where getFrameWork()
 * derives "Testframework" from a configured input_framework of 'testframework.php'.
 */
class FrontendCommentPaginationTestframework extends FrontendCommentPagination
{
    public function ___renderPaginationMarkup(): string
    {
        return 'FAKE-FRAMEWORK-MARKUP';
    }
}
