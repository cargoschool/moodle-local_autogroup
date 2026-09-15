## v4.5.3-cargoschool.1 (CargoSchool fork)
  * The institution field may hold several sites separated by " - " (space hyphen space): the user joins one group per site.
  * The institution value "*" joins every existing site group of the course whose site belongs to one of the user's tenants (requires local_cargoservices 0.18.0 or later).
  * Users holding "*" are realigned when a site group appears or a user leaves a site group.
  * Empty institutions and single values behave exactly as upstream.
  * requires Moodle 4.5, supported 4.5 to 5.1.
  * Rebased on upstream 4.5.3.
  * See CARGOSCHOOL.md.

## v4.5.3
  * Scope role-change membership verification to the event's course.

## v4.5.2
  * Ignore queued events whose course, group, or user has since been deleted.
  * Clean plugin configuration when courses are deleted.
  * Clean preserved manual assignments when users are deleted.
  * Fix autogroup-set deletion to clean manual records by group ID.
  * Add regression coverage for stale queued events and deletion cleanup.

## v2.8.2
  * Solves issue #44 - postgresql compatibility issue during upgrade process, previous fix was still buggy.

## v2.8.1
  * Solves issue #44 - postgresql compatibility issue during upgrade process

## v2.8
  * Solves Issue #38 - thanks to Giorgio Riva for the fix.

## v2.7
  * Solves Issue #37 - thanks to nrosenquist for fix

## v2.5
  * Solves https://github.com/emmarichardson/local_autogroup/issues/16 default group by
  * This change requires checking your global config field uses correct key now
  
## v2.4
  * Fixed unnecessary processing on profile update. Thanks to Luuk Verhoeven for this addition.

## v2.3
  * Added support for custom profile fields.  Thanks to Arnaud Trouvé for this addition.

## v2.2
  * Performance enhancement: Event listeners now check to see whether triggered by AutoGroup

## v2.1
  * Switched to individual toggles for event listeners
  * Minor change to settings structure for improved usability
  * Fixed compatibility with Moodle 3.1 and onwards

## v2.0
  * Added support for defining multiple grouping sets on one course
  * Sort modules are now fully modular
  * Added new sort module for Totara positional assignments

## v1.1
  * Fixed a bug which would have resulted in roles being removed from all groupsets across a site
  * Changed default permissions for plugin files 

## v1.01
  * Added event handler for course_restored and related setting

## v1.0
  * Stable release. Tested for compatibility with Moodle 2.7 and 2.8
