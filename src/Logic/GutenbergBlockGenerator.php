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
use WP_Post;

/**
 * Class ContentDiffMigrator and main logic.
 *
 * @package NewspackCustomContentMigrator\Logic
 */
class GutenbergBlockGenerator {
	/**
	 * Logger class.
	 *
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
	 * Notes:
	 *      - after updating the Posts, it is necessary to manually open the post in the editor, click "Attempt recovery", and click "Update" to get the correct HTML
	 *      - at the time of writing this, JP Tiled Gallery doesn't support captions
	 *
	 * @param int[]    $attachment_ids Attachments IDs to be used in the tiled gallery.
	 * @param ?string  $link_to         `linkTo` attribute of the Jetpack Tiled Gallery block. Can be null, or "media", or "attachment".
	 * @param string[] $tile_sizes_list List of tiles sizes in percentages (e.g. ['50', '50']).
	 *
	 * @throws \UnexpectedValueException If $link_to param is invalid.
	 *
	 * @return array to be used in the serialize_block() or serialize_blocks() function to get the raw content of a Gutenberg Block.
	 */
	public function get_jetpack_tiled_gallery( $attachment_ids, $link_to = null, $tile_sizes_list = [ '66.79014', '33.20986', '33.33333', '33.33333', '33.33333', '50.00000', '50.00000' ] ) {
		// Validate $link_to param.
		if ( ! in_array( $link_to, [ 'media', 'attachment' ] ) ) {
			throw new \UnexpectedValueException( 'Invalid $link_to param value.' );
		}

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

		$attrs = [
			'columnWidths' => [], // It will be filled after fixing the block manually from the backend.
			'ids'          => $attachment_ids,
		];
		if ( $link_to ) {
			$attrs['linkTo'] = $link_to;
		}

		return [
			'blockName'    => 'jetpack/tiled-gallery',
			'attrs'        => $attrs,
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
			/**
			 * In order for 'linkTo' attribute to work, it's not enough to add this as attribute to the block, but also the image blocks need to get the surrounding <a> element.
			 * More here: https://wordpress.com/support/wordpress-editor/blocks/gallery-block/#link-settings
			 */
			$link_to_attachment_url = 'none' !== $image_link_to ? true : false;
			$image_blocks[]         = $this->get_image( $attachment_post, 'full', $link_to_attachment_url );
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
	 * Generate an Image Block item.
	 *
	 * @param \WP_Post $attachment_post        Image Post.
	 * @param string   $size                   Image size, full by default.
	 * @param bool     $link_to_attachment_url Whether to link to the attachment URL or not.
	 * @param string   $classname              Media HTML class.
	 * @param string   $align                  Image alignment (left, right).
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_image( $attachment_post, $size = 'full', $link_to_attachment_url = true, $classname = null, $align = null ) {
		// Validate size.
		if ( ! in_array( $size, [ 'thumbnail', 'medium', 'large', 'full' ] ) ) {
			$size = 'full';
		}

		$caption_tag   = ! empty( $attachment_post->post_excerpt ) ? '<figcaption class="wp-element-caption">' . $attachment_post->post_excerpt . '</figcaption>' : '';
		$image_alt     = get_post_meta( $attachment_post->ID, '_wp_attachment_image_alt', true );
		$image_url     = wp_get_attachment_image_src( $attachment_post->ID, $size )[0];
		$attachment_id = intval( $attachment_post->ID );

		$attrs = [
			'id'       => $attachment_id,
			'sizeSlug' => $size,
		];

		// Add <a> link to attachment URL.
		$a_opening_tag = '';
		$a_closing_tag = '';
		if ( $link_to_attachment_url ) {
			$a_opening_tag = sprintf( '<a href="%s">', $image_url );
			$a_closing_tag = '</a>';
		}

		if ( $classname ) {
			$attrs['className'] = $classname;
		}

		if ( $align ) {
			$attrs['align'] = $align;
		}

		$figure_class = 'wp-block-image size-' . $size . ( $classname ? " $classname" : '' ) . ( $align ? " align$align" : '' );

		$content = '<figure class="' . $figure_class . '">' . $a_opening_tag . '<img src="' . $image_url . '" alt="' . $image_alt . '" class="wp-image-' . $attachment_id . '"/>' . $a_closing_tag . $caption_tag . '</figure>';

		return [
			'blockName'    => 'core/image',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Get a video block.
	 *
	 * @param WP_Post $attachment_post
	 *
	 * @return array
	 */
	public function get_video( WP_Post $attachment_post ): array {
		$video_url = wp_get_attachment_url( $attachment_post->ID );
		$content   = <<<VIDEO
<figure class="wp-block-video"><video controls src="$video_url"></video></figure>
VIDEO;
		return [
			'blockName'    => 'core/video',
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Generate a File Block item with a PDF preview.
	 *
	 * @param \WP_Post $attachment_post        File Post.
	 * @param string   $title                  File title.
	 * @param bool     $show_download_button   Whether to show the download button or not.
	 * @param int      $height                 PDF Block height in px, empty by default (which set it to 800px, plugin's default).
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_file_pdf( $attachment_post, $title = '', $show_download_button = true, $height = 800 ) {
		$attachment_url = wp_get_attachment_url( $attachment_post->ID );
		$uuid           = $this->format_uuidv4();
		$title          = empty( $title ) ? $attachment_post->post_title : $title;

		$attrs = [
			'id'             => $attachment_post->ID,
			'href'           => $attachment_url,
			'displayPreview' => true,
			'previewHeight'  => $height,
		];

		$download_button = $show_download_button ? '<a href="' . $attachment_url . '" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-' . $uuid . '">Download</a>' : '';

		$inner_html = '<div class="wp-block-file"><object class="wp-block-file__embed" data="' . $attachment_url . '" type="application/pdf" style="width:100%;height:' . $height . 'px" aria-label="' . $title . '"></object><a id="wp-block-file--media-' . $uuid . '" href="' . $attachment_url . '">' . $title . '</a>' . $download_button . '</div>';

		return [
			'blockName'    => 'core/file',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => $inner_html,
			'innerContent' => [ $inner_html ],
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
		$attrs = [];
		$level = intval( str_replace( 'h', '', $heading_level ) );

		if ( $level > 2 ) {
			$attrs['level'] = $level;
		}

		$anchor_attribute = ! empty( $anchor ) ? ' id="' . $anchor . '"' : '';
		$content          = "<$heading_level" . $anchor_attribute . ' class="wp-block-heading">' . $heading_content . "</$heading_level>";
		return [
			'blockName'    => 'core/heading',
			'attrs'        => $attrs,
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
	 * @param string $text_color Paragraph text color (black, blue, green, red, yellow, gray, dark-gray, medium-gray, light-gray, white).
	 * @param string $font_size Paragraph font size (small, normal, medium, large, huge).
	 * @param array  $additional_css_classes Additional paragraph classes.
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_paragraph( $paragraph_content, $anchor = '', $text_color = '', $font_size = '', array $additional_css_classes = [] ) {

		// Paragraph can have both <p class=""> classes, and <!-- wp:paragraph {"className":""} --> className attributes (called "Additional CSS classes" in Gutenberg).
		$paragraph_element_classes = [];
		$attrs                     = [];
		if ( ! empty( $text_color ) ) {
			$paragraph_element_classes[] = 'has-' . $text_color . '-color has-text-color';
			$attrs['fontSize']           = $text_color;
		}
		if ( ! empty( $font_size ) ) {
			$paragraph_element_classes[] = 'has-' . $font_size . '-font-size';
			$attrs['fontSize']           = $font_size;
		}

		// Add additional CSS classes to <p> and Block.
		if ( ! empty( $additional_css_classes ) ) {
			$paragraph_element_classes = array_merge( $paragraph_element_classes, $additional_css_classes );
			$attrs['className']        = implode( ' ', $additional_css_classes );
		}

		$paragraph_element_class_string = ! empty( $paragraph_element_classes ) ? ' class="' . implode( ' ', $paragraph_element_classes ) . '"' : '';

		$anchor_attribute = ! empty( $anchor ) ? ' id="' . $anchor . '"' : '';
		$content          = '<p' . $anchor_attribute . $paragraph_element_class_string . '>' . $paragraph_content . '</p>';

		return [
			'blockName'    => 'core/paragraph',
			'attrs'        => $attrs,
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
	 * Generate a Group Block with the constrained layout.
	 * Since Group block can have three different layouts with different markup and behavior, splitting these into separate methods.
	 *
	 * @param array $inner_blocks   Inner blocks.
	 * @param array $custom_classes Custom classes to be added to the group block.
	 * @param array $attrs          Attributes to be added to the group block.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_group_constrained( $inner_blocks, $custom_classes = [], $attrs = [] ) {

		$class_append_custom = ! empty( $custom_classes ) ? implode( ' ', $custom_classes ) : '';

		$inner_content   = [];
		$inner_content[] = ' <div class="wp-block-group' . ( ! empty( $class_append_custom ) ? ' ' . implode( ' ', $custom_classes ) : '' ) . '">';
		$inner_content   = array_merge( $inner_content, array_fill( 1, count( $inner_blocks ), null ) );
		$inner_content[] = '</div> ';

		if ( ! empty( $custom_classes ) ) {
			$attrs['className'] = implode( ' ', $custom_classes );
		}
		$attrs = array_merge(
			$attrs,
			[
				'layout' => [
					'type' => 'constrained',
				],
			]
		);

		return [
			'blockName'    => 'core/group',
			'attrs'        => $attrs,
			'innerBlocks'  => $inner_blocks,
			'innerHTML'    => ' <div class="wp-block-group' . ( ! empty( $class_append_custom ) ? ' ' . implode( ' ', $custom_classes ) : '' ) . '">  </div> ',
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

		$list_tag = $ordered ? 'ol' : 'ul';

		// Inner content.
		$inner_content = array_fill( 1, count( $elements ), null );
		array_unshift( $inner_content, '<' . $list_tag . '>' );
		array_push( $inner_content, '</' . $list_tag . '>' );

		return [
			'blockName'    => 'core/list',
			'attrs'        => $attrs,
			'innerBlocks'  => array_map( [ $this, 'get_list_item' ], $elements ),
			'innerHTML'    => '<' . $list_tag . '></' . $list_tag . '>',
			'innerContent' => $inner_content,
		];
	}

	/**
	 * Generate a Youtube block -- uses core/embed.
	 *
	 * @param string $src     YT src.
	 * @param string $caption Optional.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_youtube( $src, $caption = '' ) {
		// Remove GET params from $src, otherwise the embed might not work.
		$src_parsed  = wp_parse_url( $src );
		$src_cleaned = $src_parsed['scheme'] . '://' . $src_parsed['host'] . $src_parsed['path'];

		return $this->get_core_embed( $src_cleaned, $caption );
	}

	/**
	 * Generate a Vimeo block -- uses core/embed.
	 *
	 * @param string $src     Vimeo src.
	 * @param string $caption Optional.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_vimeo( $src, $caption = '' ) {
		return $this->get_core_embed( $src, $caption );
	}

	/**
	 * Generate a Vimeo block -- uses core/embed.
	 *
	 * @param string $src     Vimeo src.
	 * @param string $caption Optional.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_twitter( $src, $caption = '' ) {
		return [
			'blockName'    => 'core/embed',
			'attrs'        => [
				'url'              => $src,
				'type'             => 'rich',
				'providerNameSlug' => 'twitter',
				'responsive'       => true,
			],
			'innerBlocks'  => [],
			'innerHTML'    => ' <figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper"> ' . $src . ' </div></figure> ',
			'innerContent' => [
				0 => ' <figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper"> ' . $src . ' </div></figure> ',
			],
		];
	}

	/**
	 * Generate a core/embed.
	 *
	 * @param string $src     Src.
	 * @param string $caption Optional.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_core_embed( $src, $caption = '' ) {
		return [
			'blockName'    => 'core/embed',
			'attrs'        => [
				'url'              => $src,
				'type'             => 'rich',
				'providerNameSlug' => 'embed-handler',
				'responsive'       => true,
				'className'        => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
			],
			'innerBlocks'  => [],
			'innerHTML'    => ' <figure class="wp-block-embed is-type-rich is-provider-embed-handler wp-block-embed-embed-handler wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper"> ' . $src . ' </div></figure> ',
			'innerContent' => [
				0 => ' <figure class="wp-block-embed is-type-rich is-provider-embed-handler wp-block-embed-embed-handler wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper"> ' . $src . ' </div></figure> ',
			],
		];
	}

	/**
	 * Generate a Facebook block (which is visible in menu only after Jetpack connection is registered),
	 * which uses core/embed in background.
	 *
	 * @param string $src     Facebook src.
	 * @param string $caption Optional.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_facebook( $src, $caption = '' ) {
		return $this->get_core_embed( $src, $caption );
	}

	/**
	 * Generate a Newspack Iframe block.
	 *
	 * @param string $src URL.
	 * @param string $width Width.
	 * @param string $height Height.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_iframe( $src, $width = null, $height = null ) {
		$attrs = [
			'src' => $src,
		];

		if ( $width ) {
			$attrs['width'] = $width;
		}

		if ( $height ) {
			$attrs['height'] = $height;
		}

		return [
			'blockName'    => 'newspack-blocks/iframe',
			'attrs'        => $attrs,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Generate a Genesis Accordion Block.
	 *
	 * WARNING: To use this block we need to install Genesis Blocks: https://wordpress.org/plugins/genesis-blocks/.
	 *
	 * @param string  $title Accordion title.
	 * @param string  $body Accordion body content.
	 * @param boolean $use_html_block If the content shoulb have a html block as parent, if not it defaults to paragraph.
	 * @param boolean $open If the accordion is open by default, defaults to false.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	public function get_accordion( $title, $body, $use_html_block = false, $open = false ) {
		$inner_block = $use_html_block ? $this->get_html( $body ) : $this->get_paragraph( $body );

		$attrs = $open ? [ 'accordionOpen' => $open ] : [];
		return [
			'blockName'    => 'genesis-blocks/gb-accordion',
			'attrs'        => $attrs,
			'innerBlocks'  => [ $inner_block ],
			'innerHTML'    => '<div class="wp-block-genesis-blocks-gb-accordion gb-block-accordion"><details><summary class="gb-accordion-title">' . $title . '</summary><div class="gb-accordion-text"></div></details></div>',
			'innerContent' => [
				'<div class="wp-block-genesis-blocks-gb-accordion gb-block-accordion"><details><summary class="gb-accordion-title">' . $title . '</summary><div class="gb-accordion-text">',
				null,
				'</div></details></div>',
			],
		];
	}

	/**
	 * Generate a List Block item.
	 *
	 * @param string $content Item content.
	 *
	 * @return array to be used in the serialize_blocks function to get the raw content of a Gutenberg Block.
	 */
	private function get_list_item( $content ) {
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
	 * Generate a UUId v4.
	 *
	 * @param string $data Data to be used to generate the UUID v4.
	 *
	 * @return string UUID v4.
	 */
	private function format_uuidv4( $data = null ) {
		$data = $data ?? random_bytes( 16 );
		assert( strlen( $data ) == 16 );

		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100.
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10.

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
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

	/**
	 * Generate a Newspack Homepage Articles Block for categories.
	 *
	 * @param array $category_ids array of category IDs.
	 * @param array $args args to pass to the block.
	 *
	 * @return array
	 */
	public function get_homepage_articles_for_category( array $category_ids, array $args ): array {
		if ( empty( $category_ids ) ) {
			return [];
		}
		$args['categories'] = $category_ids;

		if ( empty( $args['postsToShow'] ) ) {
			// Enforce a sane default if the value is not passed.
			$args['postsToShow'] = 16;
		}

		return [
			'blockName'    => 'newspack-blocks/homepage-articles',
			'attrs'        => $args,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

	/**
	 * Generate a Newspack Homepage Articles Block with specific posts.
	 *
	 * @param array $post_ids array of post IDs.
	 * @param array $args args to pass to the block.
	 *
	 * @return array
	 */
	public function get_homepage_articles_for_specific_posts( array $post_ids, array $args ): array {
		if ( empty( $post_ids ) ) {
			return [];
		}
		$args['specificPosts'] = $post_ids;

		if ( empty( $args['postsToShow'] ) ) {
			// Enforce a sane default if the value is not passed.
			$args['postsToShow'] = 16;
		}

		return [
			'blockName'    => 'newspack-blocks/homepage-articles',
			'attrs'        => $args,
			'innerBlocks'  => [],
			'innerHTML'    => '',
			'innerContent' => [],
		];
	}

}
