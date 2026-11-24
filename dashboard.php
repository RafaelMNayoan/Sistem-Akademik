<?php
require_once '../koneksi.php';
$user = wajib_portal();
$role = $user['role'];
if ($role === 'admin') {
    pindah('../admin/dashboard.php');
}
$hak = matriks_hak_akses()[$role] ?? [];
$bp = prodi_batas() ?? '';
$kartu = [];

if ($role === 'kaprodi') {
    $kodeMk = mk_prodi($bp);
    [$in, $pi] = daftar_in($kodeMk);
    $kartu = [
        ['Mahasiswa prodi', ambil_satu('SELECT COUNT(*) AS n FROM mahasiswa WHERE program_studi = ?', [$bp])['n']],
        ['Dosen prodi', ambil_satu('SELECT COUNT(*) AS n FROM dosen WHERE prodi = ?', [$bp])['n']],
        ['KRS menunggu persetujuan (buka menu KRS)', ambil_satu("SELECT COUNT(DISTINCT k.nim) AS n FROM krs k JOIN mahasiswa mh ON mh.nim = k.nim WHERE k.status = 'Diajukan' AND mh.program_studi = ?", [$bp])['n']],
        ['Jadwal menunggu persetujuan', ambil_satu("SELECT COUNT(*) AS n FROM kelas_kuliah WHERE status = 'Diajukan' AND kode_mk IN ($in)", $pi)['n']],
        ['Skripsi menunggu', ambil_satu("SELECT COUNT(*) AS n FROM skripsi s JOIN mahasiswa mh ON mh.nim = s.nim WHERE s.status = 'Diajukan' AND mh.program_studi = ?", [$bp])['n']],
        ['Surat menunggu', ambil_satu("SELECT COUNT(*) AS n FROM surat WHERE status IN ('Diajukan','Diproses') AND prodi = ?", [$bp])['n']],
        ['Usulan beasiswa', ambil_satu("SELECT COUNT(*) AS n FROM beasiswa b JOIN mahasiswa mh ON mh.nim = b.nim WHERE b.status = 'Diusulkan' AND mh.program_studi = ?", [$bp])['n']],
        ['Kurikulum belum disetujui', ambil_satu("SELECT COUNT(*) AS n FROM mata_kuliah WHERE status_kurikulum = 'Draft' AND (prodi = ? OR prodi IS NULL)", [$bp])['n']],
    ];
} elseif ($role === 'staff') {
    $kartu = [
        ['Mahasiswa', ambil_satu('SELECT COUNT(*) AS n FROM mahasiswa')['n']],
        ['Dosen', ambil_satu('SELECT COUNT(*) AS n FROM dosen')['n']],
        ['KRS diajukan', ambil_satu("SELECT COUNT(*) AS n FROM krs WHERE status = 'Diajukan'")['n']],
        ['Jadwal menunggu Kaprodi', ambil_satu("SELECT COUNT(*) AS n FROM kelas_kuliah WHERE status = 'Diajukan'")['n']],
        ['Surat perlu diproses', ambil_satu("SELECT COUNT(*) AS n FROM surat WHERE status = 'Diajukan'")['n']],
        ['Surat siap cetak', ambil_satu("SELECT COUNT(*) AS n FROM surat WHERE status = 'Disetujui'")['n']],
    ];
} elseif ($role === 'bendahara') {
    perbarui_keterlambatan();
    $kartu = [
        ['Tagihan belum lunas', ambil_satu("SELECT COUNT(*) AS n FROM tagihan WHERE status NOT IN ('Dibayar','Dibatalkan')")['n']],
        ['Total tunggakan', rupiah(ambil_satu("SELECT COALESCE(SUM(total_bayar), 0) AS n FROM tagihan WHERE status NOT IN ('Dibayar','Dibatalkan')")['n'])],
        ['Pembayaran menunggu verifikasi', ambil_satu("SELECT COUNT(*) AS n FROM pembayaran WHERE status = 'Menunggu'")['n']],
        ['Beasiswa/keringanan perlu final', ambil_satu("SELECT COUNT(*) AS n FROM beasiswa WHERE status = 'Disetujui Prodi'")['n']],
        ['Komponen biaya aktif', ambil_satu('SELECT COUNT(*) AS n FROM komponen_biaya WHERE aktif = 1')['n']],
        ['Tagihan terlambat', ambil_satu("SELECT COUNT(*) AS n FROM tagihan WHERE status = 'Terlambat'")['n']],
    ];
} elseif ($role === 'dosen') {
    $kelas = ambil_satu("SELECT COUNT(*) AS n FROM kelas_kuliah WHERE dosen_id = ? AND status = 'Disetujui'", [$user['dosen_id'] ?? 0])['n'];
    $kartu = [
        ['Kelas diampu', $kelas],
        ['Mahasiswa bimbingan akademik', ambil_satu('SELECT COUNT(*) AS n FROM mahasiswa WHERE dosen_pa_id = ?', [$user['dosen_id'] ?? 0])['n']],
        ['KRS menunggu persetujuan saya', ambil_satu("SELECT COUNT(DISTINCT k.nim) AS n FROM krs k JOIN mahasiswa mh ON mh.nim = k.nim WHERE k.status = 'Diajukan' AND mh.dosen_pa_id = ?", [$user['dosen_id'] ?? 0])['n']],
        ['Skripsi bimbingan', ambil_satu("SELECT COUNT(*) AS n FROM skripsi WHERE pembimbing_id = ? AND status IN ('Disetujui','Layak Sidang')", [$user['dosen_id'] ?? 0])['n']],
    ];
}

$judul = 'Dashboard ' . nama_peran($role);
include '../includes/header.php';
?>
<h1>Dashboard <?= e(nama_peran($role)) ?></h1>
<div class="kartu"><h2>Selamat datang, <?= e($user['nama']) ?></h2>
<p><strong>Peran:</strong> <?= e(nama_peran($role)) ?><?php if (!empty($user['prodi'])): ?> | <strong>Program studi:</strong> <?= e($user['prodi']) ?><?php endif; ?> | <strong>Tahun akademik aktif:</strong> <?= e(tahun_aktif()) ?></p>
<?php if ($role === 'kaprodi' && trim((string) $user['prodi']) === ''): ?><p class="keterangan">Akun ini belum memiliki program studi, sehingga data prodi belum tampil. Minta Admin mengisi prodi pada Kelola Pengguna.</p><?php endif; ?>
<?php if ($role === 'dosen' && empty($user['dosen_id'])): ?><p class="keterangan">Akun ini belum dihubungkan ke data dosen. Minta Admin atau Staff menautkannya di menu Data Dosen.</p><?php endif; ?>
</div>
<div class="ringkasan">
<?php foreach ($kartu as [$label, $nilai]): ?><div class="kartu"><span><?= e($label) ?></span><strong><?= e($nilai) ?></strong></div><?php endforeach; ?>
</div>
<div class="kartu"><h2>Menu cepat</h2><p>
<?php foreach ($hak as $modul => $izin): ?><a href="<?= e(alamat_modul($role, $modul)) ?>" class="tombol kecil"><?= e(NAMA_MODUL[$modul] ?? $modul) ?></a> <?php endforeach; ?>
</p></div>
<?php include '../includes/footer.php'; ?>
