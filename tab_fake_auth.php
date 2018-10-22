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
 * This page displays a course page in a Microsoft Teams tab.
 *
 * @package local_o365
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2018 onwards Microsoft, Inc. (http://microsoft.com/)
 */

require_once(__DIR__ . '/../../config.php');

// force theme
if (get_config('theme_boost_o365teams', 'version')) {
    $SESSION->theme = 'boost_o365teams';
}

echo "<script src=\"https://unpkg.com/@microsoft/teams-js@1.3.4/dist/MicrosoftTeams.min.js\" crossorigin=\"anonymous\"></script>";
echo "<script src=\"https://secure.aadcdn.microsoftonline-p.com/lib/1.0.15/js/adal.min.js\" crossorigin=\"anonymous\"></script>";
echo "<script src=\"https://code.jquery.com/jquery-3.1.1.js\" crossorigin=\"anonymous\"></script>";

$id = required_param('id', PARAM_INT);

$USER->editing = false; // turn off editing if the page is opened in iframe

$redirecturl = new moodle_url('/local/o365/tab_redirect.php');
$coursepageurl = new moodle_url('/course/view.php', array('id' => $id));
$loginpageurl = new moodle_url('/login/index.php');
$ssostarturl = new moodle_url('/local/o365/sso_start.php');

echo html_writer::tag('button', 'Login to Azure AD', array('id' => 'btnLogin', 'onclick' => 'login()'));

$js = '
microsoftTeams.initialize();

if (!inIframe()) {
    window.location.href = "' . $redirecturl->out() . '";
    sleep(20);
}

// ADAL.js configuration
let config = {
    clientId: "' . get_config('auth_oidc', 'clientid') . '",
    redirectUri: "' . $CFG->wwwroot . '/local/o365/sso_end.php",
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
        
    }
}

function login() {
//    $("#divError").text("").css({ display: "none" });
//    $("#divProfile").css({ display: "none" });
    microsoftTeams.authentication.authenticate({
//        url: window.location.origin + "/tab-auth/silent-start",
        url: "' . $ssostarturl->out() . '",
        width: 600,
        height: 400,
        successCallback: function (result) {
            // AuthenticationContext is a singleton
            let authContext = new AuthenticationContext();
            let idToken = authContext.getCachedToken(config.clientId);
            if (idToken) {
                // login using the token
                
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
//            $("#divError").text(reason).css({ display: "" });
//            $("#divProfile").css({ display: "none" });
        }
    });
}

function inIframe () {
    try {
        return window.self !== window.top;
    } catch (e) {
        return true;
    }
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}
';

echo html_writer::script($js);

/*
if (!$USER->id) {
    $SESSION->wantsurl = $coursepageurl;

    require_once($CFG->dirroot . '/auth/oidc/auth.php');
    $auth = new \auth_plugin_oidc('authcodeteams');
    $auth->set_httpclient(new \auth_oidc\httpclient());
    $auth->handleredirect();
}
*/
