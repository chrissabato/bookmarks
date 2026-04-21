<?php
require __DIR__ . '/db.php';
init_schema();

header('Content-Type: application/json');

// Only allow requests from same origin
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) ?? '';
    if ($host !== $origin) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function post(string $key, mixed $default = null): mixed {
    return $_POST[$key] ?? $default;
}

function required_post(string $key): string {
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') throw new InvalidArgumentException("Missing: $key");
    return $v;
}

function required_int(string $key): int {
    $v = $_POST[$key] ?? null;
    if (!ctype_digit((string)$v)) throw new InvalidArgumentException("Invalid int: $key");
    return (int)$v;
}

function json_ids(string $key): array {
    $raw = $_POST[$key] ?? '[]';
    $ids = json_decode($raw, true);
    if (!is_array($ids)) throw new InvalidArgumentException("Invalid ids array");
    foreach ($ids as $id) {
        if (!is_int($id) && !ctype_digit((string)$id)) throw new InvalidArgumentException("Non-integer id");
    }
    return array_map('intval', $ids);
}

function ok(): string {
    return json_encode(['ok' => true]);
}

try {
    $pdo = get_db();

    switch ($action) {

        // ── Page Sets ─────────────────────────────────────────────────────────

        case 'page_sets.list':
            echo json_encode($pdo->query(
                "SELECT id, slug, title, position FROM page_sets ORDER BY position"
            )->fetchAll());
            break;

        case 'page_sets.create':
            $slug  = required_post('slug');
            $title = required_post('title');
            if (!preg_match('/^[a-z0-9_-]+$/', $slug)) throw new InvalidArgumentException("Invalid slug");
            $max = (int)$pdo->query("SELECT COALESCE(MAX(position)+1,0) FROM page_sets")->fetchColumn();
            $pdo->prepare("INSERT INTO page_sets (slug,title,position) VALUES (?,?,?)")
                ->execute([$slug, $title, $max]);
            $id = (int)$pdo->lastInsertId();
            echo json_encode(['id' => $id, 'slug' => $slug, 'title' => $title, 'position' => $max]);
            break;

        case 'page_sets.update':
            $id = required_int('id');
            $fields = [];
            $params = [];
            if (isset($_POST['title']) && $_POST['title'] !== '') { $fields[] = 'title=?'; $params[] = $_POST['title']; }
            if (isset($_POST['slug'])  && $_POST['slug']  !== '') {
                if (!preg_match('/^[a-z0-9_-]+$/', $_POST['slug'])) throw new InvalidArgumentException("Invalid slug");
                $fields[] = 'slug=?';
                $params[] = $_POST['slug'];
            }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE page_sets SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            }
            echo ok();
            break;

        case 'page_sets.delete':
            $id = required_int('id');
            $pdo->prepare("DELETE FROM page_sets WHERE id=?")->execute([$id]);
            echo ok();
            break;

        case 'page_sets.reorder':
            $ids = json_ids('ids');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE page_sets SET position=? WHERE id=?");
            foreach ($ids as $pos => $id) $stmt->execute([$pos, $id]);
            $pdo->commit();
            echo ok();
            break;

        // ── Categories ────────────────────────────────────────────────────────

        case 'categories.list':
            $psId = (int)($_GET['page_set_id'] ?? 0);
            if (!$psId) throw new InvalidArgumentException("Missing page_set_id");
            $cats = $pdo->prepare(
                "SELECT id, name, position FROM categories WHERE page_set_id=? ORDER BY position"
            );
            $cats->execute([$psId]);
            $result = [];
            foreach ($cats->fetchAll() as $cat) {
                $bStmt = $pdo->prepare(
                    "SELECT id, label, url, position FROM bookmarks WHERE category_id=? ORDER BY position"
                );
                $bStmt->execute([$cat['id']]);
                $cat['bookmarks'] = $bStmt->fetchAll();
                $result[] = $cat;
            }
            echo json_encode($result);
            break;

        case 'categories.create':
            $psId = required_int('page_set_id');
            $name = required_post('name');
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position)+1,0) FROM categories WHERE page_set_id=?");
            $stmt->execute([$psId]);
            $max = (int)$stmt->fetchColumn();
            $pdo->prepare("INSERT INTO categories (page_set_id,name,position) VALUES (?,?,?)")
                ->execute([$psId, $name, $max]);
            $id = (int)$pdo->lastInsertId();
            echo json_encode(['id' => $id, 'name' => $name, 'position' => $max, 'bookmarks' => []]);
            break;

        case 'categories.update':
            $id   = required_int('id');
            $name = required_post('name');
            $pdo->prepare("UPDATE categories SET name=? WHERE id=?")->execute([$name, $id]);
            echo ok();
            break;

        case 'categories.delete':
            $id = required_int('id');
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
            echo ok();
            break;

        case 'categories.reorder':
            $psId = required_int('page_set_id');
            $ids  = json_ids('ids');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE categories SET position=? WHERE id=? AND page_set_id=?");
            foreach ($ids as $pos => $id) $stmt->execute([$pos, $id, $psId]);
            $pdo->commit();
            echo ok();
            break;

        // ── Bookmarks ─────────────────────────────────────────────────────────

        case 'bookmarks.create':
            $catId = required_int('category_id');
            $label = required_post('label');
            $url   = required_post('url');
            $stmt  = $pdo->prepare("SELECT COALESCE(MAX(position)+1,0) FROM bookmarks WHERE category_id=?");
            $stmt->execute([$catId]);
            $max   = (int)$stmt->fetchColumn();
            $pdo->prepare("INSERT INTO bookmarks (category_id,label,url,position) VALUES (?,?,?,?)")
                ->execute([$catId, $label, $url, $max]);
            $id = (int)$pdo->lastInsertId();
            echo json_encode(['id' => $id, 'label' => $label, 'url' => $url, 'position' => $max]);
            break;

        case 'bookmarks.update':
            $id     = required_int('id');
            $fields = [];
            $params = [];
            if (array_key_exists('label', $_POST)) { $fields[] = 'label=?'; $params[] = $_POST['label']; }
            if (array_key_exists('url',   $_POST)) { $fields[] = 'url=?';   $params[] = $_POST['url']; }
            if ($fields) {
                $params[] = $id;
                $pdo->prepare("UPDATE bookmarks SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
            }
            echo ok();
            break;

        case 'bookmarks.delete':
            $id = required_int('id');
            $pdo->prepare("DELETE FROM bookmarks WHERE id=?")->execute([$id]);
            echo ok();
            break;

        case 'bookmarks.reorder':
            $catId = required_int('category_id');
            $ids   = json_ids('ids');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE bookmarks SET position=? WHERE id=? AND category_id=?");
            foreach ($ids as $pos => $id) $stmt->execute([$pos, $id, $catId]);
            $pdo->commit();
            echo ok();
            break;

        case 'bookmarks.move':
            $id       = required_int('id');
            $targetCat = required_int('target_category_id');
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position)+1,0) FROM bookmarks WHERE category_id=?");
            $stmt->execute([$targetCat]);
            $newPos = (int)$stmt->fetchColumn();
            $pdo->prepare("UPDATE bookmarks SET category_id=?, position=? WHERE id=?")
                ->execute([$targetCat, $newPos, $id]);
            echo ok();
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: $action"]);
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
