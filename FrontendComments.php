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
use function ProcessWire\wire as wire;

class FrontendComments extends Wire
{

    protected Page|null $page = null; // the page object where the comments live on
    protected Field|null $field = null; // the PW field containing the comments
    protected FrontendCommentArray|null $comments = null; // the WireArray containing the comments as objects
    protected int|string $num_comments_on_page = 10; // number of comments per page
    protected string $ulClass = 'comments-list'; // default CSS class for the top level list element
    protected string $replyUlClass = 'reply-list'; // default CSS class for the sublevel list elements
    protected TextElements $commentsHeadline;

    /**
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function __construct(FrontendCommentArray $comments)
    {
        parent::__construct();

        $this->comments = $comments; // the FrontendCommentArray object (unsorted)
        $this->field = $comments->getField(); // Processwire comment field object
        $this->page = $comments->getPage(); // the current page object, which contains the comment field

        $this->num_comments_on_page = $this->field->get('input_fc_pagnumber');

        $this->commentsHeadline = new TextElements();
        $this->commentsHeadline->setTag($this->field->get('input_fc_comments_tag_headline'));
        $this->commentsHeadline->setAttribute('class', 'fc-comments-headline');

        // set headline if set
        $headline = FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_comments_headline');
        if ($headline && $headline !== 'none') {
            $this->commentsHeadline->setContent($headline);
        } else {
            if ($headline !== 'none') {
                // default headline
                $this->commentsHeadline->setContent($this->_('Comments'));
            }
        }
    }

    /**
     * Get the headline object of the commenting list for further manipulations
     * @return \FrontendForms\TextElements
     */
    public function getCommentsHeadline(): TextElements
    {
        return $this->commentsHeadline;
    }

    /**
     * Get all published (visible) comments as a WireArray
     * @param \FrontendComments\FrontendCommentArray $comments
     * @param int $parentid
     * @param \FrontendComments\FrontendCommentArray|null $commentArray
     * @param int $level
     * @param bool|int $reverse
     * @return \FrontendComments\FrontendCommentArray
     * @throws \ProcessWire\WireException
     */
    public static function getCommentListArray(FrontendCommentArray $comments, int $parentid = 0, FrontendCommentArray $commentArray = null, int $level = 0, bool|int $reverse = false): FrontendCommentArray
    {

        if (is_null($commentArray))
            $commentArray = wire(new FrontendCommentArray());

        // find the comments
        $parentComments = $comments->find('parent_id=' . $parentid . ',status=' . FieldtypeFrontendComments::approved . '|' . FieldtypeFrontendComments::spamReplies . ',sort=sort');

        if ($parentComments) {

            // reverse the comment order on level 0 if set
            if (($parentid === 0) && ($reverse)) {
                $parentComments = $parentComments->reverse();
            }

            $total = count($parentComments);

            foreach ($parentComments as $key => $data) {

                $data->set('level', $level);
                $data->set('levelnumber', $level . '-' . $key);
                $data->set('totalCommentsLevel', $total);
                $data->set('totalComments', $comments->count);
                $data->set('firstItem', ($key === array_key_first($parentComments->getArray())));
                $data->set('lastItem', ($key === array_key_last($parentComments->getArray())));

                $commentArray->add($data);

                // start the recursion to get the children
                self::getCommentListArray($comments, $data->id, $commentArray, $level + 1, $reverse);

            }
        }
        return $commentArray;
    }

    /**
     * Set the class for the top level list element of the comments
     * @param string $class
     * @return void
     */
    public
    function setListClass(string $class): void
    {
        $this->ulClass = $class;
    }

    /**
     * Return the class name of the ul class
     * @return string
     */
    public
    function getListClass(): string
    {
        return $this->ulClass;
    }

    /**
     * Set the class name for the sublevel ul-elements (reply comments list)
     * @param string $class
     * @return void
     */
    public
    function setReplyListClass(string $class): void
    {
        $this->replyUlClass = $class;
    }

    /**
     * Return the class name of the reply ul class
     * @return string
     */
    public
    function getReplyListClass(): string
    {
        return $this->replyUlClass;
    }

    /**
     * Get the FrontendCommentArray containing all comments for displaying on a page depending on pagination settings
     * Slices the array if necessary
     * @return \FrontendComments\FrontendCommentArray
     * @throws \ProcessWire\WireException
     */
    protected
    function getCommentsForDisplay(): FrontendCommentArray
    {

        // first, remove all comments which are not approved or spam (status 0 or 4)
        $this->comments->filter("status=1|3");

        // overwrite the pagination number if set inside the template
        $this->num_comments_on_page = $this->field->get('input_fc_pagnumber');

        // get the sort order of the comments
        $reverse = $this->field->get('input_fc_sort') ?? 0;


        $comments = self::getCommentListArray($this->comments, 0, null, 0, $reverse); // get the sorted commentArray

        // slice the array if pagination is enabled
        if ($this->num_comments_on_page && $this->num_comments_on_page > 0) {

            $limit = $this->num_comments_on_page;
            $pagPage = 1;

            if ($this->wire('input')->queryStringClean(['validNames' => ['page']])) {
                $pagPage = (int)explode('=', $this->wire('input')->queryStringClean(['validNames' => ['page']]))[1];
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

        // set the headline text
        $headline = $this->field->get('input_fc_comments_headline');
        if ($headline && $headline !== 'none') $this->commentsHeadline->setText($headline);
        return $this->commentsHeadline->render();
    }

    /**
     * Render the comments as a list of divs
     * @return string
     * @throws \ProcessWire\WireException
     */
    public
    function ___renderCommentsDiv(): string
    {

        $comments = $this->getCommentsForDisplay();

        // every comment list (independent of the first comment level) starts with a div and an unordered list
        $out = '<div id="' . $this->field->name . '-comments-container" class="comments-list">';

        // render the headline for the comments
        $out .= $this->renderCommentsHeadline();

        if($comments->count() == 0){
            $out .= '<p>'.$this->_('There are no comments at the moment. Be the first to write one.').'</p>';
        } else {

        // set the max level of comments to display
        $maxLevel = $this->field->get('input_fc_reply_depth');

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
     * @throws \ProcessWire\WireException
     */
    public
    function ___render(): string
    {
        return $this->renderCommentsDiv();
    }

    /**
     * @return string
     * @throws \ProcessWire\WireException
     */
    public
    function __toString(): string
    {
        return $this->render();
    }

}
