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

$interface = Session::getCurrentInterface();
if ($interface === 'helpdesk') {
   Html::helpHeader(__('FAQ', 'betterfaq'));
} else {
   Html::header(__('FAQ', 'betterfaq'), $_SERVER['PHP_SELF'], 'tools');
}

// Load global config
$hero_title    = PluginBetterfaqConfig::getGlobalConfig('hero_title') ?: 'Foire Aux Questions';
$hero_subtitle = PluginBetterfaqConfig::getGlobalConfig('hero_subtitle') ?: 'Comment pouvons-nous vous aider ?';

// Load categories with config
$categories = PluginBetterfaqCategory::getRootCategoriesWithConfig();

// Build subcategory map: [cat_id => [children array]]
$subcategories_map = [];
foreach ($categories as $cat) {
   $subcategories_map[(int) $cat['id']] = PluginBetterfaqCategory::getChildCategoriesWithConfig((int) $cat['id']);
}

// Handle search query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$is_search = !empty($search_query);

if ($is_search) {
   // If searching, show search results
   $featured_articles = PluginBetterfaqCategory::searchArticles($search_query, 200);
} else {
   // Get filter preference from query parameter
   $filter = isset($_GET['filter']) ? $_GET['filter'] : 'viewed';
   if (!in_array($filter, ['viewed', 'newest'])) {
      $filter = 'viewed';
   }

   // Load articles based on filter
   if ($filter === 'newest') {
      $featured_articles = PluginBetterfaqCategory::getNewestArticles(10);
   } else {
      $featured_articles = PluginBetterfaqCategory::getMostViewedArticles(10);
   }
}

?>
<style>
.bfaq-search-wrap {
   max-width: 700px;
   margin: 0 auto 30px auto;
}
.bfaq-search-form {
   display: flex;
   gap: 0;
   border: 1px solid #ddd;
   border-radius: 6px;
   overflow: hidden;
   background: #fff;
}
.bfaq-search-form input[type="text"] {
   flex: 1;
   padding: 12px 18px;
   border: none;
   font-size: 1em;
   outline: none;
   background: transparent;
}
.bfaq-search-form button {
   padding: 12px 20px;
   border: none;
   background: var(--glpi-mainmenu-bg,var(--bs-primary));
   color: var(--glpi-mainmenu-fg,#fff);
   font-size: 1em;
   cursor: pointer;
   transition: opacity 0.2s;
}
.bfaq-search-form button:hover {
   opacity: 0.85;
}
.bfaq-grid {
   display: grid;
   grid-template-columns: repeat(3, 1fr);
   gap: 24px;
   max-width: 1200px;
   margin: 0 auto;
   padding: 0 20px;
}
.bfaq-card {
   background: #fff;
   padding: 30px 24px;
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
   transform: translateY(-4px);
   box-shadow: 0 4px 12px rgba(0,0,0,0.12);
   text-decoration: none;
   color: inherit;
}
.bfaq-card-icon {
   font-size: 3.5em;
   margin-bottom: 18px;
   display: block;
}
.bfaq-card h3 {
   margin: 0 0 8px 0;
   font-size: 1.15em;
   font-weight: 700;
   color: #222;
}
.bfaq-card-desc {
   color: #777;
   font-size: 0.9em;
   margin: 0;
   line-height: 1.4;
}
.bfaq-featured-section {
   max-width: 1200px;
   margin: 50px auto;
   padding: 0 20px;
}
.bfaq-featured-header {
   display: flex;
   align-items: center;
   justify-content: center;
   margin-bottom: 25px;
   gap: 20px;
   flex-wrap: wrap;
   flex-direction: column;
}
.bfaq-featured-title {
   font-size: 1.4em;
   font-weight: 700;
   color: #333;
   margin: 0;
   text-align: center;
}
.bfaq-filter-group {
   display: flex;
   align-items: center;
   gap: 10px;
}
.bfaq-filter-label {
   font-weight: 600;
   color: #666;
   font-size: 0.95em;
   white-space: nowrap;
}
.bfaq-filter-select {
   padding: 8px 12px;
   border: 1px solid #ddd;
   border-radius: 4px;
   background: #fff;
   font-size: 0.95em;
   cursor: pointer;
   color: #333;
   transition: border-color 0.2s;
}
.bfaq-filter-select:hover {
   border-color: var(--glpi-mainmenu-bg,var(--bs-primary));
}
.bfaq-featured-list {
   border: 1px solid #e5e5e5;
   border-radius: 6px;
   overflow: hidden;
}
.bfaq-featured-item {
   background: #fff;
   padding: 16px 20px;
   text-decoration: none;
   color: inherit;
   display: flex;
   align-items: center;
   justify-content: space-between;
   gap: 20px;
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
   font-size: 1em;
   color: #333;
   line-height: 1.4;
   font-weight: 400;
}
.bfaq-featured-icon {
   flex-shrink: 0;
   width: 24px;
   height: 24px;
   display: flex;
   align-items: center;
   justify-content: center;
   color: #aaa;
}
/* Active card state (has subcategories, currently selected) */
.bfaq-card--active {
    border-color: var(--glpi-mainmenu-bg,var(--bs-primary));
    box-shadow: 0 0 0 2px var(--glpi-mainmenu-bg,var(--bs-primary));
}
.bfaq-card--active h3 {
    color: var(--glpi-mainmenu-bg,var(--bs-primary));
}

/* Subcategory panel */
.bfaq-subcat-panel {
    display: none;
    max-width: 1200px;
    margin: 24px auto 0 auto;
    padding: 0 20px;
    animation: bfaq-fade-in 0.2s ease;
}
.bfaq-subcat-panel.is-visible {
    display: block;
}
@keyframes bfaq-fade-in {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.bfaq-subcat-title {
    font-size: 1.2em;
    font-weight: 700;
    color: #333;
    text-align: center;
    margin: 0 0 16px 0;
}
.bfaq-subcat-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}
.bfaq-subcat-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 18px 12px;
    text-align: center;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.15s, box-shadow 0.15s;
}
.bfaq-subcat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.10);
    text-decoration: none;
    color: inherit;
}
.bfaq-subcat-card-icon {
    font-size: 2.2em;
    display: block;
    margin-bottom: 10px;
}
.bfaq-subcat-card h4 {
    margin: 0;
    font-size: 0.9em;
    font-weight: 600;
    color: #222;
}
@media (max-width: 900px) {
    .bfaq-subcat-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 600px) {
    .bfaq-subcat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
   .bfaq-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
   }
}
@media (max-width: 480px) {
   .bfaq-grid {
      grid-template-columns: 1fr;
      gap: 14px;
   }
   .bfaq-card {
      padding: 24px;
   }
   .bfaq-card-icon {
      font-size: 2.5em;
      margin-bottom: 14px;
   }
   .bfaq-featured-icon {
      display: none;
   }
}
</style>

<div style="max-width:1200px; margin:20px auto; padding:0 20px;">

<div class="bfaq-search-wrap">
   <form class="bfaq-search-form" method="GET" action="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>">
      <input type="text" name="q" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit"><i class="ti ti-search"></i> <?php echo __('Search', 'betterfaq'); ?></button>
   </form>
</div>

<?php if (count($categories) === 0): ?>
<div style="text-align:center; padding:40px; color:#999;">
   <p style="font-size:1.2em;"><?php echo __('No FAQ categories available.', 'betterfaq'); ?></p>
</div>
<?php else: ?>
<div style="max-width:1200px; margin:0 auto; padding:0 20px; margin-bottom:20px; text-align:center;">
   <h2 style="font-size:1.5em; font-weight:700; color:#333; margin:0;"><?php echo __('Choose a category', 'betterfaq'); ?></h2>
</div>
<div class="bfaq-grid">
<?php foreach ($categories as $cat):
   $cat_id    = (int) $cat['id'];
   $cat_name  = htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8');
   $cat_color = htmlspecialchars($cat['color'], ENT_QUOTES, 'UTF-8');
   $cat_icon  = $cat['icon'];
   $cat_url   = htmlspecialchars($base_url . '/front/category.php?id=' . $cat_id, ENT_QUOTES, 'UTF-8');

   // Determine icon display: image file upload takes precedence, fallback to folder icon
   $image_url = $base_url . '/ajax/get_image.php?f=';
   $allowed_img_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
   $ext = strtolower(pathinfo($cat_icon, PATHINFO_EXTENSION));

   if (!empty($cat_icon) && in_array($ext, $allowed_img_exts, true)) {
      $icon_html = '<img src="' . htmlspecialchars($image_url . urlencode($cat_icon), ENT_QUOTES, 'UTF-8')
                 . '" alt="" style="width:64px; height:64px; object-fit:contain;">';
   } else {
      $icon_html = '<i class="ti ti-folder"></i>';
   }
   $has_children = !empty($subcategories_map[$cat_id]);
?>
   <?php if ($has_children): ?>
   <div class="bfaq-card" role="button" tabindex="0" data-cat-id="<?php echo $cat_id; ?>">
      <span class="bfaq-card-icon"><?php echo $icon_html; ?></span>
      <h3><?php echo $cat_name; ?></h3>
      <?php if (!empty($cat['comment'])): ?>
         <p class="bfaq-card-desc"><?php echo htmlspecialchars($cat['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
   </div>
   <?php else: ?>
   <a href="<?php echo $cat_url; ?>" class="bfaq-card">
      <span class="bfaq-card-icon"><?php echo $icon_html; ?></span>
      <h3><?php echo $cat_name; ?></h3>
      <?php if (!empty($cat['comment'])): ?>
         <p class="bfaq-card-desc"><?php echo htmlspecialchars($cat['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
   </a>
   <?php endif; ?>
<?php endforeach; ?>
</div>

<!-- Subcategory panel (shown when a parent card is clicked) -->
<div class="bfaq-subcat-panel" id="bfaq-subcat-panel" aria-live="polite">
    <h2 class="bfaq-subcat-title"><?php echo __('Select a subcategory', 'betterfaq'); ?></h2>
    <?php foreach ($categories as $cat):
        $cat_id   = (int) $cat['id'];
        $children = $subcategories_map[$cat_id];
        if (empty($children)) continue;
    ?>
    <div class="bfaq-subcat-grid" id="bfaq-subcat-<?php echo $cat_id; ?>" style="display:none;">
        <?php foreach ($children as $child):
            $child_id   = (int) $child['id'];
            $child_name = htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8');
            $child_url  = htmlspecialchars($base_url . '/front/category.php?id=' . $child_id, ENT_QUOTES, 'UTF-8');
            $child_icon = $child['icon'];
            $ext        = strtolower(pathinfo($child_icon, PATHINFO_EXTENSION));
            if (!empty($child_icon) && in_array($ext, $allowed_img_exts, true)) {
                $child_icon_html = '<img src="' . htmlspecialchars($image_url . urlencode($child_icon), ENT_QUOTES, 'UTF-8') . '" alt="" style="width:48px;height:48px;object-fit:contain;">';
            } else {
                $child_icon_html = '<i class="ti ti-folder"></i>';
            }
        ?>
        <a href="<?php echo $child_url; ?>" class="bfaq-subcat-card">
            <span class="bfaq-subcat-card-icon"><?php echo $child_icon_html; ?></span>
            <h4><?php echo $child_name; ?></h4>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var panel      = document.getElementById('bfaq-subcat-panel');
    var activeCard = null;
    var activeGrid = null;

    document.querySelectorAll('.bfaq-card[data-cat-id]').forEach(function (card) {
        var catId = card.getAttribute('data-cat-id');
        var grid  = document.getElementById('bfaq-subcat-' + catId);
        if (!grid) return;

        card.style.cursor = 'pointer';

        function handleActivate() {
            var isSame = (activeCard === card);

            if (activeCard) {
                activeCard.classList.remove('bfaq-card--active');
            }
            if (activeGrid) {
                activeGrid.style.display = 'none';
            }

            if (isSame) {
                panel.classList.remove('is-visible');
                activeCard = null;
                activeGrid = null;
            } else {
                card.classList.add('bfaq-card--active');
                grid.style.display = '';
                panel.classList.add('is-visible');
                activeCard = card;
                activeGrid = grid;
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        card.addEventListener('click', handleActivate);
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleActivate();
            }
        });
    });
}());
</script>

<!-- Featured Articles Section -->
<?php if (count($featured_articles) > 0): ?>
<div class="bfaq-featured-section">
   <div class="bfaq-featured-header">
      <h2 class="bfaq-featured-title">
         <?php if ($is_search): ?>
            <?php echo htmlspecialchars(sprintf(__('Search Results for "%s"', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?> (<?php echo count($featured_articles); ?>)
         <?php else: ?>
            <?php echo ($filter === 'newest') ? __('Newest Articles', 'betterfaq') : __('Most Viewed Articles', 'betterfaq'); ?>
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
   <div class="bfaq-featured-list">
      <?php foreach ($featured_articles as $article):
         $article_id = (int) $article['id'];
         $article_name = htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8');
         $article_views = (int) ($article['view'] ?? 0);
         $article_date = isset($article['date_mod']) ? htmlspecialchars($article['date_mod'], ENT_QUOTES, 'UTF-8') : '';

         // Get article's category ID
         $cat_id = PluginBetterfaqCategory::getArticleCategoryId($article_id);
         if ($cat_id > 0) {
            $article_url = htmlspecialchars($base_url . '/front/category.php?id=' . $cat_id . '&article_id=' . $article_id, ENT_QUOTES, 'UTF-8');
         } else {
            // Fallback if no category found
            $article_url = htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8');
         }
      ?>
         <a href="<?php echo $article_url; ?>" class="bfaq-featured-item">
            <div class="bfaq-featured-item-content">
               <h4><?php echo $article_name; ?></h4>
            </div>
            <div class="bfaq-featured-icon">
               <i class="ti ti-chevron-right"></i>
            </div>
         </a>
      <?php endforeach; ?>
   </div>
</div>
<?php elseif ($is_search): ?>
<!-- No search results message -->
<div class="bfaq-featured-section">
   <div style="text-align:center; padding:40px; color:#999;">
      <p style="font-size:1.1em;">
         <?php echo htmlspecialchars(sprintf(__('No results found for "%s".', 'betterfaq'), $search_query), ENT_QUOTES, 'UTF-8'); ?>
      </p>
   </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div>

<?php
Html::footer();
