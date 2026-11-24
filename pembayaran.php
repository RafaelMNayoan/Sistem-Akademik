<?php
require_once '../koneksi.php';
$user = wajib_portal('pembayaran');
perbarui_keterlambatan();
$METODE = ['Transfer', 'Virtual Account', 'Tunai'];

function sisa_tagihan($t)
{
    return (float) $t['total_bayar'] - terbayar_tagihan($t['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');

    if ($aksi === 'pengaturan') {
        if (!bisa('U')) { pesan('Anda tidak berhak mengubah pengaturan.', 'gagal'); pindah('pembayaran.php'); }
        foreach (['krs_wajib_lunas', 'ujian_wajib_lunas'] as $k) {
            $baru = isset($_POST[$k]) ? '1' : '0';
            $lama = ambil_pengaturan($k);
            if ($lama !== $baru) {
                simpan_pengaturan($k, $baru);
                catat_log('pembayaran', 'ubah syarat bayar', "$k: $lama -> $baru", 'keuangan');
            }
        }
        pesan('Pengaturan syarat pembayaran disimpan.');
        pindah('pembayaran.php');
    }

    $p = $id ? ambil_satu('SELECT * FROM pembayaran WHERE id = ?', [$id]) : null;
    $t = $p ? ambil_satu('SELECT * FROM tagihan WHERE id = ?', [$p['tagihan_id']]) : null;

    if ($aksi === 'verifikasi') {
        if (!bisa('A') || !$p || $p['status'] !== 'Menunggu') { pesan('Pembayaran tidak dapat diverifikasi.', 'gagal'); }
        elseif (!$t || $t['status'] === 'Dibatalkan' || (float) $p['jumlah'] > sisa_tagihan($t) + 0.001) { pesan('Jumlah melebihi sisa tagihan atau tagihan sudah dibatalkan.', 'gagal'); }
        else {
            jalankan("UPDATE pembayaran SET status = 'Terverifikasi', diverifikasi_oleh = ?, diverifikasi_pada = ? WHERE id = ?", [$user['nama'], date('Y-m-d H:i:s'), $id]);
            hitung_status_tagihan($t['id']);
            catat_log('pembayaran', 'verifikasi pembayaran', "id $id tagihan {$t['kode_tagihan']} " . rupiah($p['jumlah']) . ': Menunggu -> Terverifikasi', 'keuangan');
            pesan('Pembayaran diverifikasi dan status tagihan diperbarui.');
        }
        pindah('pembayaran.php');
    }
    if ($aksi === 'tolak') {
        if (!bisa('A') || !$p || $p['status'] === 'Ditolak') { pesan('Pembayaran tidak dapat ditolak.', 'gagal'); }
        elseif (post('alasan') === '') { pesan('Alasan wajib diisi.', 'gagal'); }
        else {
            jalankan("UPDATE pembayaran SET status = 'Ditolak', alasan = ?, diverifikasi_oleh = ?, diverifikasi_pada = ? WHERE id = ?", [post('alasan'), $user['nama'], date('Y-m-d H:i:s'), $id]);
            if ($t) hitung_status_tagihan($t['id']);
            catat_log('pembayaran', 'tolak pembayaran', "id $id: {$p['status']} -> Ditolak, alasan " . post('alasan'), 'keuangan');
            pesan('Pembayaran ditolak. Data tetap tersimpan untuk audit.');
        }
        pindah('pembayaran.php');
    }

    // simpan (catat baru / ubah)
    $data = ['jumlah' => (float) post('jumlah'), 'metode' => post('metode'), 'tanggal' => post('tanggal'), 'referensi' => kosong_ke_null(post('referensi')), 'catatan' => kosong_ke_null(post('catatan'))];
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak menyimpan pembayaran.', 'gagal'); pindah('pembayaran.php'); }
    $tagihan = $id ? $t : ambil_satu('SELECT * FROM tagihan WHERE id = ?', [(int) post('tagihan_id')]);
    if (!$tagihan || in_array($tagihan['status'], ['Dibayar', 'Dibatalkan'], true) && !$id) { pesan('Pilih tagihan yang masih terbuka.', 'gagal'); }
    elseif ($id && (!$p || $p['status'] !== 'Menunggu')) { pesan('Hanya pembayaran berstatus Menunggu yang dapat diubah.', 'gagal'); }
    elseif ($data['jumlah'] <= 0 || !in_array($data['metode'], $METODE, true) || !strtotime($data['tanggal'])) { pesan('Jumlah, metode, dan tanggal harus valid.', 'gagal'); }
    elseif ($data['jumlah'] > sisa_tagihan($tagihan) + 0.001) { pesan('Jumlah melebihi sisa tagihan (' . rupiah(sisa_tagihan($tagihan)) . ').', 'gagal'); }
    elseif ($id) {
        jalankan('UPDATE pembayaran SET jumlah = ?, metode = ?, tanggal = ?, referensi = ?, catatan = ? WHERE id = ?', array_merge(array_values($data), [$id]));
        catat_log('pembayaran', 'ubah pembayaran', "id $id: " . beda($p, $data), 'keuangan');
        pesan('Pembayaran diperbarui.');
    } else {
        $langsung = isset($_POST['langsung']) && bisa('A');
        jalankan('INSERT INTO pembayaran (tagihan_id, jumlah, metode, tanggal, referensi, catatan, status, dicatat_oleh, diverifikasi_oleh, diverifikasi_pada) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                 [$tagihan['id'], $data['jumlah'], $data['metode'], $data['tanggal'], $data['referensi'], $data['catatan'], $langsung ? 'Terverifikasi' : 'Menunggu', $user['nama'], $langsung ? $user['nama'] : null, $langsung ? date('Y-m-d H:i:s') : null]);
        if ($langsung) hitung_status_tagihan($tagihan['id']);
        catat_log('pembayaran', 'catat pembayaran', "tagihan {$tagihan['kode_tagihan']} " . rupiah($data['jumlah']) . ' ' . $data['metode'] . ($langsung ? ' (langsung terverifikasi)' : ''), 'keuangan');
        pesan($langsung ? 'Pembayaran dicatat dan diverifikasi.' : 'Pembayaran dicatat, menunggu verifikasi.');
    }
    pindah('pembayaran.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu("SELECT * FROM pembayaran WHERE id = ? AND status = 'Menunggu'", [(int) get('edit')]) : null;
$fStatus = get('status');
$daftar = ambil_semua("SELECT p.*, t.kode_tagihan, t.nama_tagihan, t.nim, mh.nama_lengkap FROM pembayaran p JOIN tagihan t ON t.id = p.tagihan_id JOIN mahasiswa mh ON mh.nim = t.nim
    WHERE (? = '' OR p.status = ?) ORDER BY p.id DESC LIMIT 200", [$fStatus, $fStatus]);
$terbuka = [];
foreach (ambil_semua("SELECT t.*, mh.nama_lengkap FROM tagihan t JOIN mahasiswa mh ON mh.nim = t.nim WHERE t.status NOT IN ('Dibayar','Dibatalkan') ORDER BY t.nim, t.id") as $t) {
    $terbuka[$t['id']] = $t['nim'] . ' - ' . $t['nama_lengkap'] . ' | ' . $t['nama_tagihan'] . ' | sisa ' . rupiah(sisa_tagihan($t));
}

$judul = 'Verifikasi Pembayaran';
include '../includes/header.php';
?>
<h1>Verifikasi Pembayaran</h1>
<?php if (bisa('C') || $edit): ?>
<div class="kartu"><h2><?= $edit ? 'Ubah pembayaran #' . $edit['id'] : 'Catat pembayaran' ?></h2>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <?php if (!$edit): ?><div class="penuh"><label for="tagihan_id">Tagihan</label><select id="tagihan_id" name="tagihan_id" required><?= opsi($terbuka) ?></select></div><?php endif; ?>
        <div><label for="jumlah">Jumlah dibayar (Rp)</label><input type="number" id="jumlah" name="jumlah" min="1" step="1" value="<?= e($edit['jumlah'] ?? '') ?>" required></div>
        <div><label for="metode">Metode</label><select id="metode" name="metode"><?= opsi(sama($METODE), $edit['metode'] ?? 'Transfer') ?></select></div>
        <div><label for="tanggal">Tanggal bayar</label><input type="date" id="tanggal" name="tanggal" value="<?= e($edit['tanggal'] ?? date('Y-m-d')) ?>" required></div>
        <div><label for="referensi">No. referensi / VA / bukti transfer</label><input type="text" id="referensi" name="referensi" value="<?= e($edit['referensi'] ?? '') ?>"></div>
        <div class="penuh"><label for="catatan">Catatan</label><input type="text" id="catatan" name="catatan" value="<?= e($edit['catatan'] ?? '') ?>"></div>
        <?php if (!$edit && bisa('A')): ?><div class="penuh"><label><input type="checkbox" name="langsung" value="1" style="width:auto"> Langsung verifikasi (untuk tunai / bukti sudah dicek)</label></div><?php endif; ?>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Catat pembayaran' ?></button><?php if ($edit): ?><a href="pembayaran.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<?php if (bisa('U')): ?>
<div class="kartu"><h2>Syarat pembayaran untuk akses akademik</h2>
    <form method="post"><input type="hidden" name="aksi" value="pengaturan">
        <p><label><input type="checkbox" name="krs_wajib_lunas" value="1" style="width:auto" <?= ambil_pengaturan('krs_wajib_lunas') === '1' ? 'checked' : '' ?>> Mahasiswa wajib lunas agar dapat mengisi/disetujui KRS</label></p>
        <p><label><input type="checkbox" name="ujian_wajib_lunas" value="1" style="width:auto" <?= ambil_pengaturan('ujian_wajib_lunas') === '1' ? 'checked' : '' ?>> Mahasiswa wajib lunas agar kartu ujian dapat dicetak</label></p>
        <button type="submit" class="tombol">Simpan pengaturan</button>
    </form>
</div>
<?php endif; ?>
<div class="kartu"><h2>Daftar pembayaran</h2>
    <form method="get" class="filter"><div><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><?= opsi(sama(['Menunggu', 'Terverifikasi', 'Ditolak']), $fStatus) ?></select></div><button type="submit" class="tombol abu">Saring</button></form>
    <?php if (!$daftar): ?><p class="kosong">Belum ada pembayaran.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Tanggal</th><th>Mahasiswa</th><th>Tagihan</th><th class="angka">Jumlah</th><th>Metode</th><th>Status</th><th></th></tr>
    <?php foreach ($daftar as $p): ?><tr><td><?= tanggal($p['tanggal']) ?></td><td><?= e($p['nim']) ?> - <?= e($p['nama_lengkap']) ?></td><td><?= e($p['nama_tagihan']) ?><br><span class="keterangan"><?= e($p['kode_tagihan']) ?><?= $p['referensi'] ? ' | ' . e($p['referensi']) : '' ?></span></td><td class="angka"><?= rupiah($p['jumlah']) ?></td><td><?= e($p['metode']) ?></td>
        <td><?= lencana($p['status']) ?><?php if ($p['alasan']): ?><br><span class="keterangan"><?= e($p['alasan']) ?></span><?php endif; ?></td>
        <td><div class="aksi">
            <?php if (bisa('A') && $p['status'] === 'Menunggu'): ?><form method="post"><input type="hidden" name="aksi" value="verifikasi"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" class="tombol hijau kecil">Verifikasi</button></form><?php endif; ?>
            <?php if (bisa('U') && $p['status'] === 'Menunggu'): ?><a href="?edit=<?= $p['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
            <?php if (bisa('A') && $p['status'] !== 'Ditolak'): ?><form method="post" class="aksi"><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="text" name="alasan" placeholder="Alasan tolak" required><button type="submit" class="tombol merah kecil">Tolak</button></form><?php endif; ?>
            <?php if ($p['status'] === 'Terverifikasi'): ?><a href="cetak.php?jenis=kuitansi&amp;id=<?= $p['id'] ?>" target="_blank" class="tombol abu kecil">Kuitansi</a><?php endif; ?>
        </div></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
