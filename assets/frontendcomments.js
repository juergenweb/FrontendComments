/*
JS file needed for the FrontendComments module on the frontend

Created by Jürgen K.
https://github.com/juergenweb 
File name: frontendcomments.js
Created: 20.07.2023 
*/

/**
 * Function to scroll to an element like an internal anchor, but without changing the url
 * @param elementId
 */
function scrollSmoothTo(elementId) {
    let element = document.getElementById(elementId);
    element.scrollIntoView({block: 'start', behavior: 'smooth'});
}

/**
 * Function to check if the document is completely loaded
 * @param fn
 */
function docReady(fn) {
    // see if DOM is already available
    if (document.readyState === "complete" || document.readyState === "interactive") {
        // call on next available tick
        setTimeout(fn, 1);
    } else {
        document.addEventListener("DOMContentLoaded", fn);
    }
}

function configRating() {
    let starRatingControl = new StarRating('.star-rating', {
        maxStars: 5,
        clearable: true,
    });
    starRatingControl.rebuild();
}

/**
 * Load the reply form under the given comment via Ajax on demand
 */
function loadReplyForm() {
    document.addEventListener('click', (e) => {
        // check if a parent element is a link with class fc-comment-reply
        let link = e.target;
        if (link.classList.contains('fc-comment-reply')) {
            e.preventDefault();

            // grab some values from the element
            let commentId = link.dataset.id;
            let fieldName = link.dataset.field;
            let url = document.location.href;

            scrollSmoothTo('reply-comment-form-' + fieldName + '-reply-' + commentId);

            // check if a hashtag is present and remove it, because a hashtag leads to blocking the form load
            if (document.location.hash) {
                const urlObj = new URL(url);
                urlObj.hash = "";
                url = urlObj.href
            }
            url = url.split('?')[0] + '?commentid=' + commentId;

            let target = document.getElementById('reply-comment-form-' + fieldName + '-reply-' + commentId);

            // make an Ajax call to load the form from the result div
            let xhr = new XMLHttpRequest();

            xhr.onload = function () {

                let result = this.responseText;

                const parser = new DOMParser();
                let doc = parser.parseFromString(result, "text/html");
                let content = doc.getElementById('reply-comment-form-' + fieldName + '-reply-' + commentId).innerHTML;

                if (xhr.readyState === 4) {

                    // load the form inside the target div
                    target.innerHTML = content;

                    // add Ajax event listener function once more
                    subAjax('reply-form-' + commentId);

                    let ratingStars = [...document.getElementsByClassName("star-rating")];
                    if (ratingStars.length > 0) {
                        configRating();
                    }

                }

            }

            xhr.open("GET", url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send();

        }

    });

}

/**
 * Cancel the reply process and remove the form from the wrapper div under the comment by clicking
 * on the cancel link
 */
function cancelReply() {
    document.addEventListener('click', (e) => {

        // check if a parent element is a link with class fc-alert-close
        if (e.target.classList.contains('fc-cancel-link')) {
            e.preventDefault();
            document.getElementById('reply-comment-form-' + e.target.dataset.field + '-reply-' + e.target.dataset.id).innerHTML = '';
            scrollSmoothTo('comment-' + e.target.dataset.id);
        }
    });
}

function makeVote() {
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('fc-vote-link')) {
            e.preventDefault();

            let url = e.target.href;
            let field = e.target.dataset.field;
            let commentid = e.target.dataset.commentid;

            // make an Ajax call to save the vote
            let xhr = new XMLHttpRequest();

            xhr.onload = function () {

                let result = this.responseText;
                let voteresult = '';
                let votetype = '';
                let elementName = '';

                const parser = new DOMParser();
                let doc = parser.parseFromString(result, "text/html");

                let voteElement = doc.getElementById('fc-ajax-vote-result');
                let noVoteElement = doc.getElementById('fc-ajax-noVote');

                if (voteElement) {
                    voteresult = voteElement.innerText;
                    votetype = voteElement.dataset.votetype;
                }

                if (xhr.readyState === 4) {

                    if (voteresult) {
                        // update the number beside the upvotes or downvotes
                        elementName = field + '-' + commentid + '-votebadge-' + votetype;
                        let target = document.getElementById(elementName);
                        // set the new vote value inside the span element
                        target.innerHTML = voteresult;
                    }
                    if (noVoteElement) {
                        elementName = field + '-' + commentid + '-novote';
                        let target = document.getElementById(elementName);
                        if (target) {
                            // add the alert box to the div element
                            target.innerHTML = noVoteElement.innerHTML;
                        }
                    }
                }

            }

            xhr.open("GET", url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send();
        }
    });
}

// run after the body has been loaded
docReady(function () {

    // remove the reply form by clicking on the cancel link in the top right corner of the form
    cancelReply();

    // load the reply form by clicking the reply link via Ajax into the form container
    loadReplyForm();

    // rating stars
    let ratingStars = [...document.getElementsByClassName("rating__star")];

    if (ratingStars) {
        configRating();
    }
    makeVote();

});
