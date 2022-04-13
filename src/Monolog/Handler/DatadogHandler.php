<?php

declare(strict_types=1);

namespace Drupal\datadog\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Drupal\datadog\Monolog\Processor\LevelProcessor;
use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Formatter\JsonFormatter;

/**
 * Sends logs to Datadog Logs using Curl integrations.
 *
 * You'll need a Datadog account to use this handler.
 */
class DatadogHandler extends AbstractProcessingHandler {
  /**
   * Datadog Api Key access.
   *
   * @var string
   */
  protected const DATADOG_LOG_HOST = 'https://http-intake.logs.datadoghq.com/api/v2/logs';

  /**
   * Datadog Api Key access.
   *
   * @var string
   */
  private $apiKey;

  /**
   * Datadog optionals attributes.
   *
   * @var array
   */
  private $attributes;

  /**
   * Constructor.
   *
   * @param string $apiKey
   *   Datadog Api Key access.
   * @param array $attributes
   *   Some options fore Datadog Logs.
   * @param string|int $level
   *   The minimum logging level at which this handler will be triggered.
   * @param bool $bubble
   *   Whether the messages that are handled can bubble up the stack or not.
   */
  public function __construct(
    string $apiKey,
    array $attributes = [],
           $level = Logger::DEBUG,
    bool $bubble = TRUE
  ) {
    if (!extension_loaded('curl')) {
      throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
    }

    parent::__construct($level, $bubble);

    $this->apiKey = $this->getApiKey($apiKey);
    $this->attributes = $attributes;
    $this->pushProcessor(new LevelProcessor());

  }

  /**
   * Handles a log record.
   *
   * @param array $record
   *   The record.
   */
  protected function write(array $record): void {
    $this->send($record['formatted']);
  }

  /**
   * Send request to https://http-intake.logs.datadoghq.com on send action.
   *
   * @param string $record
   *   The record.
   */
  protected function send(string $record): void {
    $headers = [
      'Content-Type:application/json',
        sprintf('DD-API-KEY:%s', $this->apiKey),
    ];

    $source = $this->getSource();
    $hostname = $this->getHostname();
    $service = $this->getService($record);

    $url = sprintf(
      '%s?ddsource=%s&service=%s&hostname=%s',
      self::DATADOG_LOG_HOST,
      $source,
      $service,
      $hostname
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $record);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

    Util::execute($ch);
  }

  /**
   * Get Datadog Api Key from $attributes params.
   *
   * @param string $apiKey
   *   The api key.
   */
  protected function getApiKey(string $apiKey): string {
    if ($apiKey) {
      return $apiKey;
    }
    else {
      throw new \Exception('The Datadog Api Key is required');
    }
  }

  /**
   * Get Datadog Source from $attributes params.
   *
   * @return mixed|string
   *   Mixed.
   */
  protected function getSource() {
    return !empty($this->attributes['source']) ? $this->attributes['source'] : 'php';
  }

  /**
   * Get service.
   *
   * @param string $record
   *   The record.
   *
   * @return mixed
   *   Mixed.
   */
  protected function getService(string $record) {
    $channel = json_decode($record, TRUE);

    return !empty($this->attributes['service']) ? $this->attributes['service'] : $channel['channel'];
  }

  /**
   * Get Datadog Hostname from $attributes params.
   */
  protected function getHostname() {
    return !empty($this->attributes['hostname']) ? $this->attributes['hostname'] : $_SERVER['SERVER_NAME'];
  }

  /**
   * Returns the default formatter to use with this handler.
   *
   * @return \Monolog\Formatter\JsonFormatter
   *   Returns formatter.
   */
  protected function getDefaultFormatter(): FormatterInterface {
    return new JsonFormatter();
  }

}
