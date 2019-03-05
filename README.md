# flysystem-nuxeo
Flysystem adapter for Nuxeo

## Todos

+ Tests Units
+ PHP 7 version

## Install
```
composer require ak1r0/flysystem-nuxeo
```

## Usage

### Init

```php
$config = array(
    'url' => 'https://host/nuxeo',
    'username' => 'root',
    'password' => 'root',
    'baseRepository' => '/default-domain/workspaces/myWorkspace/'
)

$client = new \Nuxeo\Client\Api\NuxeoClient($config['url'], $config['username'], $config['password']);

$nuxeoAdapter = new \Ak1r0\Flysystem\Adapter\Nuxeo($client);
$nuxeoAdapter->setPathPrefix($config['baseRepository']);

$filesystem   = new \League\Flysystem\Filesystem($nuxeoAdapter);
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\UidResolverPlugin($nuxeoAdapter));
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\MimetypeConverterPlugin($nuxeoAdapter));
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\ConcatenatorPlugin($nuxeoAdapter));
```

### Basic functions

```php
// Read
$filesystem->read($path): string; // The file content
$filesystem->readStream($path): string; // The file content

// Write
$filesystem->write(string $path, string $content): bool;

// Write Stream
$handle = fopen($pathToNewContent, 'r');
$filesystem->writeStream(string $path, resource $handle): bool;

// Create Dir
$filesystem->createDir($dirname, $config): array; // return ['path' => '/path/to/dir/', 'type' => 'dir'];

// Update
$filesystem->update(string $path, string $content): bool;

// Update Stream
$handle = fopen($pathToNewContent, 'r');
$filesystem->updateStream(string $path, resource $handle): bool;

// Rename
$filesystem->rename(string $path, string $newName): bool;

// Copy
$filesystem->copy(string $fromPath, string $toPath): bool;

// Delete
$filesystem->delete(string $path): bool;
$filesystem->deleteDir(string $dirPath): bool;
```

### Get the UID after a write operation

```php
$filesystem->getMetadata($path): array;
/*
return [
    'type'      => 'file', // string
    'path'      => '/path/to/doc/', // string
    'dirname'   => 'dir', // string
    'timestamp' => 123456, // int
    'size'      => 500, // int
    'mimetype'  => 'application/pdf', // string
    'uid'       => 'f4e22103-2540-46e8-8ed6-2a78586bd2e3', // string - only for writes methods
];
*/
```

## Plugins

### UidResolverPlugin
Flysystem works using paths but one best pratice with Nuxeo is to use uids

```php
$uid = 'f4e22103-2540-46e8-8ed6-2a78586bd2e3';
$filesystem->resolveUid(string $uid): string; // return '/path/to/doc/';
```

### MimetypeConverterPlugin
```php
$filesystem->convert(string $path, string $mimeType): array;
/*
return [
   'type'      => 'file', // string
   'path'      => '/path/to/doc/', // string
   'dirname'   => 'dir', // string
   'timestamp' => 123456, // int
   'size'      => 500, // int
   'mimetype'  => 'application/pdf', // string
   'contents'  => '...' // string the file content
];
*/
```

### ConcatenatorPlugin
```php
$filesystem->concatenate(array [$path, $path1, $path2]): array;
```

For this case nuxeo also implements a faster way using uids

```php
$normalisedArray = $filesystem->concatenate(array [$uid, $uid1, $uid2], bool true): array;
```

Boths cases return this array
```php
[
   'type'      => 'file', // string
   'path'      => '/path/to/doc/', // string
   'dirname'   => 'dir', // string
   'timestamp' => 123456, // int
   'size'      => 500, // int
   'mimetype'  => 'application/pdf', // string
   'contents'  => '...' // string the file content
];
```