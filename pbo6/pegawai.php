<?php

declare(strict_types=1);

// Parent class
abstract class Pegawai
{
    private int $id;
    private string $nama;

    public function __construct(int $id, string $nama)
    {
        if ($id < 0) {
            throw new InvalidArgumentException('ID tidak boleh negatif');
        }

        if (trim($nama) === '') {
            throw new InvalidArgumentException('Nama wajib diisi');
        }

        $this->id   = $id;
        $this->nama = trim($nama);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNama(): string
    {
        return $this->nama;
    }

    // Tiap child wajib menghitung gaji dengan caranya sendiri.
    abstract public function hitungGaji(): int;
}

// Child 1: gaji tetap
final class PegawaiTetap extends Pegawai
{
    private int $gajiPokok;

    public function __construct(int $id, string $nama, int $gajiPokok)
    {
        parent::__construct($id, $nama);

        if ($gajiPokok < 0) {
            throw new InvalidArgumentException('Gaji pokok tidak boleh negatif');
        }

        $this->gajiPokok = $gajiPokok;
    }

    public function hitungGaji(): int
    {
        return $this->gajiPokok;
    }
}

// Child 2: dibayar per jam
final class PegawaiKontrak extends Pegawai
{
    private int $upahPerJam;
    private int $jamKerja;

    public function __construct(int $id, string $nama, int $upahPerJam, int $jamKerja)
    {
        parent::__construct($id, $nama);

        if ($upahPerJam < 0 || $jamKerja < 0) {
            throw new InvalidArgumentException('Upah per jam dan jam kerja tidak boleh negatif');
        }

        $this->upahPerJam = $upahPerJam;
        $this->jamKerja   = $jamKerja;
    }

    public function hitungGaji(): int
    {
        return $this->upahPerJam * $this->jamKerja;
    }
}

// Menampilkan output dua object
$daftar = [
    new PegawaiTetap(1, 'Andi', 6000000),
    new PegawaiKontrak(2, 'Siti', 50000, 100),
];

foreach ($daftar as $p) {
    echo $p->getId() . ' | ' . $p->getNama()
        . ' | Rp' . number_format($p->hitungGaji(), 0, ',', '.')
        . '<br>';
}

// Uji validasi angka negatif
try {
    new PegawaiKontrak(3, 'Budi', -50000, 100);
} catch (InvalidArgumentException $e) {
    echo 'Ditolak: ' . $e->getMessage() . '<br>';
}