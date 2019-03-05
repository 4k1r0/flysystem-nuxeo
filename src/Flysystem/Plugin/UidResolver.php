<?php

namespace Ak1r0\Flysystem\Plugin;

use League\Flysystem\FileNotFoundException;

interface UidResolver
{
    /**
     * Resolve an UID into a path
     *
     * @param string $uid
     *
     * @return string
     * @throws FileNotFoundException
     */
    public function resolveUid($uid);
}