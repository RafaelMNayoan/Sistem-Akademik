<?php
require_once '../koneksi.php';
$user = wajib_portal('krs');
$role = $user['role'];
$bp = prodi_batas();
$did = (int) ($user['dosen_id'] ?? 0);
$lihatIsi = $role !== 'bendahara'; // Bendahara hanya melihat status bayar

/* Apakah mahasiswa ini dalam cakupan peran (prodi untuk Kaprodi, bimbingan untuk Dosen)? */
function mhs_dalam_cakupan($m)
{
    global $role, $bp, $did;
    if (!$m) return false;
    if ($role === 'kaprodi') return $m['program_studi'] === $bp;
    if ($role === 'dosen') return $did > 0 && (int) $m['dosen_pa_id'] === $did;
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'setujui' || $aksi === 'tolak') {
        $nim = post('nim');
        $sem = (int) post('semester');
        $m = ambil_satu('SELECT nim, program_studi, dosen_pa_id FROM mahasiswa WHERE nim = ?', [$nim]);
        $jumlah = ambil_satu('SELECT COUNT(*) AS n FROM krs WHERE nim = ? AND semester = ?', [$nim, $sem]);
        if (!bisa('A') || !mhs_dalam_cakupan($m) || (int) $jumlah['n'] === 0) {
            pesan('Anda tidak berhak memproses KRS ini.', 'gagal');
        } elseif ($aksi === 'setujui' && ambil_pengaturan('krs_wajib_lunas') === '1' && jumlah_tunggakan($nim) > 0) {
            pesan('KRS belum bisa disetujui: mahasiswa masih memiliki tagihan yang belum lunas (syarat bayar KRS aktif).', 'gagal');
        } elseif ($aksi === 'tolak' && post('catatan') === '') {
            pesan('Tuliskan alasan penolakan pada kolom catatan.', 'gagal');
        } else {
            $baru = $aksi === 'setujui' ? 'Disetujui' : 'Ditolak';
            jalankan('UPDATE krs SET status = ?, diproses_oleh = ?, diproses_pada = ?, catatan = ? WHERE nim = ? AND semester = ?',
                     [$baru, $user['nama'], date('Y-m-d H:i:s'), kosong_ke_null(post('catatan')), $nim, $sem]);
            catat_log('krs', $aksi . ' KRS', "nim $nim semester $sem -> $baru");
            pesan('KRS ' . $nim . ' semester ' . $sem . ' ' . strtolower($baru) . '.');
        }
        pindah('krs.php');
    }

    if ($aksi === 'setujui_semua') {
        if (!bisa('A')) { pesan('Anda tidak berhak menyetujui KRS.', 'gagal'); pindah('krs.php'); }
        $syaratSemua = "k.status = 'Diajukan'"; $pSemua = [];
        if ($role === 'kaprodi') { $syaratSemua .= ' AND mh.program_studi = ?'; $pSemua[] = $bp; }
        if ($role === 'dosen') { $syaratSemua .= ' AND mh.dosen_pa_id = ?'; $pSemua[] = $did; }
        $antrian = ambil_semua("SELECT DISTINCT k.nim, k.semester FROM krs k JOIN mahasiswa mh ON mh.nim = k.nim WHERE $syaratSemua", $pSemua);
        $ok = 0; $lewat = 0;
        foreach ($antrian as $q) {
            if (ambil_pengaturan('krs_wajib_lunas') === '1' && jumlah_tunggakan($q['nim']) > 0) { $lewat++; continue; }
            jalankan("UPDATE krs SET status = 'Disetujui', diproses_oleh = ?, diproses_pada = ? WHERE nim = ? AND semester = ? AND status = 'Diajukan'", [$user['nama'], date('Y-m-d H:i:s'), $q['nim'], $q['semester']]);
            $ok++;
        }
        catat_log('krs', 'setujui semua KRS', "$ok disetujui, $lewat dilewati (belum lunas)");
        pesan("$ok KRS disetujui" . ($lewat ? ", $lewat dilewati karena belum lunas." : '.'));
        pindah('krs.php');
    }

    if ($aksi === 'simpan') {
        $id = (int) post('id');
        $lama = ambil_satu('SELECT * FROM krs WHERE id = ?', [$id]);
        $sem = (int) post('semester');
        $kode = post('kode_mk');
        if (!bisa('U') || !$lama) {
            pesan('Anda tidak berhak mengubah KRS.', 'gagal');
        } elseif ($sem < 1 || $sem > 14 || !ambil_satu('SELECT kode_mk FROM mata_kuliah WHERE kode_mk = ?', [$kode])) {
            pesan('Semester (1-14) dan mata kuliah harus valid.', 'gagal');
        } else {
            try {
                jalankan("UPDATE krs SET semester = ?, kode_mk = ?, status = 'Diajukan', diproses_oleh = ?, diproses_pada = ?, catatan = NULL WHERE id = ?", [$sem, $kode, $user['nama'], date('Y-m-d H:i:s'), $id]);
                catat_log('krs', 'ubah KRS', "id $id nim {$lama['nim']}: " . beda($lama, ['semester' => $sem, 'kode_mk' => $kode]) . ' (status kembali Diajukan)');
                pesan('KRS diperbarui dan diajukan ulang untuk persetujuan.');
            } catch (PDOException $ex) {
                pesan('Mata kuliah itu sudah ada di KRS mahasiswa pada semester tersebut.', 'gagal');
            }
        }
        pindah('krs.php');
    }
    pindah('krs.php');
}

$edit = get('edit') !== '' && bisa('U') ? ambil_satu('SELECT k.*, mh.nama_lengkap FROM krs k JOIN mahasiswa mh ON mh.nim = k.nim WHERE k.id = ?', [(int) get('edit')]) : null;

$fStatus = get('status');
$cari = get('cari');
$syarat = "(mh.nim LIKE ? OR mh.nama_lengkap LIKE ?) AND (? = '' OR k.status = ?)";
$param = ["%$cari%", "%$cari%", $fStatus, $fStatus];
if ($role === 'kaprodi') { $syarat .= ' AND mh.program_studi = ?'; $param[] = $bp; }
if ($role === 'dosen') { $syarat .= ' AND mh.dosen_pa_id = ?'; $param[] = $did; }
$baris = ambil_semua("SELECT k.id, k.nim, k.semester, k.status, k.catatan, k.diproses_oleh, mh.nama_lengkap, mh.program_studi, mh.dosen_pa_id, m.kode_mk, m.nama_mk, m.sks
    FROM krs k JOIN mahasiswa mh ON mh.nim = k.nim JOIN mata_kuliah m ON m.kode_mk = k.kode_mk
    WHERE $syarat ORDER BY k.nim, k.semester, m.kode_mk", $param);
$grup = [];
foreach ($baris as $b) {
    $g = $b['nim'] . '|' . $b['semester'];
    $grup[$g]['nim'] = $b['nim'];
    $grup[$g]['nama'] = $b['nama_lengkap'];
    $grup[$g]['semester'] = $b['semester'];
    $grup[$g]['program_studi'] = $b['program_studi'];
    $grup[$g]['dosen_pa_id'] = $b['dosen_pa_id'];
    $grup[$g]['sks'] = ($grup[$g]['sks'] ?? 0) + (int) $b['sks'];
    $grup[$g]['status'][$b['status']] = true;
    $grup[$g]['catatan'] = $grup[$g]['catatan'] ?? $b['catatan'];
    $grup[$g]['mk'][] = $b;
}

$judul = 'KRS';
include '../includes/header.php';
?>
<h1>KRS Mahasiswa</h1>
<?php if ($edit): ?>
<div class="kartu">
    <h2>Ubah KRS <?= e($edit['nim']) ?> - <?= e($edit['nama_lengkap']) ?></h2>
    <form method="post" class="form-isi">
        <input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $edit['id'] ?>">
        <div><label for="kode_mk">Mata kuliah</label><select id="kode_mk" name="kode_mk" required><?= opsi(daftar_matkul(), $edit['kode_mk']) ?></select></div>
        <div><label for="semester">Semester</label><input type="number" id="semester" name="semester" min="1" max="14" value="<?= e($edit['semester']) ?>" required></div>
        <div class="penuh"><small>Setelah diubah, status KRS kembali menjadi "Diajukan" dan harus disetujui ulang.</small></div>
        <div class="penuh baris-tombol"><button type="submit" class="tombol">Simpan perubahan</button><a href="krs.php" class="tombol abu">Batal</a></div>
    </form>
</div>
<?php endif; ?>
<div class="kartu">
    <h2>Daftar KRS<?= $role === 'dosen' ? ' mahasiswa bimbingan saya' : ($bp !== null ? ' prodi ' . e($bp) : '') ?></h2>
    <form method="get" class="filter">
        <div><label for="cari">Cari NIM atau nama</label><input type="text" id="cari" name="cari" value="<?= e($cari) ?>"></div>
        <div><label for="status">Status</label><select id="status" name="status"><option value="">Semua</option><?= opsi(sama(['Diajukan', 'Disetujui', 'Ditolak']), $fStatus) ?></select></div>
        <button type="submit" class="tombol abu">Saring</button>
    </form>
    <?php if (bisa('A')): ?><form method="post" onsubmit="return confirm('Setujui semua KRS berstatus Diajukan?')" style="margin-bottom:12px"><input type="hidden" name="aksi" value="setujui_semua"><button type="submit" class="tombol hijau">Setujui semua KRS yang diajukan</button></form><?php endif; ?>
    <?php if (!$grup): ?><p class="kosong">Belum ada data KRS.</p><?php else: ?>
    <div class="gulir"><table>
        <tr><th>Mahasiswa</th><th>Sem</th><?php if ($lihatIsi): ?><th>Mata kuliah</th><?php endif; ?><th class="angka">SKS</th><th>Status KRS</th><th>Status bayar</th><?php if (bisa('A') || bisa('U')): ?><th></th><?php endif; ?></tr>
        <?php foreach ($grup as $g): $stat = array_keys($g['status']); $ringkas = count($stat) === 1 ? $stat[0] : 'Diajukan'; $tung = jumlah_tunggakan($g['nim']);
              $punya = mhs_dalam_cakupan(['program_studi' => $g['program_studi'], 'dosen_pa_id' => $g['dosen_pa_id']]); ?>
        <tr>
            <td><?= e($g['nim']) ?> - <?= e($g['nama']) ?></td><td><?= e($g['semester']) ?></td>
            <?php if ($lihatIsi): ?><td><?php foreach ($g['mk'] as $r): ?><div><?= e($r['kode_mk']) ?> - <?= e($r['nama_mk']) ?> <?php if (count($stat) > 1): ?><?= lencana($r['status']) ?><?php endif; ?><?php if (bisa('U')): ?> <a href="?edit=<?= $r['id'] ?>">ubah</a><?php endif; ?></div><?php endforeach; ?></td><?php endif; ?>
            <td class="angka"><?= $g['sks'] ?></td>
            <td><?= lencana($ringkas) ?><?php if ($g['catatan']): ?><br><span class="keterangan"><?= e($g['catatan']) ?></span><?php endif; ?></td>
            <td><?= $tung === 0 ? lencana('Lunas') : lencana('Belum lunas') ?></td>
            <?php if (bisa('A') || bisa('U')): ?><td>
                <?php if (bisa('A') && $punya): ?>
                <form method="post" class="aksi"><input type="hidden" name="nim" value="<?= e($g['nim']) ?>"><input type="hidden" name="semester" value="<?= e($g['semester']) ?>">
                    <input type="text" name="catatan" placeholder="Catatan (wajib bila ditolak)">
                    <?php if ($ringkas !== 'Disetujui'): ?><button type="submit" name="aksi" value="setujui" class="tombol hijau kecil">Setujui</button><?php endif; ?>
                    <?php if ($ringkas !== 'Ditolak'): ?><button type="submit" name="aksi" value="tolak" class="tombol merah kecil">Tolak</button><?php endif; ?></form>
                <?php endif; ?></td><?php endif; ?>
        </tr><?php endforeach; ?>
    </table></div><?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
