<?php

const ROLE_BARU = ['kaprodi', 'staff', 'bendahara', 'dosen'];
const HARI_KULIAH = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const JENIS_SURAT_MHS = ['Surat Keterangan Aktif Kuliah', 'Surat Cuti Akademik', 'Surat Pengantar Penelitian/PKL', 'Surat Keterangan Lulus', 'Surat Dispensasi'];
const JENIS_SURAT_DOSEN = ['Surat Tugas', 'Surat Keterangan Mengajar', 'Surat Izin/Dispensasi'];

const NAMA_MODUL = [
    'akun' => 'Manajemen Akun', 'konten' => 'Pengumuman', 'master' => 'Data Master', 'dosen' => 'Data Dosen',
    'mahasiswa' => 'Data Mahasiswa', 'kurikulum' => 'Kurikulum & Mata Kuliah', 'jadwal' => 'Jadwal Kuliah', 'krs' => 'KRS',
    'presensi' => 'Presensi', 'nilai' => 'Nilai', 'materi' => 'Materi & Tugas', 'skripsi' => 'Skripsi / Tugas Akhir',
    'surat' => 'Surat & Dokumen', 'biaya' => 'Komponen Biaya & Tarif', 'tagihan' => 'Tagihan Mahasiswa',
    'pembayaran' => 'Verifikasi Pembayaran', 'beasiswa' => 'Beasiswa & Keringanan', 'laporan_keuangan' => 'Laporan Keuangan',
    'laporan_akademik' => 'Laporan & Statistik Akademik', 'log_keuangan' => 'Log Transaksi Keuangan', 'log_aktivitas' => 'Log Aktivitas',
];

function huruf_izin($role, $modul)
{
    $teks = matriks_hak_akses()[$role][$modul] ?? '';
    $inti = trim(explode('(', $teks)[0]);
    preg_match_all('/[CRUDA]/', $inti, $cocok);
    $h = implode('', $cocok[0]);
    if (strpos($h, 'C') !== false && strpos($h, 'R') !== false && strpos($h, 'U') !== false && strpos($h, 'D') !== false && strpos($h, 'A') === false) {
        $h .= 'A';
    }
    return $h;
}

function bisa($huruf)
{
    global $IZIN;
    return strpos((string) $IZIN, $huruf) !== false;
}

function alamat_modul($role, $modul)
{
    $peta = [
        'akun' => 'admin/pengguna.php', 'konten' => 'portal/pengumuman.php', 'master' => 'portal/master.php',
        'dosen' => 'portal/dosen.php', 'mahasiswa' => 'portal/mahasiswa.php', 'kurikulum' => 'portal/matakuliah.php',
        'jadwal' => 'portal/jadwal.php', 'krs' => 'portal/krs.php', 'presensi' => 'portal/presensi.php',
        'nilai' => 'portal/nilai.php', 'materi' => 'portal/materi.php', 'skripsi' => 'portal/skripsi.php',
        'surat' => 'portal/surat.php', 'biaya' => 'portal/biaya.php', 'tagihan' => 'portal/tagihan.php',
        'pembayaran' => 'portal/pembayaran.php', 'beasiswa' => 'portal/beasiswa.php',
        'laporan_keuangan' => 'portal/laporan_keuangan.php', 'laporan_akademik' => 'portal/laporan_akademik.php',
        'log_keuangan' => 'portal/log.php?jenis=keuangan', 'log_aktivitas' => 'portal/log.php?jenis=umum',
    ];
    if ($role === 'admin') {
        $admin = ['mahasiswa' => 'admin/mahasiswa.php', 'kurikulum' => 'admin/matakuliah.php', 'krs' => 'admin/krs.php',
                  'presensi' => 'admin/presensi.php', 'nilai' => 'admin/nilai.php', 'tagihan' => 'admin/tagihan.php',
                  'materi' => 'admin/materi.php'];
        if (isset($admin[$modul])) {
            return '../' . $admin[$modul];
        }
    }
    return '../' . ($peta[$modul] ?? 'portal/dashboard.php');
}

function wajib_portal($modul = null)
{
    global $PENGGUNA, $IZIN;
    $role = $_SESSION['role'] ?? '';

    if ($role === 'admin' && !empty($_SESSION['admin_id'])) {
        $u = ambil_satu('SELECT id, username, nama FROM admin WHERE id = ?', [$_SESSION['admin_id']]);
        if (!$u) {
            $_SESSION = [];
            session_destroy();
            pindah('../index.php');
        }
        $u['role'] = 'admin';
        $u['prodi'] = null;
        $u['status'] = 'Aktif';
        $u['dosen_id'] = null;
    } elseif (in_array($role, ROLE_BARU, true) && !empty($_SESSION['pengguna_id'])) {
        $u = ambil_satu('SELECT id, username, nama, role, prodi, status FROM akun_pengguna WHERE id = ?', [$_SESSION['pengguna_id']]);
        if (!$u || $u['status'] !== 'Aktif' || $u['role'] !== $role) {
            $_SESSION = [];
            session_destroy();
            pindah('../index.php');
        }
        $d = ambil_satu('SELECT id FROM dosen WHERE akun_id = ?', [$u['id']]);
        $u['dosen_id'] = $d ? (int) $d['id'] : null;
    } else {
        pindah('../index.php');
    }

    $PENGGUNA = $u;
    $IZIN = '';
    if ($modul !== null) {
        if (!isset(matriks_hak_akses()[$u['role']][$modul])) {
            http_response_code(403);
            $judul = 'Akses ditolak';
            $user = $u;
            include __DIR__ . '/header.php';
            echo '<div class="pesan gagal">Peran ' . e(nama_peran($u['role'])) . ' tidak memiliki izin untuk modul ini.</div>';
            echo '<p><a href="dashboard.php">Kembali ke dashboard</a></p>';
            include __DIR__ . '/footer.php';
            exit;
        }
        $IZIN = huruf_izin($u['role'], $modul);
    }
    return $u;
}

function prodi_batas()
{
    global $PENGGUNA;
    if (($PENGGUNA['role'] ?? '') === 'kaprodi') {
        return trim((string) $PENGGUNA['prodi']) !== '' ? $PENGGUNA['prodi'] : '(belum diatur)';
    }
    return null;
}

function daftar_in($daftar)
{
    $daftar = array_values($daftar);
    return $daftar ? [implode(',', array_fill(0, count($daftar), '?')), $daftar] : ['NULL', []];
}

function mk_dosen()
{
    global $PENGGUNA;
    if (empty($PENGGUNA['dosen_id'])) {
        return [];
    }
    return array_column(ambil_semua("SELECT DISTINCT kode_mk FROM kelas_kuliah WHERE dosen_id = ? AND status = 'Disetujui'", [$PENGGUNA['dosen_id']]), 'kode_mk');
}

function mk_prodi($prodi)
{
    return array_column(ambil_semua('SELECT kode_mk FROM mata_kuliah WHERE prodi = ? OR prodi IS NULL', [$prodi]), 'kode_mk');
}

function cakupan_mk()
{
    global $PENGGUNA;
    $role = $PENGGUNA['role'] ?? '';
    if ($role === 'dosen') {
        return mk_dosen();
    }
    if ($role === 'kaprodi') {
        return mk_prodi(prodi_batas());
    }
    return null;
}

function tahun_aktif()
{
    $t = ambil_satu('SELECT nama, semester FROM tahun_akademik WHERE aktif = 1 ORDER BY id DESC LIMIT 1');
    return $t ? $t['nama'] . ' ' . $t['semester'] : '-';
}

function ambil_pengaturan($kunci, $bawaan = '0')
{
    $r = ambil_satu('SELECT nilai FROM pengaturan WHERE kunci = ?', [$kunci]);
    return $r ? $r['nilai'] : $bawaan;
}

function simpan_pengaturan($kunci, $nilai)
{
    if (ambil_satu('SELECT kunci FROM pengaturan WHERE kunci = ?', [$kunci])) {
        jalankan('UPDATE pengaturan SET nilai = ? WHERE kunci = ?', [$nilai, $kunci]);
    } else {
        jalankan('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)', [$kunci, $nilai]);
    }
}

function daftar_dosen($prodi = '')
{
    $hasil = [];
    foreach (ambil_semua("SELECT id, nip, nama FROM dosen WHERE status = 'Aktif' AND (? = '' OR prodi = ?) ORDER BY nama", [$prodi, $prodi]) as $r) {
        $hasil[$r['id']] = $r['nama'] . ' (' . $r['nip'] . ')';
    }
    return $hasil;
}

function jumlah_tunggakan($nim)
{
    $r = ambil_satu("SELECT COUNT(*) AS n FROM tagihan WHERE nim = ? AND status NOT IN ('Dibayar','Dibatalkan')", [$nim]);
    return (int) $r['n'];
}

function terbayar_tagihan($id)
{
    $r = ambil_satu("SELECT COALESCE(SUM(jumlah), 0) AS j FROM pembayaran WHERE tagihan_id = ? AND status = 'Terverifikasi'", [$id]);
    return (float) $r['j'];
}

function hitung_status_tagihan($id)
{
    $t = ambil_satu('SELECT * FROM tagihan WHERE id = ?', [$id]);
    if (!$t || $t['status'] === 'Dibatalkan') {
        return;
    }
    $semua = ambil_satu('SELECT COUNT(*) AS n FROM pembayaran WHERE tagihan_id = ?', [$id]);
    if ((int) $semua['n'] === 0 && $t['status'] === 'Dibayar') {
        return;
    }
    $bayar = terbayar_tagihan($id);
    $tgl = ambil_satu("SELECT MAX(tanggal) AS tgl FROM pembayaran WHERE tagihan_id = ? AND status = 'Terverifikasi'", [$id]);
    if ((float) $t['total_bayar'] <= 0 || $bayar >= (float) $t['total_bayar']) {
        $status = 'Dibayar';
        $tglBayar = $tgl['tgl'] ?: date('Y-m-d');
    } elseif ($bayar > 0) {
        $status = 'Sebagian';
        $tglBayar = null;
    } else {
        $status = $t['tanggal_jatuh_tempo'] < date('Y-m-d') ? 'Terlambat' : 'Belum Dibayar';
        $tglBayar = null;
    }
    jalankan('UPDATE tagihan SET status = ?, tanggal_bayar = ? WHERE id = ?', [$status, $tglBayar, $id]);
}

function perbarui_keterlambatan()
{
    jalankan("UPDATE tagihan SET status = 'Terlambat' WHERE status = 'Belum Dibayar' AND tanggal_jatuh_tempo < ?", [date('Y-m-d')]);
}

function catat_log($modul, $aksi, $detail = '', $jenis = 'umum')
{
    global $PENGGUNA;
    $role = $PENGGUNA['role'] ?? ($_SESSION['role'] ?? '-');
    $pengguna = $PENGGUNA['username'] ?? ($_SESSION['nim'] ?? '-');
    jalankan('INSERT INTO log_aktivitas (waktu, jenis, peran, pengguna, modul, aksi, detail) VALUES (?, ?, ?, ?, ?, ?, ?)',
             [date('Y-m-d H:i:s'), $jenis, $role, $pengguna, $modul, $aksi, $detail]);
}

function beda($lama, $baru)
{
    $o = [];
    foreach ($baru as $k => $v) {
        if ((string) ($lama[$k] ?? '') !== (string) $v) {
            $o[] = $k . ': ' . ($lama[$k] ?? '') . ' -> ' . $v;
        }
    }
    return $o ? implode('; ', $o) : 'tidak ada perubahan';
}

function lencana($teks)
{
    $hijau = ['Disetujui', 'Aktif', 'Dibayar', 'Terverifikasi', 'Hadir', 'Selesai', 'Buka', 'Lunas'];
    $kuning = ['Diajukan', 'Menunggu', 'Draft', 'Diusulkan', 'Sebagian', 'Diproses', 'Disetujui Prodi', 'Belum Dibayar', 'Cuti', 'Izin', 'Sakit', 'Belum lunas'];
    $merah = ['Ditolak', 'Terlambat', 'Dibatalkan', 'Dicabut', 'Alpha'];
    $biru = ['Layak Sidang'];
    $w = in_array($teks, $hijau, true) ? 'hijau' : (in_array($teks, $kuning, true) ? 'kuning' : (in_array($teks, $merah, true) ? 'merah' : (in_array($teks, $biru, true) ? 'biru' : 'abu')));
    return '<span class="lencana ' . $w . '">' . e($teks) . '</span>';
}

function kosong_ke_null($v)
{
    $v = trim((string) $v);
    return $v === '' ? null : $v;
}

function jam_valid($j)
{
    return (bool) preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $j);
}

function nilai_ke_angka($huruf)
{
    $p = ['A' => 4, 'B+' => 3.5, 'B' => 3, 'C+' => 2.5, 'C' => 2, 'D' => 1, 'E' => 0];
    return $p[$huruf] ?? 0;
}

function simpan_presensi($sesiId, $nim, $status, $catatan = null)
{
    $ada = ambil_satu('SELECT id FROM presensi WHERE sesi_id = ? AND nim = ?', [$sesiId, $nim]);
    if ($ada) {
        jalankan('UPDATE presensi SET status = ?, jam_masuk = ? WHERE id = ?', [$status, date('H:i:s'), $ada['id']]);
    } else {
        jalankan('INSERT INTO presensi (sesi_id, nim, jam_masuk, status, catatan) VALUES (?, ?, ?, ?, ?)', [$sesiId, $nim, date('H:i:s'), $status, $catatan]);
    }
}

function simpan_nilai($nim, $kode, $grade)
{
    $ada = ambil_satu('SELECT id, huruf_grade FROM nilai WHERE nim = ? AND kode_mk = ?', [$nim, $kode]);
    if ($ada) {
        jalankan('UPDATE nilai SET huruf_grade = ? WHERE id = ?', [$grade, $ada['id']]);
        return $ada['huruf_grade'];
    }
    jalankan('INSERT INTO nilai (nim, kode_mk, huruf_grade) VALUES (?, ?, ?)', [$nim, $kode, $grade]);
    return null;
}
