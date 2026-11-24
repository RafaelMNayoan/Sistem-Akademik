<?php
require_once '../koneksi.php';
$user = wajib_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['hapus'])) {
        jalankan('DELETE FROM mata_kuliah WHERE kode_mk = ?', [post('hapus')]);
        pesan('Mata kuliah dihapus (KRS, sesi presensi, materi, dan tugasnya ikut terhapus).');
        pindah('matakuliah.php');
    }

    $kodeLama = post('kode_lama');
    $kode = strtoupper(post('kode_mk'));
    $nama = post('nama_mk');
    $sks = (int) post('sks');
    $kelas = post('kelas');

    if ($kode === '' || $nama === '' || strlen($kode) > 20 || strlen($nama) > 150
        || $sks < 1 || $sks > 6 || !in_array($kelas, ['Teori', 'Praktik', 'Kombinasi'])) {
        pesan('Kode, nama, SKS (1-6), dan kelas harus diisi dengan benar.', 'gagal');
        pindah('matakuliah.php');
    }

    if ($kodeLama === '') {
        if (ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode])) {
            pesan('Kode ' . $kode . ' sudah dipakai.', 'gagal');
        } else {
            jalankan('INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, kelas) VALUES (?, ?, ?, ?)', [$kode, $nama, $sks, $kelas]);
            pesan('Mata kuliah ditambahkan.');
        }
    } else {
        jalankan('UPDATE mata_kuliah SET nama_mk = ?, sks = ?, kelas = ? WHERE kode_mk = ?', [$nama, $sks, $kelas, $kodeLama]);
        pesan('Mata kuliah diperbarui.');
    }
    pindah('matakuliah.php');
}

$edit = get('edit') !== '' ? ambil_satu('SELECT * FROM mata_kuliah WHERE kode_mk = ?', [get('edit')]) : null;

$daftar = ambil_semua('SELECT m.*, (SELECT COUNT(*) FROM krs k WHERE k.kode_mk = m.kode_mk) AS peserta
                       FROM mata_kuliah m ORDER BY m.kode_mk');

$judul = 'Mata Kuliah';
include '../includes/header.php';
?>
<h1>Mata Kuliah</h1>

<div class="kartu">
    <h2><?= $edit ? 'Ubah mata kuliah' : 'Tambah mata kuliah' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="kode_lama" value="<?= $edit ? e($edit['kode_mk']) : '' ?>">
        <div>
            <label for="kode_mk">Kode</label>
            <input type="text" id="kode_mk" name="kode_mk" placeholder="IF107" maxlength="20"
                   value="<?= e($edit['kode_mk'] ?? '') ?>" <?= $edit ? 'readonly' : 'required' ?>>
        </div>
        <div>
            <label for="nama_mk">Nama mata kuliah</label>
            <input type="text" id="nama_mk" name="nama_mk" value="<?= e($edit['nama_mk'] ?? '') ?>" required>
        </div>
        <div>
            <label for="sks">SKS</label>
            <input type="number" id="sks" name="sks" min="1" max="6" value="<?= e($edit['sks'] ?? 3) ?>" required>
        </div>
        <div>
            <label for="kelas">Kelas</label>
            <select id="kelas" name="kelas"><?= opsi(sama(['Teori', 'Praktik', 'Kombinasi']), $edit['kelas'] ?? 'Kombinasi') ?></select>
        </div>
        <div class="penuh baris-tombol">
            <button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah mata kuliah' ?></button>
            <?php if ($edit): ?><a href="matakuliah.php" class="tombol abu">Batal</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="kartu">
    <h2>Daftar mata kuliah</h2>
    <?php if (!$daftar): ?>
        <p class="kosong">Belum ada mata kuliah.</p>
    <?php else: ?>
        <div class="gulir">
            <table>
                <tr><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Kelas</th><th class="angka">Peserta</th><th></th></tr>
                <?php foreach ($daftar as $m): ?>
                    <tr>
                        <td><?= e($m['kode_mk']) ?></td>
                        <td><?= e($m['nama_mk']) ?></td>
                        <td class="angka"><?= $m['sks'] ?></td>
                        <td><?= e($m['kelas']) ?></td>
                        <td class="angka"><?= $m['peserta'] ?></td>
                        <td>
                            <div class="aksi">
                                <a href="?edit=<?= e($m['kode_mk']) ?>" class="tombol kecil">Ubah</a>
                                <form method="post" onsubmit="return confirm('Hapus mata kuliah ini? Semua data terkait ikut terhapus.')">
                                    <input type="hidden" name="hapus" value="<?= e($m['kode_mk']) ?>">
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
