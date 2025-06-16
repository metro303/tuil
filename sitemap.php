<?php
// ========== CONFIGURATION ==========
$domain_url            = 'https://officialadmin.bangkok.go.th/upload/';
$base_url              = 'https://officialadmin.bangkok.go.th/upload/?video=';
$sitemap_name          = 'sitemap';       // Each chunk: sitemap-1.xml, sitemap-2.xml, …
$max_links_per_sitemap = 20000;
$local_file            = 'car.txt';       // Local keyword file
// ====================================

$script_dir = dirname(__FILE__);

// 1) Ensure the script directory is writable
if (!is_writable($script_dir)) {
    die("❌ The directory '{$script_dir}' is not writable by PHP. Grant Modify/Write rights and try again.");
}

// 2) Ensure car.txt exists and is readable
$file_path = $script_dir . DIRECTORY_SEPARATOR . $local_file;
if (!file_exists($file_path) || !is_readable($file_path)) {
    die("❌ Cannot read '{$local_file}'. Make sure it exists in '{$script_dir}' and is readable.");
}

// 3) Read all non-empty lines from car.txt
$raw_lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$keywords  = [];

foreach ($raw_lines as $line) {
    // Decode any percent-encoded entries (just in case), then trim
    $decoded = trim(rawurldecode($line));
    if ($decoded !== '') {
        $keywords[] = $decoded;
    }
}

// 4) Fail early if no valid lines
if (empty($keywords)) {
    die("❌ No valid keywords found in {$local_file}");
}

// 5) Begin building the sitemap‐index XML
$sitemap_index  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$sitemap_index .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// This array will hold chunks of <urlset> content
$sitemap_files = [];

/** 
 * 6) For each keyword:
 *    - Convert spaces → hyphens
 *    - Lowercase the slug (for consistency)
 *    - rawurlencode it
 *    - Assign it to the appropriate chunk (every $max_links_per_sitemap)
 */
foreach ($keywords as $i => $keyword) {
    // 6a) Lowercase the keyword, replace spaces with hyphens
    $lowercased     = mb_strtolower($keyword, 'UTF-8');
    $slug_candidate = str_replace(' ', '-', $lowercased);

    // 6b) rawurlencode the slug (Thai characters → %E0…, etc.)
    $encoded_keyword = rawurlencode($slug_candidate);

    // 6c) Determine which chunk number this link belongs to
    $sitemap_num = (int)ceil(($i + 1) / $max_links_per_sitemap);

    // 6d) If this is the first URL in chunk‐# $sitemap_num, initialize <urlset>
    if (!isset($sitemap_files[$sitemap_num])) {
        $sitemap_files[$sitemap_num]  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $sitemap_files[$sitemap_num] .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    }

    // 6e) Build the full URL and escape for XML
    $full_url     = $base_url . $encoded_keyword;
    $escaped_url  = htmlspecialchars($full_url, ENT_QUOTES, 'UTF-8');

    $sitemap_files[$sitemap_num] .= "  <url>" . PHP_EOL;
    $sitemap_files[$sitemap_num] .= "    <loc>{$escaped_url}</loc>" . PHP_EOL;
    $sitemap_files[$sitemap_num] .= "  </url>" . PHP_EOL;
}

// 7) Close each <urlset>, write to its own file, and add it to sitemap‐index
foreach ($sitemap_files as $num => $content) {
    $content .= '</urlset>' . PHP_EOL;

    // Write chunk file (e.g. sitemap-1.xml, sitemap-2.xml, …)
    $chunk_filename = "{$sitemap_name}-{$num}.xml";
    $absolute_path  = $script_dir . DIRECTORY_SEPARATOR . $chunk_filename;

    if (false === file_put_contents($absolute_path, $content)) {
        die("❌ Failed to write {$absolute_path}. Check permissions.");
    }

    // In the sitemap‐index, point to the public URL for this chunk
    $index_loc = htmlspecialchars($domain_url . $chunk_filename, ENT_QUOTES, 'UTF-8');

    $sitemap_index .= "  <sitemap>" . PHP_EOL;
    $sitemap_index .= "    <loc>{$index_loc}</loc>" . PHP_EOL;
    $sitemap_index .= "  </sitemap>" . PHP_EOL;
}

// 8) Finalize and write sitemap‐index.xml
$sitemap_index .= '</sitemapindex>' . PHP_EOL;
$index_filename = $script_dir . DIRECTORY_SEPARATOR . 'sitemap-index.xml';

if (false === file_put_contents($index_filename, $sitemap_index)) {
    die("❌ Failed to write {$index_filename}. Check permissions.");
}

// 9) Success message (with a real newline)
echo "✅ Sitemap(s) successfully created in '{$script_dir}'.\n";
?>
