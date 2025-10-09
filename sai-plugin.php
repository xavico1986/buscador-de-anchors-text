<?php
/**
 * Plugin Name: Anchors sin IA
 * Description: Herramienta para buscar entradas y extraer anchors sin IA.
 * Version: 1.0.0
 * Author: OpenAI
 * Text Domain: anchors-sin-ia
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SAI_PLUGIN_FILE' ) ) {
    define( 'SAI_PLUGIN_FILE', __FILE__ );
}

define( 'SAI_PLUGIN_DIR', plugin_dir_path( SAI_PLUGIN_FILE ) );
define( 'SAI_PLUGIN_URL', plugin_dir_url( SAI_PLUGIN_FILE ) );

require_once SAI_PLUGIN_DIR . 'includes/class-sai-anchors.php';
require_once SAI_PLUGIN_DIR . 'includes/linkbuilder-helpers.php';
require_once SAI_PLUGIN_DIR . 'includes/class-sai-rest.php';

class Anchors_Sin_IA_Plugin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        new SAI_REST_Controller();
    }

    /**
     * Registers the admin page under Tools.
     */
    public function register_admin_page() {
        add_management_page(
            __( 'Anchors sin IA', 'anchors-sin-ia' ),
            __( 'Anchors sin IA', 'anchors-sin-ia' ),
            'edit_posts',
            'anchors-sin-ia',
            [ $this, 'render_admin_page' ]
        );

        // Página del flujo Linkbuilder
        add_management_page(
            __( 'Linkbuilder sin IA', 'anchors-sin-ia' ),
            __( 'Linkbuilder sin IA', 'anchors-sin-ia' ),
            'edit_posts',
            'sai-linkbuilder',
            [ $this, 'render_linkbuilder_page' ]
        );
    }

    /**
     * Outputs the container for the admin app.
     */
    public function render_admin_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Anchors sin IA', 'anchors-sin-ia' ) . '</h1><div id="sai-app"></div></div>';
    }

    /**
     * Outputs the container for the linkbuilder flow.
     */
    public function render_linkbuilder_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Linkbuilder sin IA', 'anchors-sin-ia' ) . '</h1><div id="sai-linkbuilder-app"></div></div>';
    }

    /**
     * Enqueue assets only on plugin pages.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {

        // Página principal del extractor (anchors)
        if ( 'tools_page_anchors-sin-ia' === $hook ) {
            wp_enqueue_style(
                'anchors-sin-ia-admin',
                SAI_PLUGIN_URL . 'assets/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'anchors-sin-ia-admin',
                SAI_PLUGIN_URL . 'assets/admin.js',
                [],
                '1.0.0',
                true
            );

            wp_localize_script(
                'anchors-sin-ia-admin',
                'AnchorsSinIA',
                [
                    'restUrl' => esc_url_raw( rest_url( 'anchors/v1/' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'perPage' => 50,
                    'i18n'    => [
                        'search'         => __( 'Buscar', 'anchors-sin-ia' ),
                        'view'           => __( 'Ver', 'anchors-sin-ia' ),
                        'select'         => __( 'Seleccionar', 'anchors-sin-ia' ),
                        'noResults'      => __( 'Sin resultados.', 'anchors-sin-ia' ),
                        'copySuccess'    => __( 'Anchors copiados al portapapeles.', 'anchors-sin-ia' ),
                        'copyError'      => __( 'No se pudo copiar. Copie manualmente.', 'anchors-sin-ia' ),
                        'loading'        => __( 'Cargando...', 'anchors-sin-ia' ),
                        'keywordLabel'   => __( 'Palabra clave (canónico)', 'anchors-sin-ia' ),
                        'includeBody'    => __( 'Buscar también en el contenido', 'anchors-sin-ia' ),
                        'wordCount'      => __( 'Palabras', 'anchors-sin-ia' ),
                        'preset'         => __( 'Preset', 'anchors-sin-ia' ),
                        'extractAnchors' => __( 'Extraer anchors', 'anchors-sin-ia' ),
                        'copyAnchors'    => __( 'Copiar anchors', 'anchors-sin-ia' ),
                        'tableHeader'    => [ __( 'Anchor', 'anchors-sin-ia' ), __( 'Clasificación', 'anchors-sin-ia' ), __( 'Frecuencia', 'anchors-sin-ia' ) ],
                        'keywordRequired'=> __( 'Introduce una palabra clave.', 'anchors-sin-ia' ),
                        'loadError'      => __( 'Ocurrió un error. Inténtalo nuevamente.', 'anchors-sin-ia' ),
                        'noAnchors'      => __( 'No hay anchors disponibles.', 'anchors-sin-ia' ),
                        'back'           => __( 'Volver a la búsqueda', 'anchors-sin-ia' ),
                        'extracting'     => __( 'Extrayendo...', 'anchors-sin-ia' ),
                        'pageLabel'      => __( 'Página', 'anchors-sin-ia' ),
                        'usedQuotas'     => __( 'Cuotas usadas', 'anchors-sin-ia' ),
                    ],
                ]
            );
            return;
        }

        // Página del linkbuilder por pasos
        if ( 'tools_page_sai-linkbuilder' === $hook ) {
            wp_enqueue_style(
                'anchors-sin-ia-admin',
                SAI_PLUGIN_URL . 'assets/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'anchors-sin-ia-linkbuilder',
                SAI_PLUGIN_URL . 'assets/linkbuilder.js',
                [],
                '1.0.0',
                true
            );

            wp_localize_script(
                'anchors-sin-ia-linkbuilder',
                'AnchorsSinIALinkbuilder',
                [
                    'restUrl' => esc_url_raw( rest_url( 'anchors/v1/' ) ),
                    'nonce'   => wp_create_nonce( 'wp_rest' ),
                    'perPage' => 50,
                    'i18n'    => [
                        'search'            => __( 'Buscar', 'anchors-sin-ia' ),
                        'includeBody'       => __( 'Buscar también en el contenido', 'anchors-sin-ia' ),
                        'keywordLabel'      => __( 'Palabra clave (canónico)', 'anchors-sin-ia' ),
                        'madreHeading'      => __( 'Paso 1: Selecciona la madre', 'anchors-sin-ia' ),
                        'hijasHeading'      => __( 'Paso 2: Selecciona las hijas', 'anchors-sin-ia' ),
                        'nietasHeading'     => __( 'Paso 3: Selecciona las nietas', 'anchors-sin-ia' ),
                        'exportHeading'     => __( 'Paso 4: Exportar CSV', 'anchors-sin-ia' ),
                        'next'              => __( 'Continuar', 'anchors-sin-ia' ),
                        'saveMadre'         => __( 'Guardar madre y continuar', 'anchors-sin-ia' ),
                        'saveHijas'         => __( 'Guardar hijas y continuar', 'anchors-sin-ia' ),
                        'saveNietas'        => __( 'Guardar nietas y continuar', 'anchors-sin-ia' ),
                        'reset'             => __( 'Reiniciar flujo', 'anchors-sin-ia' ),
                        'loading'           => __( 'Cargando...', 'anchors-sin-ia' ),
                        'noResults'         => __( 'Sin resultados.', 'anchors-sin-ia' ),
                        'view'              => __( 'Ver', 'anchors-sin-ia' ),
                        'select'            => __( 'Seleccionar', 'anchors-sin-ia' ),
                        'selectionLimit'    => __( 'Límite de selección', 'anchors-sin-ia' ),
                        'selected'          => __( 'Seleccionados', 'anchors-sin-ia' ),
                        'canonical'         => __( 'Canónico', 'anchors-sin-ia' ),
                        'madreAnchors'      => __( 'Anchors de la madre', 'anchors-sin-ia' ),
                        'hijaAnchors'       => __( 'Anchors por hija', 'anchors-sin-ia' ),
                        'wordCount'         => __( 'Palabras', 'anchors-sin-ia' ),
                        'preset'            => __( 'Preset', 'anchors-sin-ia' ),
                        'exportCsv'         => __( 'Exportar CSV', 'anchors-sin-ia' ),
                        'exportSummary'     => __( 'Resumen de enlaces', 'anchors-sin-ia' ),
                        'copyError'         => __( 'No se pudo completar la acción.', 'anchors-sin-ia' ),
                        'step'              => __( 'Paso', 'anchors-sin-ia' ),
                        'backToStep'        => __( 'Volver al paso', 'anchors-sin-ia' ),
                        'cannibalHeading'   => __( 'Canibalización', 'anchors-sin-ia' ),
                        'csvGenerated'      => __( 'CSV generado correctamente.', 'anchors-sin-ia' ),
                        'limitExceeded'     => __( 'Has superado el límite de selección.', 'anchors-sin-ia' ),
                        'missingMadre'      => __( 'Selecciona una madre antes de continuar.', 'anchors-sin-ia' ),
                        'missingHijas'      => __( 'Selecciona al menos una hija.', 'anchors-sin-ia' ),
                        'missingNietas'     => __( 'Selecciona al menos una nieta.', 'anchors-sin-ia' ),
                        'exportError'       => __( 'No se pudo generar el CSV.', 'anchors-sin-ia' ),
                    ],
                ]
            );
            return;
        }
    }
}

new Anchors_Sin_IA_Plugin();

