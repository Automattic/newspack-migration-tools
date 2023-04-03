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
	 * Generate a Jetpack Slideshow Block.
	 *
	 * @param int[]   $attachment_ids Attachments IDs to be used in the tiled gallery.
	 * @param int     $images_per_row Images per row.
	 * @param string  $image_size Image size (thumbnail, medium, large, full), full by default.
	 * @param string  $image_link_to Link images to `none`, `media`, or to `attachment`.
	 * @param boolean $crop_images To crop the gallery images or not.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_gallery( $attachment_ids, $images_per_row = 3, $image_size = 'full', $image_link_to = 'none', $crop_images = false ) {
		$attachment_posts = [];
		$image_blocks     = [];
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_post = get_post( $attachment_id );
			if ( ! $attachment_post ) {
				$this->logger->log( 'gallery_migrator.log', sprintf( "Attachment %d doesn't exist!", $attachment_id ), Logger::WARNING );
				continue;
			}

			$attachment_posts[] = $attachment_post;
		}

		foreach ( $attachment_posts as $attachment_post ) {
			$image_blocks[] = $this->get_image( $attachment_post );
		}

		// Inner content.
		$inner_content = array_fill( 1, count( $attachment_posts ), null );
		array_unshift( $inner_content, '<figure class="wp-block-gallery has-nested-images columns-' . $images_per_row . '">' );
		array_push( $inner_content, '</figure>' );

		return [
			'blockName'    => 'core/gallery',
			'attrs'        => [
				'columns'   => $images_per_row,
				'imageCrop' => $crop_images,
				'linkTo'    => $image_link_to,
				'sizeSlug'  => $image_size,
			],
			'innerBlocks'  => $image_blocks,
			'innerHTML'    => '<figure class="wp-block-gallery has-nested-images columns-' . $images_per_row . '"></figure>',
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Generate a List Block item.
	 *
	 * @param \WP_Post $attachment_post Image Post.
	 * @param string   $size Image size, full by default.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_image( $attachment_post, $size = 'full' ) {
		$caption_tag = ! empty( $attachment_post->post_excerpt ) ? '<figcaption class="wp-element-caption">' . $attachment_post->post_excerpt . '</figcaption>' : '';
		$image_alt   = get_post_meta( $attachment_post->ID, '_wp_attachment_image_alt', true );

		$attrs = [
			'sizeSlug' => $size,
		];

		$content = '<figure class="wp-block-image size-' . $size . '"><img src="' . wp_get_attachment_url( $attachment_post->ID ) . '" alt="' . $image_alt . '"/>' . $caption_tag . '</figure>';

		return [
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate a Header Block.
	 *
	 * @param string $heading_content Paragraph content.
	 * @param string $heading_level Heading level (h1, h2, h3, h4, h5, h6), defaults to h2.
	 * @param string $anchor Paragraph anchor.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_heading( $heading_content, $heading_level = 'h2', $anchor = '' ) {
		$anchor_attribute = ! empty( $anchor ) ? ' id="' . $anchor . '"' : '';
		$content          = "<$heading_level" . $anchor_attribute . '>' . $heading_content . "</$heading_level>";
		return [
			'blockName'    => 'core/heading',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate a Paragraph Block.
	 *
	 * @param string $paragraph_content Paragraph content.
	 * @param string $anchor Paragraph anchor.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_paragraph( $paragraph_content, $anchor = '' ) {
		$anchor_attribute = ! empty( $anchor ) ? ' id="' . $anchor . '"' : '';
		$content          = '<p' . $anchor_attribute . '>' . $paragraph_content . '</p>';

		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate a Quote Block.
	 *
	 * @param string $quote_content Quote content.
	 * @param string $cite_content Quote cite.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_quote( $quote_content, $cite_content = '' ) {
		$content = '<p>' . $quote_content . '</p>';
		$cite    = ! empty( $cite_content ) ? "<cite>$cite_content</cite>" : '';

		return [
			'blockName'    => 'core/quote',
			'attrs'        => [],
			'innerBlocks'  => [
				[
					'blockName'    => 'core/paragraph',
					'attrs'        => [],
					'innerBlocks'  => [],
					'innerHTML'    => $content,
					'innerContent' => [ $content ],
				],
			],
			'innerHTML'    => '<blockquote class="wp-block-quote">' . $cite . '</blockquote>',
			'innerContent' => [
				'<blockquote class="wp-block-quote">',
				null,
				$cite . '</blockquote>',
			],
		];
	}

	/**
	 * Generate a HTML Block.
	 *
	 * @param string $html_content HTML content.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_html( $html_content ) {
		return [
			'blockName'    => 'core/html',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $html_content,
			'innerContent' => [ $html_content ],
		];
	}

	/**
	 * Generate a PDF Viewer Block (Gutenberg PDF Viewer Block plugin should be installed).
	 *
	 * @param int    $pdf_media_id PDF Media ID.
	 * @param string $pdf_url PDF URL if known.
	 * @param string $width PDF Block width, empty by default (which set it to 100%, plugin's default).
	 * @param string $height PDF Block height, empty by default (which set it to 700px, plugin's default).
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_pdf( $pdf_media_id, $pdf_url = '', $width = '', $height = '' ) {
		$pdf_url = ! empty( $pdf_url ) ? $pdf_url : wp_get_attachment_url( $pdf_media_id );
		$content = '<div class="wp-block-pdf-viewer-block-standard" style="text-align:left"><div class="uploaded-pdf"><a href="' . $pdf_url . '" data-width="' . $width . '" data-height="' . $height . '"></a></div></div>';

		return [
			'blockName'    => 'pdf-viewer-block/standard',
			'attrs'        => [
				'mediaID' => $pdf_media_id,
			],
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate a Featured Image Block.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_featured_image() {
		return [
			'blockName'    => 'core/post-featured-image',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Generate a Site Logo Block.
	 *
	 * @param int $width Logo width (in pixels).
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_site_logo( $width = 0 ) {
		$attrs = [];
		if ( 0 !== $width ) {
			$attrs['width'] = $width;
		}
		return [
			'blockName'    => 'core/site-logo',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Generate a Separator Block.
	 *
	 * @param string $style Separator style (is-style-default, is-style-dots, is-style-wide), empty is by default.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_separator( $style = '' ) {
		$attrs      = [];
		$classnames = [ 'wp-block-separator', 'has-alpha-channel-opacity' ];

		if ( ! empty( $style ) ) {
			$attrs['className'] = $style;
			$classnames[]       = $style;
		}

		$content = '<hr class="' . join( ' ', $classnames ) . '"/>';

		return [
			'blockName'    => 'core/separator',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate Newspack Author Profile Block.
	 *
	 * @param int     $author_id Author ID.
	 * @param boolean $show_email Show author email.
	 * @param boolean $show_avatar Show author avatar.
	 * @param boolean $show_newspack_job_title Show author job title.
	 * @param boolean $is_guest_author If the author is a guest author.
	 * @param boolean $show_bio Show author bio.
	 * @param boolean $show_social Show author bio.
	 * @param boolean $show_archive_link Show author link.
	 * @param boolean $show_newspack_employer Show author employer.
	 * @param boolean $show_newspack_phone_number Show author phone number.
	 * @param boolean $show_newspack_role Show author role.
	 * @return array
	 */
	public function get_author_profile( $author_id, $show_email = false, $show_avatar = false, $show_newspack_job_title = false, $is_guest_author = false, $show_bio = false, $show_social = false, $show_archive_link = false, $show_newspack_employer = false, $show_newspack_phone_number = false, $show_newspack_role = false ) {
		return [
			'blockName'    => 'newspack-blocks/author-profile',
			'attrs'        => [
				'authorId'                  => $author_id,
				'isGuestAuthor'             => $is_guest_author,
				'showBio'                   => $show_bio,
				'showEmail'                 => $show_email,
				'showAvatar'                => $show_avatar,
				'showSocial'                => $show_social,
				'showArchiveLink'           => $show_archive_link,
				'shownewspack_role'         => $show_newspack_role,
				'shownewspack_job_title'    => $show_newspack_job_title,
				'shownewspack_employer'     => $show_newspack_employer,
				'shownewspack_phone_number' => $show_newspack_phone_number,
			],
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Generate a Columns Block.
	 *
	 * @param array  $columns Columns list.
	 * @param string $style Columns style (is-style-default, is-style-borders, is-style-first-col-to-second, is-style-first-col-to-third).
	 * @param string $is_stacked_on_mobile If the columns should be stacked on mobile, true by default.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_columns( $columns, $style = '', $is_stacked_on_mobile = true ) {
		$attrs      = [];
		$classnames = [ 'wp-block-columns' ];

		if ( ! $is_stacked_on_mobile ) {
			$attrs['isStackedOnMobile'] = false;
			$classnames[]               = 'is-not-stacked-on-mobile';
		}

		if ( ! empty( $style ) ) {
			$attrs['className'] = $style;
			$classnames[]       = $style;
		}

		// Inner content.
		$inner_content = array_fill( 1, count( $columns ), null );
		array_unshift( $inner_content, '<div class="wp-block-columns">' );
		array_push( $inner_content, '</div>' );

		return [
			'blockName'    => 'core/columns',
			'attrs'        => $attrs,
			'innerBlocks'  => $columns,
			'innerHTML'    => '<div class="' . join( ' ', $classnames ) . '"></div>',
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Generate a Column Block.
	 *
	 * @param array  $blocks Blocks list.
	 * @param string $width Column width (e.g. 100px, 50%, 100em, 50rem, 50vw). If empty the columns sizes will be even.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_column( $blocks, $width = '' ) {
		$attrs  = [];
		$styles = [];

		if ( ! empty( $width ) ) {
			$attrs['width'] = $width;
			$styles[]       = "flex-basis:$width";
		}

		$styles_attribute = ! empty( $styles ) ? ' style="' . implode( ' ', $styles ) . '"' : '';

		// Inner content.
		$inner_content = array_fill( 1, count( $blocks ), null );
		array_unshift( $inner_content, '<div class="wp-block-column"' . $styles_attribute . '>' );
		array_push( $inner_content, '</div>' );

		return [
			'blockName'    => 'core/column',
			'attrs'        => $attrs,
			'innerBlocks'  => $blocks,
			'innerHTML'    => '<div class="wp-block-column"' . $styles_attribute . '></div>',
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Generate a List Block.
	 *
	 * @param array   $elements List elements.
	 * @param boolean $ordered If the list is ordered. False by default.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_list( $elements, $ordered = false ) {
		$attrs = [];

		if ( $ordered ) {
			$attrs['ordered'] = true;
		}

		// Inner content.
		$inner_content = array_fill( 1, count( $elements ), null );
		array_unshift( $inner_content, '<ol>' );
		array_push( $inner_content, '</ol>' );

		return [
			'blockName'    => 'core/list',
			'attrs'        => $attrs,
			'innerBlocks'  => array_map( [ $this, 'get_list_item' ], $elements ),
			'innerHTML'    => '<ol></ol>',
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Generate a List Block item.
	 *
	 * @param string $content Item content.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_list_item( $content ) {
		$item = '<li>' . $content . '</li>';

		return [
			'blockName'    => 'core/list-item',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $item,
			'innerContent' => [ $item ],
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
