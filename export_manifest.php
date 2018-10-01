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
 * Download tab manifest file.
 *
 * @package local_o365
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2018 onwards Microsoft, Inc. (http://microsoft.com/)
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filestorage/zip_archive.php');
require_once($CFG->dirroot . '/local/o365/lib.php');

// task 1 : check if app ID has been set, and print error if it's missing
$appid = get_config('local_o365', 'teams_tab_app_id');
if (!$appid || $appid == '00000000-0000-0000-0000-000000000000') {
    $redirecturl = new moodle_url('/admin/settings.php', array('section' => 'local_o365', 's_local_o365_tabs' => '5'));
    print_error('errorinvalidteamstabappid', 'local_o365', $redirecturl);
}

// task 2 : prepare manifest folder
$pathtomanifestfolder = $CFG->dataroot . '/temp/manifest';
if (file_exists($pathtomanifestfolder)) {
    local_o365_rmdir($pathtomanifestfolder);
}
mkdir($pathtomanifestfolder, 0777, true);

// task 3 : prepare manifest file
$manifest = array(
    '$schema' => 'https://developer.microsoft.com/en-us/json-schemas/teams/v1.3/MicrosoftTeams.schema.json',
    'manifestVersion' => '1.3',
    'version' => '1.0.0',
    'id' => $appid,
    'packageName' => 'ie.enovation.ms.teams.moodletab', //todo update package name
    'developer' => array(
        'name' => 'Enovation Solutions', // todo update developer name
        'websiteUrl' => 'https://enovation.ie', // todo update developer website URL
        'privacyUrl' => 'https://enovation.ie', // todo update privacy URL
        'termsOfUseUrl' => 'https://enovation.ie', // todo update terms of use URL
    ),
    'icons' => array(
        'color' => 'color.png',
        'outline' => 'outline.png',
    ),
    'name' => array(
        'short' => 'Moodle', // todo update short name
        'full' => 'Moodle course', // todo update full name
    ),
    'description' => array(
        'short' => 'This tab allows a Moodle course to be displayed in Microsoft Teams.', // todo update short description
        'full' => 'This tab allows a Moodle course to be displayed in Microsoft Teams.', // todo update full description
    ),
    'accentColor' => '#FFFFFF',
    'configurableTabs' => array(
        array(
            'configurationUrl' => $CFG->wwwroot . '/local/o365/tab_configuration.php',
            'canUpdateConfiguration' => false,
            'scopes' => array('team'),
        ),
    ),
    'permissions' => array(
        'identity', 'messageTeamMembers',
    ),
    'validDomains' => array(
        parse_url($CFG->wwwroot, PHP_URL_HOST),
    ),
);

$file = $pathtomanifestfolder . '/manifest.json';
file_put_contents($file, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// task 4 : prepare icons
copy($CFG->dirroot . '/local/o365/pix/color.png', $pathtomanifestfolder . '/color.png');
copy($CFG->dirroot . '/local/o365/pix/outline.png', $pathtomanifestfolder . '/outline.png');

// task 5 : compress the folder
$ziparchive = new zip_archive();
$zipfilename = $pathtomanifestfolder . '/manifest.zip';
$ziparchive->open($zipfilename);
$filenames = array('manifest.json', 'color.png', 'outline.png');
foreach ($filenames as $filename) {
    $ziparchive->add_file_from_pathname($filename, $pathtomanifestfolder . '/' . $filename);
}
$ziparchive->close();

// task 6 : download the zip file
header("Content-type: application/zip");
header("Content-Disposition: attachment; filename=manifest.zip");
header("Content-length: " . filesize($zipfilename));
header("Pragma: no-cache");
header("Expires: 0");
readfile($zipfilename);
