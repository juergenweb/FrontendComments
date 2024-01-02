<?php
    declare(strict_types=1);

    /*
     * Class to create the comment Array, which contains all comments and can be manipulated in several ways
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: CommentArray.php
     * Created: 24.06.2023
     *
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected int $commentId: the value (id) of the query string "commentid"
     * @property string $code: the code for updating the comment status via mail link
     * @property array $userdata: array that hold various data about the user visiting the comments
     * @property int|bool|null $input_fc_outputorder: form should be displayed after (tru) or before (false) the comment list
     *
     * @method setPage(): set the page the comment field is part of
     * @method Page getPage(): set the page the comment field is part of
     * @method setField(): set the field object for the comments field
     * @method Page getField(): get the field object for the comment field
     * @method CommentArray makeNew(): create a new CommentArray
     *
     * @method setReplyDepth(): change the reply depth on per field base
     * @method setModeration(): change the comments moderation status on per field base
     * @method setMailTemplate(): change the mail template on per field base
     * @method setModeratorEmails(): change the mail addresses of the moderators
     * @method setMailSubject(): change the mail subject of the notification mails for the moderators
     * @method setMailTitle(): change the mail title of the notification mails for the moderators
     * @method setSenderEmail(): change the sender email address of the notification mails for the moderators
     * @method setSenderName(): change the name of the sender of the notification mails for the moderators
     * @method setSortNewToOld(): set if the comments should be output from new to old or not
     * @method showFormAfterComments(): set if the form should be rendered after the comment list or not
     * @method showStarRating(): set if the star rating should be displayed or not
     * @method showTextareaCounter(): set if a character counter should be displayed under the comment textarea or not
     * @method showVoting(): set if a voting options for a comment should be displayed or not
     * @method useCaptcha(): set if a CAPTCHA should be used or not
     *
     */

    namespace FrontendComments;

    use Exception;
    use FrontendForms\Alert;
    use PDO;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\PaginatedArray;
    use ProcessWire\WireArray;
    use ProcessWire\WireException;
    use ProcessWire\WirePaginatable;

    class CommentArray extends PaginatedArray implements WirePaginatable
    {

        use configValues;

        // Declare all properties
        protected Page|null $page = null;
        protected Field|null $field = null;
        protected int $commentId = 0;
        protected string|null $code = null;
        protected array $userdata = [];
        protected int|bool|null $input_fc_outputorder = false;


        /**
         * @throws \ProcessWire\WireException
         */
        public function __construct()
        {
            parent::__construct();

            // set the value if query string 'code' is present or not (true/false)
            $this->code = $this->getQueryStringValue('code');

            // set the value of the query string 'commentid' as an integer
            $this->commentId = (int)$this->getQueryStringValue('commentid');

            // put user data inside an array for later usage by votes
            $this->userdata = [
                'user_id' => $this->wire('user')->id,
                'ip' => $this->wire('session')->getIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];

        }

        /**
         * Helper function to round to certain steps (half, quarter, …)
         * @param $num
         * @param $parts -> 2 = half steps, 4 = quarter steps,....
         * @return float
         */
        protected function mRound($num, $parts): float
        {
            $res = $num * $parts;
            $res = round($res);
            return $res / $parts;
        }

        /**
         * Calculate the average Rating of all comments
         * @return float|null
         */
        public function getAverageStarRating(): ?float
        {
            // find all comments with rating (not null)
            $values = [];
            foreach ($this as $comment) {
                if (!is_null($comment->stars)) {
                    $values[] = $comment->stars;
                }
            }

            if (count($values) > 0) {
                $averageValue = array_sum($values) / count($values);
                $rounded = round($averageValue, 1);
                return $this->mRound($rounded, 2);
            }
            return null;
        }

        /**
         * Render FontAwesome stars depending on half-step number
         * @param float|int|null $stars
         * @param bool $showNull
         * @return string
         */
        public static function ___renderStarsOnly(float|int|null $stars, bool $showNull = false): string
        {
            $out = '';
            if ($showNull && $stars == null) {
                $stars = 0;
            }
            if (!is_null($stars)) {
                $out = '<div class="star-rating-result">';

                $fullStars = round($stars, 0, PHP_ROUND_HALF_DOWN);

                $halfStars = (($stars - $fullStars) === 0.0) ? 0 : 1;
                $emptyStars = 5 - $fullStars - $halfStars;
                // full stars
                if ($fullStars) {
                    for ($x = 1; $x <= $fullStars; $x++) {
                        $out .= '<span class="full-star"></span>';
                    }
                }
                if ($halfStars) {
                    $out .= '<span class="half-star"></span>';
                }
                if ($emptyStars) {
                    for ($x = 1; $x <= $emptyStars; $x++) {
                        $out .= '<span class="empty-star"></span>';
                    }
                }
                $out .= '</div>';
            }
            return $out;
        }

        /**
         * Public function to change the reply depth
         * @param int $depth - 1, 2, 3... must be higher than 0
         * @return $this
         */
        public function setReplyDepth(int $depth): self
        {
            $depth = $depth > 0 ?? 1;
            $this->field->input_fc_depth = $depth;
            return $this;
        }

        /**
         * Public function to change the reply depth
         * @param int $moderate
         * @return $this
         */
        public function setModeration(int $moderate): self
        {
            $this->field->input_fc_moderate = $moderate;
            return $this;
        }

        /**
         * Public function to change the mail template
         * @param string $template
         * @return $this
         */
        public function setMailTemplate(string $template): self
        {
            $this->field->input_fc_emailTemplate = $template;
            return $this;
        }

        /**
         * Public function to change the email addresses of the moderators, where the mails should be sent to
         * @param string $moderatoremail
         * @return $this
         */
        public function setModeratorEmails(string $moderatoremail): self
        {
            $this->field->input_fc_default_to = $moderatoremail;
            return $this;
        }

        /**
         * Set a new custom mail subject
         * @param string $subject
         * @return $this
         */
        public function setMailSubject(string $subject): self
        {
            $this->field->input_fc_subject = $subject;
            return $this;
        }

        /**
         * Set a new custom mail title
         * @param string $title
         * @return $this
         */
        public function setMailTitle(string $title): self
        {
            $this->field->input_fc_title = $title;
            return $this;
        }

        /**
         * Set a new email address, which will be displayed as the sender mail address of the notification mails
         * @param string $email
         * @return $this
         */
        public function setSenderEmail(string $email): self
        {
            $this->field->input_fc_email = $email;
            return $this;
        }

        /**
         * Set a new name, which will be displayed as the sender name of the notification mails
         * @param string $name
         * @return $this
         */
        public function setSenderName(string $name): self
        {
            $this->field->input_fc_sender = $name;
            return $this;
        }

        /**
         * Set if the sort order should be from new to old or not
         * @param bool $sort
         * @return $this
         */
        public function setSortNewToOld(bool $sort): self
        {
            $this->field->input_fc_sort = $sort;
            return $this;
        }

        /**
         * Render the form after the comments
         * @param bool $after
         * @return $this
         */
        public function showFormAfterComments(bool $after): self
        {
            $this->field->input_fc_outputorder = $after;
            return $this;
        }

        /**
         * Show star rating or not
         * @param bool $show
         * @return $this
         */
        public function showStarRating(bool $show): self
        {
            $this->field->input_fc_stars = $show;
            return $this;
        }

        /**
         * Show the character counter under the textarea or not
         * @param bool $show
         * @return $this
         */
        public function showTextareaCounter(bool $show): self
        {
            $this->field->input_fc_counter = $show;
            return $this;
        }

        /**
         * Show voting options on comments or not
         * @param bool $show
         * @return $this
         */
        public function showVoting(bool $show): self
        {
            $this->field->input_fc_voting = $show;
            return $this;
        }

        /**
         * Set the usage of a CAPTCHA by entering the name of the CAPTCHA, inherit or none
         * @param string $captcha
         * @return $this
         */
        public function useCaptcha(string $captcha): self
        {
            $this->field->input_fc_captcha = $captcha;
            return $this;
        }

        /**
         * Create a new blank CommentArray and add a page and field to it
         * @return \ProcessWire\WireArray
         */
        public function makeNew(): WireArray
        {
            $a = parent::makeNew();
            if ($this->page) $a->setPage($this->page);
            if ($this->field) $a->setField($this->field);
            return $a;
        }

        /**
         * Set the page that these comments are on
         * @param \ProcessWire\Page $page
         * @return void
         */
        public function setPage(Page $page): void
        {
            $this->page = $page;
        }

        /**
         * Get the page object for the comments
         * @return \ProcessWire\Page
         */
        public function getPage(): Page
        {
            return $this->page;
        }

        /**
         * Set the Field that these comments are on
         * @param \ProcessWire\Field $field
         * @return void
         */
        public function setField(Field $field): void
        {
            $this->field = $field;
        }

        /**
         * Get the field object of containing the comments
         * @return \ProcessWire\Field
         */
        public function getField(): Field
        {
            return $this->field;
        }

        /**
         * Get the value of a specific query string if present
         * @param string $queryName
         * @return string|null
         * @throws \ProcessWire\WireException
         */
        protected function getQueryStringValue(string $queryName): string|null
        {
            $queryName = trim($queryName);
            $value = $this->wire('input')->queryStringClean(['validNames' => [$queryName]]);
            if ($value != '') {
                $value = $this->wire('sanitizer')->text($value);
                return preg_replace('~\D~', '', $value);
            }
            return null;
        }

        /**
         * Get the comment form object
         * @return \FrontendComments\CommentForm
         * @throws \ProcessWire\WireException
         */
        public function getCommentForm(): CommentForm
        {
            include_once('CommentForm.php');
            return $this->wire(new CommentForm($this));
        }

        /** Get all comments
         * @return Comments
         * @throws WireException
         */
        public function getComments(): Comments
        {
            include_once('Comments.php');
            return $this->wire(new Comments($this));
        }

        /**
         * Render the comments only
         * @return string
         * @throws WireException
         */
        public function renderComments(): string
        {
            $comments = $this->getComments();

            return $comments->___renderComments(0, $this->commentId);
        }

        /**
         * Render the comment form only
         * @return string
         * @throws WireException
         */
        public function ___renderForm(): string
        {
            $form = $this->getCommentForm();
            return $form->___render();
        }

        /**
         * Render the comment form and the comments as an unordered list
         * @return string
         * @throws WireException
         */
        public function render(): string
        {
            if (array_key_exists('input_fc_outputorder', $this->getFrontendCommentsInputfieldConfigValues($this->field))) {
                $this->input_fc_outputorder = $this->getFrontendCommentsInputfieldConfigValues($this->field)['input_fc_outputorder'];
            }

            // check if the rating is enabled;
            $field = $this->field;
            if (!is_null($field->input_fc_voting)) {

                if ($this->wire('config')->ajax) {
                    // check if the querystring votecommentid is present for adding a vote to a comment
                    $queryString = $this->wire('input')->queryString();
                    parse_str($queryString, $queryParams);

                    if (array_key_exists('votecommentid', $queryParams)) {
                        if (array_key_exists('vote', $queryParams)) {

                            $vote = $queryParams['vote'];
                            $database = $this->wire('database');
                            $fieldTableName = 'field_' . $this->field->name;

                            $votesTableName = 'field_' . $this->field->name . '_votes';
                            $comment = $this->find('id=' . $queryParams['votecommentid'])->first();

                            // 1) check first if the user has not voted for this comment within a certain number of days

                            // check the database if user has voted for this comment
                            $statement = "SELECT id 
		                        FROM $votesTableName
                                WHERE comment_id = :comment_id
                                AND user_id = :user_id
                                AND user_agent = :user_agent
                                AND ip = :ip";

                            $query = $database->prepare($statement);

                            $query->bindValue(':comment_id', $queryParams['votecommentid'], PDO::PARAM_INT);
                            $query->bindValue(':user_id', $this->userdata['user_id'], PDO::PARAM_INT);
                            $query->bindValue(':user_agent', $this->userdata['user_agent']);
                            $query->bindValue(':ip', $this->userdata['ip']);

                            $rowsnumber = 0;
                            try {
                                $query->execute();
                                $rowsnumber = $query->rowCount();
                                $query->closeCursor();
                                $result = true;
                            } catch (Exception $e) {

                                $result = false;
                            }

                            if ($result && ($rowsnumber === 0)) {

                                //2) save data to the "votes" table first
                                $statement = "INSERT INTO $votesTableName (comment_id, user_id, user_agent, ip, vote)" .
                                    " VALUES (:comment_id, :user_id, :user_agent, :ip, :vote)";

                                $query = $database->prepare($statement);

                                $query->bindValue(':comment_id', $queryParams['votecommentid'], PDO::PARAM_INT);
                                $query->bindValue(':ip', $this->userdata['ip']);
                                $query->bindValue(':user_id', $this->userdata['user_id'], PDO::PARAM_INT);
                                $query->bindValue(':user_agent', $this->userdata['user_agent']);

                                $value = ($vote === 'up') ? 1 : -1;

                                $query->bindValue(':vote', $value, PDO::PARAM_INT);

                                $result = 0;
                                try {
                                    $query->execute();
                                    $result = $query->rowCount();
                                    $query->closeCursor();
                                } catch (Exception $e) {

                                }

                                if ($result) {

                                    // 3) increase the upvotes or downvotes in field table
                                    $commentId = $queryParams['votecommentid'];
                                    $pageId = $this->wire('page')->id;

                                    // update the field table by incrementing up or downloads
                                    $updateCol = ($value === 1) ? 'upvotes' : 'downvotes';

                                    $statement = "UPDATE $fieldTableName 
                                SET $updateCol = :$updateCol
                                WHERE  pages_id=$pageId AND id=$commentId
                                ";

                                    $query = $database->prepare($statement);

                                    $newValue = $comment->{$updateCol} + 1;
                                    $query->bindValue(':' . $updateCol, $newValue, PDO::PARAM_INT);

                                    try {
                                        $query->execute();
                                        $query->closeCursor();
                                    } catch (Exception $e) {

                                    }

                                    // finally, add the new value to the result div
                                    echo '<div id="fc-ajax-vote-result" data-votetype="' . $vote . '">' . $newValue . '</div>';
                                }
                            } else {
                                // not allowed to vote

                                // create the alert box
                                $alert = new Alert();
                                $dayslocked = $this->getFrontendCommentsInputfieldConfigValues($this->field)['input_fc_voting_lock'];
                                $timePeriod = $dayslocked . ' ' . $this->_n($this->_('day'),
                                        $this->_('days'), $dayslocked);
                                $alert->setContent(sprintf($this->_('It seems that you have rated this comment within the last %s. In this case, you may not vote again.'), $timePeriod));
                                $alert->setCSSClass('alert_warningClass');

                                echo '<div id="fc-ajax-noVote">' . $alert->___render() . '</div>';
                            }
                        }
                    }
                }
            }

            // show the form on top only if id=0 or id is different, but query string with code is present
            $form = '';
            if (($this->commentId === 0) || (($this->code))) {
                $form = $this->___renderForm();
            }

            $content = array_filter([$form, $this->renderComments()]);

            // reverse the order of comments and form depending on the config settings
            if ($this->input_fc_outputorder) {
                $content = array_reverse($content);
            }
            return implode('', $content);
        }

        /**
         * @throws \ProcessWire\WireException
         */
        public function __toString()
        {
            return $this->render();
        }

    }
