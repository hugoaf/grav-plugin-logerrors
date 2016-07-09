<?php
/**
 * LogErrors v1.0.1
 *
 * This plugin logs not found (404) page errors to the data folder so it can be read with Data Plugin
 *
 * @package     Log Errors
 * @version     1.0.1
 * @link        <https://github.com/hugoaf/grav-plugin-logerrors>
 * @author      Hugo Avila <hugoavila@sitioi.com>
 * @copyright   2015, Hugo Avila
 * @license     <http://opensource.org/licenses/MIT>        MIT
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\File\File;
use Symfony\Component\Yaml\Yaml;

class LogerrorsPlugin extends Plugin
{

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPageNotFound' => ['onPageNotFound', 1],
        ];
    }

    /**
     *  if page not found found saves data
     *
     */
    public function onPageNotFound()
    {
        $this->uri = $this->grav['uri'];
        $this->savelog($this->uri->url);
    }

    /**
     *  Saves data to the log file
     *  - creates or append not found errors url, time and HTTP_REFERER
     *  - creates a summary and save it to file
     */
    protected function savelog($url)
    {

        $params       = $this->config->get('plugins.logerrors');
        $filename     = !empty($params['filename']) ? trim($params['filename']) : 'notfound.txt';
        $folder       = !empty($params['folder']) ? trim($params['folder']) : 'logerrors';
        $locator      = $this->grav['locator'];
        $path         = $locator->findResource('user://data', true);
        $fullFileName = $path . DS . $folder . DS . $filename;
        $file         = File::instance($fullFileName);
        $referer      = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $time         = date("Y-m-d h:i:sa");
        $vars         = array(
            'url'     => $url,
            'time'    => $time,
            'referer' => $referer,
        );

        if (!file_exists($path . DS . $folder)) {mkdir($path . DS . $folder, 0755);}

        if (file_exists($fullFileName)) {
            $data = Yaml::parse($file->content());
            if (count($data) > 0) {
                array_unshift($data, $vars);
            } else {
                $data[] = $vars;
            }
        } else {
            $data[] = $vars;
        }

        $file->save(Yaml::dump($data));

        //
        //  Creates a file and update summary for recurrent notfound errors
        //
        $urls = array_column($data, 'url');
        asort($urls);
        $summary    = array();
        $prev_value = array('url' => null, 'count' => null);
        foreach ($urls as $val) {
            if ($prev_value['url'] != $val) {
                unset($prev_value);
                $prev_value = array('url' => $val, 'count' => 0);
                $summary[]  = &$prev_value;
            }
            $prev_value['count']++;
        }

        $count = array();
        foreach ($summary as $key => $row) {
            $count[$key] = $row['count'];
        }

        array_multisort($count, SORT_DESC, $summary);

        // create file and save summary
        $fullSummaryFileName = $path . DS . $folder . DS . 'summary_' . $filename;
        $file_summary        = File::instance($fullSummaryFileName);

        $file_summary->save(Yaml::dump($summary));

    }

}
