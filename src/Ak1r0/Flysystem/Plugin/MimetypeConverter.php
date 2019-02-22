<?php

namespace Ak1r0\Flysystem\Plugin;

/**
 *
 */
interface MimetypeConverter
{
    /**
     * Convert a doc to another mimetype
     *
     * @param string $path
     * @param string $mimetype
     *
     * @return array|false
     */
    public function convert($path, $mimetype);
}