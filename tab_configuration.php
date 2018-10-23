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

$PAGE->set_context(context_system::instance());

// force a theme without navigation and block
if (get_config('theme_boost_o365teams', 'version')) {
    $SESSION->theme = 'boost_o365teams';
}

echo '<link rel="stylesheet" type="text/css" href="styles.css">';
echo "<script src=\"https://statics.teams.microsoft.com/sdk/v1.0/js/MicrosoftTeams.min.js\" crossorigin=\"anonymous\"></script>";
echo "<script src=\"https://secure.aadcdn.microsoftonline-p.com/lib/1.0.15/js/adal.min.js\" crossorigin=\"anonymous\"></script>";
echo "<script src=\"https://code.jquery.com/jquery-3.1.1.js\" crossorigin=\"anonymous\"></script>";

$redirecturl = new moodle_url('/local/o365/tab_redirect.php');
$ssostarturl = new moodle_url('/local/o365/sso_start.php');
$ssoendurl = new moodle_url('/local/o365/sso_end.php');
$oidcloginurl = new moodle_url('/auth/oidc/index.php');

$url->params(array('bypassauth' => 1, 'sesskey' => sesskey()));
$SESSION->wantsurl = $url;

$sesskey = optional_param('sesskey', null, PARAM_RAW);
$bypassauth = optional_param('bypassauth', false, PARAM_BOOL);
if ($sesskey || !confirm_sesskey($sesskey)) {
    $bypassauth = false;
}

$js = '
microsoftTeams.initialize();

if (!inIframe()) {
    window.location.href = "' . $redirecturl->out() . '";
    sleep(20);
}

// ADAL.js configuration
let config = {
    clientId: "' . get_config('auth_oidc', 'clientid') . '",
    redirectUri: "' . $ssoendurl->out() . '",
    cacheLocation: "localStorage",
    navigateToLoginRequestUrl: false,
};

let upn = undefined;

window.onload = setTitles;

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

    // See if there is a cached user and it matches the expected user
    let user = authContext.getCachedUser();
    if (user) {
        if (user.userName !== upn) {
            // User does not match, clear the cache
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
                
                // Failed to get the token silently; need to show the login button
                $("#btnLogin").css({ display: "" });
            }
        });
    } else {
        // login using the token
        window.location.href = "' . $oidcloginurl->out() . '";
        sleep(20);
    }
}

function login() {
    microsoftTeams.authentication.authenticate({
        url: "' . $ssostarturl->out() . '",
        width: 600,
        height: 400,
        successCallback: function (result) {
            // AuthenticationContext is a singleton
            let authContext = new AuthenticationContext();
            let idToken = authContext.getCachedToken(config.clientId);
            if (idToken) {
                // login using the token
                window.location.href = "' . $oidcloginurl->out() . '";
                sleep(20);
            } else {
                console.error("Error getting cached id token. This should never happen.");                            
                // At this point we have to get the user involved, so show the login button
                $("#btnLogin").css({ display: "" });
            };
        },
        failureCallback: function (reason) {
            console.log("Login failed: " + reason);
            if (reason === "CancelledByUser" || reason === "FailedToOpenWindow") {
                console.log("Login was blocked by popup blocker or canceled by user.");
            }
            // At this point we have to get the user involved, so show the login button
            $("#btnLogin").css({ display: "" });
        }
    });
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
        webUrl: "' . $CFG->wwwroot . '/course/view.php?id=' . '" + courseid,
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
        webUrl: "' . $CFG->wwwroot . '/course/view.php?id=' . '" + courseid,
        suggestedTabName: tabnamevalue,
    });
}

function inIframe() {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}

function setTitles() { 
   var text;
   var x = document.getElementById("id_course").options.length;
   for (i = 0; i < x; i++ ) {
      text = document.getElementById("id_course").options[i].text;
      document.getElementById("id_course").options[i].title=text;
   }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

';

if ($USER->id == 0 || !$bypassauth) {
    $js .= '    
microsoftTeams.getContext(function (context) {
    upn = context.upn;
    loadData(upn);
});
    ';
}

echo html_writer::script($js);

$form = new \local_o365\form\tabconfiguration();
$form->display();
