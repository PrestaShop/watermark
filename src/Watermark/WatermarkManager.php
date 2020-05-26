<?php
namespace PrestaShop\Module\Watermark\Watermark;

use Tools;
use ImageManager;

class WatermarkManager
{

    /** @var string $yAlign */
    private $yAlign;
    /** @var string $xAlign */
    private $xAlign;
    /** @var int $transparency */
    private $transparency;


    public function __construct($yAlign, $xAlign, $transparency)
    {
        $this->yAlign = $yAlign;
        $this->xAlign = $xAlign;
        $this->transparency = $transparency;
    }

    /**
     * @param string $imagepath
     * @param string $watermarkpath
     * @param string $outputpath
     *
     * @return bool
     */
    public function watermarkByImage($imagepath, $watermarkpath, $outputpath)
    {

        $Xoffset = $Yoffset = $xpos = $ypos = 0;

        // Force image type by extension
        $watermarkExtension = Tools::strtolower(pathinfo($watermarkpath, PATHINFO_EXTENSION));
        if ($watermarkExtension === 'jpeg') {
            $watermarkExtension = 'jpg';
        }

        $type = getimagesize($imagepath)[2];

        $image = ImageManager::create($type, $imagepath);
        if (!$image) {
            return false;
        }

        $imagew = ImageManager::create(static::convertExtensionToImageType($watermarkExtension), $watermarkpath);
        if (!$imagew) {
            $this->context->controller->errors[] = $this->trans('Watermark image format is unsupported, allowed file types are: .gif, .jpg, .png', [], 'Modules.Watermark.Admin');

            return false;
        }

        list($watermarkWidth, $watermarkHeight) = getimagesize($watermarkpath);
        list($imageWidth, $imageHeight) = getimagesize($imagepath);
        if ($this->xAlign === 'middle') {
            $xpos = $imageWidth / 2 - $watermarkWidth / 2 + $Xoffset;
        }
        if ($this->xAlign === 'left') {
            $xpos = 0 + $Xoffset;
        }
        if ($this->xAlign === 'right') {
            $xpos = $imageWidth - $watermarkWidth - $Xoffset;
        }
        if ($this->yAlign === 'middle') {
            $ypos = $imageHeight / 2 - $watermarkHeight / 2 + $Yoffset;
        }
        if ($this->yAlign === 'top') {
            $ypos = 0 + $Yoffset;
        }
        if ($this->yAlign === 'bottom') {
            $ypos = $imageHeight - $watermarkHeight - $Yoffset;
        }
        if (!$this->imagecopymerge_alpha($image, $imagew, $xpos, $ypos, 0, 0, $watermarkWidth, $watermarkHeight, $this->transparency, $watermarkExtension)) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return ImageManager::write($type, $image, $outputpath);
    }

    /**
     * Convert file extension to image type
     *
     * @param string $extension
     *
     * @return int
     *
     * @since 2.0.0
     */
    public static function convertExtensionToImageType($extension)
    {
        $imageTypes = [
            'jpg' => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'gif' => IMAGETYPE_GIF,
            'png' => IMAGETYPE_PNG,
        ];
        if (!array_key_exists(Tools::strtolower($extension), $imageTypes)) {
            return IMAGETYPE_GIF;
        }

        return $imageTypes[Tools::strtolower($extension)];
    }

    /**
     * Merges images and watermarks.
     *
     * @param $dst_im
     * @param $src_im
     * @param $dst_x
     * @param $dst_y
     * @param $src_x
     * @param $src_y
     * @param $src_w
     * @param $src_h
     * @param $pct
     * @param $watermarkExtension
     *
     * @since 2.0.0
     *
     * @return bool
     */
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct, $watermarkExtension)
    {
        if ($watermarkExtension === 'png') { // Needed for PNG-24 with transparency
            $cut = imagecreatetruecolor($src_w, $src_h);
            imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
            imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        } else {
            $cut = $src_im;
        }

        return imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct);
    }
}
