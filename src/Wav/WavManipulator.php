<?php

namespace IndieHD\AudioManipulator\Wav;

use IndieHD\AudioManipulator\BaseManipulator;
use IndieHD\AudioManipulator\Converting\ConverterManipulatorInterface;

class WavManipulator extends BaseManipulator implements ConverterManipulatorInterface
{
    protected $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function convert(string $output, $filters = [])
    {
        $this->converter->applyFilters($filters)->convert($output);
    }
}