<?php
/**
 * Seeds page-based mockups for learning, resources, case studies, and data centre.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/**
 * Upserts a taxonomy term and returns its ID.
 *
 * @param string $taxonomy Taxonomy key.
 * @param string $name     Term name.
 * @param string $slug     Term slug.
 * @return int
 */
function gsf_hub_seed_term( $taxonomy, $name, $slug ) {
	$existing = term_exists( $slug, $taxonomy );

	if ( $existing && ! is_wp_error( $existing ) ) {
		return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
	}

	$created = wp_insert_term(
		$name,
		$taxonomy,
		array(
			'slug' => $slug,
		)
	);

	if ( is_wp_error( $created ) ) {
		$existing = term_exists( $name, $taxonomy );

		return (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
	}

	return (int) $created['term_id'];
}

/**
 * Copies a local asset into the media library once and returns the attachment ID.
 *
 * @param string $absolute_path Local file path.
 * @param string $title         Attachment title.
 * @return int
 */
function gsf_hub_seed_attachment( $absolute_path, $title ) {
	if ( ! file_exists( $absolute_path ) ) {
		return 0;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_gsf_hub_source_path',
			'meta_value'     => $absolute_path,
		)
	);

	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}

	$bits = wp_upload_bits( basename( $absolute_path ), null, (string) file_get_contents( $absolute_path ) );

	if ( ! empty( $bits['error'] ) ) {
		return 0;
	}

	$filetype      = wp_check_filetype( $bits['file'] );
	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$bits['file']
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $bits['file'] );
	wp_update_attachment_metadata( $attachment_id, $metadata );
	update_post_meta( $attachment_id, '_gsf_hub_source_path', $absolute_path );

	return (int) $attachment_id;
}

/**
 * Upserts a post by slug and returns the post ID.
 *
 * @param array<string,mixed> $args Post payload.
 * @return int
 */
function gsf_hub_seed_post( array $args ) {
	$existing = get_page_by_path( $args['post_name'], OBJECT, $args['post_type'] );
	$postarr  = array(
		'post_title'   => $args['post_title'],
		'post_name'    => $args['post_name'],
		'post_type'    => $args['post_type'],
		'post_status'  => 'publish',
		'post_content' => $args['post_content'],
		'post_excerpt' => $args['post_excerpt'],
	);

	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}

	if ( ! empty( $args['categories'] ) && 'post' === $args['post_type'] ) {
		wp_set_post_terms( $post_id, array_map( 'intval', $args['categories'] ), 'category', false );
	}

	if ( ! empty( $args['tags'] ) && 'post' === $args['post_type'] ) {
		wp_set_post_terms( $post_id, array_values( $args['tags'] ), 'post_tag', false );
	}

	if ( ! empty( $args['taxonomy_terms'] ) ) {
		foreach ( $args['taxonomy_terms'] as $taxonomy => $term_ids ) {
			wp_set_post_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy, false );
		}
	}

	if ( ! empty( $args['thumbnail_id'] ) ) {
		set_post_thumbnail( $post_id, (int) $args['thumbnail_id'] );
	}

	if ( 'courses' === $args['post_type'] ) {
		update_post_meta( $post_id, '_tutor_course_price_type', 'free' );
		update_post_meta(
			$post_id,
			'_tutor_course_settings',
			array(
				'maximum_students'        => 0,
				'enrollment_expiry'       => '',
				'enable_content_drip'     => 0,
				'content_drip_type'       => '',
				'enable_tutor_bp'         => 0,
				'course_enrollment_period'=> 'no',
				'enrollment_starts_at'    => '',
				'enrollment_ends_at'      => '',
				'pause_enrollment'        => 'no',
			)
		);
		update_post_meta( $post_id, '_video', array() );
		update_post_meta( $post_id, '_tutor_enable_qa', 'no' );
		update_post_meta( $post_id, '_tutor_is_public_course', 'no' );
		update_post_meta( $post_id, 'tutor_course_sale_price', '0' );
		update_post_meta( $post_id, '_course_duration', array( 'hours' => '0', 'minutes' => '0' ) );
		update_post_meta( $post_id, '_tutor_course_level', 'intermediate' );
	}

	return (int) $post_id;
}

/**
 * Upserts a page by slug and returns the page ID.
 *
 * @param array<string,mixed> $args Page payload.
 * @return int
 */
function gsf_hub_seed_page( array $args ) {
	$page_id = gsf_hub_seed_post(
		array(
			'post_type'    => 'page',
			'post_title'   => $args['post_title'],
			'post_name'    => $args['post_name'],
			'post_content' => $args['post_content'],
			'post_excerpt' => '',
		)
	);

	if ( $page_id && ! empty( $args['template'] ) ) {
		update_post_meta( $page_id, '_wp_page_template', $args['template'] );
	}

	return $page_id;
}

$theme_assets = ABSPATH . 'wp-content/themes/gsf-hub/assets/images/';
$child_assets = ABSPATH . 'wp-content/themes/gsf-hub-sunrise/assets/images/';
$upload_assets = ABSPATH . 'wp-content/uploads/2026/05/';

$resource_category_id = gsf_hub_seed_term( 'category', 'Resources', 'resources' );
$case_category_id     = gsf_hub_seed_term( 'category', 'Case Studies', 'case-studies' );

$course_paths = array(
	'field-practice'         => gsf_hub_seed_term( 'course-category', 'Field Practice', 'field-practice' ),
	'monitoring-reporting'   => gsf_hub_seed_term( 'course-category', 'Monitoring & Reporting', 'monitoring-reporting' ),
	'community-leadership'   => gsf_hub_seed_term( 'course-category', 'Community Leadership', 'community-leadership' ),
);

$hero_beach_id     = gsf_hub_seed_attachment( $theme_assets . 'hero-beach.jpg', 'Hero Beach' );
$lizard_image_id   = gsf_hub_seed_attachment( $theme_assets . 'featured-lizard.jpg', 'Featured Lizard' );
$flower_image_id   = gsf_hub_seed_attachment( $theme_assets . 'resource-flowers.jpg', 'Resource Flowers' );
$coastal_image_id  = gsf_hub_seed_attachment( $child_assets . 'hero-card-coastal.png', 'Caribbean Coastal Seascapes' );
$field_image_id    = gsf_hub_seed_attachment( $upload_assets . 'pexels-asemirski-13165440.jpg', 'Field Team Monitoring' );

$resource_posts = array(
	'gender-checklist-project-design' => array(
		'post_title'   => 'Gender Checklist for Project Design',
		'post_excerpt' => 'A practical checklist to help programme teams scope participation, safeguards, and monitoring before implementation begins.',
		'post_content' => '<p>Use this resource at concept stage to review stakeholder inclusion, risks, reporting expectations, and beneficiary pathways.</p><p>It is designed for conservation trust funds, grantees, and technical partners working across multilingual Caribbean contexts.</p>',
		'thumbnail_id' => $flower_image_id,
		'tags'         => array( 'Checklist', 'Planning', 'PDF' ),
	),
	'community-monitoring-toolkit'    => array(
		'post_title'   => 'Community Monitoring Toolkit',
		'post_excerpt' => 'A field-oriented toolkit for collecting gender-smart biodiversity observations with simple, reusable templates.',
		'post_content' => '<p>This toolkit includes sample data sheets, interview prompts, and facilitation notes for local monitoring teams.</p><p>It is intended to support practical learning, offline use, and reporting consistency across territories.</p>',
		'thumbnail_id' => $field_image_id,
		'tags'         => array( 'Toolkit', 'Monitoring', 'Field Use' ),
	),
	'safeguards-reporting-template'   => array(
		'post_title'   => 'Safeguards Reporting Template',
		'post_excerpt' => 'A reusable reporting structure for documenting implementation progress, participation, and social safeguards considerations.',
		'post_content' => '<p>Programme teams can adapt this template for quarterly updates, donor submissions, and internal management reviews.</p><p>It is formatted to reduce duplicate reporting effort while keeping gender metrics visible.</p>',
		'thumbnail_id' => $hero_beach_id,
		'tags'         => array( 'Template', 'Reporting', 'DOCX' ),
	),
	'offline-engagement-pack'         => array(
		'post_title'   => 'Offline Engagement Pack',
		'post_excerpt' => 'A ready-to-use pack of facilitation materials for workshops, onboarding, and local sessions where connectivity is limited.',
		'post_content' => '<p>The pack bundles printable forms, slide outlines, and quick-reference guidance for partner-led sessions.</p><p>It is useful for field teams who need resilient facilitation tools that can still align with central reporting.</p>',
		'thumbnail_id' => $coastal_image_id,
		'tags'         => array( 'Offline', 'Facilitation', 'ZIP' ),
	),
);

$resource_post_ids = array();

foreach ( $resource_posts as $slug => $resource_post ) {
	$resource_post_ids[ $slug ] = gsf_hub_seed_post(
		array(
			'post_type'    => 'post',
			'post_title'   => $resource_post['post_title'],
			'post_name'    => $slug,
			'post_content' => $resource_post['post_content'],
			'post_excerpt' => $resource_post['post_excerpt'],
			'categories'   => array( $resource_category_id ),
			'tags'         => $resource_post['tags'],
			'thumbnail_id' => $resource_post['thumbnail_id'],
		)
	);
}

$case_posts = array(
	'women-led-mangrove-restoration-saint-lucia' => array(
		'post_title'   => 'Women-led mangrove restoration in Saint Lucia',
		'post_excerpt' => 'A coastal restoration case study showing how livelihoods, local leadership, and ecosystem recovery can be advanced together.',
		'post_content' => '<p>Community leaders in Saint Lucia combined restoration work with local stewardship, women\'s participation, and practical monitoring.</p><p>The result was a programme that produced visible environmental gains while also building trusted community ownership.</p>',
		'thumbnail_id' => $field_image_id,
		'tags'         => array( 'Mangroves', 'Women Leaders', 'Saint Lucia' ),
	),
	'sea-turtle-conservation-belize'             => array(
		'post_title'   => 'Sea turtle conservation with women rangers in Belize',
		'post_excerpt' => 'A case study on how ranger training and visible women-led stewardship strengthened conservation messaging and local engagement.',
		'post_content' => '<p>Belize partners used the programme to showcase women rangers, improve community trust, and connect biodiversity work with public education.</p>',
		'thumbnail_id' => $hero_beach_id,
		'tags'         => array( 'Belize', 'Rangers', 'Conservation Education' ),
	),
	'climate-smart-farming-grenada'              => array(
		'post_title'   => 'Climate-smart farming for women in Grenada',
		'post_excerpt' => 'A practical story about linking conservation outcomes to women\'s economic resilience through community-led farming approaches.',
		'post_content' => '<p>Grenada partners used training, coordination, and peer exchange to turn small-scale pilot efforts into reusable local practice.</p>',
		'thumbnail_id' => $flower_image_id,
		'tags'         => array( 'Grenada', 'Livelihoods', 'Climate Resilience' ),
	),
	'women-leading-policy-change-dominican-republic' => array(
		'post_title'   => 'Women leading policy change in the Dominican Republic',
		'post_excerpt' => 'An example of how storytelling and programme evidence can influence policy conversations beyond the lifespan of a single grant.',
		'post_content' => '<p>Dominican Republic stakeholders combined evidence, advocacy, and local testimony to make programme outcomes legible to decision-makers.</p>',
		'thumbnail_id' => $lizard_image_id,
		'tags'         => array( 'Policy', 'Dominican Republic', 'Storytelling' ),
	),
);

$case_post_ids = array();

foreach ( $case_posts as $slug => $case_post ) {
	$case_post_ids[ $slug ] = gsf_hub_seed_post(
		array(
			'post_type'    => 'post',
			'post_title'   => $case_post['post_title'],
			'post_name'    => $slug,
			'post_content' => $case_post['post_content'],
			'post_excerpt' => $case_post['post_excerpt'],
			'categories'   => array( $case_category_id ),
			'tags'         => $case_post['tags'],
			'thumbnail_id' => $case_post['thumbnail_id'],
		)
	);
}

$courses = array(
	'test-course' => array(
		'post_title'   => 'Community Safeguards Basics',
		'post_excerpt' => 'A practical introduction to participation, safeguards, and inclusive implementation workflows.',
		'post_content' => '<p>Build a shared foundation for planning, facilitation, and risk-aware project delivery across the hub.</p>',
		'thumbnail_id' => $coastal_image_id,
		'terms'        => array( $course_paths['community-leadership'] ),
	),
	'inclusive-grant-monitoring' => array(
		'post_title'   => 'Inclusive Grant Monitoring',
		'post_excerpt' => 'A lightweight monitoring pathway for turning activity data into usable programme evidence.',
		'post_content' => '<p>Learn how to structure reporting rhythms, collect clearer metrics, and build evidence that supports both learning and accountability.</p>',
		'thumbnail_id' => $field_image_id,
		'terms'        => array( $course_paths['monitoring-reporting'] ),
	),
	'coastal-restoration-facilitation' => array(
		'post_title'   => 'Coastal Restoration Facilitation',
		'post_excerpt' => 'A field-practice course focused on community sessions, restoration planning, and delivery coordination.',
		'post_content' => '<p>This course supports facilitators who need practical routines for managing participation and conservation activity in coastal settings.</p>',
		'thumbnail_id' => $hero_beach_id,
		'terms'        => array( $course_paths['field-practice'] ),
	),
	'storytelling-for-programme-evidence' => array(
		'post_title'   => 'Storytelling for Programme Evidence',
		'post_excerpt' => 'A communication-focused module for turning outcomes into clear stories for partners, donors, and communities.',
		'post_content' => '<p>Practice framing impact narratives, selecting evidence, and presenting results in a way that supports uptake and learning.</p>',
		'thumbnail_id' => $lizard_image_id,
		'terms'        => array( $course_paths['community-leadership'] ),
	),
);

foreach ( $courses as $slug => $course ) {
	gsf_hub_seed_post(
		array(
			'post_type'      => 'courses',
			'post_title'     => $course['post_title'],
			'post_name'      => $slug,
			'post_content'   => $course['post_content'],
			'post_excerpt'   => $course['post_excerpt'],
			'taxonomy_terms' => array(
				'course-category' => $course['terms'],
			),
			'thumbnail_id'   => $course['thumbnail_id'],
		)
	);
}

$dashboard_url            = home_url( '/dashboard/' );
$student_registration_url = home_url( '/student-registration/' );
$resources_url            = home_url( '/resources/' );
$case_studies_url         = home_url( '/case-studies/' );
$data_centre_url          = home_url( '/data-centre/' );
$directory_url            = home_url( '/directory/' );
$forum_url                = home_url( '/forum/' );

$featured_case_url      = get_permalink( $case_post_ids['women-led-mangrove-restoration-saint-lucia'] );
$featured_case_image    = wp_get_attachment_image_url( $field_image_id, 'large' );
$resource_report_url    = get_permalink( $resource_post_ids['safeguards-reporting-template'] );
$resource_toolkit_url   = get_permalink( $resource_post_ids['community-monitoring-toolkit'] );
$resource_offline_url   = get_permalink( $resource_post_ids['offline-engagement-pack'] );

$learning_content = <<<'HTML'
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--learning","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--learning"><div class="wp-block-group__inner-container"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Learning</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Build practical skills for gender-smart conservation delivery.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">This page uses Tutor LMS as the core learning engine. The page itself stays editable in WordPress, while the course catalogue, enrolment flow, and learner journey remain plugin-driven.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{DASHBOARD_URL}}">Open learner dashboard</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{{REGISTER_URL}}">Create learner account</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-kpi-grid hub-kpi-grid--data-centre"} -->
<div class="wp-block-columns hub-kpi-grid hub-kpi-grid--data-centre"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-kpi-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-kpi-card"><div class="wp-block-group__inner-container"><p class="hub-page-hero__eyebrow">Tutor LMS</p><p class="hub-kpi-card__value">4</p><p class="hub-kpi-card__label">mock course pathways seeded for review</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-kpi-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-kpi-card"><div class="wp-block-group__inner-container"><p class="hub-page-hero__eyebrow">Flexible</p><p class="hub-kpi-card__value">4</p><p class="hub-kpi-card__label">languages already supported in the wider site structure</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-kpi-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-kpi-card"><div class="wp-block-group__inner-container"><p class="hub-page-hero__eyebrow">Reusable</p><p class="hub-kpi-card__value">1</p><p class="hub-kpi-card__label">page template used instead of a custom learning front-end</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"className":"hub-card-grid","style":{"spacing":{"margin":{"top":"1rem"}}}} -->
<div class="wp-block-columns hub-card-grid" style="margin-top:1rem"><!-- wp:column {"width":"65%"} -->
<div class="wp-block-column" style="flex-basis:65%"><!-- wp:group {"className":"hub-info-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-info-card"><div class="wp-block-group__inner-container"><p class="hub-section__eyebrow">Learning pathways</p><h2 class="hub-section__title">Start with field practice, reporting, or storytelling.</h2><p class="hub-section__text">The mockup keeps the course catalogue native to Tutor LMS, while the surrounding context explains how the learning offer is organised for the platform.</p><ul class="hub-pill-list"><li>Self-paced modules</li><li>Quizzes and completion tracking</li><li>Partner-ready onboarding</li><li>Certificate pathways</li></ul></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"35%"} -->
<div class="wp-block-column" style="flex-basis:35%"><!-- wp:group {"className":"hub-note-box","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-note-box"><div class="wp-block-group__inner-container"><p><strong>Implementation note:</strong> this page is a normal WordPress page. The course listings below come from the Tutor shortcode, so editors can keep working through plugin settings and courses rather than a custom coded course grid.</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><div class="hub-section__heading"><p class="hub-section__eyebrow">Course catalogue</p><h2 class="hub-section__title">Tutor-powered learning content</h2><p class="hub-section__text">The shortcode below uses the existing Tutor LMS plugin so course cards, filters, and pagination come from the LMS layer.</p></div><!-- wp:shortcode -->
[tutor_course count="6" column_per_row="3" course_filter="on" show_pagination="on"]
<!-- /wp:shortcode --></div></div>
<!-- /wp:group -->
HTML;

$resources_content = <<<'HTML'
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--resources","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--resources"><div class="wp-block-group__inner-container"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Resources</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Find templates, toolkits, and reporting aids without leaving WordPress.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">This mockup treats resources as standard WordPress posts in a dedicated category. That keeps publishing simple and avoids inventing a custom library system before the content model is fully settled.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{RESOURCE_REPORT_URL}}">Open sample report</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{{DATA_CENTRE_URL}}">View the data centre mockup</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-card-grid"} -->
<div class="wp-block-columns hub-card-grid"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-info-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-info-card"><div class="wp-block-group__inner-container"><h3>Downloadable templates</h3><p>Structured files for narrative reporting, safeguards, and implementation updates.</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-info-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-info-card"><div class="wp-block-group__inner-container"><h3>Field-ready toolkits</h3><p>Guides and checklists that still make sense offline, in workshops, or during site visits.</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:group {"className":"hub-info-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-info-card"><div class="wp-block-group__inner-container"><h3>Reusable references</h3><p>Plain WordPress publishing keeps versioning, translations, and future filters manageable.</p></div></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section hub-post-feed","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section hub-post-feed"><div class="wp-block-group__inner-container"><div class="hub-section__heading"><p class="hub-section__eyebrow">Resource library</p><h2 class="hub-section__title">Current resource entries</h2><p class="hub-section__text">These cards are standard posts filtered by the Resources category, so future editors can keep using familiar publishing workflows.</p></div><!-- wp:latest-posts {"postsToShow":6,"displayPostDate":true,"displayExcerpt":true,"excerptLength":18,"displayFeaturedImage":true,"featuredImageAlign":"center","featuredImageSizeSlug":"large","postLayout":"grid","columns":3,"categories":[{{RESOURCE_CATEGORY_ID}}]} /--></div></div>
<!-- /wp:group -->
HTML;

$case_studies_content = <<<'HTML'
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--case-studies","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--case-studies"><div class="wp-block-group__inner-container"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Case Studies</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Show outcomes through stories people can actually use.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">The case study page is page-based and editor-friendly, but the stories themselves are just standard posts. That means the content model stays simple while still giving the site a proper story hub.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{FEATURED_CASE_URL}}">Read featured case</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{{FORUM_URL}}">Open peer exchange</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-card-grid"} -->
<div class="wp-block-columns hub-card-grid"><!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%"><!-- wp:group {"className":"hub-highlight-card","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-highlight-card"><div class="wp-block-group__inner-container"><div class="hub-highlight-card__media"><img src="{{FEATURED_CASE_IMAGE}}" alt=""></div><p class="hub-section__eyebrow">Featured story</p><h3>Women-led mangrove restoration in Saint Lucia</h3><p>A story-led entry point for programme results, community testimony, and lessons that can feed future training or reporting.</p><div class="hub-inline-stats"><div class="hub-inline-stat"><strong>2,350</strong><span>Mangroves planted</span></div><div class="hub-inline-stat"><strong>180</strong><span>Women involved</span></div><div class="hub-inline-stat"><strong>12 ha</strong><span>Area restored</span></div></div></div></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%"><!-- wp:group {"className":"hub-note-box","layout":{"type":"constrained"}} -->
<div class="wp-block-group hub-note-box"><div class="wp-block-group__inner-container"><p><strong>Implementation note:</strong> the mock stories are regular posts, so future storytelling can scale through editorial workflow, categories, tags, translations, and Search &amp; Filter later if needed.</p></div></div>
<!-- /wp:group -->

<!-- wp:list {"className":"hub-pill-list"} -->
<ul class="hub-pill-list"><li>Outcome narratives</li><li>Community voice</li><li>Policy learning</li><li>Replication insights</li></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section hub-story-feed","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section hub-story-feed"><div class="wp-block-group__inner-container"><div class="hub-section__heading"><p class="hub-section__eyebrow">Story collection</p><h2 class="hub-section__title">Current case study entries</h2><p class="hub-section__text">These cards pull directly from posts in the Case Studies category, keeping the page easy to maintain while still giving it a clear hub structure.</p></div><!-- wp:latest-posts {"postsToShow":8,"displayPostDate":true,"displayExcerpt":true,"excerptLength":18,"displayFeaturedImage":true,"featuredImageAlign":"center","featuredImageSizeSlug":"large","postLayout":"grid","columns":4,"categories":[{{CASE_CATEGORY_ID}}]} /--></div></div>
<!-- /wp:group -->
HTML;

$data_centre_content = <<<'HTML'
<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--data-centre","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull hub-page-hero hub-page-hero--data-centre"><div class="wp-block-group__inner-container"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->
<p class="hub-page-hero__eyebrow">Data Centre</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->
<h1 class="wp-block-heading hub-page-hero__title">Storytelling dashboard</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"hub-page-hero__text"} -->
<p class="hub-page-hero__text">This page is a WordPress page mockup inspired by your dashboard concept. It uses page content and reusable CSS now, so a future chart or BI plugin can replace the placeholder visuals without rebuilding the page structure.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"hub-page-hero__actions"} -->
<div class="wp-block-buttons hub-page-hero__actions"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{FEATURED_CASE_URL}}">View featured story</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{{RESOURCES_URL}}">Browse reports and resources</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-kpi-grid hub-kpi-grid--data-centre"} -->
<div class="wp-block-columns hub-kpi-grid hub-kpi-grid--data-centre"><!-- wp:column -->
<div class="wp-block-column"><div class="hub-kpi-card"><p class="hub-page-hero__eyebrow">Territories</p><p class="hub-kpi-card__value">8</p><p class="hub-kpi-card__label">territories represented in the current mockup</p></div></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><div class="hub-kpi-card"><p class="hub-page-hero__eyebrow">Projects</p><p class="hub-kpi-card__value">24</p><p class="hub-kpi-card__label">active examples referenced in dashboard stories</p></div></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><div class="hub-kpi-card"><p class="hub-page-hero__eyebrow">Beneficiaries</p><p class="hub-kpi-card__value">1,250+</p><p class="hub-kpi-card__label">illustrative reach for mock reporting</p></div></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><div class="hub-kpi-card"><p class="hub-page-hero__eyebrow">Women participants</p><p class="hub-kpi-card__value">67%</p><p class="hub-kpi-card__label">participation share in the sample dashboard</p></div></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><div class="hub-kpi-card"><p class="hub-page-hero__eyebrow">Course completions</p><p class="hub-kpi-card__value">1,485</p><p class="hub-kpi-card__label">linked to the learning-side mock metrics</p></div></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-panel-grid hub-panel-grid--storytelling"} -->
<div class="wp-block-columns hub-panel-grid hub-panel-grid--storytelling"><!-- wp:column {"width":"34%"} -->
<div class="wp-block-column" style="flex-basis:34%"><div class="hub-story-panel"><div class="hub-highlight-card__media"><img src="{{FEATURED_CASE_IMAGE}}" alt=""></div><p class="hub-section__eyebrow">Featured story</p><h3>Women-led mangrove restoration in Saint Lucia</h3><p>This panel shows how story-led evidence could sit beside dashboard metrics without leaving WordPress.</p><div class="hub-story-panel__actions wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{{FEATURED_CASE_URL}}">Watch story</a></div></div></div></div>
<!-- /wp:column -->
<!-- wp:column {"width":"33%"} -->
<div class="wp-block-column" style="flex-basis:33%"><div class="hub-story-panel"><p class="hub-section__eyebrow">Story metrics</p><h3>Programme outcomes connected to one narrative</h3><div class="hub-inline-stats"><div class="hub-inline-stat"><strong>2,350</strong><span>Mangroves planted</span></div><div class="hub-inline-stat"><strong>180</strong><span>Women involved</span></div><div class="hub-inline-stat"><strong>12 ha</strong><span>Area restored</span></div></div><p class="hub-caption">“This project has strengthened our community and our coastline. We are protecting nature today for our children tomorrow.”</p></div></div>
<!-- /wp:column -->
<!-- wp:column {"width":"33%","className":"hub-chart-stack"} -->
<div class="wp-block-column hub-chart-stack" style="flex-basis:33%"><div class="hub-chart-panel"><p class="hub-section__eyebrow">Gender participation</p><h3>Participation snapshot</h3><div class="hub-donut-wrap"><div class="hub-donut">67%<small>Women</small></div><ul class="hub-legend"><li><span>Women</span><strong>67%</strong></li><li><span>Men</span><strong>30%</strong></li><li><span>Other / prefer not to say</span><strong>3%</strong></li></ul></div></div><div class="hub-chart-panel" style="margin-top:1rem"><p class="hub-section__eyebrow">Completions</p><h3>Course completions over time</h3><div class="hub-line-chart"><div class="hub-line-chart__graph"><div class="hub-line-chart__path"><svg viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true"><polyline fill="none" stroke="url(#hubLineGradient)" stroke-width="2" points="0,18 12,16 24,12 36,10 48,9 60,7 72,6 84,3 100,1"></polyline><defs><linearGradient id="hubLineGradient" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#1ca6a6"></stop><stop offset="100%" stop-color="#005e7a"></stop></linearGradient></defs></svg></div></div><div class="hub-line-chart__labels"><span>May '23</span><span>Sep '23</span><span>Jan '24</span><span>May '24</span><span>Sep '24</span><span>Jan '25</span><span>May '25</span></div></div></div></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-panel-grid hub-panel-grid--territories"} -->
<div class="wp-block-columns hub-panel-grid hub-panel-grid--territories"><!-- wp:column {"width":"68%"} -->
<div class="wp-block-column" style="flex-basis:68%"><div class="hub-chart-panel"><p class="hub-section__eyebrow">Projects by territory</p><h3>Illustrative geographic spread</h3><div class="hub-map-points"><span>Jamaica · 5 projects</span><span>Haiti · 3 projects</span><span>Dominican Republic · 4 projects</span><span>Saint Lucia · 3 projects</span><span>Grenada · 2 projects</span><span>Belize · 2 projects</span></div><p class="hub-section__eyebrow" style="margin-top:1rem">Project outcomes by territory</p><div class="hub-bar-chart"><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:84%"></div><span class="hub-bar-chart__label">Jam</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:62%"></div><span class="hub-bar-chart__label">Hai</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:69%"></div><span class="hub-bar-chart__label">Dom</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:66%"></div><span class="hub-bar-chart__label">SLU</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:43%"></div><span class="hub-bar-chart__label">Gre</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:38%"></div><span class="hub-bar-chart__label">Bel</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:54%"></div><span class="hub-bar-chart__label">Bah</span></div><div class="hub-bar-chart__item"><div class="hub-bar-chart__bar" style="height:40%"></div><span class="hub-bar-chart__label">T&amp;T</span></div></div></div></div>
<!-- /wp:column -->
<!-- wp:column {"width":"32%"} -->
<div class="wp-block-column" style="flex-basis:32%"><div class="hub-learning-impact"><p class="hub-section__eyebrow">Learning impact</p><h3>1,485 course completions</h3><p class="hub-caption">A simple panel that can later be replaced with real plugin or BI metrics.</p><div class="hub-learning-impact__stats"><div><strong>32</strong><span>Courses</span></div><div><strong>18</strong><span>Partners</span></div><div><strong>6,860</strong><span>Certificates issued</span></div></div><p class="hub-caption" style="margin-top:1rem"><a href="{{DASHBOARD_URL}}">Explore learning →</a></p></div></div>
<!-- /wp:column -->
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:columns {"className":"hub-panel-grid hub-panel-grid--summary"} -->
<div class="wp-block-columns hub-panel-grid hub-panel-grid--summary"><!-- wp:column {"width":"56%"} -->
<div class="wp-block-column" style="flex-basis:56%"><div class="hub-table-panel"><p class="hub-section__eyebrow">Evidence for partners</p><h3>Programme progress</h3><ul class="hub-progress-table"><li><div class="hub-progress-row"><div class="hub-progress-row__meta"><strong>Ecosystem restoration</strong><span>10 / 15</span><span>67%</span></div><div class="hub-progress-row__track"><div class="hub-progress-row__fill" style="width:67%"></div></div></div></li><li><div class="hub-progress-row"><div class="hub-progress-row__meta"><strong>Sustainable livelihoods</strong><span>7 / 10</span><span>70%</span></div><div class="hub-progress-row__track"><div class="hub-progress-row__fill" style="width:70%"></div></div></div></li><li><div class="hub-progress-row"><div class="hub-progress-row__meta"><strong>Women&apos;s leadership</strong><span>6 / 8</span><span>75%</span></div><div class="hub-progress-row__track"><div class="hub-progress-row__fill" style="width:75%"></div></div></div></li><li><div class="hub-progress-row"><div class="hub-progress-row__meta"><strong>Capacity &amp; learning</strong><span>6 / 10</span><span>60%</span></div><div class="hub-progress-row__track"><div class="hub-progress-row__fill" style="width:60%"></div></div></div></li></ul></div></div>
<!-- /wp:column -->
<!-- wp:column {"width":"44%"} -->
<div class="wp-block-column" style="flex-basis:44%"><div class="hub-quote-panel"><blockquote><p>“When we invest in women and nature together, our islands become more resilient, our communities thrive, and our future is brighter.”</p><cite class="hub-caption">GSF Hub partner</cite></blockquote></div></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:group -->
HTML;

$learning_page_id = gsf_hub_seed_page(
	array(
		'post_title'   => 'Learn',
		'post_name'    => 'learning',
		'post_content' => strtr(
			$learning_content,
			array(
				'{{DASHBOARD_URL}}' => esc_url( $dashboard_url ),
				'{{REGISTER_URL}}'  => esc_url( $student_registration_url ),
			)
		),
		'template'     => 'template-content-canvas.php',
	)
);

$resources_page_id = gsf_hub_seed_page(
	array(
		'post_title'   => 'Resources',
		'post_name'    => 'resources',
		'post_content' => strtr(
			$resources_content,
			array(
				'{{RESOURCE_CATEGORY_ID}}' => (string) $resource_category_id,
				'{{RESOURCE_REPORT_URL}}'  => esc_url( $resource_report_url ),
				'{{DATA_CENTRE_URL}}'      => esc_url( $data_centre_url ),
			)
		),
		'template'     => 'template-content-canvas.php',
	)
);

$case_studies_page_id = gsf_hub_seed_page(
	array(
		'post_title'   => 'Case Studies',
		'post_name'    => 'case-studies',
		'post_content' => strtr(
			$case_studies_content,
			array(
				'{{CASE_CATEGORY_ID}}'  => (string) $case_category_id,
				'{{FEATURED_CASE_URL}}' => esc_url( $featured_case_url ),
				'{{FEATURED_CASE_IMAGE}}' => esc_url( $featured_case_image ),
				'{{FORUM_URL}}'         => esc_url( $forum_url ),
			)
		),
		'template'     => 'template-content-canvas.php',
	)
);

$data_centre_page_id = gsf_hub_seed_page(
	array(
		'post_title'   => 'Data Centre',
		'post_name'    => 'data-centre',
		'post_content' => strtr(
			$data_centre_content,
			array(
				'{{FEATURED_CASE_URL}}'    => esc_url( $featured_case_url ),
				'{{FEATURED_CASE_IMAGE}}'  => esc_url( $featured_case_image ),
				'{{RESOURCES_URL}}'        => esc_url( $resources_url ),
				'{{DASHBOARD_URL}}'        => esc_url( $dashboard_url ),
			)
		),
		'template'     => 'template-content-canvas.php',
	)
);

WP_CLI::log( 'Seeded pages:' );
WP_CLI::log( 'Learn: ' . get_permalink( $learning_page_id ) );
WP_CLI::log( 'Resources: ' . get_permalink( $resources_page_id ) );
WP_CLI::log( 'Case Studies: ' . get_permalink( $case_studies_page_id ) );
WP_CLI::log( 'Data Centre: ' . get_permalink( $data_centre_page_id ) );
