<?php
/**
 * Self-contained recommendation engine for the GSF Resource Assistant.
 *
 * @package GSF_Resource_Assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GSF_Resource_Recommendation_Engine {
	const MISSING_VALUE = 'Not stated in the source record';

	/**
	 * Build ranked, cited recommendations from the controlled WordPress corpus.
	 *
	 * @param array  $posts     Public corpus records.
	 * @param string $situation User question.
	 * @param int    $top_k     Maximum result count.
	 * @return array
	 */
	public static function recommend( $posts, $situation, $top_k = 6 ) {
		$situation = trim( wp_strip_all_tags( (string) $situation, true ) );
		$top_k     = max( 1, min( 8, absint( $top_k ) ) );
		$resources = self::prepare_resources( is_array( $posts ) ? $posts : array() );
		$warnings  = array();

		if ( empty( $resources ) ) {
			$warnings[] = __( 'The controlled WordPress library does not contain any supported public content.', 'gsf-resource-assistant' );
		}

		$query_tokens = self::tokenize( $situation, true );
		$query_raw    = self::tokenize( $situation, false );
		$ranked       = self::rank_resources( $resources, $query_tokens, $query_raw, $situation );
		$ranked       = self::deduplicate_titles( $ranked );
		$ranked       = self::diversify( $ranked, $top_k, $query_raw );

		if ( empty( $ranked ) && ! empty( $resources ) ) {
			$warnings[] = __( 'No sufficiently supported match was found. Try adding a role, location, topic, or type of help.', 'gsf-resource-assistant' );
		}

		return array(
			'query'           => array( 'situation' => $situation ),
			'recommendations' => array_slice( $ranked, 0, $top_k ),
			'warnings'        => $warnings,
			'engine'          => 'wordpress-php',
			'library_count'   => count( $resources ),
		);
	}

	private static function prepare_resources( $posts ) {
		$resources = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || 'publish' !== ( $post['post_status'] ?? '' ) ) {
				continue;
			}
			$resource = self::prepare_resource( $post );
			if ( $resource ) {
				$resources[] = $resource;
			}
		}
		return $resources;
	}

	private static function prepare_resource( $post ) {
		$post_type = sanitize_key( $post['post_type'] ?? '' );
		$title     = trim( html_entity_decode( (string) ( $post['post_title'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$meta      = isset( $post['meta'] ) && is_array( $post['meta'] ) ? $post['meta'] : array();
		if ( '' === $title || '' === $post_type ) {
			return null;
		}

		$description = trim( (string) ( $post['post_excerpt'] ?? '' ) . ' ' . (string) ( $post['post_content'] ?? '' ) );
		$description = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $description, true ) );
		if ( strlen( $description ) < 10 ) {
			return null;
		}

		$topics = array_merge(
			self::meta_values( $meta, array( 'topic', 'focus_area', 'specialization', 'expertise', 'project_type', 'indicator_type', 'result_level', 'activity_type', 'assessment_stage' ) ),
			isset( $post['topics'] ) && is_array( $post['topics'] ) ? $post['topics'] : array()
		);
		$topics      = self::unique_strings( $topics );
		$locations   = self::meta_values( $meta, array( 'country_region', 'country', 'gsf_story_country' ) );
		$target_users = self::meta_values( $meta, array( 'target_users', 'audience', 'target_population', 'organization_type' ) );
		$sectors     = self::meta_values( $meta, array( 'sectors', 'sector' ) );
		$type        = self::resource_type( $post_type, $meta );
		$url         = esc_url_raw( (string) ( $post['url'] ?? self::first_meta( $meta, array( 'external_url', 'gsf_story_url' ) ) ) );

		$search_text = implode(
			' ',
			array(
				$title,
				$title,
				$title,
				$type,
				implode( ' ', $topics ),
				implode( ' ', $topics ),
				implode( ' ', $locations ),
				implode( ' ', $target_users ),
				implode( ' ', $sectors ),
				$description,
			)
		);

		return array(
			'title'             => $title,
			'description'       => $description,
			'resource_type'     => $type,
			'group'             => self::content_group( $type ),
			'topics'            => $topics,
			'locations'         => self::unique_strings( $locations ),
			'target_users'      => self::unique_strings( $target_users ),
			'sectors'           => self::unique_strings( $sectors ),
			'eligibility'       => self::meta_values( $meta, array( 'eligibility_rules', 'eligibility' ) ),
			'financial_support' => self::first_meta( $meta, array( 'financial_support' ) ),
			'deadline'          => self::first_meta( $meta, array( 'application_deadline', 'deadline' ) ),
			'contact'           => self::first_meta( $meta, array( 'contact_information', 'contact' ) ),
			'updated_at'        => sanitize_text_field( (string) ( $post['post_modified'] ?? '' ) ),
			'url'               => $url,
			'next_step'         => self::next_step( $post_type ),
			'tokens'            => self::token_counts( self::tokenize( $search_text, false ) ),
			'title_tokens'      => self::tokenize( $title, false ),
			'topic_tokens'      => self::tokenize( implode( ' ', $topics ), false ),
		);
	}

	private static function rank_resources( $resources, $query_tokens, $query_raw, $situation ) {
		if ( empty( $resources ) || empty( $query_tokens ) ) {
			return array();
		}

		$query_counts = self::token_counts( $query_tokens );
		$document_frequency = array();
		foreach ( $resources as $resource ) {
			foreach ( array_keys( $resource['tokens'] ) as $token ) {
				$document_frequency[ $token ] = ( $document_frequency[ $token ] ?? 0 ) + 1;
			}
		}

		$document_count = max( 1, count( $resources ) );
		$idf            = array();
		foreach ( $document_frequency as $token => $frequency ) {
			$idf[ $token ] = log( ( $document_count + 1 ) / ( $frequency + 1 ) ) + 1;
		}

		$query_vector = self::weighted_vector( $query_counts, $idf );
		$query_norm   = self::vector_norm( $query_vector );
		$ranked       = array();

		foreach ( $resources as $resource ) {
			$matched_terms = array_values( array_intersect( array_keys( $query_counts ), array_keys( $resource['tokens'] ) ) );
			if ( empty( $matched_terms ) ) {
				continue;
			}

			$resource_vector = self::weighted_vector( $resource['tokens'], $idf );
			$similarity      = self::cosine( $query_vector, $query_norm, $resource_vector );
			$title_overlap   = self::overlap_ratio( $query_raw, $resource['title_tokens'] );
			$topic_overlap   = self::overlap_ratio( $query_tokens, $resource['topic_tokens'] );
			$location_match  = self::first_phrase_match( $situation, $resource['locations'] );
			$target_match    = self::first_phrase_match( $situation, $resource['target_users'] );
			$sector_match    = self::first_phrase_match( $situation, $resource['sectors'] );

			$score = 0.08 + ( $similarity * 0.72 ) + ( $title_overlap * 0.12 ) + ( $topic_overlap * 0.08 );
			$score += self::intent_bonus( $resource['group'], $query_tokens );
			$score += self::type_specific_bonus( $resource['resource_type'], $query_tokens );
			if ( $location_match ) {
				$score += 0.07;
			}
			if ( $target_match ) {
				$score += 0.06;
			}
			if ( $sector_match ) {
				$score += 0.04;
			}

			$ranked[] = self::build_recommendation(
				$resource,
				min( 1, $score ),
				$matched_terms,
				$location_match,
				$target_match,
				$sector_match
			);
		}

		usort(
			$ranked,
			function ( $left, $right ) {
				return $right['fit_score'] <=> $left['fit_score'];
			}
		);
		return $ranked;
	}

	private static function build_recommendation( $resource, $score, $matched_terms, $location_match, $target_match, $sector_match ) {
		$reasons       = array();
		$barriers      = array();
		$uncertainties = array();
		$application   = self::is_application_resource( $resource['resource_type'] );

		if ( $location_match ) {
			$reasons[] = sprintf( __( 'Location coverage includes %s.', 'gsf-resource-assistant' ), $location_match );
		}
		if ( $target_match ) {
			$reasons[] = sprintf( __( 'Designed for %s.', 'gsf-resource-assistant' ), $target_match );
		}
		if ( $sector_match ) {
			$reasons[] = sprintf( __( 'Relevant to the %s sector.', 'gsf-resource-assistant' ), $sector_match );
		}

		$matched_topics = self::matched_labels( $matched_terms, $resource['topics'] );
		if ( ! empty( $matched_topics ) ) {
			$reasons[] = sprintf( __( 'Addresses %s.', 'gsf-resource-assistant' ), implode( ', ', array_slice( $matched_topics, 0, 3 ) ) );
		}
		if ( empty( $reasons ) ) {
			$reasons[] = sprintf(
				__( 'Covers related themes: %s.', 'gsf-resource-assistant' ),
				implode( ', ', array_slice( $matched_terms, 0, 6 ) )
			);
		}

		foreach ( $resource['eligibility'] as $rule ) {
			$barriers[] = sprintf( __( 'Requirement: %s', 'gsf-resource-assistant' ), $rule );
		}

		if ( $application ) {
			if ( empty( $resource['eligibility'] ) ) {
				$uncertainties[] = __( 'Eligibility rules are missing from the source record.', 'gsf-resource-assistant' );
			}
			if ( '' === $resource['contact'] ) {
				$uncertainties[] = __( 'Contact information is missing from the source record.', 'gsf-resource-assistant' );
			}
			if ( '' === $resource['financial_support'] ) {
				$uncertainties[] = __( 'The source does not state whether financial support is included.', 'gsf-resource-assistant' );
			}
			if ( '' === $resource['deadline'] ) {
				$uncertainties[] = __( 'No application deadline is stated; current availability must be confirmed.', 'gsf-resource-assistant' );
			}
		}

		$deadline_timestamp = '' !== $resource['deadline'] ? strtotime( $resource['deadline'] ) : false;
		if ( $deadline_timestamp && $deadline_timestamp < current_time( 'timestamp', true ) ) {
			$barriers[] = sprintf(
				__( 'The recorded deadline (%s) has passed.', 'gsf-resource-assistant' ),
				gmdate( 'Y-m-d', $deadline_timestamp )
			);
		}

		$updated_timestamp = '' !== $resource['updated_at'] ? strtotime( $resource['updated_at'] . ' UTC' ) : false;
		if ( $updated_timestamp && $updated_timestamp < ( current_time( 'timestamp', true ) - YEAR_IN_SECONDS ) ) {
			$uncertainties[] = sprintf(
				__( 'This item was last updated %s and may need confirmation.', 'gsf-resource-assistant' ),
				gmdate( 'Y-m-d', $updated_timestamp )
			);
		}

		$quote = self::trim_text( $resource['title'] . '. ' . $resource['description'], 520 );
		return array(
			'resource'             => $resource['title'],
			'resource_type'        => $resource['resource_type'],
			'group'                => $resource['group'],
			'summary'              => self::trim_text( $resource['description'], 360 ),
			'fit_score'            => round( max( 0, min( 1, $score ) ), 4 ),
			'eligibility_status'   => $application ? 'needs_verification' : 'not_applicable',
			'why_it_fits'          => self::unique_strings( $reasons ),
			'possible_barriers'    => self::unique_strings( $barriers ),
			'eligibility'          => $resource['eligibility'],
			'financial_support'    => $resource['financial_support'] ?: self::MISSING_VALUE,
			'application_deadline' => $resource['deadline'] ?: self::MISSING_VALUE,
			'contact_information'  => $resource['contact'] ?: self::MISSING_VALUE,
			'next_steps'           => array( $resource['next_step'] ),
			'uncertainties'        => self::unique_strings( $uncertainties ),
			'sources'              => array(
				array(
					'document' => $resource['title'],
					'url'      => $resource['url'],
					'quote'    => $quote,
				),
			),
		);
	}

	private static function deduplicate_titles( $recommendations ) {
		$by_title = array();
		foreach ( $recommendations as $item ) {
			$key = self::normalize( $item['resource'] );
			if ( ! isset( $by_title[ $key ] ) ) {
				$by_title[ $key ] = $item;
				continue;
			}
			$current = $by_title[ $key ];
			if ( 'data_centre' === $current['group'] && 'data_centre' !== $item['group'] && $item['fit_score'] >= ( $current['fit_score'] - 0.15 ) ) {
				$by_title[ $key ] = $item;
			} elseif ( $item['fit_score'] > $current['fit_score'] ) {
				$by_title[ $key ] = $item;
			}
		}
		$items = array_values( $by_title );
		usort(
			$items,
			function ( $left, $right ) {
				return $right['fit_score'] <=> $left['fit_score'];
			}
		);
		return $items;
	}

	private static function diversify( $recommendations, $top_k, $query_raw ) {
		if ( count( $recommendations ) <= 1 || $top_k <= 1 ) {
			return $recommendations;
		}

		$strongest = $recommendations[0];
		$starting  = $strongest;
		$explicit_data_record = ! empty( array_intersect( $query_raw, array( 'indicator', 'indicators', 'ecoequity', 'project', 'projects', 'activity', 'activities' ) ) );
		if ( 'data_centre' === $strongest['group'] && ! $explicit_data_record ) {
			foreach ( $recommendations as $candidate ) {
				if ( in_array( $candidate['group'], array( 'learn', 'resources', 'case_studies', 'opportunities' ), true ) && $candidate['fit_score'] >= ( $strongest['fit_score'] * 0.70 ) ) {
					$starting = $candidate;
					break;
				}
			}
		}

		$selected       = array( $starting );
		$selected_names = array( self::normalize( $starting['resource'] ) => true );
		$seen_groups    = array( $starting['group'] => true );
		$threshold      = 0.08;

		foreach ( $recommendations as $item ) {
			$name_key = self::normalize( $item['resource'] );
			if ( isset( $selected_names[ $name_key ] ) || isset( $seen_groups[ $item['group'] ] ) || $item['fit_score'] < $threshold ) {
				continue;
			}
			$selected[]                    = $item;
			$selected_names[ $name_key ]   = true;
			$seen_groups[ $item['group'] ] = true;
			if ( count( $selected ) >= $top_k ) {
				return $selected;
			}
		}

		foreach ( $recommendations as $item ) {
			$name_key = self::normalize( $item['resource'] );
			if ( isset( $selected_names[ $name_key ] ) ) {
				continue;
			}
			$selected[]                  = $item;
			$selected_names[ $name_key ] = true;
			if ( count( $selected ) >= $top_k ) {
				break;
			}
		}
		return $selected;
	}

	private static function resource_type( $post_type, $meta ) {
		$types = array(
			'gsf_resource'    => 'Resource',
			'gsf_course'      => 'Learn course',
			'gsf_case_study'  => 'Case study',
			'forum'           => 'Forum',
			'topic'           => 'Forum discussion',
			'gsf_data_story'  => 'Data story',
			'gsf_project'     => 'Data Centre project',
			'gsf_indicator'   => 'Data Centre indicator',
			'gsf_activity'    => 'Data Centre activity',
			'gsf_ecoequity'   => 'Data Centre Ecoequity score',
			'gsf_data_centre' => 'Data Centre',
			'expert_directory'=> 'Expert',
			'zoom-meetings'   => 'Event or webinar',
		);
		$type = $types[ $post_type ] ?? 'Hub content';
		if ( 'gsf_resource' === $post_type ) {
			$subtype = self::first_meta( $meta, array( 'resource_type' ) );
			$type    = $subtype ? $type . ' · ' . $subtype : $type;
		} elseif ( 'gsf_case_study' === $post_type ) {
			$subtype = self::first_meta( $meta, array( 'case_study_type' ) );
			$type    = $subtype ? $type . ' · ' . $subtype : $type;
		}
		return $type;
	}

	private static function content_group( $type ) {
		$type = self::normalize( $type );
		if ( 0 === strpos( $type, 'data centre' ) || 'data story' === $type ) {
			return 'data_centre';
		}
		if ( 0 === strpos( $type, 'learn' ) ) {
			return 'learn';
		}
		if ( 0 === strpos( $type, 'resource' ) || in_array( $type, array( 'document', 'toolkit', 'template' ), true ) ) {
			return 'resources';
		}
		if ( 0 === strpos( $type, 'case study' ) ) {
			return 'case_studies';
		}
		if ( 0 === strpos( $type, 'forum' ) ) {
			return 'community';
		}
		if ( 0 === strpos( $type, 'expert' ) ) {
			return 'experts';
		}
		if ( false !== strpos( $type, 'event' ) || false !== strpos( $type, 'webinar' ) ) {
			return 'events';
		}
		if ( preg_match( '/grant|fund|loan|programme|program|training/', $type ) ) {
			return 'opportunities';
		}
		return 'other';
	}

	private static function next_step( $post_type ) {
		$steps = array(
			'gsf_resource'    => __( 'Open the resource page and review or download the material.', 'gsf-resource-assistant' ),
			'gsf_course'      => __( 'Open the Learn course page and review enrollment details.', 'gsf-resource-assistant' ),
			'gsf_case_study'  => __( 'Open the case study and review the implementation lessons.', 'gsf-resource-assistant' ),
			'forum'           => __( 'Open the forum and browse relevant public discussions.', 'gsf-resource-assistant' ),
			'topic'           => __( 'Open the public discussion and review or contribute to the thread.', 'gsf-resource-assistant' ),
			'gsf_data_story'  => __( 'Open the data story and review the supporting results.', 'gsf-resource-assistant' ),
			'gsf_project'     => __( 'Open the Data Centre project and review its country, partners, and implementation details.', 'gsf-resource-assistant' ),
			'gsf_indicator'   => __( 'Open the Data Centre indicator and review its definition, baseline, and target.', 'gsf-resource-assistant' ),
			'gsf_activity'    => __( 'Open the Data Centre activity and review its delivery and participation details.', 'gsf-resource-assistant' ),
			'gsf_ecoequity'   => __( 'Open the Data Centre Ecoequity record and review the reported assessment.', 'gsf-resource-assistant' ),
			'gsf_data_centre' => __( 'Open the Data Centre and explore the available projects, indicators, and results.', 'gsf-resource-assistant' ),
			'expert_directory'=> __( 'Open the expert profile and review their expertise.', 'gsf-resource-assistant' ),
			'zoom-meetings'   => __( 'Open the event page and confirm the schedule and registration details.', 'gsf-resource-assistant' ),
		);
		return $steps[ $post_type ] ?? __( 'Open the cited Hub item and review the full material.', 'gsf-resource-assistant' );
	}

	private static function tokenize( $text, $expand ) {
		$normalized = self::normalize( $text );
		$tokens     = preg_split( '/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$stopwords  = array_flip(
			array( 'a', 'about', 'am', 'an', 'and', 'are', 'as', 'at', 'be', 'could', 'do', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'our', 'some', 'that', 'the', 'their', 'to', 'use', 'want', 'what', 'where', 'which', 'who', 'with', 'would' )
		);
		$result = array();
		foreach ( $tokens as $token ) {
			if ( strlen( $token ) < 2 || isset( $stopwords[ $token ] ) ) {
				continue;
			}
			$result[] = $token;
		}

		if ( $expand ) {
			$synonyms = array(
				'nctf'       => array( 'ctf', 'conservation', 'trust', 'fund', 'governance', 'capacity' ),
				'ctf'        => array( 'nctf', 'conservation', 'trust', 'fund', 'governance', 'capacity' ),
				'gender'     => array( 'women', 'equity', 'inclusion', 'responsive' ),
				'women'      => array( 'gender', 'equity', 'leadership', 'inclusion' ),
				'mangrove'   => array( 'coastal', 'restoration', 'resilience', 'wetland', 'marine' ),
				'coastal'    => array( 'mangrove', 'shoreline', 'marine', 'restoration', 'resilience' ),
				'hurricane'  => array( 'storm', 'resilience', 'disaster', 'preparedness', 'equipment' ),
				'resilience' => array( 'adaptation', 'climate', 'disaster', 'continuity', 'hurricane' ),
				'tourism'    => array( 'hospitality', 'visitor', 'business' ),
				'training'   => array( 'course', 'learning', 'workshop', 'capacity' ),
				'course'     => array( 'training', 'learning', 'workshop', 'capacity' ),
				'courses'    => array( 'course', 'training', 'learning', 'workshop', 'capacity' ),
				'tool'       => array( 'toolkit', 'template', 'guidance', 'resource' ),
				'tools'      => array( 'toolkit', 'template', 'guidance', 'resource' ),
				'experts'    => array( 'expert', 'specialist', 'directory' ),
				'examples'   => array( 'example', 'case', 'study', 'story' ),
				'indicators' => array( 'indicator', 'data', 'evidence', 'results', 'monitoring' ),
				'funds'      => array( 'fund', 'ctf', 'nctf', 'finance', 'management' ),
				'evidence'   => array( 'data', 'indicator', 'results', 'monitoring' ),
				'data'       => array( 'evidence', 'indicator', 'results', 'monitoring' ),
				'governance' => array( 'capacity', 'policy', 'institutional', 'management' ),
			);
			foreach ( array_values( array_unique( $result ) ) as $token ) {
				if ( isset( $synonyms[ $token ] ) ) {
					$result = array_merge( $result, $synonyms[ $token ] );
				}
			}
		}
		return array_values( array_unique( $result ) );
	}

	private static function intent_bonus( $group, $query_tokens ) {
		$tokens = array_flip( $query_tokens );
		$intents = array(
			'learn'        => array( array( 'course', 'learning', 'training', 'workshop' ), 0.18 ),
			'resources'    => array( array( 'tool', 'tools', 'toolkit', 'template', 'guidance', 'resource' ), 0.14 ),
			'case_studies' => array( array( 'example', 'examples', 'case', 'study', 'story' ), 0.11 ),
			'opportunities'=> array( array( 'grant', 'funding', 'loan', 'opportunity' ), 0.16 ),
			'experts'      => array( array( 'expert', 'experts', 'specialist', 'directory' ), 0.08 ),
			'community'    => array( array( 'forum', 'discussion', 'peer', 'community' ), 0.07 ),
			'events'       => array( array( 'event', 'webinar' ), 0.08 ),
		);
		if ( ! isset( $intents[ $group ] ) ) {
			return 0;
		}
		foreach ( $intents[ $group ][0] as $token ) {
			if ( isset( $tokens[ $token ] ) ) {
				return $intents[ $group ][1];
			}
		}
		return 0;
	}

	private static function type_specific_bonus( $resource_type, $query_tokens ) {
		$type   = self::normalize( $resource_type );
		$tokens = array_flip( $query_tokens );
		$rules  = array(
			'indicator' => array( 'indicator', 0.25 ),
			'project'   => array( 'project', 0.14 ),
			'ecoequity' => array( 'ecoequity', 0.20 ),
			'activity'  => array( 'activity', 0.14 ),
		);
		foreach ( $rules as $type_marker => $rule ) {
			if ( false !== strpos( $type, $type_marker ) && isset( $tokens[ $rule[0] ] ) ) {
				return $rule[1];
			}
		}
		return 0;
	}

	private static function normalize( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = remove_accents( wp_strip_all_tags( $text, true ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/[^a-z0-9]+/u', ' ', $text );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function token_counts( $tokens ) {
		$counts = array();
		foreach ( $tokens as $token ) {
			$counts[ $token ] = ( $counts[ $token ] ?? 0 ) + 1;
		}
		return $counts;
	}

	private static function weighted_vector( $counts, $idf ) {
		$vector = array();
		foreach ( $counts as $token => $count ) {
			$vector[ $token ] = ( 1 + log( max( 1, $count ) ) ) * ( $idf[ $token ] ?? 1 );
		}
		return $vector;
	}

	private static function vector_norm( $vector ) {
		$sum = 0;
		foreach ( $vector as $weight ) {
			$sum += $weight * $weight;
		}
		return sqrt( $sum );
	}

	private static function cosine( $query_vector, $query_norm, $resource_vector ) {
		$resource_norm = self::vector_norm( $resource_vector );
		if ( 0.0 === $query_norm || 0.0 === $resource_norm ) {
			return 0;
		}
		$dot = 0;
		foreach ( $query_vector as $token => $weight ) {
			if ( isset( $resource_vector[ $token ] ) ) {
				$dot += $weight * $resource_vector[ $token ];
			}
		}
		return $dot / ( $query_norm * $resource_norm );
	}

	private static function overlap_ratio( $query_tokens, $candidate_tokens ) {
		$query_tokens = array_values( array_unique( $query_tokens ) );
		if ( empty( $query_tokens ) || empty( $candidate_tokens ) ) {
			return 0;
		}
		$overlap = count( array_intersect( $query_tokens, array_unique( $candidate_tokens ) ) );
		return min( 1, $overlap / max( 1, min( 6, count( $query_tokens ) ) ) );
	}

	private static function first_phrase_match( $query, $values ) {
		$query = ' ' . self::normalize( $query ) . ' ';
		foreach ( $values as $value ) {
			$normalized = self::normalize( $value );
			if ( $normalized && false !== strpos( $query, ' ' . $normalized . ' ' ) ) {
				return $value;
			}
		}
		return '';
	}

	private static function matched_labels( $matched_terms, $labels ) {
		$matched = array_flip( $matched_terms );
		$result  = array();
		foreach ( $labels as $label ) {
			foreach ( self::tokenize( $label, false ) as $token ) {
				if ( isset( $matched[ $token ] ) ) {
					$result[] = $label;
					break;
				}
			}
		}
		return self::unique_strings( $result );
	}

	private static function meta_values( $meta, $keys ) {
		$values = array();
		foreach ( $keys as $key ) {
			if ( ! isset( $meta[ $key ] ) ) {
				continue;
			}
			$items = is_array( $meta[ $key ] ) ? $meta[ $key ] : array( $meta[ $key ] );
			foreach ( $items as $item ) {
				foreach ( preg_split( '/[|;]/', (string) $item ) as $part ) {
					$part = trim( sanitize_text_field( $part ) );
					if ( '' !== $part ) {
						$values[] = $part;
					}
				}
			}
		}
		return self::unique_strings( $values );
	}

	private static function first_meta( $meta, $keys ) {
		$values = self::meta_values( $meta, $keys );
		return $values[0] ?? '';
	}

	private static function unique_strings( $items ) {
		$seen   = array();
		$result = array();
		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			$key  = self::normalize( $item );
			if ( '' !== $key && ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$result[]     = $item;
			}
		}
		return $result;
	}

	private static function trim_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text, true ) ) );
		if ( strlen( $text ) <= $limit ) {
			return $text;
		}
		$trimmed = substr( $text, 0, max( 1, $limit - 1 ) );
		$space   = strrpos( $trimmed, ' ' );
		return rtrim( false !== $space ? substr( $trimmed, 0, $space ) : $trimmed ) . '…';
	}

	private static function is_application_resource( $type ) {
		return (bool) preg_match( '/grant|funding|loan|programme|program|opportunity/', self::normalize( $type ) );
	}
}
