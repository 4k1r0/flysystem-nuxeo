<?php


namespace Ak1r0\Flysystem\Plugin;


interface UidResolver
{
    /**
     * Resolve an UID into a path
     *
     * @param string $uid
     *
     * @return string
     */
    public function resolveUid($uid);
}