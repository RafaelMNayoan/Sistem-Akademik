<?php
require_once '../koneksi.php';
$user = wajib_portal('kurikulum');
$role = $user['role'];
$bp = prodi_batas();

// Kaprodi hanya boleh mengubah/menyetujui mata kuliah prodinya atau mata kuliah umum.
function mk_boleh_tulis($mk)
{
    global $bp;
    return $bp === null || ($mk && ($mk['prodi'] === null || $mk['prodi'] === $bp));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'hapus') {
        if (!bisa('D')) { pesan('Anda tidak berhak menghapus mata kuliah.', 'gagal'); pindah('matakuliah.php'); }
        $mk = ambil_satu('SELECT * FROM mata_kuliah WHERE kode_mk = ?', [post('kode_mk')]);
        if ($mk) {
            jalankan('DELETE FROM mata_kuliah WHERE kode_mk = ?', [$mk['kode_mk']]);
            catat_log('kurikulum', 'hapus mata kuliah', $mk['kode_mk'] . ' ' . $mk['nama_mk']);
            pesan('Mata kuliah dihapus (KRS, presensi, materi, dan tugasnya ikut terhapus).');
        }
        pindah('matakuliah.php');
    }

    if ($aksi === 'setujui' || $aksi === 'draft') {
        $mk = ambil_satu('SELECT * FROM mata_kuliah WHERE kode_mk = ?', [post('kode_mk')]);
        if (!bisa('A') || !mk_boleh_tulis($mk)) {
            pesan('Anda tidak berhak menyetujui mata kuliah ini.', 'gagal');
        } else {
            $baru = $aksi === 'setujui' ? 'Disetujui' : 'Draft';
            jalankan('UPDATE mata_kuliah SET status_kurikulum = ? WHERE kode_mk = ?', [$baru, $mk['kode_mk']]);
            catat_log('kurikulum', $aksi === 'setujui' ? 'setujui kurikulum' : 'tarik persetujuan', $mk['kode_mk'] . ': ' . $mk['status_kurikulum'] . ' -> ' . $baru);
            pesan($aksi === 'setujui' ? 'Mata kuliah disetujui dan masuk kurikulum aktif.' : 'Mata kuliah dikembalikan ke draft.');
        }
        pindah('matakuliah.php');
    }

    $kodeLama = post('kode_lama');
    $kode = strtoupper(post('kode_mk'));
    $data = ['nama_mk' => post('nama_mk'), 'sks' => (int) post('sks'), 'kelas' => post('kelas'),
             'prodi' => $bp !== null ? $bp : kosong_ke_null(post('prodi')), 'semester_ke' => post('semester_ke') !== '' ? (int) post('semester_ke') : null];
    if ($kodeLama !== '' ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak untuk aksi ini.', 'gagal'); pindah('matakuliah.php'); }
    if ($kode === '' || $data['nama_mk'] === '' || strlen($kode) > 20 || strlen($data['nama_mk']) > 150 || $data['sks'] < 1 || $data['sks'] > 6
        || !in_array($data['kelas'], ['Teori', 'Praktik', 'Kombinasi'], true) || ($data['prodi'] !== null && !in_array($data['prodi'], PRODI, true))
        || ($data['semester_ke'] !== null && ($data['semester_ke'] < 1 || $data['semester_ke'] > 14))) {
        pesan('Kode, nama, SKS (1-6), kelas, prodi, dan semester harus diisi dengan benar.', 'gagal');
        pindah('matakuliah.php');
    }
    if ($kodeLama === '') {
        if (ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode])) {
            pesan('Kode ' . $kode . ' sudah dipakai.', 'gagal');
        } else {
            $status = $role === 'admin' ? 'Disetujui' : 'Draft';
            jalankan('INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, kelas, prodi, semester_ke, status_kurikulum) VALUES (?, ?, ?, ?, ?, ?, ?)',
                     [$kode, $data['nama_mk'], $data['sks'], $data['kelas'], $data['prodi'], $data['semester_ke'], $status]);
            catat_log('kurikulum', 'tambah mata kuliah', "$kode {$data['nama_mk']} ($status)");
            pesan('Mata kuliah ditambahkan' . ($status === 'Draft' ? ' sebagai draft. Setujui agar masuk kurikulum aktif.' : '.'));
        }
    } else {
        $lama = ambil_satu('SELECT * FROM mata_kuliah WHERE kode_mk = ?', [$kodeLama]);
        if (!mk_boleh_tulis($lama)) {
            pesan('Mata kuliah ini milik prodi lain.', 'gagal');
        } else {
            jalankan('UPDATE mata_kuliah SET nama_mk = ?, sks = ?, kelas = ?, prodi = ?, semester_ke = ? WHERE kode_mk = ?',
                     [$data['nama_mk'], $data['sks'], $data['kelas'], $data['prodi'], $data['semester_ke'], $kodeLama]);
            catat_log('kurikulum', 'ubah mata kuliah', "$kodeLama: " . beda($lama, $data));
            pesan('Mata kuliah diperbarui.');
        }
    }
    pindah('matakuliah.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM mata_kuliah WHERE kode_mk = ?', [get('edit')]) : null;
if ($edit && !mk_boleh_tulis($edit)) { $edit = null; }
$filterProdi = get('prodi');
$daftar = ambil_semua("SELECT m.*, (SELECT COUNT(*) FROM krs k WHERE k.kode_mk = m.kode_mk) AS peserta
    FROM mata_kuliah m WHERE (? = '' OR m.prodi = ? OR (? = 'umum' AND m.prodi IS NULL)) ORDER BY m.kode_mk", [$filterProdi, $filterProdi, $filterProdi]);

$judul = 'Kurikulum & Mata Kuliah';
include '../includes/header.php';
?>
<h1>Kurikulum & Mata Kuliah</h1>
<?php if (bisa('C') || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah mata kuliah' : 'Susun mata kuliah baru' ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="kode_lama" value="<?= $edit ? e($edit['kode_mk']) : '' ?>">
        <div><label for="kode_mk">Kode</label><input type="text" id="kode_mk" name="kode_mk" placeholder="IF107" maxlength="20" value="<?= e($edit['kode_mk'] ?? '') ?>" <?= $edit ? 'readonly' : 'required' ?>></div>
        <div><label for="nama_mk">Nama mata kuliah</label><input type="text" id="nama_mk" name="nama_mk" value="<?= e($edit['nama_mk'] ?? '') ?>" required></div>
        <div><label for="sks">SKS</label><input type="number" id="sks" name="sks" min="1" max="6" value="<?= e($edit['sks'] ?? 3) ?>" required></div>
        <div><label for="kelas">Kelas</label><select id="kelas" name="kelas"><?= opsi(sama(['Teori', 'Praktik', 'Kombinasi']), $edit['kelas'] ?? 'Kombinasi') ?></select></div>
        <div><label for="prodi">Program studi</label>
            <?php if ($bp !== null): ?><input type="text" value="<?= e($bp) ?>" readonly>
            <?php else: ?><select id="prodi" name="prodi"><option value="">Umum (semua prodi)</option><?= opsi(sama(PRODI), $edit['prodi'] ?? '') ?></select><?php endif; ?></div>
        <div><label for="semester_ke">Semester kurikulum</label><input type="number" id="semester_ke" name="semester_ke" min="1" max="14" value="<?= e($edit['semester_ke'] ?? '') ?>"></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah mata kuliah' ?></button><?php if ($edit): ?><a href="matakuliah.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar mata kuliah</h2>
    <form method="get" class="filter"><div><label for="fp">Saring program studi</label><select id="fp" name="prodi"><option value="">Semua</option><option value="umum" <?= $filterProdi === 'umum' ? 'selected' : '' ?>>Umum</option><?= opsi(sama(PRODI), $filterProdi) ?></select></div><button type="submit" class="tombol abu">Saring</button></form>
    <?php if (!$daftar): ?><p class="kosong">Belum ada mata kuliah.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>Kode</th><th>Mata kuliah</th><th class="angka">SKS</th><th>Kelas</th><th>Prodi</th><th>Sem</th><th>Kurikulum</th><th class="angka">Peserta</th><?php if (bisa('U') || bisa('A') || bisa('D')): ?><th></th><?php endif; ?></tr>
        <?php foreach ($daftar as $m): $tulis = mk_boleh_tulis($m); ?><tr>
            <td><?= e($m['kode_mk']) ?></td><td><?= e($m['nama_mk']) ?></td><td class="angka"><?= (int) $m['sks'] ?></td><td><?= e($m['kelas']) ?></td><td><?= e($m['prodi'] ?: 'Umum') ?></td><td><?= e($m['semester_ke'] ?: '-') ?></td><td><?= lencana($m['status_kurikulum']) ?></td><td class="angka"><?= $m['peserta'] ?></td>
            <?php if (bisa('U') || bisa('A') || bisa('D')): ?><td><div class="aksi">
                <?php if (bisa('U') && $tulis): ?><a href="?edit=<?= e($m['kode_mk']) ?>" class="tombol kecil">Ubah</a><?php endif; ?>
                <?php if (bisa('A') && $tulis): ?><form method="post"><input type="hidden" name="aksi" value="<?= $m['status_kurikulum'] === 'Draft' ? 'setujui' : 'draft' ?>"><input type="hidden" name="kode_mk" value="<?= e($m['kode_mk']) ?>"><button type="submit" class="tombol <?= $m['status_kurikulum'] === 'Draft' ? 'hijau' : 'abu' ?> kecil"><?= $m['status_kurikulum'] === 'Draft' ? 'Setujui' : 'Tarik' ?></button></form><?php endif; ?>
                <?php if (bisa('D')): ?><form method="post" onsubmit="return confirm('Hapus mata kuliah ini? Semua data terkait ikut terhapus.')"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="kode_mk" value="<?= e($m['kode_mk']) ?>"><button type="submit" class="tombol merah kecil">Hapus</button></form><?php endif; ?>
            </div></td><?php endif; ?>
        </tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
