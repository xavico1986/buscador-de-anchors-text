<?php
/**
 * Anchor extraction logic without AI.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAI_Anchors {

    /**
     * List of Spanish stopwords.
     *
     * @var array
     */
    protected $stopwords = [
        'a', 'acá', 'ahí', 'al', 'algo', 'algunas', 'algunos', 'allí', 'allá', 'ante', 'antes', 'aquel', 'aquella',
        'aquellas', 'aquellos', 'aqui', 'aquí', 'arriba', 'así', 'atrás', 'bajo', 'bastante', 'bien', 'cada', 'casi',
        'como', 'con', 'contra', 'cual', 'cuales', 'cualquier', 'cualquiera', 'cualquieras', 'cuando', 'cuanto',
        'cuanta', 'cuantas', 'cuantos', 'de', 'dejar', 'del', 'demás', 'demasiada', 'demasiadas', 'demasiado',
        'demasiados', 'dentro', 'desde', 'donde', 'dos', 'el', 'él', 'ella', 'ellas', 'ellos', 'en', 'encima', 'entonces',
        'entre', 'era', 'erais', 'eran', 'eras', 'eres', 'es', 'esa', 'esas', 'ese', 'eso', 'esos', 'esta', 'está', 'estaba',
        'estabais', 'estaban', 'estabas', 'estad', 'estada', 'estadas', 'estado', 'estados', 'estamos', 'estando',
        'estar', 'estaremos', 'estará', 'estarán', 'estarás', 'estaré', 'estaréis', 'estaría', 'estaríais', 'estaríamos',
        'estarían', 'estarías', 'estas', 'estás', 'este', 'esto', 'estos', 'estoy', 'fin', 'fue', 'fueron', 'fui', 'fuimos',
        'ha', 'habéis', 'haber', 'habrá', 'habrán', 'habrás', 'habré', 'habréis', 'habría', 'habríais', 'habríamos',
        'habrían', 'habrías', 'haciendo', 'hace', 'haces', 'hacia', 'haciendo', 'han', 'hasta', 'hay', 'haya', 'hayan',
        'hayas', 'he', 'hemos', 'hube', 'hubiera', 'hubierais', 'hubieran', 'hubieras', 'hubieron', 'hubiese', 'hubieseis',
        'hubiesen', 'hubieses', 'hubimos', 'hubiste', 'hubisteis', 'la', 'las', 'le', 'les', 'lo', 'los', 'mas', 'más', 'me',
        'mi', 'mis', 'mucho', 'muchos', 'muy', 'nada', 'ni', 'no', 'nos', 'nosotras', 'nosotros', 'nuestra', 'nuestras',
        'nuestro', 'nuestros', 'o', 'os', 'otra', 'otras', 'otro', 'otros', 'para', 'pero', 'poca', 'pocas', 'poco', 'pocos',
        'por', 'porque', 'primero', 'puede', 'pueden', 'pues', 'que', 'qué', 'querer', 'quien', 'quienes', 'se', 'sea',
        'seas', 'ser', 'será', 'serán', 'serás', 'seré', 'seréis', 'sería', 'seríais', 'seríamos', 'serían', 'serías',
        'si', 'sí', 'siempre', 'siendo', 'sin', 'sobre', 'sois', 'solamente', 'solo', 'sólo', 'somos', 'son', 'soy', 'su',
        'sus', 'tal', 'tales', 'también', 'tampoco', 'tan', 'tanta', 'tantas', 'tanto', 'tantos', 'te', 'tenemos', 'tener',
        'tengo', 'ti', 'tiene', 'tienen', 'toda', 'todas', 'todavía', 'todo', 'todos', 'tu', 'tus', 'un', 'una', 'uno',
        'unos', 'vosotras', 'vosotros', 'y', 'ya', 'yo'
    ];

    /**
     * CTA or noisy terms to exclude.
     *
     * @var array
     */
    protected $cta_terms = [
        'whatsapp', 'compra', 'comprar', 'cotiza', 'cotizar', 'precio', 'oferta', 'ofertas', 'promocion',
        'promociones', 'promo', 'descuentos', 'clic', 'click', 'click aqui', 'haz clic', 'suscríbete', 'suscribete',
        'registro', 'regístrate', 'registrate', 'teléfono', 'telefono', 'moldurama', 'mx'
    ];

    /**
     * Connector or brand words for canonical core.
     *
     * @var array
     */
    protected $connector_words = [ 'mx', 'com', 'de', 'del', 'para', 'en' ];

    /**
     * Topic core terms that must appear in anchors.
     *
     * @var array
     */
    protected $core_terms = [ 'caseton', 'poliestireno', 'eps', 'unicel', 'losa', 'reticular', '40x40x20' ];

    /**
     * Cleans raw content removing headings, scripts and HTML tags.
     *
     * @param string $content Raw HTML content.
     * @return string Clean plain text.
     */
    public function clean_content( $content ) {
        if ( empty( $content ) ) {
            return '';
        }

        $content = preg_replace( '/<script[\s\S]*?<\/script>/i', ' ', $content );
        $content = preg_replace( '/<style[\s\S]*?<\/style>/i', ' ', $content );
        $content = preg_replace( '/<h[1-6][^>]*>[\s\S]*?<\/h[1-6]>/i', ' ', $content );
        $content = strip_tags( $content );
        $content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $content = preg_replace( '/\s+/u', ' ', $content );

        return trim( $content );
    }

    /**
     * Counts words in text.
     *
     * @param string $text Text to count.
     * @return int
     */
    public function get_word_count( $text ) {
        if ( empty( $text ) ) {
            return 0;
        }

        $words = preg_split( '/[\s]+/u', trim( $text ) );
        $words = array_filter( $words, 'strlen' );

        return count( $words );
    }

    /**
     * Extracts anchors and quotas.
     *
     * @param string $canonical Canonical keyword.
     * @param string $body_text Clean body text.
     * @return array
     */
    public function extract( $canonical, $body_text ) {
        $body_text = $this->prepare_text( $body_text );
        $canonical = trim( (string) $canonical );

        if ( '' === $body_text || '' === $canonical ) {
            return [ 'anchors' => [], 'quotas' => [ 'total' => 0, 'exacta' => 0, 'frase' => 0, 'semantica' => 0 ] ];
        }

        $word_count    = $this->get_word_count( $body_text );
        $presets       = $this->get_presets( $word_count );
        $normalized    = $this->normalize( $body_text );
        $tokens        = $this->tokenize_with_positions( $body_text );
        $candidates    = $this->generate_candidates( $tokens, $body_text );
        $canonical_norm = $this->normalize( $canonical );
        $canonical_core = $this->canonical_core( $canonical );
        $canonical_core_norm = $this->normalize( $canonical_core );

        $valid_candidates = [];

        foreach ( $candidates as $candidate ) {
            if ( ! $this->is_candidate_valid( $candidate, $canonical_core_norm ) ) {
                continue;
            }

            $classification = $this->classify_candidate( $candidate['text'], $canonical_norm, $canonical_core_norm );

            $frequency = $this->count_frequency( $candidate['text'], $normalized );
            if ( $frequency < 1 ) {
                continue;
            }

            $candidate['classification'] = $classification;
            $candidate['frequency']      = $frequency;
            $valid_candidates[]          = $candidate;
        }

        $deduped = $this->deduplicate_candidates( $valid_candidates );

        $grouped = [ 'exacta' => [], 'frase' => [], 'semantica' => [] ];
        foreach ( $deduped as $candidate ) {
            $grouped[ $candidate['classification'] ][] = $candidate;
        }

        foreach ( $grouped as &$items ) {
            usort(
                $items,
                function ( $a, $b ) {
                    if ( $a['frequency'] === $b['frequency'] ) {
                        return mb_strlen( $a['text'] ) <=> mb_strlen( $b['text'] );
                    }

                    return $b['frequency'] <=> $a['frequency'];
                }
            );
        }
        unset( $items );

        $selected = [];
        $counts   = [ 'exacta' => 0, 'frase' => 0, 'semantica' => 0 ];

        // Initial allocation respecting quotas per classification order.
        foreach ( [ 'exacta', 'frase', 'semantica' ] as $type ) {
            $limit = isset( $presets[ $type ] ) ? (int) $presets[ $type ] : 0;
            if ( $limit < 1 || empty( $grouped[ $type ] ) ) {
                continue;
            }
            $slice = array_slice( $grouped[ $type ], 0, $limit );
            $selected = array_merge( $selected, $slice );
            $counts[ $type ] = count( $slice );
        }

        $total_needed = (int) $presets['total'];
        $total_selected = count( $selected );

        if ( $total_selected < $total_needed ) {
            $fallback_order = [ 'frase', 'semantica', 'exacta' ];
            $used_texts     = array_column( $selected, 'text' );

            foreach ( $fallback_order as $type ) {
                if ( $total_selected >= $total_needed ) {
                    break;
                }

                if ( empty( $grouped[ $type ] ) ) {
                    continue;
                }

                $index = $counts[ $type ];
                while ( isset( $grouped[ $type ][ $index ] ) && $total_selected < $total_needed ) {
                    $candidate = $grouped[ $type ][ $index ];
                    if ( in_array( $candidate['text'], $used_texts, true ) ) {
                        $index++;
                        continue;
                    }
                    $selected[]       = $candidate;
                    $used_texts[]     = $candidate['text'];
                    $counts[ $type ] += 1;
                    $total_selected++;
                    $index++;
                }
            }
        }

        // Cap totals by available anchors.
        $counts['exacta']    = min( $counts['exacta'], count( $grouped['exacta'] ) );
        $counts['frase']     = min( $counts['frase'], count( $grouped['frase'] ) );
        $counts['semantica'] = min( $counts['semantica'], count( $grouped['semantica'] ) );
        $counts['total']     = count( $selected );

        usort(
            $selected,
            function ( $a, $b ) {
                $order = [ 'exacta' => 0, 'frase' => 1, 'semantica' => 2 ];
                $class_cmp = $order[ $a['classification'] ] <=> $order[ $b['classification'] ];
                if ( 0 !== $class_cmp ) {
                    return $class_cmp;
                }
                if ( $a['frequency'] === $b['frequency'] ) {
                    return mb_strlen( $a['text'] ) <=> mb_strlen( $b['text'] );
                }

                return $b['frequency'] <=> $a['frequency'];
            }
        );

        $anchors = array_map(
            function ( $item ) {
                return [
                    'text'          => $item['text'],
                    'class'         => $item['classification'],
                    'frequency'     => $item['frequency'],
                ];
            },
            $selected
        );

        return [
            'anchors' => $anchors,
            'quotas'  => [
                'total'     => $counts['total'],
                'exacta'    => $counts['exacta'],
                'frase'     => $counts['frase'],
                'semantica' => $counts['semantica'],
            ],
        ];
    }

    /**
     * Prepares text by collapsing whitespace.
     *
     * @param string $text Text to prepare.
     * @return string
     */
    protected function prepare_text( $text ) {
        $text = (string) $text;
        $text = preg_replace( '/\s+/u', ' ', $text );
        return trim( $text );
    }

    /**
     * Normalizes text to lowercase without accents.
     *
     * @param string $text Text to normalize.
     * @return string
     */
    protected function normalize( $text ) {
        $text = wp_strip_all_tags( $text );
        $text = strtolower( $text );
        $text = remove_accents( $text );
        $text = preg_replace( '/[^\p{L}0-9\s]+/u', ' ', $text );
        $text = preg_replace( '/\s+/u', ' ', $text );
        return trim( $text );
    }

    /**
     * Generates canonical core string.
     *
     * @param string $canonical Canonical keyword.
     * @return string
     */
    protected function canonical_core( $canonical ) {
        $tokens = preg_split( '/\s+/u', strtolower( remove_accents( $canonical ) ) );
        $filtered = array_diff( $tokens, $this->connector_words );
        $filtered = array_filter( $filtered );
        return implode( ' ', $filtered );
    }

    /**
     * Tokenizes text and keeps positions.
     *
     * @param string $text Clean text.
     * @return array
     */
    protected function tokenize_with_positions( $text ) {
        $tokens = [];
        if ( '' === $text ) {
            return $tokens;
        }

        if ( preg_match_all( '/\b[\p{L}0-9][\p{L}0-9\p{Mn}\p{Pd}]*\b/u', $text, $matches, PREG_OFFSET_CAPTURE ) ) {
            foreach ( $matches[0] as $match ) {
                $tokens[] = [
                    'token'  => $match[0],
                    'offset' => $match[1],
                    'length' => mb_strlen( $match[0] ),
                ];
            }
        }

        return $tokens;
    }

    /**
     * Generates candidate anchors from tokens.
     *
     * @param array  $tokens Tokens with positions.
     * @param string $text   Full text.
     * @return array
     */
    protected function generate_candidates( $tokens, $text ) {
        $candidates = [];
        $token_count = count( $tokens );

        for ( $i = 0; $i < $token_count; $i++ ) {
            for ( $window = 2; $window <= 7; $window++ ) {
                $end_index = $i + $window - 1;
                if ( $end_index >= $token_count ) {
                    break;
                }

                $start = $tokens[ $i ]['offset'];
                $end_token = $tokens[ $end_index ];
                $end = $end_token['offset'] + $end_token['length'];
                $substr = mb_substr( $text, $start, $end - $start );
                $substr = $this->prepare_text( $substr );
                $length = mb_strlen( $substr );

                if ( $length < 6 || $length > 80 ) {
                    continue;
                }

                $candidates[] = [
                    'text'   => $substr,
                    'tokens' => array_slice( $tokens, $i, $window ),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Validates candidate anchor.
     *
     * @param array  $candidate Candidate data.
     * @param string $canonical_core_norm Normalized canonical core.
     * @return bool
     */
    protected function is_candidate_valid( $candidate, $canonical_core_norm ) {
        $text = $candidate['text'];
        $normalized = $this->normalize( $text );

        if ( '' === $normalized ) {
            return false;
        }

        if ( preg_match( '/https?:\/\//i', $text ) || preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text ) ) {
            return false;
        }

        if ( preg_match( '/\b\d{2,}\b/', $text ) && preg_match( '/\b\d{7,}\b/', $text ) ) {
            // Likely a phone number.
            return false;
        }

        $tokens_norm = preg_split( '/\s+/u', $normalized );
        $alpha_tokens = array_filter(
            $tokens_norm,
            function ( $token ) {
                return (bool) preg_match( '/[a-z]/u', $token );
            }
        );

        if ( count( $alpha_tokens ) < 2 ) {
            return false;
        }

        $non_stop = array_filter(
            $tokens_norm,
            function ( $token ) {
                return ! in_array( $token, $this->stopwords, true );
            }
        );

        if ( count( $non_stop ) < 2 ) {
            return false;
        }

        foreach ( $this->cta_terms as $term ) {
            if ( false !== strpos( $normalized, $term ) ) {
                return false;
            }
        }

        $contains_core = false;
        foreach ( $this->core_terms as $term ) {
            if ( false !== strpos( $normalized, $term ) ) {
                $contains_core = true;
                break;
            }
        }

        if ( ! $contains_core && '' !== $canonical_core_norm ) {
            $canonical_tokens = preg_split( '/\s+/u', $canonical_core_norm );
            foreach ( $canonical_tokens as $core_token ) {
                if ( '' !== $core_token && false !== strpos( $normalized, $core_token ) ) {
                    $contains_core = true;
                    break;
                }
            }
        }

        if ( ! $contains_core ) {
            return false;
        }

        return true;
    }

    /**
     * Classifies candidate anchor.
     *
     * @param string $anchor_text Anchor.
     * @param string $canonical_norm Normalized canonical string.
     * @param string $canonical_core_norm Normalized canonical core string.
     * @return string
     */
    protected function classify_candidate( $anchor_text, $canonical_norm, $canonical_core_norm ) {
        $anchor_norm = $this->normalize( $anchor_text );

        if ( '' !== $canonical_norm && $anchor_norm === $canonical_norm ) {
            return 'exacta';
        }

        if ( '' !== $canonical_core_norm && $anchor_norm === $canonical_core_norm ) {
            return 'exacta';
        }

        if ( '' !== $canonical_norm && false !== strpos( $anchor_norm, $canonical_norm ) ) {
            return 'frase';
        }

        if ( '' !== $canonical_core_norm && false !== strpos( $anchor_norm, $canonical_core_norm ) ) {
            return 'frase';
        }

        return 'semantica';
    }

    /**
     * Counts frequency of anchor in normalized text.
     *
     * @param string $anchor_text Anchor text.
     * @param string $normalized_text Normalized text.
     * @return int
     */
    protected function count_frequency( $anchor_text, $normalized_text ) {
        $anchor_norm = $this->normalize( $anchor_text );
        if ( '' === $anchor_norm ) {
            return 0;
        }

        $pattern = '/\b' . preg_quote( $anchor_norm, '/' ) . '\b/u';
        if ( preg_match_all( $pattern, $normalized_text, $matches ) ) {
            return count( $matches[0] );
        }

        return substr_count( $normalized_text, $anchor_norm );
    }

    /**
     * Removes duplicates based on signature.
     *
     * @param array $candidates Candidate anchors.
     * @return array
     */
    protected function deduplicate_candidates( $candidates ) {
        $signatures = [];

        foreach ( $candidates as $candidate ) {
            $tokens = preg_split( '/\s+/u', $this->normalize( $candidate['text'] ) );
            $signature_tokens = [];
            foreach ( $tokens as $token ) {
                if ( '' === $token || in_array( $token, $this->stopwords, true ) ) {
                    continue;
                }
                $signature_tokens[] = $token;
            }
            $signature = implode( ' ', $signature_tokens );
            if ( '' === $signature ) {
                $signature = $this->normalize( $candidate['text'] );
            }

            if ( isset( $signatures[ $signature ] ) ) {
                $existing = $signatures[ $signature ];
                if ( $candidate['frequency'] > $existing['frequency'] ) {
                    $signatures[ $signature ] = $candidate;
                } elseif ( $candidate['frequency'] === $existing['frequency'] && mb_strlen( $candidate['text'] ) < mb_strlen( $existing['text'] ) ) {
                    $signatures[ $signature ] = $candidate;
                }
            } else {
                $signatures[ $signature ] = $candidate;
            }
        }

        return array_values( $signatures );
    }

    /**
     * Calculates presets based on word count.
     *
     * @param int $word_count Number of words.
     * @return array
     */
    public function get_presets( $word_count ) {
        if ( $word_count <= 700 ) {
            return [
                'total'     => 4,
                'exacta'    => 1,
                'frase'     => 1,
                'semantica' => 2,
            ];
        }

        if ( $word_count <= 1500 ) {
            return [
                'total'     => 6,
                'exacta'    => 1,
                'frase'     => 3,
                'semantica' => 2,
            ];
        }

        return [
            'total'     => 8,
            'exacta'    => 1,
            'frase'     => 4,
            'semantica' => 3,
        ];
    }
}
