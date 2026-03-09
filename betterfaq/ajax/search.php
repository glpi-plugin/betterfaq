<?php

include("../../../inc/includes.php");

header('Content-Type: application/json; charset=utf-8');

Session::checkLoginUser();

// Permission check
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
   echo json_encode(['error' => 'Access denied', 'results' => []]);
   exit;
}

$query = isset($_GET['q']) ? trim(strip_tags($_GET['q'])) : '';

if (empty($query) || mb_strlen($query) < 2 || mb_strlen($query) > 200) {
   echo json_encode(['results' => []]);
   exit;
}

global $CFG_GLPI;
$base_url = $CFG_GLPI['root_doc'] . '/plugins/' . PLUGIN_BETTERFAQ_DIR;

$articles = PluginBetterfaqCategory::searchArticles($query, 10);

$results = [];
foreach ($articles as $article) {
   $raw_text = strip_tags($article['answer'] ?? '');
   $excerpt  = mb_substr($raw_text, 0, 120);
   if (mb_strlen($raw_text) > 120) {
      $excerpt .= '...';
   }

   // Get article's category ID
   $cat_id = PluginBetterfaqCategory::getArticleCategoryId((int) $article['id']);
   if ($cat_id > 0) {
      $article_url = $base_url . '/front/category.php?id=' . $cat_id . '&article_id=' . (int) $article['id'];
   } else {
      // Fallback if no category found
      $article_url = $base_url . '/front/index.php';
   }

   $results[] = [
      'id'      => (int) $article['id'],
      'title'   => $article['name'],
      'excerpt' => $excerpt,
      'url'     => $article_url,
   ];
}

echo json_encode(['results' => $results]);
