<?php

date_default_timezone_set('Asia/Jayapura');

$durasiSession = 8 * 60 * 60;

ini_set('session.gc_maxlifetime', (string) $durasiSession);

session_set_cookie_params([
    'lifetime' => $durasiSession,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

ob_start();
error_reporting(E_ALL & ~E_DEPRECATED);
session_start();

try {
    $db = new PDO('mysql:host=localhost;dbname=siakad_db;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("CREATE TABLE IF NOT EXISTS akun_pengguna (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        nama VARCHAR(100) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('kaprodi','staff','bendahara','dosen') NOT NULL,
        prodi VARCHAR(100) NULL,
        status ENUM('Aktif','Non-Aktif') NOT NULL DEFAULT 'Aktif',
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Akun awal per peran dibuat sekali saja. Password disimpan dalam bentuk hash.
    $akunAwal = [
        ['kaprodi', 'Kaprodi', 'kaprodi123', 'kaprodi'],
        ['staff', 'Staff Akademik', 'staff123', 'staff'],
        ['bendahara', 'Bendahara', 'bendahara123', 'bendahara'],
        ['dosen', 'Dosen', 'dosen123', 'dosen'],
    ];
    $cekAkun = $db->prepare('SELECT id FROM akun_pengguna WHERE username = ? LIMIT 1');
    $buatAkun = $db->prepare('INSERT INTO akun_pengguna (username, nama, password, role, prodi, status) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($akunAwal as [$usernameAwal, $namaAwal, $passwordAwal, $roleAwal]) {
        $cekAkun->execute([$usernameAwal]);
        if (!$cekAkun->fetch()) {
            $buatAkun->execute([$usernameAwal, $namaAwal, password_hash($passwordAwal, PASSWORD_DEFAULT), $roleAwal, 'Teknik Informatika', 'Aktif']);
        }
    }
    // Tabel & data tambahan untuk modul Kaprodi, Staff, Bendahara, Dosen (dijalankan sekali).
    require_once __DIR__ . '/includes/skema.php';
    migrasi_skema($db);
    $daftarProdiDb = array_column($db->query('SELECT nama FROM prodi ORDER BY id')->fetchAll(), 'nama');
} catch (PDOException $e) {
    die('Tidak bisa terhubung ke database. Pastikan MySQL di XAMPP sudah jalan dan DB_SIAKAD.sql sudah diimpor.');
}

define('AGAMA', ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']);
define('PRODI', !empty($daftarProdiDb) ? $daftarProdiDb : ['Teknik Informatika', 'Sistem Informasi', 'Jaringan Komputer']);
define('JENJANG', ['S1', 'D3']);
define('STATUS_MHS', ['Aktif', 'Cuti', 'Non-Aktif']);
define('GRADE', ['A', 'B+', 'B', 'C+', 'C', 'D', 'E']);

function ambil_semua($sql, $param = [])
{
    global $db;
    $s = $db->prepare($sql);
    $s->execute($param);
    return $s->fetchAll();
}

function ambil_satu($sql, $param = [])
{
    global $db;
    $s = $db->prepare($sql);
    $s->execute($param);
    return $s->fetch() ?: null;
}

function jalankan($sql, $param = [])
{
    global $db;
    $s = $db->prepare($sql);
    $s->execute($param);
}

function e($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

function pindah($alamat)
{
    header('Location: ' . $alamat);
    exit;
}

function pesan($teks, $jenis = 'sukses')
{
    $_SESSION['pesan'] = [$jenis, $teks];
}

function post($nama)
{
    return trim($_POST[$nama] ?? '');
}

function get($nama)
{
    return trim($_GET[$nama] ?? '');
}

function opsi($daftar, $dipilih = '')
{
    $html = '';
    foreach ($daftar as $nilai => $tulisan) {
        $sel = ((string) $nilai === (string) $dipilih) ? ' selected' : '';
        $html .= '<option value="' . e($nilai) . '"' . $sel . '>' . e($tulisan) . '</option>';
    }
    return $html;
}

function sama($daftar)
{
    return array_combine($daftar, $daftar);
}

function daftar_matkul()
{
    $hasil = [];
    foreach (ambil_semua('SELECT kode_mk, nama_mk FROM mata_kuliah ORDER BY kode_mk') as $r) {
        $hasil[$r['kode_mk']] = $r['kode_mk'] . ' - ' . $r['nama_mk'];
    }
    return $hasil;
}

function daftar_mahasiswa()
{
    $hasil = [];
    foreach (ambil_semua('SELECT nim, nama_lengkap FROM mahasiswa ORDER BY nim') as $r) {
        $hasil[$r['nim']] = $r['nim'] . ' - ' . $r['nama_lengkap'];
    }
    return $hasil;
}

function rupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function tanggal($tgl)
{
    return $tgl ? date('d/m/Y', strtotime($tgl)) : '-';
}

function tanggal_jam($waktu)
{
    return $waktu ? date('d/m/Y H:i', strtotime($waktu)) : '-';
}

function untuk_input($waktu)
{
    return $waktu ? date('Y-m-d\TH:i', strtotime($waktu)) : '';
}

function wajib_mahasiswa()
{
    if (($_SESSION['role'] ?? '') !== 'mahasiswa' || empty($_SESSION['nim'])) {
        pindah('index.php');
    }
    $mhs = ambil_satu('SELECT * FROM mahasiswa WHERE nim = ?', [$_SESSION['nim']]);
    if (!$mhs) {
        session_destroy();
        pindah('index.php');
    }
    return $mhs;
}

function wajib_admin()
{
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['admin_id'])) {
        pindah('index.php');
    }
    $admin = ambil_satu('SELECT * FROM admin WHERE id = ?', [$_SESSION['admin_id']]);
    if (!$admin) {
        session_destroy();
        pindah('index.php');
    }
    return $admin;
}


/* Hak akses berbasis peran sesuai matriks Hak_Akses_Sistem_Kampus.xlsx. */
function matriks_hak_akses()
{
    return [
        'admin' => ['akun' => 'CRUD', 'konten' => 'CRUD', 'master' => 'CRUD', 'dosen' => 'CRUD', 'mahasiswa' => 'CRUD', 'kurikulum' => 'CRUD', 'jadwal' => 'CRUD', 'krs' => 'CRUD', 'presensi' => 'R', 'nilai' => 'R', 'materi' => 'R', 'skripsi' => 'R', 'surat' => 'CRUD', 'biaya' => 'R', 'tagihan' => 'R', 'pembayaran' => 'R', 'beasiswa' => 'R', 'laporan_keuangan' => 'R', 'laporan_akademik' => 'R (semua)', 'log_keuangan' => 'R', 'log_aktivitas' => 'R'],
        'kaprodi' => ['konten' => 'CRU (prodi)', 'master' => 'R', 'dosen' => 'R (prodi)', 'mahasiswa' => 'R (prodi)', 'kurikulum' => 'CRUA', 'jadwal' => 'RA', 'krs' => 'RA', 'presensi' => 'R', 'nilai' => 'R', 'materi' => 'R', 'skripsi' => 'RA', 'surat' => 'RA', 'biaya' => 'R', 'tagihan' => 'R (prodi)', 'beasiswa' => 'RA (usulan)', 'laporan_keuangan' => 'R (ringkasan prodi)', 'laporan_akademik' => 'R (prodi)'],
        'staff' => ['konten' => 'CRU', 'master' => 'RU', 'dosen' => 'CRU', 'mahasiswa' => 'CRU', 'kurikulum' => 'R', 'jadwal' => 'CRU', 'krs' => 'RU', 'presensi' => 'R', 'nilai' => 'R', 'skripsi' => 'R', 'surat' => 'CRU', 'tagihan' => 'R (status bayar)', 'beasiswa' => 'R', 'laporan_akademik' => 'R'],
        'bendahara' => ['mahasiswa' => 'R (identitas & status bayar)', 'krs' => 'R (status bayar)', 'biaya' => 'CRUD', 'tagihan' => 'CRUD', 'pembayaran' => 'CRUA', 'beasiswa' => 'CRUA', 'laporan_keuangan' => 'R (semua)', 'log_keuangan' => 'R'],
        'dosen' => ['konten' => 'R', 'master' => 'R', 'dosen' => 'R (diri sendiri)', 'mahasiswa' => 'R (kelas/bimbingan)', 'kurikulum' => 'R', 'jadwal' => 'R', 'krs' => 'RA (mhs bimbingan)', 'presensi' => 'CRU', 'nilai' => 'CRU (kelasnya)', 'materi' => 'CRUD', 'skripsi' => 'RUA (bimbingan)', 'surat' => 'CR', 'laporan_akademik' => 'R (kelasnya)'],
        'mahasiswa' => ['konten' => 'R', 'master' => 'R', 'mahasiswa' => 'R (diri sendiri)', 'kurikulum' => 'R', 'jadwal' => 'R', 'krs' => 'CR (diri sendiri)', 'presensi' => 'R (diri sendiri)', 'nilai' => 'R (diri sendiri)', 'materi' => 'R + kumpul tugas', 'skripsi' => 'CR (diri sendiri)', 'surat' => 'CR (pengajuan)', 'biaya' => 'R', 'tagihan' => 'R (diri sendiri)', 'beasiswa' => 'R (diri sendiri)'],
    ];
}

function nama_peran($role)
{
    $nama = ['admin' => 'Admin Website', 'kaprodi' => 'Kaprodi', 'staff' => 'Staff (Tata Usaha/Akademik)', 'bendahara' => 'Bendahara / Keuangan', 'dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'];
    return $nama[$role] ?? 'Pengguna';
}

function wajib_peran_baru()
{
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['kaprodi', 'staff', 'bendahara', 'dosen'], true) || empty($_SESSION['pengguna_id'])) {
        pindah('../index.php');
    }
    $user = ambil_satu('SELECT id, username, nama, role, prodi, status FROM akun_pengguna WHERE id = ?', [$_SESSION['pengguna_id']]);
    if (!$user || $user['status'] !== 'Aktif') {
        $_SESSION = [];
        session_destroy();
        pindah('../index.php');
    }
    return $user;
}

function cek_hak_modul($role, $modul)
{
    $matriks = matriks_hak_akses();
    return isset($matriks[$role][$modul]);
}

function nim_baru()
{
    $tahun = date('y');
    $baris = ambil_satu('SELECT MAX(nim) AS terakhir FROM mahasiswa WHERE nim LIKE ?', [$tahun . '%']);
    $urut = $baris['terakhir'] ? (int) substr($baris['terakhir'], 2) + 1 : 1;
    return $tahun . str_pad($urut, 3, '0', STR_PAD_LEFT);
}

function mk_diambil($nim, $semester)
{
    return ambil_semua('SELECT m.kode_mk, m.nama_mk, m.sks
                        FROM krs k JOIN mata_kuliah m ON m.kode_mk = k.kode_mk
                        WHERE k.nim = ? AND k.semester = ?
                        ORDER BY m.nama_mk', [$nim, $semester]);
}

function hitung_ipk($nim)
{
    $baris = ambil_satu("SELECT SUM(CASE n.huruf_grade
                            WHEN 'A' THEN 4 WHEN 'B+' THEN 3.5 WHEN 'B' THEN 3
                            WHEN 'C+' THEN 2.5 WHEN 'C' THEN 2 WHEN 'D' THEN 1 ELSE 0 END * m.sks)
                            / SUM(m.sks) AS ipk
                         FROM nilai n JOIN mata_kuliah m ON m.kode_mk = n.kode_mk
                         WHERE n.nim = ?", [$nim]);
    return (float) $baris['ipk'];
}


function simpan_upload($kolom)
{
    if (empty($_FILES[$kolom]['name'])) {
        return null;
    }

    if ($_FILES[$kolom]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $ekstensi = strtolower(
        pathinfo($_FILES[$kolom]['name'], PATHINFO_EXTENSION)
    );

    $ekstensiDiizinkan = [
        'pdf',
        'doc',
        'docx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'mp4',
        'zip'
    ];

    if (!in_array($ekstensi, $ekstensiDiizinkan, true)) {
        return false;
    }

    $namaAsli = pathinfo(
        $_FILES[$kolom]['name'],
        PATHINFO_FILENAME
    );

    $namaBersih = strtolower($namaAsli);

    $namaBersih = preg_replace('/\s+/', '-', $namaBersih);

    $namaBersih = preg_replace('/[^a-z0-9-]/', '', $namaBersih);

    $namaBersih = preg_replace('/-+/', '-', $namaBersih);

    $namaBersih = trim($namaBersih, '-');

    if ($namaBersih === '') {
        $namaBersih = 'dokumen';
    }

    $namaBersih = substr($namaBersih, 0, 80);

    $waktu = date('Ymd-His');
    $unik = bin2hex(random_bytes(4));

    $namaFile = $namaBersih . '-' . $waktu . '-' . $unik . '.' . $ekstensi;

    $folder = __DIR__ . '/uploads/';

    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    if (!move_uploaded_file(
        $_FILES[$kolom]['tmp_name'],
        $folder . $namaFile
    )) {
        return false;
    }

    return 'uploads/' . $namaFile;
}

require_once __DIR__ . '/includes/portal.php';
