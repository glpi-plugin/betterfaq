<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginBetterfaqCategory {

   /**
    * Returns article IDs to HIDE from the current user, or null for super-admin (no restriction).
    * Articles with no targets are unpublished, so only articles with targets matching the user are visible.
    */
   private static function getHiddenArticleIds() {
      global $DB;

      try {
         if (Session::haveRight('config', UPDATE)) {
            return null; // Super-admin sees everything
         }

         $user_id    = (int) Session::getLoginUserID();
         $profile_id = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
         $entity_id  = (int) ($_SESSION['glpiactive_entity'] ?? 0);
         $groups     = isset($_SESSION['glpigroups']) ? array_map('intval', $_SESSION['glpigroups']) : [];

         // Get ALL FAQ article IDs (articles with no targets are unpublished and should be hidden)
         $all = [];
         foreach ($DB->request(['SELECT' => 'id', 'FROM' => 'glpi_knowbaseitems', 'WHERE' => ['is_faq' => 1]]) as $r) {
            $all[(int) $r['id']] = true;
         }

         if (empty($all)) {
            return null; // No articles at all
         }

         // Collect article IDs the current user CAN see (articles with targets matching the user)
         $visible = [];

         // Entity target: root entity (0) sees all entity-targeted articles
         if ($entity_id === 0) {
            foreach ($DB->request(['SELECT' => 'knowbaseitems_id', 'FROM' => 'glpi_entities_knowbaseitems']) as $r) {
               $visible[(int) $r['knowbaseitems_id']] = true;
            }
         } elseif ($entity_id > 0) {
            // Get ancestor entities to check recursive targets
            $ancestor_ids = array_keys(getAncestorsOf('glpi_entities', $entity_id));

            $where = ['OR' => [
               ['entities_id' => $entity_id],
            ]];

            if (!empty($ancestor_ids)) {
               $where['OR'][] = [
                  'entities_id'  => $ancestor_ids,
                  'is_recursive' => 1,
               ];
            }

            foreach ($DB->request(['SELECT' => 'knowbaseitems_id', 'FROM' => 'glpi_entities_knowbaseitems', 'WHERE' => $where]) as $r) {
               $visible[(int) $r['knowbaseitems_id']] = true;
            }
         }

         // Profile target
         if ($profile_id > 0) {
            foreach ($DB->request(['SELECT' => 'knowbaseitems_id', 'FROM' => 'glpi_knowbaseitems_profiles', 'WHERE' => ['profiles_id' => $profile_id]]) as $r) {
               $visible[(int) $r['knowbaseitems_id']] = true;
            }
         }

         // User target
         if ($user_id > 0) {
            foreach ($DB->request(['SELECT' => 'knowbaseitems_id', 'FROM' => 'glpi_knowbaseitems_users', 'WHERE' => ['users_id' => $user_id]]) as $r) {
               $visible[(int) $r['knowbaseitems_id']] = true;
            }
         }

         // Group targets
         if (!empty($groups)) {
            foreach ($DB->request(['SELECT' => 'knowbaseitems_id', 'FROM' => 'glpi_groups_knowbaseitems', 'WHERE' => ['groups_id' => $groups]]) as $r) {
               $visible[(int) $r['knowbaseitems_id']] = true;
            }
         }

         // Hide = all articles NOT in the visible set (includes articles with no targets + non-matching targeted articles)
         $hidden = array_diff_key($all, $visible);
         return !empty($hidden) ? $hidden : null;

      } catch (\Throwable $t) {
         error_log('BetterFAQ getHiddenArticleIds error: ' . $t->getMessage() . ' | Trace: ' . $t->getTraceAsString());
         return null; // Fallback: show all
      }
   }

   /**
    * Filter articles by excluding hidden IDs. Pass null for $hidden to skip filtering.
    */
   private static function filterByVisibility($articles, $hidden) {
      if ($hidden === null) {
         return $articles; // No restriction
      }

      $result = [];
      foreach ($articles as $article) {
         $id = (int) $article['id'];
         if (!isset($hidden[$id])) {
            $result[] = $article;
         }
      }
      return $result;
   }

   static function getRootCategories() {
      global $DB;

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
         error_log('BetterFAQ getRootCategories error: ' . $e->getMessage());
      }
      return $categories;
   }

   static function getRootCategoriesWithConfig() {
      $categories  = self::getRootCategories();
      $config_map  = PluginBetterfaqConfig::getCategoryConfigMap();

      foreach ($categories as &$cat) {
         $cat_id = (int) $cat['id'];
         $cat['icon']        = $config_map[$cat_id]['icon'] ?? '';
         $cat['color']       = $config_map[$cat_id]['color'] ?? '#0055a4';
         $cat['is_featured'] = ($config_map[$cat_id]['is_featured'] ?? '0') === '1';
         $cat['sort_order']  = (int) ($config_map[$cat_id]['sort_order'] ?? 0);
         $cat['article_count'] = self::countArticlesInCategory($cat_id);
      }
      unset($cat);

      usort($categories, function ($a, $b) {
         if ($a['sort_order'] !== $b['sort_order']) {
            return $a['sort_order'] - $b['sort_order'];
         }
         return strcasecmp($a['name'], $b['name']);
      });

      return $categories;
   }

   static function getChildCategories($parent_id) {
      global $DB;

      $categories = [];
      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitemcategories',
            'WHERE' => ['knowbaseitemcategories_id' => (int) $parent_id],
            'ORDER' => 'name ASC',
         ]);
         foreach ($iterator as $row) {
            $categories[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getChildCategories error: ' . $e->getMessage());
      }
      return $categories;
   }

   static function getChildCategoriesWithConfig($parent_id) {
      $children   = self::getChildCategories($parent_id);
      $config_map = PluginBetterfaqConfig::getCategoryConfigMap();

      foreach ($children as &$cat) {
         $cat_id = (int) $cat['id'];
         $cat['icon']       = $config_map[$cat_id]['icon'] ?? '';
         $cat['sort_order'] = (int) ($config_map[$cat_id]['sort_order'] ?? 0);
      }
      unset($cat);

      usort($children, function ($a, $b) {
         if ($a['sort_order'] !== $b['sort_order']) {
            return $a['sort_order'] - $b['sort_order'];
         }
         return strcasecmp($a['name'], $b['name']);
      });

      return $children;
   }

   static function getCategoryById($id) {
      global $DB;

      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitemcategories',
            'WHERE' => ['id' => (int) $id],
            'LIMIT' => 1,
         ]);
         foreach ($iterator as $row) {
            return $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getCategoryById error: ' . $e->getMessage());
      }
      return null;
   }

   static function getBreadcrumbChain($id) {
      $chain = [];
      $current_id = (int) $id;
      $max_depth = 20;

      while ($current_id > 0 && $max_depth > 0) {
         $cat = self::getCategoryById($current_id);
         if (!$cat) {
            break;
         }
         array_unshift($chain, $cat);
         $current_id = (int) ($cat['knowbaseitemcategories_id'] ?? 0);
         $max_depth--;
      }

      return $chain;
   }

   static function getArticlesByCategory($category_id) {
      global $DB;

      $articles = [];
      try {
         $iterator = $DB->request([
            'SELECT'    => ['kb.*'],
            'FROM'      => 'glpi_knowbaseitems AS kb',
            'LEFT JOIN' => [
               'glpi_knowbaseitems_knowbaseitemcategories AS kbc' => [
                  'ON' => [
                     'kbc' => 'knowbaseitems_id',
                     'kb'  => 'id',
                  ],
               ],
            ],
            'WHERE' => [
               'kbc.knowbaseitemcategories_id' => (int) $category_id,
               'kb.is_faq' => 1,
               'OR' => [
                  ['kb.begin_date' => null],
                  ['kb.begin_date' => ['<=', date('Y-m-d H:i:s')]],
               ],
            ],
            'GROUPBY' => 'kb.id',
            'ORDER'   => 'kb.name ASC',
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getArticlesByCategory error: ' . $e->getMessage());
      }

      $hidden = self::getHiddenArticleIds();
      return self::filterByVisibility($articles, $hidden);
   }

   static function getTopArticlesByCategory($category_id, $exclude_id = 0, $limit = 3) {
      global $DB;

      $articles = [];
      try {
         $where = [
            'kbc.knowbaseitemcategories_id' => (int) $category_id,
            'kb.is_faq' => 1,
            'OR' => [
               ['kb.begin_date' => null],
               ['kb.begin_date' => ['<=', date('Y-m-d H:i:s')]],
            ],
         ];
         if ($exclude_id > 0) {
            $where['NOT'] = ['kb.id' => (int) $exclude_id];
         }
         $iterator = $DB->request([
            'SELECT'    => ['kb.*'],
            'FROM'      => 'glpi_knowbaseitems AS kb',
            'LEFT JOIN' => [
               'glpi_knowbaseitems_knowbaseitemcategories AS kbc' => [
                  'ON' => [
                     'kbc' => 'knowbaseitems_id',
                     'kb'  => 'id',
                  ],
               ],
            ],
            'WHERE'   => $where,
            'GROUPBY' => 'kb.id',
            'ORDER'   => 'kb.view DESC',
            'LIMIT'   => (int) $limit,
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getTopArticlesByCategory error: ' . $e->getMessage());
      }

      $hidden = self::getHiddenArticleIds();
      return self::filterByVisibility($articles, $hidden);
   }

   static function countArticlesInCategory($category_id) {
      return count(self::getArticlesByCategory($category_id));
   }

   static function buildFullTree() {
      global $DB;

      $all = [];
      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitemcategories',
            'ORDER' => 'name ASC',
         ]);
         foreach ($iterator as $row) {
            $all[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ buildFullTree error: ' . $e->getMessage());
         return [];
      }

      $by_parent = [];
      foreach ($all as $cat) {
         $parent = (int) ($cat['knowbaseitemcategories_id'] ?? 0);
         $by_parent[$parent][] = $cat;
      }

      return self::buildTreeRecursive($by_parent, 0);
   }

   private static function buildTreeRecursive(&$by_parent, $parent_id) {
      $tree = [];
      if (!isset($by_parent[$parent_id])) {
         return $tree;
      }
      foreach ($by_parent[$parent_id] as $cat) {
         $cat['children'] = self::buildTreeRecursive($by_parent, (int) $cat['id']);
         $tree[] = $cat;
      }
      return $tree;
   }

   static function searchArticles($keyword, $limit = 50) {
      global $DB;

      $articles = [];
      $keyword = trim($keyword);
      if (empty($keyword)) {
         return $articles;
      }

      try {
         $search_term = '%' . $DB->escape($keyword) . '%';

         $iterator = $DB->request([
            'SELECT'    => ['kb.*', 'kbc.knowbaseitemcategories_id'],
            'FROM'      => 'glpi_knowbaseitems AS kb',
            'LEFT JOIN' => [
               'glpi_knowbaseitems_knowbaseitemcategories AS kbc' => [
                  'ON' => [
                     'kbc' => 'knowbaseitems_id',
                     'kb'  => 'id',
                  ],
               ],
            ],
            'WHERE' => [
               'kb.is_faq' => 1,
               'OR' => [
                  ['kb.name'   => ['LIKE', $search_term]],
                  ['kb.answer' => ['LIKE', $search_term]],
               ],
            ],
            'GROUPBY' => 'kb.id',
            'ORDER'   => 'kb.name ASC',
            'LIMIT'   => (int) $limit,
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ searchArticles error: ' . $e->getMessage());
      }

      $hidden = self::getHiddenArticleIds();
      return self::filterByVisibility($articles, $hidden);
   }

   static function getArticleById($id) {
      global $DB;

      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitems',
            'WHERE' => ['id' => (int) $id, 'is_faq' => 1],
            'LIMIT' => 1,
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getArticleById error: ' . $e->getMessage());
         return null;
      }

      if (empty($articles)) {
         return null;
      }

      $hidden = self::getHiddenArticleIds();
      $filtered = self::filterByVisibility($articles, $hidden);
      return !empty($filtered) ? $filtered[0] : null;
   }

   static function getArticleCategoryId($article_id) {
      global $DB;

      try {
         $iterator = $DB->request([
            'SELECT' => ['knowbaseitemcategories_id'],
            'FROM'   => 'glpi_knowbaseitems_knowbaseitemcategories',
            'WHERE'  => ['knowbaseitems_id' => (int) $article_id],
            'LIMIT'  => 1,
         ]);
         foreach ($iterator as $row) {
            return (int) $row['knowbaseitemcategories_id'];
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getArticleCategoryId error: ' . $e->getMessage());
      }
      return 0;
   }

   static function getMostViewedArticles($limit = 10) {
      global $DB;

      $articles = [];
      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitems',
            'WHERE' => [
               'is_faq' => 1,
               'OR' => [
                  ['begin_date' => null],
                  ['begin_date' => ['<=', date('Y-m-d H:i:s')]],
               ],
            ],
            'ORDER' => 'view DESC',
            'LIMIT' => (int) $limit,
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getMostViewedArticles error: ' . $e->getMessage());
      }

      $hidden = self::getHiddenArticleIds();
      return self::filterByVisibility($articles, $hidden);
   }

   static function getNewestArticles($limit = 10) {
      global $DB;

      $articles = [];
      try {
         $iterator = $DB->request([
            'FROM'  => 'glpi_knowbaseitems',
            'WHERE' => [
               'is_faq' => 1,
               'OR' => [
                  ['begin_date' => null],
                  ['begin_date' => ['<=', date('Y-m-d H:i:s')]],
               ],
            ],
            'ORDER' => 'date_creation DESC',
            'LIMIT' => (int) $limit,
         ]);
         foreach ($iterator as $row) {
            $articles[] = $row;
         }
      } catch (Exception $e) {
         error_log('BetterFAQ getNewestArticles error: ' . $e->getMessage());
      }

      $hidden = self::getHiddenArticleIds();
      return self::filterByVisibility($articles, $hidden);
   }

   public static function renderTree($tree, $active_ids, $base_url, $current_id = 0, $config_map = []) {
      if (empty($tree)) {
         return '';
      }
      $html = '<ul>';
      foreach ($tree as $node) {
         $html .= self::renderNode($node, $active_ids, $base_url, $current_id, 0, $config_map);
      }
      $html .= '</ul>';
      return $html;
   }

   public static function renderNode($node, $active_ids, $base_url, $current_id = 0, $depth = 0, $config_map = []) {
      $node_id      = (int) $node['id'];
      $has_children = !empty($node['children']);
      $is_active    = $node_id === (int) $current_id;
      $in_path      = in_array($node_id, $active_ids, true);

      // Get icon from config
      $icon = $config_map[$node_id]['icon'] ?? 'ti ti-folder';
      if (!empty($icon)) {
         if (strpos($icon, 'ti ') === 0) {
            $icon = '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i>';
         } elseif (strpos($icon, '<i') === false) {
            $icon = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
         }
      } else {
         $icon = '<i class="ti ti-folder"></i>';
      }

      $html = '<li>';

      if ($has_children) {
         $is_expanded  = ($in_path || $is_active) ? 'true' : 'false';
         $toggle_class = ($in_path || $is_active) ? ' expanded' : '';
         $html .= '<button type="button" aria-expanded="' . $is_expanded . '" aria-pressed="false">'
            . '<span class="bfaq-button-text">' . htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="bfaq-tree-toggle' . $toggle_class . '"><i class="ti ti-chevron-down"></i></span>'
            . '</button>';

         $display = ($in_path || $is_active) ? '' : ' style="display:none"';
         $html .= '<ul' . $display . '>';
         foreach ($node['children'] as $child) {
            $html .= self::renderNode($child, $active_ids, $base_url, $current_id, $depth + 1, $config_map);
         }
         $html .= '</ul>';
      } else {
         $active_class = $is_active ? ' class="active"' : '';
         $html .= '<a href="' . htmlspecialchars($base_url . '/front/index.php?id=' . $node_id, ENT_QUOTES, 'UTF-8') . '"' . $active_class . '>'
            . '<span class="bfaq-button-text">' . htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8') . '</span>'
            . '</a>';
      }

      $html .= '</li>';
      return $html;
   }
}
