<?php
// Copy this file to config.php and fill in your own values.
// config.php is gitignored so your institution's specific values never get committed.
//
// This controls who can view page sets that are NOT marked "Public" in the
// admin panel (see is_public on page_sets). Page sets marked Public skip
// this check entirely. admin.php and api.php are protected separately at
// the web server level — see .htaccess.

return [
    // CIDR ranges treated as a trusted network (e.g. your campus IP block).
    // Leave empty to disable IP-based access.
    'trusted_ip_ranges' => [
        // '158.104.0.0/16',
    ],

    // Usernames allowed to view restricted sets when authenticated via
    // Shibboleth (requires a Shibboleth SP configured in Apache — see the
    // AuthType shibboleth lines in .htaccess). Leave empty to disable.
    'shibboleth_users' => [
        // 'jdoe',
    ],
];
