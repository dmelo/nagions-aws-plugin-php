<?php

require_once __DIR__ . '/vendor/autoload.php';

use Aws\CloudWatch\CloudWatchClient;
use Commando\Command;

$cmd = new Command();
$cmd->option('p')
    ->require()
    ->aka('profile')
    ->describeAs('Section name of your ~/.aws/credentials file');
$cmd->option('r')
    ->require()
    ->aka('region')
    ->describeAs('Region name e.g.: sa-east-1 (South America / Sao Paulo)');
$cmd->option('n')
    ->require()
    ->aka('namespace')
    ->describeAs('Metric namesapce, can be found at the alarm detail, on AWS CloudWatch panel');
$cmd->option('m')
    ->require()
    ->aka('metric')
    ->describeAs('Metric name, can be found at the alarm detail, on AWS CloudWatch panel');
$cmd->option('d')
    ->require()
    ->aka('dimension-name')
    ->describeAs('Dimension name, can be found at the alarm detail, on AWS CloudWatch panel');
$cmd->option('i')
    ->require()
    ->aka('dimension-value')
    ->describeAs('Dimension value, can be found at the alarm detail, on AWS CloudWatch panel');
$cmd->option('w')
    ->require()
    ->aka('warning')
    ->describeAs('Warning threshold');
$cmd->option('c')
    ->require()
    ->aka('critical')
    ->describeAs('Critical threshold');

$options = $cmd->getOptions();
$profile = $options['p']->getValue();
$region = $options['r']->getValue();
$namespace = $options['n']->getValue();
$metric = $options['m']->getValue();
$dimensionName = $options['d']->getValue();
$dimensionValue = $options['i']->getValue();
$w = (int) $options['w']->getValue();
$c = (int) $options['c']->getValue();


$client = CloudWatchClient::factory(array(
    'profile' => $profile,
    'region' => $region,
    'version' => '2010-08-01',
));


$startTime = new \DateTime();
$startTime = $startTime->sub(new \DateInterval('PT10M'));
$endTime = new \DateTime();



$ret = $client->getMetricStatistics(array(
    'Namespace' => $namespace,
    'MetricName' => $metric,
    'StartTime' => $startTime,
    'EndTime' => $endTime,
    'Period' => 600,
    'Statistics' => array('Average'),
    'Dimensions' => array(
        array(
            'Name' => $dimensionName,
            'Value' => $dimensionValue,
        )
   )
));

$inc = $w < $c; // Is it increasing or decreasing?
$datapoints = $ret->get('Datapoints');

foreach ($datapoints as $key => $value) {
    $point = $value['Average'];
}

if (isset($point)) {
    if ($inc) {
        $state = $point >= $w ? $point >= $c ? 2 : 1 : 0;
    } else {
        $state = $point <= $w ? $point <= $c ? 2 : 1 : 0;
    }
} else {
    $state = 3;
    $point = '<unknown>';
}

$msg = '';
switch ($state) {
    case 0:
        $msg = 'OK';
        break;
    case 1:
        $msg = 'Warning';
        break;
    case 2:
        $msg = 'Critical';
        break;
    default:
        $msg = 'Unknown';
}

$msg .= ". $namespace, $metric, $dimensionName -> $dimensionValue, with average $point";
echo $msg . PHP_EOL;
return $state;
