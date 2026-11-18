<?php // Selfcare original template. Social icons as inline SVG. Variables: $links (name => url)
use function Selfcare\e;
$paths = [
    'facebook' => '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v8h4v-8h3l1-4h-4V8.5A.5.5 0 0 1 14 8Z"/>',
    'x' => '<path d="M4 4l16 16M20 4 4 20"/>',
    'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>',
    'youtube' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5v.01"/>',
];
foreach ($links as $n => $u): if (!$u || !isset($paths[$n])) { continue; } ?>
<a href="<?= e($u) ?>" rel="noopener noreferrer" target="_blank" aria-label="<?= e(ucfirst($n)) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $paths[$n] ?></svg></a>
<?php endforeach; ?>
