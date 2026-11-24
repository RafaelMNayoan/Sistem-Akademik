<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM nilai WHERE id = ?', [(int) $_POST['hapus']]);
        pesan('Nilai dihapus.');
        pindah('nilai.php');
    }

    $nim = post('nim');
    $kode = post('kode_mk');
    $grade = post('huruf_grade');

    if (!ambil_satu('SELECT nim FROM mahasiswa WHERE nim = ?', [$nim])
        || !ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode])
        || !in_array($grade, GRADE)) {
        pesan('Pilih mahasiswa, mata kuliah, dan nilai huruf dengan benar.', 'gagal');
    } else {
        jalankan('INSERT INTO nilai (nim, kode_mk, huruf_grade) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE huruf_grade = VALUES(huruf_grade)', [$nim, $kode, $grade]);
        pesan('Nilai disimpan.');
    }
    pindah('nilai.php' . ($nim ? '?nim=' . urlencode($nim) : ''));
}

$filter = get('nim');
$daftar = ambil_semua("SELECT n.*, mh.nama_lengkap, m.nama_mk, m.sks FROM nilai n
        JOIN mahasiswa mh ON mh.nim = n.nim
        JOIN mata_kuliah m ON m.kode_mk = n.kode_mk
        WHERE (? = '' OR n.nim = ?)
        ORDER BY n.nim, n.kode_mk", [$filter, $filter]);

$judul = 'Nilai Mahasiswa';
include '../includes/header.php';
?>
<h1>Nilai Mahasiswa</h1>

<div class="kartu">
    <h2>Input nilai</h2>
    <form method="post" class="form-isi">
        <div>
            <label for="nim">Mahasiswa</label>
            <select id="nim" name="nim" required><?= opsi(daftar_mahasiswa(), $filter) ?></select>
        </div>
        <div>
            <label for="kode_mk">Mata kuliah</label>
            <select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul()) ?></select>
        </div>
        <div>
            <label for="huruf_grade">Nilai huruf</label>
            <select id="huruf_grade" name="huruf_grade"><?= opsi(sama(GRADE), 'A') ?></select>
        </div>
        <div class="penuh">
            <small>Kalau nilai mahasiswa untuk mata kuliah ini sudah ada, nilai lama akan diganti.</small>
        </div>
        <div class="penuh">
            <button type="submit" class="tombol">Simpan nilai</button>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar nilai</h2>
    <form method="get" class="filter">
        <div>
            <label for="saring">Saring mahasiswa</label>
            <select id="saring" name="nim"><option value="">Semua mahasiswa</option><?= opsi(daftar_mahasiswa(), $filter) ?></select>
        </div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>

    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada nilai.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Mahasiswa</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Nilai</th><th></th></tr>
                <?php foreach ($daftar as $n): ?>
                    <tr>
                        <td><?= e($n['nim']) ?> - <?= e($n['nama_lengkap']) ?></td>
                        <td><?= e($n['kode_mk']) ?> - <?= e($n['nama_mk']) ?></td>
                        <td class="angka"><?= $n['sks'] ?></td>
                        <td><span class="lencana biru"><?= e($n['huruf_grade']) ?></span></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Hapus nilai ini?')">
                                <input type="hidden" name="hapus" value="<?= $n['id'] ?>">
                                <button type="submit" class="tombol merah kecil">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php if ($filter !== ''): ?><p><strong>IPK mahasiswa ini: <?= number_format(hitung_ipk($filter), 2) ?></strong></p><?php endif; ?>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
