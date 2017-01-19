<?php
declare(strict_types=1);

namespace Oneko\ApcuAdminBundle\Lib;

class Image
{
    /** @var Apcu */
    protected $apcu;

    /**
     * Image constructor.
     *
     * @param Apcu $apcu
     */
    public function __construct(Apcu $apcu)
    {
        $this->apcu = $apcu;
    }

    /**
     * @return resource
     */
    public function imagen1()
    {
        $mem = $this->apcu->getMem();
        $size = Apcu::GRAPH_SIZE;
        $imagen = imagecreate($size + 50, $size + 10);
        $s = $mem[Apcu::MEM_NUM_SEG] * $mem[Apcu::MEM_SEG_SIZE];
        $a = $mem[Apcu::MEM_AVAIL_MEM];
        $x = $y = $size / 2;
        $fuzz = 0.000001;

        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        // This block of code creates the pie chart.  It is a lot more complex than you
        // would expect because we try to visualize any memory fragmentation as well.
        $angle_from = 0;
        $string_placement = [];
        for ($i = 0; $i < $mem[Apcu::MEM_NUM_SEG]; $i++) {
            $ptr = 0;
            $free = $mem[Apcu::MEM_BLOCK_LISTS][$i];
            uasort($free, [$this->apcu, 'blockSort']);
            foreach ($free as $block) {
                if ($block[Apcu::MEM_BLOCK_OFFSET] !== $ptr) {       // Used block
                    $angle_to = $angle_from + ($block[Apcu::MEM_BLOCK_OFFSET] - $ptr) / $s;
                    if (($angle_to + $fuzz) > 1) {
                        $angle_to = 1;
                    }
                    if (($angle_to * 360) - ($angle_from * 360) >= 1) {
                        $this->apcu->fillArc($imagen, $x, $y, $size, (int)$angle_from * 360, (int)$angle_to * 360, $colBlack, $colRed);
                        if (($angle_to - $angle_from) > 0.05) {
                            $string_placement[] = [$angle_from, $angle_to];
                        }
                    }
                    $angle_from = $angle_to;
                }
                $angle_to = $angle_from + $block[Apcu::MEM_BLOCK_SIZE] / $s;
                if (($angle_to + $fuzz) > 1) {
                    $angle_to = 1;
                }
                if (($angle_to * 360) - ($angle_from * 360) >= 1) {
                    $this->apcu->fillArc(
                        $imagen,
                        $x,
                        $y,
                        $size,
                        (int)($angle_from * 360),
                        $angle_to * 360,
                        $colBlack,
                        $colGreen
                    );
                    if (($angle_to - $angle_from) > 0.05) {
                        $string_placement[] = [$angle_from, $angle_to];
                    }
                }
                $angle_from = $angle_to;
                $ptr = $block[Apcu::MEM_BLOCK_OFFSET] + $block[Apcu::MEM_BLOCK_SIZE];
            }
            if ($ptr < $mem[Apcu::MEM_SEG_SIZE]) { // memory at the end
                $angle_to = $angle_from + ($mem[Apcu::MEM_SEG_SIZE] - $ptr) / $s;
                if (($angle_to + $fuzz) > 1) {
                    $angle_to = 1;
                }
                $this->apcu->fillArc($imagen, $x, $y, $size, (int)$angle_from * 360, (int)$angle_to * 360, $colBlack, $colRed);
                if (($angle_to - $angle_from) > 0.05) {
                    $string_placement[] = [$angle_from, $angle_to];
                }
            }
        }
        foreach ($string_placement as $angle) {
            $this->apcu->textArc(
                $imagen,
                $x,
                $y,
                $size,
                $angle[0] * 360,
                $angle[1] * 360,
                $colBlack,
                $this->apcu->bsize($s * ($angle[1] - $angle[0]))
            );
        }

        return $imagen;
    }

    /**
     * @return resource
     */
    public function imagen2()
    {
        $imagen = imagecreate(Apcu::GRAPH_SIZE + 50, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        $s = $this->apcu->getCache(Apcu::CACHE_NUM_HITS) + $this->apcu->getCache(Apcu::CACHE_NUM_MISSES);
        $a = $this->apcu->getCache(Apcu::CACHE_NUM_HITS);

        $this->apcu->fillBox(
            $imagen,
            30,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-$a * (Apcu::GRAPH_SIZE - 21) / $s) : 0,
            $colBlack,
            $colGreen,
            sprintf('%.1f%%', $s ? $this->apcu->getCache(Apcu::CACHE_NUM_HITS) * 100 / $s : 0)
        );
        $this->apcu->fillBox(
            $imagen,
            130,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-max(4, ($s - $a) * (Apcu::GRAPH_SIZE - 21) / $s)) : 0,
            $colBlack,
            $colRed,
            sprintf('%.1f%%', $s ? $this->apcu->getCache(Apcu::CACHE_NUM_MISSES) * 100 / $s : 0)
        );

        return $imagen;
    }

    /**
     * @return resource
     */
    public function imagen3()
    {
        $imagen = imagecreate(2 * Apcu::GRAPH_SIZE + 150, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        $s = $this->apcu->getMem(Apcu::MEM_NUM_SEG) * $this->apcu->getMem(Apcu::MEM_SEG_SIZE);
        $x = 130;
        $y = 1;
        $j = 1;

        // This block of code creates the bar chart.  It is a lot more complex than you
        // would expect because we try to visualize any memory fragmentation as well.
        for ($i = 0; $i < $this->apcu->getMem(Apcu::MEM_NUM_SEG); $i++) {
            $ptr = 0;
            $free = $this->apcu->getMem(Apcu::MEM_BLOCK_LISTS)[$i];
            uasort($free, [$this->apcu, 'blockSort']);
            foreach ($free as $block) {
                if ($block[Apcu::MEM_BLOCK_OFFSET] !== $ptr) {       // Used block
                    $h = (Apcu::GRAPH_SIZE -5) * ($block[Apcu::MEM_BLOCK_OFFSET] - $ptr) / $s;
                    if ($h > 0) {
                        $j++;
                        if ($j < 75) {
                            $this->apcu->fillBox(
                                $imagen,
                                $x,
                                (int)$y,
                                50,
                                (int)$h,
                                $colBlack,
                                $colRed,
                                $this->apcu->bsize($block[Apcu::MEM_BLOCK_OFFSET] - $ptr),
                                $j
                            );
                        } else {
                            $this->apcu->fillBox($imagen, $x, (int)$y, 50, (int)$h, $colBlack, $colRed);
                        }
                    }
                    $y += $h;
                }

                $h = (int)((Apcu::GRAPH_SIZE - 5) * ($block[Apcu::MEM_BLOCK_SIZE]) / $s);
                if ($h > 0) {
                    $j++;
                    if ($j < 75) {
                        $this->apcu->fillBox(
                            $imagen,
                            $x,
                            (int)$y,
                            50,
                            (int)$h,
                            $colBlack,
                            $colGreen,
                            $this->apcu->bsize($block[Apcu::MEM_BLOCK_SIZE]),
                            $j
                        );
                    } else {
                        $this->apcu->fillBox($imagen, $x, $y, 50, (int)$h, $colBlack, $colGreen);
                    }
                }

                $y += $h;
                $ptr = $block[Apcu::MEM_BLOCK_OFFSET] + $block[Apcu::MEM_BLOCK_SIZE];
            }
            if ($ptr < $this->apcu->getMem(Apcu::MEM_SEG_SIZE)) { // memory at the end
                $h = (Apcu::GRAPH_SIZE -5) * ($this->apcu->getMem(Apcu::MEM_SEG_SIZE) - $ptr) / $s;
                if ($h > 0) {
                    $this->apcu->fillBox(
                        $imagen,
                        $x,
                        (int)$y,
                        50,
                        (int)$h,
                        $colBlack,
                        $colRed,
                        $this->apcu->bsize($this->apcu->getMem(Apcu::MEM_SEG_SIZE) - $ptr),
                        $j++
                    );
                }
            }
        }
        return $imagen;
    }

    /**
     * @return resource
     */
    public function imagen4()
    {
        $imagen = imagecreate(Apcu::GRAPH_SIZE + 50, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);

        $s = $this->apcu->getCache(Apcu::CACHE_NUM_HITS) + $this->apcu->getCache(Apcu::CACHE_NUM_MISSES);
        $a = $this->apcu->getCache(Apcu::CACHE_NUM_HITS);

        $this->apcu->fillBox(
            $imagen,
            30,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-$a * (Apcu::GRAPH_SIZE - 21)/$s) : 0,
            $colBlack,
            $colGreen,
            sprintf('%.1f%%', $s ? $this->apcu->getCache(Apcu::CACHE_NUM_HITS) * 100 / $s : 0)
        );
        $this->apcu->fillBox(
            $imagen,
            130,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-max(4, ($s - $a) * (Apcu::GRAPH_SIZE - 21) / $s)) : 0,
            $colBlack,
            $colRed,
            sprintf('%.1f%%', $s ? $this->apcu->getCache(Apcu::CACHE_NUM_MISSES) * 100/$s : 0)
        );

        return $imagen;
    }

    /**
     * @param $image
     * @return array
     */
    protected function getColors(&$image)
    {
        $colWhite = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
        $colRed   = imagecolorallocate($image, 0xD0, 0x60, 0x30);
        $colGreen = imagecolorallocate($image, 0x60, 0xF0, 0x60);
        $colBlack = imagecolorallocate($image, 0, 0, 0);
        imagecolortransparent($image, $colWhite);

        return [$colWhite, $colRed, $colGreen, $colBlack];
    }
}
