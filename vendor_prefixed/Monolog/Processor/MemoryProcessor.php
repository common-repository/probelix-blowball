<?php declare(strict_types=1);

/*
 * This file is part of the PbxVendor\Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PbxVendor\Monolog\Processor;

/**
 * Some methods that are common for all memory processors
 *
 * @author Rob Jensen
 */
abstract class MemoryProcessor implements ProcessorInterface
{
    /**
     * @var bool If true, get the real size of memory allocated from system. Else, only the memory used by emalloc() is reported.
     */
    protected $realUsage;

    /**
     * @var bool If true, then format memory size to human readable string (MB, KB, B depending on size)
     */
    protected $useFormatting;

    /**
     * @param bool $realUsage     Set this to true to get the real size of memory allocated from system.
     * @param bool $useFormatting If true, then format memory size to human readable string (MB, KB, B depending on size)
     */
    public function __construct(bool $realUsage = true, bool $useFormatting = true)
    {
        $this->realUsage = $realUsage;
        $this->useFormatting = $useFormatting;
    }

    /**
     * Formats bytes into a human readable string if $this->useFormatting is true, otherwise return $bytes as is
     *
     * @param  int        $bytes
     * @return string|int Formatted string if $this->useFormatting is true, otherwise return $bytes as int
     */
    protected function formatBytes(int $bytes)
    {
        if (!$this->useFormatting) {
            return $bytes;
        }

        if ($bytes > 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2).' MB';
        } elseif ($bytes > 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes . ' B';
    }
}
