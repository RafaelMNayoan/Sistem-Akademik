<?php
require_once __DIR__ . '/ikon.php';
$peran = $_SESSION['role'];
$halaman = basename($_SERVER['SCRIPT_NAME']);
$dirIni = basename(dirname($_SERVER['SCRIPT_NAME']));

if ($peran === 'admin') {
    $namaPortal = 'Portal Admin';
    $namaPengguna = $user['nama'];
    $beranda = '../admin/dashboard.php';
    $menu = [
        '../admin/dashboard.php'  => 'Dashboard',
        '../admin/mahasiswa.php'  => 'Data Mahasiswa',
        '../admin/matakuliah.php' => 'Mata Kuliah',
        '../admin/krs.php'        => 'Kelola KRS',
        '../admin/presensi.php'   => 'Presensi',
        '../admin/tagihan.php'    => 'Tagihan',
        '../admin/materi.php'     => 'Materi',
        '../admin/tugas.php'      => 'Tugas',
        '../admin/nilai.php'      => 'Nilai',
        '../portal/pengumuman.php' => 'Pengumuman',
        '../portal/master.php'    => 'Data Master',
        '../portal/dosen.php'     => 'Data Dosen',
        '../portal/jadwal.php'    => 'Jadwal',
        '../portal/skripsi.php'   => 'Skripsi',
        '../portal/surat.php'     => 'Surat',
        '../portal/biaya.php'     => 'Biaya',
        '../portal/pembayaran.php' => 'Pembayaran',
        '../portal/beasiswa.php'  => 'Beasiswa',
        '../portal/laporan_keuangan.php' => 'Lap. Keuangan',
        '../portal/laporan_akademik.php' => 'Lap. Akademik',
        '../portal/log.php?jenis=keuangan' => 'Log Keuangan',
        '../portal/log.php?jenis=umum' => 'Log Aktivitas',
        '../admin/akun.php'       => 'Akun Saya',
        '../admin/pengguna.php'   => 'Kelola Pengguna',
    ];
} elseif ($peran === 'mahasiswa') {
    $namaPortal = 'Portal Mahasiswa';
    $namaPengguna = $user['nama_lengkap'] . ' (' . $user['nim'] . ')';
    $beranda = 'dashboard.php';
    $menu = [
        'dashboard.php' => 'Dashboard',
        'profil.php'    => 'Profil',
        'pengumuman.php' => 'Pengumuman',
        'krs.php'       => 'KRS',
        'jadwal.php'    => 'Jadwal',
        'presensi.php'  => 'Presensi',
        'materi.php'    => 'Materi',
        'tugas.php'     => 'Tugas',
        'nilai.php'     => 'Nilai',
        'skripsi.php'   => 'Skripsi',
        'surat.php'     => 'Surat',
        'tagihan.php'   => 'Tagihan',
    ];
} else {
    $namaPortal = 'Portal ' . nama_peran($peran);
    $namaPengguna = $user['nama'];
    $beranda = '../portal/dashboard.php';
    $ringkas = ['konten' => 'Pengumuman', 'master' => 'Data Master', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa',
        'kurikulum' => 'Mata Kuliah', 'jadwal' => 'Jadwal', 'krs' => 'KRS', 'presensi' => 'Presensi', 'nilai' => 'Nilai',
        'materi' => 'Materi', 'skripsi' => 'Skripsi', 'surat' => 'Surat', 'biaya' => 'Biaya', 'tagihan' => 'Tagihan',
        'pembayaran' => 'Pembayaran', 'beasiswa' => 'Beasiswa', 'laporan_keuangan' => 'Lap. Keuangan',
        'laporan_akademik' => 'Lap. Akademik', 'log_keuangan' => 'Log Keuangan', 'log_aktivitas' => 'Log Aktivitas'];
    $menu = ['../portal/dashboard.php' => 'Dashboard'];
    foreach ($ringkas as $modulMenu => $labelMenu) {
        if (isset(matriks_hak_akses()[$peran][$modulMenu])) {
            $menu[alamat_modul($peran, $modulMenu)] = $labelMenu;
            if ($modulMenu === 'materi') {
                $menu['../portal/tugas.php'] = 'Tugas';
            }
        }
    }
    $menu['../portal/akun.php'] = 'Akun Saya';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - <?= e($namaPortal) ?> SIAKAD</title>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/app.js"></script>
</head>
<body>
<header class="atas">
    <div class="wadah baris">
        <a class="merek" href="<?= e($beranda) ?>">
            <span class="logo">SP</span>
            <span class="merek-teks"><strong>SIAKAD</strong><small><?= e($namaPortal) ?></small></span>
        </a>
        <div class="pengguna">
            <span class="tanggal-hari" data-tanggal></span>
            <button type="button" class="tema-tombol" data-tema-toggle aria-label="Ganti tema" title="Ganti tema">
                <span class="ikon-matahari"><?= ikon('sun', 19) ?></span><span class="ikon-bulan"><?= ikon('moon', 19) ?></span>
            </button>
            <span class="chip-pengguna" title="<?= e($namaPengguna) ?>">
                <span class="avatar"><?= e(inisial_nama($namaPengguna)) ?></span>
                <span class="nama-pengguna"><?= e($namaPengguna) ?></span>
            </span>
            <a class="keluar" href="../logout.php?portal=<?= $peran ?>"><?= ikon('logout', 16) ?> Keluar</a>
        </div>
    </div>
    <nav class="menu" aria-label="Menu portal">
        <button type="button" class="menu-buka" data-menu-buka aria-expanded="false"><?= ikon('menu', 18) ?> Menu <?= e($namaPortal) ?></button>
        <div class="wadah">
            <?php foreach ($menu as $file => $nama): ?>
                <?php
                $jalur = parse_url($file, PHP_URL_PATH);
                $kueri = parse_url($file, PHP_URL_QUERY);
                $dirItem = strpos($jalur, '../') === 0 ? basename(dirname($jalur)) : $dirIni;
                $aktif = basename($jalur) === $halaman && $dirItem === $dirIni
                    && ($kueri === null || strpos($_SERVER['QUERY_STRING'] ?? '', $kueri) !== false);
                ?>
                <a href="<?= e($file) ?>" class="<?= $aktif ? 'aktif' : '' ?>"<?= $aktif ? ' aria-current="page"' : '' ?>><?= ikon_menu($file) ?><?= e($nama) ?></a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>

<main class="wadah">
<?php if (!empty($_SESSION['pesan'])): ?>
    <div class="pesan <?= e($_SESSION['pesan'][0]) ?>" data-kilat role="status"><?= e($_SESSION['pesan'][1]) ?></div>
    <?php unset($_SESSION['pesan']); ?>
<?php endif; ?>
