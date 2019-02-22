# flysystem-nuxeo
Flysystem adapter for Nuxeo

# Usage

## Init

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

$filesystem   = new \Ak1r0\Flysystem\Filesystem($nuxeoAdapter);
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\UidResolverPlugin($nuxeoAdapter));
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\MimetypeConverterPlugin($nuxeoAdapter));
$filesystem->addPlugin(new \Ak1r0\Flysystem\Plugin\ConcatenatorPlugin($nuxeoAdapter));
```

## Returns

```php
$normalisedArray = [
 'type'      => 'file', // string
 'path'      => /path/to/doc/, // string
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

## Basic functions

### Read
```php
$normalisedArray = $filesystem->read($path);
$normalisedArray = $filesystem->readStream($path);
```

### Write
```php
$normalisedArray = $filesystem->write($path, file_get_contents($pathToNewContent));
```

```php
$resource = fopen($pathToNewContent, 'r');
$normalisedArray = $filesystem->writeStream($path, $resource);
```

### Create Dir
```php
$array = $filesystem->createDir($dirname, $config);
// $array = ['path' => '/path/to/dir/', 'type' => 'dir'];
```

### Update
```php
$normalisedArray = $filesystem->update($path, file_get_contents($pathToNewContent));
```
```php
$resource = fopen($pathToNewContent, 'r');
$normalisedArray = $filesystem->updateStream($path, $resource);
```

### Rename
```php
$bool = $filesystem->rename($path, $newName);
```

### Copy
```php
$bool = $filesystem->copy($fromPath, $toPath);
```

### Delete
```php
$bool = $filesystem->delete($path);
$bool = $filesystem->deleteDir($dirPath);
```

## Plugins

### Uid to path
Flysystem works using paths but one best pratice with Nuxeo is to use uids

```php
$uid = 'f4e22103-2540-46e8-8ed6-2a78586bd2e3';
$path = $filesystem->resolveUid($uid); // $path = '/path/to/doc/';
```

### Convert mimetype
```php
$normalisedArray = $filesystem->convert($path, 'text/plain');
```

### Concatenate
```php
$normalisedArray = $filesystem->concatenate([$path, $path1, $path2]);
```

For this case nuxeo also implements a faster way using uids

```php
$normalisedArray = $filesystem->concatenateByUids([$uid, $uid1, $uid2]);
```