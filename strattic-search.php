<?php

/**
 * EDIT: Note, a derivative of this code was ready to merge to master as of 2021-09-13. This snippet may no longer be required.
 * This is useful for when a complete total override of the theme template is required.
 * It loads whatever template is at strattic-search.php instead of themes normal theme template.
 * By default, we use the WordPress page template. You can override it and make it use any template you like via the following code:
 * This will behave just like a post/page template. The search content is outputted via the_content().
 * EDIT: For some sites, template_include is required instead of page_template.
 */
add_filter(
//	'page_template',
	'template_include',
	function ( $template ) {
		$settings = get_option( strattic()->search->settings::SETTINGS_OPTION );

		if ( is_strattic_search() ) {
			$template = ABSPATH . '/wp-content/mu-plugins/strattic-search/strattic-search-template.php';
		}

		return $template;
	}
);

/**
 * Is this a Strattic search page?
 * Doesn't use Strattic_Search_Settings() because it is not always loaded yet.
 *
 * @return bool True if on a search page.
 */
function is_strattic_search() {
	$slug = strattic()->search->settings->get_option( 'search-page-slug' );


	if (
		'/' . $slug . '/' === substr( $_SERVER['REQUEST_URI'], 0, strlen( $slug ) + 2 )
		||
		'/' . $slug === substr( $_SERVER['REQUEST_URI'], 0, strlen( $slug ) + 1 )
	) {
		return true;
	}

	return false;
}

/**
 * Add a body class to the search page.
 */
add_filter(
	'body_class',
	function( $classes ) {
		if ( is_strattic_search() ) {
			$classes = explode( ' ', 'strattic-search-humdata search search-results  style-color-xsdn-bg group-blog hmenu hmenu-center-split header-full-width main-center-align wpb-js-composer js-comp-ver-4.12.1 vc_responsive' );
		}

		return $classes;
	}
);

/**
 * Making the resource thumbnails available for Strattic search.
 */
add_filter(
	'strattic_search_item',
	function( $item ) {

		$post_id = $item['id'];
		if ( 'post' !== get_post_type( $post_id ) ) {
			return array();
		}

		$item['thumbnails'] = array();
		$item['thumbnails']['full'] = str_replace( home_url(), '', get_the_post_thumbnail_url( $post_id, 'full' ) );

		$resource_id = get_post_meta( $post_id, 'resource_thumbnail', true );
		if ( '' !== $resource_id ) {
			$url = wp_get_attachment_url( $resource_id );
			if ( '' !== $url ) {
				$path        = str_replace( home_url(), '', $url );
				if ( '' !== $path ) {
					$item['thumbnails']['full'] = $path;
				}
			}
		} else {
			$attachments = get_posts( array(
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
				'post_parent'    => $post_id,
				'exclude'        => get_post_thumbnail_id()
			) );
			if ( isset( $attachments[0]->ID ) ) {
				$attachment_id = $attachments[0]->ID;
				$url = wp_get_attachment_url( $attachment_id );
				if ( '' !== $url ) {
					$path = str_replace( home_url(), '', $url );
					if ( '' !== $path ) {
						$item['thumbnails']['full'] = $path;
					}
				}
			}
		}

		return $item;
	}
);

/**
 * Also add the new Strattic search item into the formatting method as well.
 */
add_filter(
	'strattic_search_formatting',
	function( $item, $page ) {
		$item['thumbnails'] = $page['thumbnails'];

		return $item;
	},
	null,
	2
);

/**
 * DO NOT use the strattic_post_types filter for this, as this 
 * also filters the posts to be published.
 * We use the strattic_search_data filter, because the later 
 * strattic_search_item can not be unset.
 */
/**
 * Enable only one specific post-type in Strattic search.
 */
add_filter(
	'strattic_search_data',
	function( $data ) {

		foreach ( $data as $key => $d ) {
			if ( 'post' !== get_post_type( $d['id'] ) ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
);

/**
 * Forcing search to operate on the active category.
 * 
 * @param string $html The page HTML.
 * @return string the modified page HTML.
 */
add_filter(
	'strattic_buffer',
	function( $html ) {

		// get current category data
		$category_data = get_term(get_query_var('cat'), 'category');
		// use category slug, if any, otherwise use "terms" $_GET param value (via JS)
		$taxonomy_terms = ($category_data && $category_data->slug) ? $category_data->slug : '';

		$string = '<input type="search" class="search-field form-fluid no-livesearch"';

		$new_fields = '<input name="taxonomy" value="category" type="hidden" />';
		$new_fields .= '<input name="terms" value="'.$taxonomy_terms.'" type="hidden" />';

		$html = str_replace( $string, $new_fields . $string, $html );

		return $html;
	}
);


/**
 * Add CSS file to search page.
 */
add_filter(
	'template_redirect',
	function() {
		if ( is_strattic_search() ) {
			wp_enqueue_style( 'strattic-search-cdx', '/wp-content/mu-plugins/strattic-search/strattic-search.css', array(), '1.0', 'all' );
		}
	}
);

/**
 * Adding script for handling unexpected search form added to the site after search implementation.
 */
add_filter(
	'wp_footer',
	function( $html ) {

		// Bail out if not on search page.
		if ( ! is_strattic_search() ) {
			return;
		}

		?>
		<script>
		document.addEventListener("DOMContentLoaded", function() {
			const params = new Proxy(new URLSearchParams(window.location.search), {
				get: (searchParams, prop) => searchParams.get(prop),
			});

			let strattic_taxonomy_terms = document.getElementsByClassName( 'search-field' );
			for ( let i = 0; i < strattic_taxonomy_terms.length; i++ ) {
				strattic_taxonomy_terms[ i ].value = params.q;
			}
		});
		</script><?php
	}
);

/**
 * Only show 12 posts per search page.
 */
add_action(
	'wp_head',
	function() {
		echo '
<script>
	var strattic_algolia = [];
	strattic_algolia.hitsPerPage = '.get_option('posts_per_page').';
</script>
';

	}
);

/**
 * Also add resource data to the new Strattic search item into the formatting method as well.
 */
add_filter(
	'strattic_search_formatting',
	function( $item, $page ) {

		foreach ($item['categories'] as $category) {
			if($category['slug'] === 'resource-library') {
				$item['short_title'] = strlen($item['title']) > 50 ? substr($item['title'], 0, 50).'...' : $item['title'];

				$resource_category = get_field('resource_category', $item['id']);
				$item['resource_category'] = ($resource_category !== '') ? $resource_category : 'Resource';

				$resource_link = get_field('resource_link', $item['id']);
				$item['resource_url'] = $resource_link['url'];
				$item['resource_title'] = $resource_link['title'];
				$item['resource_target'] = $resource_link['target'];

				$item['resource_description'] = get_field('resource_description', $item['id']);

				break;
			}
		}

		return $item;
	},
	null,
	3
);

/**
 * Adding script for handling the category in search results.
 */
add_filter(
	'wp_footer',
	function( $html ) {

		// Bail out if not on search page.
		if ( ! is_strattic_search() ) {
			return;
		}

		?>
		<script>
		document.addEventListener("DOMContentLoaded", function() {

			jQuery(document).ready(function($) {

				// get URL param
				function getURLParam(param) {
					let params = {};

					if (location.search) {
						let parts = location.search.substring(1).split('&');

						for (let i = 0; i < parts.length; i++) {
							let nv = parts[i].split('=');
							if (!nv[0]) {
								continue;
							}
							params[nv[0]] = nv[1] || true;
						}

						return params[param];
					}

					return undefined;
				}

				// get searched category
				let searchTaxonomyTerm = getURLParam('terms');

				// if category found
				if(searchTaxonomyTerm) {
					// set current category in Strattic hidden field
					$('.header-main-container .search-container input[name="terms"]').val(searchTaxonomyTerm);

					// append body class to allow CSS processing
					$('body').addClass('search-results-' + searchTaxonomyTerm);
				}

			});

		});
		</script>
		<?php
	}
);
