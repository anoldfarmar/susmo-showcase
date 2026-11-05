<?php
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
$destination = $root . '/theme/assets/images';
if (!is_dir($destination)) { mkdir($destination, 0777, true); }
$assets = array('首页图.png'=>array('hero.webp', 1942), '正面.png'=>array('wig-front.webp', 1100), '侧面.png'=>array('wig-side.webp', 1100), '局部特写.png'=>array('wig-detail.webp', 1100), '商品示意图.png'=>array('wig-cap.webp', 1100));
foreach ($assets as $source => [$name, $maxWidth]) {
    $image = imagecreatefrompng($root . '/素材库/' . $source);
    $width = min($maxWidth, imagesx($image));
    $height = (int) round(imagesy($image) * $width / imagesx($image));
    $scaled = imagescale($image, $width, $height, IMG_BICUBIC);
    if (!imagewebp($scaled, $destination . '/' . $name, 86)) { throw new RuntimeException('Asset conversion failed.'); }
    echo $name . ': ' . $width . 'x' . $height . ', ' . filesize($destination . '/' . $name) . " bytes\n";
    imagedestroy($image); imagedestroy($scaled);
}
copy($root . '/素材库/susmo-logo.svg', $destination . '/susmo-logo.svg');
