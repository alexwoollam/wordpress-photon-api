<?php
require_once __DIR__ . '/base-testcase.php';

class WP_Image_Processing_UnitTestCase extends PhotonBaseTestCase {

	/**
	 * Test the image is resized and cropped as expected.
	 */
	public function testImageCrop() {
		$data = $this->get_blogs_php_image( 'foto777-titolo.jpg', array( 'w' => '300', 'crop' => '1' ) );
		$this->assertImageDimensions( $data, 300, 225 );
	}

	/**
	 * Test the image is resized to the dimensions of the specified "box".
	 */
	public function testImageResizing() {
		$data = $this->get_blogs_php_image( 'foto777-titolo.jpg', array( 'resize' => '100,100' ) );
		$this->assertImageDimensions( $data, 100, 100 );
	}

	/**
	 * Test the image is resized to fit into the specified "box".
	 */
	public function testImageFit() {
		$data = $this->get_blogs_php_image( 'kate-middleton-644.jpg', array( 'fit' => '80,80' ) );
		$this->assertImageDimensions( $data, 55, 80 );
	}

	/**
	 * Test crop at offsets and dimensions specified in percentages.
	 */
	public function testImageCropOffset() {
		$data = $this->get_blogs_php_image( 'kate-middleton-644.jpg', array( 'crop' => '25,5,40,40' ) );
		$this->assertImageDimensions( $data, 177, 258 );
	}

	/**
	 * If the requested width is out of bounds we return the original
	 * image, but we must still process the grayscale filter.
	 */
	public function testImageOversizedWidthRequest() {
		$data = $this->get_blogs_php_image( 'pasta-scampi-468.jpg', array( 'w' => '5000', 'filter' => 'grayscale' ) );
		$this->assertImageDimensions( $data, 468, 185 );
		$this->assertImageGrayscale( $data );
	}

	/**
	 * Ensure we have the correct dimensions after a chain of
	 * transformations and that the last filter is also applied.
	 */
	public function testImageArgumentChaining() {
		$data = $this->get_blogs_php_image(
			'kate-middleton-644.jpg',
			array( 'h' => '400', 'fit' => '200,200', 'crop' => '20px,20px,60,40', 'filter' => 'emboss,grayscale' )
		);
		$this->assertImageDimensions( $data, 83, 80 );
		$this->assertImageGrayscale( $data );
	}

	public function dataGrayscaleTransparentPNGStaysTransparentAfterWrite() {
		/* Both images are 1140 pixels wide. `w=1000` forces them to be rewritten */
		return array(
			array( 'recebeu.png', array( 'w' => '1000' ) ),
			array( 'oi.png', array( 'w' => '1000' ) ),
		);
	}

	/**
	 * Ensure transparent grayscale PNGs aren't silently demoted, losing transparency
	 *
	 * @dataProvider dataGrayscaleTransparentPNGStaysTransparentAfterWrite
	 */
	public function testGrayscaleTransparentPNGStaysTransparentAfterWrite( $image, $params ) {
		$data = $this->get_blogs_php_image( $image, $params );

		$image = imagecreatefromstring( $data );
		$first_pixel = imagecolorat( $image, 0, 0 );
		$first_pixel = imagecolorsforindex( $image, $first_pixel );

		$this->assertGreaterThan( 0, $first_pixel['alpha'] );
	}

	/**
	 * A jpeg's real quality is not only determined by it's quality value;
	 * there is more than one way of inferring it, ours isn't perfect and
	 * should not be used as an upper limit, as it sometimes introduces
	 * a significant amount of artifacts.
	 */
	public function testUserIsAllowedToOverrideQualityPropagation() {
		$image = 'low-quality-image-that-is-made-worse.jpg';

		$original_data = $this->get_blogs_php_image( $image );
		$original_image = imagecreatefromstring( $original_data );

		// The image is 600 pixels wide, we reduce w to force it to be processed
		$default_quality_data = $this->get_blogs_php_image( $image, array( 'w' => 599 ) );
		$default_quality_image = imagecreatefromstring( $default_quality_data );

		$max_quality_data = $this->get_blogs_php_image( $image, array( 'w' => 599, 'quality' => 100 ) );
		$max_quality_image = imagecreatefromstring( $max_quality_data );

		$error_from_default = $this->getSumOfSquarePixelDistances( $original_image, $default_quality_image, true );
		$error_from_max = $this->getSumOfSquarePixelDistances( $original_image, $max_quality_image, true );

		$this->assertLessThan( $error_from_default, $error_from_max );
	}

	public function dataBothImageLimitsAreEnforced() {
		/* Both images are above the 20000px limit in only one dimension */
		return array(
			array( 'too-tall.jpg', array( 'resize' => '100,100' ) ),
			array( 'too-wide.jpg', array( 'resize' => '100,100' ) ),
		);
	}

	/**
	 * Ensure images that are large in any dimension are rejected
	 *
	 * @dataProvider dataBothImageLimitsAreEnforced
	 */
	public function testBothImageLimitsAreEnforced( $image, $params ) {
		$data = $this->get_blogs_php_image( $image, $params );
		$this->assertRequestFailed( $data );
	}

	public function testRotationIsNotLostForWebpWithStrip() {
		$data = $this->get_blogs_php_image(
			'landscape-8-exif.jpg',
			array( 'w' => '500', 'strip' => 'all' ),
			array( 'ACCEPT' => 'image/jpeg' )
		);
		$this->assertImageFormat( $data, 'jpeg' );
		$this->assertImageDimensions( $data, 500, 750 );
		$this->assertRotation( $data, false );
	}

	public function testRotationMetadataIsPreservedForWebpWithoutStrip() {
		$data = $this->get_blogs_php_image(
			'landscape-8-exif.jpg',
			array( 'w' => '500', 'strip' => 'color' ),
			array( 'ACCEPT' => 'image/jpeg' )
		);
		$this->assertImageFormat( $data, 'jpeg' );
		$this->assertImageDimensions( $data, 500, 750 );
		$this->assertRotation( $data, false );
	}

	public function dataPercentageSignInSizeIsIgnored() {
		return array(
			array( 'foto777-titolo.jpg', array( 'w' => '50%' ), 50, 37 ),
			array( 'foto777-titolo.jpg', array( 'h' => '50%' ), 66, 50 ),
		);
	}

	/**
	 * Ensures widths and heights such as 50% are intepreted as 50px
	 *
	 * @dataProvider dataPercentageSignInSizeIsIgnored
	 */
	public function testPercentageSignInSizeIsIgnored( $image, $params, $width, $height ) {
		$data = $this->get_blogs_php_image( $image, $params );
		$this->assertImageDimensions( $data, $width, $height );
	}

	public function dataBasicLetterboxing() {
		return array(
			array( 'foto777-titolo.jpg', array( 'lb' => '300,350' ), array( 62, 63, 0, 0 ) ),
			array( 'foto777-titolo.jpg', array( 'lb' => '300,349' ), array( 62, 62, 0, 0 ) ),
			array( 'foto777-titolo.jpg', array( 'lb' => '196,147' ), array( 0, 0, 0, 0 ) ),
			array( 'kate-middleton-644.jpg', array( 'lb' => '300,1200' ), array( 382, 382, 0, 0 ) ),
			array( 'kate-middleton-644.jpg', array( 'lb' => '600,350' ), array( 0, 0, 179, 180 ) ),
		);
	}

	/** Tests the basic letterboxing functionality
	 *
	 * @dataProvider dataBasicLetterboxing
	 */
	public function testBasicLetterboxing( $image, $params, $expected_black_borders ) {
		$data = $this->get_blogs_php_image( $image, $params );
		$black_borders = $this->calculateBlackBorders( $data );
		$this->assertEquals( $expected_black_borders , $black_borders );
	}

	public function dataBadExifDataCanBeStrippedFromPNG() {
		return array(
			array( 'broken-exif.png', array( 'strip' => 'all' ) ),
			array( 'broken-exif.png', array( 'strip' => 'all', 'w' => 100 ) ),
			array( 'broken-exif.png', array( 'strip' => 'info' ) ),
			array( 'broken-exif.png', array( 'strip' => 'info', 'w' => 100 ) ),
			array( 'broken-exif.png', array( 'strip' => 'color' ) ),
			array( 'broken-exif.png', array( 'strip' => 'color', 'w' => 100 ) ),
		);
	}

	/** Tests that strip=all works with PNG files, including when they have
	 * bad data. This only works with optipng
	 *
	 * @dataProvider dataBadExifDataCanBeStrippedFromPNG
	 */
	public function testBadExifDataCanBeStrippedFromPNG( $image, $params ) {
		$data = $this->get_blogs_php_image( $image, $params );
		$chunks = $this->getPNGChunks( $data );
		$this->assertNotContains( 'eXIf', $chunks );
	}

	public function dataLargeGifsAreRejectedAccordingToMemoryLimit() {
		return array(
			array( 'small.gif', array( 'w' => 127 ), 50 * 2**20, '512M', true ),
			array( 'small.gif', array( 'w' => 127 ), 50 * 2**20, '128M', false ),
			array( 'small.gif', array( 'w' => 127 ), 50 * 2**20, '-1', true ),
		);
	}

	/**
	 * Ensures gifs that are likely to fatal during processing are rejected early
	 *
	 * @dataProvider dataLargeGifsAreRejectedAccordingToMemoryLimit
	 */
	public function testLargeGifsAreRejectedAccordingToMemoryLimit( $image, $params, $num_appended_bytes, $mem_limit, $should_be_processed ) {
		$data = $this->get_blogs_php_image( $image, $params, array(), array( "-dmemory_limit=$mem_limit" ), $num_appended_bytes );
		if ( $should_be_processed ) {
			$this->assertStringStartsWith( 'GIF', $data );
		} else {
			$this->assertStringStartsNotWith( 'GIF', $data );
		}
	}
}
