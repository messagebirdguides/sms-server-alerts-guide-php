<?php

use Monolog\Logger, Monolog\Handler\AbstractProcessingHandler;
use MessageBird\Client, MessageBird\Objects\Message;

/**
 * This Monolog handler sends log messages via SMS
 * using the MessageBird SDK.
 */
class MessageBirdHandler extends AbstractProcessingHandler {

    private $messagebird;
    private $message;

    public function __construct(array $options, $level = Logger::DEBUG, bool $bubble = true) {
        if (!isset($options['apiKey']) || !isset($options['originator']) || !isset($options['recipients']))
            throw new \Exception("Incomplete configuration parameters. Required: apiKey, originator, recipients");

        $this->messagebird = new Client($options['apiKey']);
        $this->message = new Message;
        $this->message->originator = $options['originator'];
        $this->message->recipients = $options['recipients'];

        parent::__construct($level, $bubble);
    }

    protected function write(array $record) {
        // Shorten log entry
        $this->message->body = (strlen($record['formatted']) > 140)
            ? substr($record['formatted'], 0, 140) . ' ...'
            : $record['formatted'];

        // Send notification with MessageBird SDK
        try {
            $this->messagebird->messages->create($this->message);
        } catch (Exception $e) {
            error_log(get_class($e).": ".$e->getMessage());
        }
    }
}