<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_vae\local;

/**
 * Validation of a JSON document against the schema shipped in docs/schema.
 *
 * WHY NOT A LIBRARY. Moodle ships no JSON Schema validator, and the reimport cannot rely on one
 * being installed. WHY NOT RULES WRITTEN IN PHP. A second statement of the schema's rules would
 * drift from the first in silence. This class therefore READS the schema and interprets it: the
 * rules live in one place, docs/schema, and the test runs every counter-example of the schema's
 * own test set through it, so that it refuses exactly what ajv refuses in continuous integration.
 *
 * A SUBSET, AND IT SAYS SO. Only the keywords the schema uses are implemented. Any other keyword
 * met in a schema is a coding_exception, never a silent pass: a schema that grows a keyword this
 * class does not know must break the tests, not wave invalid claims through.
 *
 * Objects are expected as json_decode() returns them without its second argument - stdClass -
 * so that an empty object and an empty array stay distinct.
 *
 * AN ERROR IS DATA FIRST, A SENTENCE SECOND (lot 9 of review MMR-520). errors() gives each error as the JSON pointer of the
 * value, the rule it breaks and the values of the sentence; validate() turns them into sentences of the language file, in
 * the language of the person the page is for (message()), because those sentences reach the screen: the import of an
 * archive shows the first one when it refuses the archive. Who needs the pointer (the exporter, to name the faulty field)
 * reads it from errors(), never out of a sentence. A schema this class cannot read is still a coding_exception, in
 * English: that one is for a developer.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema_validator {
    /** @var string[] Keywords that describe and constrain nothing. */
    private const ANNOTATIONS = ['$schema', '$id', '$comment', '$defs', 'title', 'description'];

    /** @var string Directory holding the schema files. */
    private $directory;

    /** @var \stdClass[] Schema files already read, by path relative to the directory. */
    private $documents = [];

    /**
     * Constructor.
     *
     * @param string|null $directory Only passed by tests; the plugin's docs/schema otherwise.
     */
    public function __construct(?string $directory = null) {
        global $CFG;

        $this->directory = $directory ?? $CFG->dirroot . '/admin/tool/vae/docs/schema';
    }

    /**
     * The errors of a document against a schema file, as sentences, empty when it conforms.
     *
     * @param mixed $data The decoded document.
     * @param string $file The schema, relative to the schema directory.
     * @return string[] One sentence per error, in the language of the page, each naming the JSON pointer of the value.
     */
    public function validate($data, string $file = 'preuve.schema.json'): array {
        return array_map([self::class, 'message'], $this->errors($data, $file));
    }

    /**
     * The errors of a document against a schema file, as data, empty when it conforms.
     *
     * @param mixed $data The decoded document.
     * @param string $file The schema, relative to the schema directory.
     * @return array[] One entry per error: `pointer`, the JSON pointer of the value (/name/0/name, no escaping); `rule`, the
     *                 end of the name of its sentence in the language file (schemaerror<rule>); `a`, the values of the sentence.
     */
    public function errors($data, string $file = 'preuve.schema.json'): array {
        return $this->check($data, $this->document($file), $file, '');
    }

    /**
     * The sentence of one error, in the language of the page.
     *
     * @param array $error As errors() gives it.
     * @return string
     */
    public static function message(array $error): string {
        return get_string('schemaerror' . $error['rule'], 'tool_vae', (object) $error['a']);
    }

    /**
     * One error, as errors() gives it.
     *
     * @param string $rule The end of the name of its sentence (schemaerror<rule>).
     * @param string $pointer Where the value sits in the document.
     * @param array $values The other values of the sentence, besides the pointer.
     * @return array
     */
    private static function error(string $rule, string $pointer, array $values = []): array {
        return ['pointer' => $pointer, 'rule' => $rule, 'a' => ['pointer' => $pointer] + $values];
    }

    /**
     * Check one value against one schema.
     *
     * @param mixed $data
     * @param mixed $schema
     * @param string $file The file the schema comes from, to resolve its references.
     * @param string $pointer Where the value sits in the document.
     * @return array[] As errors() gives them.
     */
    private function check($data, $schema, string $file, string $pointer): array {
        if ($schema === true) {
            return [];
        }
        if ($schema === false) {
            return [self::error('false', $pointer)];
        }
        if (!$schema instanceof \stdClass) {
            throw new \coding_exception("Malformed schema in $file at $pointer");
        }

        $errors = [];
        foreach (get_object_vars($schema) as $keyword => $rule) {
            if (in_array($keyword, self::ANNOTATIONS, true)) {
                continue;
            }
            switch ($keyword) {
                case '$ref':
                    [$target, $targetfile] = $this->resolve($rule, $file);
                    $errors = array_merge($errors, $this->check($data, $target, $targetfile, $pointer));
                    break;
                case 'type':
                    if (!self::has_type($data, $rule)) {
                        // Nothing else holds for a value of the wrong type.
                        return [self::error('type', $pointer, ['type' => $rule])];
                    }
                    break;
                case 'enum':
                    if (!in_array($data, $rule, true)) {
                        $errors[] = self::error('enum', $pointer);
                    }
                    break;
                case 'const':
                    if ($data !== $rule) {
                        $errors[] = self::error('const', $pointer, ['value' => json_encode($rule)]);
                    }
                    break;
                case 'pattern':
                    // D: an ECMAScript $ never matches before a final newline; PHP's does without it.
                    if (is_string($data) && !preg_match('/' . str_replace('/', '\/', $rule) . '/uD', $data)) {
                        $errors[] = self::error('pattern', $pointer, ['pattern' => $rule]);
                    }
                    break;
                case 'minLength':
                    if (is_string($data) && \core_text::strlen($data) < $rule) {
                        $errors[] = self::error('minlength', $pointer, ['limit' => $rule]);
                    }
                    break;
                case 'maxLength':
                    if (is_string($data) && \core_text::strlen($data) > $rule) {
                        $errors[] = self::error('maxlength', $pointer, ['limit' => $rule]);
                    }
                    break;
                case 'minimum':
                    if ((is_int($data) || is_float($data)) && $data < $rule) {
                        $errors[] = self::error('minimum', $pointer, ['limit' => $rule]);
                    }
                    break;
                case 'maximum':
                    if ((is_int($data) || is_float($data)) && $data > $rule) {
                        $errors[] = self::error('maximum', $pointer, ['limit' => $rule]);
                    }
                    break;
                case 'format':
                    if ($rule !== 'date-time') {
                        throw new \coding_exception("Unsupported format $rule in $file");
                    }
                    if (is_string($data) && !self::is_date_time($data)) {
                        $errors[] = self::error('datetime', $pointer);
                    }
                    break;
                case 'required':
                    if ($data instanceof \stdClass) {
                        foreach ($rule as $name) {
                            if (!property_exists($data, $name)) {
                                $errors[] = self::error('required', "$pointer/$name");
                            }
                        }
                    }
                    break;
                case 'properties':
                    if ($data instanceof \stdClass) {
                        foreach (get_object_vars($rule) as $name => $subschema) {
                            if (property_exists($data, $name)) {
                                $errors = array_merge(
                                    $errors,
                                    $this->check($data->$name, $subschema, $file, "$pointer/$name")
                                );
                            }
                        }
                    }
                    break;
                case 'additionalProperties':
                    if ($data instanceof \stdClass) {
                        $known = isset($schema->properties) ? array_keys(get_object_vars($schema->properties)) : [];
                        foreach (get_object_vars($data) as $name => $value) {
                            if (!in_array($name, $known, true)) {
                                $errors = array_merge($errors, $this->check($value, $rule, $file, "$pointer/$name"));
                            }
                        }
                    }
                    break;
                case 'items':
                    if (is_array($data)) {
                        foreach ($data as $index => $item) {
                            $errors = array_merge($errors, $this->check($item, $rule, $file, "$pointer/$index"));
                        }
                    }
                    break;
                case 'anyOf':
                case 'oneOf':
                    $passing = 0;
                    foreach ($rule as $subschema) {
                        if (!$this->check($data, $subschema, $file, $pointer)) {
                            $passing++;
                        }
                    }
                    if ($keyword === 'anyOf' ? $passing === 0 : $passing !== 1) {
                        $errors[] = self::error($passing ? 'shapesmany' : 'shapesnone', $pointer);
                    }
                    break;
                default:
                    throw new \coding_exception("Unsupported schema keyword $keyword in $file");
            }
        }

        return $errors;
    }

    /**
     * The schema a reference points to.
     *
     * Handles what the schema writes: a file relative to the current one, a fragment of the
     * current one (#/$defs/name), or both.
     *
     * @param string $ref
     * @param string $file The file holding the reference.
     * @return array{0: mixed, 1: string} The target schema, and the file it lives in.
     */
    private function resolve(string $ref, string $file): array {
        [$path, $fragment] = array_pad(explode('#', $ref, 2), 2, '');

        if ($path !== '') {
            $base = dirname($file);
            $file = $base === '.' ? $path : $base . '/' . $path;
        }

        $target = $this->document($file);
        foreach (array_filter(explode('/', $fragment), 'strlen') as $part) {
            $part = str_replace(['~1', '~0'], ['/', '~'], $part);
            if (!$target instanceof \stdClass || !property_exists($target, $part)) {
                throw new \coding_exception("Unresolvable reference $ref in $file");
            }
            $target = $target->$part;
        }

        return [$target, $file];
    }

    /**
     * One schema file, read once.
     *
     * @param string $file Relative to the schema directory.
     * @return \stdClass
     */
    private function document(string $file): \stdClass {
        if (strpos($file, '..') !== false) {
            throw new \coding_exception("Schema reference leaves the schema directory: $file");
        }

        if (!isset($this->documents[$file])) {
            $path = $this->directory . '/' . $file;
            $content = is_readable($path) ? file_get_contents($path) : false;
            if ($content === false) {
                throw new \coding_exception("Schema file not found: $file");
            }
            $this->documents[$file] = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        }

        return $this->documents[$file];
    }

    /**
     * Whether a value has a JSON type.
     *
     * @param mixed $data
     * @param string $type
     * @return bool
     */
    private static function has_type($data, string $type): bool {
        switch ($type) {
            case 'string':
                return is_string($data);
            case 'object':
                return $data instanceof \stdClass;
            case 'array':
                return is_array($data);
            case 'integer':
                return is_int($data);
            case 'number':
                return is_int($data) || is_float($data);
            case 'boolean':
                return is_bool($data);
            case 'null':
                return $data === null;
        }

        throw new \coding_exception("Unsupported schema type $type");
    }

    /**
     * Whether a string is an RFC 3339 date-time, as ajv-formats checks it.
     *
     * A date alone is refused: the schema writes every instant with its time and offset, and a
     * bare date drifts by a day across time zones.
     *
     * @param string $value
     * @return bool
     */
    public static function is_date_time(string $value): bool {
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})[Tt](\d{2}):(\d{2}):(\d{2})(\.\d+)?([Zz]|[+-](\d{2}):(\d{2}))$/D';
        if (!preg_match($pattern, $value, $m)) {
            return false;
        }

        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return false;
        }
        if ((int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 60) {
            return false;
        }

        return !isset($m[9]) || ((int) $m[9] <= 23 && (int) $m[10] <= 59);
    }
}
