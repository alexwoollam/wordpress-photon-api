<?php
abstract class PhotonBaseTestcase extends PHPUnit\Framework\TestCase {
	protected function getSumOfSquarePixelDistances( $image1, $image2, $check_only_overlap = false) {
		$width1 = imagesx( $image1 );
		$height1 = imagesy( $image1 );
		$width2 = imagesx( $image2 );
		$height2 = imagesy( $image2 );

		if ( ! $check_only_overlap && ( $width1 !== $width2 || $height1 !== $height2 ) ) {
			return false;
		}
		$width = min( $width1, $width2 );
		$height = min( $height1, $height2 );

		$sum = 0;
		for ( $i = 0; $i < $height; $i++ ) {
			for ( $j = 0; $j < $width; $j++ ) {
				$rgb1 = imagecolorat( $image1, $j, $i );
				$r1 = ( $rgb1 >> 16 ) & 0xff;
				$g1 = ( $rgb1 >> 8 ) & 0xff;
				$b1 = $rgb1 & 0xff;

				$rgb2 = imagecolorat( $image2, $j, $i );
				$r2 = ( $rgb2 >> 16 ) & 0xff;
				$g2 = ( $rgb2 >> 8 ) & 0xff;
				$b2 = $rgb2 & 0xff;

				$sum += ( $r2 - $r1 ) * ( $r2 - $r1 ) + ( $g2 - $g1 ) * ( $g2 - $g1 ) + ( $b2 - $b1 ) * ( $b2 - $b1 );
			}
		}

		return $sum;
	}

	/**
	 * Helper assertion for testing an image is in grayscale format
	 *
	 * @param string $image_data Image raw data.
	 */
	protected function assertImageGrayscale( $image_data ) {
		$grayscale = true;
		$image = imagecreatefromstring( $image_data );
		$width = imagesx( $image );
		$height = imagesy( $image );

		for ( $h = 0; $h < $height; $h++ ) {
			if ( false == $grayscale ) {
				break;
			}
			for ( $w = 0; $w < $width; $w++ ) {
				$rgb = imagecolorat( $image, $w, $h );
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				if ( $r > $b + 1 || $r < $b - 1 ) {
					$grayscale = false;
					break;
				}
				if ( $r > $g + 1 || $r < $g - 1 ) {
					$grayscale = false;
					break;
				}
				if ( $b > $g + 1 || $b < $g - 1 ) {
					$grayscale = false;
					break;
				}
			}
		}
		$this->assertEquals( $grayscale, true );
	}

	/**
	 * Helper assertion to check actual image dimensions an image using raw data
	 *
	 * @param string $image_data Image raw data.
	 * @param int    $width      Width to verify.
	 * @param int    $height     Height to verify.
	 */
	protected function assertImageDimensions( $image_data, $width, $height ) {
		$size = getimagesizefromstring( $image_data );
		$detected_width = $size[0] ?? false;
		$detected_height = $size[1] ?? false;
		$this->assertEquals( $width, $detected_width );
		$this->assertEquals( $height, $detected_height );
	}

	/**
	 * Helper assertion to check image exif rotaion
	 *
	 * @param string $image_data Image raw data.
	 * @param string $rotation   Rotation to verify.
	 */
	protected function assertRotation( $image_data, $rotation ) {
		$file = tempnam( '/dev/shm/', 'exif-' );
		register_shutdown_function( 'unlink', $file );
		file_put_contents( $file, $image_data );
		// exif_read_data() does not support webp. Therefore we rely on external tools
		$exif = json_decode( shell_exec( 'exiftool -j -Orientation -n ' . escapeshellarg( $file ) ), true );
		$this->assertEquals( $rotation, $exif[0]['Orientation'] ?? false );
	}

	/**
	 * Helper assertion to check image format using raw data
	 *
	 * @param string $image_data   Image raw data.
	 * @param string $image_format Format to verify.
	 */
	protected function assertImageFormat( $image_data, $image_format ) {
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime_type = $finfo->buffer( $image_data );
		$this->assertEquals( "image/$image_format", $mime_type );
	}

	/**
	 * Helper assertion to determine if the returned data indicates an error
	 *
	 * @param string $data the returned data
	 */
	protected function assertRequestFailed( $data ) {
		$this->assertEquals( 'Error', substr( $data, 0, 5 ) );
	}

	/**
	 * Returns the data output by blogs.php.
	 *
	 * @param string $file Test image file to be loaded
	 * @param array  $params Get parameters to be used in the fake request
	 * @param array  $headers Custom headers
	 */
	protected function get_blogs_php_image( $file, $params = array(), $headers = array(), $extra_php_arguments = array(), $num_appended_bytes = 0 ) {
		if ( empty( $params ) ) {
			// Serve original file if no operations are defined
			$filepath = __DIR__ . "/data/$file";
			return file_get_contents( $filepath );
		}

		// Turn the $params array into a single line, PHP-parseable syntax
		$get_str = strtr( var_export( $params, true ), "'\r\n", '"  ' );

		$extra_headers = '';
		foreach ( $headers as $header => $value ) {
			$key = 'HTTP_' . strtoupper( $header );
			$extra_headers .= '$_SERVER[' . var_export( $key, true ) . ']=' . var_export( $value, true ) . ';';
		}

		ob_start();
		passthru(
			'echo | php -B ' .
			escapeshellarg(
				"require_once '" . dirname( __DIR__ ) . '/plugin.php' . "';" .
				'global $num_appended_bytes; $num_appended_bytes = ' . (int) $num_appended_bytes . ';' .
				"require_once '" . __DIR__ . '/testing-hooks.php' . "';" .
				"\$_SERVER['REQUEST_URI'] = '" . "/example.com/$file" . "';" .
				$extra_headers .
				'$_GET=' . $get_str . ';'
			) .
			' ' . implode( ' ', array_map( 'escapeshellarg', $extra_php_arguments ) ) .
			' -F ' .
			escapeshellarg( dirname( __DIR__ ) . '/index.php' ),
			$return
		);
		$data = ob_get_clean();

		$this->assertEquals( 0, $return );

		return $data;
	}

	protected function isPixelDark( $pixel ) {
		// All channels are > 32
		return 0 === ( $pixel & 0xe0e0e0 );
	}

	protected function calculateBlackBorders( $image_data ) {
		$image = imagecreatefromstring( $image_data );
		$width = imagesx( $image );
		$height = imagesy( $image );

		for ( $i = 0; $i < $height; $i++ ) {
			for ( $j = 0; $j < $width && $this->isPixelDark( imagecolorat( $image, $j, $i ) ); $j++ );
			if ( $j !== $width ) {
				break;
			}
		}
		$top = $i;

		for ( $i = $height - 1; $i >= 0; $i-- ) {
			for ( $j = 0; $j < $width && $this->isPixelDark( imagecolorat( $image, $j, $i ) ); $j++ );
			if ( $j !== $width ) {
				break;
			}
		}
		$bottom = $height - 1 - $i;

		for ( $j = 0; $j < $width; $j++ ) {
			for ( $i = 0; $i < $height && $this->isPixelDark( imagecolorat( $image, $j, $i ) ); $i++ );
			if ( $i !== $height ) {
				break;
			}
		}
		$left = $j;

		for ( $j = $width - 1; $j >= 0; $j-- ) {
			for ( $i = 0; $i < $height && $this->isPixelDark( imagecolorat( $image, $j, $i ) ); $i++ );
			if ( $i !== $height ) {
				break;
			}
		}
		$right = $width - 1 - $j;

		return array( $top, $bottom, $left, $right );
	}

	protected function getPNGChunks( $data ) {
		// Assumes valid PNG
		$chunks = array();
		$offset = 8;
		while ( $offset < strlen( $data ) ) {
			$len = ord( $data[ $offset ] ) << 24
				| ord( $data[ $offset + 1 ] ) << 16
				| ord( $data[ $offset + 2 ] ) << 8
				| ord( $data[ $offset + 3 ] );
			$chunks[] = substr( $data, $offset + 4, 4 );
			$offset += $len + 12;
		}
		return $chunks;
	}

}

