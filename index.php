<?php
// =========================== SESSION & DATA ===========================
session_start();

// Untuk reset data
// $_SESSION['tasks'] = [
//     [ 'id' => 1, 'title' => 'Belajar PHP',       'status' => 'belum'   ],
//     [ 'id' => 2, 'title' => 'Kerjakan tugas UX', 'status' => 'selesai' ],
// ];

// Data awal, hanya dipakai sekali saat session baru
if (!isset($_SESSION['tasks'])) {
  $_SESSION['tasks'] = [
    [ 'id' => 1, 'title' => 'Belajar PHP',       'status' => 'belum'   ],
    [ 'id' => 2, 'title' => 'Kerjakan tugas UX', 'status' => 'selesai' ],
  ];
}

// Gunakan referensi ke sesi agar mutasi langsung tersimpan
$tasks =& $_SESSION['tasks'];

// ========================== UTIL & HELPERS ===========================
/** Cek apakah status tugas sudah selesai */
function isDone(string $status): bool { return strtolower($status) === 'selesai'; }

/** Escape HTML sederhana */
function h(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/** Bangun URL dengan query tambahan tanpa menimpa query yang ada */
function buildQueryUrl(array $params): string {
  $current = $_GET;
  foreach ($params as $k => $v) { $current[$k] = $v; }
  $path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
  return $path . (empty($current) ? '' : '?' . http_build_query($current));
}

/** Redirect bersih (hapus parameter action/id agar PRG aman) */
function redirect_clean(?string $notif = null): void {
  if ($notif !== null) $_SESSION['notif'] = $notif;
  $path = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
  header('Location: ' . $path, true, 303);
  exit;
}

/** Ambil notifikasi dari query (opsional, biar tahan terhadap PRG) */
function takeNotif(): string {
  if (!isset($_SESSION['notif'])) return '';
  $msg = $_SESSION['notif'];
  unset($_SESSION['notif']);
  return $msg;
}

/** Cari index tugas berdasarkan id; return -1 jika tidak ketemu */
function findTaskIndexById(array $tasks, int $id): int {
  foreach ($tasks as $i => $t) if ((int)$t['id'] === $id) return $i;
  return -1;
}

/** Toggle status tugas berdasarkan id */
function toggleTaskStatusById(array &$tasks, int $id): bool {
  $i = findTaskIndexById($tasks, $id);
  if ($i < 0) return false;
  $tasks[$i]['status'] = isDone($tasks[$i]['status']) ? 'belum' : 'selesai';
  return true;
}

/** Hapus tugas berdasarkan id */
function deleteTaskById(array &$tasks, int $id): bool {
  $i = findTaskIndexById($tasks, $id);
  if ($i < 0) return false;
  array_splice($tasks, $i, 1);
  return true;
}

/** Validasi input judul */
function validasiJudul(?string $judul): string {
  $judul = trim((string)$judul);
  if ($judul === '') return '';
  return mb_substr($judul, 0, 120);
}

/** Tambah tugas (pakai sesi) */
function tambahTugas(array &$tasks, string $judul): void {
  $max = empty($tasks) ? 0 : max(array_map(fn($t) => (int)$t['id'], $tasks));
  $tasks[] = [ 'id' => $max + 1, 'title' => $judul, 'status' => 'belum' ];
}

/** Badge status dengan Tailwind */
function renderStatusBadge(string $status): string {
  $done = isDone($status);
  $class = $done ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-amber-100 text-amber-700 border-amber-200';
  $label = $done ? 'Selesai' : 'Belum';
  return "<span class=\"px-2 py-1 text-xs border rounded-md $class\">$label</span>";
}

/** Render satu item tugas lengkap (checkbox toggle + tombol hapus) */
function renderTaskItem(array $task): string {
  $id     = (int)($task['id'] ?? 0);
  $title  = h($task['title'] ?? '-');
  $status = (string)($task['status'] ?? 'belum');
  $checked = isDone($status) ? 'checked' : '';
  $badge  = renderStatusBadge($status);

  // Form GET untuk toggle status via checkbox (onchange submit)
  $toggleForm = "<form method=\"GET\" action=\"\" class=\"flex items-center gap-2\">"
              .   "<input type=\"hidden\" name=\"action\" value=\"toggle\">"
              .   "<input type=\"hidden\" name=\"id\" value=\"$id\">"
              .   "<input type=\"checkbox\" $checked class=\"h-4 w-4 rounded border-gray-300\" onchange=\"this.form.submit()\">"
              .   "<span class=\"select-none\">$title</span>"
              . "</form>";

  // Form POST untuk hapus
  $deleteForm = "<form method=\"POST\" action=\"\" onsubmit=\"return confirm('Hapus tugas ini?');\">"
              .   "<input type=\"hidden\" name=\"action\" value=\"delete\">"
              .   "<input type=\"hidden\" name=\"id\" value=\"$id\">"
              .   "<button class=\"text-red-600 hover:text-red-700 text-sm\" aria-label=\"Hapus tugas\">Hapus</button>"
              . "</form>";

  return "<li class=\"flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2\">"
       .   "<div class=\"flex items-center gap-2\">$toggleForm</div>"
       .   "<div class=\"flex items-center gap-3\">$badge $deleteForm</div>"
       . "</li>";
}

/** Tampilkan daftar tugas */
function tampilkanDaftar(array $tasks): void {
  echo '<ul id="todoList" class="space-y-2">';
  foreach ($tasks as $task) echo renderTaskItem($task);
  echo '</ul>';
}

// ========================== ACTION HANDLERS ==========================
// Urutan: POST dulu (mutasi), lalu GET (mutasi ringan), lalu render.
$notif = '';

// 1) POST: tambah / hapus
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'add') {
    $judul = validasiJudul($_POST['title'] ?? '');
    $notif = ($judul === '') ? 'Judul tidak boleh kosong.' : 'Tugas berhasil ditambahkan.';
    if ($judul !== '') tambahTugas($tasks, $judul);
    redirect_clean($notif); // PRG
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $ok = deleteTaskById($tasks, $id);
    $notif = $ok ? 'Tugas dihapus.' : 'Tugas tidak ditemukan.';
    redirect_clean($notif); // PRG
  }
}

// 2) GET: toggle status via checkbox
if (($_GET['action'] ?? '') === 'toggle') {
  $id = (int)($_GET['id'] ?? 0);
  $ok = toggleTaskStatusById($tasks, $id);
  $notif = $ok ? 'Status tugas diperbarui.' : 'Tugas tidak ditemukan.';
  redirect_clean($notif); // PRG
}

// Ambil notif dari query (jika ada) setelah PRG
$notif = $notif ?: takeNotif();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Aplikasi Todolist</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap');
    body { font-family: 'Open Sans', sans-serif; }
  </style>
</head>
<body class="bg-gray-50">
  <div class="container mx-auto p-4 max-w-2xl flex flex-col gap-4">
    <h1 class="text-2xl font-bold text-center">Aplikasi Todolist</h1>

    <!-- Notifikasi sederhana -->
    <?php if ($notif): ?>
      <div class="rounded-md border border-blue-200 bg-blue-50 text-blue-700 px-3 py-2 text-sm">
        <?= h($notif) ?>
      </div>
      <script>
        if (history.replaceState) {
          const url = new URL(window.location);
          url.searchParams.delete('notif');
          history.replaceState({}, document.title, url.pathname + url.search);
        }
      </script>
    <?php endif; ?>

    <!-- Form tambah (POST) -->
    <form method="POST" class="flex gap-2">
      <input type="hidden" name="action" value="add" />
      <input 
        type="text" 
        name="title"
        placeholder="Tambahkan tugas baru" 
        class="w-full p-2 border border-gray-300 rounded-md"
      />
      <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">Tambah</button>
    </form>

    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold">Daftar Tugas</h2>
        <span class="text-sm text-gray-500">Total: <?= count($tasks) ?></span>
      </div>

      <?php tampilkanDaftar($tasks); ?>
    </div>
  </div>
</body>
</html>
