<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM tagihan WHERE id = ?', [(int) $_POST['hapus']]);
        pesan('Tagihan dihapus.');
        pindah('tagihan.php');
    }

    if (isset($_POST['lunas'])) {
        jalankan("UPDATE tagihan SET status = 'Dibayar', tanggal_bayar = ? WHERE id = ?", [date('Y-m-d'), (int) $_POST['lunas']]);
        pesan('Tagihan ditandai lunas.');
        pindah('tagihan.php');
    }

    $id = (int) post('id');
    $nim = post('nim');
    $semester = (int) post('semester');
    $kode = post('kode_tagihan');
    $nama = post('nama_tagihan');
    $biaya = (float) post('biaya');
    $potongan = (float) post('potongan');
    $jatuhTempo = post('tanggal_jatuh_tempo');
    $tglBayar = post('tanggal_bayar') !== '' ? post('tanggal_bayar') : null;
    $status = post('status');

    if (!ambil_satu('SELECT nim FROM mahasiswa WHERE nim = ?', [$nim]) || $kode === '' || $nama === ''
        || $semester < 1 || $semester > 14 || !strtotime($jatuhTempo)
        || !in_array($status, ['Belum Dibayar', 'Dibayar', 'Terlambat'])) {
        pesan('Mahasiswa, semester, kode, nama, jatuh tempo, dan status harus diisi dengan benar.', 'gagal');
    } elseif ($biaya < 0 || $potongan < 0 || $potongan > $biaya) {
        pesan('Biaya tidak boleh minus dan potongan tidak boleh melebihi biaya.', 'gagal');
    } else {
        $total = $biaya - $potongan;
        try {
            if ($id) {
                jalankan('UPDATE tagihan SET nim = ?, semester = ?, kode_tagihan = ?, nama_tagihan = ?, biaya = ?,
                          potongan = ?, total_bayar = ?, tanggal_jatuh_tempo = ?, tanggal_bayar = ?, status = ? WHERE id = ?',
                         [$nim, $semester, $kode, $nama, $biaya, $potongan, $total, $jatuhTempo, $tglBayar, $status, $id]);
                pesan('Tagihan diperbarui.');
            } else {
                jalankan('INSERT INTO tagihan (nim, semester, kode_tagihan, nama_tagihan, biaya, potongan, total_bayar,
                          tanggal_jatuh_tempo, tanggal_bayar, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                         [$nim, $semester, $kode, $nama, $biaya, $potongan, $total, $jatuhTempo, $tglBayar, $status]);
                pesan('Tagihan ditambahkan.');
            }
        } catch (PDOException $ex) {
            pesan('Kode tagihan sudah dipakai. Gunakan kode lain.', 'gagal');
        }
    }
    pindah('tagihan.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM tagihan WHERE id = ?', [(int) get('edit')]) : null;

$filter = get('nim');
$daftar = ambil_semua("SELECT t.*, mh.nama_lengkap FROM tagihan t JOIN mahasiswa mh ON mh.nim = t.nim
        WHERE (? = '' OR t.nim = ?)
        ORDER BY t.id DESC", [$filter, $filter]);

$judul = 'Kelola Tagihan';
include '../includes/header.php';
?>
<h1>Kelola Tagihan</h1>

<div class="kartu">
    <h2><?= $edit ? 'Ubah tagihan' : 'Tambah tagihan' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div>
            <label for="nim">Mahasiswa</label>
            <select id="nim" name="nim" required><?= opsi(daftar_mahasiswa(), $edit['nim'] ?? '') ?></select>
        </div>
        <div>
            <label for="semester">Semester</label>
            <input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester'] ?? 1) ?>" required>
        </div>
        <div>
            <label for="kode_tagihan">Kode tagihan</label>
            <input type="text" id="kode_tagihan" name="kode_tagihan" value="<?= e($edit['kode_tagihan'] ?? 'TGH-' . date('ymdHis')) ?>" required>
        </div>
        <div>
            <label for="nama_tagihan">Nama tagihan</label>
            <input type="text" id="nama_tagihan" name="nama_tagihan" placeholder="SPP Semester 1" value="<?= e($edit['nama_tagihan'] ?? '') ?>" required>
        </div>
        <div>
            <label for="biaya">Biaya (Rp)</label>
            <input type="number" id="biaya" name="biaya" min="0" step="1000" value="<?= e($edit['biaya'] ?? '') ?>" required>
        </div>
        <div>
            <label for="potongan">Potongan (Rp)</label>
            <input type="number" id="potongan" name="potongan" min="0" step="1000" value="<?= e($edit['potongan'] ?? 0) ?>" required>
        </div>
        <div>
            <label for="tanggal_jatuh_tempo">Jatuh tempo</label>
            <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" value="<?= e($edit['tanggal_jatuh_tempo'] ?? '') ?>" required>
        </div>
        <div>
            <label for="tanggal_bayar">Tanggal bayar (kosongkan jika belum)</label>
            <input type="date" id="tanggal_bayar" name="tanggal_bayar" value="<?= e($edit['tanggal_bayar'] ?? '') ?>">
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status"><?= opsi(sama(['Belum Dibayar', 'Dibayar', 'Terlambat']), $edit['status'] ?? 'Belum Dibayar') ?></select>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah tagihan' ?></button>
            <?php if ($edit): ?><a href="tagihan.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar tagihan</h2>
    <form method="get" class="filter">
        <div>
            <label for="saring">Saring mahasiswa</label>
            <select id="saring" name="nim"><option value="">Semua mahasiswa</option><?= opsi(daftar_mahasiswa(), $filter) ?></select>
        </div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>

    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada tagihan.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Mahasiswa</th><th>Sem</th><th>Tagihan</th><th class="angka">Total</th><th>Jatuh tempo</th><th>Status</th><th></th></tr>
                <?php foreach ($daftar as $t): ?>
                    <?php $warna = $t['status'] === 'Dibayar' ? 'hijau' : ($t['status'] === 'Terlambat' ? 'merah' : 'kuning'); ?>
                    <tr>
                        <td><?= e($t['nim']) ?> - <?= e($t['nama_lengkap']) ?></td>
                        <td><?= e($t['semester']) ?></td>
                        <td><?= e($t['nama_tagihan']) ?><br><span class="keterangan"><?= e($t['kode_tagihan']) ?></span></td>
                        <td class="angka"><?= rupiah($t['total_bayar']) ?></td>
                        <td><?= tanggal($t['tanggal_jatuh_tempo']) ?></td>
                        <td><span class="lencana <?= $warna ?>"><?= e($t['status']) ?></span></td>
                        <td>
                            <div class="aksi">
                                <?php if ($t['status'] !== 'Dibayar'): ?>
                                    <form method="post">
                                        <input type="hidden" name="lunas" value="<?= $t['id'] ?>">
                                        <button type="submit" class="tombol hijau kecil">Tandai lunas</button>
                                    </form>
                                <?php endif; ?>
                                <a href="?edit=<?= $t['id'] ?>" class="tombol kecil">Ubah</a>
                                <form method="post" onsubmit="return confirm('Hapus tagihan ini?')">
                                    <input type="hidden" name="hapus" value="<?= $t['id'] ?>">
                                    <button type="submit" class="tombol merah kecil">Hapus</button>
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
