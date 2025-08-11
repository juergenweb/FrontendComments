/*
JS file needed for the FrontendCommentsManager module on the backend

Created by Jürgen K.
https://github.com/juergenweb
File name: frontendcommentsmanager.js
Created: 13.06.2025

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
 * Check if a specific query parameter exists in the current URL
 * @param field
 * @returns {boolean}
 */
function checkQueryParam(field) {
    const url = window.location.href;
    return url.indexOf('?' + field + '=') !== -1 || url.indexOf('&' + field + '=') !== -1;
}

/**
 * Modify a specific URL query parameter with a new value
 * @param uri
 * @param key
 * @param value
 * @returns {*|string}
 */
function modifyQueryStringParam(uri, key, value) {
    let re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    let separator = uri.indexOf('?') !== -1 ? "&" : "?";
    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    } else {
        return uri + separator + key + "=" + value;
    }
}

/**
 * Remove a specific parameter (querystring) from a URL
 * @param url
 * @param parameter
 * @returns {*}
 * @constructor
 */
function removeParameterFromUrl(url, parameter) {
    return url
        .replace(new RegExp('[?&]' + parameter + '=[^&#]*(#.*)?$'), '$1')
        .replace(new RegExp('([?&])' + parameter + '=[^&]*&'), '$1');
}

/**
 * Get the path without query strings at the end
 * @param url
 * @returns {*}
 */
function getPathFromUrl(url) {
    return url.split("?")[0];
}

/**
 * Warn the user if there are unsaved changes
 */
function warnUserUnsavedChanges() {

    let formSubmitting = false;
    const notifyUser = (e) => {
        if (formSubmitting) {
            return;
        } else {
            let changes = false;
            // check if there are changes
            let statusChanges = document.querySelector('[data-status="true"]');
            console.log(statusChanges.length);
            if(statusChanges){
                    changes = true;
                } else {
                let feedbackChange = document.querySelector('[data-feedback="true"]');
                if(feedbackChange){
                    changes = true;
                } else {
                    changes = false;
                }
            }

            if(changes){
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                (e || window.event).returnValue = message; // For Gecko + IE
                return message; // For Webkit, Safari, Chrome, etc.
            }

        }


    };

    window.addEventListener("beforeunload", notifyUser);

    const setFormSubmitting = () => {
        formSubmitting = true;
    };

    document.querySelector('#fcmm-comment-form').onsubmit = setFormSubmitting;
}

/**
 * Function to remove line breaks
 * @param str
 * @returns {string}
 */
function remove_linebreaks_ss(str) {
    let newstr = "";

    // Looop and traverse string
    for (let i = 0; i < str.length; i++)
        if (!(str[i] == "\n" || str[i] == "\r"))
            newstr += str[i];
    return newstr
}

/**
 * Check if feedback value has been changed
 */
function checkFeedbackChange() {
    for (let instance in CKEDITOR.instances) {

        let editor = CKEDITOR.instances[instance];
        let defaultValue = editor.getData();
        let textareaID = editor.name;
        let commentID = editor.element.getAttribute('data-comment');
        let textarea = document.getElementById(textareaID);

        // check for changes
        CKEDITOR.instances[instance].on('change', function () {

            let currentValue = CKEDITOR.instances[instance].getData();
            textarea.innerText = currentValue;

            defaultValue = remove_linebreaks_ss(defaultValue);
            currentValue = remove_linebreaks_ss(currentValue);

            let changeElement = document.getElementById('changed-' + commentID);
            let hiddenChange = document.getElementById('changes-' + commentID);

            if (defaultValue != currentValue) {
                changeElement.dataset.feedback = true;
                changeElement.style.display = "block";
                hiddenChange.value = true;
            } else {
                changeElement.dataset.feedback = false;
                if (changeElement.dataset["status"] == "false") {
                    changeElement.style.display = "none";
                    hiddenChange.value = false;
                }
            }
        });
    }
}

/**
 * Check if status value has been changed
 */
function checkStatusChange() {

    // get all status elements
    let statusInputs = document.getElementsByClassName('fcmm-status');
    for (let i = 0; i < statusInputs.length; i++) {

        let commentID = statusInputs[i].dataset["comment"];
        let changeElement = document.getElementById('changed-' + commentID);
        let hiddenChange = document.getElementById('changes-' + commentID);

        let defaultValue = statusInputs[i].value;
        statusInputs[i].addEventListener("change", function () {
            if (defaultValue != statusInputs[i].value) {
                changeElement.dataset.status = true;
                changeElement.style.display = "block";
                hiddenChange.value = true;
            } else {
                changeElement.dataset.status = false;
                hiddenChange.value = false;
                if (changeElement.dataset["feedback"] == "false") {
                    changeElement.style.display = "none";
                }
            }
        });
    }
}

/**
 * Run this function after the complete body has been loaded
 */
docReady(function () {

    checkStatusChange();
    checkFeedbackChange();
    // check if values have been changed and warn the user
    warnUserUnsavedChanges();

    let currentURL = window.location.href;
    let cleanedUrl = getPathFromUrl(currentURL);
    let urlParts = cleanedUrl.split("/");
    urlParts = urlParts.filter(n => n)
    let numberOfParts = urlParts.length;
    if (numberOfParts === 8) {
        // pagination is active
        let lastItem = urlParts.slice(-1).pop();
        currentURL = currentURL.replace(lastItem, '');
    }

    let filterForm = document.getElementById('fcmm-comment-list');
    if (filterForm) {

        // find all form elements
        let formElements = filterForm.querySelectorAll("[data-filter='true']");
        if (formElements) {

            let hasQueryStrings = currentURL.includes('?');
            let querySign = "?";

            // add eventlistener to each input element of the form
            for (let i = 0; i < formElements.length; i++) {
                formElements[i].addEventListener("change", function () {
                    let value = formElements[i].value;
                    let name = formElements[i].dataset.filterName;
                    if (hasQueryStrings) {
                        querySign = "&";
                    }
                    // add query string only if it does not exist
                    if (!checkQueryParam(name)) {
                        let queryString = querySign + name + "=" + value;
                        //redirect
                        window.location.replace(currentURL + queryString);
                    } else {
                        // check if value is not the same and replace it
                        let modifiedCurrentURL = modifyQueryStringParam(currentURL, name, value)
                        //redirect
                        window.location.replace(modifiedCurrentURL);
                    }
                });
            }
        }
    }

    let tags = document.getElementById('fcm-filter-tags');
    if (tags) {
        let tagElements = tags.querySelectorAll("li");

        for (let i = 0; i < tagElements.length; i++) {
            tagElements[i].addEventListener("click", function () {

                let filterName = tagElements[i].dataset.type;
                let url = removeParameterFromUrl(currentURL, filterName)
                //redirect
                window.location.replace(url);
            });
        }
    }



});