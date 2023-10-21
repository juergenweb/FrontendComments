# FrontendComments
Processwire Fieldtype to add and manage comments on your site based on the FrontendForms module.

## My intention for the re-creation of a Fieldtype that exists 
I wanted to use all the advantages of my FrontendForms module on a comment component and I wanted that the forms of the comment component looks like the same as all other forms on a site. This is why I decided to develop a own version of a comment component.

The other reason was to increase my knowledge on creating modules in ProcessWire by creating my first "FieldtypeMulti" module. 

## Is this a copy of the Comments Fieldtype by Ryan?
No, it is not. I have studied the code of Ryans module to understand how this Fieldtype works and I have adapted the structure and some parts of the code and use it in this module, but it is far away from a line for line copy. There are a lot of codes and logic behind the scenes, that are completely different from Ryans version.
But to be honest, I have taken Ryans version to get an idea which features can be included in the component (star rating, comment rating,..).

## Highlights / Features
* Only 1 line of code is necessary to render the comments + forms on the frontend
* Enable/disable star rating on per comment field base
* Enable/disable rating of comments (like/dislike) on per comment field base
* Offer commenters the sending of notification emails if a new reply has been posted
* Queuing the sending of notification emails instead of sending all at once (prevent problems of sending to much emails)
* Ajax driven form submission with server side validation
* Reply forms will be loaded only on demand, if the user clicks the link to add a reply to a comment


## Include comments inside a template
You only need to include the field inside a template to render the comments on the frontend. In this case you have several possibilities:

### Simple direct output with "echo"
If you do not want to change a parameter on the frontend, you can simply output the comments using the "echo" method and the name of the comments field. In this case the comments field name is "mycomments". Please replace it with your comment field name.

```php
echo $page->mycomments;
```
### Simple indirect output with "echo"
If you do not want to output the comments directly via "echo" method, you can put it inside a variable and output it later on.

```php
$comments = $page->mycomments;
echo $comments;
```
### Indirect output including the change of some parameter
This kind of output is necessary, if you want to change some global settings before the output.

```php
$comments = $page->mycomments;
$comments->setReplyDepth(0); // use another reply depth than in the global settings
echo $comments;
```
