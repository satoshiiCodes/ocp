<?php
/**
 * api/index-endpoint.php
 *
 * Every read for index.php lives in this one file.
 *
 * index.php is the login page: it renders a form and nothing else, so it has no
 * rows to read. The one thing it needs from the database is the account being
 * signed in, and that lookup belongs to the login action itself - it is a
 * credential check whose result decides the redirect - so it stays in the page.
 *
 * The file exists so index.php follows the same layout as every other page: one
 * actions file, one endpoint file. It returns the page's variables, which today is
 * just the empty message that the form shows on a failed sign-in.
 *
 * Returns
 *   error   string the message shown above the login form
 */

$ocp_endpoint = [
    'error' => '',
];

return $ocp_endpoint;
