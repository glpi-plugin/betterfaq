<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginBetterfaqProfile extends CommonDBTM {

   public static $rightname = 'config';

   static function getTypeName($nb = 0) {
      return __('Better FAQ', 'betterfaq');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item instanceof Profile) {
         return __('Better FAQ', 'betterfaq');
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item instanceof Profile) {
         self::showProfileForm($item);
      }
      return true;
   }

   static function showProfileForm(Profile $profile) {
      $rights  = self::getAllRights();
      $canedit = Session::haveRight('config', UPDATE);
      $ID      = $profile->getID();

      echo '<div class="card mt-3">';
      echo '<div class="card-header"><h5>' . __('Better FAQ - Permissions', 'betterfaq') . '</h5></div>';
      echo '<div class="card-body">';

      if ($canedit) {
         echo '<form method="POST" action="' . Profile::getFormURL() . '">';
         echo Html::hidden('id', ['value' => $ID]);
         echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      }

      $profile->displayRightsChoiceMatrix($rights, [
         'canedit'       => $canedit,
         'default_class' => 'tab_bg_2',
         'title'         => __('Better FAQ plugin', 'betterfaq'),
      ]);

      if ($canedit) {
         echo '<div class="mt-3 text-center">';
         echo '<button type="submit" name="update" class="btn btn-primary">' . __('Save') . '</button>';
         echo '</div>';
         echo '</form>';
      }

      echo '</div></div>';
   }

   static function getAllRights($all = false) {
      return [
         [
            'itemtype' => 'PluginBetterfaqConfig',
            'label'    => __('Manage FAQ configuration', 'betterfaq'),
            'field'    => 'plugin_betterfaq_config',
            'rights'   => [
               READ   => __('Read'),
               UPDATE => __('Update'),
            ],
         ],
         [
            'itemtype' => 'PluginBetterfaqConfig',
            'label'    => __('View FAQ', 'betterfaq'),
            'field'    => 'plugin_betterfaq_faq',
            'rights'   => [
               READ => __('Read'),
            ],
         ],
      ];
   }

   static function addDefaultProfileRights() {
      global $DB;

      $right_names = ['plugin_betterfaq_config', 'plugin_betterfaq_faq'];

      foreach ($right_names as $right_name) {
         $existing_profiles = [];
         $iterator = $DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => $right_name],
         ]);
         foreach ($iterator as $row) {
            $existing_profiles[] = (int) $row['profiles_id'];
         }

         $all_profiles = $DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']);
         foreach ($all_profiles as $profile) {
            if (!in_array((int) $profile['id'], $existing_profiles, true)) {
               $DB->insert('glpi_profilerights', [
                  'profiles_id' => $profile['id'],
                  'name'        => $right_name,
                  'rights'      => 0,
               ]);
            }
         }
      }

      // Find super-admin profile
      $super_admin_id = 0;
      $sa_iter = $DB->request([
         'SELECT' => ['id'],
         'FROM'   => 'glpi_profiles',
         'WHERE'  => ['name' => 'Super-Admin'],
         'LIMIT'  => 1,
      ]);
      foreach ($sa_iter as $sa_row) {
         $super_admin_id = (int) $sa_row['id'];
      }
      if (!$super_admin_id) {
         $fb_iter = $DB->request([
            'SELECT' => ['profiles_id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => ['name' => 'config', 'rights' => ['>', 0]],
            'ORDER'  => 'profiles_id ASC',
            'LIMIT'  => 1,
         ]);
         foreach ($fb_iter as $fb_row) {
            $super_admin_id = (int) $fb_row['profiles_id'];
         }
      }
      if (!$super_admin_id) {
         return;
      }

      // Grant super-admin READ|UPDATE on config, READ on faq
      $grants = [
         'plugin_betterfaq_config' => READ | UPDATE,
         'plugin_betterfaq_faq'    => READ,
      ];

      foreach ($grants as $right_name => $rights_val) {
         $has_row = $DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => ['profiles_id' => $super_admin_id, 'name' => $right_name],
         ])->count() > 0;

         if ($has_row) {
            $DB->update('glpi_profilerights',
               ['rights' => $rights_val],
               ['profiles_id' => $super_admin_id, 'name' => $right_name]
            );
         } else {
            $DB->insert('glpi_profilerights', [
               'profiles_id' => $super_admin_id,
               'name'        => $right_name,
               'rights'      => $rights_val,
            ]);
         }
      }
   }

   static function removeRights() {
      ProfileRight::deleteProfileRights(['plugin_betterfaq_config', 'plugin_betterfaq_faq']);
   }
}
