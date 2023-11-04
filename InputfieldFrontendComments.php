<?php
    declare(strict_types=1);

    /*
     * Input field for the comments
     * This class collects the input data in the backend
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: InputfieldComments.php
     * Created: 24.06.2023
     *
     * @property Module $frontendForms: The FrontendForms module object
     */

    namespace ProcessWire;

    use FrontendComments\configValues;

    class InputfieldFrontendComments extends Inputfield
    {

        use configValues;

        const pendingApproval = 0; // status waiting for approvement
        const approved = 1; // status approved
        const spam = 2; // status SPAM

        protected array $frontendFormsConfig = [];


        public function __construct()
        {
            parent::__construct();

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();

        }

        /**
         * Get the info data about this input field
         * @return array
         */
        public static function getModuleInfo(): array
        {
            return array(
                'title' => __('FrontendComments', __FILE__),
                'summary' => __('Inputfield for managing comments on a page.',
                    __FILE__),
                'href' => 'https://github.com/juergenweb/FieldtypeFrontendComments',
                'icon' => 'comment',
                'permanent' => false,
                'version' => '1.0.0',
                'requires' => [
                    'FieldtypeFrontendComments',
                    'PHP>=8.0',
                    'ProcessWire>=3'
                ],
                'author' => 'Jürgen Kern'
            );
        }

        /**
         * Array containing the texts for each status
         * @return array
         */
        public static function statusTexts(): array
        {
            return [
                __('pending Approval'),
                __('approved'),
                __('SPAM')
            ];
        }

        /**
         * Array containing the FontAwesome icons for each status
         * @return string[]
         */
        public static function statusIcons(): array
        {
            return [
                'fa-warning',
                'fa-check-circle',
                'fa-times-circle'
            ];
        }

        /**
         * Get the array containing the status colors
         * @return string[]
         */
        public static function statusColors(): array
        {
            return [
                'warning',
                'success',
                'danger'
            ];
        }

        /**
         * init() is called when the system is ready for API usage
         * @return void
         */
        public function init(): void
        {
            parent::init();
            require_once(__DIR__ . '/FieldtypeFrontendComments.module');
        }


        public function ___processInput(WireInputData $input): InputfieldFrontendComments
        {

            $n = 1;

            // names of the various form fields
            $names = array(
                'author',
                'email',
                'status',
                'parent_id',
                'text',
                'sort'
            );

            // loop through each comment
            foreach ($this->value as $comment) {

                $comment->setTrackChanges();

                // create the names of the input fields for each comment form
                $data = array();
                foreach ($names as $name) {
                    $inputName = $this->name . "_" . $name . "_" . $comment->id;
                    $value = isset($input[$inputName]) ? $input[$inputName] : '';
                    $data[$name] = $value;
                }

                // loop through each input field of the comment
                foreach ($data as $key => $value) {

                    // check if input field value has been changed
                    if (($value || $key == 'status') && $comment->$key != $value) {
                        $comment->$key = $value; // set the new value
                        $this->message(sprintf($this->_('Updated %s for comment #%d'), $key, $n));
                        $comment->trackChange($key);
                        $this->value->trackChange('update'); // set a track change value for all comments

                    }
                }
                $n++;
            }
            return $this;
        }

        /**
         * Render the input field for a comment in the backend
         * @return string
         * @throws WirePermissionException|\ProcessWire\WireException
         */
        public function ___render(): string
        {

            // create fieldset
            $fieldset = $this->modules->get('InputfieldFieldset');
            $fieldset->label = $this->_('Comments');

            // Add text that there are no comments at the moment
            if (count($this->value) == 0) {
                $noComments = $this->modules->get('InputfieldMarkup');
                $noComments->markupText($this->_('No comments at the moment.'));
                $fieldset->add($noComments);
            }
            // list of comments
            $i = 1;

            foreach ($this->value as $comment) {

                $commentfieldset = $this->modules->get('InputfieldFieldset');
                $commentfieldset->entityEncodeText = false;
                $commentfieldset->icon = self::statusIcons()[$comment->status];
                $commentfieldset->themeColor = self::statusColors()[$comment->status];
                $commentfieldset->label = strtoupper(self::statusTexts()[$comment->status]) . ': ' . $this->_('Comment') . ' #' . $i . ' ' .
                    sprintf($this->_('Posted on: %s'), $this->wire('datetime')->date($this->frontendFormsConfig['input_dateformat'], $comment->created));
                $commentfieldset->collapsed = true;

                // comment text
                $text = $this->modules->get('InputfieldTextarea');
                $text->label = $this->_('Comment');
                $text->attr('id|name', $this->name . '_text_' . $comment->id);
                $text->attr('value', $comment->text);
                $commentfieldset->add($text);

                // email
                $email = $this->modules->get('InputfieldEmail');
                $email->label = $this->_('Email');
                $email->attr('id|name', $this->name . '_email_' . $comment->id);
                $email->attr('value', $comment->email);
                $commentfieldset->add($email);

                // author
                $author = $this->modules->get('InputfieldText');
                $author->label = $this->_('Author');
                $author->attr('id|name', $this->name . '_author_' . $comment->id);
                $author->attr('value', $comment->author);
                $commentfieldset->add($author);

                // status
                $status = $this->modules->get('InputfieldSelect');
                $status->label = $this->_('Status');
                $status->attr('id|name', $this->name . '_status_' . $comment->id);
                $status->attr('value', $comment->status);
                $status->addOption(self::pendingApproval, $this->_('Pending approval'));
                $status->addOption(self::approved, $this->_('Approved'));
                $status->addOption(self::spam, $this->_('SPAM'));
                $commentfieldset->add($status);

                $fieldset->add($commentfieldset);
                $i++;
            }

            return $fieldset->render();
        }

    }
