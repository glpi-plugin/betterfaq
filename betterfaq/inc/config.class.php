<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginBetterfaqConfig extends CommonGLPI {

   public static $rightname = 'plugin_betterfaq_config';

   static function getTypeName($nb = 0) {
      return __('Better FAQ', 'betterfaq');
   }

   static function getMenuName() {
      return __('Better FAQ', 'betterfaq');
   }

   static function getMenuContent() {
      global $CFG_GLPI;

      if (Session::haveRight('plugin_betterfaq_config', UPDATE) || Session::haveRight('config', UPDATE)) {
         return [
            'title' => self::getMenuName(),
            'page'  => $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR . '/front/config.form.php',
            'icon'  => 'ti ti-help',
         ];
      }

      return false;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() == 'Config') {
         return [
            1 => __('Category Images', 'betterfaq'),
         ];
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      if ($item->getType() == 'Config') {
         switch ($tabnum) {
            case 1:
               self::showCategoryImagesTab();
               break;
         }
      }
      return true;
   }

   static function showConfigForm() {
      if (!Session::haveRight('plugin_betterfaq_config', UPDATE) && !Session::haveRight('config', UPDATE)) {
         return false;
      }

      echo "<div class='center'>";
      self::showCategoryImagesTab();
      echo "</div>";
   }

   static function showCategoryImagesTab() {
      global $DB, $CFG_GLPI;

      // Get root KB categories
      $categories = [];
      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitemcategories',
            'WHERE' => [
               'OR' => [
                  ['knowbaseitemcategories_id' => 0],
                  ['knowbaseitemcategories_id' => null],
               ],
            ],
            'ORDER' => 'name ASC',
         ]);
         foreach ($iterator as $row) {
            $categories[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ: Error loading categories - ' . $e->getMessage());
      }

      $config_map  = self::getCategoryConfigMap();
      $action_url  = $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR . '/front/config.form.php';
      $uploads_dir = dirname(__DIR__) . '/uploads/categories/';
      $image_url   = $CFG_GLPI['root_doc'] . '/plugins/betterfaq/ajax/get_image.php?f=';

      echo "<form method='POST' action='" . htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8') . "' enctype='multipart/form-data'>";
      echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
      echo Html::hidden('action', ['value' => 'save_categories']);

      echo "<div style='padding: 10px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; margin-bottom: 15px;'>";
      echo "<p style='margin: 0;'><strong>" . __('Info') . ":</strong> " . __('Upload an image for each root-level knowledge base category. Images are displayed on the FAQ home page.', 'betterfaq') . "</p>";
      echo "</div>";

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr>";
      echo "<th>" . __('Category', 'betterfaq') . "</th>";
      echo "<th>" . __('Current Image', 'betterfaq') . "</th>";
      echo "<th>" . __('Upload New Image', 'betterfaq') . "</th>";
      echo "<th>" . __('Sort Order', 'betterfaq') . "</th>";
      echo "</tr>";

      if (count($categories) === 0) {
         echo "<tr><td colspan='4' class='center' style='padding:20px; color:#6c757d;'>"
            . __('No knowledge base categories found. Create categories in Setup > Dropdowns > Knowledge base categories.', 'betterfaq')
            . "</td></tr>";
      }

      foreach ($categories as $cat) {
         $cat_id     = (int) $cat['id'];
         $icon       = $config_map[$cat_id]['icon'] ?? '';
         $sort_order = $config_map[$cat_id]['sort_order'] ?? '0';

         // Determine current image cell
         $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
         $ext = strtolower(pathinfo($icon, PATHINFO_EXTENSION));
         $has_image = !empty($icon) && in_array($ext, $allowed_img_exts, true) && is_file($uploads_dir . $icon);

         echo "<tr class='tab_bg_2'>";
         echo "<td><strong>" . htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') . "</strong></td>";

         // Current Image cell
         echo "<td style='text-align:center;'>";
         if ($has_image) {
            echo "<img src='" . htmlspecialchars($image_url . urlencode($icon), ENT_QUOTES, 'UTF-8') . "' alt='' style='width:60px; height:60px; object-fit:contain; display:block; margin:0 auto 6px auto;'>";
            echo "<label style='font-size:0.85em; color:#666;'>";
            echo "<input type='checkbox' name='cat[" . $cat_id . "][remove_image]' value='1'> " . __('Remove', 'betterfaq');
            echo "</label>";
         } else {
            echo "<span style='color:#999; font-size:0.9em;'>" . __('None', 'betterfaq') . "</span>";
         }
         echo "</td>";

         // Upload cell
         echo "<td><input type='file' name='cat_image[" . $cat_id . "]' accept='image/jpeg,image/png,image/gif,image/webp,image/svg+xml'></td>";

         // Sort Order cell
         echo "<td><input type='number' name='cat[" . $cat_id . "][sort_order]' value='" . htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8') . "' style='width:60px;' min='0' max='999'></td>";

         echo "</tr>";
      }

      echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
      echo "<button type='submit' class='btn btn-primary'>" . __('Save') . "</button>";
      echo "</td></tr>";

      echo "</table>";
      echo "</form>";
   }

   static function getGlobalConfig($key) {
      global $DB;

      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_betterfaq_config',
            'WHERE' => ['category_id' => 0, 'config_key' => $key],
            'LIMIT' => 1,
         ]);
         foreach ($iterator as $row) {
            return $row['config_value'];
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getGlobalConfig error: ' . $e->getMessage());
      }
      return '';
   }

   static function setConfig($category_id, $key, $value) {
      global $DB;

      try {
         $existing = $DB->request([
            'FROM'  => 'glpi_plugin_betterfaq_config',
            'WHERE' => ['category_id' => (int) $category_id, 'config_key' => $key],
         ]);

         $exists = false;
         foreach ($existing as $r) {
            $exists = true;
            break;
         }

         if ($exists) {
            $DB->update('glpi_plugin_betterfaq_config',
               ['config_value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
               ['category_id' => (int) $category_id, 'config_key' => $key]
            );
         } else {
            $DB->insert('glpi_plugin_betterfaq_config', [
               'category_id'  => (int) $category_id,
               'config_key'   => $key,
               'config_value' => $value,
            ]);
         }
         return true;
      } catch (Exception $e) {
         error_log('BetterFAQ setConfig error: ' . $e->getMessage());
         return false;
      }
   }

   static function getCategoryConfigMap() {
      global $DB;

      $map = [];
      try {
         $iterator = $DB->request([
            'FROM' => 'glpi_plugin_betterfaq_config',
            'WHERE' => ['category_id' => ['>', 0]],
         ]);
         foreach ($iterator as $row) {
            $cat_id = (int) $row['category_id'];
            if (!isset($map[$cat_id])) {
               $map[$cat_id] = [];
            }
            $map[$cat_id][$row['config_key']] = $row['config_value'];
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getCategoryConfigMap error: ' . $e->getMessage());
      }
      return $map;
   }
}
