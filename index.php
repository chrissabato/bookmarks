<?php
require __DIR__ . '/db.php';
require __DIR__ . '/access.php';
init_schema();
$pdo = get_db();

// Determine active set
$activeSlug = $_GET['set'] ?? 'root';
$activeSet  = $pdo->prepare("SELECT id, slug, title, is_public FROM page_sets WHERE slug=?");
$activeSet->execute([$activeSlug]);
$activeSet  = $activeSet->fetch() ?: null;

check_access((bool)($activeSet['is_public'] ?? false));

// Load categories + bookmarks for the active set
$categories = [];
if ($activeSet) {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE page_set_id=? ORDER BY position");
    $stmt->execute([$activeSet['id']]);
    foreach ($stmt->fetchAll() as $cat) {
        $bStmt = $pdo->prepare("SELECT label, url, icon FROM bookmarks WHERE category_id=? ORDER BY position");
        $bStmt->execute([$cat['id']]);
        $cat['bookmarks'] = $bStmt->fetchAll();
        $categories[] = $cat;
    }
}

$title = $activeSet['title'] ?? 'Bookmarks';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css">
  <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/bold/style.css">
</head>
<body class="bg-slate-950 min-h-screen">
<div class="w-full px-4 pb-8">

  <nav class="border-b border-slate-800 py-3 mb-4 flex items-center justify-between">
    <h1 class="text-xl font-bold text-white uppercase tracking-wide">
      <?= htmlspecialchars($title) ?>
    </h1>
    <input id="search" type="search" placeholder="Search…"
           class="bg-slate-800 border border-slate-700 text-slate-200 text-sm rounded px-3 py-1.5
                  focus:outline-none focus:border-slate-500 w-48 placeholder-slate-500">
  </nav>

  <div class="columns-2 sm:columns-3 md:columns-4 lg:columns-5 xl:columns-6 2xl:columns-8 gap-3">
    <?php foreach ($categories as $cat): ?>
      <div class="category-card bg-slate-800 rounded-lg border border-slate-700 mb-3 break-inside-avoid"
           data-name="<?= htmlspecialchars(strtolower($cat['name'])) ?>">
        <div class="text-center text-sm font-semibold text-slate-200 uppercase tracking-wide py-2 px-3 border-b border-slate-700">
          <?= htmlspecialchars($cat['name']) ?>
        </div>
        <div class="flex flex-col p-2 gap-1.5">
          <?php foreach ($cat['bookmarks'] as $bm): ?>
            <?php
              $url  = $bm['url'];
              $norm = strncmp($url, '//', 2) === 0 ? 'https:' . $url : $url;
              $isJs = strncmp($norm, 'javascript:', 11) === 0;
              $fav  = '';
              if (!$isJs) {
                  try {
                      $host = parse_url($norm, PHP_URL_HOST);
                      if ($host) $fav = 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32';
                  } catch (Throwable $e) {}
              }
            ?>
            <a href="<?= htmlspecialchars($url) ?>"
               <?= $isJs ? '' : 'target="_blank"' ?>
               class="link-row flex items-center gap-2 text-sm text-slate-300 hover:text-white
                      bg-slate-900 hover:bg-slate-700 rounded px-2.5 py-1.5 transition-colors"
               data-label="<?= htmlspecialchars(strtolower($bm['label'])) ?>"
               data-url="<?= htmlspecialchars(strtolower($url)) ?>">
              <?php if (!empty($bm['icon'])): ?>
                <i class="<?= htmlspecialchars($bm['icon']) ?> w-4 text-center text-slate-400 shrink-0 text-sm"></i>
              <?php elseif ($fav): ?>
                <img src="<?= htmlspecialchars($fav) ?>" width="16" height="16"
                     class="shrink-0 opacity-60" onerror="this.style.display='none'" loading="lazy">
              <?php endif; ?>
              <span><?= htmlspecialchars($bm['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const searchInput = document.getElementById('search');
const cards = document.querySelectorAll('.category-card');

searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    if (!q) {
        cards.forEach(c => { c.hidden = false; c.querySelectorAll('.link-row').forEach(r => r.hidden = false); });
        return;
    }
    cards.forEach(card => {
        let any = false;
        card.querySelectorAll('.link-row').forEach(row => {
            const match = row.dataset.label.includes(q) || row.dataset.url.includes(q);
            row.hidden = !match;
            if (match) any = true;
        });
        card.hidden = !any;
    });
});

searchInput.addEventListener('keydown', e => {
    if (e.key === 'Escape') { searchInput.value = ''; searchInput.dispatchEvent(new Event('input')); }
});
</script>
</body>
</html>
