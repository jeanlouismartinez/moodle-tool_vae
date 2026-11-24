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
use core\output\notification;
use core\output\renderable;
use core\output\renderer_base;
use tool_vae\local\ai_assistant;

/**
 * The page of the reformulation aid (ai.php), below its heading: the policy, the confirmation or the result (T-70, lot 2;
 * lot 7, MMR-520 ticket 4).
 *
 * ONLY WORDS AND FIELDS. Nothing here decides whether the aid may be used (ai_assistant does) and nothing here is saved. Every
 * form posts to ai.php, and the text it carries is shown in a field nobody edits on the page: what is displayed is what is
 * submitted, so the vaeiste confirms the very text that leaves. Every text is given in clear to the template, which escapes
 * it, the proposal first: it is text from a provider. The messages given to a notification or a heading of the core, which
 * do not escape, are escaped here, as before. Before lot 7, the screens were written by hand in tool_vae\local\ai_screens.
 *
 * Three states, which exclude one another: the policy of the site, to accept once; the confirmation, at every send, with the
 * warning that what was confirmed changed when it did; the result, the proposal next to the original, or why there is none.
 *
 * @package    tool_vae
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ai_page implements named_templatable, renderable {
    /** @var string The policy of the site. */
    private const POLICY = 'policy';

    /** @var string The confirmation. */
    private const CONFIRM = 'confirm';

    /** @var string The result. */
    private const RESULT = 'result';

    /** @var string Which of the three. */
    private string $screen;

    /** @var array What the screen is given, by name. */
    private array $given;

    /**
     * Use policy(), confirm() or result().
     *
     * @param string $screen
     * @param array $given
     */
    private function __construct(string $screen, array $given) {
        $this->screen = $screen;
        $this->given = $given + ['params' => [], 'carried' => [], 'provider' => '', 'again' => null, 'proposal' => null,
            'error' => null];
    }

    /**
     * The policy of use, to accept once. The text is carried through, so that nothing has to be written again.
     *
     * THE SITE'S POLICY, NOT THE PLUGIN'S: the text of the core (core_ai, `userpolicy`), which the site words once, by the
     * customisation of its language, for every place that offers AI - the editor, the course assistant, this aid. The core
     * keeps one acceptance per person, whatever the place, and a second policy of the plugin's own, accepted into that same
     * register, said two things about one act. What belongs to this aid alone - name nobody, what is kept, read it against
     * your text - is on the confirmation, shown at every send, which is where it can be read before the text leaves.
     *
     * @param array $params What tells ai.php where the text was written.
     * @param string $text The vaeiste's text.
     * @param \moodle_url $back Where the way out leads.
     * @param string $backwords The words of the way out.
     * @return self
     */
    public static function policy(array $params, string $text, \moodle_url $back, string $backwords): self {
        return new self(self::POLICY, ['params' => $params, 'text' => $text, 'back' => $back, 'backwords' => $backwords]);
    }

    /**
     * The confirmation, at every send: the exact text, where it goes, what is kept. It cannot be switched off.
     *
     * @param array $params What tells ai.php where the text was written.
     * @param string $text The vaeiste's text, as ai_assistant::clean() gives it.
     * @param string $provider The name of the one provider the text will reach.
     * @param \moodle_url $back Where the way out leads.
     * @param string $backwords The words of the way out.
     * @param string[] $carried More fields the send step asks back: the single-use token and the mark of the provider.
     * @param string|null $again Why what was confirmed is shown again (the token spent, the provider changed); or null.
     * @return self
     */
    public static function confirm(
        array $params,
        string $text,
        string $provider,
        \moodle_url $back,
        string $backwords,
        array $carried = [],
        ?string $again = null
    ): self {
        return new self(self::CONFIRM, [
            'params' => $params, 'text' => $text, 'provider' => $provider, 'back' => $back, 'backwords' => $backwords,
            'carried' => $carried, 'again' => $again,
        ]);
    }

    /**
     * The result: the proposal next to the original, or why there is none.
     *
     * @param string|null $proposal What the provider proposed; null when it did not.
     * @param string|null $error Why there is none, in the plugin's own words; null when there is a proposal.
     * @param string $text The vaeiste's text.
     * @param \moodle_url $back Where the way out leads.
     * @param string $backwords The words of the way out.
     * @return self
     */
    public static function result(?string $proposal, ?string $error, string $text, \moodle_url $back, string $backwords): self {
        return new self(self::RESULT, [
            'proposal' => $proposal, 'error' => $error, 'text' => $text, 'back' => $back, 'backwords' => $backwords,
        ]);
    }

    /**
     * The template of the page.
     *
     * @param renderer_base $renderer
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'tool_vae/ai_page';
    }

    /**
     * The context of the template: the texts in clear; the notifications, the headings and the policy as HTML.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $given = $this->given;
        $context = [
            'againhtml' => false, 'errorhtml' => false, 'headinghtml' => false, 'policyhtml' => false, 'confirm' => false,
            'problemhtml' => false, 'proposal' => false, 'original' => false, 'form' => false,
        ];

        if ($this->screen === self::POLICY) {
            // The core's text is HTML written by the site, shown the way the core shows it, cleaned.
            $context['headinghtml'] = $output->heading(s(get_string('aiusagepolicy', 'core_ai')), 3);
            $context['policyhtml'] = format_text(
                get_string('userpolicy', 'core_ai'),
                FORMAT_HTML,
                ['context' => ai_assistant::core_context()]
            );
            $context['form'] = $this->form('accept', get_string('aipolicyaccept', 'tool_vae'));

            return $context;
        }

        if ($this->screen === self::CONFIRM) {
            $text = $given['text'];
            $max = ai_assistant::max_chars();
            $count = \core_text::strlen($text);
            // What becomes of the text is said as it is on this site: that Moodle empties it once the deletion request is
            // approved only where it does (ai_assistant::moodle_erases_the_texts()), and that it does not where it does not.
            $logged = ai_assistant::moodle_erases_the_texts() ? 'aiconfirmlogged' : 'aiconfirmloggednoerase';
            if ($given['again'] !== null) {
                $context['againhtml'] = $output->notification(s($given['again']), notification::NOTIFY_WARNING);
            }
            $context['headinghtml'] = $output->heading(get_string('aiconfirmheading', 'tool_vae'), 3);
            $context['confirm'] = [
                'reminder' => get_string('anonymisereminder', 'tool_vae'),
                'where' => get_string('aiconfirmwhere', 'tool_vae', $given['provider']),
                'count' => get_string('aiconfirmcount', 'tool_vae', ['count' => $count, 'max' => $max]),
                'logged' => get_string($logged, 'tool_vae'),
            ];
            if ($text === '') {
                $context['problemhtml'] = $output->notification(
                    s(get_string('errorainotext', 'tool_vae')),
                    notification::NOTIFY_ERROR
                );
            } else if ($count > $max) {
                $context['problemhtml'] = $output->notification(
                    s(get_string('errorailength', 'tool_vae', $max)),
                    notification::NOTIFY_ERROR
                );
            }
            $sendable = $text !== '' && $count <= $max;
            $context['form'] = $this->form('send', $sendable ? get_string('aiconfirmsend', 'tool_vae', $given['provider']) : '');

            return $context;
        }

        if ($given['proposal'] === null) {
            $context['errorhtml'] = $output->notification(s((string) $given['error']), notification::NOTIFY_ERROR);
        } else {
            $context['headinghtml'] = $output->heading(get_string('airesultheading', 'tool_vae'), 3);
            $context['proposal'] = [
                'notice' => get_string('airesultnotice', 'tool_vae'),
                'field' => self::field(get_string('airesultproposal', 'tool_vae'), $given['proposal'], 'tool-vae-ai-proposal'),
            ];
        }
        $context['original'] = [
            'field' => self::field(get_string('aioriginal', 'tool_vae'), $given['text'], 'tool-vae-ai-text'),
            'hint' => get_string('aibackhint', 'tool_vae'),
            'backurl' => $given['back']->out(false),
            'backwords' => $given['backwords'],
        ];

        return $context;
    }

    /**
     * A text in a field nobody edits here.
     *
     * @param string $label The label of the field.
     * @param string $text The text.
     * @param string $id The id of the field.
     * @return array
     */
    private static function field(string $label, string $text, string $id): array {
        return ['label' => $label, 'text' => $text, 'id' => $id];
    }

    /**
     * A form of the page: the fields every form carries, the text carried back, a button that goes on and a way out.
     *
     * @param string $step The step the form leads to.
     * @param string $button The words of the button that goes on; empty for none.
     * @return array
     */
    private function form(string $step, string $button): array {
        $hidden = [['name' => 'sesskey', 'value' => sesskey()]];
        foreach ($this->given['params'] + $this->given['carried'] + ['step' => $step] as $name => $value) {
            $hidden[] = ['name' => (string) $name, 'value' => (string) $value];
        }

        return [
            'action' => (new \moodle_url('/admin/tool/vae/ai.php'))->out(false),
            'hidden' => $hidden,
            'field' => self::field(get_string('aioriginal', 'tool_vae'), $this->given['text'], 'tool-vae-ai-text'),
            'button' => $button === '' ? false : ['label' => $button],
            'backurl' => $this->given['back']->out(false),
            'backwords' => $this->given['backwords'],
            'hint' => get_string('aibackhint', 'tool_vae'),
        ];
    }
}
