# FrontendComments
Processwire Fieldtype to add comments and manage comments on your site

## Include comments inside a template
You only need to include the field inside a template to render the comments on the frontend. In this case you have several possibilities:

### Simple output with "echo"
If you do not want to change a parameter on the frontend, you can simply output the comments using the "echo" method and the name of the comments field. In this case the comments field name is "mycomments". Please replace it with your comment field name.

´´
echo $page->mycomments;
´´
