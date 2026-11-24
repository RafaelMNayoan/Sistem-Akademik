<?php
require_once '../koneksi.php';
/* Cetak surat, transkrip, kartu ujian, dan kuitansi. Mahasiswa hanya boleh mencetak surat miliknya. */
$role = $_SESSION['role'] ?? '';
$jenis = get('jenis');
if ($role === 'mahasiswa') {
    $mhsLogin = wajib_mahasiswa();
    $tampil = null;
} else {
    $user = wajib_portal();
}

function halaman_cetak($judul, $isi)
{
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>' . e($judul) . '</title><link rel="stylesheet" href="../assets/style.css">
    <style>body{background:#fff}.kertas{max-width:760px;margin:20px auto;padding:20px;color:#000}.kertas h2{text-align:center;margin:0}.kertas .kop{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:18px}
    .kertas table{border-collapse:collapse}.kertas .garis th,.kertas .garis td{border:1px solid #222;padding:6px}.ttd{margin-top:50px;text-align:right}.ttd div{display:inline-block;text-align:center;min-width:220px}
    @media print{.tanpa-cetak{display:none}}</style></head><body><div class="kertas"><p class="tanpa-cetak"><button class="tombol" onclick="window.print()">Cetak</button></p>
    <div class="kop"><h2>STMIK PESAT NABIRE</h2><div>Sistem Informasi Akademik</div></div>' . $isi . '</div></body></html>';
    exit;
}
function tolak_cetak($teks)
{
    halaman_cetak('Tidak dapat dicetak', '<div class="pesan gagal">' . e($teks) . '</div>');
}

if ($jenis === 'surat') {
    $s = ambil_satu('SELECT * FROM surat WHERE id = ?', [(int) get('id')]);
    if (!$s || !in_array($s['status'], ['Disetujui', 'Selesai'], true)) tolak_cetak('Surat belum disetujui atau tidak ditemukan.');
    if ($role === 'mahasiswa') {
        if ($s['pemohon_tipe'] !== 'mahasiswa' || $s['pemohon_ref'] !== $mhsLogin['nim']) tolak_cetak('Surat ini bukan milik Anda.');
    } elseif ($role === 'dosen') {
        if ($s['pemohon_tipe'] !== 'dosen' || $s['pemohon_ref'] !== (string) $user['id']) tolak_cetak('Surat ini bukan milik Anda.');
    } elseif (!in_array($role, ['admin', 'staff', 'kaprodi'], true) || ($role === 'kaprodi' && $s['prodi'] !== prodi_batas())) {
        tolak_cetak('Anda tidak berhak mencetak surat ini.');
    }
    $kaprodi = ambil_satu("SELECT nama FROM akun_pengguna WHERE role = 'kaprodi' AND status = 'Aktif' ORDER BY (prodi = ?) DESC, nama LIMIT 1", [$s['prodi'] ?? '']);
    halaman_cetak($s['jenis'], '<h3 style="text-align:center;text-decoration:underline;margin:0">' . strtoupper(e($s['jenis'])) . '</h3><p style="text-align:center">Nomor: ' . e($s['nomor_surat']) . '</p>
    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p><table><tr><td width="140">Nama</td><td>: ' . e($s['pemohon_nama']) . '</td></tr>
    <tr><td>' . ($s['pemohon_tipe'] === 'mahasiswa' ? 'NIM' : 'Jabatan') . '</td><td>: ' . e($s['pemohon_tipe'] === 'mahasiswa' ? $s['pemohon_ref'] : 'Dosen') . '</td></tr>
    <tr><td>Program Studi</td><td>: ' . e($s['prodi'] ?: '-') . '</td></tr><tr><td>Keperluan</td><td>: ' . e($s['keperluan']) . '</td></tr></table>
    <p>Demikian surat ini dibuat untuk dipergunakan sebagaimana mestinya.</p><div class="ttd"><div>Nabire, ' . date('d/m/Y') . '<br>Ketua Program Studi<br><br><br><br><strong><u>' . e($kaprodi['nama'] ?? '________________') . '</u></strong></div></div>');
}

if ($role === 'mahasiswa') { tolak_cetak('Dokumen ini hanya dapat dicetak oleh petugas.'); }
$bp = prodi_batas();

if ($jenis === 'transkrip' || $jenis === 'kartu_ujian') {
    if (!in_array($role, ['admin', 'staff', 'kaprodi'], true)) tolak_cetak('Anda tidak berhak mencetak dokumen ini.');
    $m = ambil_satu('SELECT * FROM mahasiswa WHERE nim = ?', [get('nim')]);
    if (!$m || ($bp !== null && $m['program_studi'] !== $bp)) tolak_cetak('Mahasiswa tidak ditemukan pada cakupan Anda.');
    if ($jenis === 'kartu_ujian') {
        if (ambil_pengaturan('ujian_wajib_lunas') === '1' && jumlah_tunggakan($m['nim']) > 0) tolak_cetak('Kartu ujian tidak dapat dicetak: mahasiswa masih memiliki tagihan belum lunas.');
        $mk = mk_diambil($m['nim'], $m['semester']);
        $isi = '<h3 style="text-align:center">KARTU UJIAN</h3><table><tr><td width="140">NIM</td><td>: ' . e($m['nim']) . '</td></tr><tr><td>Nama</td><td>: ' . e($m['nama_lengkap']) . '</td></tr><tr><td>Program Studi</td><td>: ' . e($m['program_studi']) . '</td></tr><tr><td>Semester</td><td>: ' . e($m['semester']) . ' (' . e(tahun_aktif()) . ')</td></tr></table>
        <table class="garis" style="width:100%;margin-top:12px"><tr><th>No</th><th>Kode</th><th>Mata kuliah</th><th>SKS</th></tr>';
        foreach ($mk as $i => $x) $isi .= '<tr><td>' . ($i + 1) . '</td><td>' . e($x['kode_mk']) . '</td><td>' . e($x['nama_mk']) . '</td><td>' . (int) $x['sks'] . '</td></tr>';
        halaman_cetak('Kartu Ujian', $isi . '</table>');
    }
    $nilai = ambil_semua('SELECT n.huruf_grade, m.kode_mk, m.nama_mk, m.sks FROM nilai n JOIN mata_kuliah m ON m.kode_mk = n.kode_mk WHERE n.nim = ? ORDER BY m.kode_mk', [$m['nim']]);
    $isi = '<h3 style="text-align:center">TRANSKRIP NILAI</h3><table><tr><td width="140">NIM</td><td>: ' . e($m['nim']) . '</td></tr><tr><td>Nama</td><td>: ' . e($m['nama_lengkap']) . '</td></tr><tr><td>Program Studi</td><td>: ' . e($m['program_studi']) . '</td></tr></table>
    <table class="garis" style="width:100%;margin-top:12px"><tr><th>No</th><th>Kode</th><th>Mata kuliah</th><th>SKS</th><th>Nilai</th></tr>';
    foreach ($nilai as $i => $x) $isi .= '<tr><td>' . ($i + 1) . '</td><td>' . e($x['kode_mk']) . '</td><td>' . e($x['nama_mk']) . '</td><td>' . (int) $x['sks'] . '</td><td>' . e($x['huruf_grade']) . '</td></tr>';
    halaman_cetak('Transkrip', $isi . '</table><p><strong>IPK: ' . number_format(hitung_ipk($m['nim']), 2) . '</strong></p>');
}

if ($jenis === 'kuitansi') {
    if (!in_array($role, ['admin', 'bendahara'], true)) tolak_cetak('Anda tidak berhak mencetak kuitansi.');
    $p = ambil_satu("SELECT p.*, t.nama_tagihan, t.kode_tagihan, t.nim, mh.nama_lengkap FROM pembayaran p JOIN tagihan t ON t.id = p.tagihan_id JOIN mahasiswa mh ON mh.nim = t.nim WHERE p.id = ? AND p.status = 'Terverifikasi'", [(int) get('id')]);
    if (!$p) tolak_cetak('Pembayaran belum terverifikasi atau tidak ditemukan.');
    halaman_cetak('Kuitansi', '<h3 style="text-align:center">KUITANSI PEMBAYARAN</h3><table><tr><td width="160">No. kuitansi</td><td>: KW-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT) . '</td></tr><tr><td>Telah terima dari</td><td>: ' . e($p['nim']) . ' - ' . e($p['nama_lengkap']) . '</td></tr>
    <tr><td>Untuk pembayaran</td><td>: ' . e($p['nama_tagihan']) . ' (' . e($p['kode_tagihan']) . ')</td></tr><tr><td>Jumlah</td><td>: <strong>' . rupiah($p['jumlah']) . '</strong></td></tr><tr><td>Metode</td><td>: ' . e($p['metode']) . '</td></tr><tr><td>Tanggal</td><td>: ' . tanggal($p['tanggal']) . '</td></tr></table>
    <div class="ttd"><div>Nabire, ' . tanggal($p['tanggal']) . '<br>Bendahara<br><br><br><br><strong><u>' . e($p['diverifikasi_oleh'] ?: '________________') . '</u></strong></div></div>');
}
tolak_cetak('Jenis dokumen tidak dikenal.');
