<?php

namespace Ak1r0\Flysystem\Plugin;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\PluginInterface;

/**
 *
 */
class UidResolverPlugin implements PluginInterface
{
    /** @var FilesystemInterface */
    protected $filesystem;

    /** @var UidResolver */
    protected $adapter;

    /**
     * @param UidResolver $adapter
     */
    public function __construct(UidResolver $adapter)
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
        return 'resolveUid';
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
     * Transform a Nuxeo UID into a Nuxeo Path
     *
     * @param string $uid
     *
     * @return string
     * @throws FileNotFoundException
     */
    public function handle($uid)
    {
        return $this->adapter->resolveUid($uid);
    }
}