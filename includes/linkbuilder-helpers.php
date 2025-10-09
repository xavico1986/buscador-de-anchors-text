<?php
/**
 * Helper functions for Linkbuilder workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Returns the transient key for the current user state.
 *
 * @param int $user_id User ID.
 * @return string
 */
function sai_lb_state_key( $user_id ) {
    return 'sai_lb_state_' . absint( $user_id );
}

/**
 * Returns the default state structure.
 *
 * @return array
 */
function sai_lb_default_state() {
    return [
        'q_madre'           => '',
        'in_content_madre'  => 0,
        'madre_id'          => 0,
        'canonical'         => '',
        'madre_anchors'     => [],
        'limit_hijas'       => 1,
        'q_hijas'           => '',
        'in_content_hijas'  => 0,
        'hijas_ids'         => [],
        'hijas_anchors'     => [],
        'limit_nietas'      => 1,
        'q_nietas'          => '',
        'in_content_nietas' => 0,
        'nietas_ids'        => [],
    ];
}

/**
 * Retrieves the stored state for the current user.
 *
 * @return array
 */
function sai_lb_get_user_state() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return sai_lb_default_state();
    }

    $key   = sai_lb_state_key( $user_id );
    $state = get_transient( $key );

    if ( ! is_array( $state ) ) {
        $state = sai_lb_default_state();
    }

    return wp_parse_args( $state, sai_lb_default_state() );
}

/**
 * Saves the state for the current user.
 *
 * @param array $state State data.
 * @return void
 */
function sai_lb_save_user_state( $state ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    set_transient( sai_lb_state_key( $user_id ), $state, DAY_IN_SECONDS );
}

/**
 * Updates the stored state with provided data.
 *
 * @param array $updates Partial updates.
 * @return array Updated state.
 */
function sai_lb_update_state( $updates ) {
    $state = sai_lb_get_user_state();
    foreach ( $updates as $key => $value ) {
        $state[ $key ] = $value;
    }

    sai_lb_save_user_state( $state );

    return $state;
}

/**
 * Resets the stored state.
 *
 * @return void
 */
function sai_lb_reset_state() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    delete_transient( sai_lb_state_key( $user_id ) );
}

/**
 * Retrieves or builds cached analysis data for a post.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function sai_lb_get_post_analysis( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return [ 'plain' => '', 'vector' => [], 'top_ngrams' => [] ];
    }

    $cache_key = 'sai_lb_vec_' . $post_id;
    $cached    = get_transient( $cache_key );
    if ( is_array( $cached ) && isset( $cached['plain'] ) ) {
        return $cached;
    }

    $anchors = new SAI_Anchors();
    $content = get_post_field( 'post_content', $post_id );
    $plain   = $anchors->clean_content( $content );

    $analysis = [
        'plain'      => $plain,
        'vector'     => sai_lb_vectorize( $plain ),
        'top_ngrams' => sai_lb_top_ngrams( $plain ),
    ];

    set_transient( $cache_key, $analysis, DAY_IN_SECONDS );

    return $analysis;
}

/**
 * Returns plain text body for a post using cached analysis.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function sai_lb_post_plain_text( $post_id ) {
    $analysis = sai_lb_get_post_analysis( $post_id );
    return isset( $analysis['plain'] ) ? $analysis['plain'] : '';
}

/**
 * Normalizes text for vectorization.
 *
 * @param string $text Text to normalize.
 * @return string
 */
function sai_lb_normalize_for_vector( $text ) {
    $text = remove_accents( mb_strtolower( (string) $text, 'UTF-8' ) );
    $text = preg_replace( '/[^a-z0-9\sx]/u', ' ', $text );
    $text = preg_replace( '/\s+/u', ' ', $text );

    return trim( $text );
}

/**
 * Returns Spanish stopwords from the anchor extractor.
 *
 * @return array
 */
function sai_lb_stopwords() {
    static $stopwords = null;
    if ( null === $stopwords ) {
        $anchors   = new SAI_Anchors();
        $stopwords = $anchors->get_stopwords();
    }

    return $stopwords;
}

/**
 * Vectorizes a plain text string into token frequencies.
 *
 * @param string $plain Plain text.
 * @return array
 */
function sai_lb_vectorize( $plain ) {
    $normalized = sai_lb_normalize_for_vector( $plain );
    if ( '' === $normalized ) {
        return [];
    }

    $tokens    = preg_split( '/\s+/u', $normalized );
    $stopwords = sai_lb_stopwords();
    $vector    = [];

    foreach ( $tokens as $token ) {
        if ( '' === $token || in_array( $token, $stopwords, true ) ) {
            continue;
        }

        if ( mb_strlen( $token, 'UTF-8' ) < 3 ) {
            continue;
        }

        if ( ! isset( $vector[ $token ] ) ) {
            $vector[ $token ] = 0;
        }

        $vector[ $token ]++;
    }

    return $vector;
}

/**
 * Calculates cosine similarity between vectors.
 *
 * @param array $v1 Vector 1.
 * @param array $v2 Vector 2.
 * @return float
 */
function sai_lb_cosine( $v1, $v2 ) {
    if ( empty( $v1 ) || empty( $v2 ) ) {
        return 0.0;
    }

    $dot = 0.0;
    foreach ( $v1 as $token => $count ) {
        if ( isset( $v2[ $token ] ) ) {
            $dot += $count * $v2[ $token ];
        }
    }

    if ( 0.0 === $dot ) {
        return 0.0;
    }

    $mag1 = 0.0;
    foreach ( $v1 as $count ) {
        $mag1 += pow( $count, 2 );
    }

    $mag2 = 0.0;
    foreach ( $v2 as $count ) {
        $mag2 += pow( $count, 2 );
    }

    if ( 0.0 === $mag1 || 0.0 === $mag2 ) {
        return 0.0;
    }

    return $dot / ( sqrt( $mag1 ) * sqrt( $mag2 ) );
}

/**
 * Builds top n-grams from plain text.
 *
 * @param string $plain Plain text.
 * @param int    $min   Minimum n.
 * @param int    $max   Maximum n.
 * @param int    $k     Limit of phrases.
 * @return array
 */
function sai_lb_top_ngrams( $plain, $min = 2, $max = 3, $k = 20 ) {
    $normalized = sai_lb_normalize_for_vector( $plain );
    if ( '' === $normalized ) {
        return [];
    }

    $tokens    = preg_split( '/\s+/u', $normalized );
    $stopwords = sai_lb_stopwords();
    $ngrams    = [];
    $total     = count( $tokens );

    for ( $i = 0; $i < $total; $i++ ) {
        for ( $n = $min; $n <= $max; $n++ ) {
            $end = $i + $n;
            if ( $end > $total ) {
                break;
            }

            $slice = array_slice( $tokens, $i, $n );
            if ( empty( $slice ) ) {
                continue;
            }

            $first = $slice[0];
            $last  = $slice[ count( $slice ) - 1 ];

            if ( in_array( $first, $stopwords, true ) || in_array( $last, $stopwords, true ) ) {
                continue;
            }

            $phrase = implode( ' ', $slice );
            if ( mb_strlen( $phrase, 'UTF-8' ) < 6 ) {
                continue;
            }

            if ( ! isset( $ngrams[ $phrase ] ) ) {
                $ngrams[ $phrase ] = 0;
            }

            $ngrams[ $phrase ]++;
        }
    }

    if ( empty( $ngrams ) ) {
        return [];
    }

    arsort( $ngrams );

    return array_slice( $ngrams, 0, $k, true );
}

/**
 * Computes Jaccard similarity between sets of keys.
 *
 * @param array $a First set (phrase => freq).
 * @param array $b Second set.
 * @return float
 */
function sai_lb_jaccard_keys( $a, $b ) {
    if ( empty( $a ) || empty( $b ) ) {
        return 0.0;
    }

    $keys_a = array_keys( $a );
    $keys_b = array_keys( $b );

    $intersect = array_intersect( $keys_a, $keys_b );
    $union     = array_unique( array_merge( $keys_a, $keys_b ) );

    if ( empty( $union ) ) {
        return 0.0;
    }

    return count( $intersect ) / count( $union );
}

/**
 * Generates canonical variants for scoring comparisons.
 *
 * @param string $canonical Canonical phrase.
 * @return array
 */
function sai_lb_canonical_variants( $canonical ) {
    $anchors        = new SAI_Anchors();
    $canonical_norm = $anchors->normalize_text( $canonical );
    $core_norm      = $anchors->normalize_text( $anchors->get_canonical_core( $canonical ) );

    $variants = [];
    if ( '' !== $canonical_norm ) {
        $variants[] = $canonical_norm;
    }
    if ( '' !== $core_norm && $core_norm !== $canonical_norm ) {
        $variants[] = $core_norm;
    }

    $base = '' !== $core_norm ? $core_norm : $canonical_norm;

    $tokens = array_filter( preg_split( '/\s+/u', $base ) );
    foreach ( $tokens as $token ) {
        if ( '' === $token ) {
            continue;
        }
        $variants[] = $token;

        if ( preg_match( '/s$/u', $token ) ) {
            $variants[] = preg_replace( '/s$/u', '', $token );
        } else {
            $variants[] = $token . 's';
            $variants[] = $token . 'es';
        }
    }

    return array_values( array_unique( array_filter( $variants ) ) );
}

/**
 * Counts occurrences per 1000 words for canonical variants.
 *
 * @param string $plain Plain text.
 * @param array  $variants Canonical variants.
 * @return array Array with 'count' and 'density'.
 */
function sai_lb_canonical_density( $plain, $variants ) {
    if ( '' === $plain || empty( $variants ) ) {
        return [ 'count' => 0, 'density' => 0.0 ];
    }

    $normalized_body = sai_lb_normalize_for_vector( $plain );
    if ( '' === $normalized_body ) {
        return [ 'count' => 0, 'density' => 0.0 ];
    }

    $word_count = max( 1, str_word_count( $normalized_body ) );
    $count      = 0;

    foreach ( $variants as $variant ) {
        if ( '' === $variant ) {
            continue;
        }

        $pattern = '/\b' . preg_quote( $variant, '/' ) . '\b/u';
        if ( preg_match_all( $pattern, $normalized_body, $matches ) ) {
            $count += count( $matches[0] );
        }
    }

    $density = ( $count / $word_count ) * 1000;

    return [ 'count' => $count, 'density' => $density ];
}

/**
 * Computes cannibalization score for a candidate against the mother page.
 *
 * @param int    $candidate_id Candidate post ID.
 * @param int    $madre_id     Mother post ID.
 * @param string $canonical    Canonical phrase.
 * @return array
 */
function sai_lb_cannibal_score( $candidate_id, $madre_id, $canonical ) {
    $candidate_id = absint( $candidate_id );
    $madre_id     = absint( $madre_id );

    if ( ! $candidate_id || ! $madre_id || $candidate_id === $madre_id ) {
        return [ 'score' => 0, 'level' => 'amarillo', 'reasons' => [] ];
    }

    $variants = sai_lb_canonical_variants( $canonical );

    $score   = 0;
    $reasons = [];

    // Title scoring.
    $title       = get_the_title( $candidate_id );
    $title_norm  = sai_lb_normalize_for_vector( $title );
    $title_score = 0;
    if ( '' !== $title_norm ) {
        foreach ( $variants as $variant ) {
            if ( '' === $variant ) {
                continue;
            }
            if ( false !== mb_strpos( $title_norm, $variant, 0, 'UTF-8' ) ) {
                if ( $variant === sai_lb_normalize_for_vector( $canonical ) ) {
                    $title_score = max( $title_score, 30 );
                    $reasons[]   = __( 'Título contiene el canónico completo', 'anchors-sin-ia' );
                    break;
                }
                $title_score = max( $title_score, 20 );
                $reasons[]   = __( 'Título contiene parte del canónico', 'anchors-sin-ia' );
            }
        }
        if ( 0 === $title_score ) {
            foreach ( $variants as $variant ) {
                if ( '' === $variant ) {
                    continue;
                }
                $tokens = explode( ' ', $variant );
                foreach ( $tokens as $token ) {
                    if ( '' !== $token && false !== mb_strpos( $title_norm, $token, 0, 'UTF-8' ) ) {
                        $title_score = max( $title_score, 10 );
                        $reasons[]   = __( 'Título comparte token del canónico', 'anchors-sin-ia' );
                        break 2;
                    }
                }
            }
        }
    }
    $score += min( 30, $title_score );

    // Slug scoring.
    $slug      = get_post_field( 'post_name', $candidate_id );
    $slug_norm = sai_lb_normalize_for_vector( str_replace( '-', ' ', $slug ) );
    if ( '' !== $slug_norm ) {
        foreach ( $variants as $variant ) {
            if ( '' === $variant ) {
                continue;
            }
            if ( false !== mb_strpos( $slug_norm, str_replace( ' ', '-', $variant ), 0, 'UTF-8' ) || false !== mb_strpos( $slug_norm, $variant, 0, 'UTF-8' ) ) {
                $score    += 10;
                $reasons[] = __( 'Slug coincide con el canónico', 'anchors-sin-ia' );
                break;
            }
        }
        if ( $score < 10 ) {
            foreach ( $variants as $variant ) {
                $tokens = explode( ' ', $variant );
                foreach ( $tokens as $token ) {
                    if ( '' !== $token && false !== mb_strpos( $slug_norm, $token, 0, 'UTF-8' ) ) {
                        $score    += 5;
                        $reasons[] = __( 'Slug comparte token del canónico', 'anchors-sin-ia' );
                        break 2;
                    }
                }
            }
        }
    }

    $madre_analysis     = sai_lb_get_post_analysis( $madre_id );
    $candidate_analysis = sai_lb_get_post_analysis( $candidate_id );

    $similarity = sai_lb_cosine( $madre_analysis['vector'], $candidate_analysis['vector'] );
    if ( $similarity >= 0.50 ) {
        $score    += 40;
        $reasons[] = sprintf( __( 'Similitud %.2f', 'anchors-sin-ia' ), $similarity );
    } elseif ( $similarity >= 0.35 ) {
        $score    += 25;
        $reasons[] = sprintf( __( 'Similitud %.2f', 'anchors-sin-ia' ), $similarity );
    } elseif ( $similarity >= 0.20 ) {
        $score    += 10;
        $reasons[] = sprintf( __( 'Similitud %.2f', 'anchors-sin-ia' ), $similarity );
    }

    $density = sai_lb_canonical_density( $candidate_analysis['plain'], $variants );
    if ( $density['density'] >= 3 ) {
        $score    += 10;
        $reasons[] = __( 'Alta densidad del canónico', 'anchors-sin-ia' );
    } elseif ( $density['density'] >= 1 ) {
        $score    += 5;
        $reasons[] = __( 'Densidad media del canónico', 'anchors-sin-ia' );
    }

    $jaccard = sai_lb_jaccard_keys( $madre_analysis['top_ngrams'], $candidate_analysis['top_ngrams'] );
    if ( $jaccard >= 0.15 ) {
        $score    += 10;
        $reasons[] = __( 'N-grams compartidos con la madre', 'anchors-sin-ia' );
    } elseif ( $jaccard >= 0.08 ) {
        $score    += 5;
        $reasons[] = __( 'Algunos n-grams compartidos', 'anchors-sin-ia' );
    }

    $score = min( 100, (int) round( $score ) );

    if ( $score >= 70 ) {
        $level = 'rojo';
    } elseif ( $score >= 40 ) {
        $level = 'naranja';
    } elseif ( $score > 0 ) {
        $level = 'amarillo';
    } else {
        $level = 'amarillo';
    }

    return [
        'score'   => $score,
        'level'   => $level,
        'reasons' => array_values( array_unique( $reasons ) ),
    ];
}

/**
 * Assigns anchors to targets in round-robin fashion.
 *
 * @param array  $anchors   Anchors data.
 * @param array  $targets   Target post IDs.
 * @param string $from_url  Source URL.
 * @param string $canonical Canonical fallback.
 * @return array
 */
function sai_lb_round_robin_assign( $anchors, $targets, $from_url, $canonical ) {
    $rows = [];

    $targets = array_values( array_filter( array_map( 'absint', (array) $targets ) ) );
    if ( empty( $targets ) || ! $from_url ) {
        return $rows;
    }

    if ( empty( $anchors ) ) {
        $anchors = [ [ 'text' => $canonical ] ];
    }

    $anchors = array_values( (array) $anchors );

    $anchor_count  = count( $anchors );
    $target_count  = count( $targets );
    $max_iterations = max( $anchor_count, $target_count );

    if ( $max_iterations <= 0 ) {
        return $rows;
    }

    $fallback = $canonical;
    if ( '' === $fallback ) {
        $fallback = $from_url;
    }

    for ( $i = 0; $i < $max_iterations; $i++ ) {
        $anchor = $anchors[ $i % $anchor_count ];
        $text   = isset( $anchor['text'] ) && '' !== $anchor['text'] ? $anchor['text'] : $fallback;
        $to_id  = $targets[ $i % $target_count ];
        $to_url = get_permalink( $to_id );

        if ( ! $to_url ) {
            continue;
        }

        $rows[] = [
            'from'        => $from_url,
            'anchor_text' => $text,
            'to'          => $to_url,
        ];
    }

    return $rows;
}

/**
 * Generates CSV export rows and summary.
 *
 * @param array $state Current state.
 * @return array|WP_Error
 */
function sai_lb_generate_csv_data( $state ) {
    $state      = wp_parse_args( (array) $state, sai_lb_default_state() );
    $madre_id   = absint( $state['madre_id'] );
    $canonical  = $state['canonical'];
    $madre_url  = $madre_id ? get_permalink( $madre_id ) : '';
    $hijas      = array_values( array_filter( array_map( 'absint', (array) $state['hijas_ids'] ) ) );
    $nietas     = array_values( array_filter( array_map( 'absint', (array) $state['nietas_ids'] ) ) );
    $rows       = [];
    $madre_an   = is_array( $state['madre_anchors'] ) ? $state['madre_anchors'] : [];
    $hijas_an   = is_array( $state['hijas_anchors'] ) ? $state['hijas_anchors'] : [];

    if ( $madre_id && $madre_url && ! empty( $hijas ) ) {
        $rows = array_merge( $rows, sai_lb_round_robin_assign( $madre_an, $hijas, $madre_url, $canonical ) );
    }

    if ( ! empty( $nietas ) && ! empty( $hijas ) ) {
        foreach ( $hijas as $hija_id ) {
            $from_url = get_permalink( $hija_id );
            if ( ! $from_url ) {
                continue;
            }
            $anchors_hija = isset( $hijas_an[ $hija_id ] ) ? $hijas_an[ $hija_id ] : [];
            $rows         = array_merge( $rows, sai_lb_round_robin_assign( $anchors_hija, $nietas, $from_url, $canonical ) );
        }
    }

    if ( empty( $rows ) ) {
        return new WP_Error( 'sai_no_export', __( 'No hay datos suficientes para generar el CSV.', 'anchors-sin-ia' ), [ 'status' => 400 ] );
    }

    $output = fopen( 'php://temp', 'r+' );
    fputcsv( $output, [ 'from_url', 'anchor_text', 'to_url' ] );
    foreach ( $rows as $row ) {
        fputcsv( $output, [ $row['from'], $row['anchor_text'], $row['to'] ] );
    }
    rewind( $output );
    $csv = stream_get_contents( $output );
    fclose( $output );

    $anchors_hijas_total = 0;
    if ( ! empty( $hijas_an ) ) {
        foreach ( $hijas_an as $list ) {
            if ( is_array( $list ) ) {
                $anchors_hijas_total += count( $list );
            }
        }
    }

    return [
        'csv'      => $csv,
        'rows'     => $rows,
        'filename' => 'linkbuilder-' . $madre_id . '.csv',
        'summary'  => [
            'madre'  => [
                'id'      => $madre_id,
                'anchors' => count( $madre_an ),
            ],
            'hijas'  => [
                'count'   => count( $hijas ),
                'anchors' => $anchors_hijas_total,
            ],
            'nietas' => [
                'count' => count( $nietas ),
            ],
        ],
    ];
}
