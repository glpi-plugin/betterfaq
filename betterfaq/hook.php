<?php

function plugin_betterfaq_install() {
   global $DB;

   $migration = new Migration(100);

   try {
      if (!$DB->tableExists('glpi_plugin_betterfaq_config')) {
         $query = "CREATE TABLE `glpi_plugin_betterfaq_config` (
                     `id`           int(11)      NOT NULL AUTO_INCREMENT,
                     `category_id`  int(11)      NOT NULL DEFAULT 0,
                     `config_key`   varchar(50)  COLLATE utf8mb4_unicode_ci NOT NULL,
                     `config_value` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                     `created_at`   timestamp    DEFAULT CURRENT_TIMESTAMP,
                     `updated_at`   timestamp    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                     PRIMARY KEY (`id`),
                     UNIQUE KEY `uniq_cat_key` (`category_id`, `config_key`)
                   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
         $migration->addPostQuery($query);
      }

      $migration->executeMigration();

      // Create uploads directory for category images
      $upload_dir = __DIR__ . '/uploads/categories/';
      if (!is_dir($upload_dir)) {
         mkdir($upload_dir, 0755, true);
      }

      // Register profile rights and grant super-admin
      PluginBetterfaqProfile::addDefaultProfileRights();

      return true;
   } catch (Exception $e) {
      error_log('BetterFAQ plugin install error - ' . get_class($e) . ': ' . $e->getMessage()
         . ' in ' . $e->getFile() . ' line ' . $e->getLine());
      return false;
   }
}

function plugin_betterfaq_redefine_menus(array $menu): array {
   if (Session::getCurrentInterface() !== 'helpdesk') {
      return $menu;
   }

   $menu['betterfaq'] = [
      'default' => '/plugins/betterfaq/front/index.php',
      'title'   => __('FAQ', 'betterfaq'),
      'icon'    => 'ti ti-help',
   ];

   return $menu;
}

function plugin_betterfaq_uninstall() {
   try {
      PluginBetterfaqProfile::removeRights();
      // Preserve config data for reinstall
      return true;
   } catch (Exception $e) {
      error_log('BetterFAQ plugin uninstall error - ' . get_class($e) . ': ' . $e->getMessage()
         . ' in ' . $e->getFile() . ' line ' . $e->getLine());
      return false;
   }
}

