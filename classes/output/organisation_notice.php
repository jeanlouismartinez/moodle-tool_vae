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

use core\output\named_templatable;
use core\output\renderable;
use core\output\renderer_base;

/**
 * A sentence of the page organisation.php in an information box that cannot be closed (lot 7, MMR-520 ticket 4, wave 4b).
 *
 * Not a notification of the core: it is dismissed with a click and does not come back, and the sentences of this page say
 * what the figures do not say ("the panel alone decides"), which must not disappear with one. Was
 * organisation_screen::notice(); nothing() was organisation_screen::nothing().
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_notice implements named_templatable, renderable {
    /** @var string The sentence, plain text. */
    private string $text;

    /**
     * The box of a sentence.
     *
     * @param string $text Plain text, not escaped.
     */
    public function __construct(string $text) {
        $this->text = $text;
    }

    /**
     * The sentence for a pathway with nothing in it: no declaration, and no record a plan names.
     *
     * Told apart from a reader whose plans are hidden by the site: "no plan names a record" would be false, since the plans
     * are there and only unreadable.
     *
     * @param \stdClass $pathway What organisation::for_candidate() gives.
     * @return self
     */
    public static function nothing(\stdClass $pathway): self {
        return new self(get_string($pathway->plansreadable ? 'orgnofiche' : 'orgnoficheunreadable', 'tool_vae'));
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_notice';
    }

    /**
     * The context of the template: the sentence, plain.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        return ['text' => $this->text];
    }
}
