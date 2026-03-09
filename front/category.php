<?php

include("../../../inc/includes.php");

Session::checkLoginUser();

// Permission check with helpdesk fallback
$has_access = Session::haveRight('plugin_betterfaq_faq', READ)
   || Session::haveRight('plugin_betterfaq_config', READ)
   || Session::haveRight('config', READ);

if (!$has_access) {
   $interface = Session::getCurrentInterface();
   if ($interface === 'helpdesk') {
      $has_access = true;
   }
}

if (!$has_access) {
   Html::displayRightError();
   exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
   Html::displayNotFoundError();
   exit;
}

$article_id = isset($_GET['article_id']) ? (int) $_GET['article_id'] : 0;

global $CFG_GLPI;
$base_url      = $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR;
$hero_title    = PluginBetterfaqConfig::getGlobalConfig('hero_title') ?: __('FAQ', 'betterfaq');

$category = PluginBetterfaqCategory::getCategoryById($id);
if (!$category) {
   Html::displayNotFoundError();
   exit;
}

// Handle inline article view
$article = null;
if ($article_id > 0) {
   $article = PluginBetterfaqCategory::getArticleById($article_id);
   if (!$article) {
      Html::displayNotFoundError();
      exit;
   }

   // Update view counter
   $kb_item = new KnowbaseItem();
   if ($kb_item->getFromDB($article_id)) {
      $kb_item->updateCounter();
   }

   // Sanitize content
   $article['content'] = $article['answer'] ?? '';
   if (class_exists('RichText')) {
      $article['content'] = RichText::getSafeHtml($article['content']);
   }
}

$breadcrumb  = PluginBetterfaqCategory::getBreadcrumbChain($id);
$children    = PluginBetterfaqCategory::getChildCategories($id);
$articles    = PluginBetterfaqCategory::getArticlesByCategory($id);
$config_map  = PluginBetterfaqConfig::getCategoryConfigMap();
$full_tree   = PluginBetterfaqCategory::buildFullTree();

// Handle search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
if (!empty($search_query)) {
   $articles = PluginBetterfaqCategory::searchArticles($search_query, 200);
}

// Pagination
$per_page       = 10;
$total_articles = count($articles);
$total_pages    = max(1, (int) ceil($total_articles / $per_page));
$page           = isset($_GET['page']) ? max(1, min((int) $_GET['page'], $total_pages)) : 1;
$articles       = array_slice($articles, ($page - 1) * $per_page, $per_page);

// Get active path IDs for auto-expanding sidebar
$active_ids = [];
foreach ($breadcrumb as $bc) {
   $active_ids[] = (int) $bc['id'];
}

// Determine back URL for bottom nav
$breadcrumb_count = count($breadcrumb);
if ($breadcrumb_count >= 2) {
   $parent = $breadcrumb[$breadcrumb_count - 2];
   $back_url = htmlspecialchars($base_url . '/front/category.php?id=' . (int) $parent['id'], ENT_QUOTES, 'UTF-8');
} else {
   $back_url = htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8');
}

$interface = Session::getCurrentInterface();
if ($interface === 'helpdesk') {
   Html::helpHeader(__('FAQ', 'betterfaq'));
} else {
   Html::header(__('FAQ', 'betterfaq'), $_SERVER['PHP_SELF'], 'tools');
}

?>
<style>
.bfaq-layout {
   display: flex;
   gap: 30px;
   max-width: 1400px;
   margin: 20px auto;
   padding: 0 20px;
}

/* Sidebar */
.bfaq-sidebar {
   width: 280px;
   flex-shrink: 0;
   position: sticky;
   top: 80px;
   align-self: flex-start;
   max-height: calc(100vh - 100px);
   overflow-y: auto;
}

.bfaq-sidebar-inner {
   padding: 0 !important;
   background: transparent !important;
   border: none !important;
   box-shadow: none !important;
   overflow: visible !important;
}

.bfaq-sidebar h5 {
   display: flex !important;
   align-items: center !important;
   gap: 10px !important;
   margin: 0 0 8px 0 !important;
   padding: 12px 16px !important;
   font-size: 0.95em !important;
   font-weight: 700 !important;
   color: var(--bs-body-color, #1f2937) !important;
   border: 1px solid var(--bs-border-color, #d1d5db) !important;
   border-radius: 6px !important;
   box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
   background: var(--bs-body-bg, #fff) !important;
}

.bfaq-sidebar h5::before {
   content: '?' !important;
   display: inline-flex !important;
   align-items: center !important;
   justify-content: center !important;
   width: 28px !important;
   height: 28px !important;
   background: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
   color: var(--glpi-mainmenu-fg, #fff) !important;
   border-radius: 50% !important;
   font-size: 1em !important;
   font-weight: 700 !important;
   flex-shrink: 0 !important;
}

.bfaq-sidebar ul {
   list-style: none;
   padding: 0;
   margin: 0;
}

.bfaq-sidebar ul ul {
   list-style: none;
   padding: 0;
   margin: 0;
}

.bfaq-sidebar li {
   margin: 0;
}

.bfaq-sidebar-inner > ul > li {
   margin-bottom: 8px !important;
   border: 1px solid var(--bs-border-color, #d1d5db) !important;
   border-radius: 6px !important;
   box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
   overflow: hidden !important;
   background: var(--bs-body-bg, #fff) !important;
}

/* Root level buttons (accordion headers with children) */
.bfaq-sidebar-inner > ul > li > button {
   all: unset !important;
   display: flex !important;
   align-items: center !important;
   justify-content: space-between !important;
   width: 100% !important;
   padding: 20px 16px !important;
   color: var(--bs-body-color, #1f2937) !important;
   background: var(--bs-body-bg, #fff) !important;
   border: none !important;
   border-bottom: 1px solid var(--bs-border-color, #e5e7eb) !important;
   text-align: left !important;
   cursor: pointer !important;
   font-family: inherit !important;
   font-size: 0.95em !important;
   font-weight: 600 !important;
   transition: background-color 0.2s ease !important;
   box-sizing: border-box !important;
}

.bfaq-sidebar-inner > ul > li > button:hover {
   background: var(--bs-secondary-bg, #f9fafb) !important;
}

.bfaq-sidebar-inner > ul > li > button:focus {
   outline: 2px solid var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
   outline-offset: -2px !important;
}

/* Nested buttons (subcategories with children) */
.bfaq-sidebar ul ul > li > button {
   all: unset !important;
   display: flex !important;
   align-items: center !important;
   justify-content: space-between !important;
   width: 100% !important;
   padding: 20px 16px !important;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
   background: var(--bs-body-bg, #fff) !important;
   border: none !important;
   border-bottom: 1px solid var(--bs-border-color, #e5e7eb) !important;
   text-align: left !important;
   cursor: pointer !important;
   font-family: inherit !important;
   font-size: 0.95em !important;
   font-weight: 600 !important;
   transition: background-color 0.2s ease !important;
   box-sizing: border-box !important;
}

.bfaq-sidebar ul ul > li > button:hover {
   background: var(--bs-secondary-bg, #efefef) !important;
}

.bfaq-sidebar button:last-child {
   border-bottom: none !important;
}

.bfaq-button-icon {
   display: inline-flex !important;
   align-items: center !important;
   justify-content: center !important;
   width: 32px !important;
   height: 32px !important;
   margin-right: 12px !important;
   font-size: 1.2em !important;
   flex-shrink: 0 !important;
}

.bfaq-button-text {
   flex: 1 !important;
}

/* Root level links (leaf items, no children) */
.bfaq-sidebar-inner > ul > li > a {
   all: unset !important;
   display: flex !important;
   align-items: center !important;
   justify-content: space-between !important;
   width: 100% !important;
   padding: 20px 16px !important;
   color: var(--bs-body-color, #1f2937) !important;
   background: var(--bs-body-bg, #fff) !important;
   border: none !important;
   border-bottom: 1px solid var(--bs-border-color, #e5e7eb) !important;
   text-decoration: none !important;
   font-family: inherit !important;
   font-size: 0.95em !important;
   font-weight: 600 !important;
   transition: background-color 0.2s ease !important;
   cursor: pointer !important;
   box-sizing: border-box !important;
}

.bfaq-sidebar-inner > ul > li > a:hover {
   background: var(--bs-secondary-bg, #f9fafb) !important;
}

.bfaq-sidebar-inner > ul > li > a.active {
   background: var(--bs-secondary-bg, #f0f4ff) !important;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
}

.bfaq-sidebar-inner > ul > li:last-child > a {
   border-bottom: none !important;
}

/* Nested items (under collapsed/expanded sections) */
.bfaq-sidebar-inner > ul > li > ul {
   background: var(--bs-body-bg, #fff) !important;
   border: none !important;
   margin: 0 !important;
   padding: 0 !important;
}

.bfaq-sidebar ul ul ul {
   background: transparent !important;
   border: none !important;
   margin: 0 !important;
   padding: 0 !important;
}

.bfaq-sidebar ul ul a {
   all: unset !important;
   display: block !important;
   width: 100% !important;
   padding: 20px 16px !important;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
   background: var(--bs-body-bg, #fff) !important;
   border: none !important;
   border-bottom: 1px solid var(--bs-border-color, #e5e7eb) !important;
   text-decoration: none !important;
   font-family: inherit !important;
   font-size: 0.95em !important;
   transition: background-color 0.2s ease !important;
   cursor: pointer !important;
   box-sizing: border-box !important;
}

/* Depth-2+ buttons */
.bfaq-sidebar ul ul ul > li > button {
   padding: 10px 16px 10px 32px !important;
   color: var(--bs-body-color, #374151) !important;
   background: var(--bs-body-bg, #fff) !important;
}

/* Depth-2+ links */
.bfaq-sidebar ul ul ul a {
   padding: 20px 16px 20px 32px !important;
   color: var(--bs-body-color, #374151) !important;
   background: var(--bs-body-bg, #fff) !important;
}

.bfaq-sidebar ul ul a:hover {
   background: var(--bs-secondary-bg, #efefef) !important;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
}

.bfaq-sidebar ul ul a.active {
   background: var(--bs-secondary-bg, #efefef) !important;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64)) !important;
   font-weight: 600 !important;
}

.bfaq-sidebar ul ul a:last-child {
   border-bottom: none !important;
}

.bfaq-sidebar button:focus-visible {
   outline: 2px solid var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   outline-offset: -2px;
}

/* Chevron for section headers */
.bfaq-tree-toggle {
   display: inline-flex;
   align-items: center;
   justify-content: center;
   width: 20px;
   height: 20px;
   flex-shrink: 0;
   margin-left: auto;
   transition: transform 0.2s;
   font-size: 0.7em;
   color: #9ca3af;
}

.bfaq-tree-toggle.expanded {
   transform: rotate(180deg);
}

/* Main Content */
.bfaq-main {
   flex: 1;
   min-width: 0;
}

.bfaq-article-container {
   background-color: var(--bs-body-bg, #fff);
   border-radius: 4px;
}

.bfaq-category-title {
   font-size: 1.6em;
   font-weight: 700;
   color: var(--bs-body-color, #1f2937);
   margin: 0;
   padding: 20px 20px 0 20px;
}

.bfaq-category-list-wrapper {
   padding: 20px;
}

.bfaq-article-row {
   display: flex;
   align-items: center;
   border-bottom: 1px solid var(--bs-border-color, #e5e7eb);
   padding: 7px;
}

.bfaq-article-row a {
   flex: 1;
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   text-decoration: none;
   font-size: 1em;
}

.bfaq-article-row a:hover {
   text-decoration: underline;
}

.bfaq-article-chevron {
   color: #9ca3af;
   font-size: 1.7em;
   flex-shrink: 0;
   margin-left: 12px;
}

.bfaq-related-articles {
   margin: 0;
   padding: 20px;
}
.bfaq-related-articles h3 {
   font-size: 1.5rem;
   font-weight: 600;
   color: var(--tblr-muted, #6c757d);
   text-transform: uppercase;
   letter-spacing: 0.05em;
   margin: 0 0 0.75rem 0;
}

.bfaq-bottom-nav {
   display: flex;
   justify-content: space-between;
   margin: 0;
   padding: 20px;
}

.bfaq-bottom-nav a {
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   text-decoration: none;
   font-size: 1em;
}

.bfaq-bottom-nav a:hover {
   text-decoration: underline;
}

.bfaq-need-help {
   display: grid;
   grid-template-columns: 280px 1fr;
   gap: 30px;
   max-width: 1400px;
   margin: 40px auto 0 auto;
   padding: 0 20px;
}

.bfaq-need-help-spacer {
   /* Empty column to align with sidebar */
}

.bfaq-need-help-content {
   background: var(--bs-body-bg, #fff);
   padding: 30px 30px;
   border-top: 1px solid var(--bs-border-color, #e5e7eb);
}

.bfaq-need-help-content h3 {
   margin: 0 0 20px 0;
   font-size: 1.4em;
   font-weight: 700;
   color: var(--bs-body-color, #222);
}

.bfaq-need-help-content a {
   display: inline-block;
   background: var(--bs-body-bg, #fff);
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   padding: 10px 24px;
   border-radius: 6px;
   border: 1px solid var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   text-decoration: none;
   font-weight: 600;
   font-size: 0.95em;
   transition: background 0.2s, color 0.2s;
}

.bfaq-need-help-content a:hover {
   background: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   color: var(--glpi-mainmenu-fg, #fff);
   text-decoration: none;
}

@media (max-width: 768px) {
   .bfaq-layout {
      flex-direction: column;
   }
   .bfaq-sidebar {
      width: 100%;
      position: static;
      max-height: none;
   }
}

.bfaq-search-form {
   max-width: 600px;
   margin: 0 0 20px 0;
   display: flex;
   gap: 0;
}
.bfaq-search-form input[type="text"] {
   flex: 1;
   padding: 12px 18px;
   border: 1px solid var(--bs-border-color, #e5e7eb);
   border-radius: 6px 0 0 6px;
   font-size: 0.95em;
   outline: none;
   background: var(--bs-body-bg, #fff);
   color: var(--bs-body-color, #333);
}
.bfaq-search-form button {
   padding: 12px 20px;
   border: 1px solid var(--bs-border-color, #e5e7eb);
   border-left: none;
   border-radius: 0 6px 6px 0;
   background: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   color: var(--glpi-mainmenu-fg, #fff);
   font-size: 0.95em;
   cursor: pointer;
   transition: background-color 0.2s ease;
}
.bfaq-search-form button:hover {
   opacity: 0.9;
}

/* Article view styles */
.bfaq-article-header {
   border-top: 4px solid var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   padding: 20px;
   margin: 0;
}
.bfaq-article-header h1 {
   font-size: 2em;
   margin: 0 0 8px 0;
   color: var(--bs-body-color, #222);
}
.bfaq-article-meta {
   color: #6c757d;
   font-size: 0.85em;
   display: flex;
   gap: 20px;
   flex-wrap: wrap;
}
.bfaq-article-body {
   line-height: 1.7;
   font-size: 1.2em;
   color: var(--bs-body-color, #333);
   padding: 20px;
}
.bfaq-article-body img {
   max-width: 100%;
   height: auto;
   border-radius: 4px;
}
.bfaq-article-body table {
   border-collapse: collapse;
   width: 100%;
   margin: 15px 0;
}
.bfaq-article-body table th,
.bfaq-article-body table td {
   border: 1px solid var(--bs-border-color, #ddd);
   padding: 8px 12px;
   text-align: left;
}
/* Breadcrumb separator */
.breadcrumb-item + .breadcrumb-item::before {
   content: ">" !important;
}

/* Pagination */
.bfaq-pagination {
   display: flex;
   align-items: center;
   justify-content: center;
   gap: 16px;
   padding: 16px 0 4px 0;
   font-size: 0.95em;
}
.bfaq-pagination a {
   color: var(--glpi-mainmenu-bg, var(--bs-primary, #2f3f64));
   text-decoration: none;
   padding: 6px 14px;
   border: 1px solid var(--bs-border-color, #d1d5db);
   border-radius: 4px;
   transition: background 0.15s;
}
.bfaq-pagination a:hover {
   background: var(--bs-secondary-bg, #f3f4f6);
}
.bfaq-pagination span {
   color: var(--bs-body-color, #6b7280);
}
</style>

<div class="bfaq-layout">

   <!-- Sidebar tree -->
   <nav class="bfaq-sidebar">
      <div class="bfaq-sidebar-inner">
         <?php echo plugin_betterfaq_render_tree($full_tree, $active_ids, $base_url, $id); ?>
      </div>
   </nav>

   <!-- Main content -->
   <div class="bfaq-main">

      <!-- Search form -->
      <form class="bfaq-search-form" method="GET" action="<?php echo htmlspecialchars($base_url . '/front/category.php', ENT_QUOTES, 'UTF-8'); ?>">
         <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
         <input type="text" name="q" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
         <button type="submit"><i class="ti ti-search"></i> <?php echo __('Search', 'betterfaq'); ?></button>
      </form>

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-4">
         <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo __('FAQ', 'betterfaq'); ?></a></li>
            <?php foreach ($breadcrumb as $idx => $bc): ?>
               <?php $is_last = ($idx === $breadcrumb_count - 1); ?>
               <?php if ($is_last && $article_id <= 0): ?>
                  <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($bc['name'], ENT_QUOTES, 'UTF-8'); ?></li>
               <?php else: ?>
                  <li class="breadcrumb-item"><?php echo htmlspecialchars($bc['name'], ENT_QUOTES, 'UTF-8'); ?></li>
               <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($article_id > 0 && $article): ?>
               <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endif; ?>
         </ol>
      </nav>

      <!-- Article view or article list -->
      <?php if ($article_id > 0 && $article): ?>

         <div class="bfaq-article-container">
         <div class="bfaq-article-header">
            <h1><?php echo htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="bfaq-article-meta">
               <?php if (!empty($article['date_mod'])): ?>
                  <span><i class="ti ti-clock"></i> <?php echo __('Updated', 'betterfaq'); ?>: <?php echo htmlspecialchars($article['date_mod'], ENT_QUOTES, 'UTF-8'); ?></span>
               <?php endif; ?>
               <?php if (isset($article['view'])): ?>
                  <span><i class="ti ti-eye"></i> <?php echo sprintf(__('%d views', 'betterfaq'), (int) $article['view']); ?></span>
               <?php endif; ?>
            </div>
         </div>

         <div class="bfaq-article-body">
            <?php echo $article['content']; ?>
         </div>

         <?php
         $top_articles = PluginBetterfaqCategory::getTopArticlesByCategory($id, $article_id, 3);
         if (!empty($top_articles)):
         ?>
         <div class="bfaq-related-articles">
            <h3><?php echo __('More in this category', 'betterfaq'); ?></h3>
            <div class="bfaq-article-list">
               <?php foreach ($top_articles as $art): ?>
                  <div class="bfaq-article-row">
                     <a href="<?php echo htmlspecialchars($base_url . '/front/category.php?id=' . $id . '&article_id=' . (int) $art['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($art['name'], ENT_QUOTES, 'UTF-8'); ?>
                     </a>
                     <span class="bfaq-article-chevron">&#x203A;</span>
                  </div>
               <?php endforeach; ?>
            </div>
         </div>
         <?php endif; ?>

         <div class="bfaq-bottom-nav">
            <a href="<?php echo htmlspecialchars($base_url . '/front/category.php?id=' . $id, ENT_QUOTES, 'UTF-8'); ?>">
               <i class="ti ti-arrow-left"></i> <?php echo __('Back', 'betterfaq'); ?>
            </a>
            <a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
               &#x2191; <?php echo __('Top', 'betterfaq'); ?>
            </a>
         </div>

         </div>

      <?php else: ?>

         <div class="bfaq-article-container">
         <!-- Category title -->
         <?php if (!empty($search_query)): ?>
            <h1 class="bfaq-category-title"><?php echo htmlspecialchars(sprintf(__('Search Results for "%s"', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?></h1>
         <?php else: ?>
            <h1 class="bfaq-category-title"><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if (!empty($category['comment'])): ?>
               <p class="text-muted mb-3"><?php echo htmlspecialchars($category['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
         <?php endif; ?>

         <!-- Article list -->
         <?php if (count($articles) > 0): ?>
            <div class="bfaq-category-list-wrapper">
               <div class="bfaq-article-list">
                  <?php foreach ($articles as $art): ?>
                     <div class="bfaq-article-row">
                        <a href="<?php echo htmlspecialchars($base_url . '/front/category.php?id=' . $id . '&article_id=' . (int) $art['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($art['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <span class="bfaq-article-chevron">&#x203A;</span>
                     </div>
                  <?php endforeach; ?>
               </div>
               <?php
               $page_base_url = $base_url . '/front/category.php?id=' . $id;
               if (!empty($search_query)) {
                  $page_base_url .= '&q=' . urlencode($search_query);
               }
               ?>
               <?php if ($total_pages > 1): ?>
                  <div class="bfaq-pagination">
                     <?php if ($page > 1): ?>
                        <a href="<?php echo htmlspecialchars($page_base_url . '&page=' . ($page - 1), ENT_QUOTES, 'UTF-8'); ?>">
                           &lsaquo; <?php echo __('Previous', 'betterfaq'); ?>
                        </a>
                     <?php endif; ?>
                     <span><?php echo $page; ?> / <?php echo $total_pages; ?></span>
                     <?php if ($page < $total_pages): ?>
                        <a href="<?php echo htmlspecialchars($page_base_url . '&page=' . ($page + 1), ENT_QUOTES, 'UTF-8'); ?>">
                           <?php echo __('Next', 'betterfaq'); ?> &rsaquo;
                        </a>
                     <?php endif; ?>
                  </div>
               <?php endif; ?>
            </div>
         <?php else: ?>
            <div class="text-center py-5 text-muted">
               <p>
                  <?php if (!empty($search_query)): ?>
                     <?php echo htmlspecialchars(sprintf(__('No results found for "%s".', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?>
                  <?php else: ?>
                     <?php echo __('No articles in this category.', 'betterfaq'); ?>
                  <?php endif; ?>
               </p>
            </div>
         <?php endif; ?>

         <!-- Bottom navigation -->
         <div class="bfaq-bottom-nav">
            <a href="<?php echo $back_url; ?>">&#x2039; <?php echo __('Back', 'betterfaq'); ?></a>
            <a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">&#x2191; <?php echo __('Top', 'betterfaq'); ?></a>
         </div>
         </div>

      <?php endif; ?>

   </div>

</div>

<?php if ($article_id > 0): ?>
<div class="bfaq-need-help">
   <div class="bfaq-need-help-spacer"></div>
   <div class="bfaq-need-help-content">
      <h3><?php echo __('Need more help?', 'betterfaq'); ?></h3>
      <a href="<?php echo htmlspecialchars($CFG_GLPI['root_doc'] . '/ServiceCatalog', ENT_QUOTES, 'UTF-8'); ?>">
         <?php echo __('Visit Service Catalog', 'betterfaq'); ?>
      </a>
   </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.bfaq-sidebar button').forEach(function(button) {
   button.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();

      var ul = this.parentElement.querySelector(':scope > ul');
      if (ul) {
         var isHidden = ul.style.display === 'none';
         ul.style.display = isHidden ? '' : 'none';
         var toggle = this.querySelector('.bfaq-tree-toggle');
         if (toggle) {
            toggle.classList.toggle('expanded', isHidden);
         }
         this.setAttribute('aria-expanded', isHidden);
      }
   });
});
</script>

<?php

function plugin_betterfaq_render_tree($tree, $active_ids, $base_url, $current_id, $depth = 0) {
   if (empty($tree)) {
      return '';
   }
   global $config_map;
   $html = '<ul>';
   foreach ($tree as $node) {
      $html .= plugin_betterfaq_render_node($node, $active_ids, $base_url, $current_id, $depth, $config_map);
   }
   $html .= '</ul>';
   return $html;
}

function plugin_betterfaq_render_node($node, $active_ids, $base_url, $current_id, $depth = 0, $config_map = []) {
   global $CFG_GLPI;

   $node_id      = (int) $node['id'];
   $has_children = !empty($node['children']);
   $is_active    = $node_id === $current_id;
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
      $is_expanded = $in_path ? 'true' : 'false';
      $toggle_class = $in_path ? ' expanded' : '';
      $html .= '<button type="button" aria-expanded="' . $is_expanded . '" aria-pressed="false">'
         . '<span class="bfaq-button-text">' . htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8') . '</span>'
         . '<span class="bfaq-tree-toggle' . $toggle_class . '"><i class="ti ti-chevron-down"></i></span>'
         . '</button>';

      $display = $in_path ? '' : ' style="display:none"';
      $html .= '<ul' . $display . '>';
      foreach ($node['children'] as $child) {
         $html .= plugin_betterfaq_render_node($child, $active_ids, $base_url, $current_id, $depth + 1, $config_map);
      }
      $html .= '</ul>';
   } else {
      $html .= '<a href="' . htmlspecialchars($base_url . '/front/category.php?id=' . $node_id, ENT_QUOTES, 'UTF-8') . '">'
         . '<span class="bfaq-button-text">' . htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8') . '</span>'
         . '</a>';
   }

   $html .= '</li>';
   return $html;
}

Html::footer();
