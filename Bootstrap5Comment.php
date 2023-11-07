<?php

    namespace FrontendComments;

    class Bootstrap5Comment extends Comment
    {

        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct($comment, $comments);

            // Image
            $this->avatar->removeAttribute('class');
            $this->avatar->setAttribute('class', 'rounded-circle me-2');
            $this->avatar->setAttribute('width', '60');
            $this->avatar->removePrepend();
            $this->avatar->removeAppend();

            // Author
            $this->commentAuthor->setTag('span');
            $this->commentAuthor->removeAttribute('class');
            $this->commentAuthor->setAttribute('class', 'd-block font-weight-bold name text-primary fw-bold');

            // Creation date
            $this->commentCreated->setTag('span');
            $this->commentCreated->removeAttribute('class');
            $this->commentCreated->setAttribute('class', 'date text-black-50');

            // Up-votes
            $this->upvote->removePrepend();
            $this->upvote->removeAppend();
            $this->upvote->setLinkText($this->_('Like').'<i class="fa fa-thumbs-up ps-1"></i>');
            $this->upvote->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-up" class="badge bg-success mx-2">' . $this->upvotes . '</span>');

            // Down-votes
            $this->downvote->removePrepend();
            $this->downvote->removeAppend();
            $this->downvote->setLinkText($this->_('Dislike').'<i class="fa fa-thumbs-up ps-1"></i>');
            $this->downvote->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-down" class="badge bg-danger mx-2">' . $this->upvotes . '</span>');

            // Reply
            $this->replyLink->removePrepend();
            $this->replyLink->removeAppend();
            $this->replyLink->setLinkText('<i class="fa fa-reply me-1"></i>'.$this->_('Reply'));



            // Comment text
            $this->commentText->setTag('p');
            $this->commentText->removeAttribute('class');
            $this->commentText->setAttribute('class', 'comment-text');
        }

        public function ___renderCommentMarkup(bool $levelStatus): string
        {

            return  '<div class="container card">
                        <div class="d-flex justify-content-center row">
                            <div class="col-md-12">
                                <div class="d-flex flex-column comment-section">
                                    <div class="bg-white p-2">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">'
                                            .$this->___renderImage().
                                            '</div>
                                            <div class="flex-grow-1">   
                                                <div class="p-2 float-start">'.$this->___renderAuthor().'</div>
                                                <div class="p-2 float-start">'.$this->___renderRating().'</div>
                                                <div class="p-2">'.$this->___renderCreated().'</div>                                                                                                         
                                             </div> 
                                             <div flex-shrink-0>
                                                <div class="p-2 flex-grow float-end">'.$this->___renderReply($levelStatus).'</div>
                                             </div>
                                                 
                                        </div>
                                   
                                        <div class="card-body mt-2">'
                                            .$this->___renderText().
                                        '</div>
                                    </div>
                                    <div class="bg-white card-footer">
                                        <div class="d-flex flex-row float-end">
                                            <div class="p-2">'.$this->___renderVotes().'</div>
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
        }



    }
