<?php

namespace App\Services;

use App\Services\ApiService as Api;

class CalcService {

    protected $pwp = 30;
    protected $fc = 36;
    protected $depth_factor = [100 => 1, 200 => 1, 300 => 1, 500 => 2, 1000 => 5]; //MM Depth
    protected $api;
    protected $data = [];

    public function __construct() {
        $this->api = new Api();
    }

    function getData($orgId, $zoneInfo, $seedDate, $endDate) {
        $this->init_live_data($orgId, $zoneInfo, $seedDate, $endDate);
        $this->init_history_data($zoneInfo['location']['point']['lat'], $zoneInfo['location']['point']['lon'], $seedDate, $endDate);
        return $this->getChartData($seedDate, $endDate);
    }

    public function init_live_data($orgId, $zoneInfo, $seedDate, $endDate) {
        $moistureData = $this->api->getMoistureData($orgId, $zoneInfo['id'], $seedDate, $endDate);

        $calcs = [];

        if (!empty($moistureData)) {
            foreach ($moistureData['values'] as $value) {
                $d = new \DateTime($value['measurementTime']);
                $year = $d->format('Y');
                $month = $d->format('m');
                $day = $d->format('d');

                $calcs[$year][$month][$day] = $value['probeMeasurements'];
            }
        }

        $this->data['moistures'] = $calcs;

        $weatherData = $this->api->getWeatherData($orgId, $zoneInfo['id'], $seedDate, $endDate);

        $calcs = [];

        if (!empty($weatherData)) {
            foreach ($weatherData['values'] as $value) {

                if ($value['measurementTypeName'] != 'RainGauge')
                    continue;

                $d = new \DateTime($value['measurementTime']);
                $year = $d->format('Y');
                $month = $d->format('m');
                $day = $d->format('d');

                $calcs[$year][$month][$day] = $value['measurementValue'];
            }
        }

        $this->data['weather'] = $calcs;
    }

    public function init_history_data($lat, $lon, $seedDate, $endDate) {
        $seedDate = $this->dateToArray($seedDate);
        $endDate = $this->dateToArray($endDate);

        $lowYear = $seedDate['year'] - 30;
        $highYear = $endDate['year'] - 1;
        $lowDate = "$lowYear-{$seedDate['month']}-{$seedDate['day']}";
        $highDate = "$highYear-{$endDate['month']}-{$endDate['day']}";

        $collecting = true;
        $collectors = [];

        $cur_ranges = [['low' => $lowDate, 'high' => $highDate]];

        $offset = 0;

        while ($collecting) {

            $stations = $this->getStations($lat, $lon, $offset);

            if (empty($stations)) {
                $collecting = false;
                break;
            }

            foreach ($stations as $station) {
                $collector = [];
                $collector['station'] = $station;
                $new_ranges = [];
                foreach ($cur_ranges as $cur_range) {
                    $top = $this->checkPrecipRange($station, $cur_range);
                    $bottom = $this->checkPrecipRange($station, $cur_range, 'ASC');

                    if ($top !== null) {
                        $collector['ranges'][] = ['low' => $bottom, 'high' => $top];

                        if ($top < $cur_range['high']) {
                            $new_ranges[] = ['low' => $top, 'high' => $cur_range['high']];
                        }

                        if ($bottom > $cur_range['low']) {
                            $new_ranges[] = ['low' => $cur_range['low'], 'high' => $bottom];
                        }
                    } else {
                        $new_ranges[] = $cur_range;
                    }
                }

                if (isset($collector['ranges']))
                    $collectors[] = $collector;

                if (empty($new_ranges)) {
                    $collecting = false;
                    break;
                } else {
                    $cur_ranges = $new_ranges;
                }
            }

            $offset = $offset + 10;
        }

        $precips = [];

        foreach ($collectors as $collector) {
            foreach ($collector['ranges'] as $range) {
                $precips = array_merge($precips, $this->getPrecips($collector['station'], $range));
            }
        }

        foreach ($precips as $precip) {
            $d = new \DateTime($precip['date']);
            $this->data['precips'][$d->format('Y')][$d->format('m')][$d->format('d')] = $precip['rain'];
        }


        $avgData = $this->prepareAccAvgRain($seedDate, $endDate);

        $this->data['expected_rain'] = $avgData[0];
        $this->data['avg_30'] = $avgData[1];
    }

    public function getStations($lat, $lon, $offset = 0, $limit = 10) {
        $latSign = $lat >= 0 ? '-' : '+';
        $lonSign = $lon >= 0 ? '-' : '+';

        $lat = abs($lat);
        $lon = abs($lon);

        $query = "SELECT * FROM `station` order by  ABS(`lat` $latSign $lat) + ABS(`lon` $lonSign $lon) ASC LIMIT $offset,$limit";

        return json_decode(json_encode(app('db')->select($query)), true);
    }

    public function getPrecips($station, $range) {
        $query = "SELECT * FROM `precip` WHERE `station` = '{$station['id']}' AND `date` BETWEEN '{$range['low']}' AND '{$range['high']}' ORDER BY `date` DESC";
        return json_decode(json_encode(app('db')->select($query)), true);
    }

    public function checkPrecipRange($station, $range, $order = 'DESC') {
        $query = "SELECT * FROM `precip` WHERE `station` = '{$station['id']}' AND `date` BETWEEN '{$range['low']}' AND '{$range['high']}' ORDER BY `date` $order LIMIT 1";

        $data = json_decode(json_encode(app('db')->select($query)), true);

        if (!empty($data))
            return $data[0]['date'];
        else
            return null;
    }

    function getChartData($seedDate, $endDate) {

        $d1 = strtotime('-1 day', strtotime($seedDate));
        $d2 = new \DateTime($endDate);

        $go = true;

        $available_water = [];
        $avg_10 = [];
        $avg_5 = [];
        $rain = [];
        $yield = [];

        $total_rain = 0;
        while ($go) {
            $d = new \DateTime(date('Y-m-d', strtotime('+1 day', $d1)));

            $year = $d->format('Y');
            $month = $d->format('m');
            $day = $d->format('d');
            $date = $day . '-' . $month . '-' . $year;

            if ($date == $d2->format('d') . '-' . $d2->format('m') . '-' . $d->format('Y'))
                $go = false;

            date_default_timezone_set('UTC');
            $date = (strtotime($date) * 1000) - (strtotime('01-01-1970 00:00:00') * 1000);

            //Calculate 10 & 5 years rain avgs
            $avg_10[] = [$date, $this->getAvgRain(10, $year, $month, $day)];
            $avg_5[] = [$date, $this->getAvgRain(5, $year, $month, $day)];


            //Calculate Available Water
            $curAvWater = $this->calcAvailableWater($year, $month, $day);
            $available_water[] = [$date, $curAvWater];

            //Current Rain
            if (isset($this->data['weather'][$year][$month][$day])) {
                $curRain = $this->data['weather'][$year][$month][$day];
                $total_rain += $curRain;
            } else {
                $curRain = null;
            }

            $rain[] = [$date, $curRain];

            //Yield Calculations
            if (!isset($moisture) && $curAvWater != null)
                $moisture = $curAvWater;

            if (isset($moisture) && "$year-$month-$day" <= date('Y-m-d')) {
                $curYield = $this->getYieldPotential($moisture, $total_rain, $year, $month, $day);
            } else {
                $curYield = null;
            }

            $yield[] = [$date, $curYield];

            $d1 = $d->getTimestamp();
        }


        return [$avg_10, $avg_5, $rain, $available_water, $yield];
    }

    function calcAvailableWater($year, $month, $day) {
        $moistureData = $this->data['moistures'];

        $value = null;
        if (isset($moistureData[$year][$month][$day])) {
            $value = 0;
            foreach ($moistureData[$year][$month][$day] as $cal) {
                $factor = 1;

                if (isset($this->depth_factor[$cal['depth']]))
                    $factor = $this->depth_factor[$cal['depth']];

                $value += ($cal['value'] - $this->pwp) * $factor;
            }

            $value = round($value / 25.4, 2);
        }

        return $value;
    }

    private function dateToArray($date) {

        $year = substr($date, 0, 4);
        $month = substr($date, 5, 2);
        $day = substr($date, 8);

        return['year' => $year, 'month' => $month, 'day' => $day];
    }

    function getAvgRain($num, $year, $month, $day) {
        $total = 0;

        for ($i = $num; $i > 0; $i--) {
            $total += $this->data['precips'][$year - $i][$month][$day];
        }

        return round($total / $num, 2);
    }

    function prepareAccAvgRain($seedDate, $endDate, $num = 30) {

        $accAvgs = [];
        $avgs = [];

        $date = "$endDate[year]-$endDate[month]-$endDate[day]";
        $cur = new \DateTime($date);
        $curAvgRain = 0;

        $go = true;
        while ($go) {
            $year = $cur->format('Y');
            $month = $cur->format('m');
            $day = $cur->format('d');

            $newAvgRain = $this->getAvgRain($num, $year, $month, $day);
            $avgs[$year][$month][$day] = $newAvgRain;
            $curAvgRain = $accAvgs[$year][$month][$day] = $curAvgRain + $newAvgRain;

            if (strtotime("$year-$month-$day") <= strtotime("$seedDate[year]-$seedDate[month]-$seedDate[day]"))
                $go = false;

            $cur = new \DateTime(date('Y-m-d', strtotime('-1 day', strtotime("$year-$month-$day"))));
        }

        return [$accAvgs, $avgs];
    }

    function getYieldPotential($moisture, $rain, $year, $month, $day) {
        $data = [];

        $h2o = 4;
        $canola = 6;

        if (isset($this->data['expected_rain'][$year][$month][$day]))
            $avg = $this->data['expected_rain'][$year][$month][$day];
        else
            $avg = 0;

        $val = ($moisture + (($rain + $avg) / 25.4) - $h2o) * $canola;

        return round($val, 2);
    }

}
