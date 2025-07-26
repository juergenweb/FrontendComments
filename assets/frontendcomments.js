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

/**
 * Function to re-build the star rating again after form submission and errors
 */
function configRating() {
    // check first if star rating is enabled
    let ratingElements = document.getElementsByClassName("fcm-star-rating");

    if (ratingElements.length > 0) {
        let starRatingControl = new StarRating('.fcm-star-rating', {
            maxStars: 5
        });
        starRatingControl.rebuild();
    }
}

/**
 * Load the reply form under the given comment via Ajax on demand
 */
function loadReplyForm() {
    document.addEventListener('click', (e) => {
        // check if a parent element is a link with class fc-comment-reply
        let link = e.target;

        let defaultText;
        if (link.classList.contains('fc-comment-reply')) {
            e.preventDefault();

            // first, close all other open reply forms
            let replyforms = document.getElementsByClassName('fc-reply-form');
            for (let i = 0; i < replyforms.length; i++) {
                replyforms[i].innerHTML = '';
            }

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

            let separator = '?';
            if (url.split('?').length > 1) {
                separator = '&';
            }
            url = url + separator + 'commentid=' + commentId;

            let target = document.getElementById('reply-comment-form-' + fieldName + '-reply-' + commentId);

            // make an Ajax call to load the form from the result div
            let xhr = new XMLHttpRequest();

            // show spinner during ajax call
            let parentElement = target;

            // create spinner wrapper
            const fcspinnerwrapper = document.createElement("div");
            fcspinnerwrapper.classList.add("fc-reply-form-loader-wrapper");

            // create spinner span
            const fcspinner = document.createElement("span");
            fcspinner.classList.add("fc-reply-form-loader");
            fcspinner.setAttribute('id', 'spinner-' + commentId);
            fcspinnerwrapper.appendChild(fcspinner)

            const alertDialogBody = document.createElement("div");
            alertDialogBody.classList.add("fc-alert-body");

            // create alert dialog element
            const alertDialog = document.createElement("div");
            alertDialog.classList.add("fc-alert-dialog");
            alertDialogBody.appendChild(alertDialog);

            const trianglered = document.createElement("div");
            trianglered.classList.add("fc-triangle-up-red");
            alertDialog.appendChild(trianglered);

            const trianglewhite = document.createElement("div");
            trianglewhite.classList.add("fc-triangle-up-white");
            alertDialog.appendChild(trianglewhite);

            const rectanglePath = document.createElement("div");
            rectanglePath.classList.add("fc-rectangle-path");
            trianglewhite.appendChild(rectanglePath);

            const dotPath = document.createElement("div");
            dotPath.classList.add("fc-dot-path");
            trianglewhite.appendChild(dotPath);

            const alertTextWrapper = document.createElement("div");
            alertTextWrapper.classList.add("fc-alert-text");
            alertDialog.appendChild(alertTextWrapper);

            const alertText = document.createElement("p");
            defaultText = "Somenthing went wrong!";
            if (typeof loadingAlertText !== 'undefined') {
                defaultText = loadingAlertText;
            }
            alertText.innerHTML = defaultText;
            alertTextWrapper.appendChild(alertText);

            xhr.onreadystatechange = function () {

                if (xhr.readyState === 1) {
                    parentElement.appendChild(fcspinnerwrapper);
                }

                let result = this.responseText;

                if (xhr.readyState === 4) {

                    const parser = new DOMParser();
                    let doc = parser.parseFromString(result, "text/html");
                    let element = doc.getElementById('reply-comment-form-' + fieldName + '-reply-' + commentId);

                    if (element) {

                        // check if Ajax submission is enabled
                        let ajaxSubmission = false;
                        let replyForms = element.getElementsByTagName("form");

                        if (replyForms.length > 0) {
                            let replyForm = replyForms[0];
                            if (replyForm.hasAttribute("data-submitajax")) {
                                // data attribute exist
                                ajaxSubmission = true;
                            }
                        }

                        // load the form inside the target div
                        target.innerHTML = element.innerHTML;

                        // add Ajax event listener function once more if AJAX submission is enabled
                        if (ajaxSubmission) {
                            subAjax('reply-form-' + commentId);
                        }

                        let ratingStars = [...document.getElementsByClassName("fcm-star-rating")];
                        if (ratingStars.length > 0) {
                            configRating();
                        }
                    } else {
                        // remove spinner element first
                        parentElement.removeChild(fcspinnerwrapper);
                        // add text that the element was not found instead
                        parentElement.appendChild(alertDialogBody);
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
        if (e.target.classList.contains('fc-cancel-button')) {
            e.preventDefault();
            document.getElementById('reply-comment-form-' + e.target.dataset.field + '-reply-' + e.target.dataset.id).innerHTML = '';
            scrollSmoothTo('comment-' + e.target.dataset.id);
        }
    });
}


/*
 * Fade out the no vote alert and remove the content completely afterwards
 */
function fadeOutAlert(field, commentid) {

    let elementName = field + '-' + commentid + '-novote';
    let fade = document.getElementById(elementName);

    let intervalID = setInterval(function () {

        if (!fade.style.opacity) {
            fade.style.opacity = "1";
        }

        if (fade.style.opacity > 0) {
            fade.style.opacity -= "0.1";
        } else {
            clearInterval(intervalID);
            fade.innerHTML = '';
            fade.style.opacity = "1";
        }

    }, 200);
}

/**
 * Function for the up- and downvotes
 */
function makeVote() {

    let voteElements = document.getElementsByClassName("fc-votebadge");
    if (voteElements.length > 0) {

        for (let i = 0; i < voteElements.length; i++) {

            let voteElement = voteElements[i];


            voteElement.addEventListener('click', (e) => {
                e.preventDefault();

                let voteLink = e.target.parentElement;
                let url = voteLink.href;
                let field = voteLink.dataset.field;
                let commentid = voteLink.dataset.commentid;

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
                            // update the number besides the upvotes or downvotes
                            elementName = field + '-' + commentid + '-votebadge-' + votetype;
                            let target = document.getElementById(elementName);

                            // get the arrow type depending on the vote
                            let arrow;
                            if(votetype === "down"){
                                arrow = "↓ ";
                            } else {
                                arrow = "↑ ";
                            }
                            // set the new vote value inside the span element
                            target.innerHTML = arrow + voteresult;
                        }
                        if (noVoteElement) {
                            elementName = field + '-' + commentid + '-novote';
                            let target = document.getElementById(elementName);
                            if (target) {
                                // add the alert box to the div element
                                target.innerHTML = noVoteElement.innerHTML;
                                // fade the alert out after a certain time
                                setTimeout(function () {
                                    fadeOutAlert(field, commentid);
                                }, 6000);

                            }
                        }
                    }

                }

                xhr.open("GET", url);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.send();

            });
        }
    }
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
