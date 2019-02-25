<?php

namespace Ak1r0\Flysystem\Plugin;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;

/**
 *
 */
class ConcatenatorPlugin implements PluginInterface
{
    /** @var FilesystemInterface */
    protected $filesystem;

    /** @var Concatenator */
    protected $adapter;

    /**
     * @param Concatenator $adapter
     */
    public function __construct(Concatenator $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Get the method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return 'concatenate';
    }

    /**
     * Set the Filesystem object.
     *
     * @param FilesystemInterface $filesystem
     */
    public function setFilesystem(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Concatenate many documents into one
     *
     * @param array $paths
     * @param bool $isUids - If true, Concatenate by uuid, else by path
     *
     * @return array|false
     */
    public function handle(array $paths, $isUids = false)
    {
        return $isUids ?
            $this->adapter->concatenateByUids($paths)
            : $this->adapter->concatenate($paths);
    }
}