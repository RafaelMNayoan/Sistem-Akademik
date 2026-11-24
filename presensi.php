<?php
require_once '../koneksi.php';
$user = wajib_mahasiswa();
$nim = $user['nim'];

$matkul = mk_diambil($nim, $user['semester']);
$kodeKrs = array_column($matkul, 'kode_mk');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sesiId = (int) post('sesi_id');
    $status = post('status');

    $sesi = ambil_satu('SELECT * FROM sesi_presensi WHERE id = ?', [$sesiId]);

    if (!$sesi) {
        pesan('Sesi presensi tidak ditemukan.', 'gagal');
    } elseif ($sesi['status'] !== 'Buka') {
        pesan('Presensi untuk sesi ini sudah ditutup admin.', 'gagal');
    } elseif (!in_array($sesi['kode_mk'], $kodeKrs)) {
        pesan('Mata kuliah ini tidak ada di KRS Anda.', 'gagal');
    } elseif (!in_array($status, ['Hadir', 'Izin', 'Sakit'])) {
        pesan('Status presensi tidak valid.', 'gagal');
    } else {
        jalankan('INSERT INTO presensi (sesi_id, nim, jam_masuk, status, catatan) VALUES (?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE jam_masuk = VALUES(jam_masuk), status = VALUES(status), catatan = VALUES(catatan)',
                 [$sesiId, $nim, date('H:i:s'), $status, post('catatan')]);
        pesan('Presensi tersimpan.');
    }
    pindah('presensi.php');
}

$sesiBuka = ambil_semua("SELECT s.id, s.pertemuan, s.tanggal, m.nama_mk, p.status AS status_saya, p.catatan
        FROM sesi_presensi s
        JOIN mata_kuliah m ON m.kode_mk = s.kode_mk
        JOIN krs k ON k.kode_mk = s.kode_mk AND k.nim = ? AND k.semester = ?
        LEFT JOIN presensi p ON p.sesi_id = s.id AND p.nim = ?
        WHERE s.status = 'Buka'
        ORDER BY s.tanggal DESC", [$nim, $user['semester'], $nim]);

$filter = get('filter');
$riwayat = ambil_semua("SELECT p.*, s.tanggal, s.pertemuan, m.nama_mk
        FROM presensi p
        JOIN sesi_presensi s ON s.id = p.sesi_id
        JOIN mata_kuliah m ON m.kode_mk = s.kode_mk
        WHERE p.nim = ? AND (? = '' OR s.kode_mk = ?)
        ORDER BY s.tanggal DESC, p.jam_masuk DESC LIMIT 50", [$nim, $filter, $filter]);

$judul = 'Presensi';
include '../includes/header.php';
?>
<h1>Presensi Kuliah</h1>

<div class="kartu">
    <h2>Presensi yang sedang dibuka</h2>
    <?php if (!$sesiBuka): ?>
        <p class="kosong">Tidak ada presensi yang dibuka untuk mata kuliah Anda saat ini.</p>
    <?php else: ?>
        <?php foreach ($sesiBuka as $s): ?>
            <div class="item">
                <h3><?= e($s['nama_mk']) ?> - pertemuan <?= e($s['pertemuan']) ?></h3>
                <p class="keterangan">Tanggal <?= tanggal($s['tanggal']) ?></p>
                <form method="post" class="filter">
                    <input type="hidden" name="sesi_id" value="<?= $s['id'] ?>">
                    <div>
                        <label>Status</label>
                        <select name="status"><?= opsi(sama(['Hadir', 'Izin', 'Sakit']), $s['status_saya'] ?? 'Hadir') ?></select>
                    </div>
                    <div>
                        <label>Catatan (boleh kosong)</label>
                        <input type="text" name="catatan" value="<?= e($s['catatan'] ?? '') ?>">
                    </div>
                    <button type="submit" class="tombol"><?= $s['status_saya'] ? 'Perbarui presensi' : 'Simpan presensi' ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="kartu">
    <h2>Riwayat presensi</h2>
    <form method="get" class="filter">
        <div>
            <label for="filter">Mata kuliah</label>
            <select id="filter" name="filter">
                <option value="">Semua</option>
                <?php foreach ($matkul as $mk): ?>
                    <option value="<?= e($mk['kode_mk']) ?>" <?= $filter === $mk['kode_mk'] ? 'selected' : '' ?>><?= e($mk['nama_mk']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>

    <?php if (!$riwayat): ?>
        <p class="kosong">Belum ada data presensi.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Tanggal</th><th>Mata kuliah</th><th>Pertemuan</th><th>Jam</th><th>Status</th><th>Catatan</th></tr>
                <?php foreach ($riwayat as $r): ?>
                    <?php $warna = $r['status'] === 'Hadir' ? 'hijau' : ($r['status'] === 'Alpha' ? 'merah' : 'kuning'); ?>
                    <tr>
                        <td><?= tanggal($r['tanggal']) ?></td>
                        <td><?= e($r['nama_mk']) ?></td>
                        <td><?= e($r['pertemuan']) ?></td>
                        <td><?= $r['jam_masuk'] ? e(substr($r['jam_masuk'], 0, 5)) : '-' ?></td>
                        <td><span class="lencana <?= $warna ?>"><?= e($r['status']) ?></span></td>
                        <td><?= e($r['catatan']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
