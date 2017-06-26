-p/--profile <argument>
     Required. Section name of your ~/.aws/credentials file

-r/--region <argument>
     Required. Region name e.g.: sa-east-1 (South America / Sao Paulo)

-d/--dimension-name <argument>
     Required. Dimension name, can be found at the alarm detail, on AWS CloudWatch panel

-i/--dimension-value <argument>
     Required. Dimension value, can be found at the alarm detail, on AWS CloudWatch panel

-m/--metric <argument>
     Required. Metric name, can be found at the alarm detail, on AWS CloudWatch panel

-n/--namespace <argument>
     Required. Metric namesapce, can be found at the alarm detail, on AWS CloudWatch panel

-w/--warning <argument>
     Warning threshold

-c/--critical <argument>
     Critical threshold


## Bosun

To work with Bosun, `-c` and `-w` flags should not be provided. The script will
only output the json string that should be sent to Bosun, you must encapsulate
that command inside another script, e.g.:

```bash
curl -X POST -d "`./check-aws.php -p default -r sa-east-1 -n 'AWS/RDS' -m CPUCreditBalance -d DBInstanceIdentifier -i db-i-00 -f bosun`" http://bosun.example.com:8070/api/put
```
