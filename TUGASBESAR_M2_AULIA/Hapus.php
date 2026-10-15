<?php
session_start();
require 'koneksi.php';

// Hapus hanya boleh lewat POST, supaya tidak bisa terpicu lewat link biasa
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) {
    $_SESSION['flash'] = "ID event tidak valid.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT judul FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $event = $stmt->fetch();

    if (!$event) {
        $_SESSION['flash'] = "Event tidak ditemukan.";
        $_SESSION['flash_type'] = "danger";
    } else {
        // Baris di sesi_events ikut terhapus otomatis (ON DELETE CASCADE)
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['flash'] = "Event \"" . $event['judul'] . "\" beserta seluruh sesinya berhasil dihapus.";
        $_SESSION['flash_type'] = "success";
    }
} catch (PDOException $e) {
    $_SESSION['flash'] = "Gagal menghapus event: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit;