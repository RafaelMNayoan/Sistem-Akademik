<?php
require_once '../koneksi.php';
$user = wajib_portal('biaya');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');
    if ($aksi === 'aktif') { // "hapus" pada data keuangan = nonaktifkan, bukan hilang
        $k = ambil_satu('SELECT * FROM komponen_biaya WHERE id = ?', [$id]);
        if (!bisa('D') || !$k) { pesan('Anda tidak berhak.', 'gagal'); }
        else {
            $baru = $k['aktif'] ? 0 : 1;
            jalankan('UPDATE komponen_biaya SET aktif = ? WHERE id = ?', [$baru, $id]);
            catat_log('biaya', $baru ? 'aktifkan komponen' : 'nonaktifkan komponen', $k['nama'], 'keuangan');
            pesan($baru ? 'Komponen biaya diaktifkan.' : 'Komponen biaya dinonaktifkan (data tetap tersimpan untuk audit).');
        }
        pindah('biaya.php');
    }
    $data = ['nama' => post('nama'), 'prodi' => kosong_ke_null(post('prodi')), 'angkatan' => post('angkatan') !== '' ? (int) post('angkatan') : null,
             'nominal' => (float) post('nominal'), 'keterangan' => kosong_ke_null(post('keterangan'))];
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak menyimpan.', 'gagal'); pindah('biaya.php'); }
    if ($data['nama'] === '' || $data['nominal'] < 0 || ($data['prodi'] !== null && !in_array($data['prodi'], PRODI, true)) || ($data['angkatan'] !== null && ($data['angkatan'] < 2000 || $data['angkatan'] > 2100))) {
        pesan('Nama, nominal, prodi, dan angkatan harus valid.', 'gagal');
        pindah('biaya.php' . ($id ? '?edit=' . $id : ''));
    }
    if ($id) {
        $lama = ambil_satu('SELECT * FROM komponen_biaya WHERE id = ?', [$id]);
        jalankan('UPDATE komponen_biaya SET nama = ?, prodi = ?, angkatan = ?, nominal = ?, keterangan = ? WHERE id = ?', array_merge(array_values($data), [$id]));
        catat_log('biaya', 'ubah komponen', "id $id: " . beda($lama, $data), 'keuangan');
        pesan('Komponen biaya diperbarui.');
    } else {
        jalankan('INSERT INTO komponen_biaya (nama, prodi, angkatan, nominal, keterangan, aktif) VALUES (?, ?, ?, ?, ?, 1)', array_values($data));
        catat_log('biaya', 'tambah komponen', $data['nama'] . ' ' . $data['nominal'], 'keuangan');
        pesan('Komponen biaya ditambahkan.');
    }
    pindah('biaya.php');
}
$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM komponen_biaya WHERE id = ?', [(int) get('edit')]) : null;
$bp = prodi_batas();
$daftar = ambil_semua('SELECT * FROM komponen_biaya ORDER BY aktif DESC, nama');
$judul = 'Komponen Biaya & Tarif';
include '../includes/header.php';
?>
<h1>Komponen Biaya & Tarif</h1>
<?php if (bisa('C') || $edit): ?>
<div class="kartu">
    <h2><?= $edit ? 'Ubah komponen biaya' : 'Tambah komponen biaya' ?></h2>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="nama">Nama komponen (SPP/UKT, uang gedung, praktikum, ...)</label><input type="text" id="nama" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></div>
        <div><label for="nominal">Nominal (Rp)</label><input type="number" id="nominal" name="nominal" min="0" step="1000" value="<?= e($edit['nominal'] ?? '') ?>" required></div>
        <div><label for="prodi">Program studi</label><select id="prodi" name="prodi"><option value="">Semua prodi</option><?= opsi(sama(PRODI), $edit['prodi'] ?? '') ?></select></div>
        <div><label for="angkatan">Angkatan (kosongkan = semua)</label><input type="number" id="angkatan" name="angkatan" min="2000" max="2100" value="<?= e($edit['angkatan'] ?? '') ?>"></div>
        <div class="penuh"><label for="keterangan">Keterangan</label><input type="text" id="keterangan" name="keterangan" value="<?= e($edit['keterangan'] ?? '') ?>"></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah komponen' ?></button><?php if ($edit): ?><a href="biaya.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu"><h2>Daftar komponen biaya</h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada komponen biaya.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Komponen</th><th>Prodi</th><th>Angkatan</th><th class="angka">Nominal</th><th>Status</th><?php if (bisa('U') || bisa('D')): ?><th></th><?php endif; ?></tr>
    <?php foreach ($daftar as $k): ?><tr><td><?= e($k['nama']) ?><?php if ($k['keterangan']): ?><br><span class="keterangan"><?= e($k['keterangan']) ?></span><?php endif; ?></td><td><?= e($k['prodi'] ?: 'Semua') ?></td><td><?= e($k['angkatan'] ?: 'Semua') ?></td><td class="angka"><?= rupiah($k['nominal']) ?></td><td><?= $k['aktif'] ? lencana('Aktif') : lencana('Non-Aktif') ?></td>
    <?php if (bisa('U') || bisa('D')): ?><td><div class="aksi"><?php if (bisa('U')): ?><a href="?edit=<?= $k['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
        <?php if (bisa('D')): ?><form method="post"><input type="hidden" name="aksi" value="aktif"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button type="submit" class="tombol abu kecil"><?= $k['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><?php endif; ?></div></td><?php endif; ?></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
