<?php

namespace App\Dto;

class Publication
{
    private string $series;
    private string $volume;
    private string $number;
    private string $volumeLabel;
    private string $label;
    private string $targetSeries;
    private string $targetVolume;
    private string $targetNumber;

    public function __construct(
        string $series,
        string $volume = '',
        string $number = '',
        string $volumeLabel = '',
        string $label = '',
        string $targetSeries = '',
        string $targetVolume = '',
        string $targetNumber = ''
    )
    {
        $this->series = $series;
        $this->volume = $volume;
        $this->number = $number;
        $this->volumeLabel = $volumeLabel;
        $this->label = $label;
        $this->targetSeries = $targetSeries !== '' ? $targetSeries : $series;
        $this->targetVolume = $targetVolume !== '' ? $targetVolume : $volume;
        $this->targetNumber = $targetNumber !== '' ? $targetNumber : $number;
    }

    public function getSeries(): string { return $this->series; }
    public function getVolume(): string { return $this->volume; }
    public function getNumber(): string { return $this->number; }

    /** Label for a volume entry, e.g. "BGU 1" or just "CPL" if no volume. */
    public function getVolumeLabel(): string
    {
        if ($this->volumeLabel !== '') {
            return $this->volumeLabel;
        }
        if ($this->volume === '') {
            return $this->series;
        }
        return $this->series . ' ' . $this->volume;
    }

    /** Full label including number, e.g. "BGU 1 112". */
    public function getLabel(): string
    {
        if ($this->label !== '') {
            return $this->label;
        }
        $parts = array_filter([$this->series, $this->volume, $this->number], fn($p) => $p !== '');
        return implode(' ', $parts);
    }

    public function getTargetSeries(): string { return $this->targetSeries; }
    public function getTargetVolume(): string { return $this->targetVolume; }
    public function getTargetNumber(): string { return $this->targetNumber; }
}
