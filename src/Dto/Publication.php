<?php

namespace App\Dto;

class Publication
{
    private string $series;
    private string $volume;
    private string $number;

    public function __construct(string $series, string $volume = '', string $number = '')
    {
        $this->series = $series;
        $this->volume = $volume;
        $this->number = $number;
    }

    public function getSeries(): string { return $this->series; }
    public function getVolume(): string { return $this->volume; }
    public function getNumber(): string { return $this->number; }

    /** Label for a volume entry, e.g. "BGU 1" or just "CPL" if no volume. */
    public function getVolumeLabel(): string
    {
        if ($this->volume === '') {
            return $this->series;
        }
        return $this->series . ' ' . $this->volume;
    }

    /** Full label including number, e.g. "BGU 1 112". */
    public function getLabel(): string
    {
        $parts = array_filter([$this->series, $this->volume, $this->number], fn($p) => $p !== '');
        return implode(' ', $parts);
    }
}
