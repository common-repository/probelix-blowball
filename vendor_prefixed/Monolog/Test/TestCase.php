<?php declare(strict_types=1);

/*
 * This file is part of the PbxVendor\Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PbxVendor\Monolog\Test;

use PbxVendor\Monolog\Logger;
use PbxVendor\Monolog\DateTimeImmutable;
use PbxVendor\Monolog\Formatter\FormatterInterface;

/**
 * Lets you easily generate log records and a dummy formatter for testing purposes
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @phpstan-import-type Record from \PbxVendor\Monolog\Logger
 * @phpstan-import-type Level from \PbxVendor\Monolog\Logger
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param mixed[] $context
     *
     * @return array Record
     *
     * @phpstan-param  Level $level
     * @phpstan-return Record
     */
    protected function getRecord(int $level = Logger::WARNING, string $message = 'test', array $context = []): array
    {
        return [
            'message' => (string) $message,
            'context' => $context,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new DateTimeImmutable(true),
            'extra' => [],
        ];
    }

    /**
     * @phpstan-return Record[]
     */
    protected function getMultipleRecords(): array
    {
        return [
            $this->getRecord(Logger::DEBUG, 'debug message 1'),
            $this->getRecord(Logger::DEBUG, 'debug message 2'),
            $this->getRecord(Logger::INFO, 'information'),
            $this->getRecord(Logger::WARNING, 'warning'),
            $this->getRecord(Logger::ERROR, 'error'),
        ];
    }

    protected function getIdentityFormatter(): FormatterInterface
    {
        $formatter = $this->createMock(FormatterInterface::class);
        $formatter->expects($this->any())
            ->method('format')
            ->will($this->returnCallback(function ($record) {
                return $record['message'];
            }));

        return $formatter;
    }
}
