<?php
if (!defined('IN_PHPBB')) exit;

if (empty($lang) || !is_array($lang)) {
    $lang = array();
}

$lang = array_merge($lang, array(
    'GARMIN_SSO_ENABLED' => 'Activer Garmin Connect SSO',
    'GARMIN_AUTO_REGISTER' => 'Création automatique de compte',
    'GARMIN_AUTO_REGISTER_EXPLAIN' => 'Créer automatiquement un compte si l\'utilisateur n\'existe pas.',
    'GARMIN_EMAIL_DOMAIN' => 'Domaine email par défaut',
    'GARMIN_EMAIL_DOMAIN_EXPLAIN' => 'Domaine utilisé pour les emails générés.',
    'LOGIN_GARMIN' => 'Connexion Garmin Connect',
    'GARMIN_LOGIN_EXPLAIN' => 'Connectez-vous avec votre compte Garmin Connect existant.',
));