<?php
/**
 * Bulma 1 markup for a single comment.
 *
 * Rendered via ProcessWire's TemplateFile class (see FrontendComment::renderCommentTemplate()).
 * All variables below are pre-rendered, already-escaped HTML snippets (or scalars) supplied by
 * FrontendCommentBulma1::___renderComment() - this file only arranges them.
 *
 * Note: the original string-concat version opened <div class="media-content"> but never closed it
 * with its own </div> - the next explicit </div> closed <div class="content"> instead, and the
 * dangling media-content div was only ever implicitly closed by the browser when it hit </article>
 * further down (browsers close still-open elements when an end tag for an ancestor appears). That
 * happened to produce the intended nesting anyway, but the HTML source was invalid. This template
 * closes media-content explicitly, so the source now matches what was already being rendered.
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
<div class="box">
    <article class="media">
        <?= $avatar ?>
        <div class="media-content">
            <div class="content">
                <div class="pb-3">
                    <div class="b-head">
                        <?= $author ?>
                        <?= $created ?>
                        <?php if ($websiteLink !== '') : ?>
                            <?= $websiteLink ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($rating !== '') : ?>
                        <div class="fcm-comment-box mb-2"><?= $rating ?></div>
                    <?php endif; ?>

                    <div>
                        <?= $commentText ?>
                        <?php if ($feedbackText !== '') : ?>
                            <?= $feedbackText ?>
                        <?php endif; ?>
                        <?php if ($noVoteAlert !== '') : ?>
                            <?= $noVoteAlert ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <nav class="level">
                <div class="level-left">
                    <span class="level-item"><?= $votes ?></span>
                </div>
                <div class="level-right">
                    <?= $replyLink ?>
                </div>
            </nav>
        </div>
    </article>
    <?= $replyForm ?>
</div>
