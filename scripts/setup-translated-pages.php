<?php
/**
 * Creates translated public pages for the GSF Hub site.
 *
 * Run with:
 * wp eval-file scripts/setup-translated-pages.php
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) ) {
	WP_CLI::error( 'Polylang must be installed and active before running this script.' );
}

/**
 * Returns a page content wrapper using existing hub page classes.
 *
 * @param string $eyebrow Hero eyebrow.
 * @param string $title   Hero title.
 * @param string $text    Hero text.
 * @param string $body    Body block markup.
 * @param string $variant Hero variant class suffix.
 * @return string
 */
function gsf_hub_translated_page_content( $eyebrow, $title, $text, $body, $variant = 'learning' ) {
	return '<!-- wp:group {"align":"full","className":"hub-page-hero hub-page-hero--' . esc_attr( $variant ) . '","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group alignfull hub-page-hero hub-page-hero--' . esc_attr( $variant ) . '"><div class="wp-block-group__inner-container"><!-- wp:group {"align":"wide","className":"hub-page-hero__inner","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group alignwide hub-page-hero__inner"><!-- wp:paragraph {"className":"hub-page-hero__eyebrow"} -->' . "\n"
		. '<p class="hub-page-hero__eyebrow">' . esc_html( $eyebrow ) . '</p>' . "\n"
		. '<!-- /wp:paragraph -->' . "\n\n"
		. '<!-- wp:heading {"level":1,"className":"hub-page-hero__title"} -->' . "\n"
		. '<h1 class="wp-block-heading hub-page-hero__title">' . esc_html( $title ) . '</h1>' . "\n"
		. '<!-- /wp:heading -->' . "\n\n"
		. '<!-- wp:paragraph {"className":"hub-page-hero__text"} -->' . "\n"
		. '<p class="hub-page-hero__text">' . esc_html( $text ) . '</p>' . "\n"
		. '<!-- /wp:paragraph --></div>' . "\n"
		. '<!-- /wp:group --></div></div>' . "\n"
		. '<!-- /wp:group -->' . "\n\n"
		. $body;
}

/**
 * Returns a shortcode section.
 *
 * @param string $shortcode Shortcode text.
 * @param string $id        Optional section ID.
 * @return string
 */
function gsf_hub_translated_shortcode_section( $shortcode, $id = '' ) {
	$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';

	return '<!-- wp:group {"align":"wide","className":"hub-section","layout":{"type":"constrained"}} -->' . "\n"
		. '<div' . $id_attr . ' class="wp-block-group alignwide hub-section"><div class="wp-block-group__inner-container"><!-- wp:shortcode -->' . "\n"
		. $shortcode . "\n"
		. '<!-- /wp:shortcode --></div></div>' . "\n"
		. '<!-- /wp:group -->';
}

/**
 * Returns translated page definitions keyed by source English slug.
 *
 * @return array<string,array<string,array<string,string>>>
 */
function gsf_hub_translated_page_blueprints() {
	$resource_category = get_category_by_slug( 'resources' );
	$case_category     = get_category_by_slug( 'case-studies' );
	$resource_cat_id   = $resource_category ? (int) $resource_category->term_id : 0;
	$case_cat_id       = $case_category ? (int) $case_category->term_id : 0;

	$resources_feed = '<!-- wp:group {"align":"wide","className":"hub-section hub-post-feed","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group alignwide hub-section hub-post-feed"><div class="wp-block-group__inner-container"><!-- wp:latest-posts {"postsToShow":6,"displayPostDate":true,"displayExcerpt":true,"excerptLength":18,"displayFeaturedImage":true,"featuredImageAlign":"center","featuredImageSizeSlug":"large","postLayout":"grid","columns":3' . ( $resource_cat_id ? ',"categories":[' . $resource_cat_id . ']' : '' ) . '} /--></div></div>' . "\n"
		. '<!-- /wp:group -->';

	$case_feed = '<!-- wp:group {"align":"wide","className":"hub-section hub-story-feed","layout":{"type":"constrained"}} -->' . "\n"
		. '<div class="wp-block-group alignwide hub-section hub-story-feed"><div class="wp-block-group__inner-container"><!-- wp:latest-posts {"postsToShow":8,"displayPostDate":true,"displayExcerpt":true,"excerptLength":18,"displayFeaturedImage":true,"featuredImageAlign":"center","featuredImageSizeSlug":"large","postLayout":"grid","columns":4' . ( $case_cat_id ? ',"categories":[' . $case_cat_id . ']' : '' ) . '} /--></div></div>' . "\n"
		. '<!-- /wp:group -->';

	return array(
		'learning'                => array(
			'fr' => array(
				'title'   => 'Apprentissage',
				'slug'    => 'apprentissage',
				'excerpt' => 'Accedez aux cours, suivez vos progres et obtenez des certificats.',
				'content' => gsf_hub_translated_page_content(
					'Apprentissage',
					'Developpez des competences pratiques pour une conservation sensible au genre.',
					'Accedez a des cours en autonomie, suivez vos progres, remplissez les questionnaires et obtenez des certificats via la plateforme GSF Hub.',
					gsf_hub_translated_shortcode_section( '[gsf_lms_catalog]', 'cours-disponibles' ) . "\n\n" . gsf_hub_translated_shortcode_section( '[gsf_lms_dashboard]', 'mon-apprentissage' ),
					'learning'
				),
			),
			'es' => array(
				'title'   => 'Aprendizaje',
				'slug'    => 'aprendizaje',
				'excerpt' => 'Acceda a cursos, siga su progreso y obtenga certificados.',
				'content' => gsf_hub_translated_page_content(
					'Aprendizaje',
					'Desarrolle habilidades practicas para una conservacion con enfoque de genero.',
					'Acceda a cursos a su propio ritmo, siga su progreso, complete encuestas y obtenga certificados mediante la plataforma GSF Hub.',
					gsf_hub_translated_shortcode_section( '[gsf_lms_catalog]', 'cursos-disponibles' ) . "\n\n" . gsf_hub_translated_shortcode_section( '[gsf_lms_dashboard]', 'mi-aprendizaje' ),
					'learning'
				),
			),
			'nl' => array(
				'title'   => 'Leren',
				'slug'    => 'leren',
				'excerpt' => 'Open cursussen, volg voortgang en ontvang certificaten.',
				'content' => gsf_hub_translated_page_content(
					'Leren',
					'Bouw praktische vaardigheden op voor genderslim natuurbeheer.',
					'Volg cursussen in uw eigen tempo, bekijk uw voortgang, vul feedback in en ontvang certificaten via het GSF Hub leerplatform.',
					gsf_hub_translated_shortcode_section( '[gsf_lms_catalog]', 'beschikbare-cursussen' ) . "\n\n" . gsf_hub_translated_shortcode_section( '[gsf_lms_dashboard]', 'mijn-leren' ),
					'learning'
				),
			),
		),
		'resources'               => array(
			'fr' => array(
				'title'   => 'Ressources',
				'slug'    => 'ressources',
				'excerpt' => 'Modeles, outils et references pour le travail de terrain et les rapports.',
				'content' => gsf_hub_translated_page_content( 'Ressources', 'Trouvez des modeles, boites a outils et aides au rapportage.', 'Cette page rassemble les ressources publiees dans WordPress afin que les equipes puissent les retrouver et les reutiliser facilement.', $resources_feed, 'resources' ),
			),
			'es' => array(
				'title'   => 'Recursos',
				'slug'    => 'recursos',
				'excerpt' => 'Plantillas, herramientas y referencias para trabajo de campo e informes.',
				'content' => gsf_hub_translated_page_content( 'Recursos', 'Encuentre plantillas, herramientas y apoyos para informes.', 'Esta pagina reune recursos publicados en WordPress para que los equipos puedan encontrarlos y reutilizarlos con facilidad.', $resources_feed, 'resources' ),
			),
			'nl' => array(
				'title'   => 'Bronnen',
				'slug'    => 'bronnen',
				'excerpt' => 'Sjablonen, hulpmiddelen en referenties voor veldwerk en rapportage.',
				'content' => gsf_hub_translated_page_content( 'Bronnen', 'Vind sjablonen, toolkits en hulpmiddelen voor rapportage.', 'Deze pagina bundelt bronnen die in WordPress zijn gepubliceerd, zodat teams ze gemakkelijk kunnen vinden en opnieuw gebruiken.', $resources_feed, 'resources' ),
			),
		),
		'case-studies'            => array(
			'fr' => array(
				'title'   => 'Etudes de cas',
				'slug'    => 'etudes-de-cas',
				'excerpt' => 'Histoires et enseignements pratiques issus des projets GSF.',
				'content' => gsf_hub_translated_page_content( 'Etudes de cas', 'Montrez les resultats a travers des histoires utilisables.', 'Les etudes de cas presentent des resultats, des voix communautaires et des enseignements que les partenaires peuvent reutiliser.', $case_feed, 'case-studies' ),
			),
			'es' => array(
				'title'   => 'Estudios de caso',
				'slug'    => 'estudios-de-caso',
				'excerpt' => 'Historias y aprendizajes practicos de proyectos GSF.',
				'content' => gsf_hub_translated_page_content( 'Estudios de caso', 'Muestre resultados mediante historias utiles.', 'Los estudios de caso presentan resultados, voces comunitarias y aprendizajes que los socios pueden reutilizar.', $case_feed, 'case-studies' ),
			),
			'nl' => array(
				'title'   => 'Praktijkverhalen',
				'slug'    => 'praktijkverhalen',
				'excerpt' => 'Verhalen en praktische lessen uit GSF-projecten.',
				'content' => gsf_hub_translated_page_content( 'Praktijkverhalen', 'Laat resultaten zien met verhalen die mensen kunnen gebruiken.', 'Deze praktijkverhalen tonen resultaten, stemmen uit gemeenschappen en lessen die partners opnieuw kunnen toepassen.', $case_feed, 'case-studies' ),
			),
		),
		'data-centre'             => array(
			'fr' => array(
				'title'   => 'Centre de donnees',
				'slug'    => 'centre-de-donnees',
				'excerpt' => 'Tableaux de bord, indicateurs et rapports du GSF Hub.',
				'content' => gsf_hub_translated_page_content( 'Centre de donnees', 'Consultez les donnees et les indicateurs du programme.', 'Le centre de donnees rassemble les tableaux de bord, les indicateurs et les rapports pour suivre les progres.', gsf_hub_translated_shortcode_section( '[gsf_data_centre]' ), 'data-centre' ),
			),
			'es' => array(
				'title'   => 'Centro de datos',
				'slug'    => 'centro-de-datos',
				'excerpt' => 'Paneles, indicadores e informes del GSF Hub.',
				'content' => gsf_hub_translated_page_content( 'Centro de datos', 'Consulte datos e indicadores del programa.', 'El centro de datos reune paneles, indicadores e informes para dar seguimiento al progreso.', gsf_hub_translated_shortcode_section( '[gsf_data_centre]' ), 'data-centre' ),
			),
			'nl' => array(
				'title'   => 'Datacentrum',
				'slug'    => 'datacentrum',
				'excerpt' => 'Dashboards, indicatoren en rapporten van de GSF Hub.',
				'content' => gsf_hub_translated_page_content( 'Datacentrum', 'Bekijk programmagegevens en indicatoren.', 'Het datacentrum brengt dashboards, indicatoren en rapporten samen om voortgang te volgen.', gsf_hub_translated_shortcode_section( '[gsf_data_centre]' ), 'data-centre' ),
			),
		),
		'events'                  => array(
			'fr' => array(
				'title'   => 'Evenements et webinaires',
				'slug'    => 'evenements',
				'excerpt' => 'Sessions virtuelles, webinaires et evenements de la plateforme.',
				'content' => gsf_hub_translated_page_content( 'Evenements et webinaires', 'Participez aux prochains evenements et webinaires.', 'Retrouvez les sessions virtuelles, webinaires et evenements organises via le GSF Hub.', gsf_hub_translated_shortcode_section( '[gsf_events_webinars]' ), 'learning' ),
			),
			'es' => array(
				'title'   => 'Eventos y seminarios web',
				'slug'    => 'eventos',
				'excerpt' => 'Sesiones virtuales, seminarios web y eventos de la plataforma.',
				'content' => gsf_hub_translated_page_content( 'Eventos y seminarios web', 'Participe en proximos eventos y seminarios web.', 'Encuentre sesiones virtuales, seminarios web y eventos alojados mediante el GSF Hub.', gsf_hub_translated_shortcode_section( '[gsf_events_webinars]' ), 'learning' ),
			),
			'nl' => array(
				'title'   => 'Evenementen en webinars',
				'slug'    => 'evenementen',
				'excerpt' => 'Virtuele sessies, webinars en platformevenementen.',
				'content' => gsf_hub_translated_page_content( 'Evenementen en webinars', 'Neem deel aan komende leerevenementen en webinars.', 'Vind virtuele sessies, webinars en platformevenementen die via de GSF Hub worden georganiseerd.', gsf_hub_translated_shortcode_section( '[gsf_events_webinars]' ), 'learning' ),
			),
		),
		'dashboard'               => array(
			'fr' => array( 'title' => 'Tableau de bord', 'slug' => 'tableau-de-bord', 'excerpt' => 'Tableau de bord apprenant.', 'content' => '[gsf_lms_dashboard]' ),
			'es' => array( 'title' => 'Panel', 'slug' => 'panel', 'excerpt' => 'Panel del estudiante.', 'content' => '[gsf_lms_dashboard]' ),
			'nl' => array( 'title' => 'Dashboard', 'slug' => 'dashboard-nl', 'excerpt' => 'Leerlingdashboard.', 'content' => '[gsf_lms_dashboard]' ),
		),
		'student-registration'    => array(
			'fr' => array( 'title' => 'Inscription apprenant', 'slug' => 'inscription-apprenant', 'excerpt' => 'Creer un compte apprenant.', 'content' => '[tutor_student_registration_form]' ),
			'es' => array( 'title' => 'Registro de estudiante', 'slug' => 'registro-estudiante', 'excerpt' => 'Crear una cuenta de estudiante.', 'content' => '[tutor_student_registration_form]' ),
			'nl' => array( 'title' => 'Studentregistratie', 'slug' => 'studentregistratie', 'excerpt' => 'Maak een studentenaccount aan.', 'content' => '[tutor_student_registration_form]' ),
		),
		'instructor-registration' => array(
			'fr' => array( 'title' => 'Inscription formateur', 'slug' => 'inscription-formateur', 'excerpt' => 'Creer un compte formateur.', 'content' => '[tutor_instructor_registration_form]' ),
			'es' => array( 'title' => 'Registro de instructor', 'slug' => 'registro-instructor', 'excerpt' => 'Crear una cuenta de instructor.', 'content' => '[tutor_instructor_registration_form]' ),
			'nl' => array( 'title' => 'Instructeurregistratie', 'slug' => 'instructeurregistratie', 'excerpt' => 'Maak een instructeursaccount aan.', 'content' => '[tutor_instructor_registration_form]' ),
		),
		'login'                   => array(
			'fr' => array( 'title' => 'Connexion', 'slug' => 'connexion', 'excerpt' => 'Connectez-vous a votre compte.', 'content' => '[ultimatemember form_id="33"]' ),
			'es' => array( 'title' => 'Iniciar sesion', 'slug' => 'iniciar-sesion', 'excerpt' => 'Inicie sesion en su cuenta.', 'content' => '[ultimatemember form_id="33"]' ),
			'nl' => array( 'title' => 'Aanmelden', 'slug' => 'aanmelden', 'excerpt' => 'Meld u aan bij uw account.', 'content' => '[ultimatemember form_id="33"]' ),
		),
		'register'                => array(
			'fr' => array( 'title' => 'Inscription', 'slug' => 'inscription', 'excerpt' => 'Creez votre compte membre.', 'content' => '[ultimatemember form_id="32"]' ),
			'es' => array( 'title' => 'Registro', 'slug' => 'registro', 'excerpt' => 'Cree su cuenta de miembro.', 'content' => '[ultimatemember form_id="32"]' ),
			'nl' => array( 'title' => 'Registreren', 'slug' => 'registreren', 'excerpt' => 'Maak uw ledenaccount aan.', 'content' => '[ultimatemember form_id="32"]' ),
		),
		'account'                 => array(
			'fr' => array( 'title' => 'Compte', 'slug' => 'compte', 'excerpt' => 'Gerez votre compte.', 'content' => '[ultimatemember_account]' ),
			'es' => array( 'title' => 'Cuenta', 'slug' => 'cuenta', 'excerpt' => 'Administre su cuenta.', 'content' => '[ultimatemember_account]' ),
			'nl' => array( 'title' => 'Account', 'slug' => 'account-nl', 'excerpt' => 'Beheer uw account.', 'content' => '[ultimatemember_account]' ),
		),
		'password-reset'          => array(
			'fr' => array( 'title' => 'Reinitialisation du mot de passe', 'slug' => 'reinitialisation-mot-de-passe', 'excerpt' => 'Reinitialisez votre mot de passe.', 'content' => '[ultimatemember_password]' ),
			'es' => array( 'title' => 'Restablecer contrasena', 'slug' => 'restablecer-contrasena', 'excerpt' => 'Restablezca su contrasena.', 'content' => '[ultimatemember_password]' ),
			'nl' => array( 'title' => 'Wachtwoord herstellen', 'slug' => 'wachtwoord-herstellen', 'excerpt' => 'Herstel uw wachtwoord.', 'content' => '[ultimatemember_password]' ),
		),
	);
}

/**
 * Returns translated homepage meta values.
 *
 * @return array<string,array<string,string>>
 */
function gsf_hub_translated_homepage_meta() {
	return array(
		'fr' => array(
			'hero_eyebrow' => 'Plateforme de connaissances du projet CORE',
			'hero_title' => 'Conservation pratique et sensible au genre pour les Caraibes.',
			'hero_text' => 'Apprendre, echanger et suivre les progres entre fonds fiduciaires de conservation, organisations de droits des femmes et partenaires de conservation.',
			'hero_primary_label' => 'Commencer a apprendre',
			'hero_secondary_label' => 'Parcourir les ressources',
			'explore_eyebrow' => 'Explorer le Hub',
			'explore_title' => 'Trouvez ce dont vous avez besoin en deux clics.',
			'explore_action_label' => 'Voir toutes les sections',
			'quick_link_1_title' => 'Modules de formation',
			'quick_link_1_text' => 'Cours en autonomie, quiz, suivi des progres et certificats.',
			'quick_link_2_title' => 'Bibliotheque de ressources',
			'quick_link_2_text' => 'Outils, modeles, documents de guidance, videos et packs hors ligne.',
			'quick_link_3_title' => 'Centre de donnees genre',
			'quick_link_3_text' => 'Tableaux de bord regionaux, televersements NCTF et rapports telechargeables.',
			'quick_link_4_title' => 'Echange entre pairs',
			'quick_link_4_text' => 'Discussions de forum, annuaire d experts et apprentissage communautaire.',
			'case_eyebrow' => 'Etude de cas en vedette',
			'case_title' => 'Histoires caribeennes, lecons pratiques, resultats reels.',
			'case_text' => 'Mettez en valeur les beneficiaires GSF, les initiatives NCTF et les approches communautaires de conservation.',
			'data_eyebrow' => 'Suivi genre et centre de donnees',
			'data_title' => 'Les progres regionaux en un coup d oeil.',
			'resource_eyebrow' => 'Bibliotheque de ressources',
			'resource_title' => 'Des outils prets pour le terrain.',
			'event_eyebrow' => 'Webinaire a venir',
			'event_title' => 'Integrer le genre dans la conservation marine',
			'event_text' => 'Inscription, rappels et archive des enregistrements integres a la plateforme.',
		),
		'es' => array(
			'hero_eyebrow' => 'Plataforma de conocimiento del proyecto CORE',
			'hero_title' => 'Conservacion practica con enfoque de genero para el Caribe.',
			'hero_text' => 'Aprenda, intercambie y siga el progreso entre fondos nacionales de conservacion, organizaciones de derechos de las mujeres y socios de conservacion.',
			'hero_primary_label' => 'Comenzar a aprender',
			'hero_secondary_label' => 'Explorar recursos',
			'explore_eyebrow' => 'Explore el Hub',
			'explore_title' => 'Encuentre lo que necesita en dos clics.',
			'explore_action_label' => 'Ver todas las secciones',
			'quick_link_1_title' => 'Modulos de aprendizaje',
			'quick_link_1_text' => 'Cursos a su propio ritmo, cuestionarios, seguimiento de progreso y certificados.',
			'quick_link_2_title' => 'Biblioteca de recursos',
			'quick_link_2_text' => 'Herramientas, plantillas, guias, videos y paquetes sin conexion.',
			'quick_link_3_title' => 'Centro de datos de genero',
			'quick_link_3_text' => 'Paneles regionales, cargas de NCTF e informes descargables.',
			'quick_link_4_title' => 'Intercambio entre pares',
			'quick_link_4_text' => 'Foros de discusion, directorio de expertos y aprendizaje comunitario.',
			'case_eyebrow' => 'Estudio de caso destacado',
			'case_title' => 'Historias del Caribe, lecciones practicas, resultados reales.',
			'case_text' => 'Destaque beneficiarios GSF, iniciativas NCTF y enfoques comunitarios de conservacion.',
			'data_eyebrow' => 'Monitoreo de genero y centro de datos',
			'data_title' => 'El progreso regional de un vistazo.',
			'resource_eyebrow' => 'Biblioteca de recursos',
			'resource_title' => 'Herramientas listas para el campo.',
			'event_eyebrow' => 'Proximo seminario web',
			'event_title' => 'Integrar el genero en la conservacion marina',
			'event_text' => 'Registro, recordatorios y archivo de grabaciones integrados con la plataforma.',
		),
		'nl' => array(
			'hero_eyebrow' => 'Kennisplatform van het CORE-project',
			'hero_title' => 'Praktisch genderslim natuurbeheer voor het Caribisch gebied.',
			'hero_text' => 'Leer, wissel uit en volg voortgang tussen nationale natuurfondsen, vrouwenrechtenorganisaties en natuurpartners.',
			'hero_primary_label' => 'Begin met leren',
			'hero_secondary_label' => 'Bekijk bronnen',
			'explore_eyebrow' => 'Verken de Hub',
			'explore_title' => 'Vind wat u nodig hebt in twee klikken.',
			'explore_action_label' => 'Bekijk alle secties',
			'quick_link_1_title' => 'Leermodules',
			'quick_link_1_text' => 'Zelfstudiecursussen, quizzen, voortgangsregistratie en certificaten.',
			'quick_link_2_title' => 'Bronnenbibliotheek',
			'quick_link_2_text' => 'Tools, sjablonen, richtlijnen, videos en offline pakketten.',
			'quick_link_3_title' => 'Genderdatacentrum',
			'quick_link_3_text' => 'Regionale dashboards, NCTF-uploads en downloadbare rapporten.',
			'quick_link_4_title' => 'Peer exchange',
			'quick_link_4_text' => 'Forumgesprekken, expertengids en leren binnen de gemeenschap.',
			'case_eyebrow' => 'Uitgelicht praktijkverhaal',
			'case_title' => 'Caribische verhalen, praktische lessen, echte resultaten.',
			'case_text' => 'Toon GSF-begunstigden, NCTF-initiatieven en gemeenschapsgerichte natuurbenaderingen.',
			'data_eyebrow' => 'Gendermonitoring en datacentrum',
			'data_title' => 'Regionale voortgang in een oogopslag.',
			'resource_eyebrow' => 'Bronnenbibliotheek',
			'resource_title' => 'Tools klaar voor gebruik in het veld.',
			'event_eyebrow' => 'Aankomend webinar',
			'event_title' => 'Gender integreren in mariene conservatie',
			'event_text' => 'Registratie, herinneringen en opnamearchief geintegreerd met het platform.',
		),
	);
}

/**
 * Finds a page by slug without depending on current language filtering.
 *
 * @param string $slug Page slug.
 * @return WP_Post|null
 */
function gsf_hub_translated_find_page_by_slug( $slug ) {
	$posts = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 1,
			'suppress_filters' => true,
		)
	);

	return ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ? $posts[0] : null;
}

/**
 * Creates or updates one translated page set.
 *
 * @param string                              $source_slug  English source slug.
 * @param array<string,array<string,string>> $translations Translation payload.
 * @return array<string,int>
 */
function gsf_hub_translated_upsert_page_set( $source_slug, array $translations ) {
	$source = gsf_hub_translated_find_page_by_slug( $source_slug );

	if ( ! $source instanceof WP_Post ) {
		WP_CLI::warning( sprintf( 'Source page not found: %s', $source_slug ) );
		return array();
	}

	pll_set_post_language( $source->ID, 'en' );

	$linked = pll_get_post_translations( $source->ID );

	if ( empty( $linked['en'] ) ) {
		$linked['en'] = (int) $source->ID;
	}

	$template = get_post_meta( $source->ID, '_wp_page_template', true );
	$results  = array( 'en' => (int) $source->ID );

	foreach ( $translations as $lang => $payload ) {
		$page_id = ! empty( $linked[ $lang ] ) ? (int) $linked[ $lang ] : 0;
		$page    = $page_id ? get_post( $page_id ) : null;

		if ( ! $page instanceof WP_Post ) {
			$existing = gsf_hub_translated_find_page_by_slug( $payload['slug'] );
			$page     = $existing instanceof WP_Post ? $existing : null;
		}

		$postarr = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $payload['title'],
			'post_name'    => $payload['slug'],
			'post_excerpt' => $payload['excerpt'] ?? '',
			'post_content' => $payload['content'],
			'post_author'  => (int) $source->post_author,
			'menu_order'   => (int) $source->menu_order,
		);

		if ( $page instanceof WP_Post ) {
			$postarr['ID'] = $page->ID;
			$updated       = wp_update_post( $postarr, true );
			$page_id       = is_wp_error( $updated ) ? 0 : (int) $updated;
		} else {
			$created = wp_insert_post( $postarr, true );
			$page_id = is_wp_error( $created ) ? 0 : (int) $created;
		}

		if ( ! $page_id ) {
			WP_CLI::warning( sprintf( 'Could not save %s translation for %s.', strtoupper( $lang ), $source_slug ) );
			continue;
		}

		if ( $template ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
		}

		pll_set_post_language( $page_id, $lang );
		$linked[ $lang ] = $page_id;
		$results[ $lang ] = $page_id;
	}

	pll_save_post_translations( $linked );

	return $results;
}

/**
 * Updates translated homepage custom fields.
 *
 * @return array<string,int>
 */
function gsf_hub_translated_update_homepage_meta() {
	$front_page_id = (int) get_option( 'page_on_front' );

	if ( ! $front_page_id ) {
		return array();
	}

	$translations = pll_get_post_translations( $front_page_id );
	$updated      = array();
	$removed_copy_filter = false;

	if ( function_exists( 'gsf_hub_polylang_copy_homepage_meta_keys' ) ) {
		$removed_copy_filter = remove_filter( 'pll_copy_post_metas', 'gsf_hub_polylang_copy_homepage_meta_keys' );
	}

	foreach ( gsf_hub_translated_homepage_meta() as $lang => $values ) {
		if ( empty( $translations[ $lang ] ) ) {
			continue;
		}

		foreach ( $values as $key => $value ) {
			update_post_meta( (int) $translations[ $lang ], 'gsf_hub_' . $key, $value );
		}

		$updated[ $lang ] = (int) $translations[ $lang ];
	}

	if ( $removed_copy_filter ) {
		add_filter( 'pll_copy_post_metas', 'gsf_hub_polylang_copy_homepage_meta_keys' );
	}

	return $updated;
}

$saved = array();

foreach ( gsf_hub_translated_page_blueprints() as $source_slug => $translations ) {
	$saved[ $source_slug ] = gsf_hub_translated_upsert_page_set( $source_slug, $translations );
}

$saved['home_meta'] = gsf_hub_translated_update_homepage_meta();

if ( isset( PLL()->static_pages ) && method_exists( PLL()->static_pages, 'clean_cache' ) ) {
	PLL()->static_pages->clean_cache();
}

flush_rewrite_rules();

WP_CLI::success( wp_json_encode( $saved ) );
