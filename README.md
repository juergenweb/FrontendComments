# FrontendComments
Processwire Fieldtype/Inputfield to add and manage comments on your site based on the FrontendForms module.

## My intention for the re-creation of a Fieldtype that exists 
I wanted to use all the advantages of my FrontendForms module on a comment component and I wanted that the forms of the comment component looks like the same as all other forms on my site, so that they integrate seamlessly into my site. This is why I decided to develop my own version of a comment module.

The other reason was to increase my knowledge on creating modules in ProcessWire by creating my first "FieldtypeMulti" module. 

## Is this a copy of the Comments Fieldtype by Ryan?
No, it is not. I have studied the code of Ryan's module to understand how this Fieldtype works and I have adapted the structure and some parts of the code and use it in this module, but it is far away from a line for line copy. There are a lot of codes and logic behind the scenes, that are completely different from Ryan's version.
But to be honest, I have taken Ryan's version to get an idea which features can be included in the component (star rating, comment rating,…).

## Highlights / Features
* Only 1 line of code is necessary to render the comments + forms on the frontend
* Enable/disable star rating on per comment field base
* Enable/disable rating of comments (like/dislike) on per comment field base
* Offer commenters the receiving of notification emails if a new reply has been posted
* Queuing the sending of notification emails instead of sending all at once (preventing of performance issues by sending to many emails at once)
* Ajax driven form submission with server side validation withoud page reload
* Reply forms will only be loaded on demand by clicking on a reply symbol
* Usage of HTML email templates (provided by FrontendForms)


## Include comments inside a template
You only need to include the field inside a template to render the comments on the frontend. In this case you have several possibilities:

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

## Queuing notification emails
This module offers commenters the possibility to get notified, if a new reply has been posted to their comment or to other comments. This could lead to a very large amount of notification emails each time a comment will be posted, especially if your site has a high comment activity.

Sending a lot of emails at once will have an impact on your site performance, because the sending of mails will be triggered via LazyCron on page load and this will have an impact on the loading process of the page during the Cron task runs.

In other words, a user visits a page on your site and if LazyCron will be triggered at this moment, the module will send out fe 200 mails at once. This could take a lot of time until the last mail has been sent, and this blocks the loading process of the page. BTW if you are sending a lot of mails at once, you probably get marked as a spammer ;-)

To prevent such issues by sending a large amount of mails at once, all notification emails will be sent in smaller groups of 20 mails per batch. The LazyCron interval is set to 2 minutes. 20 mails every 2 Minutes should be a good ratio between batch size and time.

Technically, this works by writing each notification email into a row inside a custom database table. Each time LazyCron will be triggered, 20 mails in this table will be sent out, and the rows will be deleted afterwards. On the next LazyCron run, the next 20 mails will be sent and so on until there is no mail left in this database table.

Only to mention: This only happens to notification emails for commenters, not for moderators. Moderators will get the notification email about a new comment immediately, so they can react just in time (fe approve the comment or mark the comment as Spam).

## Public methods to change field parameters in directly in templates
There are a lot of configuration parameters that can be set as global values inside the details tab of the input field. Just for the case you want to use a comment field on various templates (pages), but you do not want use the same parameters on each of them, you have 2 possibilities:

1) Create a comment field for each of the templates and change the parameters in the backend configuration to your needs or
2) you only create 1 comment field and change some parameters by using some of these public methods (recommended)

In the following methods, the comment field is named "mycomments". Please change this name to the name of your comment field.

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
Comments can be displayed from newest to oldes or vice versa. With this method you set the sort order. Setting it to true means that all comments will be displayed from the newest to the oldest. False sorts the comments the other way.

```php
$comments = $page->mycomments;
$comments->setSortNewToOld(true); //true or false
echo $comments;
```

### showFormAfterComments() - whether to show the form before or after the comments
With this method you set the rendering order of the form and the comments. Setting it to true means that the form will be displayed after the comment lis. False renders the form before the comment list.

```php
$comments = $page->mycomments;
$comments->showFormAfterComments(true); //true or false
echo $comments;
```



