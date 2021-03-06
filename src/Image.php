<?php
/**
 * Image
 * Basic functions for image manipulation with a simple PHP install (no Imagemagik required).
 * 
 *
 * TODO:
 *      limit : show an image of WxH in a potentially smaller space (ugh. Use CSS)
 *
 *      rotateCW : rotate an image clockwise in 90-degree increments.
 *      rotateCCW : rotate an imagae counter-clockwise in 90-degree increments.
 *          http://www.php.net//manual/en/function.imagerotate.php
 *      
 *      flipX : flip an image horizontally so left becomes right, right becomes left
 *      flipY : flip an image vertically so top becomes bottom, bottom becomes top
 *          http://www.php.net//manual/en/function.imageflip.php
 *
 *      watermark : adds a watermark image to an image
 *      textmark : adds text to an image as a watermark
 *      stream: stream an image from file (e.g. from a location not web-accessible)
 */
namespace Craftsmancoding;
class Image {
    
    /**
     *
     * @param string $dst image path
     */
    private static function _prep_destination($dst) {
        // Careful!
        // is it an image?
        if (!in_array(strtolower(substr($dst, -4)), array('.jpg','jpeg','.gif','.png'))) {
            throw new \Exception('Destination file must be an image: '.$dst);
        }        
        if(file_exists($dst)) {
            if (!unlink($dst)) {
                throw new \Exception('Unable to overwrite destination file '.$dst);
            }
        }
        if (!file_exists(dirname($dst))) {
            if (!mkdir(dirname($dst),0777,true)) {
                throw new \Exception('Failed to create directory '.dirname($dst));
            }            
        }    
    }
    
    /**
     * Read image at $src and return image resource
     *
     * @param string $src full path to source image
     */
    private static function _get_resource($src) {
        $ext = strtolower(strrchr($src, '.'));
        switch ($ext) {
            case '.jpg':
            case '.jpeg':
                $src_img = @imagecreatefromjpeg($src);
                break;
            case '.gif':
                $src_img = @imagecreatefromgif($src);
                break;
            case '.png':

                $src_img = @imagecreatefrompng($src);
                break;
        }
        if (!$src_img) {
            throw new \Exception('Failed to create image');
        }
        
        return $src_img;
    }

    /** 
     * Create a new image to file
     *
     * @param $src_image image resource
     * @param $dst destination file path
     * @param integer width (x) of src image
     * @param integer height (y) of src image
     * @param integer width (x) of new image
     * @param integer height (y) of new image)
     *
     */
    private static function _create($src_img, $dst, $ox,$oy,$nx,$ny) {
    
        $dst_img = imagecreatetruecolor($nx, $ny);
        
        $ext = strtolower(strrchr($dst, '.'));

        // Special behavior for PNGs
        if ($ext=='.png') {

                // integer representation of the color black (rgb: 0,0,0)

             $background = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);

                // removing the black from the placeholder
                imagecolortransparent($dst_img, $background);

                // turning off alpha blending (to ensure alpha channel information 
                // is preserved, rather than removed (blending with the rest of the 
                // image in the form of black))
                imagealphablending($dst_img, false);

                // turning on alpha channel information saving (to ensure the full range 
                // of transparency is preserved)
                imagesavealpha($dst_img, true);
        } 

        
        imagecopyresampled($dst_img, $src_img, 0,0,0,0,$nx,$ny,$ox,$oy);
        

        switch ($ext) {
            case '.jpg':
            case '.jpeg':
                if(!imagejpeg($dst_img, $dst,100)) {
                    throw new \Exception('Failed to create thumbnail image at '.$dst);
                }
                break;
            case '.gif':
                if(!imagegif($dst_img, $dst)) {
                    throw new \Exception('Failed to create thumbnail image at '.$dst);
                }
                break;
            case '.png':
                if(!imagepng($dst_img, $dst)) {
                    throw new \Exception('Failed to create thumbnail image at '.$dst);
                }
                break;
        }
        
        return $dst;
    
    }
    
    /**
     * Crops an image specified at path $src to path $dst. If $src = $target,
     * the image will be destructively edited in place.
     
     * $ratio is the ratio of image's ACTUAL pixel width to the pixel width that was DISPLAYED.
     * E.g. if the image was ACTUALLY 2000 pixels wide, but it had to be displayed at 1000 pixels
     * wide in order to fit onto the screen, then pass a ratio of 2.
     * This argument can be useful when reading parameters from various Javascript cropping libraries
     * (e.g. jCrop): just pass in the ratio between actual/displayed and the crop will adjust.
     *
     * @param string $src path
     * @param string $dst path
     * @param integer $x x-coordinate of crop area start point
     * @param integer $y y-coordinate of crop area start point
     * @param integer $w width of crop selection
     * @param integer $h height of crop selection
     * @param numeric $ratio multiplier of actual-width/displayed-width (default: 1)
     * @return string $dst
     */
    public static function crop($src,$dst,$x,$y,$w,$h,$ratio=1) { 
        if (!file_exists($src)) {
            throw new \Exception('File not found '.$src);
        }
        
        $src_img = '';
        $ext = strtolower(strrchr($dst, '.'));
        $image_func = '';
        $quality = null; // different vals for different funcs
        switch ($ext) {
            case '.gif':
                $src_img = @imagecreatefromgif($src);
                $image_func = 'imagegif';
                break;
            case '.jpg':
            case '.jpeg':
                $src_img = @imagecreatefromjpeg($src);
                $image_func = 'imagejpeg';
                $quality = 100;
                break;
            case '.png':
                $src_img = @imagecreatefrompng($src);
                $image_func = 'imagepng';
                $quality = 0;
                break;
        }
        
        if (!$src_img) {
            throw new \Exception('Could not read image '.$src); 
        }

        if (!file_exists(dirname($dst))) {
            if (!@mkdir(dirname($dst),0777,true)) {
                throw new \Exception('Failed to create directory '.dirname($dst));
            }            
        }

        // With jCrop, the user will select the full width of the *displayed* image, 
        // which is not necessarily equal to the dimensions of the original image, so we 
        // adjust values via the $ratio var.        
        // Remember: order of ops for type-casting. (int) filters ONLY the variable to its right!!
        $src_x = (int) ($ratio * $x);
        $src_y = (int) ($ratio * $y);
        $src_w = (int) ($ratio * $w);
        $src_h = (int) ($ratio * $h);

        $dst_img = imagecreatetruecolor($src_w, $src_h);

        if ($ext=='.png') {


                // integer representation of the color black (rgb: 0,0,0)
                $background = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);

                // removing the black from the placeholder
                imagecolortransparent($dst_img, $background);

                // turning off alpha blending (to ensure alpha channel information 
                // is preserved, rather than removed (blending with the rest of the 
                // image in the form of black))
                imagealphablending($dst_img, false);

                // turning on alpha channel information saving (to ensure the full range 
                // of transparency is preserved)
                imagesavealpha($dst_img, true);
        }
        
        // The un-aptly named: imagecopy: Copy part of src image to dst img defined by points on the src
        if (!imagecopy($dst_img, $src_img, 0, 0, $src_x, $src_y, $src_w, $src_h)) {
            imagedestroy($src_img);
            imagedestroy($dst_img);
            print "$src_x, $src_y, $src_w, $src_h"; exit;
            throw new \Exception('Could not crop image');
        }

        // Write the cropped image resource to the filesystem
        if (!$image_func($dst_img,$dst,$quality)) {
            imagedestroy($src_img);
            imagedestroy($dst_img);
            throw new \Exception('Could not save cropped image to '.$dst);
        }
        
        // Cleanup
        imagedestroy($src_img);
        imagedestroy($dst_img);

        return $dst;
    }


    /**
     * Scale an image to new width and height, copy to spec'd dir. 
     * This may distort aspect ratio!
     *
     *
     */
    public static function scale($src,$dst,$new_w,$new_h) {
        self::_prep_destination($dst);
        $src_img = self::_get_resource($src);
        
        $ox = imagesx($src_img);
        $oy = imagesy($src_img);
        $nx = $new_w;
        $ny = $new_h;
        
        return self::_create($src_img, $dst, $ox,$oy,$nx,$ny);
    }

    /**
     * Scale an image to a new height while maintaining aspect ratio.
     * Processes image at $src path and writes it to the $dst filename,
     * changing the image type according to the extensions detected.
     * This will attempt to create the destination directory if it 
     * does not exist. 
     *
     * Throws tantrums if things don't work out its way.
     *
     * @param string $src full path to source image
     * @param string $dst full name of image including path
     * @param integer $new_h new height in pixels
     * @return string $dst on success. Throws exception on fail.
     */
    public static function scale2h($src,$dst,$new_h) { 
    
        self::_prep_destination($dst);
        
        $src_img = self::_get_resource($src);
        
        // old XY (from src) to new XY
        $ox = imagesx($src_img);
        $oy = imagesy($src_img);
        $nx = floor($new_h * ( $ox / $oy ));
        $ny = $new_h;
        
        return self::_create($src_img, $dst, $ox,$oy,$nx,$ny);
    }
    
    /**
     * Scale an image to a new width while maintaining aspect ratio.
     * Processes image at $src path and writes it to the $dst filename,
     * changing the image type according to the extensions detected.
     * This will attemp to create the destination directory if it 
     * does not exist. 
     *
     * Throws tantrums if things don't work out its way.
     *
     * @param string $src full path to source image
     * @param string $dst full name of image including path
     * @param integer $new_w new width in pixels
     * @return string $dst on success. Throws exception on fail.
     */
    public static function scale2w($src,$dst,$new_w) { 
    
        self::_prep_destination($dst);
        
        $src_img = self::_get_resource($src);        
        
        // old XY (from src) to new XY
        $ox = imagesx($src_img);
        $oy = imagesy($src_img);
        $nx = $new_w;
        $ny = floor($new_w * ($oy / $ox));
        
        return self::_create($src_img, $dst, $ox,$oy,$nx,$ny);
    }

     /**
     * rotateCW Clockwise
     *
     * Throws tantrums if things don't work out its way.
     *
     * @param string $src full path to source image
     * @param int $degrees angle
     */
    public static function rotate($src,$degrees,$clockwise=true) {  
        if (!file_exists($src)) {
            throw new \Exception('File not found '.$src);
        }
        
        if($clockwise) {
            $degrees = -$degrees;
        }

        $src_img = '';
        $ext = strtolower(strrchr($src, '.'));
        switch ($ext) {
            case '.jpg':
            case '.jpeg':
                $src_img = @imagecreatefromjpeg($src);
                // Rotate
                $rotate = imagerotate($src_img, $degrees, 0);
                imagejpeg($rotate,$src);
                break;
            case '.png':
                $src_img = @imagecreatefrompng($src);
                $bgColor = imagecolorallocatealpha($src_img, 255, 255, 255, 127);
                // Rotate
                $rotate = imagerotate($src_img, $degrees, $bgColor);
                imagesavealpha($rotate, true);
                imagepng($rotate,$src);
                break;
        }
        
        if (!$src_img) {
            throw new \Exception('Could not read image '.$src); 
        }


        // Free the memory
        imagedestroy($src_img);
        imagedestroy($rotate);

        return $src;
    }

    /** 
     * Generate a thumbnail of dimensions $w x $h from the image at $src, save it to $dst.
     * If the aspect ratio of desired thumbnail does not match the aspect ratio of the original $src,
     * the image will be centered and cropped to fit.
     *
     *  E.g. if original image is 300 x 100 
     *  and desired thumb is 100 x 100,
     *  the sides will be cropped:
     *      +---------------------+
     *      |     ¦        ¦      |
     *      |     ¦ thumb  ¦      |
     *      |     ¦ 100x100¦      |
     *      |     ¦        ¦      |
     *      +---------------------+
     * 
     * @param $src string full path to source image
     * @param $dst string full path to where destination image will be written
     * @param $w integer with of thumbnail in pixels
     * @param $h integer height of thumbnail in pixels
     */
    public static function thumbnail($src,$dst,$w,$h) {

        $w = floor($w);
        $h = floor($h);
        
        if (!$w || !$h) {
            throw new \Exception('Invalid thumbnail dimensions.');
        }
        
        self::_prep_destination($dst);
        
        $src_img = self::_get_resource($src);        
        
        // old XY (from src) to new XY
        $ox = imagesx($src_img);
        $oy = imagesy($src_img);        
        
        $ratio_thumb = $w/$h;
        $ratio_orig = $ox/$oy;
        
        // Scale to height and crop the width
        if ($ratio_thumb < $ratio_orig) {
            $dst = self::scale2h($src,$dst,$h);
            $nx = floor(($h/$oy) * $ox); // calc w of scaled image
            $x = abs(($nx - $w)/2);
            $dst = self::crop($dst,$dst,$x,0,$w,$h); // overwrite the image in place
            return $dst;
        }
        // Scale to width and crop the height
        elseif ($ratio_thumb > $ratio_orig) {
            $dst = self::scale2w($src,$dst,$w);
            $ny = floor(($w/$ox) * $oy); // calc h of scaled image
            $y = abs(($ny - $h)/2);
            $dst = self::crop($dst,$dst,0,$y,$w,$h); // overwrite the image in place
            return $dst;
        
        }
        // Ratios Equal: Scale only
        else {
            return self::scale($src,$dst,$w,$h);
        }
        
    }    
}
/*EOF*/