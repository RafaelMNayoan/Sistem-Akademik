<?php
require_once '../koneksi.php';
$user = wajib_portal('tagihan');
$role = $user['role'];
$bp = prodi_batas();
$rinci = $role !== 'staff';     // Staff hanya melihat status bayar
perbarui_keterlambatan();

function kode_tagihan() { return 'TGH-' . date('ymdHis') . '-' . random_int(100, 999); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');

    if ($aksi === 'batal') {
        $t = ambil_satu('SELECT * FROM tagihan WHERE id = ?', [$id]);
        if (!bisa('D') || !$t) { pesan('Anda tidak berhak membatalkan tagihan.', 'gagal'); }
        elseif (post('alasan') === '') { pesan('Alasan pembatalan wajib diisi.', 'gagal'); }
        elseif ($t['status'] === 'Dibatalkan') { pesan('Tagihan sudah dibatalkan.', 'gagal'); }
        elseif (terbayar_tagihan($id) > 0) { pesan('Tagihan sudah memiliki pembayaran terverifikasi, tidak bisa dibatalkan.', 'gagal'); }
        else {
            jalankan("UPDATE tagihan SET status = 'Dibatalkan', alasan_batal = ? WHERE id = ?", [post('alasan'), $id]);
            catat_log('tagihan', 'batalkan tagihan', "{$t['kode_tagihan']} ({$t['nim']}): {$t['status']} -> Dibatalkan, alasan " . post('alasan'), 'keuangan');
            pesan('Tagihan dibatalkan. Data tetap tersimpan untuk audit.');
        }
        pindah('tagihan.php');
    }

    if ($aksi === 'terbitkan') {
        $kb = ambil_satu('SELECT * FROM komponen_biaya WHERE id = ? AND aktif = 1', [(int) post('komponen_id')]);
        $sem = (int) post('semester');
        $tempo = post('tanggal_jatuh_tempo');
        if (!bisa('C') || !$kb || $sem < 1 || $sem > 14 || !strtotime($tempo)) {
            pesan('Pilih komponen biaya aktif, semester (1-14), dan jatuh tempo.', 'gagal');
        } else {
            $sasaran = ambil_semua("SELECT nim FROM mahasiswa WHERE status = 'Aktif' AND (? IS NULL OR program_studi = ?) AND (? IS NULL OR angkatan = ?)", [$kb['prodi'], $kb['prodi'], $kb['angkatan'], $kb['angkatan']]);
            $n = 0;
            foreach ($sasaran as $s) {
                if (ambil_satu("SELECT id FROM tagihan WHERE nim = ? AND semester = ? AND nama_tagihan = ? AND status <> 'Dibatalkan'", [$s['nim'], $sem, $kb['nama']])) continue;
                jalankan("INSERT INTO tagihan (nim, semester, kode_tagihan, nama_tagihan, biaya, potongan, total_bayar, tanggal_jatuh_tempo, status, dibuat_oleh) VALUES (?, ?, ?, ?, ?, 0, ?, ?, 'Belum Dibayar', ?)",
                         [$s['nim'], $sem, kode_tagihan(), $kb['nama'], $kb['nominal'], $kb['nominal'], $tempo, $user['username']]);
                $n++;
            }
            catat_log('tagihan', 'terbitkan tagihan massal', "{$kb['nama']} semester $sem: $n tagihan", 'keuangan');
            pesan($n . ' tagihan diterbitkan (tagihan yang sudah ada dilewati).');
        }
        pindah('tagihan.php');
    }

    // simpan satu tagihan
    $data = ['nim' => post('nim'), 'semester' => (int) post('semester'), 'nama_tagihan' => post('nama_tagihan'), 'biaya' => (float) post('biaya'), 'potongan' => (float) post('potongan'), 'tanggal_jatuh_tempo' => post('tanggal_jatuh_tempo')];
    if ($id ? !bisa('U') : !bisa('C')) { pesan('Anda tidak berhak menyimpan tagihan.', 'gagal'); pindah('tagihan.php'); }
    $lama = $id ? ambil_satu('SELECT * FROM tagihan WHERE id = ?', [$id]) : null;
    if (!ambil_satu('SELECT nim FROM mahasiswa WHERE nim = ?', [$data['nim']]) || $data['nama_tagihan'] === '' || $data['semester'] < 1 || $data['semester'] > 14 || !strtotime($data['tanggal_jatuh_tempo'])) {
        pesan('Mahasiswa, semester, nama tagihan, dan jatuh tempo harus valid.', 'gagal');
    } elseif ($data['biaya'] < 0 || $data['potongan'] < 0 || $data['potongan'] > $data['biaya']) {
        pesan('Biaya tidak boleh minus dan potongan tidak boleh melebihi biaya.', 'gagal');
    } elseif ($id && (!$lama || $lama['status'] === 'Dibatalkan')) {
        pesan('Tagihan tidak ditemukan atau sudah dibatalkan.', 'gagal');
    } elseif ($id && terbayar_tagihan($id) > $data['biaya'] - $data['potongan']) {
        pesan('Total baru lebih kecil daripada jumlah yang sudah dibayar.', 'gagal');
    } else {
        $total = $data['biaya'] - $data['potongan'];
        if ($id) {
            jalankan('UPDATE tagihan SET nim = ?, semester = ?, nama_tagihan = ?, biaya = ?, potongan = ?, tanggal_jatuh_tempo = ?, total_bayar = ? WHERE id = ?', array_merge(array_values($data), [$total, $id]));
            hitung_status_tagihan($id);
            catat_log('tagihan', 'ubah tagihan', "{$lama['kode_tagihan']}: " . beda($lama, $data), 'keuangan');
            pesan('Tagihan diperbarui.');
        } else {
            $kode = kode_tagihan();
            jalankan("INSERT INTO tagihan (nim, semester, kode_tagihan, nama_tagihan, biaya, potongan, total_bayar, tanggal_jatuh_tempo, status, dibuat_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Belum Dibayar', ?)",
                     [$data['nim'], $data['semester'], $kode, $data['nama_tagihan'], $data['biaya'], $data['potongan'], $total, $data['tanggal_jatuh_tempo'], $user['username']]);
            catat_log('tagihan', 'buat tagihan', "$kode {$data['nim']} " . rupiah($total), 'keuangan');
            pesan('Tagihan diterbitkan dengan kode ' . $kode . '.');
        }
    }
    pindah('tagihan.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT * FROM tagihan WHERE id = ?', [(int) get('edit')]) : null;
$fStatus = get('status'); $cari = get('cari');
$syarat = "(t.nim LIKE ? OR mh.nama_lengkap LIKE ?) AND (? = '' OR t.status = ?)";
$param = ["%$cari%", "%$cari%", $fStatus, $fStatus];
if ($bp !== null) { $syarat .= ' AND mh.program_studi = ?'; $param[] = $bp; }
$daftar = ambil_semua("SELECT t.*, mh.nama_lengkap, mh.program_studi FROM tagihan t JOIN mahasiswa mh ON mh.nim = t.nim WHERE $syarat ORDER BY t.id DESC LIMIT 300", $param);
$komponen = [];
foreach (ambil_semua('SELECT * FROM komponen_biaya WHERE aktif = 1 ORDER BY nama') as $k) { $komponen[$k['id']] = $k['nama'] . ' - ' . rupiah($k['nominal']) . ' (' . ($k['prodi'] ?: 'semua prodi') . ', ' . ($k['angkatan'] ?: 'semua angkatan') . ')'; }

$judul = 'Tagihan Mahasiswa';
include '../includes/header.php';
?>
<h1>Tagihan Mahasiswa</h1>
<?php if (bisa('C') && !$edit): ?>
<div class="kartu"><h2>Terbitkan tagihan dari komponen biaya</h2>
    <?php if (!$komponen): ?><p class="kosong">Belum ada komponen biaya aktif. Tambahkan di menu Biaya.</p><?php else: ?>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="terbitkan">
        <div class="penuh"><label for="komponen_id">Komponen biaya (sasaran mengikuti prodi &amp; angkatan komponen)</label><select id="komponen_id" name="komponen_id"><?= opsi($komponen) ?></select></div>
        <div><label for="sem2">Semester</label><input type="number" id="sem2" name="semester" min="1" max="14" value="1" required></div>
        <div><label for="tempo2">Jatuh tempo</label><input type="date" id="tempo2" name="tanggal_jatuh_tempo" required></div>
        <div class="penuh"><button type="submit" class="tombol hijau" onclick="return confirm('Terbitkan tagihan ke semua mahasiswa aktif sasaran?')">Terbitkan tagihan</button></div>
    </form><?php endif; ?>
</div>
<?php endif; ?>
<?php if (bisa('C') || $edit): ?>
<div class="kartu"><h2><?= $edit ? 'Ubah tagihan ' . e($edit['kode_tagihan']) : 'Tambah tagihan per mahasiswa' ?></h2>
    <form method="post" class="form-isi"><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>">
        <div><label for="nim">Mahasiswa</label><select id="nim" name="nim" required><?= opsi(daftar_mahasiswa(), $edit['nim'] ?? '') ?></select></div>
        <div><label for="semester">Semester</label><input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester'] ?? 1) ?>" required></div>
        <div><label for="nama_tagihan">Nama tagihan</label><input type="text" id="nama_tagihan" name="nama_tagihan" placeholder="SPP Semester 1" value="<?= e($edit['nama_tagihan'] ?? '') ?>" required></div>
        <div><label for="tempo">Jatuh tempo</label><input type="date" id="tempo" name="tanggal_jatuh_tempo" value="<?= e($edit['tanggal_jatuh_tempo'] ?? '') ?>" required></div>
        <div><label for="biaya">Biaya (Rp)</label><input type="number" id="biaya" name="biaya" min="0" step="1000" value="<?= e($edit['biaya'] ?? '') ?>" required></div>
        <div><label for="potongan">Potongan (Rp)</label><input type="number" id="potongan" name="potongan" min="0" step="1000" value="<?= e($edit['potongan'] ?? 0) ?>" required></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Tambah tagihan' ?></button><?php if ($edit): ?><a href="tagihan.php" class="tombol abu">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu"><h2>Daftar tagihan<?= $bp !== null ? ' prodi ' . e($bp) : '' ?><?= $role === 'staff' ? ' (status bayar)' : '' ?></h2>
    <form method="get" class="filter"><div><label for="cari">Cari NIM atau nama</label><input type="text" id="cari" name="cari" value="<?= e($cari) ?>"></div>
        <div><label for="fs">Status</label><select id="fs" name="status"><option value="">Semua</option><?= opsi(sama(['Belum Dibayar', 'Sebagian', 'Dibayar', 'Terlambat', 'Dibatalkan']), $fStatus) ?></select></div><button type="submit" class="tombol abu">Saring</button></form>
    <?php if (!$daftar): ?><p class="kosong">Belum ada tagihan.</p><?php else: ?>
    <div class="gulir"><table><tr><th>Mahasiswa</th><th>Sem</th><th>Tagihan</th><?php if ($rinci): ?><th class="angka">Total</th><th class="angka">Terbayar</th><th>Jatuh tempo</th><?php endif; ?><th>Status</th><?php if (bisa('U') || bisa('D')): ?><th></th><?php endif; ?></tr>
    <?php foreach ($daftar as $t): ?><tr><td><?= e($t['nim']) ?> - <?= e($t['nama_lengkap']) ?></td><td><?= e($t['semester']) ?></td><td><?= e($t['nama_tagihan']) ?><?php if ($rinci): ?><br><span class="keterangan"><?= e($t['kode_tagihan']) ?></span><?php endif; ?></td>
        <?php if ($rinci): ?><td class="angka"><?= rupiah($t['total_bayar']) ?></td><td class="angka"><?= rupiah(terbayar_tagihan($t['id'])) ?></td><td><?= tanggal($t['tanggal_jatuh_tempo']) ?></td><?php endif; ?>
        <td><?= lencana($t['status']) ?><?php if ($t['alasan_batal']): ?><br><span class="keterangan"><?= e($t['alasan_batal']) ?></span><?php endif; ?></td>
        <?php if (bisa('U') || bisa('D')): ?><td><?php if ($t['status'] !== 'Dibatalkan'): ?><div class="aksi">
            <?php if (bisa('U')): ?><a href="?edit=<?= $t['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
            <?php if (bisa('D') && $t['status'] !== 'Dibayar'): ?><form method="post" class="aksi" onsubmit="return confirm('Batalkan tagihan ini?')"><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="text" name="alasan" placeholder="Alasan pembatalan" required><button type="submit" class="tombol merah kecil">Batalkan</button></form><?php endif; ?>
        </div><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
