<?php
declare(strict_types = 1);
namespace Oneko\ApcuAdminBundle\Controller;

use Oneko\ApcuAdminBundle\Lib\Apcu;
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
                'host' => $apcu->getHost(),
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
}
