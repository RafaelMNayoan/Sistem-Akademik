<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM tugas_kuliah WHERE id = ?', [(int) $_POST['hapus']]);
        pesan('Tugas dihapus beserta jawaban mahasiswa.');
        pindah('tugas.php');
    }

    if (isset($_POST['beri_nilai'])) {
        $nilai = post('nilai');
        if ($nilai !== '' && (!is_numeric($nilai) || $nilai < 0 || $nilai > 100)) {
            pesan('Nilai harus angka 0 sampai 100.', 'gagal');
        } else {
            jalankan('UPDATE pengumpulan_tugas SET nilai = ? WHERE id = ?',
                     [$nilai === '' ? null : $nilai, (int) $_POST['beri_nilai']]);
            pesan('Nilai disimpan.');
        }
        pindah('tugas.php?lihat=' . (int) post('tugas_id'));
    }

    $id = (int) post('id');
    $kode = post('kode_mk');
    $nama = post('nama_tugas');
    $batas = str_replace('T', ' ', post('batas_waktu'));

    if (!ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode]) || $nama === '' || !strtotime($batas)) {
        pesan('Pilih mata kuliah, isi nama tugas, dan batas waktu.', 'gagal');
        pindah('tugas.php');
    }

    if ($id) {
        jalankan('UPDATE tugas_kuliah SET kode_mk = ?, nama_tugas = ?, deskripsi = ?, batas_waktu = ? WHERE id = ?',
                 [$kode, $nama, post('deskripsi'), $batas, $id]);
        pesan('Tugas diperbarui.');
    } else {
        jalankan('INSERT INTO tugas_kuliah (kode_mk, nama_tugas, deskripsi, batas_waktu) VALUES (?, ?, ?, ?)',
                 [$kode, $nama, post('deskripsi'), $batas]);
        pesan('Tugas ditambahkan.');
    }
    pindah('tugas.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM tugas_kuliah WHERE id = ?', [(int) get('edit')]) : null;

$lihat = get('lihat') !== '' ? ambil_satu('SELECT t.*, m.nama_mk FROM tugas_kuliah t
                                            JOIN mata_kuliah m ON m.kode_mk = t.kode_mk WHERE t.id = ?', [(int) get('lihat')]) : null;
$jawaban = [];
if ($lihat) {
    $jawaban = ambil_semua('SELECT p.*, mh.nama_lengkap FROM pengumpulan_tugas p
                            JOIN mahasiswa mh ON mh.nim = p.nim
                            WHERE p.tugas_id = ? ORDER BY p.waktu_kumpul', [$lihat['id']]);
}

$daftar = ambil_semua('SELECT t.*, m.nama_mk,
        (SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id = t.id) AS jumlah_jawaban
        FROM tugas_kuliah t JOIN mata_kuliah m ON m.kode_mk = t.kode_mk
        ORDER BY t.batas_waktu DESC');

$judul = 'Kelola Tugas';
include '../includes/header.php';
?>
<h1>Kelola Tugas Kuliah</h1>

<?php if ($lihat): ?>
<div class="kartu">
    <h2>Jawaban: <?= e($lihat['nama_tugas']) ?> (<?= e($lihat['nama_mk']) ?>)</h2>
    <p><a href="tugas.php" class="tombol abu kecil">Kembali</a></p>
    <?php if (!$jawaban): ?>
        <p class="kosong">Belum ada mahasiswa yang mengumpulkan.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Mahasiswa</th><th>Waktu kumpul</th><th>File</th><th>Catatan</th><th>Nilai (0-100)</th></tr>
                <?php foreach ($jawaban as $j): ?>
                    <tr>
                        <td><?= e($j['nim']) ?> - <?= e($j['nama_lengkap']) ?></td>
                        <td><?= tanggal_jam($j['waktu_kumpul']) ?></td>
                        <td><?php if ($j['file_jawaban']): ?><a href="../<?= e($j['file_jawaban']) ?>" target="_blank">Buka file</a><?php else: ?>-<?php endif; ?></td>
                        <td><?= e($j['catatan']) ?></td>
                        <td>
                            <form method="post" class="aksi">
                                <input type="hidden" name="beri_nilai" value="<?= $j['id'] ?>">
                                <input type="hidden" name="tugas_id" value="<?= $lihat['id'] ?>">
                                <input type="number" name="nilai" min="0" max="100" step="0.01" value="<?= e($j['nilai']) ?>">
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
    <h2><?= $edit ? 'Ubah tugas' : 'Tambah tugas' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div>
            <label for="nama_tugas">Nama tugas</label>
            <input type="text" id="nama_tugas" name="nama_tugas" value="<?= e($edit['nama_tugas'] ?? '') ?>" required>
        </div>
        <div>
            <label for="kode_mk">Mata kuliah</label>
            <select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul(), $edit['kode_mk'] ?? '') ?></select>
        </div>
        <div>
            <label for="batas_waktu">Batas pengumpulan</label>
            <input type="datetime-local" id="batas_waktu" name="batas_waktu" value="<?= e(untuk_input($edit['batas_waktu'] ?? '')) ?>" required>
        </div>
        <div class="penuh">
            <label for="deskripsi">Petunjuk / deskripsi tugas</label>
            <textarea id="deskripsi" name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah tugas' ?></button>
            <?php if ($edit): ?><a href="tugas.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar tugas</h2>
    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada tugas.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Tugas</th><th>Mata kuliah</th><th>Batas</th><th class="angka">Jawaban</th><th></th></tr>
                <?php foreach ($daftar as $t): ?>
                    <tr>
                        <td><?= e($t['nama_tugas']) ?></td>
                        <td><?= e($t['nama_mk']) ?></td>
                        <td><?= tanggal_jam($t['batas_waktu']) ?></td>
                        <td class="angka"><?= $t['jumlah_jawaban'] ?></td>
                        <td>
                            <div class="aksi">
                                <a href="?lihat=<?= $t['id'] ?>" class="tombol hijau kecil">Jawaban</a>
                                <a href="?edit=<?= $t['id'] ?>" class="tombol kecil">Ubah</a>
                                <form method="post" onsubmit="return confirm('Hapus tugas ini beserta semua jawabannya?')">
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
