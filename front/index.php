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

global $CFG_GLPI;
$base_url = $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR;

// --- URL parameters ---
$id           = isset($_GET['id'])         ? (int) $_GET['id']   : 0;
$article_id   = isset($_GET['article_id']) ? (int) $_GET['article_id'] : 0;
$search_query = isset($_GET['q'])          ? trim($_GET['q'])     : '';
$is_search    = !empty($search_query);

// --- Determine mode ---
if ($id > 0 && $article_id > 0) {
   $mode = 'article';
} elseif ($id > 0) {
   $mode = 'category';
} else {
   $mode = 'home';
}

// --- Always load ---
$config_map = PluginBetterfaqConfig::getCategoryConfigMap();
$full_tree  = PluginBetterfaqCategory::buildFullTree();

// --- Mode-specific data ---
$category         = null;
$breadcrumb       = [];
$breadcrumb_count = 0;
$articles         = [];
$article          = null;
$active_ids       = [];
$total_pages      = 1;
$page             = 1;
$back_url         = htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8');

if ($mode === 'home') {
   $hero_title    = PluginBetterfaqConfig::getGlobalConfig('hero_title')    ?: __('FAQ', 'betterfaq');
   $hero_subtitle = PluginBetterfaqConfig::getGlobalConfig('hero_subtitle') ?: __('How can we help you?', 'betterfaq');
   $categories    = PluginBetterfaqCategory::getRootCategoriesWithConfig();

   if ($is_search) {
      $featured_articles = PluginBetterfaqCategory::searchArticles($search_query, 200);
   } else {
      $filter = isset($_GET['filter']) ? $_GET['filter'] : 'viewed';
      if (!in_array($filter, ['viewed', 'newest'])) {
         $filter = 'viewed';
      }
      if ($filter === 'newest') {
         $featured_articles = PluginBetterfaqCategory::getNewestArticles(10);
      } else {
         $featured_articles = PluginBetterfaqCategory::getMostViewedArticles(10);
      }
   }
} else {
   // category or article mode
   $category = PluginBetterfaqCategory::getCategoryById($id);
   if (!$category) {
      Html::displayNotFoundError();
      exit;
   }

   $breadcrumb       = PluginBetterfaqCategory::getBreadcrumbChain($id);
   $breadcrumb_count = count($breadcrumb);
   $articles         = PluginBetterfaqCategory::getArticlesByCategory($id);

   if ($is_search) {
      $articles = PluginBetterfaqCategory::searchArticles($search_query, 200);
   }

   // Pagination (category mode)
   if ($mode === 'category') {
      $per_page       = 10;
      $total_articles = count($articles);
      $total_pages    = max(1, (int) ceil($total_articles / $per_page));
      $page           = isset($_GET['page']) ? max(1, min((int) $_GET['page'], $total_pages)) : 1;
      $articles       = array_slice($articles, ($page - 1) * $per_page, $per_page);
   }

   // Active path IDs for sidebar
   foreach ($breadcrumb as $bc) {
      $active_ids[] = (int) $bc['id'];
   }

   // Back URL
   if ($breadcrumb_count >= 2) {
      $parent   = $breadcrumb[$breadcrumb_count - 2];
      $back_url = htmlspecialchars($base_url . '/front/index.php?id=' . (int) $parent['id'], ENT_QUOTES, 'UTF-8');
   }

   // Article mode
   if ($mode === 'article') {
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
}

$interface = Session::getCurrentInterface();
if ($interface === 'helpdesk') {
   Html::helpHeader(__('FAQ', 'betterfaq'));
} else {
   Html::header(__('FAQ', 'betterfaq'), $_SERVER['PHP_SELF'], 'tools');
}

?>
<style>
/* ===== Layout ===== */
.bfaq-layout {
   display: flex;
   gap: 30px;
   max-width: 1400px;
   margin: 20px auto;
   padding: 0 20px;
}

/* ===== Sidebar ===== */
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

/* ===== Main content area ===== */
.bfaq-main {
   flex: 1;
   min-width: 0;
}

/* ===== Hero (home mode) ===== */
.bfaq-hero {
   margin-bottom: 30px;
}

.bfaq-hero h1 {
   font-size: 1.8em;
   margin: 0 0 24px 0;
   font-weight: 700;
   color: var(--bs-body-color, #222);
}

.bfaq-hero .bfaq-search-form {
   max-width: 600px;
   margin: 0;
}

.bfaq-hero .bfaq-search-form input[type="text"] {
   color: var(--bs-body-color, #333);
}

/* ===== Search form ===== */
.bfaq-search-form {
   display: flex;
   gap: 0;
   border: 1px solid #ddd;
   border-radius: 6px;
   overflow: hidden;
   background: #fff;
   margin-bottom: 20px;
}

.bfaq-search-form input[type="text"] {
   flex: 1;
   padding: 12px 18px;
   border: none;
   font-size: 1em;
   outline: none;
   background: transparent;
   color: var(--bs-body-color, #333);
}

.bfaq-search-form button {
   padding: 12px 20px;
   border: none;
   background: var(--glpi-mainmenu-bg, var(--bs-primary));
   color: var(--glpi-mainmenu-fg, #fff);
   font-size: 1em;
   cursor: pointer;
   transition: opacity 0.2s;
}

.bfaq-search-form button:hover {
   opacity: 0.85;
}

/* ===== Home: Popular Topics grid ===== */
.bfaq-section-heading {
   font-size: 1.2em;
   font-weight: 700;
   color: #333;
   margin: 0 0 16px 0;
}

.bfaq-grid {
   display: grid;
   grid-template-columns: repeat(3, 1fr);
   gap: 16px;
   margin-bottom: 40px;
}

.bfaq-card {
   background: #fff;
   padding: 24px 18px;
   border-radius: 8px;
   box-shadow: 0 1px 3px rgba(0,0,0,0.08);
   transition: transform 0.2s, box-shadow 0.2s;
   text-decoration: none;
   color: inherit;
   display: block;
   border: 1px solid #e5e5e5;
   text-align: center;
}

.bfaq-card:hover {
   transform: translateY(-3px);
   box-shadow: 0 4px 12px rgba(0,0,0,0.12);
   text-decoration: none;
   color: inherit;
}

.bfaq-card-icon {
   font-size: 2.8em;
   margin-bottom: 12px;
   display: block;
}

.bfaq-card h3 {
   margin: 0 0 6px 0;
   font-size: 1.05em;
   font-weight: 700;
   color: #222;
}

.bfaq-card-desc {
   color: #777;
   font-size: 0.88em;
   margin: 0;
   line-height: 1.4;
}

/* ===== Home: Popular Articles list ===== */
.bfaq-featured-section {
   margin-bottom: 30px;
}

.bfaq-featured-header {
   display: flex;
   align-items: center;
   justify-content: space-between;
   margin-bottom: 16px;
   gap: 12px;
   flex-wrap: wrap;
}

.bfaq-filter-group {
   display: flex;
   align-items: center;
   gap: 8px;
}

.bfaq-filter-label {
   font-weight: 600;
   color: #666;
   font-size: 0.92em;
   white-space: nowrap;
}

.bfaq-filter-select {
   padding: 6px 10px;
   border: 1px solid #ddd;
   border-radius: 4px;
   background: #fff;
   font-size: 0.92em;
   cursor: pointer;
   color: #333;
}

.bfaq-featured-list {
   border: 1px solid #e5e5e5;
   border-radius: 6px;
   overflow: hidden;
}

.bfaq-featured-item {
   background: #fff;
   padding: 14px 18px;
   text-decoration: none;
   color: inherit;
   display: flex;
   align-items: center;
   justify-content: space-between;
   gap: 16px;
   border-bottom: 1px solid #f0f0f0;
   transition: background-color 0.2s;
}

.bfaq-featured-item:last-child {
   border-bottom: none;
}

.bfaq-featured-item:hover {
   background: #f8f8f8;
   text-decoration: none;
}

.bfaq-featured-item-content {
   flex: 1;
   min-width: 0;
}

.bfaq-featured-item h4 {
   margin: 0;
   font-size: 0.95em;
   color: #333;
   line-height: 1.4;
   font-weight: 400;
}

.bfaq-featured-icon {
   flex-shrink: 0;
   color: #aaa;
}

/* ===== Category / Article mode ===== */
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

/* ===== Article view ===== */
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

/* ===== Need more help (outside layout) ===== */
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
   padding: 30px;
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

/* ===== Breadcrumb ===== */
.breadcrumb-item + .breadcrumb-item::before {
   content: ">" !important;
}

/* ===== Pagination ===== */
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

/* ===== Responsive ===== */
@media (max-width: 768px) {
   .bfaq-layout {
      flex-direction: column;
   }
   .bfaq-sidebar {
      width: 100%;
      position: static;
      max-height: none;
   }
   .bfaq-need-help {
      grid-template-columns: 1fr;
   }
   .bfaq-need-help-spacer {
      display: none;
   }
   .bfaq-grid {
      grid-template-columns: repeat(2, 1fr);
   }
}

@media (max-width: 480px) {
   .bfaq-grid {
      grid-template-columns: 1fr;
   }
}
</style>

<div class="bfaq-layout">

   <!-- Sidebar tree -->
   <nav class="bfaq-sidebar">
      <div class="bfaq-sidebar-inner">
         <?php echo PluginBetterfaqCategory::renderTree($full_tree, $active_ids, $base_url, $id, $config_map); ?>
      </div>
   </nav>

   <!-- Main content -->
   <div class="bfaq-main">

   <?php if ($mode === 'home'): ?>

      <!-- Hero -->
      <div class="bfaq-hero">
         <h1><?php echo htmlspecialchars($hero_subtitle, ENT_QUOTES, 'UTF-8'); ?></h1>
         <form class="bfaq-search-form" method="GET" action="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" name="q" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit"><i class="ti ti-search"></i> <?php echo __('Search', 'betterfaq'); ?></button>
         </form>
      </div>

      <!-- Popular Topics -->
      <?php if (!$is_search && count($categories) > 0): ?>
      <h2 class="bfaq-section-heading"><?php echo __('Popular Topics', 'betterfaq'); ?></h2>
      <div class="bfaq-grid">
         <?php foreach ($categories as $cat):
            $cat_id    = (int) $cat['id'];
            $cat_name  = htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8');
            $cat_url   = htmlspecialchars($base_url . '/front/index.php?id=' . $cat_id, ENT_QUOTES, 'UTF-8');
            $cat_icon  = $cat['icon'];
            $image_url = $base_url . '/ajax/get_image.php?f=';
            $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $ext = strtolower(pathinfo($cat_icon, PATHINFO_EXTENSION));
            if (!empty($cat_icon) && in_array($ext, $allowed_img_exts, true)) {
               $icon_html = '<img src="' . htmlspecialchars($image_url . urlencode($cat_icon), ENT_QUOTES, 'UTF-8')
                          . '" alt="" style="width:56px; height:56px; object-fit:contain;">';
            } else {
               $icon_html = '<i class="ti ti-folder"></i>';
            }
         ?>
         <a href="<?php echo $cat_url; ?>" class="bfaq-card">
            <span class="bfaq-card-icon"><?php echo $icon_html; ?></span>
            <h3><?php echo $cat_name; ?></h3>
            <?php if (!empty($cat['comment'])): ?>
               <p class="bfaq-card-desc"><?php echo htmlspecialchars($cat['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
         </a>
         <?php endforeach; ?>
      </div>
      <?php elseif (!$is_search): ?>
      <div style="text-align:center; padding:40px; color:#999;">
         <p style="font-size:1.1em;"><?php echo __('No FAQ categories available.', 'betterfaq'); ?></p>
      </div>
      <?php endif; ?>

      <!-- Popular Articles / Search Results -->
      <div class="bfaq-featured-section">
         <div class="bfaq-featured-header">
            <h2 class="bfaq-section-heading" style="margin:0;">
               <?php if ($is_search): ?>
                  <?php echo htmlspecialchars(sprintf(__('Search Results for "%s"', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($featured_articles); ?>)
               <?php else: ?>
                  <?php echo ($filter === 'newest') ? __('Newest Articles', 'betterfaq') : __('Popular Articles', 'betterfaq'); ?>
               <?php endif; ?>
            </h2>
            <?php if (!$is_search): ?>
            <div class="bfaq-filter-group">
               <label class="bfaq-filter-label" for="bfaq-filter"><?php echo __('Sort by:', 'betterfaq'); ?></label>
               <select id="bfaq-filter" class="bfaq-filter-select" onchange="location.href='<?php echo htmlspecialchars($base_url . '/front/index.php?filter=', ENT_QUOTES, 'UTF-8'); ?>' + this.value;">
                  <option value="viewed" <?php echo ($filter === 'viewed') ? 'selected' : ''; ?>><?php echo __('Most Viewed', 'betterfaq'); ?></option>
                  <option value="newest" <?php echo ($filter === 'newest') ? 'selected' : ''; ?>><?php echo __('Newest', 'betterfaq'); ?></option>
               </select>
            </div>
            <?php endif; ?>
         </div>

         <?php if (count($featured_articles) > 0): ?>
         <div class="bfaq-featured-list">
            <?php foreach ($featured_articles as $art):
               $art_id  = (int) $art['id'];
               $art_name = htmlspecialchars($art['name'], ENT_QUOTES, 'UTF-8');
               $cat_id  = PluginBetterfaqCategory::getArticleCategoryId($art_id);
               if ($cat_id > 0) {
                  $art_url = htmlspecialchars($base_url . '/front/index.php?id=' . $cat_id . '&article_id=' . $art_id, ENT_QUOTES, 'UTF-8');
               } else {
                  $art_url = htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8');
               }
            ?>
            <a href="<?php echo $art_url; ?>" class="bfaq-featured-item">
               <div class="bfaq-featured-item-content"><h4><?php echo $art_name; ?></h4></div>
               <div class="bfaq-featured-icon"><i class="ti ti-chevron-right"></i></div>
            </a>
            <?php endforeach; ?>
         </div>
         <?php elseif ($is_search): ?>
         <div style="text-align:center; padding:40px; color:#999;">
            <p style="font-size:1.05em;"><?php echo htmlspecialchars(sprintf(__('No results found for "%s".', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?></p>
         </div>
         <?php endif; ?>
      </div>

   <?php elseif ($mode === 'category'): ?>

      <!-- Search form -->
      <form class="bfaq-search-form" method="GET" action="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>">
         <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
         <input type="text" name="q" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
         <button type="submit"><i class="ti ti-search"></i> <?php echo __('Search', 'betterfaq'); ?></button>
      </form>

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-4">
         <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo __('FAQ', 'betterfaq'); ?></a></li>
            <?php foreach ($breadcrumb as $idx => $bc):
               $is_last = ($idx === $breadcrumb_count - 1);
            ?>
               <?php if ($is_last): ?>
                  <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($bc['name'], ENT_QUOTES, 'UTF-8'); ?></li>
               <?php else: ?>
                  <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . '/front/index.php?id=' . (int) $bc['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bc['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
               <?php endif; ?>
            <?php endforeach; ?>
         </ol>
      </nav>

      <div class="bfaq-article-container">
         <?php if (!empty($search_query)): ?>
            <h1 class="bfaq-category-title"><?php echo htmlspecialchars(sprintf(__('Search Results for "%s"', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?></h1>
         <?php else: ?>
            <h1 class="bfaq-category-title"><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if (!empty($category['comment'])): ?>
               <p class="text-muted" style="padding:0 20px;"><?php echo htmlspecialchars($category['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
         <?php endif; ?>

         <?php if (count($articles) > 0): ?>
            <div class="bfaq-category-list-wrapper">
               <div class="bfaq-article-list">
                  <?php foreach ($articles as $art): ?>
                     <div class="bfaq-article-row">
                        <a href="<?php echo htmlspecialchars($base_url . '/front/index.php?id=' . $id . '&article_id=' . (int) $art['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($art['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        <span class="bfaq-article-chevron">&#x203A;</span>
                     </div>
                  <?php endforeach; ?>
               </div>
               <?php if ($total_pages > 1):
                  $page_base_url = $base_url . '/front/index.php?id=' . $id;
                  if (!empty($search_query)) {
                     $page_base_url .= '&q=' . urlencode($search_query);
                  }
               ?>
               <div class="bfaq-pagination">
                  <?php if ($page > 1): ?>
                     <a href="<?php echo htmlspecialchars($page_base_url . '&page=' . ($page - 1), ENT_QUOTES, 'UTF-8'); ?>">&lsaquo; <?php echo __('Previous', 'betterfaq'); ?></a>
                  <?php endif; ?>
                  <span><?php echo $page; ?> / <?php echo $total_pages; ?></span>
                  <?php if ($page < $total_pages): ?>
                     <a href="<?php echo htmlspecialchars($page_base_url . '&page=' . ($page + 1), ENT_QUOTES, 'UTF-8'); ?>"><?php echo __('Next', 'betterfaq'); ?> &rsaquo;</a>
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

         <div class="bfaq-bottom-nav">
            <a href="<?php echo $back_url; ?>">&#x2039; <?php echo __('Back', 'betterfaq'); ?></a>
            <a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">&#x2191; <?php echo __('Top', 'betterfaq'); ?></a>
         </div>
      </div>

   <?php elseif ($mode === 'article'): ?>

      <!-- Search form -->
      <form class="bfaq-search-form" method="GET" action="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>">
         <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
         <input type="text" name="q" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
         <button type="submit"><i class="ti ti-search"></i> <?php echo __('Search', 'betterfaq'); ?></button>
      </form>

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb" class="mb-4">
         <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo __('FAQ', 'betterfaq'); ?></a></li>
            <?php foreach ($breadcrumb as $idx => $bc): ?>
               <li class="breadcrumb-item"><a href="<?php echo htmlspecialchars($base_url . '/front/index.php?id=' . (int) $bc['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bc['name'], ENT_QUOTES, 'UTF-8'); ?></a></li>
            <?php endforeach; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8'); ?></li>
         </ol>
      </nav>

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
                  <a href="<?php echo htmlspecialchars($base_url . '/front/index.php?id=' . $id . '&article_id=' . (int) $art['id'], ENT_QUOTES, 'UTF-8'); ?>">
                     <?php echo htmlspecialchars($art['name'], ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <span class="bfaq-article-chevron">&#x203A;</span>
               </div>
               <?php endforeach; ?>
            </div>
         </div>
         <?php endif; ?>

         <div class="bfaq-bottom-nav">
            <a href="<?php echo htmlspecialchars($base_url . '/front/index.php?id=' . $id, ENT_QUOTES, 'UTF-8'); ?>">
               <i class="ti ti-arrow-left"></i> <?php echo __('Back', 'betterfaq'); ?>
            </a>
            <a href="#" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">
               &#x2191; <?php echo __('Top', 'betterfaq'); ?>
            </a>
         </div>
      </div>

   <?php endif; ?>

   </div><!-- .bfaq-main -->

</div><!-- .bfaq-layout -->

<?php if ($mode === 'article'): ?>
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
Html::footer();
