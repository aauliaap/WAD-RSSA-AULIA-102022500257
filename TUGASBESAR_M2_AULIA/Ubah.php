<?php
session_start();
require 'koneksi.php';

function h($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// Ambil id dari URL (GET) atau dari form (POST)
$id = filter_var($_GET['id'] ?? $_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['flash'] = "ID event tidak valid.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

// Ambil data event yang akan diubah
$stmt = $pdo->prepare("SELECT id, judul, deskripsi, kategori FROM events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['flash'] = "Event tidak ditemukan.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

// Nilai awal form = data dari database
$judul     = $event['judul'];
$kategori  = $event['kategori'];
$deskripsi = $event['deskripsi'];
$judulErr = $kategoriErr = $deskripsiErr = "";
$dbErr = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $judul = trim($_POST["judul"] ?? "");
    if (empty($judul)) {
        $judulErr = "Judul event wajib diisi";
    } elseif (strlen($judul) > 150) {
        $judulErr = "Judul maksimal 150 karakter";
    }

    $kategori = $_POST["kategori"] ?? "";
    if (empty($kategori)) {
        $kategoriErr = "Pilih kategori event";
    } elseif (!in_array($kategori, ['Teknologi', 'Bisnis'])) {
        $kategoriErr = "Kategori tidak valid";
    }

    $deskripsi = trim($_POST["deskripsi"] ?? "");
    if (empty($deskripsi)) {
        $deskripsiErr = "Deskripsi wajib diisi";
    }

    if ($judulErr === "" && $kategoriErr === "" && $deskripsiErr === "") {
        try {
            $stmt = $pdo->prepare(
                "UPDATE events SET judul = ?, deskripsi = ?, kategori = ? WHERE id = ?"
            );
            $stmt->execute([$judul, $deskripsi, $kategori, $id]);

            $_SESSION['flash'] = "Event \"" . $judul . "\" berhasil diperbarui.";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $dbErr = "Gagal memperbarui database: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ubah Event - Event &amp; Workshop</title>
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

    <div class="breadcrumb"><a href="index.php">&larr; Kembali ke Master Event</a></div>

    <div class="page__head">
        <div>
            <h1>Ubah event</h1>
            <p class="muted">Perbarui judul, kategori, dan deskripsi event.</p>
        </div>
    </div>

    <?php if ($dbErr !== ""): ?>
        <div class="alert alert--danger" role="alert"><?= h($dbErr) ?></div>
    <?php endif; ?>

    <section class="panel panel--narrow">
        <form method="POST" action="ubah.php?id=<?= (int) $id ?>" class="form" novalidate>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="field">
                <label for="judul">Judul event</label>
                <input id="judul" name="judul" type="text" maxlength="150" value="<?= h($judul) ?>">
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
                <textarea id="deskripsi" name="deskripsi" rows="5"><?= h($deskripsi) ?></textarea>
                <?php if ($deskripsiErr): ?><p class="field__error"><?= h($deskripsiErr) ?></p><?php endif; ?>
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Simpan perubahan</button>
                <a href="index.php" class="btn btn--ghost">Batal</a>
            </div>
        </form>
    </section>

</main>

</body>
</html>