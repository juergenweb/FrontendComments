<?php
/**
 * Uikit 3 markup for a single comment.
 *
 * Rendered via ProcessWire's TemplateFile class (see FrontendComment::renderCommentTemplate()).
 * All variables below are pre-rendered, already-escaped HTML snippets (or scalars) supplied by
 * FrontendCommentUikit3::___renderComment() - this file only arranges them.
 *
 * Note: the original string-concat version of this markup opened <ul class="uk-comment-meta ...">
 * but never closed it with </ul>. That was fixed here - the <ul> below is properly closed.
 *
 * Available variables:
 * @var string $noVoteAlert   rendered "you already voted" alert box (if applicable)
 * @var string $avatar        rendered user avatar image
 * @var string $author        rendered comment author name
 * @var string $rating        rendered star rating (if enabled)
 * @var string $created       rendered creation date
 * @var string $votes         rendered up-/downvote links
 * @var string $replyLink     rendered "reply" link
 * @var string $commentText   rendered comment text
 * @var string $feedbackText  rendered moderation feedback text (if present)
 * @var string $websiteLink   rendered link to the author's homepage
 * @var string $replyForm     rendered reply form (if opened)
 * @var int    $level         nesting level of this comment (0 = top level)
 * @var string $levelnumber   position number of this comment within its level
 */
?>
<?php if ($noVoteAlert !== '') : ?>
    <?= $noVoteAlert ?>
<?php endif; ?>
<article class="uk-comment uk-comment-primary uk-visible-toggle" tabindex="-1" role="comment">
    <header class="uk-comment-header uk-position-relative">
        <div class="uk-grid-medium uk-flex-middle" data-uk-grid>
            <?= $avatar ?>
            <div class="uk-width-expand">
                <?= $author ?>
                <ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">
                    <?php if ($rating !== '') : ?>
                        <li class="fcm-comment-box"><?= $rating ?></li>
                    <?php endif; ?>
                    <?= $created ?>
                    <?= $votes ?>
                    <?= $replyLink ?>
                </ul>
            </div>
        </div>
    </header>

    <?= $commentText ?>

    <?php if ($feedbackText !== '') : ?>
        <?= $feedbackText ?>
    <?php endif; ?>

    <?php if ($websiteLink !== '') : ?>
        <?= $websiteLink ?>
    <?php endif; ?>

    <?= $replyForm ?>
</article>
