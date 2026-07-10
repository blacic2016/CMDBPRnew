<?php
/**
 * Utility to generate a high-quality fallback image for PASSWORD entries.
 */
require_once __DIR__ . '/../config.php';

$dir = __DIR__ . '/../public/assets/img';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$filepath = $dir . '/browser_fallback.png';

$width = 600;
$height = 375;
$im = imagecreatetruecolor($width, $height);

// Define colors
$bg = imagecolorallocate($im, 10, 25, 47);       // Dark deep blue
$accent = imagecolorallocate($im, 0, 184, 212);   // Sonda Cyan
$white = imagecolorallocate($im, 255, 255, 255);
$gray = imagecolorallocate($im, 120, 130, 145);
$orange = imagecolorallocate($im, 245, 124, 0);

// Fill background
imagefill($im, 0, 0, $bg);

// Draw grid lines
$grid_color = imagecolorallocate($im, 17, 34, 64);
for ($x = 0; $x < $width; $x += 30) {
    imageline($im, $x, 0, $x, $height, $grid_color);
}
for ($y = 0; $y < $height; $y += 30) {
    imageline($im, 0, $y, $width, $y, $grid_color);
}

// Draw a stylized shield/lock
// Shield shape
$points = [
    300, 100, // Top center
    360, 130, // Top right
    360, 210, // Bottom right curved start
    300, 260, // Bottom center point
    240, 210, // Bottom left curved start
    240, 130  // Top left
];
imagefilledpolygon($im, $points, 6, imagecolorallocatealpha($im, 0, 184, 212, 80));
imagepolygon($im, $points, 6, $accent);

// Keyhole in the shield
imagefilledellipse($im, 300, 170, 28, 28, $bg);
imagefilledrectangle($im, 294, 170, 306, 205, $bg);

// Title text
imagestring($im, 5, 220, 290, "SONDA PASSWORD VAULT", $white);
imagestring($im, 2, 210, 315, "MODULO DE ACCESOS Y CREDENCIALES", $gray);

// Save image
if (imagepng($im, $filepath)) {
    echo "✅ Fallback asset generated successfully at public/assets/img/browser_fallback.png\n";
} else {
    echo "❌ Error generating asset.\n";
}
imagedestroy($im);
