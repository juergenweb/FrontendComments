<?php

    declare(strict_types=1);

    namespace FrontendComments;

    /*
 * Class to create the comment list, which contains all comments and can be manipulated in several ways
 * List of comments can be displayed as an unordered list or using div containers only
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: FrontendComments.php
 * Created: 24.12.2024
 */

    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\Wire;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use FrontendForms\TextElements;
    use ProcessWire\WireException;
    use ProcessWire\WirePermissionException;

    use function ProcessWire\wire as wire;

class FrontendComments extends Wire
{
    protected Page|null $page = null; // the page object where the comments live on
    protected Field|null $field = null; // the PW field containing the comments
    protected FrontendCommentArray|null $comments = null; // the WireArray containing the comments as objects
    protected int|string $num_comments_on_page = 10; // number of comments per page
    protected string $ulClass = 'fcm-comments-list'; // default CSS class for the top level list element
    protected string $replyUlClass = 'reply-list'; // default CSS class for the sublevel list elements
    protected TextElements $commentsHeadline;
    // true when input_fc_comments_headline is explicitly set to "none" - renderCommentsHeadline()
    // uses this to skip rendering entirely, instead of relying on $this->commentsHeadline (always
    // a real object, never null) rendering nothing on its own when no content was ever set
    protected bool $headlineSuppressed = false;

    /**
     * @throws WireException
     * @throws WirePermissionException
     */
    public function __construct(FrontendCommentArray $comments)
    {
        parent::__construct();

        $this->comments = $comments; // the FrontendCommentArray object (unsorted)
        $this->field = $comments->getField(); // Processwire comment field object
        $this->page = $comments->getPage(); // the current page object, which contains the comment field

        // A field that has never had "input_fc_pagnumber" explicitly saved (e.g. created before
        // this setting existed, or set via setPaginationNumber() with input_fc_pagorientation
        // never touched, or vice versa) returns null here, not the configured default of 10 (see
        // FieldtypeFrontendComments::getDefaultData()) - that default only applies inside
        // getConfigValue(), used to render the admin config form, not by this direct read.
        // $num_comments_on_page is a non-nullable `int|string` property, so assigning null
        // directly used to throw a TypeError right here in the constructor - on EVERY comment
        // render for such a field. Same bug, same fix, as FrontendCommentPagination.php.
        $this->num_comments_on_page = $this->field->get('input_fc_pagnumber') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_pagnumber'];

        $this->commentsHeadline = new TextElements();
        // Same "never explicitly saved" null-safety as num_comments_on_page above -
        // TextElements::setTag() takes a non-nullable string, so an unconfigured field's null
        // value used to throw a TypeError right here, before pagination was ever reached.
        $this->commentsHeadline->setTag($this->field->get('input_fc_comments_tag_headline') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_comments_tag_headline']);
        $this->commentsHeadline->setAttribute('class', 'fc-comments-headline');

        // set headline if set
        $headline = FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_comments_headline');

        if ($headline && $headline !== 'none') {
            $this->commentsHeadline->setContent($headline);
        } else {
            if ($headline !== 'none') {
                // default headline
                $this->commentsHeadline->setContent($this->_('Comments'));
            } else {
                // explicitly disabled - do not render the headline element at all
                $this->headlineSuppressed = true;
            }
        }
    }

    /**
     * Get the headline object of the commenting list for further manipulations
     * @return TextElements
     */
    public function getCommentsHeadline(): TextElements
    {
        return $this->commentsHeadline;
    }

    /**
     * Get all published (visible) comments as a WireArray
     * @param FrontendCommentArray $comments
     * @param int $parentid
     * @param FrontendCommentArray|null $commentArray
     * @param int $level
     * @param bool|int $reverse
     * @return FrontendCommentArray
     * @throws WireException
     */
    public static function getCommentListArray(FrontendCommentArray $comments, int $parentid = 0, FrontendCommentArray|null $commentArray = null, int $level = 0, bool|int $reverse = false): FrontendCommentArray
    {

        if (is_null($commentArray)) {
            $commentArray = wire(new FrontendCommentArray());
        }

        // find the comments. SPAM is included here in addition to approved/featured, because a
        // spam-marked comment that still has approved/featured replies underneath it must stay
        // reachable so the recursion below can still find and render those replies - it is then
        // filtered back out again right after, unless it actually has such replies (in which case
        // it is kept and rendered as a placeholder instead of its real content, see
        // FrontendComment::isSpamPlaceholder() / hasVisibleReplies()).
        $parentComments = $comments->find('parent_id=' . $parentid . ',status=' . FieldtypeFrontendComments::approved . '|' . FieldtypeFrontendComments::featured . '|' . FieldtypeFrontendComments::spam . ',sort=sort');

        foreach ($parentComments as $candidate) {
            if ($candidate->get('status') === FieldtypeFrontendComments::spam && !$candidate->hasVisibleReplies()) {
                $parentComments->remove($candidate);
            }
        }


        if ($parentComments->count()) {
            // reverse the comment order on level 0 if set
            if (($parentid === 0) && ($reverse)) {
                $parentComments = $parentComments->reverse();
            }

            foreach ($parentComments as $key => $data) {
                $data->set('level', $level);
                $data->set('levelnumber', $level . '-' . $key);
                $data->set('firstItem', ($key === array_key_first($parentComments->getArray())));
                $data->set('lastItem', ($key === array_key_last($parentComments->getArray())));

                $commentArray->add($data);

                // start the recursion to get the children
                if (!is_null($data->id)) {
                    self::getCommentListArray($comments, $data->id, $commentArray, $level + 1, $reverse);
                }
            }
        }
        return $commentArray;
    }

    /**
     * Set the class for the top level list element of the comments
     * @param string $class
     * @return void
     */
    public function setListClass(string $class): void
    {
        $this->ulClass = $class;
    }

    /**
     * Return the class name of the ul class
     * @return string
     */
    public function getListClass(): string
    {
        return $this->ulClass;
    }

    /**
     * Set the class name for the sublevel ul-elements (reply comments list)
     * @param string $class
     * @return void
     */
    public function setReplyListClass(string $class): void
    {
        $this->replyUlClass = $class;
    }

    /**
     * Return the class name of the reply ul class
     * @return string
     */
    public function getReplyListClass(): string
    {
        return $this->replyUlClass;
    }

    /**
     * Get the FrontendCommentArray containing all comments for displaying on a page depending on pagination
     * settings Slices the array if necessary
     * @return FrontendCommentArray
     * @throws WireException
     */
    protected function getCommentsForDisplay(): FrontendCommentArray
    {

        // Remove all comments that are neither approved, featured, nor spam. SPAM is kept here
        // (unlike before) so that a spam comment with existing approved/featured replies can still
        // be reached by getCommentListArray() below, which decides per comment whether it actually
        // needs to stay in the tree - a spam comment without such replies is filtered back out there
        // and never rendered, exactly as before this change.
        $this->comments->filter('status=' . FieldtypeFrontendComments::approved . '|' . FieldtypeFrontendComments::featured . '|' . FieldtypeFrontendComments::spam);

        // Real bug found and fixed: getCommentListArray() below prunes a spam comment back out
        // the moment it turns out to have no visible (approved/featured) replies - but it only
        // ever did that on its OWN local copy of the candidates at each level, never on
        // $this->comments itself. FrontendCommentPagination reads its totalComments straight from
        // this same, shared $this->comments array (see FrontendCommentArray::getPagination()),
        // so a spam comment with no visible replies stayed counted there forever, even though it
        // is never actually rendered - e.g. "Showing 1 to 4 of 9 comments" with only 8 comments
        // (all approved, one spam with no replies) ever actually shown. Prune the exact same
        // "spam without visible replies" comments here too, so the count used for pagination
        // matches what getCommentListArray() will actually display.
        foreach ($this->comments as $candidate) {
            if ($candidate->get('status') === FieldtypeFrontendComments::spam && !$candidate->hasVisibleReplies()) {
                $this->comments->remove($candidate);
            }
        }

        // overwrite the pagination number if set inside the template - same null-safety as the
        // constructor's own read of this setting above.
        $this->num_comments_on_page = $this->field->get('input_fc_pagnumber') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_pagnumber'];

        // get the sort order of the comments
        $reverse = $this->field->get('input_fc_sort') ?? 0;


        $comments = self::getCommentListArray($this->comments, 0, null, 0, $reverse); // get the sorted commentArray

        // slice the array if pagination is enabled
        if ($this->num_comments_on_page && $this->num_comments_on_page > 0) {
            $limit = $this->num_comments_on_page;
            $pagPage = 1;

            // use the site's configurable pagination query-string key instead of a hardcoded
            // 'page' - FrontendCommentArray::redirectToComment() already builds its "jump to
            // comment" link with this same setting, so reading it back with a hardcoded 'page'
            // would silently break pagination on any site that customizes this prefix
            $pageNumPrefix = $this->wire('config')->pageNumUrlPrefix;
            if ($this->wire('input')->queryStringClean(['validNames' => [$pageNumPrefix]])) {
                $pagPage = (int)explode('=', $this->wire('input')->queryStringClean(['validNames' => [$pageNumPrefix]]))[1];
            }

            $start = ($pagPage - 1) * ($limit);
            $comments = $comments->slice($start, $limit); // slice the comment array
        }

        return $comments;
    }

    /**
     * Render the headline over the comment list
     * @return string
     */
    protected function renderCommentsHeadline(): string
    {
        $out = '';
        if (!$this->headlineSuppressed) {
            $out = $this->commentsHeadline->render();
        }
        return $out;
    }

    /**
     * Render the comments as a list of divs
     * @return string
     * @throws WireException
     */
    public function ___renderCommentsDiv(): string
    {

        $comments = $this->getCommentsForDisplay();

        // every comment list (independent of the first comment level) starts with a div and an unordered list
        $out = '<div id="' . $this->field->name . '-comments-container" class="fcm-comments-list">';

        // render the headline for the comments
        $out .= $this->renderCommentsHeadline();

        if ($comments->count() == 0) {
            $out .= '<p>' . $this->_('There are no comments at the moment. Be the first to write one.') . '</p>';
        } else {
            // set the max level of comments to display
            // Same "never explicitly saved" null-safety as elsewhere in this constructor/class:
            // an unconfigured field's null value here used to get written straight into a reply
            // comment's 'level' (via set('level', $maxLevel) below, for any reply whose real
            // level exceeds it), which then TypeErrors two lines later at
            // renderComment($comment->get('levelnumber'), $comment->get('level')) - that method's
            // $level parameter is a non-nullable int.
            $maxLevel = $this->field->get('input_fc_reply_depth') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_reply_depth'];

            foreach ($comments as $key => $comment) {
                // correct comment level if it is higher than the allowed level
                if ($comment->get('level') > $maxLevel) {
                    $comment->set('level', $maxLevel);
                    $comment->set('levelnumber', $comment->get('level') . '-' . $key);
                }

                $out .= '<div id="comment-' . $comment->get('id') . '" class="fc-listitem level-' . $comment->get('level') . '">';
                $out .= $comment->renderComment($comment->get('levelnumber'), $comment->get('level'));
                $out .= '</div>';
            }
        }
        $out .= '</div>';

        return $out;
    }

    /**
     * Alias method for the renderCommentsDiv() method
     * @return string
     * @throws WireException
     */
    public function ___render(): string
    {
        return $this->renderCommentsDiv();
    }

    /**
     * @return string
     * @throws WireException
     */
    public function __toString(): string
    {
        return $this->render();
    }
}
