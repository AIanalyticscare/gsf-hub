<?php
/**
 * Provisions a bbPress-powered forum page and starter community structure.
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! function_exists( 'bbp_insert_forum' ) || ! function_exists( 'bbp_insert_topic' ) ) {
	WP_CLI::error( 'bbPress must be installed and active before running this script.' );
}

/**
 * Returns a stable author ID for seeded forum content.
 *
 * @return int
 */
function gsf_hub_forum_seed_author_id() {
	$admin_users = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ids',
		)
	);

	if ( ! empty( $admin_users[0] ) ) {
		return (int) $admin_users[0];
	}

	return 1;
}

/**
 * Returns forum page blueprints in each supported language.
 *
 * @return array<string, array<string, string>>
 */
function gsf_hub_forum_page_blueprints() {
	return array(
		'en' => array(
			'title'   => 'Forum',
			'slug'    => 'forum',
			'excerpt' => 'Join topical discussions, ask questions, and share practical learning across the GSF community.',
			'intro'   => 'Join topical discussions, ask questions, and share practical learning across the GSF community.',
			'signin'  => 'Sign in with your member account to create topics and reply to discussions.',
			'guide'   => 'Choose a forum below to start a topic, reply to an ongoing discussion, or follow community updates.',
			'search'  => 'Search the community',
			'latest'  => 'Latest conversations',
		),
		'fr' => array(
			'title'   => 'Forum',
			'slug'    => 'forum-communaute',
			'excerpt' => 'Participez aux discussions, posez des questions et partagez des apprentissages pratiques au sein de la communaute GSF.',
			'intro'   => 'Participez aux discussions, posez des questions et partagez des apprentissages pratiques au sein de la communaute GSF.',
			'signin'  => 'Connectez-vous avec votre compte membre pour creer des sujets et repondre aux discussions.',
			'guide'   => 'Choisissez un forum ci-dessous pour lancer un sujet, repondre a une discussion en cours ou suivre les mises a jour de la communaute.',
			'search'  => 'Rechercher dans la communaute',
			'latest'  => 'Dernieres conversations',
		),
		'es' => array(
			'title'   => 'Foro',
			'slug'    => 'foro',
			'excerpt' => 'Participe en conversaciones tematicas, haga preguntas y comparta aprendizaje practico con la comunidad GSF.',
			'intro'   => 'Participe en conversaciones tematicas, haga preguntas y comparta aprendizaje practico con la comunidad GSF.',
			'signin'  => 'Inicie sesion con su cuenta de miembro para crear temas y responder en las discusiones.',
			'guide'   => 'Elija un foro abajo para iniciar un tema, responder a una conversacion en curso o seguir las novedades de la comunidad.',
			'search'  => 'Buscar en la comunidad',
			'latest'  => 'Conversaciones recientes',
		),
		'nl' => array(
			'title'   => 'Forum',
			'slug'    => 'gemeenschapsforum',
			'excerpt' => 'Neem deel aan thematische gesprekken, stel vragen en deel praktijkgericht leren binnen de GSF-gemeenschap.',
			'intro'   => 'Neem deel aan thematische gesprekken, stel vragen en deel praktijkgericht leren binnen de GSF-gemeenschap.',
			'signin'  => 'Meld u aan met uw ledenaccount om onderwerpen te starten en op gesprekken te reageren.',
			'guide'   => 'Kies hieronder een forum om een onderwerp te starten, op een lopend gesprek te reageren of updates uit de gemeenschap te volgen.',
			'search'  => 'Zoek in de gemeenschap',
			'latest'  => 'Recente gesprekken',
		),
	);
}

/**
 * Builds the forum page content from a blueprint.
 *
 * @param array<string, string> $blueprint Blueprint values.
 * @return string
 */
function gsf_hub_forum_page_content( $blueprint ) {
	unset( $blueprint );

	return "<!-- wp:shortcode -->\n[bbp-forum-index]\n<!-- /wp:shortcode -->";
}

/**
 * Creates or updates translated forum pages.
 *
 * @return array<string, int>
 */
function gsf_hub_ensure_forum_pages() {
	$blueprints   = gsf_hub_forum_page_blueprints();
	$page_ids     = array();
	$translations = array();
	$has_polylang = function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' );

	foreach ( $blueprints as $language => $blueprint ) {
		$page = get_page_by_path( $blueprint['slug'], OBJECT, 'page' );

		if ( ! $page instanceof WP_Post && 'en' !== $language && $has_polylang && function_exists( 'pll_switch_language' ) ) {
			pll_switch_language( $language );
			$page = get_page_by_path( $blueprint['slug'], OBJECT, 'page' );
			pll_switch_language( 'en' );
		}

		$created = false;

		if ( ! $page instanceof WP_Post ) {
			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $blueprint['title'],
					'post_name'    => $blueprint['slug'],
					'post_excerpt' => $blueprint['excerpt'],
					'post_content' => gsf_hub_forum_page_content( $blueprint ),
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				WP_CLI::error( sprintf( 'Failed to create the %s forum page: %s', strtoupper( $language ), $page_id->get_error_message() ) );
			}

			$page    = get_post( $page_id );
			$created = true;
		}

		if ( ! $page instanceof WP_Post ) {
			continue;
		}

		$update_args = array(
			'ID'         => $page->ID,
			'post_title' => $blueprint['title'],
			'post_name'  => $blueprint['slug'],
		);

		if ( '' === trim( (string) $page->post_excerpt ) ) {
			$update_args['post_excerpt'] = $blueprint['excerpt'];
		}

		if ( false !== strpos( (string) $page->post_content, '[bbp-topic-index' ) || false !== strpos( (string) $page->post_content, '[bbp-search-form]' ) || false !== strpos( (string) $page->post_content, '[bbp-search]' ) || false !== strpos( (string) $page->post_content, $blueprint['intro'] ) ) {
			$update_args['post_content'] = gsf_hub_forum_page_content( $blueprint );
		} elseif ( $created || '' === trim( (string) $page->post_content ) ) {
			$update_args['post_content'] = gsf_hub_forum_page_content( $blueprint );
		}

		wp_update_post( $update_args );
		update_post_meta( $page->ID, '_wp_page_template', 'template-forum.php' );

		if ( $has_polylang ) {
			pll_set_post_language( $page->ID, $language );
			$translations[ $language ] = (int) $page->ID;
		}

		$page_ids[ $language ] = (int) $page->ID;
	}

	if ( $has_polylang && count( $translations ) > 1 ) {
		pll_save_post_translations( $translations );
	}

	return $page_ids;
}

/**
 * Returns starter forum blueprints.
 *
 * @param string $language Language slug.
 * @return array<string, array<string, string>>
 */
function gsf_hub_forum_blueprints( $language = 'en' ) {
	$blueprints = array(
		'en' => array(
			'general-discussion' => array(
			'title'       => 'General Discussion',
			'slug'        => 'general-discussion',
			'description' => 'Open discussion for announcements, introductions, and cross-cutting community questions.',
			),
			'training-and-learning-exchange' => array(
			'title'       => 'Training and Learning Exchange',
			'slug'        => 'training-and-learning-exchange',
			'description' => 'Discuss courses, learning priorities, webinar takeaways, and training needs across the network.',
			),
			'expert-roster-and-partnerships' => array(
			'title'       => 'Expert Roster and Partnerships',
			'slug'        => 'expert-roster-and-partnerships',
			'description' => 'Exchange opportunities for collaboration, advisory support, and practitioner matchmaking.',
			),
			'resources-tools-and-field-practice' => array(
			'title'       => 'Resources, Tools, and Field Practice',
			'slug'        => 'resources-tools-and-field-practice',
			'description' => 'Share templates, field tools, reporting approaches, and practical lessons from implementation.',
			),
			'events-and-webinars' => array(
			'title'       => 'Events and Webinars',
			'slug'        => 'events-and-webinars',
			'description' => 'Coordinate upcoming sessions, post event reminders, and continue webinar discussions.',
			),
		),
		'fr' => array(
			'general-discussion' => array( 'title' => 'Discussion generale', 'slug' => 'discussion-generale', 'description' => 'Espace ouvert pour les annonces, presentations et questions transversales de la communaute.' ),
			'training-and-learning-exchange' => array( 'title' => 'Formation et apprentissage', 'slug' => 'formation-apprentissage', 'description' => 'Discutez des cours, priorites d apprentissage, webinaires et besoins de formation du reseau.' ),
			'expert-roster-and-partnerships' => array( 'title' => 'Experts et partenariats', 'slug' => 'experts-partenariats', 'description' => 'Echangez des opportunites de collaboration, d appui-conseil et de mise en relation.' ),
			'resources-tools-and-field-practice' => array( 'title' => 'Ressources, outils et pratique terrain', 'slug' => 'ressources-outils-pratique-terrain', 'description' => 'Partagez modeles, outils terrain, methodes de rapportage et lecons pratiques.' ),
			'events-and-webinars' => array( 'title' => 'Evenements et webinaires', 'slug' => 'evenements-webinaires', 'description' => 'Coordonnez les sessions a venir, publiez des rappels et poursuivez les discussions apres webinaire.' ),
		),
		'es' => array(
			'general-discussion' => array( 'title' => 'Discusion general', 'slug' => 'discusion-general', 'description' => 'Espacio abierto para anuncios, presentaciones y preguntas transversales de la comunidad.' ),
			'training-and-learning-exchange' => array( 'title' => 'Formacion e intercambio de aprendizaje', 'slug' => 'formacion-aprendizaje', 'description' => 'Converse sobre cursos, prioridades de aprendizaje, seminarios web y necesidades de formacion.' ),
			'expert-roster-and-partnerships' => array( 'title' => 'Expertos y alianzas', 'slug' => 'expertos-alianzas', 'description' => 'Intercambie oportunidades de colaboracion, apoyo tecnico y conexiones entre profesionales.' ),
			'resources-tools-and-field-practice' => array( 'title' => 'Recursos, herramientas y practica de campo', 'slug' => 'recursos-herramientas-practica-campo', 'description' => 'Comparta plantillas, herramientas de campo, enfoques de informes y lecciones practicas.' ),
			'events-and-webinars' => array( 'title' => 'Eventos y seminarios web', 'slug' => 'eventos-seminarios-web', 'description' => 'Coordine proximas sesiones, publique recordatorios y continue conversaciones de seminarios web.' ),
		),
		'nl' => array(
			'general-discussion' => array( 'title' => 'Algemene discussie', 'slug' => 'algemene-discussie', 'description' => 'Open ruimte voor aankondigingen, introducties en brede vragen uit de gemeenschap.' ),
			'training-and-learning-exchange' => array( 'title' => 'Training en kennisuitwisseling', 'slug' => 'training-kennisuitwisseling', 'description' => 'Bespreek cursussen, leerprioriteiten, webinars en trainingsbehoeften in het netwerk.' ),
			'expert-roster-and-partnerships' => array( 'title' => 'Experts en partnerschappen', 'slug' => 'experts-partnerschappen', 'description' => 'Wissel kansen uit voor samenwerking, advies en verbinding tussen professionals.' ),
			'resources-tools-and-field-practice' => array( 'title' => 'Bronnen, tools en veldpraktijk', 'slug' => 'bronnen-tools-veldpraktijk', 'description' => 'Deel sjablonen, veldtools, rapportageaanpakken en praktische lessen.' ),
			'events-and-webinars' => array( 'title' => 'Evenementen en webinars', 'slug' => 'evenementen-webinars', 'description' => 'Stem komende sessies af, plaats herinneringen en zet webinargesprekken voort.' ),
		),
	);

	return $blueprints[ $language ] ?? $blueprints['en'];
}

/**
 * Creates starter forums when they do not exist yet.
 *
 * @param int $author_id Author ID.
 * @return array<string, int>
 */
function gsf_hub_ensure_forum_structure( $author_id ) {
	$forum_ids        = array();
	$translations_map = array();
	$languages        = function_exists( 'pll_languages_list' ) ? pll_languages_list() : array( 'en' );

	foreach ( $languages as $language ) {
		foreach ( gsf_hub_forum_blueprints( $language ) as $key => $blueprint ) {
			$existing = get_page_by_path( $blueprint['slug'], OBJECT, bbp_get_forum_post_type() );

			if ( $existing instanceof WP_Post ) {
				$forum_id = (int) $existing->ID;
			} else {
				$forum_id = bbp_insert_forum(
					array(
						'post_title'   => $blueprint['title'],
						'post_name'    => $blueprint['slug'],
						'post_content' => $blueprint['description'],
						'post_author'  => $author_id,
					)
				);

				if ( empty( $forum_id ) ) {
					WP_CLI::warning( sprintf( 'Could not create forum: %s', $blueprint['title'] ) );
					continue;
				}
			}

			if ( function_exists( 'pll_set_post_language' ) ) {
				pll_set_post_language( (int) $forum_id, $language );
				$translations_map[ $key ][ $language ] = (int) $forum_id;
			}

			$forum_ids[ $language ][ $key ] = (int) $forum_id;
		}
	}

	if ( function_exists( 'pll_save_post_translations' ) ) {
		foreach ( $translations_map as $translations ) {
			if ( count( $translations ) > 1 ) {
				pll_save_post_translations( $translations );
			}
		}
	}

	return $forum_ids;
}

/**
 * Returns starter topics keyed by slug.
 *
 * @param string $language Language slug.
 * @return array<int, array<string, string>>
 */
function gsf_hub_topic_blueprints( $language = 'en' ) {
	$blueprints = array(
		'en' => array(
			array(
			'title'       => 'Welcome to the GSF community forum',
			'slug'        => 'welcome-to-the-gsf-community-forum',
			'forum_slug'  => 'general-discussion',
			'content'     => 'Use this space to introduce yourself, share what area of work you represent, and suggest how the forum can support collaboration across the GSF network.',
			),
			array(
			'title'       => 'What training topics would help your work right now?',
			'slug'        => 'what-training-topics-would-help-your-work-right-now',
			'forum_slug'  => 'training-and-learning-exchange',
			'content'     => 'Share priority learning needs, course ideas, or webinar themes that would be most useful to your team or institution this year.',
			),
			array(
			'title'       => 'Where could expert collaboration add the most value?',
			'slug'        => 'where-could-expert-collaboration-add-the-most-value',
			'forum_slug'  => 'expert-roster-and-partnerships',
			'content'     => 'Use this discussion to highlight advisory gaps, possible peer exchanges, or cross-country collaboration opportunities that the expert roster should support.',
			),
		),
		'fr' => array(
			array( 'title' => 'Bienvenue dans le forum communautaire GSF', 'slug' => 'bienvenue-forum-communautaire-gsf', 'forum_slug' => 'general-discussion', 'content' => 'Utilisez cet espace pour vous presenter, partager votre domaine de travail et proposer comment le forum peut soutenir la collaboration dans le reseau GSF.' ),
			array( 'title' => 'Quels themes de formation aideraient votre travail maintenant?', 'slug' => 'themes-formation-utiles-maintenant', 'forum_slug' => 'training-and-learning-exchange', 'content' => 'Partagez les besoins d apprentissage, idees de cours ou themes de webinaires les plus utiles pour votre equipe ou institution.' ),
			array( 'title' => 'Ou la collaboration avec des experts ajouterait-elle le plus de valeur?', 'slug' => 'collaboration-experts-valeur', 'forum_slug' => 'expert-roster-and-partnerships', 'content' => 'Soulignez les besoins d appui-conseil, les echanges entre pairs ou les collaborations transnationales que le registre d experts devrait soutenir.' ),
		),
		'es' => array(
			array( 'title' => 'Bienvenida al foro comunitario de GSF', 'slug' => 'bienvenida-foro-comunitario-gsf', 'forum_slug' => 'general-discussion', 'content' => 'Use este espacio para presentarse, compartir su area de trabajo y sugerir como el foro puede apoyar la colaboracion en la red GSF.' ),
			array( 'title' => 'Que temas de formacion ayudarian a su trabajo ahora?', 'slug' => 'temas-formacion-utiles-ahora', 'forum_slug' => 'training-and-learning-exchange', 'content' => 'Comparta necesidades de aprendizaje, ideas de cursos o temas de seminarios web utiles para su equipo o institucion.' ),
			array( 'title' => 'Donde podria aportar mas valor la colaboracion con expertos?', 'slug' => 'colaboracion-expertos-valor', 'forum_slug' => 'expert-roster-and-partnerships', 'content' => 'Use esta conversacion para destacar brechas de asesoria, intercambios entre pares o colaboraciones entre paises.' ),
		),
		'nl' => array(
			array( 'title' => 'Welkom op het GSF gemeenschapsforum', 'slug' => 'welkom-gsf-gemeenschapsforum', 'forum_slug' => 'general-discussion', 'content' => 'Gebruik deze ruimte om uzelf voor te stellen, uw werkgebied te delen en te zeggen hoe het forum samenwerking in het GSF-netwerk kan ondersteunen.' ),
			array( 'title' => 'Welke trainingsthemas zouden uw werk nu helpen?', 'slug' => 'trainingsthemas-helpen-werk-nu', 'forum_slug' => 'training-and-learning-exchange', 'content' => 'Deel leerbehoeften, cursusideeen of webinarthemas die nuttig zijn voor uw team of instelling.' ),
			array( 'title' => 'Waar kan samenwerking met experts de meeste waarde toevoegen?', 'slug' => 'samenwerking-experts-waarde', 'forum_slug' => 'expert-roster-and-partnerships', 'content' => 'Gebruik deze discussie om adviesbehoeften, peer exchanges of grensoverschrijdende samenwerking te benoemen.' ),
		),
	);

	return $blueprints[ $language ] ?? $blueprints['en'];
}

/**
 * Creates starter topics in the seeded forums.
 *
 * @param int              $author_id Author ID.
 * @param array<string,int> $forum_ids Forum IDs keyed by blueprint slug.
 * @return array<string, int>
 */
function gsf_hub_ensure_forum_topics( $author_id, $forum_ids ) {
	$topic_ids        = array();
	$translations_map = array();
	$languages        = function_exists( 'pll_languages_list' ) ? pll_languages_list() : array( 'en' );

	foreach ( $languages as $language ) {
		foreach ( gsf_hub_topic_blueprints( $language ) as $blueprint ) {
			if ( empty( $forum_ids[ $language ][ $blueprint['forum_slug'] ] ) ) {
				continue;
			}

			$existing = get_page_by_path( $blueprint['slug'], OBJECT, bbp_get_topic_post_type() );

			if ( $existing instanceof WP_Post ) {
				$topic_id = (int) $existing->ID;
			} else {
				$topic_id = bbp_insert_topic(
					array(
						'post_title'   => $blueprint['title'],
						'post_name'    => $blueprint['slug'],
						'post_content' => $blueprint['content'],
						'post_author'  => $author_id,
					),
					array(
						'forum_id' => (int) $forum_ids[ $language ][ $blueprint['forum_slug'] ],
					)
				);

				if ( empty( $topic_id ) ) {
					WP_CLI::warning( sprintf( 'Could not create topic: %s', $blueprint['title'] ) );
					continue;
				}
			}

			if ( function_exists( 'pll_set_post_language' ) ) {
				pll_set_post_language( (int) $topic_id, $language );
				$translations_map[ $blueprint['forum_slug'] ][ $language ] = (int) $topic_id;
			}

			$topic_ids[ $language ][ $blueprint['slug'] ] = (int) $topic_id;
		}
	}

	if ( function_exists( 'pll_save_post_translations' ) ) {
		foreach ( $translations_map as $translations ) {
			if ( count( $translations ) > 1 ) {
				pll_save_post_translations( $translations );
			}
		}
	}

	return $topic_ids;
}

$author_id = gsf_hub_forum_seed_author_id();
$page_ids  = gsf_hub_ensure_forum_pages();
$forum_ids = gsf_hub_ensure_forum_structure( $author_id );
$topic_ids = gsf_hub_ensure_forum_topics( $author_id, $forum_ids );

flush_rewrite_rules();

WP_CLI::success(
	wp_json_encode(
		array(
			'forum_pages'  => $page_ids,
			'forums'       => $forum_ids,
			'topics'       => $topic_ids,
			'author_id'    => $author_id,
		)
	)
);
