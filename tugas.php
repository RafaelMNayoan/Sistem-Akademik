<?php
require_once '../koneksi.php';
$user = wajib_portal('materi');
$role = $user['role'];
$kodeSaya = $role === 'dosen' ? mk_dosen() : [];
$cak = cakupan_mk();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus'])) {
        $t = ambil_satu('SELECT * FROM tugas_kuliah WHERE id = ?', [(int) $_POST['hapus']]);
        if (!bisa('D') || !$t || !in_array($t['kode_mk'], $kodeSaya, true)) {
            pesan('Anda hanya dapat menghapus tugas kelas Anda sendiri.', 'gagal');
        } else {
            jalankan('DELETE FROM tugas_kuliah WHERE id = ?', [$t['id']]);
            catat_log('tugas', 'hapus tugas', $t['kode_mk'] . ' ' . $t['nama_tugas']);
            pesan('Tugas dihapus beserta jawaban mahasiswa.');
        }
        pindah('tugas.php');
    }
    if (isset($_POST['beri_nilai'])) {
        $j = ambil_satu('SELECT p.id, p.nilai, t.kode_mk FROM pengumpulan_tugas p JOIN tugas_kuliah t ON t.id = p.tugas_id WHERE p.id = ?', [(int) $_POST['beri_nilai']]);
        $nilai = post('nilai');
        if (!bisa('U') || !$j || !in_array($j['kode_mk'], $kodeSaya, true)) {
            pesan('Anda hanya dapat menilai tugas kelas Anda sendiri.', 'gagal');
        } elseif ($nilai !== '' && (!is_numeric($nilai) || $nilai < 0 || $nilai > 100)) {
            pesan('Nilai harus angka 0 sampai 100.', 'gagal');
        } else {
            jalankan('UPDATE pengumpulan_tugas SET nilai = ? WHERE id = ?', [$nilai === '' ? null : $nilai, $j['id']]);
            catat_log('tugas', 'nilai tugas', 'pengumpulan ' . $j['id'] . ': ' . ($j['nilai'] ?? '-') . ' -> ' . ($nilai === '' ? '-' : $nilai));
            pesan('Nilai disimpan.');
        }
        pindah('tugas.php?lihat=' . (int) post('tugas_id'));
    }
    $id = (int) post('id');
    $kode = post('kode_mk');
    $nama = post('nama_tugas');
    $batas = str_replace('T', ' ', post('batas_waktu'));
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak untuk aksi ini.', 'gagal'); pindah('tugas.php'); }
    if (!in_array($kode, $kodeSaya, true) || $nama === '' || !strtotime($batas)) {
        pesan('Pilih kelas yang Anda ampu, isi nama tugas, dan batas waktu.', 'gagal');
        pindah('tugas.php');
    }
    if ($id) {
        $lama = ambil_satu('SELECT * FROM tugas_kuliah WHERE id = ?', [$id]);
        if (!$lama || !in_array($lama['kode_mk'], $kodeSaya, true)) { pesan('Tugas bukan milik kelas Anda.', 'gagal'); pindah('tugas.php'); }
        jalankan('UPDATE tugas_kuliah SET kode_mk = ?, nama_tugas = ?, deskripsi = ?, batas_waktu = ? WHERE id = ?', [$kode, $nama, post('deskripsi'), $batas, $id]);
        catat_log('tugas', 'ubah tugas', "id $id $kode $nama");
        pesan('Tugas diperbarui.');
    } else {
        jalankan('INSERT INTO tugas_kuliah (kode_mk, nama_tugas, deskripsi, batas_waktu) VALUES (?, ?, ?, ?)', [$kode, $nama, post('deskripsi'), $batas]);
        catat_log('tugas', 'tambah tugas', "$kode $nama");
        pesan('Tugas ditambahkan.');
    }
    pindah('tugas.php');
}

$batasSql = ''; $pCak = [];
if ($cak !== null) { [$in, $pCak] = daftar_in($cak); $batasSql = " AND t.kode_mk IN ($in)"; }
$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM tugas_kuliah WHERE id = ?', [(int) get('edit')]) : null;
if ($edit && !in_array($edit['kode_mk'], $kodeSaya, true)) { $edit = null; }
$lihat = get('lihat') !== '' ? ambil_satu('SELECT t.*, m.nama_mk FROM tugas_kuliah t JOIN mata_kuliah m ON m.kode_mk = t.kode_mk WHERE t.id = ?' . $batasSql, array_merge([(int) get('lihat')], $pCak)) : null;
$jawaban = $lihat ? ambil_semua('SELECT p.*, mh.nama_lengkap FROM pengumpulan_tugas p JOIN mahasiswa mh ON mh.nim = p.nim WHERE p.tugas_id = ? ORDER BY p.waktu_kumpul', [$lihat['id']]) : [];
$daftar = ambil_semua("SELECT t.*, m.nama_mk, (SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id = t.id) AS jumlah_jawaban
    FROM tugas_kuliah t JOIN mata_kuliah m ON m.kode_mk = t.kode_mk WHERE 1 = 1 $batasSql ORDER BY t.batas_waktu DESC", $pCak);
$pilihanMk = [];
foreach ($kodeSaya as $kd) { $mk = ambil_satu('SELECT nama_mk FROM mata_kuliah WHERE kode_mk = ?', [$kd]); $pilihanMk[$kd] = $kd . ' - ' . ($mk['nama_mk'] ?? ''); }

$judul = 'Tugas Kuliah';
include '../includes/header.php';
?>
<h1>Tugas Kuliah</h1>
<?php if ($lihat): $bolehNilai = bisa('U') && in_array($lihat['kode_mk'], $kodeSaya, true); ?>
<div class="kartu">
    <h2>Jawaban: <?= e($lihat['nama_tugas']) ?> (<?= e($lihat['nama_mk']) ?>)</h2>
    <p><a href="tugas.php" class="tombol abu kecil">Kembali</a></p>
    <?php if (!$jawaban): ?><p class="kosong">Belum ada mahasiswa yang mengumpulkan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Mahasiswa</th><th>Waktu kumpul</th><th>File</th><th>Catatan</th><th>Nilai (0-100)</th></tr>
        <?php foreach ($jawaban as $j): ?><tr><td><?= e($j['nim']) ?> - <?= e($j['nama_lengkap']) ?></td><td><?= tanggal_jam($j['waktu_kumpul']) ?></td>
        <td><?php if ($j['file_jawaban']): ?><a href="../<?= e($j['file_jawaban']) ?>" target="_blank">Buka file</a><?php else: ?>-<?php endif; ?></td><td><?= e($j['catatan']) ?></td>
        <td><?php if ($bolehNilai): ?><form method="post" class="aksi"><input type="hidden" name="beri_nilai" value="<?= $j['id'] ?>"><input type="hidden" name="tugas_id" value="<?= $lihat['id'] ?>"><input type="number" name="nilai" min="0" max="100" step="0.01" value="<?= e($j['nilai']) ?>"><button type="submit" class="tombol kecil">Simpan</button></form><?php else: ?><?= e($j['nilai'] ?? '-') ?><?php endif; ?></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php endif; ?>
<?php if ((bisa('C') && $pilihanMk) || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah tugas' : 'Tambah tugas' ?></h2>
    <form method="post" class="form-isi"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="nama_tugas">Nama tugas</label><input type="text" id="nama_tugas" name="nama_tugas" value="<?= e($edit['nama_tugas'] ?? '') ?>" required></div>
        <div><label for="kode_mk">Mata kuliah (kelas saya)</label><select id="kode_mk" name="kode_mk" required><?= opsi($pilihanMk, $edit['kode_mk'] ?? '') ?></select></div>
        <div><label for="batas_waktu">Batas pengumpulan</label><input type="datetime-local" id="batas_waktu" name="batas_waktu" value="<?= e(untuk_input($edit['batas_waktu'] ?? '')) ?>" required></div>
        <div class="penuh"><label for="deskripsi">Petunjuk / deskripsi tugas</label><textarea id="deskripsi" name="deskripsi" rows="3"><?= e($edit['deskripsi'] ?? '') ?></textarea></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah tugas' ?></button><?php if ($edit): ?><a href="tugas.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar tugas</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada tugas.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Tugas</th><th>Mata kuliah</th><th>Batas</th><th class="angka">Jawaban</th><th></th></tr>
        <?php foreach ($daftar as $t): $milik = in_array($t['kode_mk'], $kodeSaya, true); ?><tr><td><?= e($t['nama_tugas']) ?></td><td><?= e($t['nama_mk']) ?></td><td><?= tanggal_jam($t['batas_waktu']) ?></td><td class="angka"><?= $t['jumlah_jawaban'] ?></td>
        <td><div class="aksi"><a href="?lihat=<?= $t['id'] ?>" class="tombol hijau kecil">Jawaban</a>
            <?php if (bisa('U') && $milik): ?><a href="?edit=<?= $t['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
            <?php if (bisa('D') && $milik): ?><form method="post" onsubmit="return confirm('Hapus tugas ini beserta semua jawabannya?')"><input type="hidden" name="hapus" value="<?= $t['id'] ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
