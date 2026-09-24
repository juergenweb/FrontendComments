<?php
/**
 * Pico 2 markup for a single comment.
 *
 * Rendered via ProcessWire's TemplateFile class (see FrontendComment::renderCommentTemplate()).
 * All variables below are pre-rendered, already-escaped HTML snippets (or scalars) supplied by
 * FrontendCommentPico2::___renderComment() - this file only arranges them.
 *
 * Note: the original string-concat version put $feedbackText's <article class="comment-feedback">
 * inside the <p class="comment-text">...</p> wrapping $commentText. <article> is not valid content
 * for <p>, so browsers auto-closed the <p> there anyway - the rendered DOM already had them as
 * siblings, not nested. This template writes that same (already-in-effect) sibling structure
 * directly, so the HTML source now matches what was actually rendered - existing CSS still applies,
 * since .comment-feedback is not styled as a descendant of .comment-text.
 *
 * Available variables:
 * @var string $avatar        rendered user avatar image
 * @var string $author        rendered comment author name
 * @var string $created       rendered creation date
 * @var string $rating        rendered star rating (if enabled)
 * @var string $websiteLink   rendered link to the author's homepage
 * @var string $commentText   rendered comment text
 * @var string $feedbackText  rendered moderation feedback text (if present)
 * @var string $noVoteAlert   rendered "you already voted" alert box (if applicable)
 * @var string $votes         rendered up-/downvote links
 * @var string $replyLink     rendered "reply" link
 * @var string $replyForm     rendered reply form (if opened)
 * @var int    $level         nesting level of this comment (0 = top level)
 * @var string $levelnumber   position number of this comment within its level
 */
?>
<article class="pico-comment">
    <header>
        <?= $avatar ?>
        <div class="meta">
            <?= $author ?>
            <?= $created ?>
            <?php if ($rating !== '') : ?>
                <div class="fcm-comment-box"><?= $rating ?></div>
            <?php endif; ?>
            <?php if ($websiteLink !== '') : ?>
                <?= $websiteLink ?>
            <?php endif; ?>
        </div>
    </header>

    <p class="comment-text"><?= $commentText ?></p>
    <?php if ($feedbackText !== '') : ?>
        <?= $feedbackText ?>
    <?php endif; ?>

    <footer>
        <?php if ($noVoteAlert !== '') : ?>
            <?= $noVoteAlert ?>
        <?php endif; ?>
        <?= $votes ?>
        <?= $replyLink ?>
        <?= $replyForm ?>
    </footer>
</article>
