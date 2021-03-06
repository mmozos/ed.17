<?php

namespace Handler;

use Symfony\Component\HttpFoundation;

/**
 * Class Uploader
 *
 * @see https://gist.github.com/beberlei/978346
 */
class Uploader
{
    /** @var HttpFoundation\File\File - not a persistent field! */
    private $file;

    /** @var array */
    private $fileDuplicatePath = [];

    /** @var array */
    private $filePersistencePath = [];

    /** @var string */
    protected static $uploadDirectory;

    /** @var string */
    protected static $zipSubDirectory = 'Zips';

    /** @param $dir */
    public function setUploadDirectory($dir)
    {
        static::$uploadDirectory = $dir;
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     */
    public static function getUploadDirectory()
    {
        //error_log('Upload dir: '.static::$uploadDirectory);
        if (empty(static::$uploadDirectory)) {
            static::$uploadDirectory = getenv('UPLOADS_DIR');
        }
        if (!is_dir(static::$uploadDirectory) && !mkdir(static::$uploadDirectory, 0755, true)) {
            throw new \RuntimeException('Trying to access to invalid upload directory path');
        }
        //error_log('Is upload dir permission ok? '.(is_dir(static::$uploadDirectory) ? 'yes' : 'no'));
        $zipDirectory = static::$uploadDirectory.DIRECTORY_SEPARATOR.static::$zipSubDirectory.date('-Y-m-d');
        //error_log('Upload subdir: '.$zipDirectory);
        if (!is_dir($zipDirectory) && !mkdir($zipDirectory, 0755, true)) {
            throw new \RuntimeException('Trying to access to invalid upload subdirectory path');
        }
        //error_log('Is upload subdir permission ok? '.(is_dir($zipDirectory) ? 'yes' : 'no'));

        return $zipDirectory;
    }

    /**
     * @param HttpFoundation\File\File $file
     */
    public function setFile(HttpFoundation\File\File $file)
    {
        $this->file = $file;
    }

    /**
     * @return array
     */
    public function getFileDuplicatePath()
    {
        return $this->fileDuplicatePath;
    }

    /**
     * @return array
     */
    public function getFilePersistencePath()
    {
        return $this->filePersistencePath;
    }

    /**
     * @param string $clientIp
     * @param string $user
     * @param string $time
     * @param string $token
     *
     * @return bool
     *
     * @throws HttpFoundation\File\Exception\FileException|\RuntimeException
     */
    public function processFile($clientIp, $user, $time, $token)
    {
        if (!($this->file instanceof HttpFoundation\File\UploadedFile)) {
            throw new HttpFoundation\File\Exception\FileException($this->file);
        }
        $targetDir = static::getUploadDirectory();
        if (!is_dir($targetDir) && !mkdir($targetDir, umask(), true)) {
            throw new \RuntimeException('Could not create target directory to move temporary file into.');
        }
        $result = $this->doMove($this->file, $targetDir, $clientIp, $user, $time, $token);

        return $result;
    }

    /**
     * @return bool
     */
    private function isNewFile()
    {
        $mimeType = $this->file->getMimeType();
        if ('text/plain' === $mimeType) {
            return true;
        } elseif ('application/zip' === $mimeType) {
            $count = 0;
            $md5 = md5_file($this->file->getPathname());
            foreach (static::getTargetDirs(static::getUploadDirectory()) as $dir) {
                $files = static::getTargetFiles($dir);
                $count += count($files);
                foreach ($files as $file) {
                    if ($md5 === md5_file($file)) {
                        $this->fileDuplicatePath[] = [md5($file) => realpath($file)];
                    } else {
                        --$count;
                    }
                }
            }

            return 0 === $count ? true : false;
        }

        return false;
    }

    /**
     * @param HttpFoundation\File\UploadedFile $uploadedFile
     * @param string                           $targetDir
     * @param string                           $clientIp
     * @param string                           $user
     * @param string                           $time
     * @param string                           $token
     *
     * @return string|false
     */
    private function doMove(HttpFoundation\File\UploadedFile $uploadedFile, $targetDir, $clientIp, $user, $time, $token)
    {
        $targetDir .= DIRECTORY_SEPARATOR.date('Y-m-d+H-i-s', $time).'+'.$clientIp.'+'.$user.'+'.$token;
        $originalName = $uploadedFile->getClientOriginalName();
        $file = $targetDir.DIRECTORY_SEPARATOR.$originalName;
        if ($this->isNewFile() && !in_array(md5($file), array_keys($this->fileDuplicatePath))) {
            if (is_file($file)) {
                return uniqid();
            }
            $uploadedFile->move($targetDir, $originalName);
            $this->filePersistencePath[] = [md5($file) => $file];

            return 1;
        }

        return 0;
    }

    /**
     * @return array
     */
    public function doPurge()
    {
        /** @var array $result */
        $purges = [];
        foreach (static::getTargetDirs(static::getUploadDirectory()) as $dir) {
            $files = static::getTargetFiles($dir);
            if (1 === count($files) && ($file = realpath($files[0])) && 'text/plain' === mime_content_type($file) && unlink($file) && $folder = dirname($file)) {
                /*$exists = false;
                foreach ($this->getFilePersistencePath() as $key => $fresh) {
                    if (strpos(array_pop($fresh), $folder)) {
                        unset($this->filePersistencePath[$key]);
                        $exists = true;
                    }
                }
                if (!$exists) {
                    foreach ($this->getFileDuplicatePath() as $key => $dupe) {
                        if (strpos(array_pop($fresh), $folder)) {
                            unset($this->fileDuplicatePath[$key]);
                            $exists = true;
                        }
                    }
                    if (!$exists) {
                        array_push($purges, $file);
                    }
                }*/
            }
        }

        //return ['fresh' => $this->getFilePersistencePath(), 'dupe' => $this->getFileDuplicatePath(), 'garb' => $purges];
        return [];
    }

    /**
     * @param $dir
     *
     * @return array
     */
    private function getTargetDirs($dir)
    {
        return array_merge([$dir], glob($dir.'/*', GLOB_ONLYDIR));
    }

    /**
     * @param $dir
     *
     * @return array|null
     */
    private static function getTargetFiles($dir)
    {
        return array_filter(glob($dir.'/*'), 'is_file');
    }

    /**
     * @return string
     */
    public static function publishUploadDirectory()
    {
        $filename = static::zipUploadDirectory(static::getUploadDirectory());

        return '<a target="_blank" href="'.$filename.'">'.$filename.'</a>';
    }

    /**
     * @param string $srcDir
     * @param string $targetDir
     *
     * @return string
     */
    public static function zipUploadDirectory($srcDir, $targetDir = PUBLIC_DIR)
    {
        // Get real path for our folder
        $rootPath = realpath($srcDir);
        $filename = date('Y-m-d+H-i-s', time()).'_'.(uniqid()).'.zip';

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($targetDir.DIRECTORY_SEPARATOR.$filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var \SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $total = 0;
        foreach ($files as $name => $file) {
            $path = realpath($file);
            if (empty($path)) {
                continue;
            }
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                if ($zip->addFile($filePath, $relativePath)) {
                    ++$total;
                }
            }
        }
        // Zip archive will be created only after closing object
        if ($total) {
            $set = $zip->setPassword('r11t17');
        }
        $zip->close();

        return $total ? $filename : '';
    }
}
