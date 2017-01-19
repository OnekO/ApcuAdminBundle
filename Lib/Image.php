<?php
declare(strict_types=1);

namespace Oneko\ApcuAdminBundle\Lib;

class Image {
    /**
     * @return resource
     */
    protected function imagen1()
    {
        $apcu = $this->get(static::APCU_SRV);
        $mem = $apcu->getMem();
        $size = Apcu::GRAPH_SIZE;
        $imagen = imagecreate($size + 50, $size + 10);
        $s = $mem[static::MEM_NUM_SEG] * $mem[static::MEM_SEG_SIZE];
        $a = $mem[static::MEM_AVAIL_MEM];
        $x = $y = $size / 2;
        $fuzz = 0.000001;

        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        // This block of code creates the pie chart.  It is a lot more complex than you
        // would expect because we try to visualize any memory fragmentation as well.
        $angle_from = 0;
        $string_placement = [];
        for ($i = 0; $i < $mem[static::MEM_NUM_SEG]; $i++) {
            $ptr = 0;
            $free = $mem[static::MEM_BLOCK_LISTS][$i];
            uasort($free, [$apcu, 'blockSort']);
            foreach ($free as $block) {
                if ($block[static::MEM_BLOCK_OFFSET] !== $ptr) {       // Used block
                    $angle_to = $angle_from + ($block[static::MEM_BLOCK_OFFSET] - $ptr) / $s;
                    if (($angle_to + $fuzz) > 1) {
                        $angle_to = 1;
                    }
                    if (($angle_to * 360) - ($angle_from * 360) >= 1) {
                        $apcu->fillArc($imagen, $x, $y, $size, (int)$angle_from * 360, (int)$angle_to * 360, $colBlack, $colRed);
                        if (($angle_to - $angle_from) > 0.05) {
                            $string_placement[] = [$angle_from, $angle_to];
                        }
                    }
                    $angle_from = $angle_to;
                }
                $angle_to = $angle_from + $block[static::MEM_BLOCK_SIZE] / $s;
                if (($angle_to + $fuzz) > 1) {
                    $angle_to = 1;
                }
                if (($angle_to * 360) - ($angle_from * 360) >= 1) {
                    $apcu->fillArc(
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
                $ptr = $block[static::MEM_BLOCK_OFFSET] + $block[static::MEM_BLOCK_SIZE];
            }
            if ($ptr < $mem[static::MEM_SEG_SIZE]) { // memory at the end
                $angle_to = $angle_from + ($mem[static::MEM_SEG_SIZE] - $ptr) / $s;
                if (($angle_to + $fuzz) > 1) {
                    $angle_to = 1;
                }
                $apcu->fillArc($imagen, $x, $y, $size, (int)$angle_from * 360, (int)$angle_to * 360, $colBlack, $colRed);
                if (($angle_to - $angle_from) > 0.05) {
                    $string_placement[] = [$angle_from, $angle_to];
                }
            }
        }
        foreach ($string_placement as $angle) {
            $apcu->textArc(
                $imagen,
                $x,
                $y,
                $size,
                $angle[0] * 360,
                $angle[1] * 360,
                $colBlack,
                $apcu->bsize($s * ($angle[1] - $angle[0]))
            );
        }

        return $imagen;
    }

    /**
     * @return resource
     */
    protected function imagen2()
    {
        $apcu = $this->get(static::APCU_SRV);
        $imagen = imagecreate(Apcu::GRAPH_SIZE + 50, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        $s = $apcu->getCache(static::CACHE_NUM_HITS) + $apcu->getCache(static::CACHE_NUM_MISSES);
        $a = $apcu->getCache(static::CACHE_NUM_HITS);

        $apcu->fillBox(
            $imagen,
            30,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-$a * (Apcu::GRAPH_SIZE - 21) / $s) : 0,
            $colBlack,
            $colGreen,
            sprintf('%.1f%%', $s ? $apcu->getCache(static::CACHE_NUM_HITS) * 100 / $s : 0)
        );
        $apcu->fillBox(
            $imagen,
            130,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-max(4, ($s - $a) * (Apcu::GRAPH_SIZE - 21) / $s)) : 0,
            $colBlack,
            $colRed,
            sprintf('%.1f%%', $s ? $apcu->getCache(static::CACHE_NUM_MISSES) * 100 / $s : 0)
        );

        return $imagen;
    }

    /**
     * @return resource
     */
    protected function imagen3()
    {
        $apcu = $this->get(static::APCU_SRV);
        $imagen = imagecreate(2 * Apcu::GRAPH_SIZE + 150, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);
        $s = $apcu->getMem(static::MEM_NUM_SEG) * $apcu->getMem(static::MEM_SEG_SIZE);
        $x = 130;
        $y = 1;
        $j = 1;

        // This block of code creates the bar chart.  It is a lot more complex than you
        // would expect because we try to visualize any memory fragmentation as well.
        for ($i = 0; $i < $apcu->getMem(static::MEM_NUM_SEG); $i++) {
            $ptr = 0;
            $free = $apcu->getMem(static::MEM_BLOCK_LISTS)[$i];
            uasort($free, [$apcu, 'blockSort']);
            foreach ($free as $block) {
                if ($block[static::MEM_BLOCK_OFFSET] !== $ptr) {       // Used block
                    $h = (Apcu::GRAPH_SIZE -5) * ($block[static::MEM_BLOCK_OFFSET] - $ptr) / $s;
                    if ($h > 0) {
                        $j++;
                        if ($j < 75) {
                            $apcu->fillBox(
                                $imagen,
                                $x,
                                (int)$y,
                                50,
                                (int)$h,
                                $colBlack,
                                $colRed,
                                $apcu->bsize($block[static::MEM_BLOCK_OFFSET] - $ptr),
                                $j
                            );
                        } else {
                            $apcu->fillBox($imagen, $x, (int)$y, 50, (int)$h, $colBlack, $colRed);
                        }
                    }
                    $y += $h;
                }

                $h = (int)((Apcu::GRAPH_SIZE - 5) * ($block[static::MEM_BLOCK_SIZE]) / $s);
                if ($h > 0) {
                    $j++;
                    if ($j < 75) {
                        $apcu->fillBox(
                            $imagen,
                            $x,
                            (int)$y,
                            50,
                            (int)$h,
                            $colBlack,
                            $colGreen,
                            $apcu->bsize($block[static::MEM_BLOCK_SIZE]),
                            $j
                        );
                    } else {
                        $apcu->fillBox($imagen, $x, $y, 50, (int)$h, $colBlack, $colGreen);
                    }
                }

                $y += $h;
                $ptr = $block[static::MEM_BLOCK_OFFSET] + $block[static::MEM_BLOCK_SIZE];
            }
            if ($ptr < $apcu->getMem(static::MEM_SEG_SIZE)) { // memory at the end
                $h = (Apcu::GRAPH_SIZE -5) * ($apcu->getMem(static::MEM_SEG_SIZE) - $ptr) / $s;
                if ($h > 0) {
                    $apcu->fillBox(
                        $imagen,
                        $x,
                        (int)$y,
                        50,
                        (int)$h,
                        $colBlack,
                        $colRed,
                        $apcu->bsize($apcu->getMem(static::MEM_SEG_SIZE) - $ptr),
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
    protected function imagen4()
    {
        $apcu = $this->get(static::APCU_SRV);
        $imagen = imagecreate(Apcu::GRAPH_SIZE + 50, Apcu::GRAPH_SIZE + 10);
        list(, $colRed, $colGreen, $colBlack) = $this->getColors($imagen);

        $s = $apcu->getCache(static::CACHE_NUM_HITS) + $apcu->getCache(static::CACHE_NUM_MISSES);
        $a = $apcu->getCache(static::CACHE_NUM_HITS);

        $apcu->fillBox(
            $imagen,
            30,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-$a * (Apcu::GRAPH_SIZE - 21)/$s) : 0,
            $colBlack,
            $colGreen,
            sprintf('%.1f%%', $s ? $apcu->getCache(static::CACHE_NUM_HITS) * 100 / $s : 0)
        );
        $apcu->fillBox(
            $imagen,
            130,
            Apcu::GRAPH_SIZE,
            50,
            $s ? (int)(-max(4, ($s - $a) * (Apcu::GRAPH_SIZE - 21) / $s)) : 0,
            $colBlack,
            $colRed,
            sprintf('%.1f%%', $s ? $apcu->getCache(static::CACHE_NUM_MISSES) * 100/$s : 0)
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
