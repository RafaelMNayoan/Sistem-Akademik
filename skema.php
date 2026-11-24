<?php

const SKEMA_VERSI = '4';

function skema_ddl()
{
    return [
        'pengaturan' => "CREATE TABLE IF NOT EXISTS pengaturan (
            kunci VARCHAR(50) PRIMARY KEY,
            nilai VARCHAR(255) NULL
        )",
        'prodi' => "CREATE TABLE IF NOT EXISTS prodi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL UNIQUE,
            jenjang VARCHAR(10) NOT NULL DEFAULT 'S1',
            keterangan VARCHAR(255) NULL
        )",
        'tahun_akademik' => "CREATE TABLE IF NOT EXISTS tahun_akademik (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(20) NOT NULL,
            semester ENUM('Ganjil','Genap') NOT NULL,
            tgl_mulai DATE NULL,
            tgl_selesai DATE NULL,
            aktif TINYINT NOT NULL DEFAULT 0
        )",
        'dosen' => "CREATE TABLE IF NOT EXISTS dosen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nip VARCHAR(30) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            prodi VARCHAR(100) NULL,
            email VARCHAR(100) NULL,
            no_hp VARCHAR(20) NULL,
            status ENUM('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
            akun_id INT NULL
        )",
        'pengumuman' => "CREATE TABLE IF NOT EXISTS pengumuman (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(200) NOT NULL,
            isi TEXT NOT NULL,
            prodi VARCHAR(100) NULL,
            penulis VARCHAR(100) NOT NULL,
            peran_penulis VARCHAR(20) NOT NULL,
            dibuat DATETIME NOT NULL
        )",
        'kelas_kuliah' => "CREATE TABLE IF NOT EXISTS kelas_kuliah (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kode_mk VARCHAR(20) NOT NULL,
            dosen_id INT NULL,
            tahun_akademik VARCHAR(30) NOT NULL,
            hari VARCHAR(10) NOT NULL,
            jam_mulai VARCHAR(5) NOT NULL,
            jam_selesai VARCHAR(5) NOT NULL,
            ruang VARCHAR(50) NOT NULL,
            status ENUM('Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Diajukan',
            catatan VARCHAR(255) NULL,
            diubah_oleh VARCHAR(100) NULL
        )",
        'skripsi' => "CREATE TABLE IF NOT EXISTS skripsi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nim VARCHAR(10) NOT NULL,
            judul VARCHAR(255) NOT NULL,
            abstrak TEXT NULL,
            pembimbing_id INT NULL,
            status ENUM('Diajukan','Disetujui','Ditolak','Layak Sidang','Selesai') NOT NULL DEFAULT 'Diajukan',
            catatan_kaprodi VARCHAR(255) NULL,
            nilai_huruf VARCHAR(2) NULL,
            diajukan DATETIME NOT NULL
        )",
        'bimbingan_skripsi' => "CREATE TABLE IF NOT EXISTS bimbingan_skripsi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            skripsi_id INT NOT NULL,
            waktu DATETIME NOT NULL,
            isi TEXT NOT NULL,
            oleh VARCHAR(100) NOT NULL,
            peran VARCHAR(20) NOT NULL
        )",
        'surat' => "CREATE TABLE IF NOT EXISTS surat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            jenis VARCHAR(100) NOT NULL,
            pemohon_tipe ENUM('mahasiswa','dosen') NOT NULL,
            pemohon_ref VARCHAR(50) NOT NULL,
            pemohon_nama VARCHAR(100) NOT NULL,
            prodi VARCHAR(100) NULL,
            keperluan TEXT NULL,
            status ENUM('Diajukan','Diproses','Disetujui','Ditolak','Selesai') NOT NULL DEFAULT 'Diajukan',
            nomor_surat VARCHAR(60) NULL,
            catatan VARCHAR(255) NULL,
            dibuat DATETIME NOT NULL,
            diproses_oleh VARCHAR(100) NULL,
            disetujui_oleh VARCHAR(100) NULL
        )",
        'komponen_biaya' => "CREATE TABLE IF NOT EXISTS komponen_biaya (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            prodi VARCHAR(100) NULL,
            angkatan INT NULL,
            nominal DECIMAL(15,2) NOT NULL,
            keterangan VARCHAR(255) NULL,
            aktif TINYINT NOT NULL DEFAULT 1
        )",
        'pembayaran' => "CREATE TABLE IF NOT EXISTS pembayaran (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tagihan_id INT NOT NULL,
            jumlah DECIMAL(15,2) NOT NULL,
            metode ENUM('Transfer','Virtual Account','Tunai') NOT NULL,
            tanggal DATE NOT NULL,
            referensi VARCHAR(100) NULL,
            catatan VARCHAR(255) NULL,
            status ENUM('Menunggu','Terverifikasi','Ditolak') NOT NULL DEFAULT 'Menunggu',
            dicatat_oleh VARCHAR(100) NOT NULL,
            diverifikasi_oleh VARCHAR(100) NULL,
            diverifikasi_pada DATETIME NULL,
            alasan VARCHAR(255) NULL
        )",
        'beasiswa' => "CREATE TABLE IF NOT EXISTS beasiswa (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nim VARCHAR(10) NOT NULL,
            jenis ENUM('Beasiswa','Keringanan','Cicilan','Dispensasi') NOT NULL,
            nama VARCHAR(150) NOT NULL,
            nilai_potongan DECIMAL(15,2) NOT NULL DEFAULT 0,
            tagihan_id INT NULL,
            keterangan VARCHAR(255) NULL,
            status ENUM('Diusulkan','Disetujui Prodi','Disetujui','Ditolak','Dicabut') NOT NULL DEFAULT 'Diusulkan',
            dibuat_oleh VARCHAR(100) NOT NULL,
            disetujui_prodi_oleh VARCHAR(100) NULL,
            disetujui_oleh VARCHAR(100) NULL,
            dibuat DATETIME NOT NULL
        )",
        'log_aktivitas' => "CREATE TABLE IF NOT EXISTS log_aktivitas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            waktu DATETIME NOT NULL,
            jenis ENUM('umum','keuangan') NOT NULL DEFAULT 'umum',
            peran VARCHAR(20) NOT NULL,
            pengguna VARCHAR(50) NOT NULL,
            modul VARCHAR(40) NOT NULL,
            aksi VARCHAR(60) NOT NULL,
            detail TEXT NULL
        )",
    ];
}

function skema_alter()
{
    return [
        ['mata_kuliah', 'prodi', 'VARCHAR(100) NULL'],
        ['mata_kuliah', 'semester_ke', 'INT NULL'],
        ['mata_kuliah', 'status_kurikulum', "ENUM('Draft','Disetujui') NOT NULL DEFAULT 'Disetujui'"],
        ['krs', 'status', "ENUM('Diajukan','Disetujui','Ditolak') NOT NULL DEFAULT 'Diajukan'"],
        ['krs', 'diproses_oleh', 'VARCHAR(100) NULL'],
        ['krs', 'diproses_pada', 'DATETIME NULL'],
        ['krs', 'catatan', 'VARCHAR(255) NULL'],
        ['mahasiswa', 'dosen_pa_id', 'INT NULL'],
        ['tagihan', 'alasan_batal', 'VARCHAR(255) NULL'],
        ['tagihan', 'dibuat_oleh', 'VARCHAR(100) NULL'],
    ];
}

function migrasi_skema(PDO $db)
{
    try {
        $versi = $db->query("SELECT nilai FROM pengaturan WHERE kunci = 'skema_versi'")->fetchColumn();
    } catch (PDOException $e) {
        $versi = false;
    }
    if ($versi === SKEMA_VERSI) {
        return;
    }

    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $akhiran = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

    foreach (skema_ddl() as $sql) {
        $db->exec($sql . $akhiran);
    }

    foreach (skema_alter() as [$tabel, $kolom, $definisi]) {
        try {
            $db->query("SELECT $kolom FROM $tabel LIMIT 0");
        } catch (PDOException $e) {
            $db->exec("ALTER TABLE $tabel ADD COLUMN $kolom $definisi");
        }
    }
    if ($mysql) {
        $db->exec("ALTER TABLE tagihan MODIFY status ENUM('Belum Dibayar','Sebagian','Dibayar','Terlambat','Dibatalkan') NOT NULL DEFAULT 'Belum Dibayar'");

        // Samakan collation database dan seluruh tabel agar JOIN antarkolom teks tidak gagal.
        $namaDatabase = $db->query('SELECT DATABASE()')->fetchColumn();
        if ($namaDatabase) {
            $databaseAman = '`' . str_replace('`', '``', $namaDatabase) . '`';
            $db->exec("ALTER DATABASE {$databaseAman} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Konversi seluruh tabel sekaligus, termasuk tabel yang memiliki foreign key.
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $daftarTabel = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES
                                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")
                              ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($daftarTabel as $namaTabel) {
                $tabelAman = '`' . str_replace('`', '``', $namaTabel) . '`';
                $db->exec("ALTER TABLE {$tabelAman} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    $ada = function ($tabel) use ($db) {
        return (int) $db->query("SELECT COUNT(*) FROM $tabel")->fetchColumn() > 0;
    };

    if (!$ada('prodi')) {
        $st = $db->prepare('INSERT INTO prodi (nama, jenjang) VALUES (?, ?)');
        foreach (['Teknik Informatika', 'Sistem Informasi', 'Jaringan Komputer'] as $p) {
            $st->execute([$p, 'S1']);
        }
    }

    if (!$ada('tahun_akademik')) {
        $y = (int) date('Y');
        $m = (int) date('n');
        if ($m >= 8) {
            $nama = $y . '/' . ($y + 1);
            $sem = 'Ganjil';
        } elseif ($m >= 2) {
            $nama = ($y - 1) . '/' . $y;
            $sem = 'Genap';
        } else {
            $nama = ($y - 1) . '/' . $y;
            $sem = 'Ganjil';
        }
        $db->prepare('INSERT INTO tahun_akademik (nama, semester, aktif) VALUES (?, ?, 1)')->execute([$nama, $sem]);
    }

    // Data dosen untuk akun awal "dosen" agar bisa langsung dipakai.
    $akunDosen = $db->query("SELECT id, nama, prodi FROM akun_pengguna WHERE role = 'dosen' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($akunDosen as $a) {
        $sudah = $db->prepare('SELECT id FROM dosen WHERE akun_id = ?');
        $sudah->execute([$a['id']]);
        if (!$sudah->fetch()) {
            $db->prepare('INSERT INTO dosen (nip, nama, prodi, status, akun_id) VALUES (?, ?, ?, ?, ?)')
               ->execute(['D' . str_pad((string) $a['id'], 4, '0', STR_PAD_LEFT), $a['nama'], $a['prodi'], 'Aktif', $a['id']]);
        }
    }

    // Dua kelas contoh untuk dosen awal supaya presensi/nilai/materi langsung bisa dicoba.
    if (!$ada('kelas_kuliah')) {
        $d = $db->query('SELECT id FROM dosen ORDER BY id LIMIT 1')->fetchColumn();
        $ta = $db->query('SELECT nama FROM tahun_akademik WHERE aktif = 1 LIMIT 1')->fetchColumn();
        if ($d && $ta) {
            $st = $db->prepare("INSERT INTO kelas_kuliah (kode_mk, dosen_id, tahun_akademik, hari, jam_mulai, jam_selesai, ruang, status, diubah_oleh)
                                VALUES (?, ?, ?, ?, ?, ?, ?, 'Disetujui', 'sistem')");
            foreach ([['IF101', 'Senin', '08:00', '10:30', 'Lab 1'], ['IF103', 'Rabu', '10:00', '12:30', 'R. 201']] as $k) {
                $ada_mk = $db->prepare('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?');
                $ada_mk->execute([$k[0]]);
                if ($ada_mk->fetch()) {
                    $st->execute([$k[0], $d, $ta, $k[1], $k[2], $k[3], $k[4]]);
                }
            }
        }
    }

    if (!$ada('pengumuman')) {
        $db->prepare('INSERT INTO pengumuman (judul, isi, prodi, penulis, peran_penulis, dibuat) VALUES (?, ?, NULL, ?, ?, ?)')
           ->execute(['Selamat datang di SIAKAD', 'Pengumuman resmi kampus akan tampil di halaman ini.', 'Sistem', 'admin', date('Y-m-d H:i:s')]);
    }

    $db->exec("DELETE FROM pengaturan WHERE kunci = 'skema_versi'");
    $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('skema_versi', ?)")->execute([SKEMA_VERSI]);
    foreach (['krs_wajib_lunas' => '0', 'ujian_wajib_lunas' => '0'] as $k => $v) {
        $c = $db->prepare('SELECT kunci FROM pengaturan WHERE kunci = ?');
        $c->execute([$k]);
        if (!$c->fetch()) {
            $db->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)')->execute([$k, $v]);
        }
    }
}
