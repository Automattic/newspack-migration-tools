<?php
/**
 * Generator for Gutenberg Blocks content.
 *
 * To add a new method to this class that generates a Gutenberg Block, please follow these instructions:
 *     - Create a draft post in Gutenberg and add the desired block.
 *     - Copy the source HTML of your block (from the code editor) and pass it to the `get_block_json_array_from_content` method.
 *     - Transform the JSON result to a PHP array using a tool like https://wtools.io/convert-json-to-php-array
 *     - The newly created method should return this PHP array.
 *     - Add method parameters as necessary to customize the resulting array.
 *     - The idea is to use the output of your method as an input to the serialize_block(s) to get the HTML content of the block.
 *
 * @package GutenbergBlockGenerator
 */

namespace NewspackCustomContentMigrator\Logic;

use \NewspackCustomContentMigrator\Utils\Logger;
use \WP_CLI;

/**
 * Class ContentDiffMigrator and main logic.
 *
 * @package NewspackCustomContentMigrator\Logic
 */
class GutenbergBlockGenerator {
    /**
	 * @var Logger.
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

    /**
     * Generate a Jetpack Tiled Gallery Block.
     *
     * @param int[]    $attachment_ids Attachments IDs to be used in the tiled gallery.
     * @param string[] $tile_sizes_list List of tiles sizes in percentages (e.g. ['50', '50']).
     * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
     */
    public function get_jetpack_tiled_gallery( $attachment_ids, $tile_sizes_list = [ '66.79014', '33.20986', '33.33333', '33.33333', '33.33333', '50.00000', '50.00000' ] ) {
        $gallery_content = '
        <div class="wp-block-jetpack-tiled-gallery aligncenter is-style-rectangular">
            <div class="tiled-gallery__gallery">
                <div class="tiled-gallery__row">
        ';

        $tile_sizes                      = [];
        $non_existing_attachment_indexes = [];

		$gallery_content .= join(
            ' ',
            array_filter(
                array_map(
                    function( $index, $attachment_id ) use ( &$tile_sizes, &$non_existing_attachment_indexes, $tile_sizes_list ) {
                        $attachment_url = wp_get_attachment_url( $attachment_id );

                        if ( ! $attachment_url ) {
                            $non_existing_attachment_indexes[] = $index;
                            $this->logger->log( 'jetpack_tiled_gallery_migrator.log', sprintf( "Attachment %d doesn't exist!", $attachment_id ), Logger::WARNING );
                            return null;
                        }

                        $tile_size    = $this->get_tile_image_size_by_index( $index, $tile_sizes_list );
                        $tile_sizes[] = $tile_size;
                        return '
                            <div class="tiled-gallery__col" style="flex-basis: ' . $tile_size . '%">
                            <figure class="tiled-gallery__item">
                                <img
                                        alt="' . get_the_title( $attachment_id ) . '"
                                        data-id="' . $attachment_id . '"
                                        data-link="' . $attachment_url . '"
                                        data-url="' . $attachment_url . '"
                                        src="' . $attachment_url . '"
                                    data-amp-layout="responsive"
                                />
                            </figure>
                            </div>';
                    },
                    array_keys( $attachment_ids ),
                    $attachment_ids
                )
            )
		);

		$gallery_content .= '
                </div>
            </div>
        </div>
        ';

        // delete unexisting attachments.
        foreach ( $non_existing_attachment_indexes as $non_existing_attachment_index ) {
            unset( $attachment_ids[ $non_existing_attachment_index ] );
        }

		return [
			'blockName'    => 'jetpack/tiled-gallery',
			'attrs'        => [
				'columnWidths' => [], // It will be filled after fixing the block manually from the backend.
				'ids'          => $attachment_ids,
			],
			'innerBlocks'  => [],
			'innerHTML'    => $gallery_content,
			'innerContent' => [ $gallery_content ],
        ];
    }

    /**
     * Generate a Jetpack Slideshow Block.
     *
     * @param int[]     $attachment_ids Attachments IDs to be used in the tiled gallery.
     * @param string    $transition Slideshow transition (slide or fade).
     * @param int|false $autoplay False de disable, on the delay in seconds.
     * @param string    $image_size Image size (thumbnail, medium, large, full).
     * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
     */
    public function get_jetpack_slideshow( $attachment_ids, $transition = 'slide', $autoplay = false, $image_size = 'large' ) {
        $data_autoplay = is_numeric( $autoplay ) ? 'data-autoplay="true" data-delay="' . $autoplay . '"' : '';
        $data_effect   = 'data-effect="' . $transition . '"';

        $attachment_posts = [];
		foreach ( $attachment_ids as $attachment_id ) {
            $attachment_post = get_post( $attachment_id );
            if ( ! $attachment_post ) {
                $this->logger->log( 'jetpack_slideshow_migrator.log', sprintf( "Attachment %d doesn't exist!", $attachment_id ), Logger::WARNING );
                continue;
            }

			$attachment_posts[] = $attachment_post;
		}

        $slideshow_content = '
            <div class="wp-block-jetpack-slideshow aligncenter" ' . $data_autoplay . ' ' . $data_effect . '>
                <div class="wp-block-jetpack-slideshow_container swiper-container">
                    <ul class="wp-block-jetpack-slideshow_swiper-wrapper swiper-wrapper">
        ';

        foreach ( $attachment_posts as $attachment_post ) {
            $caption = ! empty( $attachment_post->post_excerpt ) ? $attachment_post->post_excerpt : $attachment_post->post_title;

            $slideshow_content .= '<li class="wp-block-jetpack-slideshow_slide swiper-slide">
            <figure>
            <img alt="' . $attachment_post->post_title . '" class="wp-block-jetpack-slideshow_image wp-image-' . $attachment_post->ID . '" data-id="' . $attachment_post->ID . '" src="' . wp_get_attachment_url( $attachment_post->ID ) . '"/>
            <figcaption class="wp-block-jetpack-slideshow_caption gallery-caption">' . $caption . '</figcaption>
            </figure>
            </li>';
        }

        $slideshow_content .= '</ul>
                    <a class="wp-block-jetpack-slideshow_button-prev swiper-button-prev swiper-button-white" role="button"></a>
                    <a class="wp-block-jetpack-slideshow_button-next swiper-button-next swiper-button-white" role="button"></a>
                    <a aria-label="Pause Slideshow" class="wp-block-jetpack-slideshow_button-pause" role="button"></a>
                    <div class="wp-block-jetpack-slideshow_pagination swiper-pagination swiper-pagination-white"></div>
                </div>
            </div>';
        return [
            'blockName'    => 'jetpack/slideshow',
            'attrs'        => [
                'ids'      => $attachment_ids,
                'sizeSlug' => $image_size,
                'effect'   => $transition,
                'autoplay' => $autoplay,
            ],
            'innerBlocks'  => [],
            'innerHTML'    => $slideshow_content,
            'innerContent' => [ $slideshow_content ],
        ];
    }

    /**
     * Generate tile size based on its index.
     *
     * @param int      $index Tile size.
     * @param string[] $tile_sizes_list Tile sizes list in percentage (e.g. [ '66.79014', '33.20986', '33.33333', '33.33333', '33.33333', '50.00000', '50.00000' ]).
     * @return string
     */
	private function get_tile_image_size_by_index( $index, $tile_sizes_list ) {
		return $tile_sizes_list[ $index % count( $tile_sizes_list ) ];
    }

    /**
     * Helper function used for getting the PHP array needed to generate a block.
     * To be used the first time we're adding a new method to generate a block, to get the right array attributes.
     *
     * @param string $content content of one Gutenberg Block.
     * @return string a JSON encoded array of the Gutenberg Block.
     */
    public function get_block_json_array_from_content( $content ) {
        return json_encode( current( parse_blocks( $content ) ) ); // phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
    }
}
