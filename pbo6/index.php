<?php

declare(strict_types=1);

// Class harus dimuat sebelum session_start agar object di session bisa dipulihkan.
require_once __DIR__ . '/DataMahasiswa.php';

// Nama session khusus agar tidak bentrok dengan session praktikum sebelumnya.
session_name('PBO06SESSID');
session_start();

function aman(mixed $teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rupiah(int $angka): string
{
    return 'Rp' . number_format($angka, 0, ',', '.');
}

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$repo = new DataMahasiswa($_SESSION['mahasiswa'] ?? []);

$pesan = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new InvalidArgumentException('Token formulir tidak valid. Muat ulang halaman.');
        }

        $aksi    = (string) ($_POST['aksi'] ?? '');
        $nimLama = (string) ($_POST['nim_lama'] ?? '');

        if ($aksi === 'hapus') {
            if (!$repo->hapus(trim((string) ($_POST['nim'] ?? '')))) {
                throw new InvalidArgumentException('NIM tidak ditemukan.');
            }
        } elseif ($aksi === 'tambah' || $aksi === 'ubah') {
            $jenis    = (string) ($_POST['jenis'] ?? '');
            $nim      = trim((string) ($_POST['nim'] ?? ''));
            $nama     = trim((string) ($_POST['nama'] ?? ''));
            $prodi    = trim((string) ($_POST['prodi'] ?? ''));
            $semester = filter_var($_POST['semester'] ?? null, FILTER_VALIDATE_INT);
            $ukt      = filter_var($_POST['ukt'] ?? null, FILTER_VALIDATE_INT);

            if ($semester === false || $ukt === false) {
                throw new InvalidArgumentException('Semester dan UKT harus bilangan bulat.');
            }

            // Child dibuat sesuai jenis. Validasi identitas ada di parent,
            // validasi UKT dan potongan ada di masing-masing child.
            if ($jenis === 'reguler') {
                $m = new MahasiswaReguler($nim, $nama, $prodi, $semester, $ukt);
            } elseif ($jenis === 'beasiswa') {
                $potongan = filter_var($_POST['potongan'] ?? null, FILTER_VALIDATE_INT);

                if ($potongan === false) {
                    throw new InvalidArgumentException('Potongan harus bilangan bulat 0–100.');
                }

                $m = new MahasiswaBeasiswa($nim, $nama, $prodi, $semester, $ukt, $potongan);
            } else {
                throw new InvalidArgumentException('Jenis mahasiswa tidak dikenal.');
            }

            if ($aksi === 'ubah') {
                if (!$repo->ubah($nimLama, $m)) {
                    throw new InvalidArgumentException('NIM tidak boleh diubah atau data lama tidak ditemukan.');
                }
            } elseif (!$repo->tambah($m)) {
                throw new InvalidArgumentException('NIM sudah terdaftar (duplikat).');
            }
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }

        $_SESSION['mahasiswa'] = $repo->semua();

        $kataAksi = match ($aksi) {
            'hapus' => 'dihapus',
            'ubah'  => 'diubah',
            default => 'ditambahkan',
        };
        $_SESSION['flash'] = "Data berhasil {$kataAksi}.";

        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        // Validasi gagal: tampilkan pesan error, object tidak disimpan.
        $error = $e->getMessage();
    }
}

$edit = $repo->cari((string) ($_GET['edit'] ?? ''));

// Nilai awal form. Kosong untuk tambah baru.
$form = [
    'jenis'    => 'reguler',
    'nim'      => '',
    'nama'     => '',
    'prodi'    => '',
    'semester' => '',
    'ukt'      => '',
    'potongan' => '',
];
$nimLama = '';

if ($error !== '') {
    // Validasi gagal: tampilkan kembali isian terakhir supaya tidak mengetik ulang.
    foreach (array_keys($form) as $k) {
        $form[$k] = trim((string) ($_POST[$k] ?? $form[$k]));
    }
    $nimLama = (string) ($_POST['nim_lama'] ?? '');
} elseif ($edit !== null) {
    // Mode edit: isi form dari data yang dipilih, lewat getter.
    $form = [
        'jenis'    => strtolower($edit->getJenis()),
        'nim'      => $edit->getNim(),
        'nama'     => $edit->getNama(),
        'prodi'    => $edit->getProdi(),
        'semester' => (string) $edit->getSemester(),
        'ukt'      => (string) $edit->getUkt(),
        'potongan' => $edit instanceof MahasiswaBeasiswa ? (string) $edit->getPotonganPersen() : '',
    ];
    $nimLama = $edit->getNim();
}

$modeEdit = $nimLama !== '';

if ($modeEdit) {
    $form['nim'] = $nimLama; // NIM terkunci saat edit
}

// Pencarian: cocokkan NIM, nama, prodi, atau jenis.
$q      = trim((string) ($_GET['q'] ?? ''));
$semua  = $repo->semua();
$daftar = array_filter($semua, function (Mahasiswa $m) use ($q): bool {
    if ($q === '') {
        return true;
    }

    foreach ([$m->getNim(), $m->getNama(), $m->getProdi(), $m->getJenis()] as $teks) {
        if (stripos($teks, $q) !== false) {
            return true;
        }
    }

    return false;
});
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Praktikum PBO 6 - CRUD Mahasiswa (Inheritance)</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: system-ui, Arial, sans-serif;
            background: #f1f5f9;
            color: #15324d;
            margin: 0;
        }

        [hidden] { display: none !important; }

        main { max-width: 1120px; margin: 36px auto; padding: 0 18px; }
        h1 { margin-bottom: 4px; }
        small, .muted { color: #60758a; }

        .panel {
            background: white;
            border-radius: 13px;
            padding: 24px;
            margin: 20px 0;
            box-shadow: 0 5px 18px #16324d14;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        label { font-weight: 650; font-size: 13px; display: block; }

        input, select {
            display: block;
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #c8d5e3;
            border-radius: 7px;
            background: white;
            font: inherit;
        }

        input[readonly] { background: #eef2f6; color: #60758a; }

        button, .btn {
            border: 0;
            border-radius: 7px;
            background: #176397;
            color: white;
            padding: 9px 15px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }

        .danger { background: #b9414a; }
        .actions { display: flex; gap: 7px; align-items: center; }
        .actions form { margin: 0; }

        .toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
        .toolbar input { margin-top: 0; max-width: 320px; }

        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { padding: 12px 9px; text-align: left; border-bottom: 1px solid #e1e8f0; }
        th { background: #e8f1f8; }

        .scroll { overflow-x: auto; }
        .notice { padding: 12px; border-radius: 8px; background: #e3f4e9; }
        .err { background: #ffe8e8; }

        @media (max-width: 650px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <h1>Data Mahasiswa</h1>
    <p class="muted">PBO Pertemuan 6: Inheritance (Mahasiswa Reguler dan Beasiswa) + CRUD Array/Session</p>

    <?php if ($pesan): ?>
        <div class="notice"><?= aman($pesan) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice err"><?= aman($error) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $modeEdit ? 'Edit' : 'Tambah' ?> Data Mahasiswa</h2>

        <form method="post">
            <input type="hidden" name="csrf" value="<?= aman($_SESSION['csrf']) ?>">
            <input type="hidden" name="aksi" value="<?= $modeEdit ? 'ubah' : 'tambah' ?>">
            <input type="hidden" name="nim_lama" value="<?= aman($nimLama) ?>">

            <div class="grid">
                <label>Jenis Mahasiswa
                    <select name="jenis" id="jenis" required>
                        <option value="reguler" <?= $form['jenis'] === 'reguler' ? 'selected' : '' ?>>Reguler</option>
                        <option value="beasiswa" <?= $form['jenis'] === 'beasiswa' ? 'selected' : '' ?>>Beasiswa</option>
                    </select>
                </label>

                <label>NIM
                    <input
                        type="text"
                        name="nim"
                        value="<?= aman($form['nim']) ?>"
                        <?= $modeEdit ? 'readonly' : '' ?>
                        required>
                </label>

                <label>Nama
                    <input type="text" name="nama" value="<?= aman($form['nama']) ?>" required>
                </label>

                <label>Program Studi
                    <input type="text" name="prodi" value="<?= aman($form['prodi']) ?>" required>
                </label>

                <label>Semester
                    <input type="number" name="semester" min="1" max="14" value="<?= aman($form['semester']) ?>" required>
                </label>

                <label>UKT (Rp)
                    <input type="number" name="ukt" min="0" value="<?= aman($form['ukt']) ?>" required>
                </label>

                <label id="kolom-potongan">Potongan Beasiswa (%)
                    <input type="number" name="potongan" id="potongan" min="0" max="100" value="<?= aman($form['potongan']) ?>">
                </label>
            </div>

            <p>
                <button><?= $modeEdit ? 'Simpan Perubahan' : 'Tambah Mahasiswa' ?></button>
                <?php if ($modeEdit): ?>
                    <a class="btn" href="index.php">Batal</a>
                <?php endif; ?>
            </p>
        </form>
    </section>

    <script>
        // Kolom potongan hanya tampil dan wajib diisi untuk jenis Beasiswa.
        const pilihJenis = document.getElementById('jenis');
        const kolomPotongan = document.getElementById('kolom-potongan');
        const inputPotongan = document.getElementById('potongan');

        function aturKolom() {
            const beasiswa = pilihJenis.value === 'beasiswa';
            kolomPotongan.hidden = !beasiswa;
            inputPotongan.required = beasiswa;
        }

        pilihJenis.addEventListener('change', aturKolom);
        aturKolom();
    </script>

    <section class="panel">
        <h2>Daftar Mahasiswa (<?= count($daftar) ?> dari <?= count($semua) ?>)</h2>

        <form method="get" class="toolbar">
            <input type="text" name="q" value="<?= aman($q) ?>" placeholder="Cari NIM, nama, prodi, atau jenis">
            <button>Cari</button>
            <?php if ($q !== ''): ?>
                <a class="btn" href="index.php">Reset</a>
            <?php endif; ?>
        </form>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Prodi</th>
                        <th>Semester</th>
                        <th>Jenis</th>
                        <th>UKT</th>
                        <th>Potongan</th>
                        <th>Biaya Akhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar as $m): ?>
                        <tr>
                            <td><?= aman($m->getNim()) ?></td>
                            <td><?= aman($m->getNama()) ?></td>
                            <td><?= aman($m->getProdi()) ?></td>
                            <td><?= aman($m->getSemester()) ?></td>
                            <td><?= aman($m->getJenis()) ?></td>
                            <td><?= aman(rupiah($m->getUkt())) ?></td>
                            <td><?= $m instanceof MahasiswaBeasiswa ? aman($m->getPotonganPersen()) . '%' : '-' ?></td>
                            <td><?= aman(rupiah($m->hitungBiaya())) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="btn" href="?edit=<?= urlencode($m->getNim()) ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Hapus data ini?')">
                                        <input type="hidden" name="csrf" value="<?= aman($_SESSION['csrf']) ?>">
                                        <input type="hidden" name="aksi" value="hapus">
                                        <input type="hidden" name="nim" value="<?= aman($m->getNim()) ?>">
                                        <button class="danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$daftar): ?>
                        <tr>
                            <td colspan="9"><?= $q !== '' ? 'Data tidak ditemukan.' : 'Belum ada data.' ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p><small>Data disimpan pada PHP Session (sementara), bukan MySQL.</small></p>
    </section>
</main>
</body>
</html>