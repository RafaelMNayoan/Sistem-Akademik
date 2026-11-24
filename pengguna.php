<?php
require_once '../koneksi.php';
$user = wajib_admin();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $aksi=post('aksi'); $id=(int)post('id');
    if ($aksi==='tambah') {
        $username=post('username'); $nama=post('nama'); $role=post('role'); $password=$_POST['password']??''; $prodi=post('prodi');
        if ($username==='' || $nama==='' || strlen($password)<8 || !in_array($role,['kaprodi','staff','bendahara','dosen'],true)) $error='Lengkapi data. Password minimal 8 karakter.';
        elseif (ambil_satu('SELECT id FROM akun_pengguna WHERE username=?',[$username])) $error='Username sudah digunakan.';
        else { jalankan('INSERT INTO akun_pengguna (username,nama,password,role,prodi) VALUES (?,?,?,?,?)',[$username,$nama,password_hash($password,PASSWORD_DEFAULT),$role,$prodi?:null]); pesan('Akun pengguna berhasil dibuat.'); pindah('pengguna.php'); }
    } elseif ($aksi==='status' && $id>0) {
        $status=post('status'); if(in_array($status,['Aktif','Non-Aktif'],true)) jalankan('UPDATE akun_pengguna SET status=? WHERE id=?',[$status,$id]); pesan('Status akun diperbarui.'); pindah('pengguna.php');
    } elseif ($aksi==='password' && $id>0) {
        $password=$_POST['password']??''; if(strlen($password)<8) $error='Password minimal 8 karakter.'; else { jalankan('UPDATE akun_pengguna SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]); pesan('Password berhasil diubah.'); pindah('pengguna.php'); }
    }
}
$daftar=ambil_semua('SELECT id,username,nama,role,prodi,status FROM akun_pengguna ORDER BY role,nama');
$judul='Kelola Pengguna'; include '../includes/header.php';
?>
<h1>Kelola Pengguna</h1><div class="kartu"><h2>Tambah akun baru</h2><?php if($error): ?><div class="pesan gagal"><?=e($error)?></div><?php endif; ?><form method="post"><input type="hidden" name="aksi" value="tambah"><div class="form-grid"><p><label>Nama lengkap</label><input name="nama" required></p><p><label>Username</label><input name="username" required></p><p><label>Peran</label><select name="role" required><option value="kaprodi">Kaprodi</option><option value="staff">Staff Akademik</option><option value="bendahara">Bendahara</option><option value="dosen">Dosen</option></select></p><p><label>Program studi (opsional)</label><input name="prodi"></p><p><label>Password (minimal 8 karakter)</label><input type="password" name="password" minlength="8" required></p></div><button class="tombol" type="submit">Buat akun</button></form></div>
<div class="kartu"><h2>Daftar akun</h2><div class="gulir"><table><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Prodi</th><th>Status</th><th>Ubah status</th><th>Reset password</th></tr><?php foreach($daftar as $a): ?><tr><td><?=e($a['nama'])?></td><td><?=e($a['username'])?></td><td><?=e(nama_peran($a['role']))?></td><td><?=e($a['prodi']?:'-')?></td><td><?=e($a['status'])?></td><td><form method="post"><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?=$a['id']?>"><select name="status"><option <?= $a['status']==='Aktif'?'selected':'' ?>>Aktif</option><option <?= $a['status']==='Non-Aktif'?'selected':'' ?>>Non-Aktif</option></select><button class="tombol kecil" type="submit">Simpan</button></form></td><td><form method="post"><input type="hidden" name="aksi" value="password"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="password" name="password" minlength="8" placeholder="Password baru" required><button class="tombol kecil" type="submit">Reset</button></form></td></tr><?php endforeach; ?></table></div><?php if(!$daftar): ?><p class="kosong">Belum ada akun tambahan.</p><?php endif; ?></div>
<?php include '../includes/footer.php'; ?>
