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

/**
 * autogroup local plugin
 *
 * A course object relates to a Moodle course and acts as a container
 * for multiple groups. Initialising a course object will automatically
 * load each autogroup group for that course into memory.
 *
 * @package    local_autogroup
 * @copyright  Mark Ward (me@moodlemark.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autogroup\sort_module;

use local_autogroup\sort_module;
use stdClass;

/**
 * Class course
 * @package local_autogroup\domain
 */
class profile_field extends sort_module {
    // CARGOSCHOOL: start.
    /**
     * Marker read by local_cargoservices to know that this is the CargoSchool version.
     *
     * The CargoSchool version understands two extra values of the institution field:
     * several sites separated by " - " (space hyphen space), and "*" meaning every
     * site of the user's tenants. Any other value behaves exactly as upstream.
     */
    public const CARGOSCHOOL_MULTISITE = true;

    /** Value of the institution field meaning "every site of my tenants". */
    public const CARGOSCHOOL_ALL_SITES = '*';

    /** Separator of a multi-site institution (space hyphen space). */
    public const CARGOSCHOOL_SEPARATOR_REGEX = '/\s+-(?:\s+-)*\s+/';

    /**
     * @var int Id of the autogroup set using this module, 0 when unknown.
     */
    private $cargoschoolsetid = 0;
    // CARGOSCHOOL: end.

    /**
     * @var string
     */
    private $field = '';

    /**
     * @param stdClass $config
     * @param int $courseid
     */
    public function __construct($config, $courseid) {
        if ($this->config_is_valid($config)) {
            $this->field = $config->field;
        }
        $this->courseid = (int)$courseid;
    }

    /**
     * @param stdClass $config
     * @return bool
     */
    public function config_is_valid(stdClass $config) {
        if (!isset($config->field)) {
            return false;
        }

        // Ensure that the stored option is valid.
        if (array_key_exists($config->field, $this->get_config_options())) {
            return true;
        }

        return false;
    }

    /**
     * Returns the options to be displayed on the autgroup_set
     * editing form. These are defined per-module.
     *
     * @return array
     */
    public function get_config_options() {
        $options = [
            'auth' => get_string('auth', 'local_autogroup'),
            'department' => get_string('department', 'local_autogroup'),
            'institution' => get_string('institution', 'local_autogroup'),
            'lang' => get_string('lang', 'local_autogroup'),
            'city' => get_string('city', 'local_autogroup'),
        ];
        return $options;
    }

    /**
     * @param stdClass $user
     * @return array $result
     */
    public function eligible_groups_for_user(stdClass $user) {
        $field = $this->field;
        if (isset($user->$field) && !empty($user->$field)) {
            // CARGOSCHOOL: start. Several sites or "*" in the institution field.
            if ($field === 'institution') {
                $value = (string)$user->$field;
                if (self::cargoschool_is_all_sites($value)) {
                    return $this->cargoschool_all_sites($user);
                }
                if (self::cargoschool_is_multi_site($value)) {
                    return self::cargoschool_split_sites($user);
                }
            }
            // CARGOSCHOOL: end.
            return [$user->$field];
        }
        return [];
    }

    // CARGOSCHOOL: start.
    /**
     * Tells the module which autogroup set it belongs to.
     *
     * Needed by "*": only the groups of this very set are considered.
     *
     * @param int $setid Autogroup set id.
     * @return void
     */
    public function cargoschool_set_setid($setid) {
        $this->cargoschoolsetid = (int)$setid;
    }

    /**
     * Whether an institution value means "every site of my tenants".
     *
     * @param string $value Raw institution value.
     * @return bool
     */
    public static function cargoschool_is_all_sites($value) {
        return trim((string)$value) === self::CARGOSCHOOL_ALL_SITES;
    }

    /**
     * Whether an institution value lists several sites.
     *
     * @param string $value Raw institution value.
     * @return bool
     */
    public static function cargoschool_is_multi_site($value) {
        return (bool)preg_match(self::CARGOSCHOOL_SEPARATOR_REGEX, (string)$value);
    }

    /**
     * The sites listed in a multi-site institution.
     *
     * local_cargoservices is the single place where the convention is read; the
     * local fallback applies the same rule when it is not installed.
     *
     * @param stdClass $user User record.
     * @return array Site names, without "*" and without duplicates.
     */
    public static function cargoschool_split_sites(stdClass $user) {
        $manager = '\\local_cargoservices\\manager';
        if (class_exists($manager) && method_exists($manager, 'get_user_sites')) {
            $sites = \local_cargoservices\manager::get_user_sites($user);
            return array_values(array_filter($sites, function ($site) {
                return $site !== self::CARGOSCHOOL_ALL_SITES;
            }));
        }

        $sites = [];
        foreach (preg_split(self::CARGOSCHOOL_SEPARATOR_REGEX, (string)$user->institution) as $part) {
            $site = trim($part);
            if ($site === '' || $site === self::CARGOSCHOOL_ALL_SITES) {
                continue;
            }
            $key = \core_text::strtolower($site);
            if (!isset($sites[$key])) {
                $sites[$key] = $site;
            }
        }
        return array_values($sites);
    }

    /**
     * The existing site groups of this set that belong to the user's tenants.
     *
     * "*" never creates a group: it only joins the site groups already present in
     * the course, and only those of sites belonging to one of the user's tenants
     * (a course can be shared by several companies). Without local_cargoservices
     * the tenants are unknown and no group is returned.
     *
     * @param stdClass $user User record (department and institution).
     * @return array Site names, as stored in the group idnumbers.
     */
    private function cargoschool_all_sites(stdClass $user) {
        global $DB;

        $population = '\\local_cargoservices\\population';
        if ($this->cargoschoolsetid < 1 || $this->courseid < 1) {
            return [];
        }
        if (!class_exists($population) || !method_exists($population, 'tenant_sites')) {
            return [];
        }

        $tenants = \local_cargoservices\manager::get_user_tenants($user);
        if (empty($tenants)) {
            return [];
        }

        $prefix = 'autogroup|' . $this->cargoschoolsetid . '|';
        $idnumbers = $DB->get_fieldset_select(
            'groups',
            'idnumber',
            'courseid = :courseid AND ' . $DB->sql_like('idnumber', ':prefix'),
            ['courseid' => $this->courseid, 'prefix' => $DB->sql_like_escape($prefix) . '%']
        );
        if (empty($idnumbers)) {
            return [];
        }

        $tenantsites = \local_cargoservices\population::tenant_sites($tenants);

        $sites = [];
        foreach ($idnumbers as $idnumber) {
            $site = substr((string)$idnumber, strlen($prefix));
            if ($site === '' || $site === false) {
                continue;
            }
            if (self::cargoschool_is_all_sites($site) || self::cargoschool_is_multi_site($site)) {
                continue;
            }
            $key = \local_cargoservices\population::normalise($site);
            if (isset($tenantsites[$key])) {
                $sites[$site] = $site;
            }
        }
        return array_values($sites);
    }
    // CARGOSCHOOL: end.

    /**
     * @return bool|string
     */
    public function grouping_by() {
        return empty($this->field) ? false : $this->field;
    }

    /**
     * @return bool|string
     */
    public function grouping_by_text() {
        if (empty ($this->field)) {
            return false;
        }
        $options = $this->get_config_options();
        return isset($options[$this->field]) ? $options[$this->field] : $this->field;
    }
}
