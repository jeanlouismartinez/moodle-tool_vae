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
 * The page dossier.php under its heading (lot 7, MMR-520 ticket 4, wave 4b).
 *
 * Two ways out: the competencies of the site switched off (disabled()), or the dossier (dossier()): the coverage, what the
 * vaeiste says is outside their activity, the declarations (the sentence that there is none yet, or the legend of the states
 * and a block for each), the positionings of the structure, then the buttons. The page builds each piece with its arguments
 * of before and gives each button by its address, null for a button it does not offer: every condition and every read
 * stays in the page, the words and the methods are the class's.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dossier_page implements named_templatable, renderable {
    /** @var array[] The buttons, in their order: key => [key of the words, method]. */
    private const BUTTONS = [
        'declare' => ['declareheading', 'get'],
        'pathway' => ['orgtabmine', 'get'],
        'export' => ['exportdossier', 'get'],
        'faisabilite' => ['faisabheading', 'get'],
        'validation' => ['validationtitle', 'get'],
        'validationread' => ['validationreadlink', 'get'],
        'situations' => ['situationslink', 'get'],
        'situationsread' => ['situationsreadlink', 'get'],
        'consent' => ['consentlink', 'get'],
        'deposit' => ['depositheading', 'get'],
        'journal' => ['reflexiontitle', 'get'],
        'validationdeposit' => ['validationdepositheading', 'get'],
        // The editable copies are posted: the button then carries the session key that document.php requires.
        'docx' => ['documentdocx', 'post'],
        'odt' => ['documentodt', 'post'],
        'backup' => ['backupdossier', 'get'],
        'import' => ['importdossier', 'get'],
    ];

    /** @var bool Whether the competencies are switched off. */
    private bool $disabled;

    /** @var coverage|null The coverage, or null when it is not drawn. */
    private ?coverage $coverage;

    /** @var scope_box|null What the vaeiste says is outside their activity. */
    private ?scope_box $scope;

    /** @var dossier_claim[] A block per declaration. */
    private array $claims;

    /** @var positioning_section|null The positionings of the structure. */
    private ?positioning_section $positioning;

    /** @var array Key of BUTTONS => address, or null. */
    private array $buttons;

    /**
     * The page.
     *
     * @param bool $disabled
     * @param coverage|null $coverage
     * @param scope_box|null $scope
     * @param dossier_claim[] $claims
     * @param positioning_section|null $positioning
     * @param array $buttons
     */
    private function __construct(
        bool $disabled,
        ?coverage $coverage,
        ?scope_box $scope,
        array $claims,
        ?positioning_section $positioning,
        array $buttons
    ) {
        $this->disabled = $disabled;
        $this->coverage = $coverage;
        $this->scope = $scope;
        $this->claims = $claims;
        $this->positioning = $positioning;
        $this->buttons = $buttons;
    }

    /**
     * The competencies of the site switched off: the error of the core, and nothing else.
     *
     * @return self
     */
    public static function disabled(): self {
        return new self(true, null, null, [], null, []);
    }

    /**
     * The dossier.
     *
     * @param coverage|null $coverage The coverage, or null when the page does not draw it.
     * @param scope_box $scope What the vaeiste says is outside their activity (T-42).
     * @param dossier_claim[] $claims A block per declaration, in their order; none: the sentence that there is none yet.
     * @param positioning_section $positioning The positionings of the structure (T-90).
     * @param array $buttons Key of BUTTONS => address, or null for a button the page does not offer.
     * @return self
     */
    public static function dossier(
        ?coverage $coverage,
        scope_box $scope,
        array $claims,
        positioning_section $positioning,
        array $buttons
    ): self {
        return new self(false, $coverage, $scope, $claims, $positioning, $buttons);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/dossier_page';
    }

    /**
     * The context of the template: each piece drawn by its renderer, the notifications and the buttons by the core.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        if ($this->disabled) {
            return [
                'disabledhtml' => $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    \core\output\notification::NOTIFY_ERROR
                ),
                'dossier' => null,
            ];
        }

        $claims = [];
        foreach ($this->claims as $claim) {
            $claims[] = ['claimhtml' => $output->render($claim)];
        }
        $buttons = [];
        foreach (self::BUTTONS as $key => [$words, $method]) {
            if (($this->buttons[$key] ?? null) !== null) {
                $buttons[] = [
                    'buttonhtml' => $output->single_button($this->buttons[$key], get_string($words, 'tool_vae'), $method),
                ];
            }
        }

        return [
            'disabledhtml' => '',
            'dossier' => [
                'coveragehtml' => $this->coverage !== null ? $output->render($this->coverage) : '',
                'scopehtml' => $output->render($this->scope),
                'nonehtml' => $claims === []
                    ? $output->notification(get_string('nodeclarationyet', 'tool_vae'), \core\output\notification::NOTIFY_INFO)
                    : '',
                'legendhtml' => $claims === [] ? '' : $output->render(new state_legend()),
                'claims' => $claims,
                'positioninghtml' => $output->render($this->positioning),
                'buttons' => $buttons,
            ],
        ];
    }
}
