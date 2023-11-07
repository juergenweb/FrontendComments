# FrontendComments
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

Processwire Fieldtype/Inputfield to add and manage comments on your site based on the FrontendForms module.

## Requirements
* PHP>=8.0.0
* ProcessWire>=3.0.181
* GD-Library for CAPTCHA image creation
* FrontendForms
* LazyCron for sending mails

## Highlights / Features
* Only 1 line of code is necessary to render the comments + forms on the frontend
* Possibility to use one comment field with different parameters in multiple templates
* Enable/disable star rating on per comment field base
* Enable/disable rating of comments (like/dislike) on per comment field base
* Offer commenters the receiving of notification emails if a new reply has been posted
* Queuing the sending of notification emails instead of sending all at once (preventing performance issues by sending to many emails at once)
* Ajax driven form submission with server side validation without page reload
* Reply forms will only be loaded on demand by clicking on the reply symbol
* Usage of HTML email templates (provided by FrontendForms)
* Enable/disable the sending of notification emails to a commenter if status of a comment has been changed to "approved" or "SPAM"

## Table of contents
* [Is this a copy of the Comments Fieldtype by Ryan?](#is-this-a-copy-of-the-comments-fieldtype-by-ryan)
* [My intention for the re-creation of a Fieldtype that exists](#my-intention-for-the-re-creation-of-a-fieldtype-that-exists)
* [Installation and Quick-start guide](#installation-and-quick-start-guide)
* [Public methods to change field parameters in templates](#public-methods-to-change-field-parameters-in-templates)
* [Queuing notification emails](#queuing-notification-emails)
* [What happens if a comment, which has replies, will be declared as SPAM](#what-happens-if-a-comment-which-has-replies-will-be-declared-as-spam)
  

## Configurations
After you have installed the module, you have to set a default email address where the mails should be sent to. This email address can be entered manually or you can choose a ProcessWire field, which contains the email address. All other configuration options are optional.

* **`Comment moderation`** Select if you want to moderate all comments or only new comments or no comments at all. Moderation means that comments must be reviewed by a moderator, before they will be published

## Is this a copy of the Comments Fieldtype by Ryan?
No, it is not. I have studied the code of Ryan's module to understand how this Fieldtype works and I have adapted the structure and some parts of the code and use it in this module, but it is far away from a line for line copy. There are a lot of codes and logic behind the scenes, that are completely different from Ryan's version.
But to be honest, I have taken Ryan's version to get an idea which features can be included in the component (star rating, comment rating,…).

## My intention for the re-creation of a Fieldtype that exists 
I wanted to use all the advantages of my FrontendForms module on a comment component and I wanted that the forms of the comment component looks like the same as all other forms on my site, so that they integrate seamlessly into my site. This is why I decided to develop my own version of a comment module.

The other reason was to increase my knowledge on creating modules in ProcessWire by creating my first "FieldtypeMulti" module. 

## Installation and Quick-start guide
1. First of all, you need to download and install the FrontendForms module from the [module directory](https://processwire.com/modules/frontend-forms/) if you have not installed it.
2. After that, download and extract this module and put the folder inside site/modules. Be aware that the folder name must be FrontendComments and not FrontendComments-main or FrontendComments-master. GitHub adds this appendix by default. So be aware to remove it before you put the folder inside the module folder.
2. Login to your admin area and refresh all modules.
3. Find this module and install it.
4. Then you need to create your first comment field and name it fe "comments".
5. After you have created this comment field you can change some configuration settings inside the details tab of the field if needed. The only value which is required to enter, is at least one email address for a moderator.This is mandatory.
6. Now add this field to a template in the backend.
7. Add JavaScript and CSS file to the frontend (fe inside the main.php). Please copy the code for embedding the stylesheet and the JS file from the bottom of the details tab.
8. To output the comment form and the comment list on the frontend you have to add fe. "echo $page->comments" to the frontend template. Take a look on the following output methods below.

### Simple direct output with "echo"
If you do not want to change a parameter on the frontend, you can simply output the comments using the "echo" method and the name of the comments field. In this case, the comments field name is "mycomments". Please replace it with your comment field name.

```php
echo $page->mycomments;
```
### Simple indirect output with "echo"
If you do not want to output the comments directly via "echo" method, you can put it inside a variable and output it later on.

```php
$comments = $page->mycomments;
echo $comments;
```
### Indirect output, including the change of some parameter
This kind of output is necessary, if you want to change some global settings before the output.

```php
$comments = $page->mycomments;
$comments->setReplyDepth(0); // use another reply depth than in the global settings
echo $comments;
```

## Public methods to change field parameters in templates
There are a lot of configuration parameters that can be set as global values inside the details tab of the input field. 
If you are using only on one template a comment field, the backend configuration is all you need. In this case the following public methods are not relevant.
They are for the case if you want to use the same comment field on various templates, but with different settings.
This could be fe the case if you want to use a comment field on a product page and there you want to enable star rating for the product.
On another template, fe a blog page, you do not need the star rating. In this case you must overwrite the global setting for the star rating by using a public method (in this case the showStarRating() method.
That is the reason, why the public methods are there.

Of course, you can also create 2 comment fields (one for the product and one for the blog page) and make different settings on each field, but due to performance reasons, it would be better to create only one comment field and adapt the settings on each template to your needs.

Here is an example on how you can use the public methods inside a template:

```php
$comments = $page->mycomments; // get your comment field inside a template
    
// make your changes by using the public methods
$comment->setReplyDepth(1)
->setModeration(2)
->setMailTemplate('template_4.html')
->setMailSubject('Custom subject')
->setMailTitle('Custom Title')
->setSenderEmail('custom@comments.com')
->setSenderName('Custom Name')
->setModeratorEmails('moderatormail@comments.com')
->setSortNewToOld(true)
->showFormAfterComments(true)
->showStarRating(true)
->showTextareaCounter(false)
->showVoting(true)
->useCaptcha('DefaultImageCaptcha');

// output the comments
echo $comments;
```


| Method name  | Use case | 
| ------------- | ------------- |
| [setReplyDepth()](#setreplydepth---change-the-reply-depth-of-the-comments)  | change the reply depth of the comments  |
| [setModeration()](#setmoderation---change-the-moderation-status-of-comments)  | change the moderation status of comments  |
| [setMailTemplate()](#setmailtemplate---change-the-mail-template-for-the-moderator-notification-mails)  | change the template of the mails  |
| [setModeratorEmails())](#setmoderatoremails---set-the-email-addresses-for-the-moderator-notification-mails)  | set the email addresses for the moderator notification mails  |
| [setSortNewToOld()](#setsortnewtoold---change-the-sort-order-of-the-comments)  | change the sort order of the comments depending on date created  |
| [showFormAfterComments()](#showformaftercomments---whether-to-show-the-form-before-or-after-the-comments)  | whether to show the form before or after the comments  |
| [showStarRating()](#showstarrating---whether-to-show-the-star-rating-or-not)  | whether to show the star rating or not  |
| [showTextareaCounter()](#showtextareacounter---whether-to-show-a-character-counter-under-the-textarea-or-not)  | whether to show a character counter under the textarea or not  |
| [showVoting()](#showvoting---whether-to-show-voting-option-on-a-comment-or-not)  | whether to show voting option on a comment or not  |
| [useCaptcha()](#usecaptcha---whether-to-use-a-certain-type-of-captcha-or-no-captcha)  | whether to use a certain type of CAPTCHA or no CAPTCHA  |



In the following method descriptions, the comment field is named "mycomments". Please change this name to the name of your comment field.

### setReplyDepth() - change the reply depth of the comments
This method let you change the reply depth of the comment list. The value must be higher than 0. A value of 1 means a flat hierarchy with no children.

```php
$comments = $page->mycomments;
$comments->setReplyDepth(1); // value must be higher than 0
echo $comments;
```

### setModeration() - change the moderation status of comments
This method let you change how new comments will be published and approved. Possible values are 0 (no moderation, each comment will be published immediately), 1 (only comments of new commenters need approvement by a moderator) and 2 (all comments must be approved by a moderator)

```php
$comments = $page->mycomments;
$comments->setModeration(1); // 0, 1 or 2 
echo $comments;
```

### setMailTemplate() - change the mail template for the moderator notification mails
This method let you change another mail template. Please take a look at (the email template folder)[https://github.com/juergenweb/FrontendForms/tree/main/email_templates] to get the template files can be used.

```php
$comments = $page->mycomments;
$comments->setMailTemplate('template_4.html); // enter a file name form the email templates folder inside the parenthesis 
echo $comments;
```

### setModeratorEmails() - set the email addresses for the moderator notification mails
This method let you set new moderator email addresses. The notification mails about new comments will be sent to these email addresses. You can add a single email address or multiple comma separated addresses as a string

```php
$comments = $page->mycomments;
$comments->setModeratorEmails('email1@example.com, email2@example.com'); //enter one or multiple comma separated email addresses 
echo $comments;
```

### setSortNewToOld() - change the sort order of the comments
Comments can be displayed from newest to oldest or vice versa. With this method you can set the sort order. Setting it to true means that all comments will be displayed from the newest to the oldest. False sorts the comments the other way.

```php
$comments = $page->mycomments;
$comments->setSortNewToOld(true); //true or false
echo $comments;
```

### showFormAfterComments() - whether to show the form before or after the comments
With this method you set the rendering order of the form and the comments. Setting it to true means that the form will be displayed after the comment list. False renders the form before the comment list.

```php
$comments = $page->mycomments;
$comments->showFormAfterComments(true); //true or false
echo $comments;
```

### showStarRating() - whether to show the star rating or not
With this method can enable/disable star rating on the comments. Setting it to true means that the star rating will be displayed. False disable/hides the star rating.

```php
$comments = $page->mycomments;
$comments->showStarRating(true); //true or false
echo $comments;
```

### showTextareaCounter() - whether to show a character counter under the textarea or not
With this method can enable/disable the display of a character counter under the comment textarea field. Setting it to true means that the counter will be displayed. False disable/hides the counter.

```php
$comments = $page->mycomments;
$comments->showTextareaCounter(true); //true or false
echo $comments;
```

### showVoting() - whether to show voting option on a comment or not
With this method can enable/disable the display like/dislike buttons on a comment. Setting it to true means that the buttons will be displayed. False disable/hides them.

```php
$comments = $page->mycomments;
$comments->showVoting(true); //true or false
echo $comments;
```

### useCaptcha() - whether to use a certain type of CAPTCHA or no CAPTCHA
With this method can enable/disable the display of a CAPTCHA in the form. As values you can set following:
* none: no CAPTCHA will be used
* inherit: the global value as set in the FrontendForms configuration will be used or
* use one of the following CAPTCHA types: DefaultImageCaptcha, DefaultTextCaptcha, EvenCharacterTextCaptcha, ReverseTextCaptcha, SimpleMathTextCaptcha

```php
$comments = $page->mycomments;
$comments->useCaptcha('DefaultImageCaptcha); // inherit, none or one of the CAPTCHA types 
echo $comments;
```

## Queuing notification emails
This module offers commenters the possibility to get notified, if a new reply has been posted to their comment or to other comments. This could lead to a very large amount of notification emails each time a comment will be posted, especially if your site has a high comment activity.

Sending a lot of emails at once will have an impact on your site performance, because the sending of mails will be triggered via LazyCron on page load and this will have an impact on the loading process of the page during the Cron task runs.

In other words, a user visits a page on your site and if LazyCron will be triggered at this moment, the module will send out fe 200 mails at once. This could take a lot of time until the last mail has been sent, and this blocks the loading process of the page. BTW if you are sending a lot of mails at once, you probably get marked as a spammer ;-)

To prevent such issues by sending a large amount of mails at once, all notification emails will be sent in smaller groups of 20 mails per batch. The LazyCron interval is set to 2 minutes. 20 mails every 2 Minutes should be a good ratio between batch size and time.

Technically, this works by writing each notification email into a row inside a custom database table. Each time LazyCron will be triggered, 20 mails in this table will be sent out, and the rows will be deleted afterwards. On the next LazyCron run, the next 20 mails will be sent and so on until there is no mail left in this database table.

Only to mention: This only happens to notification emails for commenters, not for moderators. Moderators will get the notification email about a new comment immediately, so they can react just in time (fe approve the comment or mark the comment as Spam).

## What happens if a comment, which has replies, will be declared as SPAM
By default, all comments that are declared as SPAM are no longer visible on the frontend and will be deleted after a certain number of days if this has been set inside the moudule configuration. This is fine as long as the comment has no replies. 

If a comment has replies and you declare it as SPAM, all children (replies) are also no longer visible. This is not really desirable as you are disabling many comments at once (even comments with content that does not violate the comment guidelines). This could lead to commenters being frustrated that their comment is no longer visible.

To prevent this behaviour, all comments with replies that are declared as "SPAM" will be declared as "SPAM with replies". This means that the comment will be visible, but the comment text will be replaced with the following text: "This comment has been removed by a moderator because it does not comply with our comment guidelines."

In this case, the comment will not be deleted, like a normal SPAM comment, and all replies will still be visible in the frontend.

You don't have to worry about whether a comment has replies or not if you declare a comment as "SPAM" - it will be checked via a hook before saving. If replies are available, the status will be automatically changed to "SPAM with replies" by the hook function.

In addition, the reply link symbol and the "Like" or "Dislike" buttons will be removed from this comment (Default output -> no CSS framework).


![alt text](https://github.com/juergenweb/FrontendComments/blob/main/images/comment-banned.png?raw=true)

## Locking visitors to vote (up-vote or down-vote) for a comment
This module offers the possibility to enable up- and downvotes for comments. Inside the inputfield configuration, you can set the time range after which a user is allowed to vote again. 

The identification of a user is done by checking his IP and his browser fingerpring. It is not really a 100% safe method to identify a user, but it is ok for this case.

If a user tries to vote again within this interval, he will get a notice, that he is not allowed to vote again. 

The following image shows this scenario: Time range is set to 7 days and UIKit3 markup is selected for the output.


![alt text](https://github.com/juergenweb/FrontendComments/blob/main/images/vote-lock.png?raw=true)
