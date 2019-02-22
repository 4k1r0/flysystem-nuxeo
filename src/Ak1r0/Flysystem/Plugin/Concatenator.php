<?php

namespace Ak1r0\Flysystem\Plugin;

/**
 * Allow usage of ConcatenatorPlugin with one adpater
 */
interface Concatenator
{
    /**
     * Concatenate many documents into one
     *
     * @param string[] $paths
     *
     * @return array|false
     */
    public function concatenate(array $paths);

    /**
     * Concatenate many documents into one
     *
     * @param string[] $uids
     *
     * @return array|false
     */
    public function concatenateByUids(array $uids);
}