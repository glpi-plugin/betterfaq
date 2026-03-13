<?php
include("../../../inc/includes.php");
$params = $_GET;
$qs = http_build_query($params);
header('Location: index.php' . ($qs ? '?' . $qs : ''));
exit;
