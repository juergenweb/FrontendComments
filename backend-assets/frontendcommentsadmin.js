$(document).ready(function () {

    $("#fc-filternumber, #filterstatus, #fc-commentsfield").on('change', function ajaxWorks(event) {
        let form = $(this);
        $.ajax({
            type: form.attr('method'),
            url: form.attr('action'),
            data: $('#InputfieldForm3').serialize(),
            success: function (data) {
                let ajaxTable = $(data).find('#ajax-comments-table');
                let content = ajaxTable.html();
                let number = ajaxTable.attr('data-number');
                let target = $('#comments-table');
                target.html(content);
                let numberWrapper = $('#fc-totalnumber');
                numberWrapper.text(number);
                let changeText = $('#changetext');
                let submitButton = $('button[name="submit"]');
                if (number === '0') {
                    changeText.css({display: "none"});
                    submitButton.css({display: "none"});
                } else {
                    changeText.removeAttr('style')
                    submitButton.removeAttr('style');
                }
            }
        });
        event.preventDefault();
    });


// reload the page after modal has been closed to get all updated values
    $('button').on('pw-modal-closed', function () {
        location.reload();
    });


    function loadDetail(url, commentid) {
        let wrapperId = 'fc-comment-' + commentid;
        console.log(wrapperId);

        const xhttp = new XMLHttpRequest();
        xhttp.onload = function() {
            console.log(this.responseText);
            document.getElementById(wrapperId).innerHTML = this.responseText;
        }
        xhttp.open("GET", url, true);
        xhttp.send();
    }

    let editButtons = document.getElementsByClassName('fc-load-detail');
    for (var i = 0; i < editButtons.length; i++) {
        editButtons[i].addEventListener('click', function (event) {
            let commentId = this.dataset.commentid;
            let fieldId = this.dataset.fieldid;
            let pageId = this.dataset.pageid;
            let url  = this.dataset.href;
            let ajaxUrl = url + '?commentid=' + commentId + '&fieldid=' + fieldId + '&pageid=' + pageId;
            loadDetail(ajaxUrl, commentId);

        })
    }
});
