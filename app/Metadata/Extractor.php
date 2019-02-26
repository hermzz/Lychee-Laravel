<?php

namespace App\Metadata;

use PHPExif\Reader\Reader;

class Extractor
{
	/**
	 * Extracts metadata from an image file
	 *
	 * @param  string $filename
	 * @return array
	 */
	public function extract(string $filename): array
	{
		$reader = Reader::factory(Reader::TYPE_NATIVE);
		$exif = $reader->read($filename);

		$metadata = [
			'type'        => $exif->getMimeType(),
			'width'       => $exif->getWidth(),
			'height'      => $exif->getHeight(),
			'title'       => $exif->getTitle(),
			'description' => '',
			'orientation' => $exif->getOrientation(),
			'iso'         => $exif->getIso(),
			'aperture'    => $exif->getAperture(),
			'make'        => '',
			'model'       => $exif->getCamera(),
			'shutter'     => $exif->getExposure(),
			'focal'       => $exif->getFocalLength(),
			'takestamp'   => $exif->getCreationDate(),
			'lens'        => '',
			'tags'        => '',
			'position'    => '',
			'latitude'    => null,
			'longitude'   => null,
			'altitude'    => null
		];

		// Size
		$size = filesize($filename) / 1024;
		if ($size >= 1024) {
			$metadata['size'] = round($size / 1024, 1).' MB';
		}
		else {
			$metadata['size'] = round($size, 1).' KB';
		}

		if (!empty($metadata['focal'])) {
			$metadata['focal'] .= ' mm';
		}

		$rawExifData = $exif->getRawData();

		if (!empty($rawExifData['IFD0:Make'])) {
			$metadata['make'] = trim($rawExifData['IFD0:Make']);
		} elseif (!empty($rawExifData['Make'])) {
			$metadata['make'] = trim($rawExifData['Make']);
		}

		if (!empty($exif->getGps())) {
			list($metadata['latitude'], $metadata['longitude']) = explode(',', $exif->getGps());
		}

		// isset check for the altitude ref because the value can be 0
		if (!empty($rawExifData['GPS:GPSAltitude']) && isset($rawExifData['GPS:GPSAltitudeRef'])) {
			$metadata['altitude'] =
				$this->getGPSAltitude($rawExifData['GPS:GPSAltitude'], $rawExifData['GPS:GPSAltitudeRef']);
		} elseif (!empty($rawExifData['GPSAltitude']) && isset($rawExifData['GPSAltitudeRef'])) {
			$metadata['altitude'] =
				$this->getGPSAltitude($rawExifData['GPSAltitude'], $rawExifData['GPSAltitudeRef']);
		}

		foreach ($rawExifData as $key => $value) {
			if (strpos($key, 'LensModel') !== false) {
				$metadata['lens'] = $value;
			}
		}

		if (empty($metadata['lens']) && !empty($rawExifData['LensInfo'])) {
			$metadata['lens'] = trim($rawExifData['LensInfo']);
		} elseif (empty($metadata['lens']) && !empty($rawExifData['UndefinedTag:0x0095'])) {
			$metadata['lens'] = trim($rawExifData['UndefinedTag:0x0095']);
		}

		return $metadata;
	}

	/**
	 * Returns the altitude either above or below sea level
	 *
	 * @param  string $altitude
	 * @param  string $ref
	 * @return float
	 */
	private function getGPSAltitude(string $altitude, string $ref): float
	{
		$flip = ($ref == '1') ? -1 : 1;
		return $flip * (float) $altitude;
	}
}
