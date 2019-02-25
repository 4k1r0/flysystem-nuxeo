<?php

namespace Ak1r0\Flysystem\Adapter;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Nuxeo\Client\Api\NuxeoClient;
use Nuxeo\Client\Api\Objects as NuxeoObjects;
use Nuxeo\Client\Internals\Spi\NuxeoClientException;
use Ak1r0\Flysystem\Plugin\Concatenator;
use Ak1r0\Flysystem\Plugin\MimetypeConverter;
use Ak1r0\Flysystem\Plugin\UidResolver;
use Zend\Loader\Exception\BadMethodCallException;

/**
 * @TODO gerer les exceptions et les erreurs sur les methodes delete, deleteDir, copy, rename
 *       doit retourner un boolean mais il faut permettre d'acceder aux erreurs s'il y en a une
 */
class Nuxeo extends AbstractAdapter implements CanOverwriteFiles, UidResolver, MimetypeConverter, Concatenator
{
    /** @var NuxeoClient */
    protected $nuxeoclient;

    /**
     * Nuxeo Adapter constructor.
     *
     * @param NuxeoClient $nuxeoClient
     */
    public function __construct(NuxeoClient $nuxeoClient)
    {
        $this->nuxeoclient = $nuxeoClient;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        $tmp = $this->createTmpFile($contents);

        /** @var NuxeoObjects\Document $doc */
        /** @var NuxeoObjects\Blob\Blob $blob */
        list($doc, $blob) = $this->createFromStream($path, $tmp, $config);

        fclose($tmp);

        return $this->normalizeFileProperties(
            $doc->getPath(),
            strtotime($doc->getLastModified()),
            $blob->getFile()->getSize(),
            $blob->getMimeType(),
            $doc->getUid()
        );
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        /** @var NuxeoObjects\Document $doc */
        /** @var NuxeoObjects\Blob\Blob $blob */
        list($doc, $blob) = $this->createFromStream($path, $resource, $config);

        return $this->normalizeFileProperties(
            $doc->getPath(),
            strtotime($doc->getLastModified()),
            $blob->getFile()->getSize(),
            $blob->getMimeType(),
            $doc->getUid()
        );
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        $tmp = $this->createTmpFile($contents);

        $return = $this->updateStream($path, $tmp, $config);

        fclose($tmp);

        return $return;
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        $tmpFilename = stream_get_meta_data($resource)['uri'];
        $mimeType    = mime_content_type($tmpFilename);

        $path = $this->applyPathPrefix($path);

        $blob = $this->uploadFile($path, $tmpFilename, $mimeType);

        return $this->normalizeFileProperties(
            $path,
            time(),
            $blob->getFile()->getSize(),
            $blob->getMimeType()
        );
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        // Make sure the directory has been created first.
        $this->createDir(Util::dirname($newpath));

        $newName = basename($newpath);
        $path    = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        // Move
        /** @var NuxeoObjects\Document $doc */
        $doc = $this->nuxeoclient->automation('Document.Move')
            ->input('doc:' . $this->normalizePath($path))
            ->params([
                'target' => $this->normalizePath(Util::dirname($newpath)),
                'name'   => $newName,
            ])
            ->execute(NuxeoObjects\Document::className);

        // Rename
        $doc2 = $this->nuxeoclient->automation('Document.Update')
            ->input('doc:' . $doc->getPath())
            ->params([
                'properties' => 'dc:title=' . $newName,
            ])
            ->execute(NuxeoObjects\Document::className);

        return $doc && $doc2;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        // Make sure the directory has been created first.
        $this->createDir(Util::dirname($newpath));

        $newName = basename($newpath);
        $path    = $this->applyPathPrefix($path);
        $newpath = $this->applyPathPrefix($newpath);

        // Copy
        $doc = $this->nuxeoclient->automation('Document.Copy')
            ->input('doc:' . $this->normalizePath($path))
            ->params([
                'target'     => $this->normalizePath(Util::dirname($newpath)),
                'name'       => $newName
            ])
            ->execute(NuxeoObjects\Document::className);

        // Rename
        $doc2 = $this->nuxeoclient->automation('Document.Update')
            ->input('doc:' . $doc->getPath())
            ->params([
                'properties' => 'dc:title=' . $newName,
            ])
            ->execute(NuxeoObjects\Document::className);

        return $doc && $doc2;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $doc = $this->nuxeoclient->automation('Document.Delete')
            ->input('doc:' . $this->normalizePath($path))
            ->execute(NuxeoObjects\Blob\Blob::className);

        return true;
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        return $this->delete($dirname);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config = null)
    {
        if ($dirname === '.') {
            $dirname = '';
        }

        $dirname = trim($this->applyPathPrefix($dirname), '/');
        $dirParts = explode('/', $dirname);
        $n = count($dirParts);

        try {
            // We need to recursively create the directories if we have a path.
            for ($i = 1; $i <= $n; $i++) {
                $partialDirectory = implode(DIRECTORY_SEPARATOR, array_slice($dirParts, 0, $i));

                if (! $this->exists($partialDirectory)) {
                    $name = basename($partialDirectory);

                    $this->nuxeoclient->automation('Document.Create')
                        ->input('doc:' . $this->normalizePath(Util::dirname($dirname))) # refer to the parent document
                        ->params([
                            'type'       => 'Folder',
                            'name'       => $name,
                            'properties' => 'dc:title=' . $name,
                        ])
                        ->execute(NuxeoObjects\Document::className);
                }
            }
        } catch (NuxeoClientException $e) {
            throw new \RuntimeException(sprintf('The directory \'%s\' could not be created.', $dirname), 0, $e);
        }

        return ['path' => $this->normalizePath($dirname), 'type' => 'dir'];
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     * @throws NuxeoClientException If an error happen within Nuxeo API
     */
    public function has($path)
    {
        $path = $this->applyPathPrefix($path);
        return $this->exists($path);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return array|bool|null
     * @throws NuxeoClientException If an error happen within Nuxeo API
     */
    protected function exists($path)
    {
        try {
            return $this->findByPath($path) ? true : false;
        } catch (NuxeoClientException $e) {
            if (666 == $e->getCode()) {
                return false;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $path = $this->applyPathPrefix($path);

        /** @var NuxeoObjects\Document $doc */
        $doc = $this->findByPath($path);

        /** @var NuxeoObjects\Blob\Blob $blob */
        $blob = $this->nuxeoclient
            ->automation('Blob.Get')
            ->input('doc:' . $this->normalizePath($path))
            ->execute(NuxeoObjects\Blob\Blob::className);

        return array_merge(
            $this->normalizeFileProperties(
                $doc->getPath(),
                $doc->getLastModified(),
                $blob->getFile()->getSize(),
                $blob->getMimeType(),
                $doc->getUid()
            ),
            ['contents' => file_get_contents($blob->getFile()->getPathname())]
        );
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $path = $this->applyPathPrefix($path);

        /** @var NuxeoObjects\Document $doc */
        $doc = $this->findByPath($path);

        /** @var NuxeoObjects\Blob\Blob $blob */
        $blob = $this->nuxeoclient
            ->automation('Blob.Get')
            ->input('doc:' . $this->normalizePath($path))
            ->execute(NuxeoObjects\Blob\Blob::className);

        $handler = fopen($blob->getFile()->getPathname(), 'r');

        return array_merge(
            $this->normalizeFileProperties(
                $doc->getPath(),
                $doc->getLastModified(),
                $blob->getFile()->getSize(),
                $blob->getMimeType(),
                $doc->getUid()
            ),
            ['stream' => $handler]
        );
    }

    /**
     * Convert a doc to another mimetype
     *
     * @param string $path
     * @param string $mimetype
     *
     * @return array|false
     */
    public function convert($path, $mimetype)
    {
        /** @var NuxeoObjects\Blob\Blob $blob */
        $blob = $this->nuxeoclient
            ->automation('Blob.Convert')
            ->input('doc:' . $this->normalizePath($path))
            ->params([
                'mimeType' => $mimetype,
            ])
            ->execute(NuxeoObjects\Blob\Blob::className);

        return array_merge(
            $this->normalizeFileProperties(
                $path,
                time(),
                $blob->getFile()->getSize(),
                $blob->getMimeType()
            ),
            ['contents' => file_get_contents($blob->getFile()->getPathname())]
        );
    }

    /**
     * Concatenate many documents into one
     *
     * @param string[] $paths
     *
     * @return array|false
     */
    public function concatenate(array $paths)
    {
        $documents = [];
        foreach ($paths as $path) {
            $documents[] = $this->findByPath($path);
        }

        /** @var NuxeoObjects\Blob\Blob $blob */
        $blob = $this->nuxeoclient
            ->automation('PDF.MergeWithDocs')
            ->input($documents)
            ->execute(NuxeoObjects\Blob\Blob::className);

        return array_merge(
            $this->normalizeFileProperties(
                $blob->getFile()->getPathname(),
                time(),
                $blob->getFile()->getSize(),
                $blob->getMimeType()
            ),
            ['contents' => file_get_contents($blob->getFile()->getPathname())]
        );
    }

    /**
     * Concatenate many documents into one
     *
     * @param string[] $uids
     *
     * @return array|false
     */
    public function concatenateByUids(array $uids)
    {
        /** @var NuxeoObjects\Documents $documents */
        $documents = $this->nuxeoclient
            ->automation('Document.Query')
            ->param('query', 'SELECT * FROM Document WHERE ecm:uuid IN ("' . implode('","', $uids) . '")')
            ->execute(NuxeoObjects\Documents::className);

        /** @var NuxeoObjects\Blob\Blob $blob */
        $blob = $this->nuxeoclient
            ->automation('PDF.MergeWithDocs')
            ->input($documents->getDocuments())
            ->execute(NuxeoObjects\Blob\Blob::className);

        return array_merge(
            $this->normalizeFileProperties(
                $blob->getFile()->getPathname(),
                time(),
                $blob->getFile()->getSize(),
                $blob->getMimeType()
            ),
            ['contents' => file_get_contents($blob->getFile()->getPathname())]
        );
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     * @throws BadMethodCallException Not implemented yet
     */
    public function listContents($directory = '', $recursive = false)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     * @throws BadMethodCallException Not implemented yet
     */
    public function getMetadata($path)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws BadMethodCallException Not implemented yet
     */
    public function getSize($path)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws BadMethodCallException Not implemented yet
     */
    public function getMimetype($path)
    {
        /** @var NuxeoObjects\Blob\Blob $blob */
//        $blob = $this->service
//            ->automation('Blob.Get')
//            ->input('doc:' . $this->normalizePath($path))
//            ->execute(NuxeoObjects\Blob\Blob::class);
//
//        return $blob->getMimeType();

        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     *
     * @return array|false
     * @throws BadMethodCallException Not implemented yet
     */
    public function getTimestamp($path)
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     * @throws BadMethodCallException Not implemented yet
     */
    public function getVisibility($path)
    {
        throw new \BadMethodCallException('Not implemented');
    }


    /**
     * Resolve an UID into a path
     *
     * @param string $uid
     *
     * @return string
     */
    public function resolveUid($uid)
    {
        $path = $this->findDocument($uid)->getPath();
        return $this->removePathPrefix($path);
    }

    /**
     * Find a document with his path
     *
     * @param string $path Path
     *
     * @return NuxeoObjects\Document
     */
    public function findByPath($path)
    {
        return $this->findDocument($this->normalizePath($path));
    }

    /**
     * Get a Nuxeo Document Object
     *
     * @param string $search Path or UID
     *
     * @return NuxeoObjects\Document
     */
    protected function findDocument($search)
    {
        $doc = $this->nuxeoclient
            ->automation('Document.Fetch')
            ->param('value', $search)
            ->execute(NuxeoObjects\Document::className);

        return $doc;
    }

    /**
     * Create a new file on nuxeo from a file pointer
     * Create a new dir on nuxeo if needed
     *
     * @param string   $path     Path
     * @param resource $resource Either a string or a stream.
     * @param Config   $config   Config
     *
     * @return array ['doc'=>$doc, 'blob'=>$blob]
     */
    protected function createFromStream($path, $resource, Config $config)
    {
        // Make sure the directory has been created first.
        $this->createDir(dirname($path), $config);

        $path = $this->applyPathPrefix($path);

        return $this->upload($path, basename($path), $resource);
    }

    /**
     * Create a new file on nuxeo from a file pointer
     *
     * @param string   $path     Nuxeo document path
     * @param string   $fileName Name of the file
     * @param resource $resource file pointer
     *
     * @return array    [NuxeoObjects\Document $doc, NuxeoObjects\Blob\Blob $blob]
     */
    protected function upload($path, $fileName, $resource)
    {
        $tmpFilepath = stream_get_meta_data($resource)['uri'];
        $mimeType    = mime_content_type($tmpFilepath);

        if (false === $mimeType) {
            //@TODO wromg mimetype
        }

        /** @var NuxeoObjects\Document $doc */
        $doc = $this->nuxeoclient->automation('Document.Create')
            ->input('doc:' . $this->normalizePath(Util::dirname($path))) # refer to the parent document
            ->params([
                'type'       => 'File',
                'name'       => $fileName,
                'properties' => 'dc:title=' . $fileName,
            ])
            ->execute(NuxeoObjects\Document::className);

        $blob = $this->uploadFile($doc->getPath(), $tmpFilepath, $mimeType);

        return [$doc, $blob];
    }

    /**
     * Upload a file content to a nuxeo document and name it correctly
     *
     * @param string $path        Nuxeo document path
     * @param string $tmpFilepath Path to the current file that must be uploaded
     * @param string $mimeType    Mime type of current file
     *
     * @return NuxeoObjects\Blob\Blob
     */
    protected function uploadFile($path, $tmpFilepath, $mimeType)
    {
        # Upload du contenu du fichier
        $this->nuxeoclient->automation('Blob.Attach')
            ->input(NuxeoObjects\Blob\Blob::fromFile($tmpFilepath, $mimeType))
            ->param('document', $this->normalizePath($path))
            ->execute(NuxeoObjects\Blob\Blob::className);

        # Renomer le blob (contenu) du fichier
        return $this->nuxeoclient->automation('Blob.SetFilename')
            ->input('doc:'.$this->normalizePath($path))
            ->param('name', basename($path))
            ->execute(NuxeoObjects\Blob\Blob::className);
    }

    /**
     * Create a tempoary file
     * The file is automatically removed when closed (for example, by calling fclose(),
     * or when there are no remaining references to the file handle returned by tmpfile()), or when the script ends.
     * Caution : If the script terminates unexpectedly, the temporary file may not be deleted.
     * @see tmpfile()
     *
     * @param string $content
     *
     * @return bool|resource
     */
    protected function createTmpFile($content)
    {
        $tmp = tmpfile();

        if (!fwrite($tmp, $content)) {
            return false;
        }

        return $tmp;
    }

    /**
     * Apply directory separator before and after the path
     *
     * @param string $path
     *
     * @return string
     */
    protected function normalizePath($path)
    {
        return '/'.trim($path, '/').'/';
    }

    /**
     * Builds the normalized output array from a Directory object.
     *
     * @param string $path
     * @param int    $timestamp
     * @param int    $size The filesize in bytes.
     * @param string $mimetype
     * @param string $uid
     *
     * @return array
     */
    protected function normalizeFileProperties($path, $timestamp, $size, $mimetype, $uid = '')
    {
        $path = $this->removePathPrefix($path);

        $properties = [
            'type'      => 'file',
            'path'      => $path,
            'dirname'   => Util::dirname($path),
            'timestamp' => $timestamp, // strtotime($doc->getLastModified()),
            'size'      => $size, //$blob->getFile()->getSize();
            'mimetype'  => $mimetype, //$blob->getMimeType();
            'uid'       => $uid,
        ];

        return $properties;
    }

    /**
     * Remove a path prefix.
     *
     * @param string $path
     *
     * @return string path without the prefix
     */
    public function removePathPrefix($path)
    {
        return preg_replace('#^('.$this->getPathPrefix().')#', '', $path);
    }
}