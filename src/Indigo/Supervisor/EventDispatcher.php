<?php

namespace Indigo\Supervisor;

use Indigo\Supervisor\EventListener\EventListenerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class EventDispatcher implements LoggerAwareInterface
{
	/**
	 * Responses sent to supervisor
	 */
	const READY = "READY\n";
	const OK    = "RESULT 2\nOK";
	const FAIL  = "RESULT 4\nFAIL";

	/**
	 * Psr logger
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	protected $listeners = array();

	protected $inputStream;
	protected $outputStream;
	protected $errorStream;

	public function __construct($inputStream = STDIN, $outputStream = STDOUT, $errorStream = STDERR)
	{
		$this->inputStream  = $inputStream;
		$this->outputStream = $outputStream;
		$this->errorStream  = $errorStream;

		$this->logger = new NullLogger();
	}

	public function addListener(EventListenerInterface $listener, $prepend = false)
	{
		if ($prepend) {
			array_unshift($this->listeners, $listener);
		} else {
			array_push($this->listeners, $listener);
		}

		return $this;
	}

	public function listen()
	{
		$this->write(self::READY);

		while (true) {
			if ( ! $headers = $this->read()) {
				continue;
			}

			$headers = $this->parseData($headers);

			$payload = $this->read($headers['len']);

			$payload = explode("\n", $payload, 2);

			$payload[0] = array_merge($headers, $this->parseData($payload[0]));

			$result = $this->dispatch($payload);

			if ($result === true) {
				$this->write(self::OK);
			} elseif ($result === false) {
				$this->write(self::FAIL);
			}

			$this->write(self::READY);
		}
	}

	protected function dispatch($payload)
	{
		$result = true;
		foreach ($this->listeners as $listener) {
			// Return after failure immediately?
			$result &= $listener->listen($payload);

			if ($listener->isPropagationStopped()) {
				break;
			}
		}

		return (bool)$result;
	}

	protected function parseData($rawData)
	{
        $outputData = array();
        foreach (explode(' ', $rawData) as $data) {
        	$data = explode(':', $data);
        	$outputData[$data[0]] = $data[1];
        }

        return $outputData;
	}

	protected function read($length = null)
	{
		if (is_null($length)) {
			return trim(fgets($this->inputStream));
		} else {
			return fread($this->inputStream, $length);
		}
	}

	protected function write($value)
	{
		fwrite($this->outputStream, $value);
	}

    /**
     * Sets a logger
     *
     * @param LoggerInterface $logger
     */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}
}
