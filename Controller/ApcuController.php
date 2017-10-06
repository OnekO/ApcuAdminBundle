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
    const APCU_SRV = 'apcubundle.apcu';
    const IMAGE_SRV = 'apcubundle.image';

    /**
     * @Route("/", name="apcu_index")
     * @return Response
     */
    public function indexAction():Response
    {
        $time = time();
        $apcu = $this->get(static::APCU_SRV);
        $mem_size = $apcu->getMem(Apcu::MEM_NUM_SEG) * $apcu->getMem(Apcu::MEM_SEG_SIZE);
        $mem_avail = $apcu->getMem(Apcu::MEM_AVAIL_MEM);
        $mem_used = $mem_size - $mem_avail;
        $segSize = $apcu->bsize($apcu->getMem(Apcu::MEM_SEG_SIZE));
        $reqRateUser = sprintf(
            '%.2f',
            $apcu->getCache(Apcu::CACHE_NUM_HITS)
                ? (($apcu->getCache(Apcu::CACHE_NUM_HITS)
                    + $apcu->getCache(Apcu::CACHE_NUM_MISSES)) / ($time - $apcu->getCache(Apcu::CACHE_START_TIME)))
                : 0
        );
        $hitRateUser = sprintf(
            '%.2f',
            $apcu->getCache(Apcu::CACHE_NUM_HITS)
                ? ($apcu->getCache(Apcu::CACHE_NUM_HITS) / ($time - $apcu->getCache(Apcu::CACHE_START_TIME)))
                : 0
        );
        $missRateUser = sprintf(
            '%.2f',
            $apcu->getCache(Apcu::CACHE_NUM_MISSES)
                ? ($apcu->getCache(Apcu::CACHE_NUM_MISSES) / ($time - $apcu->getCache(Apcu::CACHE_START_TIME)))
                : 0
        );
        $insertRateUser = sprintf(
            '%.2f',
            $apcu->getCache('num_inserts')
                ? ($apcu->getCache('num_inserts') / ($time - $apcu->getCache(Apcu::CACHE_START_TIME)))
                : 0
        );
        $memFree = $apcu->bsize($mem_avail).sprintf(' (%.1f%%)', $mem_avail * 100 / $mem_size);
        $memUsed = $apcu->bsize($mem_used).sprintf(' (%.1f%%)', $mem_used * 100 / $mem_size);
        $totalHits = $apcu->getCache(Apcu::CACHE_NUM_HITS) + $apcu->getCache(Apcu::CACHE_NUM_MISSES);
        $numHits = '(0%)';
        $numMissed = '(0%)';
        if ($totalHits > 0) {
            $numHits = sprintf(
                ' (%.1f%%)',
                $apcu->getCache(Apcu::CACHE_NUM_HITS) * 100 / $totalHits
            );
            $numMissed = sprintf(
                ' (%.1f%%)',
                $apcu->getCache(Apcu::CACHE_NUM_MISSES) * 100 / ($apcu->getCache(Apcu::CACHE_NUM_HITS) +
                    $apcu->getCache(Apcu::CACHE_NUM_MISSES))
            );
        }

        $sizeVars = $apcu->bsize($apcu->getCache(Apcu::MEM_MEM_SIZE));

        $frag = $apcu->getFragmentation($apcu);

        $serverName = $_SERVER['SERVER_NAME'];
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'];



        return $this->render(
            '@ApcuAdmin/apcu/index.html.twig',
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
                'base_dir' => dirname($this->getParameter('kernel.root_dir') . '/..')
            ]
        );
    }

    /**
     * @Route("/user-cache", name="apcu_user_cache")
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
            '@ApcuAdmin/apcu/user_cache.html.twig',
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
                'base_dir' => dirname($this->getParameter('kernel.root_dir') . '/..')
            ]
        );
    }

    /**
     * @Route("/version", name="apcu_version")
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
            '@ApcuAdmin/apcu/version.html.twig',
            [
                'currentVersion' => $currentVersion,
                'lastVersion' => $lastVersion,
                'changelog' => $changelog,
                'base_dir' => dirname($this->getParameter('kernel.root_dir') . '/..')
            ]
        );
    }

    /**
     * @Route("/clean", name="apcu_clean")
     * @return Response
     */
    public function cleanAction():Response
    {
        $clean = apcu_clear_cache();

        return new JsonResponse(['success' => $clean]);
    }

    /**
     * @Route("/imagen", name="apcu_imagen")
     * @return Response
     */
    public function imagenAction():Response
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        switch ($request->get('IMG')) {
            case 1:
                $imagen = $this->get(static::IMAGE_SRV)->imagen1();
                break;
            case 2:
                $imagen = $this->get(static::IMAGE_SRV)->imagen2();
                break;
            case 3:
                $imagen = $this->get(static::IMAGE_SRV)->imagen3();
                break;
            case 4:
                $imagen = $this->get(static::IMAGE_SRV)->imagen4();
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
     * @Route("/detalle/{item}", name="apcu_detalle")
     * @return Response
     */
    public function detalleAction(string $item):Response
    {
        $detalle = apcu_fetch(urldecode($item));
        return new JsonResponse(['data' => $detalle]);
    }

    /**
     * @Route("/borrar/{item}", name="apcu_borrar")
     * @return Response
     */
    public function borrarAction(string $item):Response
    {
        $borrado = apcu_delete(urldecode($item));
        return new JsonResponse(['deleted' => $borrado]);
    }
}
