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
                if(number === '0'){
                    changeText.css({ display: "none" });
                    submitButton.css({ display: "none" });
                } else {
                    changeText.removeAttr('style')
                    submitButton.removeAttr('style');
                }
            }
        });
        event.preventDefault();
    });


});
