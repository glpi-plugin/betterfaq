<?php

define('PLUGIN_BETTERFAQ_VERSION', '1.0.0');
define('PLUGIN_BETTERFAQ_DIR', 'betterfaq');

function plugin_init_betterfaq() {
   global $PLUGIN_HOOKS;

   Plugin::loadLang('betterfaq');

   $PLUGIN_HOOKS['csrf_compliant']['betterfaq'] = true;

   Plugin::registerClass('PluginBetterfaqConfig', ['addtabon' => 'Config']);
   Plugin::registerClass('PluginBetterfaqProfile', ['addtabon' => 'Profile']);
   Plugin::registerClass('PluginBetterfaqFAQ');

   // Admin config under Configuration menu
   if (Session::haveRight('plugin_betterfaq_config', UPDATE) || Session::haveRight('config', UPDATE)) {
      $PLUGIN_HOOKS['config_page']['betterfaq'] = 'front/config.form.php';
      $PLUGIN_HOOKS['menu_toadd']['betterfaq']  = ['config' => 'PluginBetterfaqConfig'];
   }

   // FAQ browsing for all users (visibility controlled in getMenuContent)
   if (!isset($PLUGIN_HOOKS['menu_toadd']['betterfaq'])) {
      $PLUGIN_HOOKS['menu_toadd']['betterfaq'] = [];
   }
   $PLUGIN_HOOKS['menu_toadd']['betterfaq']['tools'] = 'PluginBetterfaqFAQ';

   // FAQ as a top-level nav item in the self-service interface — GLPI 11
   $PLUGIN_HOOKS['redefine_menus']['betterfaq'] = 'plugin_betterfaq_redefine_menus';
}

function plugin_version_betterfaq() {
   return [
      'name'         => 'FAQ',
      'version'      => PLUGIN_BETTERFAQ_VERSION,
      'author'       => 'DSI',
      'license'      => 'GPLv2+',
      'requirements' => [
         'glpi' => ['min' => '11.0.0'],
      ],
   ];
}

function plugin_betterfaq_check_prerequisites() { return true; }
function plugin_betterfaq_check_config()        { return true; }
