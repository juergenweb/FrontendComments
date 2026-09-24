<?php

declare(strict_types=1);

namespace Tests\Security;

use FrontendComments\FrontendComment;
use Tests\Support\TestCase;

/**
 * Regression tests for the stored-XSS fix in FrontendComment::createCommentAuthor() and
 * ::createCommentText() (FrontendComment.php).
 *
 * Background: FrontendForms\Tag::setContent()/renderNonSelfclosingTag() never escapes its
 * content (only attribute values go through htmlspecialchars() there - confirmed by reading
 * FrontendForms' own source, see Formelements/Tag.php). "author" and "text" are free-text values
 * submitted by anonymous visitors via the public comment form, and FrontendCommentForm's
 * "maxLength" sanitizer only limits length - it strips no HTML. So without escaping them
 * ourselves before setContent(), a crafted author name or comment body is stored XSS against
 * every visitor of the page. The fix wraps both in $sanitizer->entities() before setContent().
 */
final class FrontendCommentXssTest extends TestCase
{
    private function commentWith(string $author, string $text): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('author', $author);
        $comment->set('text', $text);
        return $comment;
    }

    public function testMaliciousAuthorNameIsEntityEncodedBeforeStorage(): void
    {
        $payload = '<script>alert(document.cookie)</script>';
        $comment = $this->commentWith($payload, 'harmless text');

        $textElements = $this->callMethod($comment, 'createCommentAuthor');

        $stored = $textElements->getContent();
        self::assertStringNotContainsString('<script>', $stored, 'the raw <script> tag must not survive into the stored/rendered content');
        self::assertStringContainsString('&lt;script&gt;', $stored, 'the payload must be present only in its entity-encoded form');

        // rendered output goes through Tag::render(), which - like the real FrontendForms
        // library - emits content completely unescaped, so the escaping MUST already have
        // happened before setContent() for the final HTML to be safe.
        $rendered = $textElements->render();
        self::assertStringNotContainsString('<script>', $rendered);
    }

    public function testMaliciousCommentTextIsEntityEncodedBeforeStorage(): void
    {
        $payload = '"><img src=x onerror=alert(1)>';
        $comment = $this->commentWith('Jane', $payload);

        $textElements = $this->callMethod($comment, 'createCommentText');

        $rendered = $textElements->render();
        self::assertStringNotContainsString('<img', $rendered, 'an injected tag must not appear unescaped in the rendered comment text');
        self::assertStringContainsString('&lt;img', $rendered);
        self::assertStringContainsString('&quot;', $rendered);
    }

    public function testOrdinaryAuthorNameIsUnaffected(): void
    {
        $comment = $this->commentWith('Jürgen K.', 'A perfectly normal comment.');

        $textElements = $this->callMethod($comment, 'createCommentAuthor');

        // sanitizer->entities() encodes into named HTML entities (ü -> &uuml;), so compare
        // against what the real fix actually produces rather than assuming byte-identical output.
        self::assertSame(
            \ProcessWire\TestServices::get('sanitizer')->entities('Jürgen K.'),
            $textElements->getContent()
        );
    }
}
