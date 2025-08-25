<?php
/**
 * Garmin Connect SSO Language File (English)
 * @copyright (c) 2025 UtagawaVTT
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = array();
}

$lang = array_merge($lang, array(
    'GARMIN_SSO_ENABLED'            => 'Enable Garmin Connect SSO',
    'GARMIN_SSO_ENABLED_EXPLAIN'    => 'Allow users to login with their Garmin Connect credentials.',
    'GARMIN_AUTO_REGISTER'          => 'Auto-register users',
    'GARMIN_AUTO_REGISTER_EXPLAIN'  => 'Automatically create forum account if Garmin Connect user doesn\'t exist yet.',
    'GARMIN_EMAIL_DOMAIN'           => 'Default email domain',
    'GARMIN_EMAIL_DOMAIN_EXPLAIN'   => 'Domain used to generate email addresses for new users (e.g., garmin.local).',
    'LOGIN_GARMIN'                  => 'Garmin Connect Login',
    'LOGIN_GARMIN_EXPLAIN'          => 'Use your Garmin Connect credentials to login.',
));
