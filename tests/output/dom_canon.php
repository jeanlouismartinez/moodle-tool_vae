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

namespace tool_vae\output;

/**
 * The canonical form of an HTML fragment, for the tests that prove a migrated screen draws the same DOM as before (lot 7).
 *
 * It follows the rules of the equivalence tool of lot 7 that matter for a fragment: parsed as UTF-8, comments dropped,
 * attributes in the order of their names, the names of a class in order, white space folded to one space and dropped next
 * to a block or at the edge of a block (kept between two inline elements, where a browser draws it), the session key and
 * the identifiers drawn at random (uniqid(), the id of a moodleform) named by their rank of first appearance. It never
 * changes a text, an element, their order, a class name or an address; a "<" that begins no tag is read as the text it is,
 * as a browser reads it, whatever the version of libxml (see dom_canon_test). One node per line, so that a difference is shown
 * where it is. It also gives the renderer of the core for the web (core()), for the "before" of a page.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait dom_canon {
    /**
     * The renderer of the core for the web, as a page has it.
     *
     * Under PHPUnit the default renderer of the core is the one of the command line, which writes a heading or a
     * notification as plain text: the "before" of a page is drawn with this one instead, and the page with the renderer of
     * the plugin for the same target, so that their tags are compared too.
     *
     * @return \core\output\core_renderer
     */
    protected static function core(): \core\output\core_renderer {
        global $PAGE;

        return $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
    }

    /**
     * A drawing of a form with the number of its draft area named, as the tool of equivalence does (rule N9): the core
     * draws a new one each time a form is built (file_get_unused_draft_itemid()).
     *
     * Kept in the pages import and import_dossier until a third page needed it (piece, lot 7 wave 2a): rule 20 of the
     * common rules of lot 7.
     *
     * @param string $html
     * @return string
     */
    protected static function drafts(string $html): string {
        preg_match_all('/<input[^>]*filepickerhidden[^>]*>/', $html, $inputs);
        foreach ($inputs[0] as $rank => $input) {
            preg_match('/value="(\d+)"/', $input, $value);
            $itemid = $value[1];
            $html = str_replace($itemid, '~DRAFT' . ($rank + 1) . '~', $html);
        }

        return $html;
    }

    /**
     * The canonical form of a fragment.
     *
     * @param string $html
     * @return string One node per line.
     */
    protected static function canon(string $html): string {
        $blocks = [
            'address', 'article', 'aside', 'blockquote', 'body', 'br', 'caption', 'col', 'colgroup', 'dd', 'details', 'dialog',
            'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'header', 'hgroup', 'hr', 'legend', 'li', 'main', 'nav', 'noscript', 'ol', 'optgroup', 'option', 'p', 'pre',
            'script', 'section', 'select', 'style', 'summary', 'table', 'tbody', 'td', 'template', 'tfoot', 'th', 'thead', 'tr',
            'ul', 'datalist',
        ];
        $void = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
        // A "<" that begins no tag is text, as a browser reads it (HTML5: not followed by a letter, "/", "!" or "?").
        // libxml 2.9.13, the one of the image of the runner of the continuous integration, reads it as the start of a tag
        // and drops the text up to the next ">"; libxml 2.9.14 reads it as text. The old code wrote such a "<" raw and the
        // templates escape it: left to the reader of the machine, the two drawings would be equal on one machine and
        // different on another. The text of a script or of a style is left alone: a reader takes it as it is.
        $html = preg_replace_callback(
            '~(<(script|style)\b.*?</\2>)|<(?![A-Za-z!/?])~is',
            fn(array $match): string => ($match[1] ?? '') !== '' ? $match[1] : '&lt;',
            $html
        );
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $tokens = [];
        $volatile = function (string $value) use (&$tokens): string {
            $value = preg_replace('/(sesskey(?:=|%3D))[A-Za-z0-9]+/', '$1*', $value);
            $name = function (array $m) use (&$tokens): string {
                $tokens[$m[0]] = $tokens[$m[0]] ?? '~ID' . (count($tokens) + 1) . '~';

                return $tokens[$m[0]];
            };
            $value = preg_replace_callback('/mform\d+_[A-Za-z0-9]{15}|yui_3_\d+_\d+_\d+_\d{10,}_\d+/', $name, $value);

            return preg_replace_callback('/(?<![0-9a-f])[0-9a-f]{13,}/', $name, $value);
        };
        $lines = [];
        $walk = function (\DOMElement $element, int $depth) use (&$walk, &$lines, $blocks, $void, $volatile): void {
            $tag = strtolower($element->tagName);
            $pad = str_repeat('  ', $depth);
            $attributes = [];
            foreach ($element->attributes as $attribute) {
                $value = $attribute->value;
                if ($attribute->name === 'class') {
                    $names = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
                    sort($names, SORT_STRING);
                    $value = implode(' ', $names);
                } else if ($attribute->name === 'value' && $element->getAttribute('name') === 'sesskey') {
                    $value = '*';
                } else {
                    $value = $volatile($value);
                }
                $attributes[$attribute->name] = $value;
            }
            ksort($attributes, SORT_STRING);
            $open = '<' . $tag;
            foreach ($attributes as $name => $value) {
                $open .= ' ' . $name . '=' . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $lines[] = $pad . $open . '>';
            if (in_array($tag, $void, true)) {
                return;
            }
            $children = iterator_to_array($element->childNodes);
            $isblock = fn($node) => $node instanceof \DOMElement && in_array(strtolower($node->tagName), $blocks, true);
            foreach ($children as $i => $child) {
                if ($child instanceof \DOMElement) {
                    $walk($child, $depth + 1);
                } else if ($child instanceof \DOMText) {
                    $text = preg_replace('/[ \t\n\r\f]+/', ' ', $child->data);
                    if ($i === 0 ? in_array($tag, $blocks, true) : $isblock($children[$i - 1])) {
                        $text = ltrim($text, ' ');
                    }
                    if ($i === count($children) - 1 ? in_array($tag, $blocks, true) : $isblock($children[$i + 1])) {
                        $text = rtrim($text, ' ');
                    }
                    if ($text !== '') {
                        $text = $tag === 'script' ? $volatile($text) : $text;
                        $lines[] = $pad . '  ' . json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
            }
            $lines[] = $pad . '</' . $tag . '>';
        };
        $body = $doc->getElementsByTagName('body')->item(0);
        $body->normalize();
        $walk($body, 0);

        return implode("\n", $lines) . "\n";
    }
}
