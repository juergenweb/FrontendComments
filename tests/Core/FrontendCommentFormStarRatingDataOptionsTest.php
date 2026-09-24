<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendCommentForm;
use Tests\Support\TestCase;

/**
 * Regression tests for a real, user-reported bug: the star-rating <select> field's "data-options"
 * attribute (read by assets/star-rating.min.js via `JSON.parse(el.getAttribute('data-options'))`)
 * was hand-built as a string with manually written "&quot;" HTML entities instead of real double
 * quotes, e.g. '&quot;tooltip&quot;:&quot;...&quot;'.
 *
 * FrontendCommentForm::___getStarRating() then passed that string to Select::setAttribute(), whose
 * underlying Tag::renderAttributes() HTML-escapes whatever value it is given via
 * htmlspecialchars($value, ENT_QUOTES, 'UTF-8') (see FrontendForms/Formelements/Tag.php) - so the
 * "&" of each already-written "&quot;" got escaped a second time into "&amp;quot;" in the rendered
 * HTML. Browsers only decode HTML entities once when parsing an attribute value, so
 * el.getAttribute('data-options') in JS returned the literal, un-parseable text "&quot;" (six
 * characters: &, q, u, o, t, ;) instead of a real '"' character. That is exactly the reported
 * console error:
 *   Uncaught SyntaxError: JSON.parse: expected property name or '}' at line 1 column 2
 * ("column 2" being the character right after the opening "{", where the literal "&" sat instead
 * of a real quote).
 *
 * Fix: build the options as a plain PHP array and json_encode() it, producing real JSON with real
 * quotes, and let setAttribute()'s own single-pass htmlspecialchars() call do the (correct)
 * HTML-escaping - exactly the same pattern every other attribute value in this codebase already
 * relies on.
 */
final class FrontendCommentFormStarRatingDataOptionsTest extends TestCase
{
    private function newFormWithStars(bool $showTooltip = false): FrontendCommentForm
    {
        $form = $this->newWithoutConstructor(FrontendCommentForm::class);
        $field = $this->newField(1, 'comments')->setArray(['input_fc_showtooltip' => $showTooltip]);
        $this->setProp($form, 'field', $field);
        $this->setProp($form, 'stars', new \FrontendForms\Select('stars'));

        // Normally populated by the real (here skipped) constructor - addOption() calls inside
        // ___getStarRating() read straight from this static array.
        FrontendCommentForm::$ratingValues = [
            1 => 'Terrible',
            2 => 'Poor',
            3 => 'Average',
            4 => 'Very Good',
            5 => 'Excellent',
        ];

        return $form;
    }

    /**
     * Simulates exactly what a browser does: parse the rendered HTML attribute value (decoding its
     * HTML entities exactly once, as browsers do), then feed that decoded string to the same
     * JSON.parse() call assets/star-rating.min.js makes. This is what actually failed in
     * production - a plain `getProp()` read of the PHP-side value would not have caught the
     * double-encoding bug, since that only shows up after a render+decode round trip.
     */
    private function getDataOptionsAsTheBrowserWouldSeeIt(FrontendCommentForm $form): string
    {
        $stars = $this->callMethod($form, '___getStarRating');
        $html = $stars->render();

        self::assertMatchesRegularExpression('/data-options="([^"]*)"/', $html, 'the rendered star-rating select must carry a data-options attribute');
        preg_match('/data-options="([^"]*)"/', $html, $matches);

        // A browser decodes HTML entities in an attribute value exactly once while parsing the tag.
        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    public function testDataOptionsAttributeIsValidJsonAfterABrowserWouldDecodeIt(): void
    {
        $decoded = $this->getDataOptionsAsTheBrowserWouldSeeIt($this->newFormWithStars());

        $parsed = json_decode($decoded, true);

        self::assertSame(JSON_ERROR_NONE, json_last_error(), 'the value JS actually receives from getAttribute("data-options") must be parseable JSON, not raw "&quot;" text: ' . json_last_error_msg() . ' (decoded value was: ' . $decoded . ')');
        self::assertIsArray($parsed);
        self::assertArrayHasKey('clearable', $parsed);
        self::assertArrayHasKey('tooltip', $parsed);
    }

    public function testClearableIsAlwaysEnabled(): void
    {
        $decoded = $this->getDataOptionsAsTheBrowserWouldSeeIt($this->newFormWithStars());
        $parsed = json_decode($decoded, true);

        self::assertTrue($parsed['clearable']);
    }

    public function testTooltipCarriesTheDefaultTextWhenTooltipsAreNotDisabled(): void
    {
        $decoded = $this->getDataOptionsAsTheBrowserWouldSeeIt($this->newFormWithStars(showTooltip: false));
        $parsed = json_decode($decoded, true);

        self::assertSame('Select a rating', $parsed['tooltip']);
    }

    public function testTooltipIsExplicitlyFalseWhenTooltipsAreDisabledInTheFieldConfig(): void
    {
        $decoded = $this->getDataOptionsAsTheBrowserWouldSeeIt($this->newFormWithStars(showTooltip: true));
        $parsed = json_decode($decoded, true);

        self::assertFalse($parsed['tooltip'], 'a disabled tooltip must decode back to the JSON boolean false, not an empty string or any other truthy/falsy stand-in');
    }
}
