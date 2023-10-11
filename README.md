# FrontendComments
Processwire Fieldtype to add comments and manage comments on your site

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
