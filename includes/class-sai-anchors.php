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
        'registro', 'regístrate', 'registrate', 'teléfono', 'telefono', 'moldurama', 'mx', 'llámanos', 'llamanos',
        'envíanos', 'envianos'
    ];

    /**
     * Boilerplate phrases to discard.
     *
     * @var array
     */
    protected $boilerplate_phrases = [
        'en definitiva', 'muchos casos', 'otro aspecto', 'en terminos generales', 'en términos generales',
        'estas medidas'
    ];

    /**
     * Low-value verbs to exclude when enforcing verb filters.
     *
     * @var array
     */
    protected $forbidden_verbs = [
        'es', 'son', 'esta', 'está', 'estan', 'están', 'permite', 'permiten', 'permitir', 'ayuda', 'ayudan',
        'ayudar', 'mejora', 'mejoran', 'mejorar', 'aumenta', 'aumentan', 'aumentar', 'resulta', 'resultan',
        'resultar', 'reduce', 'reducen', 'reducir', 'contribuye', 'contribuyen', 'contribuir', 'representa',
        'representan', 'representar', 'ofrece', 'ofrecen', 'ofrecer', 'utilizar', 'utiliza', 'utilizan'
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
     * Base core terms used to seed dynamic topic detection.
     *
     * @var array
     */
    protected $core_terms_base = [
        'poliestireno',
        'eps',
        'unicel',
        'aislamiento',
        'térmico',
        'acústico',
        'losa',
        'reticular',
        'nervada',
        'entrepiso',
        'techo',
        'cubierta',
        'hormigón',
        'concreto',
        'acero',
        'vigueta',
        'cimbra',
        'densidad',
        'humedad',
        'eficiencia energética',
        'transporte',
        'durabilidad',
    ];

    /**
     * Runtime core terms derived per request.
     *
     * @var array
     */
    protected $runtime_core_terms = [];

    /**
     * Optional title injected at runtime.
     *
     * @var string
     */
    public $request_title = '';

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
        $this->runtime_core_terms = [];

        $body_text = $this->prepare_text( $body_text );
        $canonical = trim( (string) $canonical );

        $word_count = $this->get_word_count( $body_text );
        $presets    = $this->get_presets( $word_count );

        if ( '' === $canonical ) {
            return new WP_Error(
                'sai_missing_canonical',
                __( 'La palabra canónica es obligatoria.', 'anchors-sin-ia' ),
                [
                    'status'     => 400,
                    'word_count' => $word_count,
                ]
            );
        }

        if ( '' === $body_text ) {
            return new WP_Error(
                'sai_empty_body',
                __( 'El cuerpo del contenido está vacío tras la limpieza.', 'anchors-sin-ia' ),
                [
                    'status'     => 400,
                    'word_count' => $word_count,
                ]
            );
        }

        $tokens = $this->tokenize_with_positions( $body_text );
        if ( empty( $tokens ) ) {
            return new WP_Error(
                'sai_no_tokens',
                __( 'No hay suficientes términos para generar anchors válidos.', 'anchors-sin-ia' ),
                [
                    'status'     => 400,
                    'word_count' => $word_count,
                ]
            );
        }

        $canonical_norm      = $this->normalize( $canonical );
        $canonical_core      = $this->canonical_core( $canonical );
        $canonical_core_norm = $this->normalize( $canonical_core );

        $maybe_title = '';
        if ( property_exists( $this, 'request_title' ) ) {
            $maybe_title = (string) $this->request_title;
        }

        $this->runtime_core_terms = $this->build_core_terms_dynamic( $canonical, $body_text, $maybe_title );

        $rounds = [
            [ 'min_window' => 2, 'max_window' => 7, 'min_frequency' => 2, 'enforce_verbs' => true ],
            [ 'min_window' => 2, 'max_window' => 8, 'min_frequency' => 2, 'enforce_verbs' => true ],
            [ 'min_window' => 2, 'max_window' => 8, 'min_frequency' => 1, 'enforce_verbs' => true ],
            [ 'min_window' => 2, 'max_window' => 8, 'min_frequency' => 1, 'enforce_verbs' => false ],
        ];

        $candidates   = [];
        $target_total = (int) $presets['total'];

        foreach ( $rounds as $round ) {
            $new_candidates = $this->collect_valid_candidates(
                $tokens,
                $body_text,
                $canonical_norm,
                $canonical_core_norm,
                $round,
                $candidates
            );

            $candidates = $this->merge_candidate_lists( $candidates, $new_candidates );

            if ( empty( $candidates ) ) {
                continue;
            }

            $deduped = $this->deduplicate_candidates( $candidates );

            $grouped = [ 'exacta' => [], 'frase' => [], 'semantica' => [] ];
            foreach ( $deduped as $candidate ) {
                $grouped[ $candidate['classification'] ][] = $candidate;
            }

            $canonical_frequency = $this->count_frequency( $canonical, $body_text );
            if ( $canonical_frequency > 0 && empty( $grouped['exacta'] ) ) {
                $start_position = mb_strpos( $body_text, $canonical, 0, 'UTF-8' );
                if ( false === $start_position ) {
                    $start_position = PHP_INT_MAX;
                }

                $grouped['exacta'][] = [
                    'text'           => $canonical,
                    'classification' => 'exacta',
                    'frequency'      => $canonical_frequency,
                    'start'          => $start_position,
                ];
            }

            foreach ( $grouped as &$items ) {
                usort(
                    $items,
                    function ( $a, $b ) {
                        if ( $a['frequency'] === $b['frequency'] ) {
                            $len_compare = mb_strlen( $a['text'], 'UTF-8' ) <=> mb_strlen( $b['text'], 'UTF-8' );
                            if ( 0 !== $len_compare ) {
                                return $len_compare;
                            }

                            return ( $a['start'] ?? PHP_INT_MAX ) <=> ( $b['start'] ?? PHP_INT_MAX );
                        }

                        return $b['frequency'] <=> $a['frequency'];
                    }
                );
            }
            unset( $items );

            $available_total = count( $grouped['exacta'] ) + count( $grouped['frase'] ) + count( $grouped['semantica'] );
            if ( $available_total < $target_total ) {
                continue;
            }

            $quotas  = $this->resolve_quotas( $presets, $grouped );
            if ( is_wp_error( $quotas ) ) {
                $this->runtime_core_terms = [];
                return $quotas;
            }
            $anchors = $this->select_anchors( $grouped, $quotas );

            if ( count( $anchors ) === $target_total ) {
                $counts_actual = [ 'exacta' => 0, 'frase' => 0, 'semantica' => 0 ];
                foreach ( $anchors as $anchor ) {
                    if ( isset( $counts_actual[ $anchor['class'] ] ) ) {
                        $counts_actual[ $anchor['class'] ]++;
                    }
                }
                $result = [
                    'word_count'      => $word_count,
                    'suggested_total' => count( $anchors ),
                    'quotas'          => [
                        'total'     => count( $anchors ),
                        'exacta'    => $counts_actual['exacta'],
                        'frase'     => $counts_actual['frase'],
                        'semantica' => $counts_actual['semantica'],
                    ],
                    'anchors'         => $anchors,
                ];

                $this->runtime_core_terms = [];

                return $result;
            }
        }

        $error = new WP_Error(
            'sai_no_candidates',
            __( 'No hay suficientes frases válidas para cubrir las cuotas SEO sin violar las reglas (exacta/frase/semántica).', 'anchors-sin-ia' ),
            [
                'status'     => 422,
                'word_count' => $word_count,
            ]
        );

        $this->runtime_core_terms = [];

        return $error;
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
     * Builds dynamic core terms using canonical, title and body context.
     *
     * @param string $canonical    Canonical keyword.
     * @param string $context_text Body text context.
     * @param string $maybe_title  Optional title text.
     * @return array
     */
    protected function build_core_terms_dynamic( $canonical, $context_text, $maybe_title = '' ) {
        $terms = [];

        foreach ( $this->core_terms_base as $term ) {
            $normalized = $this->normalize( $term );
            if ( '' !== $normalized ) {
                $terms[] = $normalized;
            }
        }

        foreach ( $this->core_terms as $term ) {
            $normalized = $this->normalize( $term );
            if ( '' !== $normalized ) {
                $terms[] = $normalized;
            }
        }

        $canonical_norm = $this->normalize( $canonical );
        if ( '' !== $canonical_norm ) {
            $terms[] = $canonical_norm;
        }

        $canonical_core = $this->canonical_core( $canonical );
        $canonical_core_norm = $this->normalize( $canonical_core );
        if ( '' !== $canonical_core_norm ) {
            $terms[] = $canonical_core_norm;

            $core_tokens = preg_split( '/\s+/u', $canonical_core_norm );
            foreach ( $core_tokens as $token ) {
                if ( '' !== $token && ! in_array( $token, $this->stopwords, true ) ) {
                    $terms[] = $token;
                }
            }
        }

        $title_source = trim( (string) $maybe_title );
        if ( '' === $title_source ) {
            $snippet_words = preg_split( '/\s+/u', trim( (string) $context_text ) );
            if ( ! empty( $snippet_words ) ) {
                $title_source = implode( ' ', array_slice( $snippet_words, 0, 12 ) );
            }
        }

        if ( '' !== $title_source ) {
            $title_norm    = $this->normalize( $title_source );
            $title_tokens  = array_filter( preg_split( '/\s+/u', $title_norm ) );
            foreach ( $title_tokens as $token ) {
                if ( '' === $token || in_array( $token, $this->stopwords, true ) ) {
                    continue;
                }

                $terms[] = $token;
            }
        }

        $ngrams = $this->top_ngrams_from_text( $context_text, 1, 3, 12 );
        foreach ( $ngrams as $ngram ) {
            if ( '' !== $ngram ) {
                $terms[] = $ngram;
            }
        }

        $terms = array_filter(
            $terms,
            function ( $term ) {
                return '' !== $term;
            }
        );

        $terms = array_values( array_unique( $terms ) );

        return $terms;
    }

    /**
     * Extracts frequent n-grams from text to seed dynamic core terms.
     *
     * @param string $text   Text to analyze.
     * @param int    $min_n  Minimum n-gram size.
     * @param int    $max_n  Maximum n-gram size.
     * @param int    $limit  Maximum n-grams to return.
     * @return array
     */
    protected function top_ngrams_from_text( $text, $min_n = 1, $max_n = 3, $limit = 12 ) {
        $text   = $this->prepare_text( $text );
        $min_n  = max( 1, (int) $min_n );
        $max_n  = max( $min_n, (int) $max_n );
        $limit  = max( 1, (int) $limit );
        $ngrams = [];

        if ( '' === $text ) {
            return [];
        }

        if ( ! preg_match_all( '/\b[\p{L}0-9][\p{L}0-9\p{Mn}\p{Pd}]*\b/u', $text, $matches ) ) {
            return [];
        }

        $tokens = [];
        foreach ( $matches[0] as $match ) {
            $normalized = $this->normalize_token( $match );
            if ( '' === $normalized ) {
                continue;
            }

            $tokens[] = $normalized;
        }

        $token_count = count( $tokens );
        if ( 0 === $token_count ) {
            return [];
        }

        $candidates = [];

        for ( $n = $min_n; $n <= $max_n; $n++ ) {
            if ( $n > $token_count ) {
                break;
            }

            for ( $i = 0; $i <= $token_count - $n; $i++ ) {
                $slice = array_slice( $tokens, $i, $n );
                if ( empty( $slice ) ) {
                    continue;
                }

                $first = $slice[0];
                $last  = $slice[ count( $slice ) - 1 ];

                if ( in_array( $first, $this->stopwords, true ) || in_array( $last, $this->stopwords, true ) ) {
                    continue;
                }

                $non_stop = 0;
                foreach ( $slice as $token ) {
                    if ( ! in_array( $token, $this->stopwords, true ) ) {
                        $non_stop++;
                    }
                }

                if ( $non_stop < 1 ) {
                    continue;
                }

                $phrase = implode( ' ', $slice );

                if ( '' === $phrase ) {
                    continue;
                }

                if ( isset( $candidates[ $phrase ] ) ) {
                    $candidates[ $phrase ]['frequency']++;
                } else {
                    $candidates[ $phrase ] = [
                        'text'      => $phrase,
                        'frequency' => 1,
                        'length'    => mb_strlen( $phrase, 'UTF-8' ),
                        'start'     => $i,
                    ];
                }
            }
        }

        if ( empty( $candidates ) ) {
            return [];
        }

        $items = array_values( $candidates );
        usort(
            $items,
            function ( $a, $b ) {
                if ( $a['frequency'] === $b['frequency'] ) {
                    if ( $a['length'] === $b['length'] ) {
                        return $a['start'] <=> $b['start'];
                    }

                    return $a['length'] <=> $b['length'];
                }

                return $b['frequency'] <=> $a['frequency'];
            }
        );

        $items = array_slice( $items, 0, $limit );

        return array_map(
            function ( $item ) {
                return $item['text'];
            },
            $items
        );
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

    protected function char_pos_from_byte_offset( $text, $byte_offset ) {
        return mb_strlen( substr( $text, 0, $byte_offset ), 'UTF-8' );
    }

    /**
     * Generates candidate anchors from tokens.
     *
     * @param array  $tokens Tokens with positions.
     * @param string $text   Full text.
     * @return array
     */
    protected function generate_candidates( $tokens, $text, $min_window = 2, $max_window = 7 ) {
        $candidates  = [];
        $token_count = count( $tokens );

        for ( $i = 0; $i < $token_count; $i++ ) {
            for ( $window = $min_window; $window <= $max_window; $window++ ) {
                $end_index = $i + $window - 1;
                if ( $end_index >= $token_count ) {
                    break;
                }

                // Offsets en bytes (de preg_match_all)
                $start_byte = $tokens[ $i ]['offset'];
                $end_token  = $tokens[ $end_index ];
                $end_byte   = $end_token['offset'] + strlen( $end_token['token'] );

                // Convertir a posiciones en caracteres (para mb_substr)
                $start_char = $this->char_pos_from_byte_offset( $text, $start_byte );
                $end_char   = $this->char_pos_from_byte_offset( $text, $end_byte );

                $substr = mb_substr( $text, $start_char, $end_char - $start_char, 'UTF-8' );
                $substr = $this->prepare_text( $substr );
                $length = mb_strlen( $substr, 'UTF-8' );

                if ( $length < 6 || $length > 80 ) {
                    continue;
                }

                $candidates[] = [
                    'text'   => $substr,
                    'tokens' => array_slice( $tokens, $i, $window ),
                    'start'  => $start_char,
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
    protected function is_candidate_valid( $candidate, $canonical_core_norm, $enforce_verbs = true ) {
        $text = $candidate['text'];
        $normalized = $this->normalize( $text );

        if ( '' === $normalized ) {
            return false;
        }

        if ( preg_match( "/[\\.,;:\"'()\\[\\]{}<>]/u", $text ) ) {
            return false;
        }

        if ( false !== mb_strpos( $text, '“' ) || false !== mb_strpos( $text, '”' ) || false !== mb_strpos( $text, '«' ) || false !== mb_strpos( $text, '»' ) || false !== mb_strpos( $text, '—' ) || false !== mb_strpos( $text, '–' ) ) {
            return false;
        }

        if ( false !== strpos( $text, '%' ) ) {
            return false;
        }

        if ( preg_match( '/https?:\/\//i', $text ) || preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text ) ) {
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

        foreach ( $this->boilerplate_phrases as $phrase ) {
            if ( false !== strpos( $normalized, $phrase ) ) {
                return false;
            }
        }

        if ( preg_match( '/\b\d{2,}\b/', $text ) && preg_match( '/\b\d{7,}\b/', $text ) ) {
            // Likely a phone number.
            return false;
        }

        if ( preg_match( '/[\d\.,]+\s?(%|usd|mxn|eur|\$)/iu', $text ) ) {
            return false;
        }

        if ( preg_match( '/\.[a-z]{2,}/iu', $text ) ) {
            if ( preg_match( '/\b[a-z0-9.-]+\.(com|mx|net|org|biz|info|edu|gob)(?:\.[a-z]{2})?\b/iu', $text ) ) {
                return false;
            }
        }

        $first_token = $candidate['tokens'][0]['token'];
        $last_token  = $candidate['tokens'][ count( $candidate['tokens'] ) - 1 ]['token'];
        $first_norm  = $this->normalize_token( $first_token );
        $last_norm   = $this->normalize_token( $last_token );

        if ( '' === $first_norm || in_array( $first_norm, $this->stopwords, true ) ) {
            return false;
        }

        if ( '' === $last_norm || in_array( $last_norm, $this->stopwords, true ) ) {
            return false;
        }

        if ( $enforce_verbs ) {
            foreach ( $tokens_norm as $token ) {
                if ( in_array( $token, $this->forbidden_verbs, true ) ) {
                    return false;
                }
            }
        }

        $core_terms = ! empty( $this->runtime_core_terms ) ? $this->runtime_core_terms : $this->core_terms;
        $contains_core = false;
        foreach ( $core_terms as $term ) {
            $term_norm = $this->normalize( $term );
            if ( '' === $term_norm ) {
                continue;
            }

            if ( false !== mb_strpos( $normalized, $term_norm, 0, 'UTF-8' ) ) {
                $contains_core = true;
                break;
            }
        }

        if ( ! $contains_core ) {
            return false;
        }

        return true;
    }

    /**
     * Collects valid candidates within a range of n-grams.
     *
     * @param array  $tokens Tokens with offsets.
     * @param string $text   Body text.
     * @param string $canonical_norm Normalized canonical.
     * @param string $canonical_core_norm Normalized canonical core.
     * @param array  $options  Extraction options (min_window, max_window, min_frequency, enforce_verbs).
     * @param array  $existing Already accepted candidates.
     * @return array
     */
    protected function collect_valid_candidates( $tokens, $text, $canonical_norm, $canonical_core_norm, $options, $existing = [] ) {
        $min_window     = isset( $options['min_window'] ) ? max( 2, (int) $options['min_window'] ) : 2;
        $max_window     = isset( $options['max_window'] ) ? max( $min_window, (int) $options['max_window'] ) : 7;
        $min_frequency  = isset( $options['min_frequency'] ) ? max( 1, (int) $options['min_frequency'] ) : 1;
        $enforce_verbs  = isset( $options['enforce_verbs'] ) ? (bool) $options['enforce_verbs'] : true;
        $raw_candidates = $this->generate_candidates( $tokens, $text, $min_window, $max_window );

        $existing_texts = [];
        foreach ( $existing as $candidate ) {
            if ( isset( $candidate['text'] ) ) {
                $existing_texts[ $candidate['text'] ] = true;
            }
        }

        $valid = [];

        foreach ( $raw_candidates as $candidate ) {
            if ( isset( $existing_texts[ $candidate['text'] ] ) ) {
                continue;
            }

            if ( ! $this->is_candidate_valid( $candidate, $canonical_core_norm, $enforce_verbs ) ) {
                continue;
            }

            $frequency = $this->count_frequency( $candidate['text'], $text );
            if ( $frequency < $min_frequency ) {
                continue;
            }

            $classification = $this->classify_candidate( $candidate['text'], $canonical_norm, $canonical_core_norm );
            $candidate['classification'] = $classification;
            $candidate['frequency']      = $frequency;
            $valid[]                     = $candidate;
            $existing_texts[ $candidate['text'] ] = true;
        }

        return $valid;
    }

    /**
     * Merges candidate lists by text keeping the strongest frequency.
     *
     * @param array $primary Primary list.
     * @param array $additional Additional list.
     * @return array
     */
    protected function merge_candidate_lists( $primary, $additional ) {
        if ( empty( $primary ) ) {
            return $additional;
        }

        foreach ( $additional as $candidate ) {
            $found = false;
            foreach ( $primary as &$existing ) {
                if ( $existing['text'] === $candidate['text'] ) {
                    $found = true;
                    if ( $candidate['frequency'] > $existing['frequency'] ) {
                        $existing = $candidate;
                    } elseif ( $candidate['frequency'] === $existing['frequency'] ) {
                        $existing_length  = mb_strlen( $existing['text'], 'UTF-8' );
                        $candidate_length = mb_strlen( $candidate['text'], 'UTF-8' );

                        if ( $candidate_length < $existing_length ) {
                            $existing = $candidate;
                        } elseif ( $candidate_length === $existing_length ) {
                            $existing_start  = $existing['start'] ?? PHP_INT_MAX;
                            $candidate_start = $candidate['start'] ?? PHP_INT_MAX;

                            if ( $candidate_start < $existing_start ) {
                                $existing = $candidate;
                            }
                        }
                    }
                    break;
                }
            }
            unset( $existing );

            if ( ! $found ) {
                $primary[] = $candidate;
            }
        }

        return $primary;
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
     * Counts frequency of anchor using strict word boundaries on original text.
     *
     * @param string $anchor_text Anchor text.
     * @param string $text        Body text.
     * @return int
     */
    protected function count_frequency( $anchor_text, $text ) {
        $anchor_text = $this->prepare_text( $anchor_text );
        if ( '' === $anchor_text || '' === $text ) {
            return 0;
        }

        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $anchor_text, '/' ) . '(?![\p{L}\p{N}])/u';
        $matches = preg_match_all( $pattern, $text, $results );
        if ( false !== $matches && $matches > 0 ) {
            return (int) $matches;
        }

        $fallback = substr_count( $text, $anchor_text );
        return (int) $fallback;
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
                } elseif ( $candidate['frequency'] === $existing['frequency'] ) {
                    $existing_length  = mb_strlen( $existing['text'], 'UTF-8' );
                    $candidate_length = mb_strlen( $candidate['text'], 'UTF-8' );

                    if ( $candidate_length < $existing_length ) {
                        $signatures[ $signature ] = $candidate;
                    } elseif ( $candidate_length === $existing_length ) {
                        $existing_start  = $existing['start'] ?? PHP_INT_MAX;
                        $candidate_start = $candidate['start'] ?? PHP_INT_MAX;

                        if ( $candidate_start < $existing_start ) {
                            $signatures[ $signature ] = $candidate;
                        }
                    }
                }
            } else {
                $signatures[ $signature ] = $candidate;
            }
        }

        return array_values( $signatures );
    }

    /**
     * Resolves quotas enforcing preset counts without reassignments.
     *
     * @param array $presets Base presets.
     * @param array $grouped Candidates grouped by class.
     * @return array
     */
    /**
     * Resuelve cuotas con fallback elástico:
     * - Intenta cubrir las cuotas preset por tipo.
     * - Si falta alguna (p.ej. "frase"), rellena con otras clases disponibles,
     *   priorizando: frase <- semantica <- exacta, hasta alcanzar el total preset.
     * - Si aun así no se llega al total, devuelve WP_Error.
     *
     * @param array $presets  Base presets (total, exacta, frase, semantica).
     * @param array $grouped  Candidatos agrupados por clase.
     * @return array|WP_Error Cuotas finales por clase.
     */
    /**
     * Resuelve cuotas con fallback elástico:
     * - Intenta cubrir las cuotas preset por tipo.
     * - Si falta alguna (p.ej. "frase"), rellena con otras clases disponibles,
     *   priorizando: frase <- semantica <- exacta, hasta alcanzar el total preset.
     * - Si aun así no se llega al total, devuelve WP_Error.
     *
     * @param array $presets  Base presets (total, exacta, frase, semantica).
     * @param array $grouped  Candidatos agrupados por clase.
     * @return array|WP_Error Cuotas finales por clase.
     */
    protected function resolve_quotas( $presets, $grouped ) {
        $available = [
            'exacta'    => isset( $grouped['exacta'] ) ? count( $grouped['exacta'] ) : 0,
            'frase'     => isset( $grouped['frase'] ) ? count( $grouped['frase'] ) : 0,
            'semantica' => isset( $grouped['semantica'] ) ? count( $grouped['semantica'] ) : 0,
        ];

        $need = [
            'total'     => (int) $presets['total'],
            'exacta'    => (int) $presets['exacta'],
            'frase'     => (int) $presets['frase'],
            'semantica' => (int) $presets['semantica'],
        ];

        // Paso 1: asignación base limitada por disponibilidad.
        $quotas = [
            'exacta'    => min( $need['exacta'], $available['exacta'] ),
            'frase'     => min( $need['frase'], $available['frase'] ),
            'semantica' => min( $need['semantica'], $available['semantica'] ),
        ];

        $assigned = $quotas['exacta'] + $quotas['frase'] + $quotas['semantica'];

        if ( $assigned >= $need['total'] ) {
            return $quotas;
        }

        // Preferencias para compensar déficits.
        $order_fill = [
            'frase'     => [ 'semantica', 'exacta' ],
            'semantica' => [ 'frase', 'exacta' ],
            'exacta'    => [ 'frase', 'semantica' ],
        ];

        // Excedentes disponibles por clase:
        $surplus = [
            'exacta'    => max( 0, $available['exacta'] - $quotas['exacta'] ),
            'frase'     => max( 0, $available['frase'] - $quotas['frase'] ),
            'semantica' => max( 0, $available['semantica'] - $quotas['semantica'] ),
        ];

        // Paso 2: intentar cumplir cuotas objetivo por clase usando excedentes de otras.
        foreach ( [ 'frase', 'semantica', 'exacta' ] as $target ) {
            while ( $assigned < $need['total'] && $quotas[ $target ] < $need[ $target ] ) {
                $filled = false;
                foreach ( $order_fill[ $target ] as $src ) {
                    if ( $surplus[ $src ] > 0 ) {
                        $surplus[ $src ]--;
                        $quotas[ $target ]++;
                        $assigned++;
                        $filled = true;
                        break;
                    }
                }
                if ( ! $filled ) {
                    break;
                }
            }
        }

        // Paso 3: si aún falta para el total, añadir con lo que quede (prioridad SEO: frase -> semantica -> exacta).
        foreach ( [ 'frase', 'semantica', 'exacta' ] as $src ) {
            while ( $assigned < $need['total'] && $surplus[ $src ] > 0 ) {
                $surplus[ $src ]--;
                $quotas[ $src ]++;
                $assigned++;
            }
        }

        if ( $assigned < $need['total'] ) {
            return new WP_Error(
                'sai_quota_deficit',
                sprintf(
                    __( 'No se pueden cubrir las cuotas SEO: total necesario %d, candidatos disponibles %d', 'anchors-sin-ia' ),
                    $need['total'],
                    $assigned
                ),
                [
                    'status'     => 422,
                    'need'       => $need,
                    'available'  => $available,
                    'assigned'   => $quotas,
                ]
            );
        }

        return $quotas;
    }

    /**
     * Selects anchors according to resolved quotas.
     *
     * @param array $grouped Grouped candidates.
     * @param array $quotas  Final quotas per class.
     * @return array
     */
    protected function select_anchors( $grouped, $quotas ) {
        $order    = [ 'exacta', 'frase', 'semantica' ];
        $selected = [];

        foreach ( $order as $type ) {
            if ( empty( $quotas[ $type ] ) || empty( $grouped[ $type ] ) ) {
                continue;
            }

            $slice    = array_slice( $grouped[ $type ], 0, (int) $quotas[ $type ] );
            $selected = array_merge( $selected, $slice );
        }

        $order_map = [ 'exacta' => 0, 'frase' => 1, 'semantica' => 2 ];
        usort(
            $selected,
            function ( $a, $b ) use ( $order_map ) {
                $class_cmp = $order_map[ $a['classification'] ] <=> $order_map[ $b['classification'] ];
                if ( 0 !== $class_cmp ) {
                    return $class_cmp;
                }

                if ( $a['frequency'] === $b['frequency'] ) {
                    $len_compare = mb_strlen( $a['text'], 'UTF-8' ) <=> mb_strlen( $b['text'], 'UTF-8' );
                    if ( 0 !== $len_compare ) {
                        return $len_compare;
                    }

                    return ( $a['start'] ?? PHP_INT_MAX ) <=> ( $b['start'] ?? PHP_INT_MAX );
                }

                return $b['frequency'] <=> $a['frequency'];
            }
        );

        return array_map(
            function ( $item ) {
                return [
                    'text'      => $item['text'],
                    'class'     => $item['classification'],
                    'frequency' => (int) $item['frequency'],
                ];
            },
            $selected
        );
    }

    /**
     * Normalizes token for edge validation.
     *
     * @param string $token Token text.
     * @return string
     */
    protected function normalize_token( $token ) {
        $token = strtolower( remove_accents( $token ) );
        $token = preg_replace( '/[^\p{L}0-9]+/u', '', $token );
        return trim( $token );
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
