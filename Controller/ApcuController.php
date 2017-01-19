<?php
declare(strict_types = 1);
namespace ApcuAdminBundle\Controller;

use ApcuAdminBundle\Lib\Apcu;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ApcuController
 *
 * @package DH\Controller
 */
class ApcuController extends Controller
{
    const APCU_SRV = 'app.apcu';
    const MEM_NUM_SEG = 'num_seg';
    const MEM_SEG_SIZE = 'seg_size';
    const MEM_MEM_SIZE = 'mem_size';
    const MEM_AVAIL_MEM = 'avail_mem';
    const MEM_BLOCK_LISTS = 'block_lists';

    const CACHE_NUM_HITS = 'num_hits';
    const CACHE_NUM_MISSES = 'num_misses';
    const CACHE_START_TIME = 'start_time';
    const MEM_BLOCK_OFFSET = 'offset';
    const MEM_BLOCK_SIZE = 'size';

    /**
     * @Route("/apcu", name="apcu_index")
     * @return Response
     */
    public function indexAction():Response
    {
        $time = time();
        $apcu = $this->get(static::APCU_SRV);
        $mem_size = $apcu->getMem(static::MEM_NUM_SEG) * $apcu->getMem(static::MEM_SEG_SIZE);
        $mem_avail = $apcu->getMem(static::MEM_AVAIL_MEM);
        $mem_used = $mem_size - $mem_avail;
        $segSize = $apcu->bsize($apcu->getMem(static::MEM_SEG_SIZE));
        $reqRateUser = sprintf(
            '%.2f',
            $apcu->getCache(static::CACHE_NUM_HITS)
                ? (($apcu->getCache(static::CACHE_NUM_HITS)
                    + $apcu->getCache(static::CACHE_NUM_MISSES)) / ($time - $apcu->getCache(static::CACHE_START_TIME)))
                : 0
        );
        $hitRateUser = sprintf(
            '%.2f',
            $apcu->getCache(static::CACHE_NUM_HITS)
                ? ($apcu->getCache(static::CACHE_NUM_HITS) / ($time - $apcu->getCache(static::CACHE_START_TIME)))
                : 0
        );
        $missRateUser = sprintf(
            '%.2f',
            $apcu->getCache(static::CACHE_NUM_MISSES)
                ? ($apcu->getCache(static::CACHE_NUM_MISSES) / ($time - $apcu->getCache(static::CACHE_START_TIME)))
                : 0
        );
        $insertRateUser = sprintf(
            '%.2f',
            $apcu->getCache('num_inserts')
                ? ($apcu->getCache('num_inserts') / ($time - $apcu->getCache(static::CACHE_START_TIME)))
                : 0
        );
        $memFree = $apcu->bsize($mem_avail).sprintf(' (%.1f%%)', $mem_avail * 100 / $mem_size);
        $memUsed = $apcu->bsize($mem_used).sprintf(' (%.1f%%)', $mem_used * 100 / $mem_size);
        $totalHits = $apcu->getCache(static::CACHE_NUM_HITS) + $apcu->getCache(static::CACHE_NUM_MISSES);
        $numHits = '(0%)';
        $numMissed = '(0%)';
        if ($totalHits > 0) {
            $numHits = sprintf(
                ' (%.1f%%)',
                $apcu->getCache(static::CACHE_NUM_HITS) * 100 / $totalHits
            );
            $numMissed = sprintf(
                ' (%.1f%%)',
                $apcu->getCache(static::CACHE_NUM_MISSES) * 100 / ($apcu->getCache(static::CACHE_NUM_HITS) +
                    $apcu->getCache(static::CACHE_NUM_MISSES))
            );
        }

        $sizeVars = $apcu->bsize($apcu->getCache(static::MEM_MEM_SIZE));

        $frag = $this->getFragmentation();

        $serverName = $_SERVER['SERVER_NAME'];
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'];



        return $this->render(
            '@App/apcu/index.html.twig',
            [
                'apcu' => $apcu,
                'host' => $this->getHost(),
                'cache' => $apcu->getCache(),
                'mem' => $apcu->getMem(),
                'time' => $time,
                'segSize' => $segSize,
                'reqRateUser' => $reqRateUser,
                'sizeVars' => $sizeVars,
                'hitRateUser' => $hitRateUser,
                'missRateUser' => $missRateUser,
                'insertRateUser' => $insertRateUser,
                'memFree' => $memFree,
                'memUsed' => $memUsed,
                'numHits' => $numHits,
                'numMissed' => $numMissed,
                'frag' => $frag,
                'serverName' => $serverName,
                'serverSoftware' => $serverSoftware,
                'apcversion' => phpversion('apcu'),
                'phpversion' => phpversion(),
                'iniSettings' => $apcu->getIniSettings(),
                ApiController::BASE_DIR => dirname($this->getParameter(ApiController::ROOT_DIR).'/..')
            ]
        );
    }

    /**
     * @Route("/apcu/user-cache", name="apcu_user_cache")
     * @return Response
     */
    public function userCacheAction():Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        $apcu = $this->get(static::APCU_SRV);
        $order = $request->get('order', 'created');
        $direction = $request->get('dir', 'asc');
        $newDirection = $direction === 'asc' ? 'desc' : 'asc';
        $count = $request->get('count') ?? 20;
        $scope = $request->get('scope', 'A');
        $page = $request->get('page', 1);
        $search = empty($request->get('search')) ? null : $request->get('search');

        return $this->render(
            '@App/apcu/user_cache.html.twig',
            [
                'apcu' => $apcu,
                'scopes' => Apcu::SCOPES,
                'scope' => $scope,
                'order' => $order,
                'direction' => $newDirection,
                'counts' => Apcu::COUNT,
                'currentCount' => $count,
                'search' => $search,
                'list' => $apcu->getItemList($order, $direction, $scope, $search),
                'page' => $page,
                'route' => $request->get('_route'),
                'routeParams' => $request->query->all(),
                ApiController::BASE_DIR => dirname($this->getParameter(ApiController::ROOT_DIR).'/..'),
            ]
        );
    }

    /**
     * @Route("/apcu/version", name="apcu_version")
     * @return Response
     */
    public function versionAction():Response
    {
        $rss = file_get_contents('http://pecl.php.net/feeds/pkg_apcu.rss');
        $currentVersion = 0;
        $lastVersion = 0;
        $changelog = [];
        if ($rss !== false) {
            $currentVersion = phpversion('apcu');
            preg_match('!<title>APCu ([0-9.]+)</title>!', $rss, $match);
            $lastVersion = $match[1];

            if (version_compare($currentVersion, $lastVersion, '<')) {
                preg_match_all('!<(title|description)>([^<]+)</\\1>!', $rss, $match);
                $changes = array_slice($match[2], 2);

                foreach ($changes as $k => $change) {
                    if ($k % 2 === 0) {
                        $textVersion = $change;
                        list(,$version) = explode(' ', $change, 2);
                        if (version_compare($currentVersion, $version, '>=')) {
                            break;
                        }
                    } else {
                        $changelog[] = [
                            'version' => $version,
                            'textVersion' => nl2br(htmlspecialchars($textVersion, ENT_QUOTES, 'UTF-8')),
                            'text' => nl2br(htmlspecialchars($change, ENT_QUOTES, 'UTF-8'))
                        ];
                    }
                }
            }
        }
        return $this->render(
            '@App/apcu/version.html.twig',
            [
                'currentVersion' => $currentVersion,
                'lastVersion' => $lastVersion,
                'changelog' => $changelog,
                ApiController::BASE_DIR => dirname($this->getParameter(ApiController::ROOT_DIR).'/..'),
            ]
        );
    }

    /**
     * @Route("/apcu/clean", name="apcu_clean")
     * @return Response
     */
    public function cleanAction():Response
    {
        $clean = apcu_clear_cache();

        return new JsonResponse(['success' => $clean]);
    }

    /**
     * @Route("/apcu/imagen", name="apcu_imagen")
     * @return Response
     */
    public function imagenAction():Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        switch ($request->get('IMG')) {
            case 1:
                $imagen = $this->imagen1();
                break;
            case 2:
                $imagen = $this->imagen2();
                break;
            case 3:
                $imagen = $this->imagen3();
                break;
            case 4:
                $imagen = $this->imagen4();
                break;
            default:
                $imagen = null;
        }

        $headers= array(
            'Content-type'=>'image/jpeg',
            'Pragma'=>'no-cache',
            'Cache-Control'=>'no-cache'
        );


        ob_start();
        imagepng($imagen);
        $imageString = ob_get_clean();

        return new Response($imageString, 200, $headers);
    }

    /**
     * @Route("/apcu/detalle/{item}", name="apcu_detalle")
     * @return Response
     */
    public function detalleAction(string $item):Response
    {
        $detalle = apcu_fetch(urldecode($item));
        return new JsonResponse(['data' => $detalle]);
    }

    /**
     * @Route("/apcu/borrar/{item}", name="apcu_borrar")
     * @return Response
     */
    public function borrarAction(string $item):Response
    {
        $borrado = apcu_delete(urldecode($item));
        return new JsonResponse(['deleted' => $borrado]);
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        $host = php_uname('n');
        if ($host) {
            $host = '('.$host.')';
        }
        if (isset($_SERVER['SERVER_ADDR'])) {
            $host .= ' ('.$_SERVER['SERVER_ADDR'].')';
        }

        return $host;
    }

    /**
     * @return string
     */
    protected function getFragmentation()
    {
        $apcu = $this->get(static::APCU_SRV);
        // Fragementation: (freeseg - 1) / total_seg
        $nseg = $freeseg = $fragsize = $freetotal = 0;
        for ($i = 0; $i < $apcu->getMem(static::MEM_NUM_SEG); $i++) {
            $ptr = 0;
            foreach ($apcu->getMem(static::MEM_BLOCK_LISTS)[$i] as $block) {
                if ($block[static::MEM_BLOCK_OFFSET] !== $ptr) {
                    ++$nseg;
                }
                $ptr = $block[static::MEM_BLOCK_OFFSET] + $block[static::MEM_BLOCK_SIZE];
                /* Only consider blocks <5M for the fragmentation % */
                if ($block[static::MEM_BLOCK_SIZE] < (5 * 1024 * 1024)) {
                    $fragsize += $block[static::MEM_BLOCK_SIZE];
                }
                $freetotal += $block[static::MEM_BLOCK_SIZE];
            }
            $freeseg += count($apcu->getMem(static::MEM_BLOCK_LISTS)[$i]);
        }

        $frag = '0%';
        if ($freeseg > 1) {
            $frag = sprintf(
                '%.2f%% (%s out of %s in %d fragments)',
                ($fragsize / $freetotal) * 100,
                $apcu->bsize($fragsize),
                $apcu->bsize($freetotal),
                $freeseg
            );
        }

        return $frag;
    }

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
