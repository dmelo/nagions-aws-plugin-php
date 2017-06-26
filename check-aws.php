#!/usr/bin/php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Aws\CloudWatch\CloudWatchClient;
use Aws\Exception\CredentialsException;
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
    ->aka('warning')
    ->describeAs('Warning threshold. Required only if format nagios is chosen');
$cmd->option('c')
    ->aka('critical')
    ->describeAs('Critical threshold. Required only if format nagios is chosen');
$cmd->option('f')
    ->aka('format')
    ->describeAs('Output format. Options are: nagios, bosun. If not informed, defaults to nagios')
    ->must(function($format) {
        $formatList = array('bosun', 'nagios');
        return in_array($format, $formatList);
    });

$options = $cmd->getOptions();
$profile = $options['p']->getValue();
$region = $options['r']->getValue();
$namespace = $options['n']->getValue();
$metric = $options['m']->getValue();
$dimensionName = $options['d']->getValue();
$dimensionValue = $options['i']->getValue();
$format = null === $options['f']->getValue() ? 'nagios' : $options['f']->getValue();
if ('nagios' === $format && (null === $options['w']->getValue() || null === $options['c']->getValue())) {
    echo "Nagios format require -w and -c to be set" . PHP_EOL;
    exit(3);
}

$w = (int) $options['w']->getValue();
$c = (int) $options['c']->getValue();



try {
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

} catch (CredentialsException $e) {
    echo $e->getMessage();
    exit(3);
}

$datapoints = $ret->get('Datapoints');

foreach ($datapoints as $key => $value) {
    $timestamp = $value['Timestamp']->getTimestamp();
    $point = $value['Average'];
}

if ('nagios' === $format) {
    $inc = $w < $c; // Is it increasing or decreasing?
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
    exit($state);
} elseif ('bosun' === $format) {
    $obj = new \stdClass();
    $obj->metric = "CheckAWS.$namespace.$metric";
    $obj->timestamp = $timestamp;
    $obj->value = $point;
    $obj->tags = new \stdClass();
    $obj->tags->$dimensionName = $dimensionValue;
    $obj->tags->region = $region;
    
    echo json_encode($obj);
}
