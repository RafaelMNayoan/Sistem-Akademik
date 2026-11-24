<?php
require_once 'koneksi.php';
require_once __DIR__ . '/includes/ikon.php';
if (!empty($_SESSION['role'])) {
    $r = $_SESSION['role'];
    if ($r === 'admin') pindah('admin/dashboard.php');
    if ($r === 'mahasiswa') pindah('mahasiswa/dashboard.php');
    if (in_array($r, ['kaprodi', 'staff', 'bendahara', 'dosen'], true)) pindah('portal/dashboard.php');
}
$menu = [
    ['login.php?role=mahasiswa', 'Mahasiswa', 'graduation', 'Portal Mahasiswa|KRS, Jadwal, Nilai, Tagihan', ''],
    ['login.php?role=dosen', 'Dosen', 'book-open', 'Portal Dosen|Presensi, Nilai, Materi, Skripsi', ''],
    ['login.php?role=kaprodi', 'Kaprodi', 'clipboard', 'Portal Kaprodi|Kurikulum, Jadwal, Persetujuan KRS', ''],
    ['login.php?role=staff', 'Staff', 'briefcase', 'Staff Akademik|Data Mahasiswa, Jadwal, Surat', ''],
    ['login.php?role=bendahara', 'Bendahara', 'banknote', 'Keuangan|Tagihan, Pembayaran, Beasiswa', ''],
    ['login.php?role=admin', 'Admin', 'shield', 'Admin Website|Akun, Dosen, Data Master', ''],
    ['mahasiswa/register.php', 'Pendaftaran', 'user-plus', 'Mahasiswa Baru|Daftar dan dapatkan NIM', 'hijau'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Akademik Kampus</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js"></script>
</head>
<body>
<div class="bar-atas"></div>
<div class="beranda-latar">
    <div class="beranda-atas">
        <span class="merek">
            <span class="logo">SP</span>
            <span class="merek-teks"><strong>SIAKAD</strong><small>Sistem Akademik Kampus</small></span>
        </span>
        <button type="button" class="tema-tombol" data-tema-toggle aria-label="Ganti tema" title="Ganti tema">
            <span class="ikon-matahari"><?= ikon('sun', 19) ?></span><span class="ikon-bulan"><?= ikon('moon', 19) ?></span>
        </button>
    </div>

    <div class="beranda">
        <div class="profil-kampus">
            <div class="lambang">SP</div>
            <p>Sistem Informasi Akademik. Pilih layanan sesuai peran Anda.</p>
            <div class="nama">Sistem Akademik Kampus</div>
        </div>

        <div>
            <div class="beranda-judul">
                <h1>Selamat datang di Portal Kampus</h1>
                <p>Pilih portal di bawah ini untuk masuk.</p>
            </div>
            <div class="kisi-menu">
            <?php foreach ($menu as [$url, $nama, $ikonMenu, $ket, $warna]): [$sub1, $sub2] = explode('|', $ket); ?>
                <a href="<?= e($url) ?>">
                    <div class="judul"><span class="ikon <?= e($warna) ?>"><?= ikon($ikonMenu, 22) ?></span><?= e($nama) ?></div>
                    <small><b><?= e($sub1) ?></b><br><?= e($sub2) ?></small>
                    <span class="panah"><?= $warna === 'hijau' ? 'Daftar sekarang' : 'Masuk' ?> <?= ikon('arrow-right', 15) ?></span>
                </a>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <footer class="beranda-footer">&copy; <?= date('Y') ?> Sistem Akademik Kampus</footer>
</div>
</body>
</html>
