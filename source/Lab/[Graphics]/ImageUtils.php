<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Lab;

/**
 * Quick image processing utilities.
 */
class ImageUtils {
	protected static $cacheSourceImage = false;
	protected static $cache = [];
	
	/**
	 * Call this to enable source image caching in RAM. If you're producing multiple targets from one source
	 * sequentially, this will speed up operation. Otherwise it'll just waste memory.
	 * 
	 * Caching is disabled by default.
	 * 
	 * TODO: This is hacky, but it'll do for now. Long term, we should decouple image loading and target generation.
	 */
	public static function enableSourceImageCache() {
		self::$cacheSourceImage = true;
	}
	
	/**
	 * Disables the effect of enableSourceImageCache().
	 * 
	 * Caching is disabled by default.
	 */
	public static function disableSourceImageCache() {
		self::$cacheSourceImage = false;
		self::$cache = [];
	}
	
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
	 * @param int|null $targetWidth
	 * Desired target width. Pass null to use the source width.
	 * 
	 * @param int|null $targetHeight
	 * Desired target height. Pass null to use the source height.
	 *
	 * @param array $options
	 * Optional (default = null). This is a dict of options, with the following supported keys:
	 * 
	 * # bool "sourceFilepathForType"
	 * Default null. Supply a filepath to be used for type detection (from its extension). The default behavior is
	 * to use the extension from $sourceFilepath.
	 * 
	 * # bool "sourceMimeType"
	 * Default null. Supply a MIME type that overrides the file extension of the $sourceFilepath. The default behavior
	 * is to guess the MIME from the $sourceFilepath file extension.
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
	 * Snapping may produce slight ratio distortion, but it's not perceptible for small snap values. 
	 * 
	 * # bool "upsample"
	 * Default true. Set to false to avoid small images being blown up in size. This is typically unwanted. If
	 * upsampling is disabled, the image will be just recompressed with the given quality option without modification.
	 * 
	 * The "snap" setting doesn't apply in this case (as width/height won't be modified).
	 * 
	 * @return array
	 * Returns meta information about the source and target image.
	 * 
	 * A dict with keys:
	 * 
	 * - dict $source: With int keys $width and $height. 
	 * - dict $target: Widht int keys $width and $height.
	 */
	public static function resize($sourceFilepath, $targetFilepath, $targetWidth, $targetHeight, array $options = null) {
		static $defaults = [
			'sourceFilepathForType' => null,
			'sourceMimeType' => null,
			'upsample' => true,
			'cover' => false,
			'crop' => false,
			'quality' => 0.75,
			'snap' => 1,
		];
		
		if ($options === null) $options = $defaults; else $options += $defaults;
		
		$sourceFilepathForType = isset($options['sourceFilepathForType']) ? $options['sourceFilepathForType'] : $sourceFilepath;
		
		if ($options['sourceMimeType']) {
			$sourceMimeType = $options['sourceMimeType'];
		} else {
			$sourceMimeType = self::getMimeTypeFromExtension($sourceFilepathForType);
		}
		
		if (isset(self::$cache[$sourceFilepath])) {
			list($source, $sourceWidth, $sourceHeight) = self::$cache[$sourceFilepath];
		} else {
			// We keep one item in cache, if it doesn't match, we reset.
			self::$cache = [];
			
			switch ($sourceMimeType) {
				case 'image/jpeg':
					$source = \imagecreatefromjpeg($sourceFilepath);
					break;
					
				case 'image/png':
					$source = \imagecreatefrompng($sourceFilepath);
					break;
					
				case 'image/gif':
					$source = \imagecreatefromgif($sourceFilepath);
					break;
				
				default:
					throw new \Exception('Unknown or unsupported source file type: "' . $sourceFilepathForType . '".');
			}
					
			$sourceWidth = \imagesx($source);
			$sourceHeight = \imagesy($source);
			
			if (self::$cacheSourceImage) {
				self::$cache[$sourceFilepath] = [$source, $sourceWidth, $sourceHeight];
			}
		}
		
		if ($targetWidth === null) $targetWidth = $sourceWidth;
		if ($targetHeight === null) $targetHeight = $sourceHeight;
		
		// FIXME: Not sure what this if (false) block is, but we have to look at it and fix it / remove it.
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
		
		if ($options['upsample'] == false && ($targetWidth > $sourceWidth || $targetHeight > $sourceHeight)) {
			$target = $source;
		} else {
			if ($options['snap'] != 1) {
				$snap = $options['snap'];
				$targetWidth = (int) ($snap * \round($targetWidth / $snap)); 
				$targetHeight = (int) ($snap * \round($targetHeight / $snap)); 
			}
			
			$target = \imagecreatetruecolor($targetWidth, $targetHeight);
		
			\imagecopyresampled($target, $source, 0, 0, $sourceCopyX, $sourceCopyY, $targetWidth, $targetHeight, $sourceCopyWidth, $sourceCopyHeight);
		}
		
		$targetMimeType = self::getMimeTypeFromExtension($targetFilepath);
		
		switch ($targetMimeType) {
			case 'image/jpeg':
				\imagejpeg($target, $targetFilepath, (int) (100 * $options['quality']));
				break;
			
			case 'image/png':
				// FIXME: The parameter "quality" should become "compression" and invserse of what it is (with smart
				// defaults for every output file type). For now we pick 0 for speed.
				\imagepng($target, $targetFilepath, 0);
				break;
			
			default:
				throw new \Exception('Unknown or unsupported target file type: "' . $targetFilepath . '".');
		}
					
		
		return [
			'source' => [
				'width' => $sourceWidth,
				'height' => $sourceHeight,
			],
			'target' => [
				'width' => $targetWidth,
				'height' => $targetHeight,
			],	
		];
	}
	
	protected static function getMimeTypeFromExtension($filepath) {
		if (\preg_match('/\.(\w+)$/Di', $filepath, $matches)) {
			$extension = $matches[1];
		} else {
			$extension = null;
		}
		
		switch ($extension) {
			case 'jpeg':
			case 'jpe':
			case 'jpg':
				return 'image/jpeg';
				break;
				
			case 'png':
				return 'image/png';
				break;
				
			case 'gif':
				return 'image/gif';
				break;
			
			default:
				return 'unsupported';
		}
	}
}