<?php
namespace GuideOS\AdventCalendar;

use WP_Block;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {
    private static $instance;

    /**
     * Stores block instance metadata for front-end bootstrapping.
     *
     * @var array
     */
    private $instances = [];
    private $settings_printed = false;

    const TEST_COOKIE = 'guideos_advent_test';
    const AJAX_ACTION = 'guideos_advent_open_door';
    const CACHE_PREFIX = 'guideos_advent_';
    const CACHE_TTL    = DAY_IN_SECONDS;
    const ISO_URL      = 'https://downloads.guideos.de/GuideOS-1.0.iso';

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_open_door' ] );
        add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ $this, 'handle_open_door' ] );
        add_action( 'wp_footer', [ $this, 'print_instance_settings' ], 20 );
    }

    public function register_block(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build';

        if ( ! file_exists( $asset_path . '/block.json' ) ) {
            return;
        }

        register_block_type( $asset_path, [
            'render_callback' => [ $this, 'render_block' ],
        ] );
    }

    public function enqueue_editor_assets(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build/index.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }
        $asset = include $asset_path;
        wp_enqueue_script( 'guideos-advent-editor', GUIDEOS_ADVENT_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
        wp_enqueue_style( 'guideos-advent-editor', GUIDEOS_ADVENT_URL . 'build/index.css', [], GUIDEOS_ADVENT_VERSION );
    }

    public function enqueue_frontend_assets(): void {
        $asset_path = GUIDEOS_ADVENT_PATH . 'build/view.asset.php';
        if ( ! file_exists( $asset_path ) ) {
            return;
        }

        $asset = include $asset_path;
        wp_register_script( 'guideos-advent-view', GUIDEOS_ADVENT_URL . 'build/view.js', $asset['dependencies'], $asset['version'], true );
        wp_script_add_data( 'guideos-advent-view', 'strategy', 'defer' );
        wp_register_style( 'guideos-advent-style', GUIDEOS_ADVENT_URL . 'build/style-index.css', [], GUIDEOS_ADVENT_VERSION );
    }

    private function enqueue_inline_script(): void {
        static $script_added = false;
        if ( $script_added ) {
            return;
        }

        $view_js_path = GUIDEOS_ADVENT_PATH . 'build/view.js';
        if ( file_exists( $view_js_path ) ) {
            $view_js_content = file_get_contents( $view_js_path );
            if ( $view_js_content ) {
                wp_add_inline_script( 'guideos-advent-view', $view_js_content );
                $script_added = true;
            }
        }
    }

    public function render_block( array $attributes, string $content, WP_Block $block ): string {
        $instance_id = $attributes['instanceId'] ?? '';
        if ( empty( $instance_id ) ) {
            return '';
        }

        $this->maybe_enable_test_cookie();

        $doors     = $this->prepare_doors( $attributes['doors'] ?? [] );
        $post_id   = $block->context['postId'] ?? 0;
        $test_mode = $this->has_test_cookie();
        $available = $this->get_available_day();

        $this->cache_instance_payload( $instance_id, $post_id, $doors );
        $this->register_instance_bootstrap( $post_id, $instance_id, $doors );

        wp_enqueue_script( 'guideos-advent-view' );
        wp_enqueue_style( 'guideos-advent-style' );

        $wrapper_attributes = $this->get_wrapper_attributes( $attributes, $instance_id );

        // Embed JavaScript inline to ensure it loads
        $this->enqueue_inline_script();

        ob_start();
        ?>
        <section <?php echo $wrapper_attributes; ?>>
            <div class="guideos-advent__inner">
                <?php if ( ! empty( $attributes['headline'] ) ) : ?>
                    <h2 class="guideos-advent__headline"><?php echo esc_html( $attributes['headline'] ); ?></h2>
                <?php endif; ?>
                <?php if ( ! empty( $attributes['subline'] ) ) : ?>
                    <p class="guideos-advent__subline"><?php echo esc_html( $attributes['subline'] ); ?></p>
                <?php endif; ?>
                <?php if ( $test_mode ) : ?>
                    <span class="guideos-advent__badge"><?php esc_html_e( 'Testmodus aktiv – alle Türen offen', 'guideos-advent' ); ?></span>
                <?php endif; ?>
                <div class="guideos-advent__grid" role="list">
                    <?php foreach ( $doors as $door ) :
                        $day      = (int) $door['day'];
                        $locked   = ! $test_mode && $day > $available;
                        $door_cls = [ 'guideos-advent__door' ];
                        if ( $locked ) {
                            $door_cls[] = 'is-locked';
                        }
                        if ( ! $locked && $day === $available && $available <= 24 && 0 !== $available ) {
                            $door_cls[] = 'is-today';
                        }
                        if ( 24 === $day ) {
                            $door_cls[] = 'is-grand';
                        }
                        ?>
                        <button
                            type="button"
                            class="<?php echo esc_attr( implode( ' ', $door_cls ) ); ?>"
                            data-day="<?php echo esc_attr( $day ); ?>"
                            data-locked="<?php echo $locked ? '1' : '0'; ?>"
                            aria-pressed="false"
                        >
                            <span class="guideos-advent__door-hinge" aria-hidden="true"></span>
                            <span class="guideos-advent__door-panel">
                                <span class="guideos-advent__door-number"><?php echo esc_html( $day ); ?></span>
                                <?php if ( ! empty( $door['title'] ) ) : ?>
                                    <span class="guideos-advent__door-title"><?php echo esc_html( $door['title'] ); ?></span>
                                <?php endif; ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="guideos-advent__status" aria-live="polite"></p>
                <div class="guideos-advent__modal" hidden>
                    <div class="guideos-advent__modal-backdrop"></div>
                    <div class="guideos-advent__modal-content" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Überraschung', 'guideos-advent' ); ?>">
                        <button class="guideos-advent__modal-close" type="button" aria-label="<?php esc_attr_e( 'Modal schließen', 'guideos-advent' ); ?>">&times;</button>
                        <div class="guideos-advent__modal-body"></div>
                    </div>
                </div>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function get_wrapper_attributes( array $attributes, string $instance_id ): string {
        $class_string = 'guideos-advent';
        if ( ! empty( $attributes['className'] ) ) {
            $class_string .= ' ' . $attributes['className'];
        }

        $args = [
            'class'        => $class_string,
            'data-instance'=> $instance_id,
        ];

        $style = $this->build_style_variables( $attributes );
        if ( $style ) {
            $args['style'] = $style;
        }

        if ( function_exists( 'get_block_wrapper_attributes' ) ) {
            return get_block_wrapper_attributes( $args );
        }

        $attr_string = '';
        foreach ( $args as $key => $value ) {
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }

        return trim( $attr_string );
    }

    private function build_style_variables( array $attributes ): string {
        $map    = [
            'backgroundColor' => '--guideos-advent-bg',
            'accentColor'     => '--guideos-advent-accent',
            'textColor'       => '--guideos-advent-text',
        ];
        $styles = [];

        foreach ( $map as $key => $var ) {
            if ( empty( $attributes[ $key ] ) ) {
                continue;
            }
            $value     = sanitize_text_field( $attributes[ $key ] );
            $styles[]  = sprintf( '%s:%s', $var, $value );
        }

        return implode( ';', $styles );
    }

    private function prepare_doors( array $doors ): array {
        $by_day = [];
        foreach ( $doors as $door ) {
            if ( isset( $door['day'] ) ) {
                $by_day[ (int) $door['day'] ] = $door;
            }
        }

        $prepared = [];
        for ( $day = 1; $day <= 24; $day++ ) {
            $raw        = $by_day[ $day ] ?? [];
            $prepared[] = $this->sanitize_door( array_merge( $this->get_default_door( $day ), $raw ) );
        }

        return $prepared;
    }

    private function sanitize_door( array $door ): array {
        $day           = max( 1, min( 24, (int) ( $door['day'] ?? 1 ) ) );
        $allowed_types = [ 'image', 'download', 'link', 'video' ];
        $type          = $door['type'] ?? 'image';
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'image';
        }

        $link = '';
        if ( ! empty( $door['linkUrl'] ) ) {
            $link = esc_url_raw( $door['linkUrl'] );
        } elseif ( 24 === $day && 'download' === $type ) {
            $link = esc_url_raw( self::ISO_URL );
        }

        return [
            'day'           => $day,
            'title'         => sanitize_text_field( $door['title'] ?? '' ),
            'type'          => $type,
            'description'   => wp_kses_post( $door['description'] ?? '' ),
            'imageUrl'      => ! empty( $door['imageUrl'] ) ? esc_url_raw( $door['imageUrl'] ) : '',
            'imageId'       => isset( $door['imageId'] ) ? (int) $door['imageId'] : 0,
            'downloadLabel' => sanitize_text_field( $door['downloadLabel'] ?? __( 'Download', 'guideos-advent' ) ),
            'linkUrl'       => $link,
            'linkLabel'     => sanitize_text_field( $door['linkLabel'] ?? __( 'Mehr erfahren', 'guideos-advent' ) ),
            'videoUrl'      => ! empty( $door['videoUrl'] ) ? esc_url_raw( $door['videoUrl'] ) : '',
        ];
    }

    private function get_default_door( int $day ): array {
        return [
            'day'           => $day,
            'title'         => sprintf( __( 'Tür %d', 'guideos-advent' ), $day ),
            'type'          => 24 === $day ? 'download' : 'image',
            'description'   => '',
            'imageUrl'      => '',
            'imageId'       => 0,
            'downloadLabel' => 24 === $day ? __( 'GuideOS ISO herunterladen', 'guideos-advent' ) : __( 'Download starten', 'guideos-advent' ),
            'linkUrl'       => 24 === $day ? self::ISO_URL : '',
            'linkLabel'     => __( 'Mehr erfahren', 'guideos-advent' ),
            'videoUrl'      => '',
        ];
    }

    private function cache_instance_payload( string $instance_id, int $post_id, array $doors ): void {
        $indexed = [];
        foreach ( $doors as $door ) {
            $indexed[ $door['day'] ] = $door;
        }

        set_transient(
            self::CACHE_PREFIX . $instance_id,
            [
                'post_id' => $post_id,
                'doors'   => $indexed,
            ],
            self::CACHE_TTL
        );
    }

    private function get_cached_payload( string $instance_id ): array {
        $payload = get_transient( self::CACHE_PREFIX . $instance_id );
        return is_array( $payload ) ? $payload : [];
    }

    private function register_instance_bootstrap( int $post_id, string $instance_id, array $doors ): void {
        $available = $this->get_available_day();

        $this->instances[ $instance_id ] = [
            'postId'       => $post_id,
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( self::AJAX_ACTION ),
            'testMode'     => $this->has_test_cookie(),
            'availableDay' => $available,
            'doors'        => array_map(
                static function ( $door ) {
                    return [
                        'day'   => $door['day'],
                        'title' => $door['title'],
                        'type'  => $door['type'],
                    ];
                },
                $doors
            ),
        ];
    }

    public function print_instance_settings(): void {
        if ( $this->settings_printed || empty( $this->instances ) ) {
            return;
        }

        $data = wp_json_encode( $this->instances );
        if ( ! $data ) {
            return;
        }

        echo '<script type="application/json" id="guideos-advent-data">' . $data . '</script>';

        // Force inline script if view.js is not loaded by WordPress
        $view_js_path = GUIDEOS_ADVENT_PATH . 'build/view.js';
        if ( file_exists( $view_js_path ) ) {
            $view_js_content = file_get_contents( $view_js_path );
            if ( $view_js_content ) {
                echo '<script id="guideos-advent-view-inline">' . $view_js_content . '</script>';
            }
        }

        $this->settings_printed = true;
    }

    private function maybe_enable_test_cookie(): void {
        if ( isset( $_GET['guideos_advent_test'] ) && '1' === $_GET['guideos_advent_test'] ) {
            $token = wp_hash( site_url() . '|' . time() );
            setcookie( self::TEST_COOKIE, $token, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            $_COOKIE[ self::TEST_COOKIE ] = $token;
        }
    }

    private function has_test_cookie(): bool {
        return ! empty( $_COOKIE[ self::TEST_COOKIE ] );
    }

    private function get_available_day(): int {
        $timezone = new \DateTimeZone( 'Europe/Berlin' );
        $now      = new \DateTime( 'now', $timezone );
        $year     = (int) $now->format( 'Y' );
        $month    = (int) $now->format( 'n' );
        $day      = (int) $now->format( 'j' );

        // Before December: no doors available
        if ( $month < 12 ) {
            return 0;
        }

        // After December 24: all doors available
        if ( $month > 12 || ( 12 === $month && $day > 24 ) ) {
            return 24;
        }

        // During December 1-24: return current day
        return $day;
    }

    private function can_open_day( int $day ): bool {
        return $day <= $this->get_available_day();
    }

    public function handle_open_door(): void {
        if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
            wp_send_json_error( new WP_Error( 'invalid_nonce', __( 'Ungültige Anfrage.', 'guideos-advent' ) ), 403 );
        }

        $instance_id = isset( $_POST['instance'] ) ? sanitize_text_field( wp_unslash( $_POST['instance'] ) ) : '';
        $day         = isset( $_POST['day'] ) ? (int) $_POST['day'] : 0;

        if ( empty( $instance_id ) || $day < 1 || $day > 24 ) {
            wp_send_json_error( new WP_Error( 'invalid_request', __( 'Ungültiges Türchen.', 'guideos-advent' ) ), 400 );
        }

        $payload = $this->get_cached_payload( $instance_id );
        if ( empty( $payload ) ) {
            wp_send_json_error( new WP_Error( 'expired', __( 'Kalender nicht gefunden oder abgelaufen. Bitte Seite neu laden.', 'guideos-advent' ) ), 410 );
        }

        $doors = $payload['doors'] ?? [];
        $door  = $doors[ $day ] ?? null;

        if ( ! $door ) {
            wp_send_json_error( new WP_Error( 'missing_door', __( 'Tür konnte nicht gefunden werden.', 'guideos-advent' ) ), 404 );
        }

        $test_mode = $this->has_test_cookie();
        if ( ! $test_mode && ! $this->can_open_day( $day ) ) {
            wp_send_json_error(
                new WP_Error( 'locked', __( 'Dieses Türchen bleibt noch geschlossen. Schau später wieder vorbei!', 'guideos-advent' ) ),
                423
            );
        }

        $door['content'] = $this->render_modal_content( $door );

        wp_send_json_success( [
            'door'         => $door,
            'testMode'     => $test_mode,
            'availableDay' => $this->get_available_day(),
        ] );
    }

    private function render_modal_content( array $door ): string {
        ob_start();

        if ( ! empty( $door['title'] ) ) {
            printf( '<h3 class="guideos-advent-modal__title">%s</h3>', esc_html( $door['title'] ) );
        }

        if ( ! empty( $door['description'] ) ) {
            printf( '<div class="guideos-advent-modal__description">%s</div>', wp_kses_post( wpautop( $door['description'] ) ) );
        }

        switch ( $door['type'] ) {
            case 'video':
                if ( ! empty( $door['videoUrl'] ) ) {
                    $embed = wp_oembed_get( $door['videoUrl'] );
                    if ( ! $embed ) {
                        $embed_url = $this->build_youtube_embed_url( $door['videoUrl'] );
                        if ( $embed_url ) {
                            $embed = sprintf(
                                '<div class="guideos-advent-modal__video"><iframe src="%s" title="YouTube" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>',
                                esc_url( $embed_url )
                            );
                        }
                    }
                    if ( $embed ) {
                        echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    }
                }
                break;
            case 'download':
                if ( ! empty( $door['linkUrl'] ) ) {
                    printf(
                        '<a class="guideos-advent-modal__button" href="%s" download><span>%s</span></a>',
                        esc_url( $door['linkUrl'] ),
                        esc_html( $door['downloadLabel'] )
                    );
                }
                break;
            case 'link':
                if ( ! empty( $door['linkUrl'] ) ) {
                    printf(
                        '<a class="guideos-advent-modal__button is-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                        esc_url( $door['linkUrl'] ),
                        esc_html( $door['linkLabel'] )
                    );
                }
                break;
            case 'image':
            default:
                if ( ! empty( $door['imageUrl'] ) ) {
                    printf(
                        '<figure class="guideos-advent-modal__figure"><img src="%s" alt="%s" loading="lazy" decoding="async" /></figure>',
                        esc_url( $door['imageUrl'] ),
                        esc_attr( $door['title'] ?: sprintf( __( 'Tür %d Motiv', 'guideos-advent' ), $door['day'] ) )
                    );
                }
                break;
        }

        return trim( (string) ob_get_clean() );
    }

    private function build_youtube_embed_url( string $url ): string {
        $pattern = '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#i';
        if ( preg_match( $pattern, $url, $matches ) ) {
            return sprintf( 'https://www.youtube.com/embed/%s?rel=0', $matches[1] );
        }

        return '';
    }
}
