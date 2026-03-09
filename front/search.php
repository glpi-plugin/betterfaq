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

$query   = isset($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';
$results = [];
if (!empty($query) && mb_strlen($query) <= 200) {
   $results = PluginBetterfaqCategory::searchArticles($query, 50);
}

$interface = Session::getCurrentInterface();
if ($interface === 'helpdesk') {
   Html::helpHeader(__('FAQ', 'betterfaq'));
} else {
   Html::header(__('FAQ', 'betterfaq'), $_SERVER['PHP_SELF'], 'tools');
}

?>
<style>
.bfaq-search-container {
   max-width: 800px;
   margin: 20px auto;
   padding: 0 20px;
}
.bfaq-search-bar {
   display: flex;
   gap: 0;
   margin-bottom: 25px;
}
.bfaq-search-bar input[type="text"] {
   flex: 1;
   padding: 12px 18px;
   border: 2px solid var(--glpi-mainmenu-bg,var(--bs-primary));
   border-right: none;
   border-radius: 8px 0 0 8px;
   font-size: 1em;
   outline: none;
}
.bfaq-search-bar button {
   padding: 12px 20px;
   border: 2px solid var(--glpi-mainmenu-bg,var(--bs-primary));
   border-radius: 0 8px 8px 0;
   background: var(--glpi-mainmenu-bg,var(--bs-primary));
   color: var(--glpi-mainmenu-fg,#fff);
   cursor: pointer;
   font-size: 1em;
}
.bfaq-result-item {
   padding: 15px 0;
   border-bottom: 1px solid #eee;
}
.bfaq-result-item:last-child { border-bottom: none; }
.bfaq-result-item a {
   font-size: 1.05em;
   color: var(--glpi-mainmenu-bg,var(--bs-primary));
   text-decoration: none;
   font-weight: 600;
}
.bfaq-result-item a:hover { text-decoration: underline; }
.bfaq-result-excerpt {
   color: #555;
   font-size: 0.9em;
   margin-top: 5px;
   line-height: 1.5;
}
</style>

<div class="bfaq-search-container">

   <div style="margin-bottom:15px;">
      <a href="<?php echo htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8'); ?>" style="color:var(--glpi-mainmenu-bg,var(--bs-primary)); text-decoration:none;">
         <i class="ti ti-arrow-left"></i> <?php echo __('Back to FAQ', 'betterfaq'); ?>
      </a>
   </div>

   <h2><?php echo __('Search Results', 'betterfaq'); ?></h2>

   <form class="bfaq-search-bar" method="GET" action="">
      <input type="text" name="q" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('Search the FAQ...', 'betterfaq'), ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit"><i class="ti ti-search"></i></button>
   </form>

   <?php if (empty($query)): ?>
      <p style="color:#6c757d;"><?php echo __('Enter a search term above.', 'betterfaq'); ?></p>
   <?php elseif (count($results) === 0): ?>
      <p style="color:#6c757d;">
         <?php echo sprintf(__('No results found for "%s".', 'betterfaq'), htmlspecialchars($query, ENT_QUOTES, 'UTF-8')); ?>
      </p>
   <?php else: ?>
      <p style="color:#6c757d; margin-bottom:15px;">
         <?php echo sprintf(_n('%d result found', '%d results found', count($results), 'betterfaq'), count($results)); ?>
         <?php echo sprintf(__('for "%s"', 'betterfaq'), htmlspecialchars($query, ENT_QUOTES, 'UTF-8')); ?>
      </p>

      <div>
         <?php foreach ($results as $article):
            // Generate plaintext excerpt from answer
            $raw_text = strip_tags($article['answer'] ?? '');
            $excerpt  = mb_substr($raw_text, 0, 200);
            if (mb_strlen($raw_text) > 200) {
               $excerpt .= '...';
            }

            // Get article's category ID
            $cat_id = PluginBetterfaqCategory::getArticleCategoryId((int) $article['id']);
            if ($cat_id > 0) {
               $article_url = htmlspecialchars($base_url . '/front/category.php?id=' . $cat_id . '&article_id=' . (int) $article['id'], ENT_QUOTES, 'UTF-8');
            } else {
               // Fallback if no category found
               $article_url = htmlspecialchars($base_url . '/front/index.php', ENT_QUOTES, 'UTF-8');
            }
         ?>
            <div class="bfaq-result-item">
               <a href="<?php echo $article_url; ?>">
                  <?php echo htmlspecialchars($article['name'], ENT_QUOTES, 'UTF-8'); ?>
               </a>
               <?php if (!empty($excerpt)): ?>
                  <div class="bfaq-result-excerpt"><?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></div>
               <?php endif; ?>
            </div>
         <?php endforeach; ?>
      </div>
   <?php endif; ?>

</div>

<?php
Html::footer();
