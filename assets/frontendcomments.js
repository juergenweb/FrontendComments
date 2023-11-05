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
    element.scrollIntoView({ block: 'start',  behavior: 'smooth' });
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

/**
 * Load the reply form under the given comment via Ajax on demand
 */
function loadReplyForm() {
    document.addEventListener('click', (e) => {
        // check if a parent element is a link with class fc-comment-reply
        let link = e.target.parentElement;
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


/**
 * Add the value of the stars to the hidden inputfield,
 * change the rating text and
 * set star icons to full or empty
 */
function executeRating() {
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('rating__star')) {

            let starValue = e.target.dataset.value;
            let formId = e.target.dataset.form_id;
            // check if the clicked value is inside the hidden input field
            let hiddenStarInput = document.getElementById(formId + '-stars');
            let ratingResult = document.getElementById(formId + '-ratingtext');
            let starContainer = document.getElementById(formId + '-rating');
            let allStars = starContainer.getElementsByTagName('i');
            let resetLink = document.getElementById('resetlink-' + formId);
            let i = 0;

            if (hiddenStarInput.value === starValue) {
                starContainer.classList.remove('vote');
                starContainer.classList.remove('unvote');
                // set value back to 0
                hiddenStarInput.value = 0;
                // add readonly attribute
                hiddenStarInput.setAttribute('readonly', 'readonly');
                // change the rating result value to unvoted;
                ratingResult.innerText = ratingResult.dataset.unvoted

                // set all stars back to empty
                for (i; i < 5; i++) {
                    allStars[i].classList.remove("fa-star");
                    allStars[i].classList.add("fa-star-o");
                }
                // finally, hide the reset link
                resetLink.setAttribute('style', 'display:none');
            } else {
                starContainer.classList.remove('unvote');
                starContainer.classList.remove('vote');
                // remove readonly attribute
                hiddenStarInput.removeAttribute('readonly');
                // set value
                hiddenStarInput.value = starValue;
                //change the icon of all stars until the current value
                ratingResult.innerText = starValue + '/5';
                // set stars to full until the value is reached
                for (i; i < 5; i++) {
                    if (i < parseInt(starValue)) {
                        allStars[i].classList.remove("fa-star-o");
                        allStars[i].classList.add("fa-star");
                    } else {
                        allStars[i].classList.remove("fa-star");
                        allStars[i].classList.add("fa-star-o");
                    }
                }
                // finally, show the reset link
                resetLink.removeAttribute('style');
            }

        }
    });
}

/**
 * Reset the star rating to unvote (n/a) by clicking the reset link
 * This set the status unrated
 */
function resetRating() {
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('fc-resetlink')) {
            e.preventDefault();
            let starContainer = document.getElementById(e.target.dataset.form_id + '-rating');
            let allStars = starContainer.getElementsByTagName('i');
            let hiddenStarInput = document.getElementById(e.target.dataset.form_id + '-stars');
            let ratingResult = document.getElementById(e.target.dataset.form_id + '-ratingtext');
            // change the rating result value to unvoted;
            ratingResult.innerText = ratingResult.dataset.unvoted
            let i = 0;

            // change class from vote to unvote
            starContainer.classList.remove('vote');
            starContainer.classList.remove('unvote');
            // set value back to 0
            hiddenStarInput.value = 0;
            // add readonly attribute
            hiddenStarInput.setAttribute('readonly', 'readonly');
            // change the rating result value to unvoted;
            ratingResult.innerText = ratingResult.dataset.unvoted
            // set all stars back to empty
            for (i; i < 5; i++) {
                allStars[i].classList.remove("fa-star");
                allStars[i].classList.add("fa-star-o");
            }
            // finally, hide the reset link
            e.target.setAttribute('style', 'display:none');
        }
    });
}

function makeVote() {
    document.addEventListener('click', (e) => {
        if (e.target.parentElement.classList.contains('fc-vote-link')) {
            e.preventDefault();

            let url = e.target.parentElement.href;
            let field = e.target.parentElement.dataset.field;
            let commentid = e.target.parentElement.dataset.commentid;

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
                    if(noVoteElement){
                        elementName = field + '-' + commentid + '-novote';
                        let target = document.getElementById(elementName);
                        if(target){
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
        executeRating();
        resetRating();
    }
    makeVote();

});
