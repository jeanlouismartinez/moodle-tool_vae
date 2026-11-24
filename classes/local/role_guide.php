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
 * The guides of one page for each role (lot S5): the vaeiste, the manager, the direction.
 *
 * A guide is text, and all of it comes from the language strings of the plugin, in French and in English: no document of
 * guide is shipped in the tree (decision of the owner of the project), and the page serves the guide in the language of its
 * reader. This class gives the data of a guide, without HTML: a title, a sentence of introduction and sections, each with a
 * title, texts, numbered steps, texts after the steps, words with their definition, and at most one link.
 *
 * WHO READS A GUIDE: whoever may open the tab of its role on the organisation page, by the same predicates and with the
 * same refusals (organisation::can_open_tab(), organisation::require_tab()), so that the guide of the vaeiste is refused in
 * a session of "log in as" as the tab "My pathway" is. A role this class does not know is refused, never replaced by
 * another. The guides read and write no data of a person.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_guide {
    /** @var string The guide of the vaeiste, read from the tab "My pathway". */
    public const VAEISTE = 'vaeiste';

    /** @var string The guide of the manager, read from the tab "Followed candidates". */
    public const MANAGER = 'manager';

    /** @var string The guide of the direction, read from the tab "Organisation". */
    public const DIRECTION = 'direction';

    /** @var string[] Each role that has a guide, and the tab of the organisation page whose right opens it, in this order. */
    public const ROLES = [
        self::VAEISTE => organisation::TAB_MINE,
        self::MANAGER => organisation::TAB_FOLLOWED,
        self::DIRECTION => organisation::TAB_SITE,
    ];

    /**
     * The sections of each guide, in their order.
     *
     * For a section: the number of its texts, of its steps and of its notes (the texts after the steps), the words it
     * defines, and the page it links to. The key of a string is "guide<role>_<section>" for the title, then
     * "_text<n>", "_step<n>" and "_note<n>"; a word has "guideword_<word>" and "guidedefinition_<word>". The section of the
     * words has the common title "guidewords".
     */
    private const SECTIONS = [
        self::VAEISTE => [
            'find' => ['texts' => 3],
            'declare' => ['steps' => 5, 'notes' => 2],
            'write' => ['texts' => 1, 'steps' => 4, 'notes' => 3],
            'precision' => ['texts' => 1, 'steps' => 2, 'notes' => 1],
            'export' => ['texts' => 1, 'steps' => 3, 'notes' => 4],
            'sees' => ['texts' => 5],
            'words' => ['words' => [
                'vae', 'declaration', 'attestation', 'fiche', 'faisabilite', 'version', 'depot', 'precision', 'archive',
            ]],
        ],
        self::MANAGER => [
            'find' => ['texts' => 6],
            'read' => ['texts' => 3],
            'attest' => ['steps' => 5, 'notes' => 3],
            'precision' => ['texts' => 1, 'steps' => 2, 'notes' => 2],
            'opinion' => ['texts' => 1, 'steps' => 3, 'notes' => 3],
            'words' => ['words' => [
                'vaeiste', 'suivre', 'declaration', 'attestation', 'precision', 'depot', 'avis', 'fiche',
            ]],
        ],
        self::DIRECTION => [
            'site' => ['texts' => 5, 'notes' => 1],
            'pending' => ['texts' => 3, 'link' => ['/admin/tool/vae/pending.php', 'pendingheading']],
            'reads' => ['texts' => 3],
            'doesnot' => ['texts' => 4],
            'words' => ['words' => [
                'vaeiste', 'responsable', 'role', 'cohorte', 'fiche', 'declaration', 'attestation', 'depot',
            ]],
        ],
    ];

    /** @var string[] The strings every guide shares, besides the words. */
    private const COMMON = ['guideback', 'guidemore', 'guideunknown', 'guidewords'];

    /**
     * The role an address names: the French word for the manager ("responsable") is the manager, the code names are themselves.
     *
     * The message of a refused guide says "the vaeiste, the manager and the direction", so the word a reader types for the
     * manager must open the guide of the manager. Nothing else is translated, and a word the page does not know is returned
     * as typed, so that it is refused as an unknown role and never replaced by another.
     *
     * @param string $name The value of the parameter "role" of the address.
     * @return string A role that has a guide, or the word as typed.
     */
    public static function named(string $name): string {
        return $name === 'responsable' ? self::MANAGER : $name;
    }

    /**
     * The role whose guide goes with a tab of the organisation page.
     *
     * @param string $tab organisation::TAB_*.
     * @return string|null The role, or null for a tab that has no guide.
     */
    public static function role_of_tab(string $tab): ?string {
        $role = array_search($tab, self::ROLES, true);

        return $role === false ? null : $role;
    }

    /**
     * Whether the current user may open the guide of a role: exactly when they may open the tab of that role.
     *
     * @param string $role
     * @return bool False for a role that has no guide.
     */
    public static function can_open(string $role): bool {
        if (!array_key_exists($role, self::ROLES)) {
            return false;
        }

        return organisation::can_open_tab(self::ROLES[$role]);
    }

    /**
     * Refuse the guide of a role the current user may not open, with the refusal of its tab; refuse a role that has no guide.
     *
     * @param string $role
     * @throws \moodle_exception guideunknown, for a role that has no guide.
     * @throws \required_capability_exception For a guide whose tab the current user may not open.
     */
    public static function require_open(string $role): void {
        if (!array_key_exists($role, self::ROLES)) {
            throw new \moodle_exception('guideunknown', 'tool_vae');
        }
        organisation::require_tab(self::ROLES[$role]);
    }

    /**
     * The address of the guide of a role.
     *
     * @param string $role A role that has a guide.
     * @return \moodle_url
     */
    public static function url(string $role): \moodle_url {
        return new \moodle_url('/admin/tool/vae/guide.php', ['role' => $role]);
    }

    /**
     * The data of the guide of a role, in the language of the reader.
     *
     * @param string $role A role that has a guide.
     * @return \stdClass title, intro, and sections: each with key, title, texts, steps, notes (lists of strings), words
     *                   (objects with word and definition) and link (null, or an object with url and label).
     * @throws \moodle_exception guideunknown, for a role that has no guide.
     */
    public static function guide(string $role): \stdClass {
        if (!array_key_exists($role, self::ROLES)) {
            throw new \moodle_exception('guideunknown', 'tool_vae');
        }
        $prefix = 'guide' . $role;

        $sections = [];
        foreach (self::SECTIONS[$role] as $key => $shape) {
            $words = [];
            foreach ($shape['words'] ?? [] as $word) {
                $words[] = (object) [
                    'word' => get_string('guideword_' . $word, 'tool_vae'),
                    'definition' => get_string('guidedefinition_' . $word, 'tool_vae'),
                ];
            }
            $link = null;
            if (isset($shape['link'])) {
                [$path, $label] = $shape['link'];
                $link = (object) ['url' => new \moodle_url($path), 'label' => get_string($label, 'tool_vae')];
            }
            $sections[] = (object) [
                'key' => $key,
                'title' => get_string(isset($shape['words']) ? 'guidewords' : $prefix . '_' . $key, 'tool_vae'),
                'texts' => self::numbered($prefix . '_' . $key . '_text', $shape['texts'] ?? 0),
                'steps' => self::numbered($prefix . '_' . $key . '_step', $shape['steps'] ?? 0),
                'notes' => self::numbered($prefix . '_' . $key . '_note', $shape['notes'] ?? 0),
                'words' => $words,
                'link' => $link,
            ];
        }

        return (object) [
            'title' => get_string($prefix . '_title', 'tool_vae'),
            'intro' => get_string($prefix . '_intro', 'tool_vae'),
            'sections' => $sections,
        ];
    }

    /**
     * Every key of a language string the guides use, so that a test can find each in both languages.
     *
     * @return string[] In no particular order, each once.
     */
    public static function string_keys(): array {
        $keys = self::COMMON;
        foreach (self::SECTIONS as $role => $sections) {
            $prefix = 'guide' . $role;
            $keys[] = 'guidelink_' . $role;
            $keys[] = $prefix . '_title';
            $keys[] = $prefix . '_intro';
            foreach ($sections as $key => $shape) {
                if (isset($shape['words'])) {
                    foreach ($shape['words'] as $word) {
                        $keys[] = 'guideword_' . $word;
                        $keys[] = 'guidedefinition_' . $word;
                    }
                    continue;
                }
                $keys[] = $prefix . '_' . $key;
                foreach (['text', 'step', 'note'] as $kind) {
                    for ($i = 1; $i <= ($shape[$kind . 's'] ?? 0); $i++) {
                        $keys[] = $prefix . '_' . $key . '_' . $kind . $i;
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * The strings "<prefix>1" to "<prefix><count>", in order.
     *
     * @param string $prefix
     * @param int $count
     * @return string[]
     */
    private static function numbered(string $prefix, int $count): array {
        $out = [];
        for ($i = 1; $i <= $count; $i++) {
            $out[] = get_string($prefix . $i, 'tool_vae');
        }

        return $out;
    }
}
