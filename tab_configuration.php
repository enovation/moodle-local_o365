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
 * This page allows a Microsoft Teams Tab to be configured.
 *
 * @package local_o365
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2018 onwards Microsoft, Inc. (http://microsoft.com/)
 */

require_once(__DIR__ . '/../../config.php');

$url = new moodle_url('/local/o365/tab_configuration.php');

// force a theme without navigation and block
if (get_config('theme_boost_o365teams', 'version')) {
    $SESSION->theme = 'boost_o365teams';
}

echo '<link rel="stylesheet" type="text/css" href="styles.css">';
echo "<script src=\"https://unpkg.com/@microsoft/teams-js@1.3.4/dist/MicrosoftTeams.min.js\" crossorigin=\"anonymous\"></script>";
echo "<script src=\"https://secure.aadcdn.microsoftonline-p.com/lib/1.0.17/js/adal.min.js\" crossorigin=\"anonymous\"></script>";

$js = '
microsoftTeams.initialize();

if (!inIframe()) {
    window.location.href = "' . $CFG->wwwroot . '/local/o365/tab_redirect.php";
}

let config = {
    clientId: "' . get_config('auth_oidc', 'clientid') . '",
    redirectUri: "' . $CFG->wwwroot . '/auth/oidc",
    cacheLocation: "localStorage",
    navigateToLoginRequestUrl: false,
};

let upn = undefined;
microsoftTeams.getContext(function (context) {
    upn = context.upn;
    loadData(upn);
});

// Loads data for the given user
function loadData(upn) {
    // Setup extra query parameters for ADAL
    // - openid and profile scope adds profile information to the id_token
    // - login_hint provides the expected user name
    if (upn) {
        config.extraQueryParameters = "scope=openid+profile&login_hint=" + encodeURIComponent(upn);
    } else {
        config.extraQueryParameters = "scope=openid+profile";
    }

    let authContext = new AuthenticationContext(config);

    // See if there\'s a cached user and it matches the expected user
    let user = authContext.getCachedUser();
    if (user) {
        if (user.userName !== upn) {
            // User doesn\'t match, clear the cache
            authContext.clearCache();
        }
    }

    // Get the id token (which is the access token for resource = clientId)
    let token = authContext.getCachedToken(config.clientId);
    if (!token) {
        // No token, or token is expired
        authContext._renewIdToken(function (err, idToken) {
            if (err) {
                console.log("Renewal failed: " + err);

                // Failed to get the token silently; show the login button
            }
        });
    }
}

function onCourseChange() {
    var course = document.getElementsByName("course[]")[0];
    var courseid = course.value;
    course.removeAttribute("multiple");

    var options = course.options;
    for (var i = 0; i < options.length; i++) {
        if (options[i].value != courseid) {
            options[i].selected = false;
        }
    }

    var tabname =  document.getElementsByName("tab_name")[0];
    var tabnamevalue = tabname.value;

    microsoftTeams.settings.setSettings({
        entityId: "course_" + courseid,
        contentUrl: "' . $CFG->wwwroot . '/local/o365/tab.php?id=' . '" + courseid,
        suggestedTabName: tabnamevalue,
    });
    microsoftTeams.settings.setValidityState(true);
}

function onTabNameChange() {
    var course = document.getElementsByName("course[]")[0];
    var courseid = course.value;

    var tabname =  document.getElementsByName("tab_name")[0];
    var tabnamevalue = tabname.value;

    microsoftTeams.settings.setSettings({
        entityId: "course_" + courseid,
        contentUrl: "' . $CFG->wwwroot . '/local/o365/tab.php?id=' . '" + courseid,
        suggestedTabName: tabnamevalue,
    });
}

function inIframe () {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}
function setTitles(){ 
   var text;
   var x = document.getElementById("id_course").options.length;
   for( i=0; i<x; i++ ){

      text = document.getElementById("id_course").options[i].text;
      document.getElementById("id_course").options[i].title=text;
   }
}
window.onload = setTitles;

';

echo html_writer::script($js);

if (!$USER->id) {
    $SESSION->wantsurl = $url;

    require_once($CFG->dirroot . '/auth/oidc/auth.php');
    $auth = new \auth_plugin_oidc('authcode');
    $auth->set_httpclient(new \auth_oidc\httpclient());
    $auth->handleredirect();
}

$form = new \local_o365\form\tabconfiguration();
$form->display();
