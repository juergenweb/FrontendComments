<?php
/**
 * Default ("None"/no-framework) markup for a single comment.
 *
 * Rendered via ProcessWire's TemplateFile class (see FrontendComment::renderCommentTemplate()).
 * All variables below are pre-rendered, already-escaped HTML snippets (or scalars) supplied by
 * FrontendComment::___renderComment() - this file only arranges them.
 *
 * This is used whenever no CSS framework theme is selected (input_framework = "none"), and it is
 * also the base every theme subclass (FrontendCommentBootstrap5 etc.) inherits from - a theme
 * subclass overwrites $commentTemplateFile with its own file right after calling
 * parent::__construct(), so this file only actually renders in the "None" case.
 *
 * Note: unlike the theme templates in frameworks/*, $statusClass sits directly on the outer
 * wrapper (fcm-comment-box), not on a separate rating wrapper - that matches how frontendcommentsnone.css
 * already targets .fcm-comment-box as the whole comment container, with .fcm-comment-head as a child.
 *
 * Available variables:
 * @var string $statusClass   'fcm-featured' or 'fcm-approved', added to the outer wrapper
 * @var string $noVoteAlert   rendered "you already voted" alert box (if applicable)
 * @var string $avatar        rendered user avatar image
 * @var string $author        rendered comment author name
 * @var string $created       rendered creation date
 * @var string $rating        rendered star rating (if enabled)
 * @var string $replyLink     rendered "reply" link
 * @var string $votes         rendered up-/downvote links
 * @var string $commentText   rendered comment text
 * @var string $feedbackText  rendered moderation feedback text (if present)
 * @var string $websiteLink   rendered link to the author's homepage
 * @var string $replyForm     rendered reply form (if opened)
 * @var int    $level         nesting level of this comment (0 = top level)
 * @var string $levelnumber   position number of this comment within its level
 */
?>
<div class="fcm-comment-box <?= $statusClass ?>">
    <div class="fcm-comment-head">
        <?php if ($noVoteAlert !== '') : ?>
            <?= $noVoteAlert ?>
        <?php endif; ?>
        <?= $avatar ?>
        <?= $author ?>
        <?= $created ?>
        <?php if ($rating !== '') : ?>
            <?= $rating ?>
        <?php endif; ?>
        <?= $replyLink ?>
        <?= $votes ?>
    </div>

    <?= $commentText ?>
    <?php if ($feedbackText !== '') : ?>
        <?= $feedbackText ?>
    <?php endif; ?>
    <?php if ($websiteLink !== '') : ?>
        <?= $websiteLink ?>
    <?php endif; ?>
    <?= $replyForm ?>
</div>
