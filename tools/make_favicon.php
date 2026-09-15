<?php
/**
 * Build the favicons from the hero painting.
 *
 *   php tools/make_favicon.php
 *
 * Re-run after changing assets/img/shepherd.webp. The crop is a square around
 * the figure — at 16 pixels a whole landscape is mud, so the icon has to be
 * the one shape that still reads.
 *
 * Writes favicon.ico at the web root plus PNGs under assets/img/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

require_once dirname(__DIR__) . '/lib/bootstrap.php';

const SRC  = APP_ROOT . '/assets/img/shepherd.webp';
const OUT  = APP_ROOT . '/assets/img';

// Square around the figure, in source pixels.
const CROP = ['x' => 700, 'y' => 170, 'size' => 220];

/** GD here has no WebP support, so fall back to macOS sips for the decode. */
function load_source(string $path): GdImage
{
    if (!is_file($path)) {
        exit("Missing $path\n");
    }
    $info = gd_info();
    if (!empty($info['WebP Support']) && str_ends_with(strtolower($path), '.webp')) {
        $im = @imagecreatefromwebp($path);
        if ($im) {
            return $im;
        }
    }
    if (str_ends_with(strtolower($path), '.webp')) {
        $tmp = sys_get_temp_dir() . '/fgc_favicon_src.png';
        exec('sips -s format png ' . escapeshellarg($path) . ' --out ' . escapeshellarg($tmp) . ' 2>/dev/null', $o, $rc);
        if ($rc !== 0 || !is_file($tmp)) {
            exit("Couldn't decode the WebP. Install GD WebP support, or convert the image to PNG first.\n");
        }
        $im = imagecreatefrompng($tmp);
        unlink($tmp);
        if (!$im) {
            exit("Couldn't read the converted PNG.\n");
        }
        return $im;
    }
    $im = @imagecreatefrompng($path) ?: @imagecreatefromjpeg($path);
    if (!$im) {
        exit("Unsupported source image.\n");
    }
    return $im;
}

/** A PNG of the crop at one size. */
function render(GdImage $square, int $size): GdImage
{
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled(
        $out, $square, 0, 0, 0, 0,
        $size, $size, imagesx($square), imagesy($square)
    );
    return $out;
}

/** An .ico wrapping PNGs — every browser since IE Vista reads this form. */
function write_ico(string $path, array $pngs): void
{
    $count  = count($pngs);
    $ico    = pack('vvv', 0, 1, $count);
    $offset = 6 + 16 * $count;
    $blobs  = '';

    foreach ($pngs as $size => $data) {
        $ico .= pack(
            'CCCCvvVV',
            $size >= 256 ? 0 : $size,
            $size >= 256 ? 0 : $size,
            0, 0, 1, 32,
            strlen($data),
            $offset
        );
        $offset += strlen($data);
        $blobs  .= $data;
    }
    file_put_contents($path, $ico . $blobs);
}

// ---- build ----
$src = load_source(SRC);
$square = imagecrop($src, [
    'x' => CROP['x'], 'y' => CROP['y'],
    'width' => CROP['size'], 'height' => CROP['size'],
]);
if (!$square) {
    exit("Crop failed — is the source smaller than the crop box?\n");
}

$written = [];

foreach ([180, 192] as $size) {
    $file = OUT . "/icon-$size.png";
    imagepng(render($square, $size), $file, 9);
    $written[] = $file;
}

// The .ico carries 16 and 32 for the browser tab.
$pngs = [];
foreach ([16, 32, 48] as $size) {
    ob_start();
    imagepng(render($square, $size), null, 9);
    $pngs[$size] = (string)ob_get_clean();
}
$icoPath = APP_ROOT . '/favicon.ico';
write_ico($icoPath, $pngs);
$written[] = $icoPath;

foreach ($written as $f) {
    printf("  %-44s %6s\n", str_replace(APP_ROOT . '/', '', $f), round(filesize($f) / 1024, 1) . 'K');
}
echo "\nDone. The <link> tags are already in lib/layout.php.\n";
