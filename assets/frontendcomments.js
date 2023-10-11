/*
JS file needed for the FrontendComments module on the frontend

Created by Jürgen K.
https://github.com/juergenweb 
File name: frontendcomments.js
Created: 20.07.2023 
*/

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
 * Cancel the reply process and remove the form from the wrapper div under the comment by clicking
 * on the cancel link
 */
function removeForm() {
    document.addEventListener('click', (e) => {
        // check if a parent element is a link with class fc-alert-close
        if (e.target.classList.contains('fc-cancel-reply')) {
            e.preventDefault();
            document.getElementById('reply-comment-form-' + e.target.dataset.field + '-reply-' + e.target.dataset.id).innerHTML = '';
        }
    });
}


function loadForm(formid = null, commentid = null)
{
    let target = null;
    let url = '';

    if(commentid === null){
        // it is not a reply
        target = document.getElementById('frontend_comments-form-wrapper');
    } else {
        // it is a reply
        target = document.getElementsByClassName('reply-form-wrapper');
    }
    // make AJAX request to load the form inside the wrapper
    if(formid){
        url = document.location.href.split('?')[0] + '?formid='+ formid;
    } else {
        url = document.location.href.split('?')[0];
    }



        let xhr = new XMLHttpRequest();

        xhr.onload = function () {
            let result = this.responseText;

            const parser = new DOMParser();
            let doc = parser.parseFromString(result, "text/html");
            let content = doc.getElementById('result').innerHTML;

            if (xhr.readyState === 4) {
                // load the form inside the target div
                target.innerHTML = content;
            }
        }
        xhr.open("GET", url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send();

}

function validateForm()
{
    // check if fc-comment-button button was clicked
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('fc-comment-button')) {
            e.preventDefault();
            console.log('submitted');
            // get the action attribute value
            let form = document.getElementById(e.target.dataset.formid);
            let url = form.getAttribute('action');
            let target = document.getElementById('frontend_comments-form-wrapper');

            // make a POST request
            let xhr = new XMLHttpRequest();

            xhr.onload = function () {
                let result = this.responseText;
                const parser = new DOMParser();
                let doc = parser.parseFromString(result, "text/html");
                let content = doc.getElementById('result').innerHTML;

                if (xhr.readyState === 4) {
                    // load the form inside the target div
                    target.innerHTML = content;
                }
            }
            xhr.open("POST", url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send();
        }
    });
}

/**
 * Function to load the reply form via Ajax into the form wrapper container after the comment
 * Close all other open instances of forms before
 */
function addForm() {

    document.addEventListener('click', (e) => {

        if ((e.target.parentElement.classList.contains('fc-comment-reply')) || (e.target.classList.contains('fc-comment-button'))){
            e.preventDefault();

            let form_id = e.target.dataset.formid;

            //let comment_id = replylink.dataset.id;
            let comment_id = e.target.parentElement.dataset.id;
            // close all open forms first
            let formwrappers = document.getElementsByClassName('reply-form-wrapper');

            for (let i = 0; i < formwrappers.length; ++i){
                if(formwrappers[i].dataset.id !== comment_id){
                    formwrappers[i].innerHTML = ''; // remove the content
                }
            }

            let url = document.location.href.split('?')[0] + '?formid='+ formid + '&commentid=' + comment_id;
            let xhr = new XMLHttpRequest();
            xhr.onload = function () {
                let result = this.responseText;
                const parser = new DOMParser();
                let doc = parser.parseFromString(result, "text/html");
                let content = doc.getElementById('result').innerHTML;

                if (xhr.readyState === 4) {
                    // load the reply form inside the div
                    document.getElementById('reply-comment-form-' + e.target.parentElement.dataset.field + '-reply-' + comment_id).innerHTML = content;
                }
            }
            xhr.open("GET", url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send();
        }
    });
}

/**
 * Add the value of the stars to the hidden inputfield,
 * change the rating text and
 * set star icons to full or empty
 */
function executeRating(){
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

            if(hiddenStarInput.value === starValue){
                starContainer.classList.remove('vote');
                starContainer.classList.remove('unvote');
                // set value back to 0
                hiddenStarInput.value = 0;
                // add readonly attribute
                hiddenStarInput.setAttribute('readonly', 'readonly');
                // change the rating result value to unvoted;
                ratingResult.innerText = ratingResult.dataset.unvoted

                // set all stars back to empty
                for(i; i < 5; i++){
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
                for(i; i < 5; i++){
                    if(i <parseInt(starValue)){
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
 * Reset the star rating to unvote by clicking the reset link
 */
function resetRating(){
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

/**
 * Function to insert an element after a specific node
 * @param newNode
 * @param existingNode
 */
function insertAfter(newNode, existingNode) {
    existingNode.parentNode.insertBefore(newNode, existingNode.nextSibling);
}


docReady(function () {

    // load form on page load via Ajax
    loadForm();
    validateForm();

    // remove the reply form by clicking on the cancel link in the top right corner of the form
    removeForm();
    // load the reply form by clicking the reply link via Ajax into the form container
    //addForm();

    // rating stars
    let ratingStars = [...document.getElementsByClassName("rating__star")];

    if (ratingStars) {
        executeRating();
        resetRating();
    }

});