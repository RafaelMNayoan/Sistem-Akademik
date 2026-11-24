<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'buka_sesi') {
        $kode = post('kode_mk');
        $pertemuan = (int) post('pertemuan');
        $tgl = post('tanggal');
        if (!ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode]) || $pertemuan < 1 || !strtotime($tgl)) {
            pesan('Pilih mata kuliah, isi pertemuan (angka) dan tanggal dengan benar.', 'gagal');
        } else {
            jalankan("INSERT INTO sesi_presensi (kode_mk, pertemuan, tanggal, status) VALUES (?, ?, ?, 'Buka')",
                     [$kode, $pertemuan, $tgl]);
            pesan('Sesi presensi dibuka. Mahasiswa sudah bisa mengisi presensi.');
        }
        pindah('presensi.php');
    }

    if ($aksi === 'saklar') {
        jalankan("UPDATE sesi_presensi SET status = IF(status = 'Buka', 'Tutup', 'Buka') WHERE id = ?", [(int) post('id')]);
        pesan('Status sesi presensi diubah.');
        pindah('presensi.php');
    }

    if ($aksi === 'hapus_sesi') {
        jalankan('DELETE FROM sesi_presensi WHERE id = ?', [(int) post('id')]);
        pesan('Sesi presensi dihapus beserta catatan kehadirannya.');
        pindah('presensi.php');
    }

    if ($aksi === 'ubah_status') {
        $sesiId = (int) post('sesi_id');
        $nim = post('nim');
        $status = post('status');
        if ($status === '') {
            jalankan('DELETE FROM presensi WHERE sesi_id = ? AND nim = ?', [$sesiId, $nim]);
        } elseif (in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpha'])) {
            jalankan('INSERT INTO presensi (sesi_id, nim, jam_masuk, status) VALUES (?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE status = VALUES(status)', [$sesiId, $nim, date('H:i:s'), $status]);
        }
        pesan('Kehadiran diperbarui.');
        pindah('presensi.php?sesi=' . $sesiId);
    }
}

$sesiDipilih = get('sesi') !== '' ? ambil_satu('SELECT s.*, m.nama_mk FROM sesi_presensi s
                                                 JOIN mata_kuliah m ON m.kode_mk = s.kode_mk WHERE s.id = ?', [(int) get('sesi')]) : null;
$rekap = [];
if ($sesiDipilih) {
    $rekap = ambil_semua('SELECT mh.nim, mh.nama_lengkap, p.status, p.jam_masuk, p.catatan
                          FROM krs k
                          JOIN mahasiswa mh ON mh.nim = k.nim
                          LEFT JOIN presensi p ON p.nim = k.nim AND p.sesi_id = ?
                          WHERE k.kode_mk = ?
                          GROUP BY mh.nim, mh.nama_lengkap, p.status, p.jam_masuk, p.catatan
                          ORDER BY mh.nama_lengkap', [$sesiDipilih['id'], $sesiDipilih['kode_mk']]);
}

$daftar = ambil_semua("SELECT s.*, m.nama_mk,
        (SELECT COUNT(*) FROM krs k WHERE k.kode_mk = s.kode_mk) AS peserta,
        (SELECT COUNT(*) FROM presensi p WHERE p.sesi_id = s.id AND p.status = 'Hadir') AS hadir
        FROM sesi_presensi s JOIN mata_kuliah m ON m.kode_mk = s.kode_mk
        ORDER BY s.tanggal DESC, s.id DESC LIMIT 100");

$judul = 'Buka/Tutup Presensi';
include '../includes/header.php';
?>
<h1>Buka / Tutup Presensi</h1>

<?php if ($sesiDipilih): ?>
<div class="kartu">
    <h2>Rekap: <?= e($sesiDipilih['nama_mk']) ?> - pertemuan <?= e($sesiDipilih['pertemuan']) ?> (<?= tanggal($sesiDipilih['tanggal']) ?>)</h2>
    <p>Status sesi:
        <span class="lencana <?= $sesiDipilih['status'] === 'Buka' ? 'hijau' : 'abu' ?>"><?= e($sesiDipilih['status']) ?></span>
        <a href="presensi.php" class="tombol abu kecil">Kembali</a>
    </p>
    <?php if (!$rekap): ?>
        <p class="kosong">Belum ada mahasiswa yang mengambil mata kuliah ini di KRS.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>NIM</th><th>Nama</th><th>Jam</th><th>Catatan</th><th>Ubah kehadiran</th></tr>
                <?php foreach ($rekap as $r): ?>
                    <tr>
                        <td><?= e($r['nim']) ?></td>
                        <td><?= e($r['nama_lengkap']) ?></td>
                        <td><?= $r['jam_masuk'] ? e(substr($r['jam_masuk'], 0, 5)) : '-' ?></td>
                        <td><?= e($r['catatan']) ?></td>
                        <td>
                            <form method="post" class="aksi">
                                <input type="hidden" name="aksi" value="ubah_status">
                                <input type="hidden" name="sesi_id" value="<?= $sesiDipilih['id'] ?>">
                                <input type="hidden" name="nim" value="<?= e($r['nim']) ?>">
                                <select name="status">
                                    <option value="">Belum isi</option>
                                    <?= opsi(sama(['Hadir', 'Izin', 'Sakit', 'Alpha']), $r['status'] ?? '') ?>
                                </select>
                                <button type="submit" class="tombol kecil">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="kartu">
    <h2>Buka sesi presensi baru</h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="buka_sesi">
        <div>
            <label for="kode_mk">Mata kuliah</label>
            <select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul()) ?></select>
        </div>
        <div>
            <label for="pertemuan">Pertemuan ke-</label>
            <input type="number" id="pertemuan" name="pertemuan" min="1" max="30" value="1" required>
        </div>
        <div>
            <label for="tanggal">Tanggal</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="penuh">
            <button type="submit" class="tombol hijau">Buka presensi</button>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar sesi presensi</h2>
    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada sesi presensi. Buat sesi baru di atas.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Tanggal</th><th>Mata kuliah</th><th>Pertemuan</th><th>Hadir</th><th>Status</th><th></th></tr>
                <?php foreach ($daftar as $s): ?>
                    <tr>
                        <td><?= tanggal($s['tanggal']) ?></td>
                        <td><?= e($s['nama_mk']) ?></td>
                        <td><?= e($s['pertemuan']) ?></td>
                        <td><?= $s['hadir'] ?> / <?= $s['peserta'] ?></td>
                        <td><span class="lencana <?= $s['status'] === 'Buka' ? 'hijau' : 'abu' ?>"><?= e($s['status']) ?></span></td>
                        <td>
                            <div class="aksi">
                                <form method="post">
                                    <input type="hidden" name="aksi" value="saklar">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <?php if ($s['status'] === 'Buka'): ?>
                                        <button type="submit" class="tombol merah kecil">Tutup</button>
                                    <?php else: ?>
                                        <button type="submit" class="tombol hijau kecil">Buka lagi</button>
                                    <?php endif; ?>
                                </form>
                                <a href="?sesi=<?= $s['id'] ?>" class="tombol kecil">Rekap</a>
                                <form method="post" onsubmit="return confirm('Hapus sesi ini beserta catatan kehadirannya?')">
                                    <input type="hidden" name="aksi" value="hapus_sesi">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="tombol abu kecil">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
