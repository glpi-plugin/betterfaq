<?php

include("../../../inc/includes.php");

if (!Session::haveRight('plugin_betterfaq_config', UPDATE) && !Session::haveRight('config', UPDATE)) {
   Html::displayRightError();
   exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = isset($_POST['action']) ? $_POST['action'] : '';

   if ($action === 'save_categories') {
      global $DB;

      $cat_data = isset($_POST['cat']) && is_array($_POST['cat']) ? $_POST['cat'] : [];

      // Validate category IDs exist in DB
      $valid_ids = [];
      try {
         $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_knowbaseitemcategories',
         ]);
         foreach ($iterator as $row) {
            $valid_ids[] = (int) $row['id'];
         }
      } catch (Exception $e) {
         error_log('BetterFAQ config save: Error fetching categories - ' . $e->getMessage());
      }

      $upload_dir = dirname(__DIR__) . '/uploads/categories/';
      if (!is_dir($upload_dir)) {
         mkdir($upload_dir, 0755, true);
      }

      // Build list of all cat_ids we need to process (union of POST cat keys and uploaded files)
      $all_cat_ids = array_unique(array_merge(
         array_keys($cat_data),
         isset($_FILES['cat_image']['tmp_name']) && is_array($_FILES['cat_image']['tmp_name'])
            ? array_keys($_FILES['cat_image']['tmp_name'])
            : []
      ));

      foreach ($all_cat_ids as $cat_id) {
         $cat_id = (int) $cat_id;
         if (!in_array($cat_id, $valid_ids, true)) {
            continue;
         }

         $values = isset($cat_data[$cat_id]) ? $cat_data[$cat_id] : [];

         // Sort order
         $sort_order = isset($values['sort_order']) ? max(0, min(999, (int) $values['sort_order'])) : 0;
         PluginBetterfaqConfig::setConfig($cat_id, 'sort_order', (string) $sort_order);

         // Handle remove checkbox
         if (isset($values['remove_image']) && $values['remove_image'] === '1') {
            // Delete any existing uploaded file for this category
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            foreach ($allowed_exts as $ext) {
               $file = $upload_dir . 'cat_' . $cat_id . '.' . $ext;
               if (is_file($file)) {
                  unlink($file);
               }
            }
            PluginBetterfaqConfig::setConfig($cat_id, 'icon', '');
            continue;
         }

         // Handle new file upload
         if (
            isset($_FILES['cat_image']['tmp_name'][$cat_id]) &&
            !empty($_FILES['cat_image']['tmp_name'][$cat_id]) &&
            $_FILES['cat_image']['error'][$cat_id] === UPLOAD_ERR_OK &&
            is_uploaded_file($_FILES['cat_image']['tmp_name'][$cat_id])
         ) {
            $tmp_path = $_FILES['cat_image']['tmp_name'][$cat_id];
            $ext = null;

            // Try MIME type validation via finfo
            try {
               $finfo = new finfo(FILEINFO_MIME_TYPE);
               $mime  = $finfo->file($tmp_path);

               $mime_to_ext = [
                  'image/jpeg' => 'jpg',
                  'image/png'  => 'png',
                  'image/gif'  => 'gif',
                  'image/webp' => 'webp',
                  'image/svg+xml' => 'svg',
               ];

               if (isset($mime_to_ext[$mime])) {
                  $ext = $mime_to_ext[$mime];
               }
            } catch (Exception $e) {
               // finfo failed, log but try fallback
               error_log('BetterFAQ: finfo error for cat ' . $cat_id . ': ' . $e->getMessage());
            }

            // Fallback: validate extension if MIME failed
            if ($ext === null && isset($_FILES['cat_image']['name'][$cat_id])) {
               $fname_ext = strtolower(pathinfo($_FILES['cat_image']['name'][$cat_id], PATHINFO_EXTENSION));
               if (in_array($fname_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                  $ext = ($fname_ext === 'jpeg') ? 'jpg' : $fname_ext;
               }
            }

            if ($ext === null) {
               // No valid extension found
               error_log('BetterFAQ: No valid image extension for cat ' . $cat_id);
               continue;
            }

            $target = $upload_dir . 'cat_' . $cat_id . '.' . $ext;

            // Delete any previously stored file for this category (any extension)
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            foreach ($allowed_exts as $old_ext) {
               $old_file = $upload_dir . 'cat_' . $cat_id . '.' . $old_ext;
               if (is_file($old_file) && $old_file !== $target) {
                  unlink($old_file);
               }
            }

            if (move_uploaded_file($tmp_path, $target)) {
               $filename = 'cat_' . $cat_id . '.' . $ext;
               PluginBetterfaqConfig::setConfig($cat_id, 'icon', $filename);
               error_log('BetterFAQ: Saved image for cat ' . $cat_id . ' -> ' . $target . ' (stored as: ' . $filename . ')');
            } else {
               error_log('BetterFAQ: move_uploaded_file failed for cat ' . $cat_id . ' from ' . $tmp_path . ' to ' . $target . ' (target dir exists: ' . (is_dir(dirname($target)) ? 'yes' : 'no') . ')');
            }
         }
      }

      Session::addMessageAfterRedirect(__('Category settings saved.', 'betterfaq'), false, INFO);
   }

   // Redirect to prevent form resubmission
   $redirect_url = Plugin::getWebDir('betterfaq') . '/front/config.form.php?_glpi_tab=PluginBetterfaqConfig$1';
   Html::redirect($redirect_url);
}

Html::header(__('Better FAQ Configuration', 'betterfaq'), $_SERVER['PHP_SELF'], "config", "plugins");
PluginBetterfaqConfig::showConfigForm();
Html::footer();
