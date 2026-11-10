<?php

declare(strict_types=1);

require_once __DIR__ . '/Mahasiswa.php';

final class MahasiswaReguler extends Mahasiswa
{
    private int $ukt;

    public function __construct(
        string $nim,
        string $nama,
        string $prodi,
        int $semester,
        int $ukt
    ) {
        // Isi data umum lewat constructor parent (tidak menulis ulang validasinya).
        parent::__construct($nim, $nama, $prodi, $semester);

        if ($ukt < 0) {
            throw new InvalidArgumentException('UKT tidak boleh negatif');
        }

        $this->ukt = $ukt;
    }

    public function getUkt(): int
    {
        return $this->ukt;
    }

    // Reguler membayar UKT penuh.
    public function hitungBiaya(): int
    {
        return $this->ukt;
    }

    public function getJenis(): string
    {
        return 'Reguler';
    }
}
