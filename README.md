# FrontendComments
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

![alt text](https://github.com/juergenweb/FrontendComments/blob/main/images/frameworks.jpg?raw=true)

Processwire Fieldtype/Inputfield to add and manage comments on your site based on the FrontendForms module.

This module is early Beta stage - so please use it with care!

## Requirements
* PHP>=8.0.0
* ProcessWire>=3.0.181
* GD-Library installed for CAPTCHA image creation
* FrontendForms>=2.2.34
* LazyCron enabled for sending mails

## Highlights / Features
* Easy integration: Only 1 line of code inside a template is necessary to render the comments + form on the frontend
* Possibility to use multiple comment fields in one template (if necessary)
* Easy to overwrite global module settings inside the template (possibility to use one comment field with different configuration settings in multiple templates)
* Enable/disable star rating
* Enable/disable rating of comments (like/dislike)
* Add additional website field to the comment form if needed
* Offer commenters the receiving of notification emails if a new reply has been posted
* Queuing the sending of notification emails instead of sending all at once (preventing performance issues by sending to many emails at once)
* Reply forms will only be loaded via AJAX on demand (by clicking on the reply link) -> faster loading time
* Option to send HTML email templates (provided by FrontendForms)
* Enable/disable the sending of notification emails to a commenter if status of a comment has been changed to "approved" or "SPAM" by a moderator
* Moderators can write a feedback directly to a comment (fe to react to positive or negative comments)
* Adding a link to an internal or external page containing the community guidlines
* Changing the status of a comment via remote link to "approved" or "spam"
* No dependencies (except FrontendForms)
* Support for UiKit 3, Pico 2 and Bootstrap 5 CSS framework out of the box

## Table of contents
* [Is this a copy of the Comments Fieldtype by Ryan?](#is-this-a-copy-of-the-comments-fieldtype-by-ryan)
* [Installation and Quick-start guide](#installation-and-quick-start-guide)
* [Queuing notification emails](#queuing-notification-emails)
* [What happens if a comment, which has replies, will be declared as SPAM](#what-happens-if-a-comment-which-has-replies-will-be-declared-as-spam)
* [Hooking to change markup](#hooking-to-change-markup)
  

## Configurations
After you have installed the module, you need to set at least 1 moderator email address to which the mails should be sent when a new comment is posted. You can enter this email address manually or you can select a user with editing rights. All other configuration options are optional.

The information about the individual configuration settings can be found right next to the corresponding configuration field.

## Is this a copy of the Comments Fieldtype by Ryan?
No, it's not. This module runs on its own codebase and has not been copied from Ryans module. I just looked at the features he offers in his module to get an idea of what might be useful or not. This module offers many more configuration settings and features than the original module, so it is not a copy.

## Installation and Quick-start guide
1. First of all, you need to download and install the FrontendForms module from the [module directory](https://processwire.com/modules/frontend-forms/) if you have not installed it.
2. After that, download and extract this module and put the folder inside site/modules. Be aware that the folder name must be FrontendComments and not FrontendComments-main or FrontendComments-master. GitHub adds this appendix by default. So be aware to remove it before you put the folder inside the module folder.
2. Login to your admin area and refresh all modules.
3. Find this module and install it.
4. Then you need to create your first comment field and name it fe "comments".
5. Once you've created this comment field, you can change some configuration settings in the "Details" tab of the field, if necessary. The only value that needs to be entered is the email address of at least one moderator. This is mandatory.
6. As the next step add this field to a template.
7. JavaScript and CSS file for the frontend will be added automatically - you don't have to take care about it.
8. To output the comment form and the comment list on the frontend you have to add fe. "*echo $page->comments->render()*" to the frontend template. Take a look on the following output methods below.

### Simple direct output with "echo"
If you want to use the global settings you only need to use the render() method. In this case, the comments field name is "mycomments". Please replace it with your comment field name.

```php
echo $page->mycomments->render();
```
### Simple indirect output with "echo"
If you do not want to output the comments directly via "echo" method, you can put it inside a variable and output it later on.

```php
$comments = $page->mycomments;
...
...
echo $comments->render();
```
### Indirect output, including the change of some parameters

This type of output is necessary if you want to override some global settings before you output the markup.

This can be the case if you want to use a comment field in different templates with different settings


```php
$comments = $page->mycomments;
$comments->setReplyDepth(0); // use another reply depth than in the global field settings
echo $comments->render();
```

A lot of information on how to override settings can be found on the configuration page of a comment field. The example *setReplyDepth()* shown above is just one example of many.

Here is an example with a lot of overwritten configuration settings:

```php
$comments = $page->mycomments; // get your comment field inside a template
    
// make your changes by using the public methods
$comments->setModeration(2);
$comments->setLoginRequired(true);
$comments->showWebsiteField(false);
$comments->setEmailTemplate("template_4.html");
$comments->setModerationEmail("email1@mydomain.com,email2@mydomain.com");
$comments->setReplyNotification(2);
$comments->setStatusChangeNotification(["1","2"]);
$comments->setFormHeadlineTag("h1");
$comments->showStarRating(2);
$comments->disableTooltip(false);
$comments->hideCharacterCounter(true);
$comments->setPrivacyType(1);
$comments->setListHeadlineTag("h1");
$comments->setReplyDepth(1);
$comments->sortNewestToOldest(true);
$comments->showVoting(false);
$comments->setDateFormat(0);
$comments->setCaptchaType("none");
$comments->setPaginationNumber(10);
$comments->setPaginationAlignment("right");
$comments->setSenderEmailAddress("myemailaddress@example.com");
$comments->setSenderName("ProcessWire");
$comments->setListHeadlineText("My comment list headline");
$comments->setFormHeadlineText("My form headline");
$comments->showFormAfterComments(false);

// output the comments
echo $comments->render();
```

Each of these above methods is explained alongside the corresponding global settings field in the module configuration, so I won't go much more into detail.

## Queuing notification emails
This module offers commenters the option to be notified whenever a new reply to their comments or other comments has been posted. This can result in a very large number of notification emails every time a comment is posted, especially if your website has high comment activity.

Sending a lot of emails at once affects server performance. Since the sending process is triggered when a page is loaded via LazyCron, this can increase the load time of a page.

To prevent such issues by sending a large amount of mails at once, all notification emails will be sent in smaller groups of 20 mails per batch. The LazyCron interval is set to 2 minutes. 20 mails every 2 Minutes should be a good ratio between batch size and time.

Only to mention: This only happens to notification emails for commenters, not for moderators. Moderators will get the notification email about a new comment immediately, so they can react just in time (fe approve the comment or mark the comment as Spam).

## Special case: What happens if a comment, which has replies, will be declared as SPAM
By default, all comments that are declared as SPAM are no longer visible in the frontend and will be deleted after a certain number of days if this has been set in the module configuration. That's fine, as long as the comment doesn't contain any answers.

If a comment contains replies and is declared as SPAM later on, then all subordinates (replies) would no longer be visible. This is not really desirable, as many comments would suddenly no longer be visible (even comments with content that does not violate the comment guidelines). This can cause commenters to feel frustrated because their comment has disappeared from the page.

To prevent this sceanario, comments marked as SPAM to which replies have already been written are declared as "SPAM with replies". This means that the comment will remain visible, but the comment text will be replaced with the following text: "Sorry, this post has been removed by moderators for violating community guidelines."

In this case, the comment will not be deleted like a normal SPAM comment and all replies to this comment will still remain visible in the frontend.

You don't have to worry about whether a comment already has replies or not if you declare a comment as "SPAM" - this will be automatically checked before saving. If answers already exist, then the status "SPAM" will be automatically changed to "SPAM with replies"


![alt text](https://github.com/juergenweb/FrontendComments/blob/main/images/violation.png?raw=true)

## Locking visitors to vote (up-vote or down-vote) for a comment
This module offers the possibility to activate upvotes and downvotes for comments. In the input field configuration, you can specify the period after which a user is allowed to vote again.

The identification of a user is done by checking their IP and browser fingerprint. It's not really a 100% safe way to identify a user, but for this case, it's fine.

If a user wishes to vote again within this period, he will receive a notification that he is not allowed to vote again, because he has already voted within the given period (take a look at the image below, where UiKit3 markup is used to render comments).

![alt text](https://github.com/juergenweb/FrontendComments/blob/main/images/vote-blocked.png?raw=true)

## Hooking to change markup

If you want to change the markup of some elements globally, you can use Hooks to add or remove for example classes of elements. To change the markup globally, you need to add the Hooks to your *site/init.php* file.

Example to add a custom CSS class to the email inputfield of the form (add this code to the site/init.php file):

```php
$wire->addHookAfter('FrontendCommentForm::getEmailField', function(HookEvent $event) {
        $emailField = $event->return;
        $emailField->setAttribute('class', 'myclass');
        $event->return = $emailField;
    });
```

Take a look at the FrontendCommentForm.php class file to see which methods are hookable. In this case every element of the form has its own hookable function. Here are some examples of the hookable function you will find there: getAuthorField(), getWebsiteField(), getCommentField(),....
