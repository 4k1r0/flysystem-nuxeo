<?php

namespace Ak1r0\Flysystem\Plugin;

use League\Flysystem\FilesystemInterface;
use League\Flysystem\PluginInterface;
use League\Flysystem\Util;

/**
 *
 */
class MimetypeConverterPlugin implements PluginInterface
{
    /** @var FilesystemInterface */
    protected $filesystem;

    /** @var MimetypeConverter */
    protected $adapter;

    /**
     * @param MimetypeConverter $adapter
     */
    public function __construct(MimetypeConverter $adapter)
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
        return 'convert';
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
     * Convert a doc to another mimetype
     *
     * @param string $path
     * @param string $mimetype
     *
     * @return array|false
     */
    public function handle($path, $mimetype)
    {
        if (!in_array($mimetype, Util\MimeType::getExtensionToMimeTypeMap())) {
            return false; //@TODO throw exception ?
        }

        return $this->adapter->convert($path, $mimetype);
    }
}