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

### Returns

```php
$normalisedArray = [
 'type'      => 'file', // string
 'path'      => '/path/to/doc/', // string
 'dirname'   => 'dir', // string
 'timestamp' => 123456, // int
 'size'      => 500, // int
 'mimetype'  => 'application/pdf', // string
 'uid'       => 'f4e22103-2540-46e8-8ed6-2a78586bd2e3', // string - only for writes methods
];

// For read/write/update methods the index 'content' is added
$normalisedArray['content'] = $content; // string

// For readStream/writeStream/updateStream methods the index 'stream' is added
$normalisedArray['stream'] = $resource; // resource
```

### Basic functions

```php
// Read
$normalisedArray = $filesystem->read($path);
$normalisedArray = $filesystem->readStream($path);

// Write
$normalisedArray = $filesystem->write($path, file_get_contents($pathToNewContent));

// Write Stream
$resource = fopen($pathToNewContent, 'r');
$normalisedArray = $filesystem->writeStream($path, $resource);

// Create Dir
$array = $filesystem->createDir($dirname, $config);
// $array = ['path' => '/path/to/dir/', 'type' => 'dir'];

// Update
$normalisedArray = $filesystem->update($path, file_get_contents($pathToNewContent));

// Update Stream
$resource = fopen($pathToNewContent, 'r');
$normalisedArray = $filesystem->updateStream($path, $resource);

// Rename
$bool = $filesystem->rename($path, $newName);

// Copy
$bool = $filesystem->copy($fromPath, $toPath);

// Delete
$bool = $filesystem->delete($path);
$bool = $filesystem->deleteDir($dirPath);
```

## Plugins

### UidResolverPlugin
Flysystem works using paths but one best pratice with Nuxeo is to use uids

```php
$uid = 'f4e22103-2540-46e8-8ed6-2a78586bd2e3';
$path = $filesystem->resolveUid($uid); // $path = '/path/to/doc/';
```

### MimetypeConverterPlugin
```php
$normalisedArray = $filesystem->convert($path, 'text/plain');
```

### ConcatenatorPlugin
```php
$normalisedArray = $filesystem->concatenate([$path, $path1, $path2]);
```

For this case nuxeo also implements a faster way using uids

```php
$normalisedArray = $filesystem->concatenateByUids([$uid, $uid1, $uid2]);
```
