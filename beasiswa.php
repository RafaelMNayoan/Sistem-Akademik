<?php
require_once '../koneksi.php';
$user = wajib_portal('beasiswa');
$role = $user['role'];
$bp = prodi_batas();

function atur_potongan($b, $arah)
{
    // arah +1 = terapkan potongan ke tagihan, -1 = cabut
    if (!$b['tagihan_id'] || (float) $b['nilai_potongan'] <= 0) return;
    $t = ambil_satu('SELECT * FROM tagihan WHERE id = ?', [$b['tagihan_id']]);
    if (!$t || $t['status'] === 'Dibatalkan') return;
    $pot = max(0, min((float) $t['biaya'], (float) $t['potongan'] + $arah * (float) $b['nilai_potongan']));
    jalankan('UPDATE tagihan SET potongan = ?, total_bayar = ? WHERE id = ?', [$pot, (float) $t['biaya'] - $pot, $t['id']]);
    hitung_status_tagihan($t['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    $id = (int) post('id');
    $b = $id ? ambil_satu('SELECT b.*, mh.program_studi FROM beasiswa b JOIN mahasiswa mh ON mh.nim = b.nim WHERE b.id = ?', [$id]) : null;
    $dalam = !$b || $bp === null || $b['program_studi'] === $bp;
    if ($b && !$dalam) {
        pesan('Data di luar prodi Anda.', 'gagal');
        pindah('beasiswa.php');
    }

    if ($aksi === 'prodi_setuju' || $aksi === 'prodi_tolak') { // Kaprodi: A atas usulan
        if (!bisa('A') || $role === 'bendahara' || !$b || $b['status'] !== 'Diusulkan') {
            pesan('Usulan tidak dapat diproses.', 'gagal');
        } else {
            $baru = $aksi === 'prodi_setuju' ? 'Disetujui Prodi' : 'Ditolak';
            jalankan('UPDATE beasiswa SET status = ?, disetujui_prodi_oleh = ? WHERE id = ?', [$baru, $user['nama'], $id]);
            catat_log('beasiswa', 'keputusan prodi', "id $id nim {$b['nim']}: Diusulkan -> $baru", 'keuangan');
            pesan('Usulan ' . ($baru === 'Ditolak' ? 'ditolak.' : 'disetujui prodi. Menunggu persetujuan akhir Bendahara.'));
        }
        pindah('beasiswa.php');
    }
    if ($aksi === 'final' || $aksi === 'tolak' || $aksi === 'cabut') { // Bendahara
        if (!bisa('A') || $role !== 'bendahara' || !$b) {
            pesan('Hanya Bendahara yang memberi persetujuan akhir.', 'gagal');
        } elseif ($aksi === 'final' && $b['status'] !== 'Disetujui Prodi') {
            pesan('Persetujuan akhir hanya setelah usulan disetujui Kaprodi (pemisahan tugas).', 'gagal');
        } elseif ($aksi === 'tolak' && !in_array($b['status'], ['Diusulkan', 'Disetujui Prodi'], true)) {
            pesan('Status tidak dapat ditolak.', 'gagal');
        } elseif ($aksi === 'cabut' && $b['status'] !== 'Disetujui') {
            pesan('Hanya yang sudah disetujui yang dapat dicabut.', 'gagal');
        } else {
            $baru = ['final' => 'Disetujui', 'tolak' => 'Ditolak', 'cabut' => 'Dicabut'][$aksi];
            jalankan('UPDATE beasiswa SET status = ?, disetujui_oleh = ? WHERE id = ?', [$baru, $user['nama'], $id]);
            if ($aksi === 'final') atur_potongan($b, 1);
            if ($aksi === 'cabut') atur_potongan($b, -1);
            catat_log('beasiswa', $aksi . ' beasiswa/keringanan', "id $id nim {$b['nim']} " . rupiah($b['nilai_potongan']) . ": {$b['status']} -> $baru", 'keuangan');
            pesan('Status diubah menjadi ' . $baru . '.');
        }
        pindah('beasiswa.php');
    }

    // simpan
    $data = ['nim' => post('nim'), 'jenis' => post('jenis'), 'nama' => post('nama'), 'nilai_potongan' => (float) post('nilai_potongan'), 'tagihan_id' => (int) post('tagihan_id') ?: null, 'keterangan' => kosong_ke_null(post('keterangan'))];
    if ($id ? !bisa('U') : !bisa('C')) {
        pesan('Anda tidak berhak menyimpan.', 'gagal');
        pindah('beasiswa.php');
    }
    $tg = $data['tagihan_id'] ? ambil_satu('SELECT nim, status FROM tagihan WHERE id = ?', [$data['tagihan_id']]) : null;
    if (!ambil_satu('SELECT nim FROM mahasiswa WHERE nim = ?', [$data['nim']]) || !in_array($data['jenis'], ['Beasiswa', 'Keringanan', 'Cicilan', 'Dispensasi'], true) || $data['nama'] === '' || $data['nilai_potongan'] < 0) {
        pesan('Mahasiswa, jenis, nama, dan nilai harus valid.', 'gagal');
    } elseif ($data['tagihan_id'] && (!$tg || $tg['nim'] !== $data['nim'] || $tg['status'] === 'Dibatalkan')) {
        pesan('Tagihan harus milik mahasiswa yang sama dan belum dibatalkan.', 'gagal');
    } elseif ($id && (!$b || $b['status'] !== 'Diusulkan')) {
        pesan('Hanya usulan berstatus Diusulkan yang dapat diubah.', 'gagal');
    } elseif ($id) {
        jalankan('UPDATE beasiswa SET nim = ?, jenis = ?, nama = ?, nilai_potongan = ?, tagihan_id = ?, keterangan = ? WHERE id = ?', array_merge(array_values($data), [$id]));
        catat_log('beasiswa', 'ubah usulan', "id $id: " . beda($b, $data), 'keuangan');
        pesan('Usulan diperbarui.');
    } else {
        jalankan("INSERT INTO beasiswa (nim, jenis, nama, nilai_potongan, tagihan_id, keterangan, status, dibuat_oleh, dibuat) VALUES (?, ?, ?, ?, ?, ?, 'Diusulkan', ?, ?)", array_merge(array_values($data), [$user['nama'], date('Y-m-d H:i:s')]));
        catat_log('beasiswa', 'buat usulan', "{$data['nim']} {$data['jenis']} " . rupiah($data['nilai_potongan']), 'keuangan');
        pesan('Usulan dibuat dan menunggu persetujuan Kaprodi.');
    }
    pindah('beasiswa.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu("SELECT * FROM beasiswa WHERE id = ? AND status = 'Diusulkan'", [(int) get('edit')]) : null;
$syarat = '1 = 1';
$param = [];
if ($bp !== null) {
    $syarat = 'mh.program_studi = ?';
    $param = [$bp];
}
$daftar = ambil_semua("SELECT b.*, mh.nama_lengkap, mh.program_studi, t.kode_tagihan FROM beasiswa b JOIN mahasiswa mh ON mh.nim = b.nim LEFT JOIN tagihan t ON t.id = b.tagihan_id WHERE $syarat ORDER BY b.id DESC LIMIT 200", $param);
$tagihanOps = [];
foreach (ambil_semua("SELECT id, nim, nama_tagihan, total_bayar FROM tagihan WHERE status NOT IN ('Dibayar','Dibatalkan') ORDER BY nim, id") as $t) {
    $tagihanOps[$t['id']] = $t['nim'] . ' | ' . $t['nama_tagihan'] . ' | ' . rupiah($t['total_bayar']);
}

$judul = 'Beasiswa & Keringanan';
include '../includes/header.php';
?>
<h1>Beasiswa & Keringanan</h1>
<?php if (bisa('C') || $edit): ?>
    <div class="kartu">
        <h2><?= $edit ? 'Ubah usulan' : 'Buat usulan beasiswa / keringanan' ?></h2>
        <p class="keterangan">Alur: usulan dibuat Bendahara, disetujui Kaprodi (tingkat prodi), lalu disetujui akhir Bendahara. Potongan baru berlaku ke tagihan setelah persetujuan akhir.</p>
        <form method="post" class="form-isi"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : '' ?>"><input type="hidden" name="aksi" value="simpan">
            <div><label for="nim">Mahasiswa</label><select id="nim" name="nim" required><?= opsi(daftar_mahasiswa(), $edit['nim'] ?? '') ?></select></div>
            <div><label for="jenis">Jenis</label><select id="jenis" name="jenis"><?= opsi(sama(['Beasiswa', 'Keringanan', 'Cicilan', 'Dispensasi']), $edit['jenis'] ?? '') ?></select></div>
            <div><label for="nama">Nama program</label><input type="text" id="nama" name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></div>
            <div><label for="nilai_potongan">Nilai potongan (Rp, 0 bila cicilan/dispensasi)</label><input type="number" id="nilai_potongan" name="nilai_potongan" min="0" step="1000" value="<?= e($edit['nilai_potongan'] ?? 0) ?>" required></div>
            <div class="penuh"><label for="tagihan_id">Terapkan ke tagihan (opsional)</label><select id="tagihan_id" name="tagihan_id">
                    <option value="">Tidak langsung ke tagihan</option><?= opsi($tagihanOps, $edit['tagihan_id'] ?? '') ?>
                </select></div>
            <div class="penuh"><label for="keterangan">Keterangan / alasan</label><input type="text" id="keterangan" name="keterangan" value="<?= e($edit['keterangan'] ?? '') ?>"></div>
            <div class="penuh baris-tombol"><button type="submit" class="tombol"><?= $edit ? 'Simpan perubahan' : 'Buat usulan' ?></button><?php if ($edit): ?><a href="beasiswa.php" class="tombol abu">Batal</a><?php endif; ?></div>
        </form>
    </div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar beasiswa & keringanan<?= $bp !== null ? ' prodi ' . e($bp) : '' ?></h2>
    <?php if (!$daftar): ?><p class="kosong">Belum ada data.</p><?php else: ?>
        <div class="gulir">
            <table>
                <tr>
                    <th>Mahasiswa</th>
                    <th>Jenis</th>
                    <th>Program</th>
                    <th class="angka">Potongan</th>
                    <th>Tagihan</th>
                    <th>Status</th><?php if (bisa('A') || bisa('U')): ?><th></th><?php endif; ?>
                </tr>
                <?php foreach ($daftar as $b): ?><tr>
                        <td><?= e($b['nim']) ?> - <?= e($b['nama_lengkap']) ?></td>
                        <td><?= e($b['jenis']) ?></td>
                        <td><?= e($b['nama']) ?><?php if ($b['keterangan']): ?><br><span class="keterangan"><?= e($b['keterangan']) ?></span><?php endif; ?></td>
                        <td class="angka"><?= rupiah($b['nilai_potongan']) ?></td>
                        <td><?= e($b['kode_tagihan'] ?: '-') ?></td>
                        <td><?= lencana($b['status']) ?></td>
                        <?php if (bisa('A') || bisa('U')): ?><td>
                                <div class="aksi">
                                    <?php if (bisa('U') && $b['status'] === 'Diusulkan'): ?><a href="?edit=<?= $b['id'] ?>" class="tombol kecil">Ubah</a><?php endif; ?>
                                    <?php if (bisa('A') && $role !== 'bendahara' && $b['status'] === 'Diusulkan'): ?><form method="post" class="aksi"><input type="hidden" name="id" value="<?= $b['id'] ?>"><button type="submit" name="aksi" value="prodi_setuju" class="tombol hijau kecil">Setujui (prodi)</button><button type="submit" name="aksi" value="prodi_tolak" class="tombol merah kecil">Tolak</button></form><?php endif; ?>
                                    <?php if ($role === 'bendahara' && bisa('A')): ?><form method="post" class="aksi"><input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <?php if ($b['status'] === 'Disetujui Prodi'): ?><button type="submit" name="aksi" value="final" class="tombol hijau kecil">Setujui akhir</button><?php endif; ?>
                                            <?php if (in_array($b['status'], ['Diusulkan', 'Disetujui Prodi'], true)): ?><button type="submit" name="aksi" value="tolak" class="tombol merah kecil">Tolak</button><?php endif; ?>
                                            <?php if ($b['status'] === 'Disetujui'): ?><button type="submit" name="aksi" value="cabut" class="tombol abu kecil" onclick="return confirm('Cabut dan batalkan potongan?')">Cabut</button><?php endif; ?></form><?php endif; ?>
                                </div>
                            </td><?php endif; ?>
                    </tr><?php endforeach; ?>
            </table>
        </div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>