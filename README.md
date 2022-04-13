# Drupal Datadog module

Uses a Monolog handler to send Logs to Datadog without a Datadog agent. Also there's a processor that maps the log levels from Drupal to Datadogs log status.
Based on https://github.com/guspio/monolog-datadog and https://github.com/nohponex/monolog-datadog-handler/blob/master/src/DataDogHandler.php.

## Usage

`docroot/sites/default/logging.services.yml`

```yaml
parameters:
  monolog.channel_handlers:
    default: ['datadog']
    php: ['error_log', 'datadog']
services:
  monolog.handler.datadog:
    class: MonologDatadog\Handler\DatadogHandler
    arguments: ['yourSuperSecureAPIKey']
```