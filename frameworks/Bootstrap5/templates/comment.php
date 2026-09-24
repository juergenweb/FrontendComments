<?php
/**
 * Bootstrap 5 markup for a single comment.
 *
 * Rendered via ProcessWire's TemplateFile class (see FrontendComment::renderCommentTemplate()).
 * All variables below are pre-rendered, already-escaped HTML snippets (or scalars) supplied by
 * FrontendCommentBootstrap5::___renderComment() - this file only arranges them.
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
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-start align-items-center">
            <?= $avatar ?>
            <div>
                <?= $author ?>
                <?= $created ?>
                <?php if ($rating !== '') : ?>
                    <div class="fcm-comment-box"><?= $rating ?></div>
                <?php endif; ?>
                <?php if ($websiteLink !== '') : ?>
                    <?= $websiteLink ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3 mb-4 pb-2">
            <?= $commentText ?>
            <?php if ($feedbackText !== '') : ?>
                <?= $feedbackText ?>
            <?php endif; ?>
        </div>

        <?php if ($noVoteAlert !== '') : ?>
            <?= $noVoteAlert ?>
        <?php endif; ?>

        <div class="small d-flex justify-content-end">
            <?= $votes ?>
            <?= $replyLink ?>
        </div>

        <?= $replyForm ?>
    </div>
</div>
