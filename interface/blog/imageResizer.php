<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
$IV = array(
	'GET' => array(
		'f' => array('filename'),
		'm' => array('int', 0, 2000, 'default' => null)
	)
);
require ROOT . '/library/preprocessor.php';
$imagePath = __TEXTCUBE_ATTACH_DIR__."/$blogid/{$_GET['f']}";
if ($fp = @fopen($imagePath, 'r')) {
	if(isset($_GET['m']) && !empty($_GET['m'])){
		if (file_exists($imagePath)) {
			$imageInfo = getimagesize($imagePath);
			// Textcube-NG: r3-32 R-526 — 그림이 아니면 getimagesize 가 false 라 원본은 아래 $imageInfo[0]·[1] 에서 false 배열 접근 경고(8.x Warning)를 냈다. 원본에서 null 이던 값과 같게 0 으로 둔다(흐름 같음)
			if (!is_array($imageInfo)) $imageInfo = array(0, 0);
			$cropSize = (int)$_GET['m'];
			$objThumbnail = new Utils_Image();
			if ($imageInfo[0] > $imageInfo[1])
				list($tempWidth, $tempHeight) = $objThumbnail->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], NULL, $cropSize);
			else
				list($tempWidth, $tempHeight) = $objThumbnail->calcOptimizedImageSize($imageInfo[0], $imageInfo[1], $cropSize, null);
			
			$objThumbnail->imageFile = $imagePath;
			if ($objThumbnail->resample($tempWidth, $tempHeight) && $objThumbnail->cropRectBySize($cropSize, $cropSize)) {
				$objThumbnail->saveAsCache();
			}
			unset($objThumbnail);
		}
	}else{
		$imageInfo = @getimagesize($imagePath);
		if (function_exists('gd_info')) {
			// Textcube-NG: r3-32 R-526 — 그림이 아니면 getimagesize 가 false 라 원본은 false[2] 경고(8.x Warning)를 냈다. 0 으로 default(404)에 보낸다
			switch (is_array($imageInfo) ? $imageInfo[2] : 0) {
				case 1:
					$image = imagecreatefromgif($imagePath);
					break;
				case 2:
					$image = imagecreatefromjpeg($imagePath);
					break;
				case 3:
					$image = imagecreatefrompng($imagePath);
					break;
				case 6:
					// Textcube-NG: r3-31 R-519 — 6 은 IMAGETYPE_BMP 다. 원본은 WBMP 용 imagecreatefromwbmp() 로 열어 늘 실패했다. PHP 7.2+ imagecreatefrombmp() 로 연다(없으면 아래 404)
					$image = function_exists('imagecreatefrombmp') ? imagecreatefrombmp($imagePath) : false;
					break;
				case IMAGETYPE_WEBP:
					// Textcube-NG: r3-31 R-519 — gd 없는 분기와 맞춰 WEBP 도 받는다. libwebp 없이 빌드된 gd 면 아래 404
					$image = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($imagePath) : false;
					break;
				default:
					Respond::NotFoundPage();
			}
			// Textcube-NG: r3-31 R-515 — gd 가 그림을 열지 못하면(깨진 파일, imagecreatefrombmp·imagecreatefromwebp 가 없는 gd 등) false 가 되어 PHP 8 에서 imagejpeg()/imagecopyresampled() TypeError 가 난다(원본 같음, 7.4 는 경고 뒤 빈 그림). 그림 아님과 같은 404로 끝낸다
			if ($image === false) Respond::NotFoundPage();
			// Textcube-NG: 폭·높이 0 그림은 나눗셈(PHP 8 DivisionByZeroError)·캔버스 생성 전에 그림 아님과 같은 404로 끝낸다(r3-30, R-485)
			if (empty($imageInfo[0]) || empty($imageInfo[1])) Respond::NotFoundPage();
			$canvasWidth = 640;
			$canvasHeight = round($imageInfo[1] * ($canvasWidth / $imageInfo[0]));
			if ($imageInfo[0] > $canvasWidth) {
				$canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
				imagealphablending($canvas, 0);
				imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, 0x7f000000);
				imagecopyresampled($canvas, $image, 0, 0, 0, 0, $canvasWidth, $canvasHeight, $imageInfo[0], $imageInfo[1]);
			} else
				$canvas = $image;
			header('Content-type: image/jpeg');
			imagejpeg($canvas);
		} else {
			// Textcube-NG: r3-31 R-519 — gd 가 없을 때도 그림(getimagesize 가 알아본 GIF·JPEG·PNG·BMP·WEBP)만 그 형식으로 내보낸다. 원본은 어떤 첨부든 Content-Type 없이(text/html) 내보내
			// 올린 .html·.svg 가 블로그 주소에서 문서로 열렸다(interface/owner/reader/imageLoader 와 같은 방식)
			if (($imageInfo === false) || !in_array($imageInfo[2], array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP, IMAGETYPE_WEBP), true)) {
				fclose($fp);
				Respond::NotFoundPage();
			}
			header('Content-type: ' . $imageInfo['mime']);
			header('X-Content-Type-Options: nosniff');
			while (!feof($fp)) {
				echo fread($fp, 8192);
				flush();
			}
		}
		fclose($fp);
	}
} else
	Respond::NotFoundPage();
?>
