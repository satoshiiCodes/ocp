<?php
/**
 * actions/index-actions.php
 *
 * Every action for index.php lives in this one file.
 *
 * index.php is the login page and it handles its own form: the sign-in POST is
 * processed in the page, before this file is reached, because the result of that
 * POST is what the page renders. There is therefore nothing to handle here.
 *
 * The file exists so index.php follows the same layout as every other page - one
 * actions file, one endpoint file - and so that a future action (a password-reset
 * request, say) has an obvious home.
 */

// Nothing to do: the login form is handled by the page itself.
return;
