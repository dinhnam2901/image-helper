<?php

class ImageHelper {

    const OPTION_TYPE = 'type';
    const OPTION_CROP = 'crop';
    const OPTION_WIDTH = 'width';
    const OPTION_HEIGHT = 'height';
    const OPTION_RATIO = 'ratio';
    const OPTION_QUALITY = 'quality';
    const TYPE_AUTO = 'auto';
    const TYPE_GIF = 'gif';
    const TYPE_PNG = 'png';
    const TYPE_JPG = 'jpg';
    const TYPE_WBMP = 'wbmp';
    const CROP_AUTO = 'auto';
    const CROP_AUTOFILL = 'autofill';
    const CROP_CUT = 'cut';
    const WIDTH_AUTO = 'auto';
    const HEIGHT_AUTO = 'auto';
    const RATIO_AUTO = 'auto';
    const QUALITY_DEFAULT = 100;

    public function __construct() {

    }

    /**
     * Get information of image
     * @param string $path input image file path
     * @return array Information of image, include:<br />
     * <pre>
     *  - image   : GD image instance
     *  - type    : File type extension, ex: jpg, png...
     *  - width   : Width of image
     *  - height  : Height of image
     *  - ratio   : Width/height ratio
     * </pre>
     */
    public function getImageInfo(string $path = ''): array {
        $out = array(
            'image'  => false, // GdImage
            'type'   => false,
            'width'  => 0,
            'height' => 0,
            'ratio'  => 0
        );
        if ($path == '') {
            return $out;
        }

        if (!$this->readImage($path, $out['image'], $out['type'])) {
            return $out;
        }

        $out['width'] = @imagesx($out['image']);
        if (!$out['width']) {
            $out['width'] = 0;
        }
        $out['height'] = @imagesy($out['image']);
        if (!$out['height']) {
            $out['height'] = 0;
        }
        if ($out['height'] != 0) {
            $out['ratio'] = $out['width'] / $out['height'];
        }
        return $out;
    }

    /**
     * Resize the image with various options
     *
     * @param string $path input image file path
     * @param string|boolean $save output image file path without file extension. Or, false to ignore save image output into file
     * @param type $option The option to resize, include (not required): <br />
     * <pre>
     *  - type: file type extension of resized image. Accept types in const with: TYPE_AUTO, TYPE_GIF, TYPE_PNG, TYPE_JPG, TYPE_WBMP
     *  - crop: crop mode when resize. Accept mode in const with: CROP_AUTO, CROP_AUTOFILL, CROP_CUT
     *  - width: with of resized image. Accept number, or WIDTH_AUTO.
     *  - height: height of resized image. Accept number, or HEIGHT_AUTO.
     *  - ratio: ratio(width/height) of resized image. Accept number, or RATIO_AUTO
     *  - quality: quality of resized image. Accept range from 0 to 100, or QUALITY_DEFAULT
     * </pre>
     * @return mixed|boolean image's information after resize, including: data, type, width, height. If unable to resize, return false
     */
    public function resize(string $path, $save = false, $option = array()) {
        // Read the information of input image based on the input path
        $imageIn = $this->getImageInfo($path);
        if ($imageIn['image'] == false) {
            return false;
        }

        // Fill missing option with automatic value
        $this->refineOption($option);

        // If ratio of resized image is automatic, it will be as ratio of input image
        if ($option['ratio'] == 'auto') {
            $option['ratio'] = $imageIn['ratio'];
        }

        // Get the blank image output based on the image input and option
        $imageOut = $this->getImageOut($imageIn, $option);

        // Calc the config value to perform copy image from input image to output image
        $copyConfig = $this->getCopyConfig($imageIn, $imageOut, $option[self::OPTION_CROP]);

        // Copy image from input image to output image
        imagecopyresampled(
            $imageOut['data'],
            $imageIn['image'],
            $copyConfig['to_x'],
            $copyConfig['to_y'],
            $copyConfig['from_x'],
            $copyConfig['from_y'],
            $copyConfig['to_width'],
            $copyConfig['to_height'],
            $copyConfig['from_width'],
            $copyConfig['from_height']);

        // If output image file path was specific, save resized image to file.
        if ($save != false) {
            $this->saveImage($imageOut, $save, $option[self::OPTION_QUALITY]);
        }

        // Return image output that resized
        return $imageOut;
    }

    /* For internal use only ************************************************* */

    private function readImage(string $path, &$image, string &$type): bool {
        $image = @imagecreatefrompng($path);
        if ($image != null && $image) {
            $type = self::TYPE_PNG;
        } else {
            $image = @imagecreatefromgif($path);
            if ($image != null && $image) {
                $type = self::TYPE_GIF;
            } else {
                $image = @imagecreatefromwbmp($path);
                if ($image != null && $image) {
                    $type = self::TYPE_WBMP;
                } else {
                    $image = @imagecreatefromjpeg($path);
                    $exif = exif_read_data($path);
                    if (!empty($exif['Orientation'])) {
                        $this->correctOrientation($image, $exif['Orientation']);
                    }
                    if ($image != null && $image) {
                        $type = self::TYPE_JPG;
                    } else {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function correctOrientation(&$image, $orientaion) {
        switch ($orientaion) {
            case 2: // horizontal flip
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;

            case 3: // 180 rotate left
                $image = imagerotate($image, 180, 0);
                break;

            case 4: // vertical flip
                imageflip($image, IMG_FLIP_VERTICAL);
                break;

            case 5: // vertical flip + 90 rotate right
                imageflip($image, IMG_FLIP_VERTICAL);
                $image = imagerotate($image, -90, 0);
                break;

            case 6: // 90 rotate right
                $image = imagerotate($image, -90, 0);
                break;

            case 7: // horizontal flip + 90 rotate right
                imageflip($image, IMG_FLIP_HORIZONTAL);
                $image = imagerotate($image, -90, 0);
                break;

            case 8: // 90 rotate left
                $image = imagerotate($image, 90, 0);
                break;

            default:
                break;
        }
    }

    private function getImageOut($imageIn, $option) {
        $imageOut = array(
            'data'   => $imageIn['image'],
            'type'   => $imageIn['type'],
            'width'  => $imageIn['width'],
            'height' => $imageIn['height']
        );

        if ($option[self::OPTION_TYPE] != self::TYPE_AUTO) {
            $imageOut['type'] = $option[self::OPTION_TYPE];
        }

        if ($option[self::OPTION_WIDTH] != self::WIDTH_AUTO) {
            $imageOut['width'] = $option[self::OPTION_WIDTH];
            if ($option[self::OPTION_HEIGHT] != self::HEIGHT_AUTO) {
                $imageOut['height'] = $option[self::OPTION_HEIGHT];
            } else {
                $imageOut['height'] = $imageOut['width'] / $option[self::OPTION_RATIO];
            }
        } else {
            if ($option[self::OPTION_HEIGHT] != self::HEIGHT_AUTO) {
                $imageOut['height'] = $option[self::OPTION_HEIGHT];
                $imageOut['width'] = $option[self::OPTION_RATIO] * $imageOut['height'];
            } else {
                $imageOut['width'] = $imageIn['width'];
                $imageOut['height'] = $imageOut['width'] / $option[self::OPTION_RATIO];
            }
        }
        $imageOut['data'] = imagecreatetruecolor($imageOut['width'], $imageOut['height']);

        $imageOut['ratio'] = $imageOut['width'] / $imageOut['height'];

        return $imageOut;
    }

    private function refineOption(&$option) {
        if (empty($option)) {
            $option = $this->getDefaultOption();
        }
        if (!isset($option[self::OPTION_TYPE])) {
            $option[self::OPTION_TYPE] = self::TYPE_AUTO;
        }
        if (!isset($option[self::OPTION_CROP])) {
            $option[self::OPTION_CROP] = self::CROP_AUTO;
        }
        if (!isset($option[self::OPTION_WIDTH])) {
            $option[self::OPTION_WIDTH] = self::WIDTH_AUTO;
        }
        if (!isset($option[self::OPTION_HEIGHT])) {
            $option[self::OPTION_HEIGHT] = self::HEIGHT_AUTO;
        }
        if (!isset($option[self::OPTION_RATIO])) {
            $option[self::OPTION_RATIO] = self::RATIO_AUTO;
        }
        if (!isset($option[self::OPTION_QUALITY])) {
            $option[self::OPTION_QUALITY] = self::QUALITY_DEFAULT;
        }
    }

    private function getDefaultOption() {
        return [
            self::OPTION_TYPE    => self::TYPE_AUTO,
            self::OPTION_CROP    => self::CROP_AUTO,
            self::OPTION_WIDTH   => self::WIDTH_AUTO,
            self::OPTION_HEIGHT  => self::HEIGHT_AUTO,
            self::OPTION_RATIO   => self::RATIO_AUTO,
            self::OPTION_QUALITY => self::QUALITY_DEFAULT
        ];
    }

    private function getCopyConfig($imageIn, $imageOut, $cropType) {
        if ($cropType == self::CROP_AUTOFILL) {
            return $this->getCopyConfigByAutoFill($imageIn, $imageOut);
        }

        if ($cropType == self::CROP_CUT) {
            return $this->getCopyConfigByCut($imageIn, $imageOut);
        }

        return $this->getCopyConfigByAuto($imageIn, $imageOut);
    }

    private function getCopyConfigByCut($imageIn, $imageOut) {
        $copy = [
            'from_x'      => 0,
            'from_y'      => 0,
            'from_width'  => $imageIn['width'],
            'from_height' => $imageIn['height'],
            'to_x'        => 0,
            'to_y'        => 0,
            'to_width'    => $imageOut['width'],
            'to_height'   => $imageOut['height']
        ];
        $copy['from_height'] = $copy['from_width'] / $imageOut['ratio'];
        if ($copy['from_height'] > $imageIn['height']) {
            $copy['from_height'] = $imageIn['height'];
            $copy['from_width'] = $copy['from_height'] * $imageOut['ratio'];
        }
        $copy['from_x'] = ($imageIn['width'] - $copy['from_width']) / 2;
        $copy['from_y'] = ($imageIn['height'] - $copy['from_height']) / 2;

        return $copy;
    }

    private function getCopyConfigByAutoFill($imageIn, $imageOut) {
        $copy = array(
            'from_x'      => 0,
            'from_y'      => 0,
            'from_width'  => $imageIn['width'],
            'from_height' => $imageIn['height'],
            'to_x'        => 0,
            'to_y'        => 0,
            'to_width'    => $imageOut['width'],
            'to_height'   => $imageOut['height']
        );
        $copy['to_height'] = $copy['to_width'] / $imageIn['ratio'];

        if ($copy['to_height'] > $imageOut['height']) {
            $copy['to_height'] = $imageOut['height'];
            $copy['to_width'] = $copy['to_height'] * $imageIn['ratio'];
        }
        $copy['to_x'] = ($imageOut['width'] - $copy['to_width']) / 2;
        $copy['to_y'] = ($imageOut['height'] - $copy['to_height']) / 2;

        return $copy;
    }

    private function getCopyConfigByAuto($imageIn, $imageOut) {
        return [
            'from_x'      => 0,
            'from_y'      => 0,
            'from_width'  => $imageIn['width'],
            'from_height' => $imageIn['height'],
            'to_x'        => 0,
            'to_y'        => 0,
            'to_width'    => $imageOut['width'],
            'to_height'   => $imageOut['height']
        ];
    }

    private function saveImage($imageData = false, $savepath = false, $quality = 100) {
        if ($imageData == false || $savepath == false) {
            return false;
        }
        if ($imageData['type'] == 'png') {
            imagepng($imageData['data'], $savepath . "." . $imageData['type'], 0);
            return true;
        }
        if ($imageData['type'] == 'gif') {
            imagegif($imageData['data'], $savepath . "." . $imageData['type']);
            return true;
        }
        if ($imageData['type'] == 'jpg') {
            imagejpeg($imageData['data'], $savepath . "." . $imageData['type'], $quality);
            return true;
        }
        if ($imageData['type'] == 'wbmp') {
            imagewbmp($imageData['data'], $savepath . "." . $imageData['type']);
            return true;
        }
        return false;
    }

}