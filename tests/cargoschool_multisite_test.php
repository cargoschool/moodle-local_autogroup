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

namespace local_autogroup;

/**
 * Tests of the CargoSchool changes: several sites and "*" in the institution field.
 *
 * @package    local_autogroup
 * @category   test
 * @copyright  2026 CargoSchool
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_autogroup\sort_module\profile_field
 * @covers     \local_autogroup\domain\autogroup_set
 */
final class cargoschool_multisite_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private $course;

    /** @var int Autogroup set id. */
    private $setid;

    /**
     * Enables the plugin with synchronous event handling and an institution set.
     */
    protected function setUp(): void {
        global $DB, $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/user/lib.php');

        set_config('enabled', 1, 'local_autogroup');
        set_config('adhoceventhandler', 0, 'local_autogroup');
        set_config('preservemanual', 1, 'local_autogroup');
        set_config('addtonewcourses', 0, 'local_autogroup');
        set_config('addtorestoredcourses', 0, 'local_autogroup');
        foreach (
            ['listenforrolechanges', 'listenforuserprofilechanges', 'listenforgroupchanges',
                'listenforgroupmembership', 'listenforuserpositionchanges'] as $name
        ) {
            set_config($name, 1, 'local_autogroup');
        }

        $this->course = $this->getDataGenerator()->create_course();
        $this->setid = $DB->insert_record('local_autogroup_set', (object)[
            'courseid' => $this->course->id,
            'sortmodule' => 'profile_field',
            'sortconfig' => json_encode(['field' => 'institution']),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        foreach (['student', 'teacher'] as $shortname) {
            $DB->insert_record('local_autogroup_roles', (object)[
                'setid' => $this->setid,
                'roleid' => $DB->get_field('role', 'id', ['shortname' => $shortname]),
            ]);
        }
    }

    /**
     * Creates a user and enrols him in the course.
     *
     * @param string $department Department.
     * @param string $institution Institution.
     * @param string $role Role shortname.
     * @return \stdClass
     */
    private function enrol(string $department, string $institution, string $role = 'student'): \stdClass {
        $user = $this->getDataGenerator()->create_user(['department' => $department, 'institution' => $institution]);
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, $role);
        return $user;
    }

    /**
     * Names of the autogroup groups a user belongs to in the course.
     *
     * @param int $userid User id.
     * @return array Sorted group names.
     */
    private function groups_of(int $userid): array {
        global $DB;
        $sql = "SELECT g.name
                  FROM {groups} g
                  JOIN {groups_members} gm ON gm.groupid = g.id
                 WHERE g.courseid = :courseid AND gm.userid = :userid
                   AND " . $DB->sql_like('g.idnumber', ':tag');
        $names = $DB->get_fieldset_sql($sql, [
            'courseid' => $this->course->id,
            'userid' => $userid,
            'tag' => 'autogroup|' . $this->setid . '|%',
        ]);
        sort($names);
        return $names;
    }

    /**
     * Whether a site group exists in the course.
     *
     * @param string $site Raw site value.
     * @return bool
     */
    private function group_exists(string $site): bool {
        global $DB;
        return $DB->record_exists('groups', [
            'courseid' => $this->course->id,
            'idnumber' => 'autogroup|' . $this->setid . '|' . $site,
        ]);
    }

    /**
     * Updates the institution of a user firing user_updated.
     *
     * @param \stdClass $user User.
     * @param string $institution New value.
     */
    private function set_institution(\stdClass $user, string $institution): void {
        user_update_user((object)['id' => $user->id, 'institution' => $institution], false, true);
    }

    /**
     * Upstream behaviour is unchanged for empty and single values.
     */
    public function test_empty_and_single_values_unchanged(): void {
        $empty = $this->enrol('SAVE S.P.A.', '');
        $single = $this->enrol('SAVE S.P.A.', 'SEA LIN');
        $hyphen = $this->enrol('SAVE S.P.A.', 'SEA-LIN');

        $this->assertSame([], $this->groups_of($empty->id));
        $this->assertSame(['SEA LIN'], $this->groups_of($single->id));
        $this->assertSame(['SEA-LIN'], $this->groups_of($hyphen->id));
        $this->assertTrue($this->group_exists('SEA LIN'));
        $this->assertTrue($this->group_exists('SEA-LIN'));
    }

    /**
     * A manager with several sites joins one group per site.
     */
    public function test_multi_site_manager(): void {
        $this->enrol('SAVE S.P.A.', 'SEA LIN');
        $manager = $this->enrol('SAVE S.P.A.', 'SEA MXP - SEA LIN', 'teacher');

        $this->assertSame(['SEA LIN', 'SEA MXP'], $this->groups_of($manager->id));
        $this->assertFalse($this->group_exists('SEA MXP - SEA LIN'));

        // Removing a site from the list removes the manager from that group.
        $this->set_institution($manager, 'SEA LIN');
        $this->assertSame(['SEA LIN'], $this->groups_of($manager->id));
    }

    /**
     * A manager with "*" joins the existing site groups of his tenants only.
     */
    public function test_all_sites_manager(): void {
        if (!method_exists('\local_cargoservices\population', 'tenant_sites')) {
            $this->markTestSkipped('local_cargoservices 0.18.0 or later is required.');
        }

        $this->enrol('SAVE S.P.A.', 'SEA LIN');
        $this->enrol('ALTRA SPA', 'ALTRA SEDE');
        $manager = $this->enrol('SAVE S.P.A.', '*', 'teacher');

        $this->assertSame(['SEA LIN'], $this->groups_of($manager->id));
        $this->assertFalse($this->group_exists('*'));

        // A new site of the same tenant appears: the manager joins it.
        $newcomer = $this->enrol('SAVE S.P.A.', 'SEA FCO');
        $this->assertSame(['SEA FCO', 'SEA LIN'], $this->groups_of($manager->id));

        // The site disappears: the manager leaves and the empty group is deleted.
        $this->set_institution($newcomer, 'SEA LIN');
        $this->assertSame(['SEA LIN'], $this->groups_of($manager->id));
        $this->assertFalse($this->group_exists('SEA FCO'));
    }

    /**
     * Without local_cargoservices "*" joins nothing; the lists still work.
     */
    public function test_split_without_cargoservices_rule(): void {
        $user = (object)['id' => 1, 'department' => 'X', 'institution' => 'A - B - a - * -  - C'];
        $this->assertSame(['A', 'B', 'C'], \local_autogroup\sort_module\profile_field::cargoschool_split_sites($user));
        $this->assertTrue(\local_autogroup\sort_module\profile_field::cargoschool_is_all_sites(' * '));
        $this->assertFalse(\local_autogroup\sort_module\profile_field::cargoschool_is_multi_site('SEA-LIN'));
    }
}
