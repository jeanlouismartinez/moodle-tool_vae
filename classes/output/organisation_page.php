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
 * The page organisation.php under its heading: the link "Help", the tabs, and the tab that is open (T-90; lot 7, MMR-520
 * ticket 4, wave 4b).
 *
 * One named constructor per way out of the page, in the order the page draws them: the tab of the site (site()), the
 * competencies switched off (disabled()), the tab of the followed vaeistes (followed()), the tab "My pathway" (mine()). Each
 * receives the pieces the page built with the arguments it gave the old screen, and draws them in the order the page drew
 * them; the reads and the rights stay in the page. The buttons of "My pathway" are given by their addresses, null for a
 * button the page does not offer: each condition is the page's, the words are the class's.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_page implements named_templatable, renderable {
    /** @var string[] The buttons of "My pathway", in their order: key => key of the words. Each is a GET. */
    private const BUTTONS = [
        'declare' => 'declareheading',
        'selfpos' => 'selfposlink',
        'situations' => 'situationslink',
        'dossier' => 'mydossier',
        'validation' => 'validationtitle',
    ];

    /** @var organisation_guide_link The link "Help" of the tab. */
    private organisation_guide_link $guide;

    /** @var \tabobject[] The tabs the reader may open (organisation_screen::tabs()). */
    private array $tabs;

    /** @var string The tab that is open. */
    private string $tab;

    /** @var bool Whether the competencies are switched off (the notification of the core instead of a tab). */
    private bool $disabled;

    /** @var renderable[] The pieces of the tab, in their order. */
    private array $parts;

    /** @var array Key of BUTTONS => address, or null for a button not offered. */
    private array $buttons;

    /**
     * The page.
     *
     * @param organisation_guide_link $guide
     * @param \tabobject[] $tabs
     * @param string $tab
     * @param bool $disabled
     * @param renderable[] $parts
     * @param array $buttons
     */
    private function __construct(
        organisation_guide_link $guide,
        array $tabs,
        string $tab,
        bool $disabled,
        array $parts,
        array $buttons
    ) {
        $this->guide = $guide;
        $this->tabs = $tabs;
        $this->tab = $tab;
        $this->disabled = $disabled;
        $this->parts = $parts;
        $this->buttons = $buttons;
    }

    /**
     * The tab of the site: the sentence that introduces it, then the configuration of the scheme.
     *
     * @param organisation_guide_link $guide The link "Help" of the tab.
     * @param \tabobject[] $tabs The tabs the reader may open.
     * @param string $tab The tab that is open.
     * @param organisation_site $site The configuration of the scheme.
     * @return self
     */
    public static function site(organisation_guide_link $guide, array $tabs, string $tab, organisation_site $site): self {
        return new self($guide, $tabs, $tab, false, [new organisation_notice(get_string('orgintrosite', 'tool_vae')), $site], []);
    }

    /**
     * The competencies of the site switched off: the error of the core, under the tabs.
     *
     * @param organisation_guide_link $guide The link "Help" of the tab.
     * @param \tabobject[] $tabs The tabs the reader may open.
     * @param string $tab The tab that is open.
     * @return self
     */
    public static function disabled(organisation_guide_link $guide, array $tabs, string $tab): self {
        return new self($guide, $tabs, $tab, true, [], []);
    }

    /**
     * The tab of the followed vaeistes: what the manager can do now, the sentence that introduces the tab, the table.
     *
     * @param organisation_guide_link $guide The link "Help" of the tab.
     * @param \tabobject[] $tabs The tabs the reader may open.
     * @param string $tab The tab that is open.
     * @param next_actions $actions What the manager can do now (lot S2).
     * @param organisation_followed $followed The followed vaeistes.
     * @return self
     */
    public static function followed(
        organisation_guide_link $guide,
        array $tabs,
        string $tab,
        next_actions $actions,
        organisation_followed $followed
    ): self {
        $intro = new organisation_notice(get_string('orgintrofollowed', 'tool_vae'));

        return new self($guide, $tabs, $tab, false, [$actions, $intro, $followed], []);
    }

    /**
     * The tab "My pathway": what the vaeiste can do now, the body the page chose (the sentence of an empty pathway, or the
     * introduction and the tables), what the vaeiste says is outside their activity, and the buttons.
     *
     * @param organisation_guide_link $guide The link "Help" of the tab.
     * @param \tabobject[] $tabs The tabs the reader may open.
     * @param string $tab The tab that is open.
     * @param next_actions $actions What the vaeiste can do now (lot S2).
     * @param renderable[] $body organisation_notice and organisation_pathway, as the page chose them.
     * @param scope_box $scope What the vaeiste says is outside their activity (T-42).
     * @param array $buttons Key of BUTTONS => address, or null for a button the page does not offer.
     * @return self
     */
    public static function mine(
        organisation_guide_link $guide,
        array $tabs,
        string $tab,
        next_actions $actions,
        array $body,
        scope_box $scope,
        array $buttons
    ): self {
        return new self($guide, $tabs, $tab, false, array_merge([$actions], $body, [$scope]), $buttons);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_page';
    }

    /**
     * The context of the template: the link, the tabs, the error, the pieces and the buttons, each drawn by its renderer.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $parts = [];
        foreach ($this->parts as $part) {
            $parts[] = ['parthtml' => $output->render($part)];
        }
        $buttons = [];
        foreach (self::BUTTONS as $key => $words) {
            if (($this->buttons[$key] ?? null) !== null) {
                $buttons[] = [
                    'buttonhtml' => $output->single_button($this->buttons[$key], get_string($words, 'tool_vae'), 'get'),
                ];
            }
        }

        return [
            'guidehtml' => $output->render($this->guide),
            'tabshtml' => $output->tabtree($this->tabs, $this->tab),
            'noticehtml' => $this->disabled
                ? $output->notification(
                    get_string('errorcompetenciesdisabled', 'tool_vae'),
                    \core\output\notification::NOTIFY_ERROR
                )
                : '',
            'parts' => $parts,
            'buttons' => $buttons,
        ];
    }
}
