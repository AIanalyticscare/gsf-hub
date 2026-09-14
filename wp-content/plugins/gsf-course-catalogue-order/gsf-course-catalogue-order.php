<?php
/**
 * Plugin Name: GSF Course Catalogue Order
 * Description: Adds an editable catalogue order to GSF Learn courses without changing homepage course selection.
 * Version: 1.0.0
 * Author: Gender Smart Facility
 * License: GPL-2.0-or-later
 * Text Domain: gsf-course-catalogue-order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the catalogue order box to Learn courses.
 */
function gsf_course_catalogue_order_add_meta_box() {
	add_meta_box(
		'gsf_course_catalogue_order',
		__( 'Catalogue order', 'gsf-course-catalogue-order' ),
		'gsf_course_catalogue_order_render_meta_box',
		'gsf_course',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_gsf_course', 'gsf_course_catalogue_order_add_meta_box' );

/**
 * Renders the catalogue order field.
 *
 * @param WP_Post $post Current course.
 */
function gsf_course_catalogue_order_render_meta_box( $post ) {
	wp_nonce_field( 'gsf_course_catalogue_order_save', 'gsf_course_catalogue_order_nonce' );
	?>
	<p>
		<label for="gsf_course_catalogue_order_value">
			<?php esc_html_e( 'Order', 'gsf-course-catalogue-order' ); ?>
		</label>
		<input
			type="number"
			class="small-text"
			id="gsf_course_catalogue_order_value"
			name="gsf_course_catalogue_order_value"
			value="<?php echo esc_attr( (string) $post->menu_order ); ?>"
			step="1"
		/>
	</p>
	<p class="description">
		<?php esc_html_e( 'Lower numbers appear first on the Learn catalogue. Courses with the same number are sorted alphabetically.', 'gsf-course-catalogue-order' ); ?>
	</p>
	<?php
}

/**
 * Saves the catalogue order into WordPress's native menu_order field.
 *
 * @param int     $post_id Course post ID.
 * @param WP_Post $post    Course post object.
 */
function gsf_course_catalogue_order_save( $post_id, $post ) {
	static $saving = false;

	if ( $saving ) {
		return;
	}

	if ( ! isset( $_POST['gsf_course_catalogue_order_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['gsf_course_catalogue_order_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'gsf_course_catalogue_order_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$order = isset( $_POST['gsf_course_catalogue_order_value'] )
		? intval( wp_unslash( $_POST['gsf_course_catalogue_order_value'] ) )
		: 0;

	if ( (int) $post->menu_order === $order ) {
		return;
	}

	$saving = true;
	wp_update_post(
		array(
			'ID'         => $post_id,
			'menu_order' => $order,
		)
	);
	$saving = false;
}
add_action( 'save_post_gsf_course', 'gsf_course_catalogue_order_save', 10, 2 );

/**
 * Adds the catalogue order to the Courses admin list.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function gsf_course_catalogue_order_add_column( $columns ) {
	$updated = array();

	foreach ( $columns as $key => $label ) {
		$updated[ $key ] = $label;

		if ( 'title' === $key ) {
			$updated['gsf_catalogue_order'] = __( 'Catalogue order', 'gsf-course-catalogue-order' );
		}
	}

	return $updated;
}
add_filter( 'manage_gsf_course_posts_columns', 'gsf_course_catalogue_order_add_column' );

/**
 * Renders the catalogue order admin column.
 *
 * @param string $column  Column name.
 * @param int    $post_id Course post ID.
 */
function gsf_course_catalogue_order_render_column( $column, $post_id ) {
	if ( 'gsf_catalogue_order' !== $column ) {
		return;
	}

	$post = get_post( $post_id );

	if ( $post ) {
		echo esc_html( (string) $post->menu_order );
	}
}
add_action( 'manage_gsf_course_posts_custom_column', 'gsf_course_catalogue_order_render_column', 10, 2 );

/**
 * Makes the catalogue order admin column sortable.
 *
 * @param array<string,string> $columns Sortable columns.
 * @return array<string,string>
 */
function gsf_course_catalogue_order_sortable_column( $columns ) {
	$columns['gsf_catalogue_order'] = 'menu_order';

	return $columns;
}
add_filter( 'manage_edit-gsf_course_sortable_columns', 'gsf_course_catalogue_order_sortable_column' );

/**
 * Applies the same order to the native public course archive.
 *
 * The homepage uses its own random-course query and is deliberately untouched.
 *
 * @param WP_Query $query Current query.
 */
function gsf_course_catalogue_order_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'gsf_course' ) ) {
		return;
	}

	$query->set(
		'orderby',
		array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		)
	);
}
add_action( 'pre_get_posts', 'gsf_course_catalogue_order_archive_query' );
