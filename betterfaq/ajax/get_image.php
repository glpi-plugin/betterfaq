<?php

include("../../../inc/includes.php");

Session::checkLoginUser();

// Get the image filename from the query parameter
$filename = isset($_GET['f']) ? $_GET['f'] : '';

// Validate filename: must match pattern cat_N.ext where N is a number and ext is allowed
if (!preg_match('/^cat_(\d+)\.(jpg|jpeg|png|gif|webp|svg)$/i', $filename)) {
   http_response_code(404);
   exit;
}

$upload_dir = dirname(__DIR__) . '/uploads/categories/';
$file_path  = $upload_dir . $filename;

// Verify file exists and is in the correct directory
if (!is_file($file_path)) {
   error_log('BetterFAQ get_image.php: File not found: ' . $file_path);
   http_response_code(404);
   exit;
}

// Security check: verify path is within upload_dir
$real_file = realpath($file_path);
$real_dir = realpath($upload_dir);
if (!$real_file || !$real_dir || strpos($real_file, $real_dir) !== 0) {
   error_log('BetterFAQ get_image.php: Path traversal attempt or realpath failed. file=' . ($real_file ?: 'null') . ', dir=' . ($real_dir ?: 'null'));
   http_response_code(404);
   exit;
}

// Determine MIME type
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime_types = [
   'jpg'  => 'image/jpeg',
   'jpeg' => 'image/jpeg',
   'png'  => 'image/png',
   'gif'  => 'image/gif',
   'webp' => 'image/webp',
   'svg'  => 'image/svg+xml',
];

$mime_type = $mime_types[$ext] ?? 'application/octet-stream';

// Send the file
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=86400');
readfile($file_path);
