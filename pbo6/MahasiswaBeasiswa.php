<?php

declare(strict_types=1);

require_once __DIR__ . '/Mahasiswa.php';

final class MahasiswaBeasiswa extends Mahasiswa
{
    private int $ukt;
    private int $potonganPersen;

    public function __construct(
        string $nim,
        string $nama,
        string $prodi,
        int $semester,
        int $ukt,
        int $potonganPersen
    ) {
        // Isi data umum lewat constructor parent.
        parent::__construct($nim, $nama, $prodi, $semester);

        if ($ukt < 0 || $potonganPersen < 0 || $potonganPersen > 100) {
            throw new InvalidArgumentException('UKT/potongan tidak valid');
        }

        $this->ukt            = $ukt;
        $this->potonganPersen = $potonganPersen;
    }

    public function getUkt(): int
    {
        return $this->ukt;
    }

    public function getPotonganPersen(): int
    {
        return $this->potonganPersen;
    }

    // Beasiswa: UKT setelah dipotong persen. intdiv() menghasilkan bilangan bulat.
    public function hitungBiaya(): int
    {
        return intdiv($this->ukt * (100 - $this->potonganPersen), 100);
    }

    public function getJenis(): string
    {
        return 'Beasiswa';
    }
}
