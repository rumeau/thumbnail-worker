<?php
/**
 * Created by PhpStorm.
 * User: Jean
 * Date: 004 04 04 2015
 * Time: 19:24
 */

return [
    /**
     * File provider: s3|local
     */
    'provider' => env('WORK_PROVIDER', 'local'),

    /**
     *
     */
    'profile' => true,

    /**
     * Temp path for downloaded files, it also store
     * the generated thumbnails
     */
    'tmp_path' => 'tmp/',

    /**
     *
     */
    'ssl' => true,

    /**
     *
     */
    'owner' => env('AWS_S3_OWNER') ? env('AWS_S3_OWNER') : '',

    /**
     * Output format of generated thumbnails, can be overrided
     * on specific files
     */
    'default_output_ext' => 'jpg',

    /**
     * Output image quality, override for specific images
     */
    'default_output_quality' => 100,
];
