<?php
namespace Solver\Shake;

/**
 * Quick image processing utilities.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ImageUtils {
	/**
	 * Processes images (intended for photos) to a variety of sizes, for thumbnail generation and different devices.
	 * 
	 * TODO: Options to add: 1) don't upscale (keep ratio but doesn't upsample, which only wastes bytes). 2) area/point of interest (for biased cropping).
	 * 
	 * @param string $sourceFilepath
	 * Absolute filepath to the file (must be JPEG for now).
	 * 
	 * @param string $targetFilepath
	 * Absolute filepath from where to write the image.
	 * 
	 * @param int $targetWidth
	 * Desired target width.
	 * 
	 * @param int $targetHeight
	 * Desired target height.
	 *
	 * @param array $options
	 * Optional (default = null). This is a dict of options, with the following supported keys:
	 * 
	 * # bool "cover"
	 * Default false. If false, the image is resized to be "contained" within the target width and height (preserving
	 * ratio). If true, the image is resized so it covers the target width and height area completely, and of the ratio
	 * of the source image and the target boxes differ, portions may extend outside that area. 
	 * 
	 * # bool "crop"
	 * Default false. This setting is only supported in "cover" = true mode. It'll crop the sides of the resized image 
	 * (symmetrically) in order to produce the exact wanted target width and height. In "cover" = false, the crop 
	 * setting has no effect.
	 * 
	 * # float "quality"
	 * Default 0.75. JPEG image quality from 0 to 1.
	 * 
	 * # int "snap"
	 * Default 1. The dimensions of the target image will be "snapped" to a grid with the specified resolution. JPEG
	 * files have an internal resolution of 8x8 pixel blocks, so when multiple images are combined in a single sprite
	 * sheet (or other similar operations), it's helpful to snap the size to 8px to match the blocks for best quality.
	 * 
	 * Keep in mind snapping will product slight ratio distortion (not perceptible for small snap values). 
	 */
	public static function resize($sourceFilepath, $targetFilepath, $targetWidth, $targetHeight, array $options = null) {
		static $defaults = [
			'cover' => false,
			'crop' => false,
			'quality' => 0.75,
			'snap' => 1,
		];
		
		if ($options === null) $options = $defaults; else $options += $defaults;
				
		$source = \imagecreatefromjpeg($sourceFilepath);
				
		$sourceWidth = \imagesx($source);
		$sourceHeight = \imagesy($source);
		
		if (false) {
			// DEPR:
			if ($targetWidth !== $targetHeight) throw new \Exception('Target width and height must be the same for now in mode "cover" (square).');
		
			if ($sourceWidth > $sourceHeight) {
				$sourceCopyWidth = $sourceHeight;
				$sourceCopyHeight = $sourceHeight;
				$sourceCopyX = (int) ($sourceWidth - $sourceHeight) / 2;
				$sourceCopyY = 0;
			} else {
				$sourceCopyWidth = $sourceWidth;
				$sourceCopyHeight = $sourceWidth;
				$sourceCopyX = 0;
				$sourceCopyY = (int) ($sourceHeight - $sourceWidth) / 2;
			}
			
			// /DEPR
		} else {
			$targetRatio = $targetWidth / $targetHeight;
			$sourceRatio = $sourceWidth / $sourceHeight;
			
			$sourceCopyWidth = $sourceWidth;
			$sourceCopyHeight = $sourceHeight;
			$sourceCopyX = 0;
			$sourceCopyY = 0;
			
			if ($options['cover']) {
				if ($options['crop']) {
					if ($sourceRatio > $targetRatio) {
						$sourceCopyWidth = (int) ($sourceHeight * $targetRatio);
						$sourceCopyX = (int) (($sourceWidth - $sourceCopyWidth) / 2);
					} else {
						$sourceCopyHeight = (int) ($sourceWidth / $targetRatio);
						$sourceCopyY = (int) (($sourceHeight - $sourceCopyHeight) / 2);
					}
				} else {
					if ($sourceRatio > $targetRatio) {
						$targetWidth = (int) ($targetHeight * $sourceRatio);
					} else {
						$targetHeight = (int) ($targetWidth / $sourceRatio);
					}
				}
			} else {
				if ($sourceRatio > $targetRatio) {
					$targetHeight = (int) ($targetWidth / $sourceRatio);
				} else {
					$targetWidth = (int) ($targetHeight * $sourceRatio);
				}
			}
		}
		
		if ($options['snap'] != 1) {
			$snap = $options['snap'];
			$targetWidth = (int) ($snap * \round($targetWidth / $snap)); 
			$targetHeight = (int) ($snap * \round($targetHeight / $snap)); 
		}
		
		$target = \imagecreatetruecolor($targetWidth, $targetHeight);
		
		\imagecopyresampled($target, $source, 0, 0, $sourceCopyX, $sourceCopyY, $targetWidth, $targetHeight, $sourceCopyWidth, $sourceCopyHeight);
		
		\imagejpeg($target, $targetFilepath, (int) (100 * $options['quality']));
	}
}