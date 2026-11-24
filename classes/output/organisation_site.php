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
 * The configuration of the scheme, on the tab "Organisation" of organisation.php (T-90; lot 7, MMR-520 ticket 4, wave 4b).
 *
 * The records and their surroundings, then the rights and what stands behind them: the grid of the actors, the rights of
 * the plugin and the roles that give, withhold or forbid each, the cohorts followed through those roles and the settings.
 * SITE: nothing here is a person but the referents of a bloc. The number of plans is drawn as it comes, with the sentence
 * that says what it counts. A right with no role is a line with the word "no role", never an omitted line; a record whose
 * framework is gone is a line with dashes, never an omitted one. Was organisation_screen::site(), site_fiches(),
 * site_actors(), site_capabilities(), site_cohorts() and site_settings().
 *
 * The names of the roles come from role_get_names(): they are HTML already, and escaping them again would show an "&amp;"
 * of a name as the letters of the entity; the name of a cohort is shown as the core shows it (format_string()).
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class organisation_site implements named_templatable, renderable {
    /** @var \stdClass What organisation::site() gives. */
    private \stdClass $data;

    /**
     * The configuration of the scheme.
     *
     * @param \stdClass $data What organisation::site() gives.
     */
    public function __construct(\stdClass $data) {
        $this->data = $data;
    }

    /**
     * The template of the piece.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/organisation_site';
    }

    /**
     * The context of the template: the headings and sentences, and each part drawn by its piece.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = $this->data;
        $fiches = ['noticehtml' => '', 'tablehtml' => '', 'note' => null];
        if ($data->fiches === null) {
            $fiches['noticehtml'] = $output->render(new organisation_notice(get_string('errorcompetenciesdisabled', 'tool_vae')));
        } else if (!$data->fiches) {
            $fiches['noticehtml'] = $output->render(new organisation_notice(get_string('orgsitenofiche', 'tool_vae')));
        } else {
            $fiches['tablehtml'] = $output->render(new table($this->records($data->fiches)));
            $fiches['note'] = ['label' => get_string('orgplansnote', 'tool_vae')];
        }
        [$actors, $notes] = $this->actors($data->powers->actors);
        $cohorts = $data->powers->cohorts;

        return [
            'ficheheading' => get_string('orgcolfiches', 'tool_vae'),
            'fiches' => $fiches,
            'powersheading' => get_string('orgpowersheading', 'tool_vae'),
            'rolesatsite' => get_string('orgrolesatsite', 'tool_vae'),
            'actorsheading' => get_string('orgactorsheading', 'tool_vae'),
            'actorsintro' => get_string('orgactorsintro', 'tool_vae'),
            'actorshtml' => $output->render(new table($actors)),
            'notes' => $notes ? ['items' => $notes] : null,
            'capabilitiesheading' => get_string('orgactorcapheading', 'tool_vae'),
            'capabilitieshtml' => $output->render(new table($this->capabilities($data->powers->capabilities))),
            'cohortsheading' => get_string('orgcohortsheading', 'tool_vae'),
            'cohorts' => [
                'noticehtml' => $cohorts === null
                    ? $output->render(new organisation_notice(get_string('orgcohortsunavailable', 'tool_vae')))
                    : '',
                'none' => $cohorts !== null && !$cohorts ? ['label' => get_string('orgnone', 'tool_vae')] : null,
                'tablehtml' => $cohorts ? $output->render(new table($this->cohorts($cohorts))) : '',
            ],
            'settingsheading' => get_string('orgsettingsheading', 'tool_vae'),
            'settingshtml' => $output->render(new table($this->settings($data->powers->settings))),
        ];
    }

    /**
     * The table of the records of the site.
     *
     * @param \stdClass[] $fiches As organisation::site() gives them.
     * @return \html_table
     */
    private function records(array $fiches): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-site-fiches';
        $table->caption = get_string('orgsitefiches', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('orgcolfiches', 'tool_vae'),
            get_string('orgcolblocs', 'tool_vae'),
            get_string('orgcolcompetencies', 'tool_vae'),
            get_string('orgcolcourses', 'tool_vae'),
            get_string('orgcolactivities', 'tool_vae'),
            get_string('orgcolmodels', 'tool_vae'),
            get_string('orgcolplancount', 'tool_vae'),
            get_string('orgcolreferents', 'tool_vae'),
            get_string('orgcolrules', 'tool_vae'),
        ];
        $dash = get_string('orgnoblocs', 'tool_vae');
        foreach ($fiches as $fiche) {
            $head = new \html_table_cell(organisation_cell::record_title($fiche));
            $head->header = true;
            $head->scope = 'row';
            $table->data[] = new \html_table_row([
                $head,
                $fiche->blocs === null || $fiche->blocs === 0
                    ? new cell_content([cell_content::text($dash)])
                    : (string) $fiche->blocs,
                $fiche->competencies === null ? new cell_content([cell_content::text($dash)]) : (string) $fiche->competencies,
                organisation_cell::courses($fiche->courses, $fiche->coursesunreadable),
                organisation_cell::activity_count($fiche->activities, $fiche->activitiesunreadable, $fiche->fichecode),
                organisation_cell::models($fiche->models),
                (string) $fiche->plans,
                organisation_cell::referents($fiche->referents),
                organisation_cell::rules($fiche->rules),
            ]);
        }

        return $table;
    }

    /**
     * The grid of the actors: one line per right, the roles that give it, and the word that says when none does; and the
     * actors without an account, a sentence each, for the list below the grid.
     *
     * @param \stdClass[] $actors As actors::grid() gives them.
     * @return array The table, and the items of the list ({name, note} each).
     */
    private function actors(array $actors): array {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-site-actors';
        $table->caption = get_string('orgactorcaption', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('orgactorcolactor', 'tool_vae'),
            get_string('orgactorcolright', 'tool_vae'),
            get_string('orgactorcolroles', 'tool_vae'),
        ];
        $notes = [];
        foreach ($actors as $actor) {
            $name = get_string('orgactor' . $actor->key, 'tool_vae');
            if (!$actor->account) {
                $notes[] = ['name' => $name, 'note' => get_string('orgactor' . $actor->key . 'note', 'tool_vae')];
                continue;
            }
            foreach ($actor->rights as $right) {
                $who = new \html_table_cell(new cell_content([cell_content::text($name)]));
                $who->header = true;
                $who->scope = 'row';
                $table->data[] = new \html_table_row([
                    $who,
                    organisation_cell::right($right->label, $right->name),
                    organisation_cell::actor_roles($right),
                ]);
            }
        }

        return [$table, $notes];
    }

    /**
     * The rights of the plugin and the roles of the site that give, withhold or forbid each.
     *
     * @param \stdClass[] $capabilities name, label, allow, prevent, prohibit.
     * @return \html_table
     */
    private function capabilities(array $capabilities): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-site-powers';
        $table->caption = get_string('orgsitepowers', 'tool_vae');
        $table->captionhide = true;
        $table->head = [
            get_string('orgcolcapability', 'tool_vae'),
            get_string('orgcolroles', 'tool_vae'),
            get_string('orgcolrolesprevent', 'tool_vae'),
            get_string('orgcolrolesprohibit', 'tool_vae'),
        ];
        foreach ($capabilities as $capability) {
            $head = new \html_table_cell(organisation_cell::right($capability->label, $capability->name));
            $head->header = true;
            $head->scope = 'row';
            $table->data[] = new \html_table_row([
                $head,
                organisation_cell::role_names($capability->allow, 'orgnorole'),
                organisation_cell::role_names($capability->prevent, 'orgnone'),
                organisation_cell::role_names($capability->prohibit, 'orgnone'),
            ]);
        }

        return $table;
    }

    /**
     * The cohorts whose members a manager follows through a role of the plugin.
     *
     * @param \stdClass[] $cohorts cohortid, name (or null), roleid, rolename.
     * @return \html_table
     */
    private function cohorts(array $cohorts): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-site-cohorts';
        $table->caption = get_string('orgcohortsheading', 'tool_vae');
        $table->captionhide = true;
        $table->head = [get_string('orgcolcohort', 'tool_vae'), get_string('orgcolcohortrole', 'tool_vae')];
        foreach ($cohorts as $cohort) {
            $name = new \html_table_cell(new cell_content([$cohort->name === null
                ? cell_content::text(get_string('orgcohortgone', 'tool_vae'))
                : cell_content::html(format_string($cohort->name, true, ['context' => \context_system::instance()]))]));
            $name->header = true;
            $name->scope = 'row';
            // The name of the role has been through format_string() already (role_get_names()).
            $table->data[] = new \html_table_row([$name, new cell_content([cell_content::html($cohort->rolename)])]);
        }

        return $table;
    }

    /**
     * The settings that open or close a part of the scheme, each with its state.
     *
     * @param \stdClass[] $settings name, value, open.
     * @return \html_table
     */
    private function settings(array $settings): \html_table {
        $table = new \html_table();
        $table->attributes['class'] = 'generaltable tool-vae-organisation tool-vae-site-settings';
        $table->caption = get_string('orgsettingsheading', 'tool_vae');
        $table->captionhide = true;
        $table->head = [get_string('orgcolsetting', 'tool_vae'), get_string('orgcolstate', 'tool_vae')];
        foreach ($settings as $setting) {
            $name = new \html_table_cell(new cell_content([cell_content::text(get_string($setting->name, 'tool_vae'))]));
            $name->header = true;
            $name->scope = 'row';
            if ($setting->name === 'overduedays') {
                $state = get_string('orgclosed', 'tool_vae');
                if ($setting->open) {
                    $state = (int) $setting->value === 1
                        ? get_string('orgdayone', 'tool_vae')
                        : get_string('orgdays', 'tool_vae', $setting->value);
                }
            } else {
                $state = get_string($setting->open ? 'orgopen' : 'orgclosed', 'tool_vae');
            }
            $table->data[] = new \html_table_row([$name, new cell_content([cell_content::text($state)])]);
        }

        return $table;
    }
}
