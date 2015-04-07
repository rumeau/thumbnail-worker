<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 005 05 04 2015
 * Time: 21:31
 */

namespace App\File;


interface FileProviderInterface
{
    /**
     * @param $file
     * @param array $options
     * @return mixed
     */
    public function getFile($file, $options);

    /**
     * @param array $files
     * @param mixed $options
     * @return mixed
     */
    public function putFiles($files, $options);
}
