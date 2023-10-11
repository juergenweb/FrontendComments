<?php
declare(strict_types=1);

/*
 * Customizing the form and its fields
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb 
 * File name: customizingForm.php
 * Created: 23.07.2023 
 */

$comments = $page->nameOfYourCommentField;
$form = $comments->getCommentForm();// get the form object
$form->getFormElementByName('email')->setLabel('My custom email label'); // grab the email field and
echo $form->render();

