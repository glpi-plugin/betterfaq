<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginBetterfaqFAQ extends CommonGLPI {

   public static $rightname = 'plugin_betterfaq_faq';

   static function getTypeName($nb = 0) {
      return __('FAQ', 'betterfaq');
   }

   static function getMenuName() {
      return __('FAQ', 'betterfaq');
   }

   static function getMenuContent() {
      global $CFG_GLPI;

      // Show for users with FAQ view permission
      if (Session::haveRight('plugin_betterfaq_faq', READ)) {
         return [
            'title' => self::getMenuName(),
            'page'  => $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR . '/front/index.php',
            'icon'  => 'ti ti-help',
         ];
      }

      // Always show for helpdesk users
      if (Session::getCurrentInterface() === 'helpdesk') {
         return [
            'title' => self::getMenuName(),
            'page'  => $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR . '/front/index.php',
            'icon'  => 'ti ti-help',
         ];
      }

      return false;
   }
}
