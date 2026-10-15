<?php
session_start();
require 'koneksi.php';


$judul = $kategori = $deskripsi = $topik = $durasi = "";
$judulErr = $kategoriErr = $deskripsiErr = $topikErr = $durasiErr = "";
$dbErr = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validasi Judul Event
    $judul = trim($_POST["judul"] ?? "");
    if (empty($judul)) {
        $judulErr = "Judul event wajib diisi";
    } elseif (strlen($judul) > 150) {
        $judulErr = "Judul maksimal 150 karakter";
    }

    // Validasi Kategori
    $kategori = $_POST["kategori"] ?? "";
    if (empty($kategori)) {
        $kategoriErr = "Pilih kategori event";
    } elseif (!in_array($kategori, ['Teknologi', 'Bisnis'])) {
        $kategoriErr = "Kategori tidak valid";
    }

    // Validasi Deskripsi
    $deskripsi = trim($_POST["deskripsi"] ?? "");
    if (empty($deskripsi)) {
        $deskripsiErr = "Deskripsi wajib diisi";
    }

    // Validasi Topik Silabus
    $topik = trim($_POST["topik"] ?? "");
    if (empty($topik)) {
        $topikErr = "Topik silabus wajib diisi";
    } elseif (strlen($topik) > 150) {
        $topikErr = "Topik maksimal 150 karakter";
    }

    // Validasi Durasi Sesi
    $durasi = trim($_POST["durasi"] ?? "");
    if (empty($durasi)) {
        $durasiErr = "Durasi sesi wajib diisi";
    } elseif (!ctype_digit($durasi)) {
        $durasiErr = "Durasi hanya boleh angka (dalam menit)";
    } elseif ((int) $durasi < 1 || (int) $durasi > 1440) {
        $durasiErr = "Durasi harus antara 1 sampai 1440 menit";
    }

    // Kalau semua valid, simpan ke database
    if ($judulErr === "" && $kategoriErr === "" && $deskripsiErr === ""
        && $topikErr === "" && $durasiErr === "") {

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO events (judul, deskripsi, kategori) VALUES (?, ?, ?)"
            );
            $stmt->execute([$judul, $deskripsi, $kategori]);
            $eventId = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO sesi_events (event_id, topik_silabus, durasi_menit) VALUES (?, ?, ?)"
            );
            $stmt->execute([$eventId, $topik, (int) $durasi]);

            $pdo->commit();

            $_SESSION['flash'] = "Event \"" . $judul . "\" berhasil ditambahkan.";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $dbErr = "Gagal menyimpan ke database: " . $e->getMessage();
        }
    }
}

// ---------------------------------------------------------------
// Ambil data untuk tabel (dengan pencarian dan filter)
// ---------------------------------------------------------------
$flash = $_SESSION['flash'] ?? "";
$flashType = $_SESSION['flash_type'] ?? "success";
unset($_SESSION['flash'], $_SESSION['flash_type']);

$q = trim($_GET['q'] ?? '');
$filterKategori = $_GET['kategori'] ?? '';

$sql = "SELECT e.id, e.judul, e.deskripsi, e.kategori,
               COUNT(s.id) AS jumlah_sesi,
               COALESCE(SUM(s.durasi_menit), 0) AS total_durasi
        FROM events e
        LEFT JOIN sesi_events s ON s.event_id = e.id";
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "e.judul LIKE ?";
    $params[] = "%" . $q . "%";
}
if (in_array($filterKategori, ['Teknologi', 'Bisnis'])) {
    $where[] = "e.kategori = ?";
    $params[] = $filterKategori;
}
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " GROUP BY e.id, e.judul, e.deskripsi, e.kategori ORDER BY e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hasil = $stmt->fetchAll();

function h($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function formatDurasi($menit)
{
    $menit = (int) $menit;
    $jam = intdiv($menit, 60);
    return ($jam > 0 ? $jam . ' jam ' : '') . ($menit % 60) . ' menit';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Event - Event &amp; Workshop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>

<header class="topbar">
    <div class="topbar__inner">
        <a href="index.php" class="brand">Event &amp; Workshop</a>
        <nav class="topbar__nav">
            <a href="index.php" class="is-active">Master Event</a>
        </nav>
    </div>
</header>

<main class="page">

    <div class="page__head">
        <div>
            <h1>Master Event</h1>
            <p class="muted">Kelola judul, kategori, silabus, dan durasi sesi untuk setiap event.</p>
        </div>
        <a href="#form-tambah" class="btn btn--primary">Tambah event</a>
    </div>

    <?php if ($flash !== ""): ?>
        <div class="alert alert--<?= h($flashType) ?>" role="status"><?= h($flash) ?></div>
    <?php endif; ?>
    <?php if ($dbErr !== ""): ?>
        <div class="alert alert--danger" role="alert"><?= h($dbErr) ?></div>
    <?php endif; ?>

    <form method="GET" action="index.php" class="toolbar">
        <input type="search" name="q" value="<?= h($q) ?>" placeholder="Cari judul event" aria-label="Cari judul event">
        <select name="kategori" aria-label="Filter kategori">
            <option value="">Semua kategori</option>
            <option value="Teknologi" <?= $filterKategori === 'Teknologi' ? 'selected' : '' ?>>Teknologi</option>
            <option value="Bisnis" <?= $filterKategori === 'Bisnis' ? 'selected' : '' ?>>Bisnis</option>
        </select>
        <button type="submit" class="btn btn--ghost">Terapkan</button>
        <?php if ($q !== '' || $filterKategori !== ''): ?>
            <a href="index.php" class="link">Reset</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Judul event</th>
                    <th>Kategori</th>
                    <th class="num">Jumlah sesi</th>
                    <th class="num">Total durasi</th>
                    <th class="actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($hasil) === 0): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty">
                                <strong>Event tidak ditemukan</strong>
                                <p class="muted">Ubah kata kunci atau filter kategori, atau tambahkan event baru.</p>
                                <a href="#form-tambah" class="btn btn--primary">Tambah event</a>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($hasil as $e): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <a href="#" class="title"><?= h($e['judul']) ?></a>
                                <div class="muted clamp"><?= h($e['deskripsi']) ?></div>
                            </td>
                            <td>
                                <span class="badge badge--<?= strtolower($e['kategori']) ?>"><?= h($e['kategori']) ?></span>
                            </td>
                            <td class="num"><?= (int) $e['jumlah_sesi'] ?></td>
                            <td class="num"><?= formatDurasi($e['total_durasi']) ?></td>
                            <td class="actions">
                                <a href="#" class="btn btn--small btn--ghost">Detail</a>
                                <a href="ubah.php?id=<?= (int) $e['id'] ?>" class="btn btn--small btn--ghost">Ubah</a>
                                <form method="POST" action="hapus.php"
                                      onsubmit="return confirm('Hapus event ini beserta seluruh sesinya?')">
                                    <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                    <button type="submit" class="btn btn--small btn--danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="panel panel--narrow" id="form-tambah" style="margin-top:1.5rem">
        <h2>Tambah event</h2>

        <form method="POST" action="index.php#form-tambah" class="form" novalidate>

            <div class="field">
                <label for="judul">Judul event</label>
                <input id="judul" name="judul" type="text" maxlength="150"
                       value="<?= h($judul) ?>" placeholder="Contoh: Workshop Docker Dasar">
                <?php if ($judulErr): ?><p class="field__error"><?= h($judulErr) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori">
                    <option value="">Pilih kategori</option>
                    <option value="Teknologi" <?= $kategori === 'Teknologi' ? 'selected' : '' ?>>Teknologi</option>
                    <option value="Bisnis" <?= $kategori === 'Bisnis' ? 'selected' : '' ?>>Bisnis</option>
                </select>
                <?php if ($kategoriErr): ?><p class="field__error"><?= h($kategoriErr) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="4"
                          placeholder="Jelaskan tujuan dan isi event secara singkat"><?= h($deskripsi) ?></textarea>
                <?php if ($deskripsiErr): ?><p class="field__error"><?= h($deskripsiErr) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="topik">Topik silabus (sesi pertama)</label>
                <input id="topik" name="topik" type="text" maxlength="150"
                       value="<?= h($topik) ?>" placeholder="Contoh: Pengenalan container">
                <?php if ($topikErr): ?><p class="field__error"><?= h($topikErr) ?></p><?php endif; ?>
            </div>

            <div class="field field--short">
                <label for="durasi">Durasi (menit)</label>
                <input id="durasi" name="durasi" type="text" inputmode="numeric"
                       value="<?= h($durasi) ?>">
                <?php if ($durasiErr): ?><p class="field__error"><?= h($durasiErr) ?></p><?php endif; ?>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Simpan event</button>
                <a href="index.php" class="btn btn--ghost">Batal</a>
            </div>
        </form>
    </section>

</main>

</body>
</html>