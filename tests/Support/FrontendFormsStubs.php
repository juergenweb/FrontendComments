<?php

/**
 * Minimal stand-ins for the external FrontendForms library classes that FrontendComments builds
 * on. Tag::setContent()/getContent()/render() intentionally reproduce the REAL, confirmed
 * behavior read directly from FrontendForms' own source (Formelements/Tag.php, main branch of
 * https://github.com/juergenweb/FrontendForms as of this engagement): content is stored and
 * rendered completely unescaped, while attribute values ARE escaped via htmlspecialchars(). That
 * asymmetry is exactly the vulnerability class the FrontendComments-side fixes guard against, so
 * the stub must preserve it faithfully rather than "helpfully" escaping content itself - a stub
 * that escaped content on its own would make the XSS regression tests pass even without the fix.
 */

declare(strict_types=1);

namespace FrontendForms {

    use ProcessWire\Wire;

    class Tag extends Wire
    {
        protected string $tag = 'div';
        protected string $content = '';
        protected array $attributes = [];

        public function setTag(string $tag): static
        {
            $this->tag = $tag;
            return $this;
        }

        public function getTag(): string
        {
            return $this->tag;
        }

        /** Mirrors the real Tag::setContent() - stores the value completely as-is, no escaping */
        public function setContent(string $content): static
        {
            $this->content = $content;
            return $this;
        }

        /** Mirrors the real Tag::getContent() - returns exactly what was stored */
        public function getContent(): string
        {
            return $this->content;
        }

        public function setAttribute(string $name, $value): static
        {
            $this->attributes[$name] = $value;
            return $this;
        }

        public function removeAttribute(string $name): static
        {
            unset($this->attributes[$name]);
            return $this;
        }

        protected function attributesToString(): string
        {
            $out = '';
            foreach ($this->attributes as $name => $value) {
                $out .= ' ' . $name . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
            }
            return $out;
        }

        /** Mirrors the real Tag::renderNonSelfclosingTag() - content is emitted completely raw */
        public function render(): string
        {
            return '<' . $this->tag . $this->attributesToString() . '>' . $this->content . '</' . $this->tag . '>';
        }
    }

    class TextElements extends Tag
    {
        public function __construct()
        {
            $this->setTag('span');
        }

        /**
         * Real FrontendForms\TextElements::setText() - used by FrontendCommentPagination for its
         * "Showing x to y of z comments" text, which is always a plain, already-safe string (built
         * from sprintf() with no user input), so html-escaping it here (as the real, text-oriented
         * "setText" presumably does, unlike the raw setContent() above) makes no observable
         * difference for anything this suite exercises.
         */
        public function setText(string $text): static
        {
            return $this->setContent(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        }
    }

    /** FrontendCommentForm extends this - none of its own inherited behavior is exercised by
     *  the methods under test, so it only needs to exist and offer getID(). */
    class Form extends Tag
    {
        public function getID(): string
        {
            return 'testform';
        }
    }

    /**
     * Minimal stand-in for FrontendForms\Select - just enough surface for
     * FrontendCommentForm::___getStarRating() to run under test. Label/option/wrapper/rule setup
     * calls are all harmless no-ops here (this suite only cares about the resulting "data-options"
     * attribute, a regression test for a real bug), while setAttribute()/render() are inherited
     * from Tag to preserve its real, confirmed htmlspecialchars(ENT_QUOTES) attribute escaping -
     * exactly the behavior that made the hand-written "&quot;"-based data-options string double-
     * encode into unparsable JSON.
     */
    class Select extends Tag
    {
        public function __construct(string $name = '')
        {
        }

        public function useInputWrapper(bool $use): static
        {
            return $this;
        }

        public function useFieldWrapper(bool $use): static
        {
            return $this;
        }

        public function setLabel(string $label): static
        {
            return $this;
        }

        public function addOption(string $text, string $value): static
        {
            return $this;
        }

        public function getSelectWrapper(): static
        {
            return $this;
        }

        public function setRule(string $rule): static
        {
            return $this;
        }

        public function setCustomFieldName(string $name): static
        {
            return $this;
        }
    }

    class Alert
    {
        public string $content = '';
        public string $cssClass = '';
        public array $attributes = [];

        public function setContent(string $content): static
        {
            $this->content = $content;
            return $this;
        }

        public function getContent(): string
        {
            return $this->content;
        }

        public function setCSSClass(string $class): static
        {
            $this->cssClass = $class;
            return $this;
        }

        public function setAttribute(string $name, string $value): static
        {
            $this->attributes[$name] = $value;
            return $this;
        }

        public function render(): string
        {
            return $this->content !== '' ? '<div class="' . $this->cssClass . '">' . $this->content . '</div>' : '';
        }
    }

    class Link extends Tag
    {
        protected string $url = '';
        protected string $queryString = '';
        protected string $anchor = '';
        protected string $linkText = '';

        public function setUrl(string $url): static
        {
            $this->url = $url;
            return $this;
        }

        public function setQueryString(string $qs): static
        {
            $this->queryString = $qs;
            return $this;
        }

        public function setAnchor(string $anchor): static
        {
            $this->anchor = $anchor;
            return $this;
        }

        public function setLinkText(string $text): static
        {
            $this->linkText = $text;
            return $this;
        }

        /**
         * Mirrors the real Link::render() - the resulting <a> tag carries whatever was set via
         * setAttribute() (e.g. FrontendCommentPagination sets 'title', 'aria-current' and
         * 'aria-label' on its pagination links for accessibility), the same way any other Tag
         * subclass does. Only href/text are handled separately here since Link, unlike plain Tag
         * subclasses, builds its href from url/queryString/anchor rather than a single setter.
         */
        public function render(): string
        {
            $href = $this->url;
            if ($this->queryString !== '') {
                $href .= '?' . $this->queryString;
            }
            if ($this->anchor !== '') {
                $href .= '#' . $this->anchor;
            }
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"' . $this->attributesToString() . '>' . $this->linkText . '</a>';
        }
    }
}
