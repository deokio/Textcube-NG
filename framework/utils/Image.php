<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

#[AllowDynamicProperties]
class Utils_Image extends Singleton {
	function __construct() {
		$this->reset();
	}

	public static function getInstance() {
		return self::_getInstance(__CLASS__);
	}

	function reset() {
		$this->extraPadding = 0;
		$this->imageFile = NULL;
		$this->resultImageDevice = NULL;
		$this->bgColorBy16 = $this->hexRGB("FFFFFF");
	}
	
	function resample($width, $height) {
		if (empty($width) && empty($height))
			return false;
		if (empty($this->imageFile) || !file_exists($this->imageFile))
			return false;
		
		// Textcube-NG: 폭·높이가 1 미만이면(예: 4096x10 이미지를 폭 100 으로 줄이면 높이 0) imagecreatetruecolor() 가
		//   PHP 8 에서 ValueError 를 던지므로 만들기 전에 false 를 돌려준다. 호출자(getImageResizer)는 false 면 원본 URL 로 되돌아간다(r3-24, R-443)
		$width = (int)$width;
		$height = (int)$height;
		if ($width < 1 || $height < 1)
			return false;

		// create an image device as image format.
		switch ($this->getImageType($this->imageFile)) {
			case "gif":
				if (imagetypes() & IMG_GIF) {
					$originImageDevice = imagecreatefromgif($this->imageFile);
				} else {
					return false;
				}
				break;
			case "jpg":
				if (imagetypes() & IMG_JPG) {
					$originImageDevice = imagecreatefromjpeg($this->imageFile);
				} else {
					return false;
				}
				break;
			case "png":
				if ((imagetypes() & IMG_PNG) && function_exists('imagecreatefrompng')) {
					$originImageDevice = imagecreatefrompng($this->imageFile);
				} else {
					return false;
				}
				break;
			case "wbmp":
				if (imagetypes() & IMG_WBMP) {
					$originImageDevice = imagecreatefromwbmp($this->imageFile);
				} else {
					return false;
				}
				break;
			// Textcube-NG: webp·avif 는 GD 에 읽기 함수가 있을 때만(PHP 7.4 GD 에는 avif 가 없다)(r3-20)
			case "webp":
				if (function_exists('imagecreatefromwebp')) {
					$originImageDevice = imagecreatefromwebp($this->imageFile);
					if ($originImageDevice === false) return false;
				} else {
					return false;
				}
				break;
			case "avif":
				if (function_exists('imagecreatefromavif')) {
					$originImageDevice = imagecreatefromavif($this->imageFile);
					if ($originImageDevice === false) return false;
				} else {
					return false;
				}
				break;
			case "xpm":
				if (imagetypes() & IMG_XPM) {
					$originImageDevice = imagecreatefromxpm($this->imageFile);
				} else {
					return false;
				}
				break;
			default:
				return false;
				break;
		}
		// 리샘플링은 최종단계에서 리샘플링만을 하는 기능임. 시스템을 예로 들면 OS의 기능에 해당함.
		// 이미지 프로세스는 어플리케이션의 기능으로 볼 수 있고, 따라서 이미지 리샘플링 중에는 이벤트가 끼어들면 안 됨.
		//$originImageDevice = fireEvent('BeforeResizeImage', $originImageDevice, $this);
		
		if (Path::getExtension($this->imageFile) == ".gif") {
			$this->resultImageDevice = imagecreate($width, $height);
		} else {
			$this->resultImageDevice = imagecreatetruecolor($width, $height);
		}
		
		$temp = imagecolorallocate($this->resultImageDevice, $this->bgColorBy16['R'], $this->bgColorBy16['G'], $this->bgColorBy16['B']);
		imagefilledrectangle($this->resultImageDevice, 0, 0, $width, $height, $temp);
		imagecopyresampled($this->resultImageDevice, $originImageDevice, 0, 0, 0, 0, $width, $height, imagesx($originImageDevice), imagesy($originImageDevice));
		//$this->resultImageDevice = fireEvent('AfterResizeImage', $this->resultImageDevice, $this);
		
		return true;
	}
	
	function resizeImageToContent($property, $originSrc, $imageWidth) {
		if ( !is_readable($originSrc) ) {
			return array($property, false);
		}
		
		list($originWidth, $originHeight, $type, $attr) = getimagesize($originSrc);		
		$attributes = Misc::getAttributesFromString($property, false);
		
		// 단위 변환.
		$onclickFlag = false;
		if (array_key_exists('width', $attributes)) {
			if (preg_match('/(.+)(%?)/', $attributes['width'], $matches)) {
				if($matches[2] == '%')
					$attributes['width'] = round($originWidth * $matches[1] / 100);
				else
					$attributes['width'] = intval($matches[1]);
				
			}
		}

		if (array_key_exists('height', $attributes)) {
			if (preg_match('/(.+)(%?)/', $attributes['height'], $matches)) {
				if ($matches[2] == '%')
					$attributes['height'] = round($originHeight * $matches[1] / 100);
				else
					$attributes['height'] = intval($matches[1]);
				
			}
		}
		
		// 가로, 세로 어느 쪽이든 0이면 이미지는 표시되지 않음. 따라서 계산할 필요 없음.
		if ((isset($attributes['width']) && $attributes['width'] <= 0) || (isset($attributes['height']) && $attributes['height'] <= 0)) {
			return array($property, false);
		}

		// 가로만 지정된 이미지의 경우.
		if (isset($attributes['width']) && !isset($attributes['height'])) {
			// 비어있는 세로를 가로의 크기를 이용하여 계산.
			$attributes['height'] = floor($originHeight * $attributes['width'] / $originWidth);
		// 세로만 지정된 이미지의 경우.
		} else if (!isset($attributes['width']) && isset($attributes['height'])) {
			// 비어있는 가로를 세로의 크기를 이용하여 계산.
			$attributes['width'] = floor($originWidth * $attributes['height'] / $originHeight);
		// 둘 다 지정되지 않은 이미지의 경우.
		} else if (!isset($attributes['width']) && !isset($attributes['height'])) {
			// 둘 다 비어 있을 경우는 오리지널 사이즈로 대치.
			$attributes['width'] = $originWidth;
			$attributes['height'] = $originHeight;
		}

		if ($attributes['width'] > $imageWidth) {
			$attributes['height'] = floor($attributes['height'] * $imageWidth / $attributes['width']);
			$attributes['width'] = $imageWidth;
		}
		
		if ($attributes['width'] < $originWidth || $attributes['height'] < $originHeight) {
			$onclickFlag = true;
		} else {
			$onclickFlag = false;
		}

		$properties = array();
		ksort($attributes);
		foreach($attributes as $key => $value) {
			array_push($properties, "$key=\"$value\"");
		}
		
		return array(implode(' ', $properties), $onclickFlag);
	}
	
	function impressWaterMark($waterMarkFile, $position="left=10|bottom=10", $gamma=100) {
		if ($this->getImageType($waterMarkFile) == "png")
			return $this->_impressWaterMarkCore("PNG", $waterMarkFile, $position);
		else
			return $this->_impressWaterMarkCore("GIF", $waterMarkFile, $position, $gamma);
	}
	
	function _impressWaterMarkCore($type, $waterMarkFile, $position, $gamma=100) {
		if (empty($waterMarkFile))
			return false;
		if (!file_exists($waterMarkFile))
			return false;
		if (empty($this->resultImageDevice))
			return false;
		
		// validate gamma.
		if (!is_int($gamma)) {
			$gamma = 100;
		} else if ($gamma < 0) {
			$gamma = 0;
		} else if ($gamma > 100) {
			$gamma = 100;
		}
		
		list($waterMarkWidth, $waterMarkHeight, $waterMakrType) = getimagesize($waterMarkFile);
		
		// position of watermark.
		if (preg_match("/^(-?[0-9A-Z]+) (-?[0-9A-Z]+)$/i", $position, $temp)) {
			$resultWidth = imagesx($this->resultImageDevice);
			$resultHeight = imagesy($this->resultImageDevice);
			
			switch ($temp[1]) {
				case "left":
					$xPosition = $this->extraPadding;
					break;
				case "center":
					$xPosition = $resultWidth / 2 - $waterMarkWidth / 2;
					break;
				case "right":
					$xPosition = $resultWidth - $waterMarkWidth - $this->extraPadding;
					break;
				default:
					// if positive, calculate x value from left.
					if (preg_match("/^([1-9][0-9]*)$/", $temp[1], $extra)) {
						if ($extra[1] > $resultWidth - $waterMarkWidth) {
							$xPosition = $resultWidth - $waterMarkWidth;
						} else {
							$xPosition = $extra[1];
						}
					// if negative, calculate x value from right.
					} else if (preg_match("/^(-?[1-9][0-9]*)$/", $temp[1], $extra)) {
						if ($resultWidth - $waterMarkWidth - abs($extra[1]) < 0) {
							$xPosition = 0;
						} else {
							$xPosition = $resultWidth - $waterMarkWidth - abs($extra[1]);
						}
					// in the case of 0.
					} else if ($temp[1] == "0") {
						$xPosition = 0;
					// the others. calculate x value from left.
					} else {
						$xPosition = $resultWidth - $waterMarkWidth - $this->extraPadding;
					}
			}
			
			switch ($temp[2]) {
				case "top":
					$yPosition = $this->extraPadding;
					break;
				case "middle":
					$yPosition = $resultHeight / 2 - $waterMarkHeight / 2;
					break;
				case "bottom":
					$yPosition = $resultHeight - $waterMarkHeight - $this->extraPadding;
					break;
				default:
					// if positive, calculate y value from top.
					if (preg_match("/^([1-9][0-9]*)$/", $temp[2], $extra)) {
						if ($extra[1] > $resultHeight - $waterMarkHeight) {
							$yPosition = $resultHeight - $waterMarkHeight;
						} else {
							$yPosition = $extra[1];
						}
					// if negative, calculate y value from bottom.
					} else if (preg_match("/^(-?[1-9][0-9]*)$/", $temp[2], $extra)) {
						if ($resultHeight - $waterMarkHeight - abs($extra[1]) < 0) {
							$yPosition = 0;
						} else {
							$yPosition = $resultHeight - $waterMarkHeight - abs($extra[1]);
						}
					// in the case of 0.
					} else if ($temp[1] == "0") {
						$yPosition = 0;
					// the others. calculate y value from bottom.
					} else {
						$yPosition = $resultHeight - $waterMarkHeight - $this->extraPadding;
					}
			}
		} else {
			$xPosition = $resultWidth - $waterMarkWidth - $this->extraPadding;
			$yPosition = $resultHeight - $waterMarkHeight - $this->extraPadding;
		}
		
		// create watermark image device.
		switch ($waterMakrType) {
			case 1:
				$waterMarkDevice = imagecreatefromgif($waterMarkFile);
				break;
			case 2:
				$waterMarkDevice = imagecreatefromjpeg($waterMarkFile);
				break;
			case 3:
				$waterMarkDevice = imagecreatefrompng($waterMarkFile);
				break;
		}
		
		// PHP >= 4.0.6
		if (strtolower($type) == "png" && function_exists("imagealphablending")) {
			imagealphablending($this->resultImageDevice, true);
			imagecopy($this->resultImageDevice, $waterMarkDevice, $xPosition, $yPosition, 0, 0, $waterMarkWidth, $waterMarkHeight);
		} else {
			// if not support alpha channel, support GIF transparency.
			$tempWaterMarkDevice = imagecreatetruecolor($waterMarkWidth, $waterMarkHeight);
			
			$bgColorBy16 = $this->hexRGB("FF00FF");
			$temp = imagecolorallocate($tempWaterMarkDevice, $bgColorBy16['R'], $bgColorBy16['G'], $bgColorBy16['B']);
			imagecolortransparent($this->resultImageDevice, $temp);
			imagefill($tempWaterMarkDevice, 0, 0, $temp);
			imagecopy($tempWaterMarkDevice, $waterMarkDevice, 0, 0, 0, 0, $waterMarkWidth, $waterMarkHeight);
			
			if (function_exists("imagecopymerge"))
				imagecopymerge($this->resultImageDevice, $tempWaterMarkDevice, $xPosition, $yPosition, 0, 0, $waterMarkWidth, $waterMarkHeight, $gamma);
			else
				imagecopy($this->resultImageDevice, $tempWaterMarkDevice, $xPosition, $yPosition, 0, 0, $waterMarkWidth, $waterMarkHeight);
			
		}
		
		return true;
	}
	
	function cropRectByCoordinates($startX, $startY, $finishX, $finishY) {
		$width = $finishX - $startX;
		$height = $finishY - $startY;
		
		$targetDevice = imagecreatetruecolor($width, $height);
		imagecolorallocate($tempWaterMarkDevice, $this->bgColorBy16['R'], $this->bgColorBy16['G'], $this->bgColorBy16['B']);
		imagecopy($targetDevice, $this->resultImageDevice, 0, 0, $startX, $startY, $width, $height);
		$this->resultImageDevice = $targetDevice;
		unset($targetDevice);
		
		return true;
	}
	
	function cropRectBySize($width, $height, $align='center', $valign='middle') {
		// Textcube-NG: 폭·높이가 1 미만이면 imagecreatetruecolor() 의 ValueError(PHP 8) 대신 false(r3-24, R-443)
		$width = (int)$width;
		$height = (int)$height;
		if ($width < 1 || $height < 1)
			return false;
		switch ($align) {
			case 'left':
				$srcX = 0;
				break;
			case 'center':
				$srcX = imagesx($this->resultImageDevice) == $width ? 0 : -floor((imagesx($this->resultImageDevice) - $width) / 2);
				break;
			case 'right':
				$srcX = imagesx($this->resultImageDevice) == $width ? 0 : -(imagesx($this->resultImageDevice) - $width);
				break;
		}
		
		switch ($valign) {
			case 'top':
				$srcY = 0;
				break;
			case 'middle':
				$srcY = imagesy($this->resultImageDevice) == $height ? 0 : -floor((imagesy($this->resultImageDevice) - $height) / 2);
				break;
			case 'bottom':
				$srcY = imagesy($this->resultImageDevice) == $height ? 0 : -(imagesy($this->resultImageDevice) - $height);
				break;
		}
		
		$targetDevice = imagecreatetruecolor($width, $height);
		$temp = imagecolorallocate($targetDevice, $this->bgColorBy16['R'], $this->bgColorBy16['G'], $this->bgColorBy16['B']);
		imagefilledrectangle($targetDevice, 0, 0, $width, $height, $temp);
		imagecopy($targetDevice, $this->resultImageDevice, $srcX, $srcY, 0, 0, imagesx($this->resultImageDevice), imagesy($this->resultImageDevice));
		$this->resultImageDevice = $targetDevice;
		unset($targetDevice);
		
		return true;
	}
	
	function saveAsFile($fileName) {
		return $this->createThumbnailIntoFile($fileName);
	}
	
	function createThumbnailIntoFile($fileName) {
		if (empty($this->resultImageDevice))
			return false;
		
		imageinterlace($this->resultImageDevice);
		switch ($this->getImageType($this->imageFile)) {
			case "gif":
				imagegif($this->resultImageDevice, $fileName);
				break;
			case "png":
				imagepng($this->resultImageDevice, $fileName);
				break;
			case "wbmp":
				imagewbmp($this->resultImageDevice, $fileName);
				break;
			// Textcube-NG: webp·avif 는 GD 에 쓰기 함수가 없으면 jpeg 로 쓴다(아래 default 와 같은 품질)(r3-20)
			case "webp":
				if (function_exists('imagewebp'))
					imagewebp($this->resultImageDevice, $fileName);
				else
					imagejpeg($this->resultImageDevice, $fileName, 80);
				break;
			case "avif":
				if (function_exists('imageavif'))
					imageavif($this->resultImageDevice, $fileName);
				else
					imagejpeg($this->resultImageDevice, $fileName, 80);
				break;
			case "jpg":
			default:
				imagejpeg($this->resultImageDevice, $fileName, 80);
				break;
		}
		
		$this->resultImageDevice = NULL;
		
		return true;
	}
	
	function saveAsCache() {
		return $this->createThumbnailIntoCache();
	}
	
	function createThumbnailIntoCache() {
		header("Content-type: image/jpeg");
		imagejpeg($this->resultImageDevice);
		
		$originImageDevice = NULL;
		$this->resultImageDevice = NULL;
		
		return true;
	}
	
	function calcOptimizedImageSize($argWidth, $argHeight, $boxWidth=NULL, $boxHeight=NULL, $resizeFlag="reduce") {
		if (empty($boxWidth) && empty($boxHeight)) {
			return array($argWidth, $argHeight);
		} else if (!empty($boxWidth) && empty($boxHeight)) {
			if ($argWidth > $boxWidth) {
				$newWidth = $boxWidth;
				$newHeight = floor($argHeight * $newWidth / $argWidth);
			} else {
				$newWidth = $argWidth;
				$newHeight = $argHeight;
			}
		} else if (empty($boxWidth) && !empty($boxHeight)) {
			if ($argHeight > $boxHeight) {
				$newHeight = $boxHeight;
				$newWidth = floor($argWidth * $newHeight / $argHeight);
			} else {
				$newWidth = $argWidth;
				$newHeight = $argHeight;
			}
		} else {
			if ($argWidth > $boxWidth) {
				$newWidth = $boxWidth;
				$newHeight = floor($argHeight * $newWidth / $argWidth);
			} else {
				$newWidth = $argWidth;
				$newHeight = $argHeight;
			}
			
			if ($newHeight > $boxHeight) {
				$tempHeight = $newHeight;
				$newHeight = $boxHeight;
				$newWidth = floor($newWidth * $newHeight / $tempHeight);
			}
		}
		
		if ($argWidth * $argHeight > $newWidth * $newHeight) {
			if ($resizeFlag == "reduce" || $resizeFlag == "both") {
				$imgWidth = $newWidth;
				$imgHeight = $newHeight;
			} else {
				$imgWidth = $argWidth;
				$imgHeight = $argHeight;
			}
		} else if ($argWidth * $argHeight == $newWidth * $newHeight) {
			$imgWidth = $argWidth;
			$imgHeight = $argHeight;
		} else if ($argWidth * $argHeight < $newWidth * $newHeight) {
			if ($resizeFlag == "enlarge" || $resizeFlag == "both") {
				$imgWidth = $newWidth;
				$imgHeight = $newHeight;
			} else {
				$imgWidth = $argWidth;
				$imgHeight = $argHeight;
			}
		}
		
		return array($imgWidth, $imgHeight);
	}
	
	function getImageType($filename) {
		if (file_exists($filename)) {
			if (function_exists("exif_imagetype")) {
				$imageType = exif_imagetype($filename);
			} else {
				$tempInfo = getimagesize($filename);
				$imageType = $tempInfo[2];
			}
			
			switch ($imageType) {
				case IMAGETYPE_GIF:
					$extension = 'gif';
					break;
				case IMAGETYPE_JPEG:
					$extension = 'jpg';
					break;
				case IMAGETYPE_PNG:
					$extension = 'png';
					break;
				case IMAGETYPE_SWF:
					$extension = 'swf';
					break;
				case IMAGETYPE_PSD:
					$extension = 'psd';
					break;
				case IMAGETYPE_BMP:
					$extension = 'bmp';
					break;
				case IMAGETYPE_TIFF_II:
				case IMAGETYPE_TIFF_MM:
					$extension = 'tiff';
					break;
				case IMAGETYPE_JPC:
					$extension = 'jpc';
					break;
				case IMAGETYPE_JP2:
					$extension = 'jp2';
					break;
				case IMAGETYPE_JPX:
					$extension = 'jpx';
					break;
				case IMAGETYPE_JB2:
					$extension = 'jb2';
					break;
				case IMAGETYPE_SWC:
					$extension = 'swc';
					break;
				case IMAGETYPE_IFF:
					$extension = 'aiff';
					break;
				case IMAGETYPE_WBMP:
					$extension = 'wbmp';
					break;
				case IMAGETYPE_XBM:
					$extension = 'xbm';
					break;
				// Textcube-NG: webp(IMAGETYPE_WEBP, PHP 7.1+)·avif(IMAGETYPE_AVIF, PHP 8.1+ — 없으면 맞지 않는 -1)(r3-20)
				case IMAGETYPE_WEBP:
					$extension = 'webp';
					break;
				case (defined('IMAGETYPE_AVIF') ? constant('IMAGETYPE_AVIF') : -1):
					$extension = 'avif';
					break;
				default:
					$extension = false;
			}
		} else {
			$extension = false;
		}
		
		return $extension;
	}
	
	/* "FFFFFF" => array(255, 255, 255) */
	function hexRGB($hexstr) {
		$int = hexdec($hexstr);
		return array('R' => 0xFF & ($int >> 0x10), 'G' => 0xFF & ($int >> 0x8), 'B' => 0xFF & $int);
	}

	// 본문에 있는 첨부파일이미지 리스트를 뽑아냄
	function getAttachmentExtracts($content, $blogid = null){
		if (is_null($blogid)) $blogid = getBlogId();

		$result = $temp = array();
		// Textcube-NG: avif 치환자도 받는다(r3-20)
		if (preg_match_all('/\[##_(1R|1L|1C|2C|3C|iMazing|Gallery)\|[^|]*\.(gif|jpg|jpeg|png|webp|avif|GIF|JPG|JPEG|PNG|WEBP|AVIF)\|(.[^\[]*)_##\]/mi', $content, $matches)) {
			foreach ($matches[0] as $image) {
				$split = explode("|", $image);
				if (!in_array($split[1], $temp)) $temp[] = $split[1];
			}
		}

		if (preg_match_all('/<img[^>]+?src=("|\')?([^\'">]*?)("|\')/mi', $content, $matches)) {
			foreach ($matches[2] as $image)
				if (!in_array(basename($image), $temp)) $temp[] = basename($image);
		}

		foreach($temp as $filename) {
			if (preg_match('/(.+)\.w(\d{1,})\-h(\d{1,})\.(.+)/', $filename, $matches))
				$filename = $matches[1].'.'.$matches[4];
			// Textcube-NG: 첨부 폴더 밖·폴더 자체(빈 값·.·..·경로 구분자·제어 문자)는 첨부 파일 이름으로 보지 않는다. webp 치환자도 받는다(r3-3)
			if ($filename === '' || $filename === '.' || $filename === '..' || preg_match('/[\x00-\x1F\x7F\/\\\\]/', $filename)) continue;

			if (file_exists(__TEXTCUBE_ATTACH_DIR__."/{$blogid}/{$filename}") && !in_array($filename, $result))
				$result[] = $filename;
		}

		return $result;
	}

	function getImageResizer($filename, $options = null, $blogid = null) {
		// version 1.2.5.1
		// usages :
		// $options = array('size'=>100) // resize & crop to square
		// $options = array('width'=>100) // resize by width 
		// $options = array('width'=>100, 'height'=>50) // resize & crop by width and height
		// $options = array('force'=>true) // refresh image
		// result : $url, $width, $height, $path

		$context = Model_Context::getInstance();

		if (is_null($blogid)) $blogid = getBlogId();
		$force = isset($options['force']) ? $options['force'] : false;
		$absolute = isset($options['absolute']) ? $options['absolute'] : true;

		$originSrc = __TEXTCUBE_ATTACH_DIR__."/{$blogid}/{$filename}";
		$originURL = ($absolute ? $context->getProperty('uri.service'):$context->getProperty('uri.path'))."/attach/{$blogid}/{$filename}";

		if (!file_exists($originSrc)) return false;

		$imageInfo = getimagesize($originSrc);
		if ($imageInfo === false || count($imageInfo) < 1) return false;
		$originWidth = $imageInfo[0];
		$originHeight = $imageInfo[1];

		if (!extension_loaded('gd')) return array($originURL, $originWidth, $originHeight, $originSrc);
		// Textcube-NG: 가로·세로가 0 이면(avif 등) 아래 크기 나눗셈을 하지 않도록, Attachment::IMAGE_MAX_DIMENSION(4096, D2 업로드 거부 기준)을 넘으면
		//   GD 메모리를 쓰지 않도록 GD 로 열지 않고 원본을 쓴다. 업로드는 Attachment::classifyUpload 가 거부하지만 이전 첨부·백업 가져오기 첨부는 남을 수 있다(r3-20)
		if ($originWidth <= 0 || $originHeight <= 0) return array($originURL, $originWidth, $originHeight, $originSrc);
		if (defined('Attachment::IMAGE_MAX_DIMENSION') && ($originWidth > Attachment::IMAGE_MAX_DIMENSION || $originHeight > Attachment::IMAGE_MAX_DIMENSION))
			return array($originURL, $originWidth, $originHeight, $originSrc);

		if (!is_dir(__TEXTCUBE_CACHE_DIR__."/thumbnail")) {
			@mkdir(__TEXTCUBE_CACHE_DIR__."/thumbnail");
			@chmod(__TEXTCUBE_CACHE_DIR__."/thumbnail", 0777);
		}
		if (!is_dir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . $blogid)) {
			@mkdir(__TEXTCUBE_CACHE_DIR__."/thumbnail/" .$blogid);
			@chmod(__TEXTCUBE_CACHE_DIR__."/thumbnail/" . $blogid, 0777);
		}

		$this->imageFile = $originSrc;
		if (isset($options['size']) && is_numeric($options['size'])) {
			if ($imageInfo[0] > $imageInfo[1])
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], NULL, $options['size']);
			else
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], $options['size'], null);

			$resizeWidth = $resizeHeight = $options['size'];
		} else if (isset($options['width']) && is_numeric($options['width']) && isset($options['height']) && is_numeric($options['height'])) {
			if ($options['width'] / $options['height'] > intval($imageInfo[0]) / intval($imageInfo[1]))
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], $options['width'], NULL);
			else
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], NULL, $options['height']);

			$resizeWidth = $options['width'];
			$resizeHeight = $options['height'];
		} else {
			if (isset($options['width']) && is_numeric($options['width'])) {
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], $options['width'], NULL);
			} elseif (isset($options['height']) && is_numeric($options['height'])) {
				list($tempWidth, $tempHeight) = $this->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], NULL, $options['height']);
			} else {
				$this->reset();
				return array($originURL, $originWidth, $originHeight, $originSrc);
			}

			$resizeWidth = $tempWidth; 
			$resizeHeight = $tempHeight;
		}
		$resizeFilename = preg_replace("/\\.([[:alnum:]]+)$/i", ".w{$resizeWidth}-h{$resizeHeight}.\\1", $filename);
		$resizeSrc = __TEXTCUBE_CACHE_DIR__."/thumbnail/{$blogid}/{$resizeFilename}";
		$resizeURL = ($absolute ? $context->getProperty('uri.service'):$context->getProperty('uri.path')) . "/cache/thumbnail/{$blogid}/{$resizeFilename}";

		if($force) @unlink($resizeSrc);

		if (file_exists($resizeSrc)) { 
			$this->reset();
			return array($resizeURL, $resizeWidth, $resizeHeight, $resizeSrc);		
		}
		if ($this->resample($tempWidth, $tempHeight)) {
			if (isset($options['width']) && is_numeric($options['width']) && isset($options['height']) && is_numeric($options['height'])) {
				@$this->cropRectBySize($options['width'], $options['height']);
			} else if (isset($options['size']) && is_numeric($options['size'])) {
				@$this->cropRectBySize($options['size'], $options['size']);
			}
			if ($this->saveAsFile($resizeSrc)) {
				@chmod($resizeSrc, 0666);
				$this->reset();
				return array($resizeURL, $resizeWidth, $resizeHeight, $resizeSrc);	
			}
		}
		$this->reset();
		return array($originURL, $originWidth, $originHeight, $originSrc);
	}
}
?>
