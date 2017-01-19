<?php
declare(strict_types = 1);

namespace Oneko\ApcuAdminBundle\Lib;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Apcu
 *
 * @package AppBundle\Lib\Utils
 */
class Apcu
{
    const DATE_FORMAT = 'd/m/Y H:i:s';
    const GRAPH_SIZE = 200;

    const OB_HOST_STATS = 1;
    const OB_USER_CACHE = 2;
    const OB_VERSION_CHECK = 3;

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

    // check validity of input variables
    protected $vardom = [
        'OB' => '/^\d+$/',           // operational mode switch
        'CC' => '/^[01]$/',          // clear cache requested
        'DU' => '/^.*$/',            // Delete User Key
        'SH' => '/^[a-z0-9]+$/',         // shared object description

        'IMG' => '/^[123]$/',             // image to generate
        'LO' => '/^1$/',                 // login requested

        'COUNT' => '/^\d+$/',           // number of line displayed in list
        'SCOPE' => '/^[AD]$/',          // list view scope
        'SORT1' => '/^[AHSMCDTZ]$/',    // first sort key
        'SORT2' => '/^[DA]$/',          // second sort key
        'AGGR' => '/^\d+$/',           // aggregation by dir level
        'SEARCH' => '~^[a-zA-Z0-9/_.-]*$~'           // aggregation by dir level
    ];

    const SORT_1 = [
        'S' => 'User entry label',
        'H' => 'Hits',
        'Z' => 'Size',
        'A' => 'Last Accessed',
        'C' => 'Created at',
        'D' => 'Deleted at',
        'T' => 'Timeout'
    ];

    const SORT_2 = [
        'D' => 'DESC',
        'A' => 'ASC'
    ];
    const SCOPES = [
        'A' => 'Actived',
        'D' => 'Deleted'
    ];

    const SCOPE_LIST = [
        'A' => 'cache_list',
        'D' => 'deleted_list'
    ];

    protected $request;

    protected $params = [
        'SCOPE' => 'A',
        'SORT1' => 'H',
        'SORT2' => 'D',
        'COUNT' => 20
    ];

    const COUNT = [
        10 => 'Top 10',
        20 => 'Top 20',
        50 => 'Top 50',
        100 => 'Top 100',
        150 => 'Top 150',
        200 => 'Top 200',
        500 => 'Top 500',
        'All' => 'All'
    ];

    protected $cache = false;
    protected $mem = false;

    /**
     * Apcu constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        if (function_exists('apcu_cache_info') === false) {
            throw new \RuntimeException('APCU NOT LOADED!');
        }

        $this->request = $requestStack->getCurrentRequest();
        $this->cache = apcu_cache_info();
        $this->mem = apcu_sma_info();
    }

    protected function checkParams()
    {
        foreach ($this->vardom as $var => $dom) {
            $param = $this->request->get($var);
            $this->params[$var] = null;
            if (!is_array($param) && preg_match($dom . 'D', $param)) {
                $this->params[$var] = $param;
            }
        }
        if (isset($this->params['OB']) === false) {
            $this->params['OB'] = static::OB_HOST_STATS;
        }

        $scopes = static::SCOPES;
        if (isset($scopes[$this->params['SCOPE']]) === false) {
            $this->params['SCOPE'] = 'A';
        }
    }

    public function clearCache()
    {
        apcu_clear_cache();
    }

    public function deleteCache($du)
    {
        apcu_delete($du);
    }

    public function textArc($im, $centerX, $centerY, $diameter, $start, $end, $color1, $text, $placeindex = 0)
    {
        $r = $diameter / 2;
        $w = deg2rad((360 + $start + ($end - $start) / 2) % 360);

        if ($placeindex > 0) {
            imageline(
                $im,
                (int)($centerX + $r * cos($w) / 2),
                (int)($centerY + $r * sin($w) / 2),
                $diameter,
                $placeindex * 12,
                $color1
            );
            imagestring($im, 4, $diameter, $placeindex * 12, $text, $color1);
        } else {
            imagestring($im, 4, (int)($centerX + $r * cos($w) / 2), (int)($centerY + $r * sin($w) / 2), $text, $color1);
        }
    }

    public function fillBox($im, $x, $y, $w, $h, $color1, $color2, $text = '', $placeindex = 0)
    {
        $x1 = $x + $w - 1;
        $y1 = $y + $h - 1;

        imagerectangle($im, $x, $y1, $x1 + 1, $y + 1, $color1);
        if ($y1 > $y) {
            imagefilledrectangle($im, $x, $y, $x1, $y1, $color2);
        } else {
            imagefilledrectangle($im, $x, $y1, $x1, $y, $color2);
        }
        imagerectangle($im, $x, $y1, $x1, $y, $color1);
        if ($text) {
            if ($placeindex > 0) {
                if ($placeindex < 16) {
                    $px = 5;
                    $py = $placeindex * 12 + 6;
                    imagefilledrectangle($im, $px + 90, $py + 3, $px + 90 - 4, $py - 3, $color2);
                    imageline($im, $x, (int)($y + $h / 2), $px + 90, $py, $color2);
                    imagestring($im, 2, $px, $py - 6, $text, $color1);
                } else {
                    if ($placeindex < 31) {
                        $px = $x + 40 * 2;
                        $py = ($placeindex - 15) * 12 + 6;
                    } else {
                        $px = $x + 40 * 2 + 100 * (int)(($placeindex - 15) / 15);
                        $py = ($placeindex % 15) * 12 + 6;
                    }
                    imagefilledrectangle($im, $px, $py + 3, $px - 4, $py - 3, $color2);
                    imageline($im, $x + $w, (int)($y + $h / 2), $px, $py, $color2);
                    imagestring($im, 2, $px + 2, $py - 6, $text, $color1);
                }
            } else {
                imagestring($im, 4, $x + 5, $y1 - 16, $text, $color1);
            }
        }
    }

    // pretty printer for byte values
//
    public function bsize($s):string
    {
        foreach (['', 'K', 'M', 'G'] as $k) {
            if ($s < 1024) {
                break;
            }
            $s /= 1024;
        }

        return sprintf('%5.1f %sBytes', $s, $k);
    }

    /**
     * @param $key
     * @param $name
     * @param string $extra
     * @return string
     */
    public function sortHeader($key, $name, $extra = ''):string
    {
        return '<a class="sortable" href="' . $this->request->get('_route') . '">'
        . $name .
        '</a>';
    }

    /**
     * @param $array1
     * @param $array2
     * @return int
     */
    public function blockSort($array1, $array2):int
    {
        if ($array1['offset'] > $array2['offset']) {
            return 1;
        } else {
            return -1;
        }
    }

    /**
     * @param $im
     * @param $centerX
     * @param $centerY
     * @param $diameter
     * @param $start
     * @param $end
     * @param $color1
     * @param $color2
     * @param string $text
     * @param int $placeindex
     */
    public function fillArc(
        $im,
        $centerX,
        $centerY,
        $diameter,
        $start,
        $end,
        $color1,
        $color2,
        $text = '',
        $placeindex = 0
    ) {
        $r = $diameter / 2;
        $w = deg2rad((360 + $start + ($end - $start) / 2) % 360);


        if (function_exists('imagefilledarc')) {
            // exists only if GD 2.0.1 is avaliable
            imagefilledarc($im, $centerX + 1, $centerY + 1, $diameter, $diameter, $start, $end, $color1, IMG_ARC_PIE);
            imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2, IMG_ARC_PIE);
            imagefilledarc(
                $im,
                $centerX,
                $centerY,
                $diameter,
                $diameter,
                $start,
                $end,
                $color1,
                IMG_ARC_NOFILL | IMG_ARC_EDGED
            );
        } else {
            imagearc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2);
            imageline(
                $im,
                $centerX,
                $centerY,
                $centerX + cos(deg2rad($start)) * $r,
                $centerY + sin(deg2rad($start)) * $r,
                $color2
            );
            imageline(
                $im,
                $centerX,
                $centerY,
                $centerX + cos(deg2rad($start + 1)) * $r,
                $centerY + sin(deg2rad($start)) * $r,
                $color2
            );
            imageline(
                $im,
                $centerX,
                $centerY,
                $centerX + cos(deg2rad($end - 1)) * $r,
                $centerY + sin(deg2rad($end)) * $r,
                $color2
            );
            imageline(
                $im,
                $centerX,
                $centerY,
                $centerX + cos(deg2rad($end)) * $r,
                $centerY + sin(deg2rad($end)) * $r,
                $color2
            );
            imagefill($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $color2);
        }
        if ($text) {
            if ($placeindex > 0) {
                imageline(
                    $im,
                    (int)($centerX + $r * cos($w) / 2),
                    (int)($centerY + $r * sin($w) / 2),
                    $diameter,
                    $placeindex * 12,
                    $color1
                );
                imagestring($im, 4, $diameter, $placeindex * 12, $text, $color1);

            } else {
                imagestring(
                    $im,
                    4,
                    (int)($centerX + $r * cos($w) / 2),
                    (int)($centerY + $r * sin($w) / 2),
                    $text,
                    $color1
                );
            }
        }
    }

    /**
     * @param $time
     * @param $ts
     * @return string
     */
    public function duration($time, $ts):string
    {
        $years = (int)((($time - $ts) / (7 * 86400)) / 52.177457);
        $rem = (int)(($time - $ts) - ($years * 52.177457 * 7 * 86400));
        $weeks = (int)($rem / (7 * 86400));
        $days = (int)($rem / 86400) - $weeks * 7;
        $hours = (int)($rem / 3600) - $days * 24 - $weeks * 7 * 24;
        $mins = (int)($rem / 60) - $hours * 60 - $days * 24 * 60 - $weeks * 7 * 24 * 60;
        $str = '';
        if ($years === 1) {
            $str .= "$years year, ";
        }
        if ($years > 1) {
            $str .= "$years years, ";
        }
        if ($weeks === 1) {
            $str .= "$weeks week, ";
        }
        if ($weeks > 1) {
            $str .= "$weeks weeks, ";
        }
        if ($days === 1) {
            $str .= "$days day,";
        }
        if ($days > 1) {
            $str .= "$days days,";
        }
        if ($hours === 1) {
            $str .= " $hours hour and";
        }
        if ($hours > 1) {
            $str .= " $hours hours and";
        }
        $str .= ($mins === 1) ? ' 1 minute' : " $mins minutes";

        return $str;
    }

    /**
     * Check if gd is loaded
     * @return bool
     */
    public function graphicsAvailable():bool
    {
        return extension_loaded('gd');
    }

    /**
     * @param string|null $key
     * @return array|bool|null
     */
    public function getCache($key = null)
    {
        if ($key !== null) {
            return $this->cache[$key] ?? null;
        }

        return $this->cache;
    }

    /**
     * @param string|null $key
     * @return array|bool|null
     */
    public function getMem($key = null)
    {
        if ($key !== null) {
            return $this->mem[$key] ?? null;
        }

        return $this->mem;
    }

    /**
     * @return \Generator
     */
    public function getIniSettings():\Generator
    {
        foreach (ini_get_all('apcu') as $k => $v) {
            yield [$k, $v];
        }
    }

    /**
     * @param string $order
     * @param string $dir
     * @param string $scope
     * @param string $search
     * @return array
     */
    public function getItemList(
        string $order = 'created',
        string $dir = 'asc',
        string $scope = 'A',
        string $search = null
    ):array
    {
        $list = [];
        /** @var array $items */
        $items = $this->getCache(Apcu::SCOPE_LIST[$scope]);

        foreach ($items as $item) {
            if ($search !== null && strpos($item['info'], $search) === false) {
                continue;
            }
            switch ($order) {
                case 'accessed':
                    $k = sprintf('%015d-', $item['access_time']);
                    break;
                case 'hits':
                    $k = sprintf('%015d-', $item['num_hits']);
                    break;
                case 'size':
                    $k = sprintf('%015d-', $item['mem_size']);
                    break;
                case 'modified':
                    $k = sprintf('%015d-', $item['mtime']);
                    break;
                case 'created':
                    $k = sprintf('%015d-', $item['creation_time']);
                    break;
                case 'timeout':
                    $k = sprintf('%015d-', $item['ttl']);
                    break;
                case 'deleted':
                    $k = sprintf('%015d-', $item['deletion_time']);
                    break;
                case 'user':
                default:
                    $k = $item['info'];
                    break;
            }
            $list[$k . $item['info']] = $item;
        }

        if ($dir === 'asc') {
            krsort($list);
        } else {
            ksort($list);
        }

        return $list;
    }

    /**
     * @return string
     */
    public function getHost()
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
    public function getFragmentation(Apcu $apcu)
    {
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
}
