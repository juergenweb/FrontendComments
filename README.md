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
* Reply forms will be loaded only on demand, if the user clicks the link to add a reply to a comment
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

In other words, a user visits a page on your site and if LazyCron will be triggered at this moment, the module will send out fe 200 mails at once. This could take a lot of time until the last mail has been sent, and this blocks the loading process of the page.

To prevent such performance issues by sending a large amount of mails at once, all notification emails will be sent in smaller groups of 20 mails per batch. The LazyCron interval is set to 2 minutes. 20 mails every 2 Minutes should be a good ratio between batch size and time.

Technically, this works by writing each notification email into a row inside a custom database table. Each time LazyCron will be triggered, 20 mails in this table will be sent out, and the rows will be deleted afterwards. On the next LazyCron run, the next 20 mails will be sent and so on until there is no mail left in this database table.

Only to mention: This only happens to notification emails for commenters, not for moderators. Moderators will get the notification email about a new comment immediately, so they can react just in time (fe approve the comment or mark the comment as Spam).



