<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Throwable;

class FetchUnits extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'INEC';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'units:fetch';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = '';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'units:fetch';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [];


    /** @var CURLRequest */
    protected $curl;

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $fp = fopen(WRITEPATH . 'units.lock', 'c');
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $this->process();
            flock($fp, LOCK_UN);
        } else {
            echo 'Unable to grab lock!';
        }
        fclose($fp);
    }

    protected function process()
    {
        $this->base = 'https://www.inecnigeria.org/wp-content/themes/independent-national-electoral-commission/custom/views/';
        $this->curl = Services::curlrequest([
            'baseURI' => $this->base
        ]);
        $states = [
            ["id" => "1", "name" => "ABIA"],
            ["id" => "2", "name" => "ADAMAWA"],
            ["id" => "3", "name" => "AKWA IBOM"],
            ["id" => "4", "name" => "ANAMBRA"],
            ["id" => "5", "name" => "BAUCHI"],
            ["id" => "6", "name" => "BAYELSA"],
            ["id" => "7", "name" => "BENUE"],
            ["id" => "8", "name" => "BORNO"],
            ["id" => "9", "name" => "CROSS RIVER"],
            ["id" => "10", "name" => "DELTA"],
            ["id" => "11", "name" => "EBONYI"],
            ["id" => "12", "name" => "EDO"],
            ["id" => "13", "name" => "EKITI"],
            ["id" => "14", "name" => "ENUGU"],
            ["id" => "37", "name" => "FEDERAL CAPITAL TERRITORY"],
            ["id" => "15", "name" => "GOMBE"],
            ["id" => "16", "name" => "IMO"],
            ["id" => "17", "name" => "JIGAWA"],
            ["id" => "18", "name" => "KADUNA"],
            ["id" => "19", "name" => "KANO"],
            ["id" => "20", "name" => "KATSINA"],
            ["id" => "21", "name" => "KEBBI"],
            ["id" => "22", "name" => "KOGI"],
            ["id" => "23", "name" => "KWARA"],
            ["id" => "24", "name" => "LAGOS"],
            ["id" => "25", "name" => "NASARAWA"],
            ["id" => "26", "name" => "NIGER"],
            ["id" => "27", "name" => "OGUN"],
            ["id" => "28", "name" => "ONDO"],
            ["id" => "29", "name" => "OSUN"],
            ["id" => "30", "name" => "OYO"],
            ["id" => "31", "name" => "PLATEAU"],
            ["id" => "32", "name" => "RIVERS"],
            ["id" => "33", "name" => "SOKOTO"],
            ["id" => "34", "name" => "TARABA"],
            ["id" => "35", "name" => "YOBE"],
            ["id" => "36", "name" => "ZAMFARA"]
        ];

        foreach ($states as $k=>$state) {
            $states[$k]['lgas'] = $this->processLGA($state['id']);
        }

        file_put_contents('inecdata.json', json_encode($states, JSON_PRETTY_PRINT));
    }

    protected function processLGA($state_id)
    {
        $lgas = $this->getLGA($state_id);
        foreach($lgas as $k=>$lga)
        {
            $lgas[$k]['wards'] = $this->processWard($state_id, $lga['abbreviation']);
        }
        return $lgas;
    }

    protected function processWard($state_id, $lga_id)
    {
        $wards = $this->getWard($state_id, $lga_id);
        foreach($wards as $k=>$ward)
        {
            $wards[$k]['units'] = $this->processUnit($state_id, $lga_id, $ward['abbreviation']);
        }
        return $wards;
    }

    protected function processUnit($state_id, $lga_id, $ward_id)
    {
        return $this->getUnit(
            $state_id,
            $lga_id,
            $ward_id,
        );
    }

    protected function getLGA(int $state_id)
    {
        CLI::write('Fetching LGA: ' . $state_id);
        $savePath = sprintf('states/%s/lgas.json', $state_id);
        if ($this->fileExists($savePath)) {
            return $this->getFile($savePath);
        }
        while (true) {
            try {
                $response = $this->curl->post('lgaView.php', [
                    'verify' => false,
                    'form_params' => [
                        'state_id' => $state_id
                    ]
                ]);
                return $this->storeFile($savePath, $response->getBody());
            } catch (Throwable $e) {
            }
        }
    }

    protected function getWard($state_id, $lga_id)
    {
        CLI::write('Fetching ward: ' . $state_id . ' - ' . $lga_id);
        $savePath = sprintf('states/%s/lgas/%s/wards.json', $state_id, $lga_id);
        if ($this->fileExists($savePath)) {
            return $this->getFile($savePath);
        }
        while (true) {
            try {
                $response = $this->curl->post('wardView.php', [
                    'verify' => false,
                    'form_params' => [
                        'state_id' => $state_id,
                        'lga_id' => $lga_id
                    ]
                ]);
                return $this->storeFile($savePath, $response->getBody());
            } catch (Throwable $e) {
            }
        }
    }


    protected function getUnit($state_id, $lga_id, $ward_id)
    {
        CLI::write('Fetching unit: ' . $state_id . ' - ' . $lga_id . ' - ' . $ward_id);
        $savePath = sprintf('states/%s/lgas/%s/wards/%s/units.json', $state_id, $lga_id, $ward_id);
        if ($this->fileExists($savePath)) {
            return $this->getFile($savePath);
        }
        while (true) {
            try {
                $response = $this->curl->post('unitView.php', [
                    'verify' => false,
                    'form_params' => [
                        'state_id' => $state_id,
                        'lga_id' => $lga_id,
                        'ward_id' => $ward_id,
                    ]
                ]);
                return $this->storeFile($savePath, $response->getBody());
            } catch (Throwable $e) {
            }
        }
    }


    protected function resolvePath($path)
    {
        return WRITEPATH . 'inecdata/' . $path;
    }

    protected function storeFile($path, $content)
    {
        $this->setupPath($path);
        file_put_contents($this->resolvePath($path), $content);
        return json_decode($content, true);
    }

    protected function getFile($path)
    {
        return json_decode(file_get_contents($this->resolvePath($path)), true);
    }

    protected function setupPath($path)
    {
        $dir = dirname($this->resolvePath($path));
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    protected function fileExists($path)
    {
        return file_exists($this->resolvePath($path));
    }
}
