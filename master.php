<?php
require_once '../koneksi.php';
$user = wajib_portal('master');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'prodi_simpan') {
        $id = (int) post('id');
        $nama = post('nama');
        $jenjang = post('jenjang');
        $ket = kosong_ke_null(post('keterangan'));
        if (!in_array($jenjang, JENJANG, true) || ($id === 0 && $nama === '')) {
            pesan('Nama prodi dan jenjang harus diisi.', 'gagal');
        } elseif ($id) {
            if (!bisa('U')) { pesan('Tidak berhak mengubah.', 'gagal'); pindah('master.php'); }
            $lama = ambil_satu('SELECT * FROM prodi WHERE id = ?', [$id]);
            jalankan('UPDATE prodi SET jenjang = ?, keterangan = ? WHERE id = ?', [$jenjang, $ket, $id]);
            catat_log('master', 'ubah prodi ' . $lama['nama'], beda($lama, ['jenjang' => $jenjang, 'keterangan' => $ket]));
            pesan('Program studi diperbarui.');
        } else {
            if (!bisa('C')) { pesan('Tidak berhak menambah.', 'gagal'); pindah('master.php'); }
            if (ambil_satu('SELECT id FROM prodi WHERE nama = ?', [$nama])) {
                pesan('Program studi itu sudah ada.', 'gagal');
            } else {
                jalankan('INSERT INTO prodi (nama, jenjang, keterangan) VALUES (?, ?, ?)', [$nama, $jenjang, $ket]);
                catat_log('master', 'tambah prodi', $nama);
                pesan('Program studi ditambahkan.');
            }
        }
        pindah('master.php');
    }

    if ($aksi === 'prodi_hapus' && bisa('D')) {
        $p = ambil_satu('SELECT * FROM prodi WHERE id = ?', [(int) post('id')]);
        if ($p) {
            $dipakai = ambil_satu('SELECT (SELECT COUNT(*) FROM mahasiswa WHERE program_studi = ?) + (SELECT COUNT(*) FROM dosen WHERE prodi = ?) + (SELECT COUNT(*) FROM mata_kuliah WHERE prodi = ?) AS n', [$p['nama'], $p['nama'], $p['nama']]);
            if ((int) $dipakai['n'] > 0) {
                pesan('Prodi masih dipakai data mahasiswa/dosen/mata kuliah, tidak bisa dihapus.', 'gagal');
            } else {
                jalankan('DELETE FROM prodi WHERE id = ?', [$p['id']]);
                catat_log('master', 'hapus prodi', $p['nama']);
                pesan('Program studi dihapus.');
            }
        }
        pindah('master.php');
    }

    if ($aksi === 'ta_simpan') {
        $id = (int) post('id');
        $nama = post('nama');
        $sem = post('semester');
        $mulai = kosong_ke_null(post('tgl_mulai'));
        $selesai = kosong_ke_null(post('tgl_selesai'));
        if (!preg_match('#^[0-9]{4}/[0-9]{4}$#', $nama) || !in_array($sem, ['Ganjil', 'Genap'], true)
            || ($mulai && !strtotime($mulai)) || ($selesai && !strtotime($selesai)) || ($mulai && $selesai && $selesai < $mulai)) {
            pesan('Isi tahun akademik (contoh 2026/2027), semester, dan tanggal dengan benar.', 'gagal');
        } elseif ($id) {
            if (!bisa('U')) { pesan('Tidak berhak mengubah.', 'gagal'); pindah('master.php'); }
            $lama = ambil_satu('SELECT * FROM tahun_akademik WHERE id = ?', [$id]);
            jalankan('UPDATE tahun_akademik SET nama = ?, semester = ?, tgl_mulai = ?, tgl_selesai = ? WHERE id = ?', [$nama, $sem, $mulai, $selesai, $id]);
            catat_log('master', 'ubah tahun akademik', beda($lama, ['nama' => $nama, 'semester' => $sem, 'tgl_mulai' => $mulai, 'tgl_selesai' => $selesai]));
            pesan('Tahun akademik diperbarui.');
        } else {
            if (!bisa('C')) { pesan('Tidak berhak menambah.', 'gagal'); pindah('master.php'); }
            jalankan('INSERT INTO tahun_akademik (nama, semester, tgl_mulai, tgl_selesai, aktif) VALUES (?, ?, ?, ?, 0)', [$nama, $sem, $mulai, $selesai]);
            catat_log('master', 'tambah tahun akademik', "$nama $sem");
            pesan('Tahun akademik ditambahkan.');
        }
        pindah('master.php');
    }

    if ($aksi === 'ta_aktif' && bisa('U')) {
        $id = (int) post('id');
        if (ambil_satu('SELECT id FROM tahun_akademik WHERE id = ?', [$id])) {
            jalankan('UPDATE tahun_akademik SET aktif = 0');
            jalankan('UPDATE tahun_akademik SET aktif = 1 WHERE id = ?', [$id]);
            catat_log('master', 'aktifkan tahun akademik', 'id ' . $id);
            pesan('Tahun akademik aktif diganti.');
        }
        pindah('master.php');
    }

    if ($aksi === 'ta_hapus' && bisa('D')) {
        $t = ambil_satu('SELECT * FROM tahun_akademik WHERE id = ?', [(int) post('id')]);
        if ($t && (int) $t['aktif'] === 1) {
            pesan('Tahun akademik yang sedang aktif tidak bisa dihapus.', 'gagal');
        } elseif ($t) {
            jalankan('DELETE FROM tahun_akademik WHERE id = ?', [$t['id']]);
            catat_log('master', 'hapus tahun akademik', $t['nama'] . ' ' . $t['semester']);
            pesan('Tahun akademik dihapus.');
        }
        pindah('master.php');
    }
    pindah('master.php');
}

$editProdi = get('edit_prodi') !== '' && bisa('U') ? ambil_satu('SELECT * FROM prodi WHERE id = ?', [(int) get('edit_prodi')]) : null;
$editTa = get('edit_ta') !== '' && bisa('U') ? ambil_satu('SELECT * FROM tahun_akademik WHERE id = ?', [(int) get('edit_ta')]) : null;
$prodi = ambil_semua('SELECT p.*, (SELECT COUNT(*) FROM mahasiswa m WHERE m.program_studi = p.nama) AS mhs FROM prodi p ORDER BY p.id');
$ta = ambil_semua('SELECT * FROM tahun_akademik ORDER BY nama DESC, semester DESC');

$judul = 'Data Master';
include '../includes/header.php';
?>
<h1>Data Master</h1>

<?php if (bisa('C') || $editProdi): ?>
<div class="kartu">
    <h2><?= $editProdi ? 'Ubah program studi' : 'Tambah program studi' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="prodi_simpan"><input type="hidden" name="id" value="<?= $editProdi ? $editProdi['id'] : '' ?>">
        <div><label for="nama">Nama program studi</label><input type="text" id="nama" name="nama" value="<?= e($editProdi['nama'] ?? '') ?>" <?= $editProdi ? 'readonly' : 'required' ?>></div>
        <div><label for="jenjang">Jenjang</label><select id="jenjang" name="jenjang"><?= opsi(sama(JENJANG), $editProdi['jenjang'] ?? 'S1') ?></select></div>
        <div class="penuh"><label for="keterangan">Keterangan</label><input type="text" id="keterangan" name="keterangan" value="<?= e($editProdi['keterangan'] ?? '') ?>"></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $editProdi ? 'Simpan perubahan' : 'Tambah prodi' ?></button><?php if ($editProdi): ?><a href="master.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu"><h2>Program studi</h2><div class="gulir"><table>
    <tr><th>Program studi</th><th>Jenjang</th><th>Keterangan</th><th class="angka">Mahasiswa</th><th></th></tr>
    <?php foreach ($prodi as $p): ?><tr><td><?= e($p['nama']) ?></td><td><?= e($p['jenjang']) ?></td><td><?= e($p['keterangan']) ?></td><td class="angka"><?= $p['mhs'] ?></td>
    <td><div class="aksi">
        <?php if (bisa('U')): ?><a href="?edit_prodi=<?= $p['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
        <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus program studi ini?')"><input type="hidden" name="aksi" value="prodi_hapus"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
    </div></td></tr><?php endforeach; ?>
</table></div></div>

<?php if (bisa('C') || $editTa): ?>
<div class="kartu">
    <h2><?= $editTa ? 'Ubah tahun akademik' : 'Tambah tahun akademik' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="ta_simpan"><input type="hidden" name="id" value="<?= $editTa ? $editTa['id'] : '' ?>">
        <div><label for="ta_nama">Tahun akademik</label><input type="text" id="ta_nama" name="nama" placeholder="2026/2027" value="<?= e($editTa['nama'] ?? '') ?>" required></div>
        <div><label for="semester">Semester</label><select id="semester" name="semester"><?= opsi(sama(['Ganjil', 'Genap']), $editTa['semester'] ?? 'Ganjil') ?></select></div>
        <div><label for="tgl_mulai">Tanggal mulai</label><input type="date" id="tgl_mulai" name="tgl_mulai" value="<?= e($editTa['tgl_mulai'] ?? '') ?>"></div>
        <div><label for="tgl_selesai">Tanggal selesai</label><input type="date" id="tgl_selesai" name="tgl_selesai" value="<?= e($editTa['tgl_selesai'] ?? '') ?>"></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $editTa ? 'Simpan perubahan' : 'Tambah tahun akademik' ?></button><?php if ($editTa): ?><a href="master.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu"><h2>Tahun akademik</h2><div class="gulir"><table>
    <tr><th>Tahun akademik</th><th>Semester</th><th>Mulai</th><th>Selesai</th><th>Status</th><th></th></tr>
    <?php foreach ($ta as $t): ?><tr><td><?= e($t['nama']) ?></td><td><?= e($t['semester']) ?></td><td><?= tanggal($t['tgl_mulai']) ?></td><td><?= tanggal($t['tgl_selesai']) ?></td>
    <td><?= $t['aktif'] ? lencana('Aktif') : lencana('Tidak aktif') ?></td>
    <td><div class="aksi">
        <?php if (bisa('U') && !$t['aktif']): ?><form method="post"><input type="hidden" name="aksi" value="ta_aktif"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="tombol hijau kecil">Jadikan aktif</button></form><?php endif; ?>
        <?php if (bisa('U')): ?><a href="?edit_ta=<?= $t['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
        <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus tahun akademik ini?')"><input type="hidden" name="aksi" value="ta_hapus"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
    </div></td></tr><?php endforeach; ?>
</table></div></div>
<?php include '../includes/footer.php'; ?>
