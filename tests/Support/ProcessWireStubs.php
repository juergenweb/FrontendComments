<?php

/**
 * Minimal, faithful-where-it-matters stand-ins for the ProcessWire core classes that
 * FrontendComments depends on.
 *
 * These are NOT a ProcessWire installation. They exist so the REAL, unmodified module source
 * files can be loaded and their methods invoked in isolation, the same way a previous debugging
 * session in this project built a reflection-based repro harness against the real source to prove
 * the tombstone-rendering logic was correct.
 *
 * Two methods are ported near-verbatim from the real ProcessWire core (wire/core/Sanitizer.php,
 * fetched from https://github.com/processwire/processwire) rather than reimplemented from
 * scratch: Sanitizer::entities() and Sanitizer::selectorValue(). Those two are the ones the
 * security fixes actually depend on, so faithfulness there matters - a hand-rolled reimplementation
 * could easily pass a test that a real PW installation would fail, or vice versa. Everything else
 * here is a deliberately lightweight double, not a claim about how ProcessWire really behaves.
 */

declare(strict_types=1);

namespace ProcessWire {

    // ---------------------------------------------------------------------
    // Exceptions
    // ---------------------------------------------------------------------

    class WireException extends \Exception
    {
    }

    class WirePermissionException extends WireException
    {
    }

    // ---------------------------------------------------------------------
    // Marker interfaces / trivial base types that only need to exist so
    // `extends` / `implements` on the real module classes resolves.
    // ---------------------------------------------------------------------

    interface Module
    {
    }

    interface ConfigurableModule
    {
    }

    interface WirePaginatable
    {
    }

    class Notice
    {
        const allowMarkup = 1;
    }

    // ---------------------------------------------------------------------
    // Wire / WireData
    // ---------------------------------------------------------------------

    class Wire
    {
        /** @var string[] recorded via warning()/error(), for tests that want to assert on them */
        public array $notices = [];

        // Real ProcessWire's Wire declares a constructor, so subclasses' parent::__construct() calls
        // always resolve to something. Without one here, invoking the REAL constructor of a module
        // class that (like most of them) starts with parent::__construct() throws PHP's "Cannot call
        // constructor" - there is no constructor anywhere in the chain to call - which only surfaces
        // the first time a test builds an object via `new X(...)` instead of
        // newInstanceWithoutConstructor(), as this stub previously went untested.
        public function __construct()
        {
        }

        public function wire($name = null)
        {
            if ($name === null) {
                return new ProcessWireApiProxy();
            }
            if (is_object($name)) {
                // real Wire::wire($obj) registers $obj with the ProcessWire instance and returns it
                return $name;
            }
            return TestServices::get((string)$name);
        }

        /**
         * Real ProcessWire's Wire::__call() is what makes hookable methods (declared as
         * `___methodName()`) callable by their public name (`$obj->methodName()`) at all - production
         * code routinely calls e.g. $class->renderPaginationMarkup() expecting it to resolve to
         * ___renderPaginationMarkup(). Without this, any test that calls a hookable method the way
         * real calling code does (rather than reaching for its ___-prefixed name directly) fails with
         * a plain "undefined method" error that has nothing to do with the code under test.
         */
        public function __call($name, $arguments)
        {
            $hookMethod = '___' . $name;
            if (method_exists($this, $hookMethod)) {
                return $this->$hookMethod(...$arguments);
            }
            throw new \Error('Call to undefined method ' . static::class . '::' . $name . '()');
        }

        /** Translation passthrough - no localization needed for these tests */
        public function _(string $text): string
        {
            return $text;
        }

        /** Plural translation passthrough, mirroring real ProcessWire's Wire::_n() - picks the
         *  singular or plural source text based on $count, without any actual localization. */
        public function _n(string $textSingular, string $textPlural, $count): string
        {
            return $count == 1 ? $textSingular : $textPlural;
        }

        public function warning(string $text, int $flags = 0): void
        {
            $this->notices[] = ['type' => 'warning', 'text' => $text, 'flags' => $flags];
        }

        public function error(string $text, int $flags = 0): void
        {
            $this->notices[] = ['type' => 'error', 'text' => $text, 'flags' => $flags];
        }

        public function message(string $text, int $flags = 0): void
        {
            $this->notices[] = ['type' => 'message', 'text' => $text, 'flags' => $flags];
        }

        /**
         * A real (if simplified) stand-in for PW's change tracking: trackChange() records which
         * named properties changed, isChanged()/getChanges() read that back. Needed for real
         * business logic that branches on it directly (e.g.
         * FieldtypeFrontendComments::correctStatusValues() checks $comment->isChanged('status') and
         * $comments->isChanged('statuschange')) - not just cosmetic bookkeeping. Unlike real PW,
         * tracking here is always "on" (no setTrackChanges(false) gating), which is fine since no
         * test in this suite depends on tracking being switched off.
         */
        protected array $trackedChanges = [];

        public function trackChange(string $what = '', $old = null, $new = null): void
        {
            if ($what !== '') {
                $this->trackedChanges[$what] = true;
            }
        }

        public function isChanged(string $what = ''): bool
        {
            if ($what === '') {
                return !empty($this->trackedChanges);
            }
            return !empty($this->trackedChanges[$what]);
        }

        public function getChanges(bool $old = false): array
        {
            return array_keys($this->trackedChanges);
        }

        public function resetTrackChanges(bool $trackChanges = true): static
        {
            $this->trackedChanges = [];
            return $this;
        }

        /** No-op flag toggle - tracking is always recorded in this stub, see $trackedChanges above. */
        public function setTrackChanges(bool $track = true): static
        {
            return $this;
        }

        /** @var array<int, string> recorded via log(), for tests that want to assert on them */
        public array $logMessages = [];

        public function log(string $message, array $options = []): void
        {
            $this->logMessages[] = $message;
        }
    }

    class WireData extends Wire implements \ArrayAccess
    {
        protected array $data = [];

        public function get(string $key)
        {
            return $this->data[$key] ?? null;
        }

        public function set(string $key, $value): static
        {
            $this->data[$key] = $value;
            return $this;
        }

        /**
         * Real PW modules commonly cache an API variable onto themselves in their constructor
         * (e.g. FieldtypeFrontendComments::__construct() does `$this->database =
         * $this->wire()->database;`), then read it back later as a plain property (`$this->database`).
         * Since this suite's tests build objects via newInstanceWithoutConstructor() specifically to
         * skip that (heavy) constructor, that assignment never happens - so falling back to the
         * global service registry here for any key not found in $data reproduces what the skipped
         * constructor would have cached, without requiring every such test to manually wire it up.
         */
        public function __get($key)
        {
            $value = $this->get($key);
            return $value !== null ? $value : TestServices::get($key);
        }

        public function __set($key, $value): void
        {
            $this->set($key, $value);
        }

        /** convenience for tests: array-literal seeding */
        public function setArray(array $values): static
        {
            foreach ($values as $k => $v) {
                $this->set($k, $v);
            }
            return $this;
        }

        // real PW WireData implements ArrayAccess too (e.g. `if ($comment['author'])`)
        public function offsetExists($offset): bool
        {
            return isset($this->data[$offset]);
        }

        public function offsetGet($offset): mixed
        {
            return $this->get((string)$offset);
        }

        public function offsetSet($offset, $value): void
        {
            $this->set((string)$offset, $value);
        }

        public function offsetUnset($offset): void
        {
            unset($this->data[$offset]);
        }
    }

    /** Trivial base so FrontendCommentsManager (extends Process) resolves; real behavior comes
     *  from WireData/Wire above (set()/get()/trackChange() etc.) - nothing about Process's own
     *  admin-page-routing behavior is exercised by these tests. */
    class Process extends WireData
    {
    }

    // ---------------------------------------------------------------------
    // WireArray / PaginatedArray
    //
    // Real WireArray::get()/find() parse a ProcessWire selector string and match it against the
    // array's items. Reimplementing that matching engine faithfully is a project of its own and
    // NOT what most of these tests need: the selector-injection fixes are regression-tested by
    // asserting "is the raw value wrapped in $sanitizer->selectorValue() before it reaches the
    // selector string" (self::$calls records every selector string get()/find() was called with),
    // not by simulating ProcessWire's real selector-to-SQL translation.
    //
    // The tombstone-rendering tests, however, DO need find() to actually match items against a
    // selector (FrontendComments::getCommentListArray() filters/recurses based on real find()
    // results) - so a deliberately simplified matcher is implemented below: top-level clauses are
    // comma-separated (quote-aware, so a quoted value's comma doesn't split), each clause is
    // `field=value`, and a value may itself be a `|`-separated OR-list (e.g. `status=1|2|4`). The
    // pseudo-field `sort=` is recognized and ignored for matching (ProcessWire uses it for
    // ordering, not filtering). This covers every selector shape actually used in this module's
    // source - it is not a general-purpose selector engine.
    //
    // A test that wants full control instead of this matching (e.g. to hand back one specific
    // canned item regardless of the selector) can set $array->findResult / ->getResult directly;
    // when set, that canned value is returned as-is and no matching happens.
    //
    // Real WireArray::find() returns a new instance of the SAME class it was called on (via
    // makeNew()) - e.g. calling find() on a FrontendCommentArray returns another
    // FrontendCommentArray, not a generic collection type. Several real methods rely on exactly
    // that (FrontendComment::getReplies() is typed to return `?FrontendCommentArray`, and would
    // throw a TypeError at runtime if find() returned anything else). So find()/reverse() below
    // build the new instance via ReflectionClass::newInstanceWithoutConstructor() - same
    // reasoning as everywhere else in this suite: a domain class like FrontendCommentArray has a
    // real constructor with far more ProcessWire/FrontendForms dependencies than a stub collection
    // needs to satisfy just to hold a few items.
    // ---------------------------------------------------------------------

    trait SimpleSelectorMatching
    {
        /** Split a selector string on top-level commas, respecting double-quoted values. */
        protected function splitSelectorClauses(string $selector): array
        {
            $clauses = [];
            $current = '';
            $inQuotes = false;
            $len = strlen($selector);
            for ($i = 0; $i < $len; $i++) {
                $ch = $selector[$i];
                if ($ch === '"') {
                    $inQuotes = !$inQuotes;
                    $current .= $ch;
                    continue;
                }
                if ($ch === ',' && !$inQuotes) {
                    $clauses[] = trim($current);
                    $current = '';
                    continue;
                }
                $current .= $ch;
            }
            if (trim($current) !== '') {
                $clauses[] = trim($current);
            }
            return $clauses;
        }

        protected function itemMatchesSelector($item, string $selector): bool
        {
            foreach ($this->splitSelectorClauses($selector) as $clause) {
                if (!preg_match('/^([a-zA-Z0-9_]+)=(.*)$/', $clause, $m)) {
                    // a clause this simplified matcher cannot parse never matches (fail closed)
                    // rather than silently ignoring it
                    return false;
                }
                [, $field, $rawValue] = $m;
                if ($field === 'sort') {
                    continue; // ordering hint, not a filter criterion
                }
                if (strlen($rawValue) >= 2 && $rawValue[0] === '"' && substr($rawValue, -1) === '"') {
                    $rawValue = substr($rawValue, 1, -1);
                }
                $itemValue = $item->get($field);
                $matchedOne = false;
                foreach (explode('|', $rawValue) as $acceptable) {
                    if ((string)$itemValue === (string)$acceptable) {
                        $matchedOne = true;
                        break;
                    }
                }
                if (!$matchedOne) {
                    return false;
                }
            }
            return true;
        }

        protected function matchItems(array $items, string $selector): array
        {
            $matches = [];
            foreach ($items as $item) {
                if ($this->itemMatchesSelector($item, $selector)) {
                    $matches[] = $item;
                }
            }
            return $matches;
        }
    }

    class WireArray extends Wire implements \Countable, \IteratorAggregate
    {
        use SimpleSelectorMatching;

        /** @var array<int, array{method:string, selector:string}> */
        public static array $calls = [];

        /** When explicitly set (not null), get()/find() return this instead of matching - see class docblock */
        public ?array $findResult = null;
        public $getResult = null;
        protected bool $getResultSet = false;

        protected array $items = [];
        /** Real PW's WireArray tracks additions/removals (getItemsAdded()/getItemsRemoved()) so
         *  save-time hooks like FieldtypeFrontendComments::updateTables() can tell which comments
         *  are brand new or were deleted, as opposed to merely status-changed. */
        protected array $itemsAdded = [];
        protected array $itemsRemoved = [];

        public static function resetLog(): void
        {
            self::$calls = [];
        }

        public function add($item): static
        {
            $this->items[] = $item;
            $this->itemsAdded[] = $item;
            return $this;
        }

        public function getItemsAdded(): array
        {
            return $this->itemsAdded;
        }

        public function getItemsRemoved(): array
        {
            return $this->itemsRemoved;
        }

        public function count(): int
        {
            return count($this->items);
        }

        public function getArray(): array
        {
            return $this->items;
        }

        public function getIterator(): \Iterator
        {
            return new \ArrayIterator($this->items);
        }

        public function first()
        {
            $first = reset($this->items);
            return $first === false ? null : $first;
        }

        /** Explicitly set the canned get() result (distinguishes "canned null" from "not set"). */
        public function setGetResult($value): void
        {
            $this->getResult = $value;
            $this->getResultSet = true;
        }

        public function get(string $selector)
        {
            self::$calls[] = ['method' => 'get', 'selector' => $selector];
            if ($this->getResultSet) {
                return $this->getResult;
            }
            $matches = $this->matchItems($this->items, $selector);
            return $matches[0] ?? null;
        }

        /** Build a new, empty instance of the calling class without running its (possibly heavy) constructor. */
        protected static function newEmptySameClass(): static
        {
            return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
        }

        public function find(string $selector): static
        {
            self::$calls[] = ['method' => 'find', 'selector' => $selector];
            $matches = $this->findResult !== null ? $this->findResult : $this->matchItems($this->items, $selector);
            $result = static::newEmptySameClass();
            foreach ($matches as $m) {
                $result->add($m);
            }
            return $result;
        }

        /**
         * Real PW's WireArray::filter() - unlike find(), this MUTATES the array itself down to only
         * the matching items and returns $this (fluent), rather than returning a new array. This
         * distinction matters in production: FrontendComments::getCommentsForDisplay() calls
         * $this->comments->filter(...) on the very same FrontendCommentArray instance that
         * FrontendCommentArray::renderPagination() later reads getTotalComments() from - so whatever
         * filter() removes here is permanently gone from that shared array, not just from this one
         * local result.
         */
        public function filter(string $selector): static
        {
            self::$calls[] = ['method' => 'filter', 'selector' => $selector];
            $this->items = array_values($this->matchItems($this->items, $selector));
            return $this;
        }

        /**
         * Real PW's WireArray::slice($start, $limit) - like find(), returns a NEW array (does not
         * mutate $this), containing at most $limit items starting at $start.
         */
        public function slice(int $start, int $limit = 0): static
        {
            $slice = $limit > 0 ? array_slice($this->items, $start, $limit) : array_slice($this->items, $start);
            $result = static::newEmptySameClass();
            foreach ($slice as $m) {
                $result->add($m);
            }
            return $result;
        }

        public function remove($item): static
        {
            foreach ($this->items as $k => $v) {
                if ($v === $item) {
                    unset($this->items[$k]);
                    $this->itemsRemoved[] = $item;
                    break;
                }
            }
            return $this;
        }

        public function reverse(): static
        {
            $result = static::newEmptySameClass();
            foreach (array_reverse($this->items, true) as $item) {
                $result->add($item);
            }
            return $result;
        }

        /** Real PW's WireArray::getItemKey() - the numeric position of $item within this array
         *  (used by FrontendCommentArray::getCommentPage() to work out which pagination page a
         *  given comment falls on), or null if it isn't present at all. */
        public function getItemKey($item): int|string|null
        {
            $key = array_search($item, $this->items, true);
            return $key === false ? null : $key;
        }
    }

    class PaginatedArray extends WireArray
    {
    }

    // ---------------------------------------------------------------------
    // Page / Field
    // ---------------------------------------------------------------------

    class Page extends WireData
    {
        public int $id = 0;
        public string $httpUrl = '';
        public string $url = '';
        public ?Page $rootParent = null;

        // Real ProcessWire Page objects answer get('id')/get('httpUrl')/get('url') from these same
        // native properties, not from a separate WireData $data array - the module relies on this
        // (see e.g. FrontendComment::saveComment()/deleteComment() using $page->get('id') in bound
        // SQL parameters), so this stub must resolve those keys the same way real PW does.
        public function get(string $key)
        {
            return match ($key) {
                'id' => $this->id,
                'httpUrl' => $this->httpUrl,
                'url' => $this->url,
                default => parent::get($key),
            };
        }
    }

    class Field extends WireData
    {
        public int $id = 0;
        public string $name = '';
        /** real PW Field exposes its config values as a public array here, accessed directly (e.g. $field->data['input_fc_spam']) */
        public array $data = [];
        public string $table = '';
        /** real PW Field::$type holds the Fieldtype instance (e.g. `$field->type instanceof
         *  FieldtypeFrontendComments`, used by FieldtypeFrontendComments::___upgrade() to find every
         *  comment field's own table that needs a schema migration) */
        public $type = null;

        public function getTable(): string
        {
            return $this->table;
        }

        // Same reasoning as Page::get() above - real Field::get('id')/get('name')/get('table') read
        // the native properties (see e.g. FrontendComment::deleteEntriesInQueueTable() using
        // $this->field->get('id') in bound SQL parameters).
        public function get(string $key)
        {
            return match ($key) {
                'id' => $this->id,
                'name' => $this->name,
                'table' => $this->table,
                default => parent::get($key),
            };
        }
    }

    /**
     * Real PW's Fields class is itself a WireArray, so it can be foreach()'d directly (e.g.
     * FieldtypeFrontendComments::___upgrade() loops over every field to find comment fields whose
     * table needs a schema migration) - IteratorAggregate reproduces just that part.
     */
    class Fields implements \IteratorAggregate
    {
        /** @var Field[] */
        protected array $byId = [];

        public function add(Field $field): void
        {
            $this->byId[$field->id] = $field;
        }

        public function findByType(string $type): array
        {
            return array_values($this->byId);
        }

        /**
         * Real PW's Fields::get() accepts either an id or a name (e.g.
         * FieldtypeFrontendComments::quietPageEditSaveForCommentsOnly() looks fields up by the
         * name strings $page->getChanges() returns) - look up by name when $id isn't numeric,
         * otherwise fall back to the id-keyed lookup used everywhere else in this suite.
         */
        public function get($id): ?Field
        {
            if (is_string($id) && !is_numeric($id)) {
                foreach ($this->byId as $field) {
                    if ($field->name === $id) return $field;
                }
                return null;
            }
            return $this->byId[$id] ?? null;
        }

        public function getIterator(): \Iterator
        {
            return new \ArrayIterator(array_values($this->byId));
        }
    }

    class Fieldtypes
    {
        protected array $stubs = [];

        public function set(string $name, $stub): void
        {
            $this->stubs[$name] = $stub;
        }

        public function get(string $name)
        {
            return $this->stubs[$name] ?? new class {
                public function savePageField($page, $field): bool
                {
                    return true;
                }
            };
        }
    }

    // ---------------------------------------------------------------------
    // Fieldtype / FieldtypeMulti base (only so FieldtypeFrontendComments' `extends` resolves)
    // ---------------------------------------------------------------------

    class Fieldtype extends WireData implements Module
    {
    }

    class FieldtypeMulti extends Fieldtype
    {
    }

    // ---------------------------------------------------------------------
    // User / Session / Input / Config / Database services
    // ---------------------------------------------------------------------

    class User extends WireData
    {
        public int $id = 0;
        protected bool $loggedIn = false;
        protected bool $superuser = false;
        /** @var string[] */
        protected array $permissions = [];

        public function setLoggedIn(bool $v): static
        {
            $this->loggedIn = $v;
            return $this;
        }

        public function setSuperuser(bool $v): static
        {
            $this->superuser = $v;
            return $this;
        }

        public function grantPermission(string $name): static
        {
            $this->permissions[] = $name;
            return $this;
        }

        public function isLoggedin(): bool
        {
            return $this->loggedIn;
        }

        public function isSuperuser(): bool
        {
            return $this->superuser;
        }

        public function hasPermission(string $name): bool
        {
            return $this->superuser || in_array($name, $this->permissions, true);
        }
    }

    class Session extends WireData
    {
        protected string $ip = '127.0.0.1';

        public function getIP(): string
        {
            return $this->ip;
        }

        public function remove(string $key): void
        {
            unset($this->data[$key]);
        }
    }

    /** Real PW's WireInputData is the array-accessible bag ___processInput() receives its
     *  submitted form values through (e.g. `$input['fieldname']`). */
    class WireInputData implements \ArrayAccess
    {
        public function __construct(protected array $data = [])
        {
        }

        public function offsetExists($offset): bool
        {
            return isset($this->data[$offset]);
        }

        public function offsetGet($offset): mixed
        {
            return $this->data[$offset] ?? null;
        }

        public function offsetSet($offset, $value): void
        {
            if ($offset === null) {
                $this->data[] = $value;
            } else {
                $this->data[$offset] = $value;
            }
        }

        public function offsetUnset($offset): void
        {
            unset($this->data[$offset]);
        }
    }

    /** Just enough of real PW's Inputfield base class for InputfieldFrontendComments to extend -
     *  its own logic under test (___processInput()) only relies on the warning()/error()/message()
     *  methods and change-tracking already provided by Wire/WireData above. */
    class Inputfield extends WireData
    {
        const collapsedNever = 0;
        const collapsedBlank = 1;

        public function attr($key, $value = null)
        {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->set($k, $v);
                }
                return $this;
            }
            if (func_num_args() === 1) {
                return $this->get($key);
            }
            $this->set($key, $value);
            return $this;
        }

        public function collapsed($value = null)
        {
            if ($value === null) {
                return $this->get('collapsed');
            }
            $this->set('collapsed', $value);
            return $this;
        }

        public function getParent()
        {
            return null;
        }
    }

    /**
     * Minimal stand-in for real PW's HookEvent, the object a hook method receives - only what
     * FieldtypeFrontendComments::correctStatusValues() actually uses: which object fired the hook
     * (->object) and its arguments (get via arguments($n), set via arguments($n, $value), matching
     * real HookEvent's overloaded single/two-argument form).
     */
    class HookEvent
    {
        protected array $args;

        public function __construct(public $object, array $args = [])
        {
            $this->args = $args;
        }

        public function arguments(int $n, $value = null)
        {
            if (func_num_args() === 1) {
                return $this->args[$n] ?? null;
            }
            $this->args[$n] = $value;
            return $this;
        }
    }

    class WireInput
    {
        protected array $getVars = [];
        protected array $postVars = [];
        protected string $queryString = '';
        protected string $url = '/';

        public function setGet(array $vars): static
        {
            $this->getVars = $vars;
            return $this;
        }

        public function setPost(array $vars): static
        {
            $this->postVars = $vars;
            return $this;
        }

        public function setQueryString(string $qs): static
        {
            $this->queryString = $qs;
            return $this;
        }

        public function setUrl(string $url): static
        {
            $this->url = $url;
            return $this;
        }

        public function get(?string $key = null)
        {
            if ($key === null) {
                return (object)$this->getVars;
            }
            return $this->getVars[$key] ?? null;
        }

        public function post(?string $key = null)
        {
            if ($key === null) {
                return $this->postVars ? (object)$this->postVars : null;
            }
            return $this->postVars[$key] ?? null;
        }

        public function queryString(): string
        {
            return $this->queryString;
        }

        /**
         * Real PW's WireInput::queryStringClean($options) rebuilds a sanitized query string
         * containing only the GET vars whose keys are listed in $options['validNames'], in
         * "key=value&key2=value2" form (empty string if none match) - used by
         * FrontendComments::getCommentsForDisplay() to read back just the pagination page-number key
         * (via setGet(), not the raw queryString() set via setQueryString()).
         */
        public function queryStringClean(array $options = []): string
        {
            $validNames = $options['validNames'] ?? [];
            $pairs = [];
            foreach ($validNames as $name) {
                if (array_key_exists($name, $this->getVars)) {
                    $pairs[] = $name . '=' . $this->getVars[$name];
                }
            }
            return implode('&', $pairs);
        }

        public function url(array $options = []): string
        {
            $url = $this->url;
            if (!empty($options['withQueryString']) && $this->queryString !== '') {
                $url .= '?' . $this->queryString;
            }
            return $url;
        }
    }

    class ConfigUrls
    {
        public string $admin = '/processwire/';
    }

    class ConfigPaths
    {
        public string $assets = '/site/assets/';
    }

    class Config
    {
        public bool $ajax = false;
        public string $httpHost = 'localhost.com';
        // Real ProcessWire core config, always a non-empty string by default ('page') - needed by
        // FrontendCommentPagination::__construct(), which reads it directly.
        public string $pageNumUrlPrefix = 'page';
        public ConfigUrls $urls;
        public ConfigPaths $paths;

        public function __construct()
        {
            $this->urls = new ConfigUrls();
            $this->paths = new ConfigPaths();
        }
    }

    /**
     * Minimal stand-in for ProcessWire's $modules API - only the one call this module actually
     * makes (getConfig('FrontendForms'), to read the active CSS framework / theme) is implemented.
     */
    class Modules
    {
        protected array $configs = [];

        public function setConfig(string $name, array $config): static
        {
            $this->configs[$name] = $config;
            return $this;
        }

        public function getConfig(string $name): array
        {
            return $this->configs[$name] ?? [];
        }
    }

    /**
     * Fake PDO statement. Tests queue up canned (rowCount, execute-succeeds) pairs per
     * Database::prepare() call, in the same order the real code prepares statements
     * (SELECT check -> INSERT -> UPDATE for the votes flow).
     */
    class FakeStatement
    {
        public array $bound = [];
        /** cursor position for fetch() below - real PDOStatement::fetch() consumes one row per
         *  call and returns false once exhausted; fetchAll() ignores it and always returns every
         *  queued row, which is a simplification but sufficient for what this suite tests. */
        private int $fetchPos = 0;

        public function __construct(
            public readonly string $sql,
            private readonly int $rowCount = 0,
            private readonly bool $succeeds = true,
            private readonly array $fetchAllResult = []
        ) {
        }

        public function bindValue($param, $value, $type = null): bool
        {
            $this->bound[$param] = $value;
            return true;
        }

        public function execute($params = null): bool
        {
            return $this->succeeds;
        }

        public function rowCount(): int
        {
            return $this->rowCount;
        }

        public function fetch(...$args)
        {
            if ($this->fetchPos >= count($this->fetchAllResult)) {
                return false;
            }
            return $this->fetchAllResult[$this->fetchPos++];
        }

        public function fetchAll(...$args): array
        {
            return $this->fetchAllResult;
        }

        public function closeCursor(): bool
        {
            return true;
        }
    }

    class Database
    {
        /** @var array<int, array{rowCount:int, succeeds:bool}> queued in call order */
        protected array $queue = [];
        /** @var FakeStatement[] every prepared statement, for assertions on the SQL text */
        public array $prepared = [];
        /** @var string[] every SQL string handed to exec() (DDL like CREATE/ALTER TABLE), in order -
         *  used by ___upgrade() migration tests to assert exactly what was (or wasn't) altered */
        public array $executed = [];
        /** Per-table override for tableExists() - defaults to true (table already exists), which is
         *  what almost every test needs; set $db->tableExistsOverrides['sometable'] = false to test
         *  the "table doesn't exist yet" branch. */
        public array $tableExistsOverrides = [];

        /** Queue the canned result for the Nth prepare() call (0-indexed call order) */
        public function queueResult(int $rowCount, bool $succeeds = true, array $fetchAllResult = []): static
        {
            $this->queue[] = ['rowCount' => $rowCount, 'succeeds' => $succeeds, 'fetchAllResult' => $fetchAllResult];
            return $this;
        }

        public function prepare(string $sql): FakeStatement
        {
            $next = array_shift($this->queue) ?? ['rowCount' => 0, 'succeeds' => true, 'fetchAllResult' => []];
            $stmt = new FakeStatement($sql, $next['rowCount'], $next['succeeds'], $next['fetchAllResult'] ?? []);
            $this->prepared[] = $stmt;
            return $stmt;
        }

        /** Real PDO's query() (used for statements with no bound parameters, e.g. "SHOW COLUMNS
         *  FROM ... LIKE ...") - shares the same queued-result mechanism as prepare() above. */
        public function query(string $sql): FakeStatement
        {
            return $this->prepare($sql);
        }

        /** Real ProcessWire's Database::exec() (used for DDL like CREATE/ALTER TABLE, where there is
         *  no result set to fetch) - just records the SQL for assertions, real PDO::exec() returns
         *  the affected row count which nothing here reads. */
        public function exec(string $sql): int
        {
            $this->executed[] = $sql;
            return 0;
        }

        /** Real ProcessWire's Database::tableExists() - see $tableExistsOverrides above. */
        public function tableExists(string $table): bool
        {
            return $this->tableExistsOverrides[$table] ?? true;
        }

        /** Real ProcessWire's Database::escapeTable() wraps a validated table name in backticks. */
        public function escapeTable(string $table): string
        {
            return '`' . $table . '`';
        }
    }

    // Real ProcessWire's database service class is actually named WireDatabasePDO (Database is
    // used loosely as a shorthand elsewhere in this file) - some module classes type-hint their own
    // $database property as WireDatabasePDO directly (e.g. FrontendCommentForm, FrontendCommentsManager).
    // Aliasing keeps this one Database stub usable everywhere without a second, parallel
    // implementation to keep in sync.
    class_alias(Database::class, WireDatabasePDO::class);

    /**
     * Real ProcessWire's WireDateTime formats timestamps and computes human-readable relative
     * time strings ("2 days ago"). Reproducing that algorithm is not the point of these tests -
     * relativeTimeStr() returns an obviously-fake, recognizable marker string so a test can assert
     * WHICH branch a caller took (formatted date+time vs. relative string) without depending on
     * PW's actual wording.
     */
    class WireDateTime
    {
        public function date($format, $ts): string
        {
            if (is_string($ts) && !ctype_digit($ts)) {
                $ts = strtotime($ts);
            }
            return date((string)$format, (int)$ts);
        }

        public function relativeTimeStr($ts): string
        {
            return 'RELATIVE(' . $ts . ')';
        }
    }

    class WireMail
    {
        public function __call($name, $args)
        {
            return $this;
        }
    }

    /**
     * Mirrors the relevant bit of real ProcessWire's TemplateFile: values set via set() (inherited
     * from WireData) are extracted into local variables in scope for the included file, and the
     * file's output is captured and returned. Used to render the module's REAL template files
     * (e.g. templates/comment.php) end-to-end in tests, rather than only asserting on the $vars
     * array that would have been handed to a template.
     */
    class TemplateFile extends WireData
    {
        public function __construct(protected string $filename)
        {
        }

        public function render(): string
        {
            extract($this->data);
            ob_start();
            include $this->filename;
            return ob_get_clean();
        }
    }

    // ---------------------------------------------------------------------
    // Test service registry - stands in for the ProcessWire API variable "fuel"
    // that $this->wire('sanitizer') / $this->wire('input') / etc. resolve against.
    // ---------------------------------------------------------------------

    class TestServices
    {
        /** @var array<string, mixed> */
        public static array $services = [];

        public static function get(string $name)
        {
            return self::$services[$name] ?? null;
        }

        public static function set(string $name, $value): void
        {
            self::$services[$name] = $value;
        }

        public static function reset(): void
        {
            self::$services = [];
            WireArray::resetLog();
        }
    }

    /**
     * Real PW's wire()/$this->wire() called with NO argument returns the ProcessWire application
     * instance itself, which exposes every API variable as a magic property (wire()->sanitizer,
     * wire()->database, ...) - this stands in for that instance so both the global wire() function
     * and Wire::wire() below can return the same thing for their no-argument form.
     */
    class ProcessWireApiProxy
    {
        public function __get($key)
        {
            return TestServices::get($key);
        }
    }

    /**
     * Real ProcessWire's Sanitizer has dozens of methods; only the ones the fixed code actually
     * calls are implemented here. entities() and selectorValue() are ported near-verbatim from
     * wire/core/Sanitizer.php (processwire/processwire @ master) so the escaping/quoting behavior
     * under test matches the real framework, not an assumption about it.
     */
    class Sanitizer
    {
        protected bool $multibyteSupport;

        public function __construct()
        {
            $this->multibyteSupport = extension_loaded('mbstring');
        }

        // --- ported near-verbatim from PW core Sanitizer::entities() ---
        public function entities($str, $flags = ENT_QUOTES, $encoding = 'UTF-8', $doubleEncode = true): string
        {
            if (!is_string($str)) {
                $str = $this->string($str);
            }
            return htmlentities($str, $flags, $encoding, $doubleEncode);
        }

        public function string($value): string
        {
            if (is_string($value)) {
                return $value;
            }
            if (is_object($value)) {
                return method_exists($value, '__toString') ? (string)$value : get_class($value);
            }
            if (is_null($value)) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? '1' : '';
            }
            if (is_array($value)) {
                return 'array-' . count($value);
            }
            return (string)$value;
        }

        public function string120($value): string
        {
            return mb_substr($this->string($value), 0, 120);
        }

        public function int($value, array $options = []): int
        {
            return (int)$value;
        }

        public function email($value): string
        {
            $value = trim($this->string($value));
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
        }

        // NOTE: real PW's textarea()/text()/httpUrl() strip/normalize far more than this (control
        // characters, disallowed tags, URL scheme handling, etc.) - none of that is what's under
        // test where these are called (InputfieldFrontendComments::___processInput() has already
        // decided the value is acceptable via its own preg_match()/filter_var() checks by the time
        // it calls these; only round-tripping the value is needed here), so a faithful port isn't
        // warranted the way entities()/selectorValue() above were.
        public function textarea($value, array $options = []): string
        {
            return trim($this->string($value));
        }

        public function text($value, array $options = []): string
        {
            return trim(str_replace(["\r", "\n"], ' ', $this->string($value)));
        }

        public function httpUrl($value): string
        {
            return trim($this->string($value));
        }

        // NOTE: same reasoning as textarea()/text()/httpUrl() above - these three aren't the
        // behavior under test where they're called (___sleepValue() has already decided what to do
        // with the value; these just need to round-trip it), so no faithful port of PW's actual
        // truncation/purification rules.
        public function maxLength($value, int $maxLength = 255, ?int $maxBytes = null): string
        {
            return mb_substr($this->string($value), 0, $maxLength);
        }

        /** Unlike maxLength() above, this one IS load-bearing for a regression test: real PW's
         *  purify() keeps a safe HTML subset (bold/italic/lists/links/...) rather than stripping all
         *  markup the way string() does - that distinction is exactly what
         *  FieldtypeFrontendComments::___sleepValue()'s moderation_feedback fix depends on, so this
         *  stub must actually strip disallowed tags while keeping the safe ones, not just pass the
         *  value through unchanged. */
        public function purify($value, array $options = []): string
        {
            return strip_tags((string)$value, '<b><strong><i><em><u><blockquote><ol><ul><li><a>');
        }

        public function fieldName($value): string
        {
            return $this->string($value);
        }

        protected function getWhitespaceArray(bool $extended = false): array
        {
            // subset of PW's whitespace list, sufficient for these tests
            return ["\r", "\n", "\t", "\0", "\x0B"];
        }

        // --- ported near-verbatim from PW core Sanitizer::selectorValue()/selectorValueV2() ---
        // (wire/core/Sanitizer.php, https://github.com/processwire/processwire, master branch)
        public function selectorValue($value, $options = [])
        {
            if (is_int($options)) {
                $options = ['maxLength' => $options];
            } elseif (!is_array($options)) {
                $options = [];
            }
            return $this->selectorValueV2($value, $options);
        }

        protected function selectorValueArray(array $value, $options = [])
        {
            $a = [];
            $allowArray = $options['allowArray'] ?? true;
            if (count($value) < 2 || !$allowArray) {
                $value = reset($value);
                $value = $this->string($value);
                return $this->selectorValueV2($value, $options);
            }
            $options['useQuotes'] = true;
            foreach ($value as $v) {
                $v = $this->selectorValueV2($v, $options);
                if (!strlen((string)$v)) {
                    $v = '""';
                }
                $a[] = $v;
            }
            return implode('|', $a);
        }

        protected function selectorValueV2($value, array $options = [])
        {
            $blacklist = [
                '"', "\\0", "\\", "`", "|", '=', '*', '%', '~', '^', '$', '#',
                '<', '>', '[', ']', '{', '}', "\r", "\n", "\t",
            ];

            $quotelist = [
                "'", ",", "!", ":", ";", "(", ")", "*", "+",
            ];

            $defaults = [
                'allowArray' => true,
                'allowSpace' => true,
                'maxLength' => 100,
                'maxBytes' => 400,
                'useQuotes' => true,
                'emptyValue' => '',
                'quoteEmpty' => false,
                'operator' => '',
                'whitelist' => [],
                'blacklist' => $blacklist,
                'quotelist' => $quotelist,
            ];

            if (is_array($value)) {
                return $this->selectorValueArray($value, $options);
            }

            if (!empty($options['blacklist'])) {
                $options['blacklist'] = array_merge($blacklist, $options['blacklist']);
            }
            if (!empty($options['quotelist'])) {
                $options['quotelist'] = array_merge($quotelist, $options['quotelist']);
            }

            $options = array_merge($defaults, $options);
            $useQuotes = $options['useQuotes'];
            $hadQuotes = false;
            $needsQuotes = false;
            $maxLength = $options['maxLength'];
            $maxBytes = $options['maxBytes'];
            $emptyValue = $options['emptyValue'];
            $blacklist = $options['blacklist'];
            $quotelist = $options['quotelist'];

            if ($emptyValue === '' && $options['quoteEmpty']) {
                $emptyValue = '""';
            }

            if (count($options['whitelist'])) {
                $blacklist = array_diff($blacklist, $options['whitelist']);
                $quotelist = array_merge($quotelist, array_intersect($options['whitelist'], $options['blacklist']));
            }

            if (!is_string($value)) {
                $value = $this->string($value);
            }
            $value = trim($value);
            if (!strlen($value)) {
                return $emptyValue;
            }

            if ($value[0] === '"' || $value[0] === "'") {
                $hadQuotes = substr($value, -1) === $value[0] ? $value[0] : false;
            }

            $value = str_replace($blacklist, ' ', $value);
            $value = trim($value);
            if (!strlen($value)) {
                return $emptyValue;
            }

            $whitespace = $this->getWhitespaceArray(false);
            $value = trim(str_replace($whitespace, ($options['allowSpace'] ? ' ' : ''), $value));
            if (!strlen($value)) {
                return $emptyValue;
            }

            if ($value[0] === "'") {
                if (substr($value, -1) === "'") {
                    $value = trim($value, "' ");
                } else {
                    $value = ltrim($value, "' ");
                }
            }

            if ($maxLength > 0 && strlen($value) > $maxLength) {
                if ($this->multibyteSupport) {
                    if (mb_strlen($value) > $maxLength) {
                        $value = mb_substr($value, 0, $maxLength, 'UTF-8');
                    }
                } else {
                    $value = substr($value, 0, $maxLength);
                }
            }

            if ($maxBytes > 0 && strlen($value) > $maxBytes) {
                if ($this->multibyteSupport) {
                    $len = mb_strlen($value);
                    while (strlen($value) > $maxBytes) {
                        $len--;
                        $value = mb_substr($value, 0, $len);
                    }
                } else {
                    $value = substr($value, 0, $maxBytes);
                }
            }

            if (!ctype_alnum(str_replace([',', ' ', '-', '_', '/', '.', "'"], '', $value))) {
                $value = preg_replace('/[^[:alnum:]\pL\pN\pP\pM\p{S} \'\/]/u', ' ', $value);
                $value = preg_replace('/\s\s+/u', ' ', $value);
            }

            $reductions = ['..' => '.', './' => ' ', '  ' => ' '];
            foreach ($reductions as $f => $r) {
                if (strpos($value, $f) === false) {
                    continue;
                }
                if (in_array($f, $options['whitelist'])) {
                    continue;
                }
                do {
                    $value = str_replace($f, $r, $value);
                } while (strpos($value, $f) !== false);
            }

            $trims = '+,';
            $value = trim($value);
            $value = trim($value, $trims);
            $value = trim($value);

            if (!strlen($value)) {
                return $emptyValue;
            }
            if (!$useQuotes) {
                return $hadQuotes && strpos($value, $hadQuotes) === false ? "$hadQuotes$value$hadQuotes" : $value;
            }

            if (!$needsQuotes) {
                $needsQuotes = $hadQuotes ? true : false;
            }

            if (!$needsQuotes) {
                foreach ($quotelist as $char) {
                    if (strpos($value, $char) === false) {
                        continue;
                    }
                    $needsQuotes = true;
                    break;
                }
            }

            if (!$needsQuotes) {
                $a = substr($value, 0, 1);
                $b = substr($value, -1);
                if (!ctype_alnum($a) && $a !== '/') {
                    $needsQuotes = true;
                } elseif (!ctype_alnum($b) && $b !== '/') {
                    $needsQuotes = true;
                } elseif ($a === '/') {
                    $needsQuotes = !ctype_alnum(str_replace(['/', '-', '_', '.'], '', $value));
                }
            }

            if ($needsQuotes) {
                $value = '"' . $value . '"';
            }

            return $value;
        }
    }

    // ---------------------------------------------------------------------
    // Global-namespaced `wire()` helper (ProcessWire\wire), imported by the real module
    // files via `use function ProcessWire\wire;`. Mirrors Wire::wire() above: an object
    // argument is registration (returned as-is for our purposes), a string argument looks
    // up a named service.
    // ---------------------------------------------------------------------

    function wire($name = null)
    {
        if ($name === null) {
            return new ProcessWireApiProxy();
        }
        if (is_object($name)) {
            return $name;
        }
        return TestServices::get((string)$name);
    }

    /** ProcessWire's global gettext-style translation helpers - passthrough for tests */
    function __(string $text, ?string $textdomain = null, ?string $context = null): string
    {
        return $text;
    }

    function _n(string $textSingular, string $textPlural, $count, ?string $textdomain = null): string
    {
        return $count == 1 ? $textSingular : $textPlural;
    }

    function _x(string $text, string $context, ?string $textdomain = null): string
    {
        return $text;
    }
}
