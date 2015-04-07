<?php namespace App\Handlers\Commands;

use App\Commands\ThumbnailJobCommand;

use Illuminate\Log\Writer;

/**
 * Class ThumbnailJobCommandHandler
 * @package App\Handlers\Commands
 */
class ThumbnailJobCommandHandler
{
    /**
     * @var Writer
     */
    public $log;

    /**
     * @var string
     */
    protected $outputExtension;

    /**
     * @var int
     */
    protected $outputQuality;

    /**
     * Create the command handler.
     *
     * @param Writer $logger
     */
    public function __construct(Writer $logger)
    {
        $this->log = $logger;
        $this->outputExtension = config('work.default_output_ext', 'jpg');
        $this->outputQuality = config('work.default_output_quality', 80);
    }

    /**
     * Handle the command.
     *
     * Process all sizes in the data message
     *
     * @param  ThumbnailJobCommand $command
     * @return bool
     */
    public function handle(ThumbnailJobCommand $command)
    {
        $name  = $command->file->name;
        $meta  = $command->file->meta;
        $files = [];

        $this->log->debug(count($command->sizes) . ' Sizes to create');

        $counter = 1;
        $command->image->backup();
        $oldOutputExtension = $command->image->extension;
        foreach ($command->sizes as $size) {
            /**
             * Set the current extension for the image,
             * dont do anything if its the same
             */
            $newOutputExtension = isset($size['ext']) ? $size['ext'] : $this->outputExtension;
            if ($oldOutputExtension != $newOutputExtension) {
                $oldOutputExtension = $newOutputExtension;
                $command->image->encode($newOutputExtension);
            }

            $this->log->debug('Creating image thumbnail and added to queue: ' . $counter);
            // Set file suffix
            $suffix = isset($size['name']) ? $size['name'] : "{$size['width']}x{$size['height']}";
            $newName = $this->formatNewName($name, $suffix);

            // add callback functionality to retain maximal original image size
            $command->image->resize($size['width'], $size['height'], function ($constraint) {
                /**
                 * @var \Intervention\Image\Constraint $constraint
                 */
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Save new thumbnail on a temporary file
            $tmpFileInfo = pathinfo($command->file->tmpName);
            $newTmpFile = $tmpFileInfo['dirname'] . '/' . $suffix . '_' . $tmpFileInfo['filename'];
            $newTmpFile = $newTmpFile . '.' . $newOutputExtension;

            $quality = isset($size['quality']) ? $size['quality'] : $this->outputQuality;

            $command->image->save($newTmpFile, $quality);
            $mime = $command->image->mime();
            $command->image->reset();

            // Add thumbnail to upload queue
            $files[] = [
                'source'        => $newTmpFile,
                'destination'   => $newName,
                'content_type'  => $mime,
                'meta'          => $meta,
            ];
            $counter++;

            // Add children thumbnail to file
            $children = new \stdClass();
            $children->tmpName     = $newTmpFile;
            $children->name        = $suffix . '_' . $tmpFileInfo['filename'] . '.' . $newOutputExtension;
            $children->size        = $command->image->filesize();
            $children->meta        = [];
            $children->contenttype = $command->image->mime();
            $command->file->childrens[] = $children;
        }

        return $command->file;
    }

    /**
     * Format the new file name
     *
     * @param $name
     * @param $suffix
     * @return string
     */
    protected function formatNewName($name, $suffix)
    {
        $info = pathinfo($name);
        $newTarget = $info['dirname'] . '/' . $info['filename'] . '_' . $suffix;
        if (isset($info['extension'])) {
            $newTarget .= '.' . $info['extension'];
        }

        return $newTarget;
    }

}
