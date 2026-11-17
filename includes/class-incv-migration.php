<?php
/**
 * One-time data migration from the plugin's previous storage prefix.
 *
 * Before 2.2.0 this plugin stored its data under the 3-letter "mmd" prefix
 * (options such as mmd_discount_rule_sets, product/order meta such as
 * _mmd_gift_enabled). From 2.2.0 everything uses the unique "incv" prefix.
 * Stores that already ran Incentiv keep their rules, gifts, settings and
 * order history: this class copies the old rows to the new names once.
 * The old rows are left untouched so nothing is lost.
 *
 * @package Incentiv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies legacy data to the incv_ names once.
 */
class INCV_Migration {

	/** Migration revision stored in incv_migration_version. */
	const VERSION = 1;

	/** Option holding the migration revision. */
	const VERSION_OPTION = 'incv_migration_version';

	/** Set to 'yes' when legacy data was found and copied. */
	const LEGACY_OPTION = 'incv_legacy_data_found';

	/** Previous storage prefix (without the trailing underscore). */
	const LEGACY_PREFIX = 'mmd';

	/**
	 * Hook the check. Runs early on every request until it has completed once.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_run' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_legacy_shortcodes' ), 20 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_legacy_widgets' ), 20 );
	}

	/**
	 * Keep the shortcodes saved in existing content working on sites that
	 * ran an earlier build. Only registered when legacy data was found.
	 */
	public static function register_legacy_shortcodes() {
		if ( ! self::has_legacy_data() ) {
			return;
		}
		global $shortcode_tags;
		foreach ( array( 'gift_selection', 'rule_banner' ) as $suffix ) {
			$new = 'incv_' . $suffix;
			$old = self::legacy_name( $suffix );
			if ( isset( $shortcode_tags[ $new ] ) && ! shortcode_exists( $old ) ) {
				add_shortcode( $old, $shortcode_tags[ $new ] );
			}
		}
	}

	/**
	 * Register hidden Elementor widgets under the legacy names (see
	 * class-incv-legacy-widgets.php). Only when legacy data was found.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_legacy_widgets( $widgets_manager ) {
		if ( ! self::has_legacy_data() || ! $widgets_manager || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		require_once INCV_PLUGIN_DIR . 'includes/class-incv-legacy-widgets.php';
		foreach ( array( 'INCV_Legacy_Gift_Notice_Widget', 'INCV_Legacy_Gift_Selection_Widget', 'INCV_Legacy_Cart_Gift_Selection_Widget', 'INCV_Legacy_Rule_Banner_Elementor_Widget' ) as $class ) {
			if ( class_exists( $class ) ) {
				$widgets_manager->register( new $class() );
			}
		}
	}

	/**
	 * Run the migration if it has not completed yet.
	 */
	public static function maybe_run() {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		// Avoid two requests running it at the same time.
		if ( get_transient( 'incv_migration_lock' ) ) {
			return;
		}
		set_transient( 'incv_migration_lock', 1, 5 * MINUTE_IN_SECONDS );
		self::run();
		delete_transient( 'incv_migration_lock' );
	}

	/**
	 * Whether legacy data was migrated on this site.
	 *
	 * @return bool
	 */
	public static function has_legacy_data() {
		return 'yes' === get_option( self::LEGACY_OPTION, 'no' );
	}

	/**
	 * Legacy name for a shortcode or Elementor widget, e.g. "gift_selection".
	 *
	 * @param string $suffix Name without prefix.
	 * @return string
	 */
	public static function legacy_name( $suffix ) {
		return self::LEGACY_PREFIX . '_' . $suffix;
	}

	/**
	 * Copy options and meta from the legacy prefix to incv_. Idempotent.
	 */
	public static function run() {
		$found  = self::migrate_options();
		$found += self::migrate_meta();

		if ( $found > 0 ) {
			update_option( self::LEGACY_OPTION, 'yes', true );
			delete_transient( 'incv_discount_cache' );
		}
		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Copy legacy options (not transients) that have no incv_ counterpart yet.
	 *
	 * @return int Number of options copied.
	 */
	private static function migrate_options() {
		global $wpdb;
		$like = $wpdb->esc_like( self::LEGACY_PREFIX . '_' ) . '%';
		// One-time migration: there is no API to list options by prefix.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration; no API lists options by prefix.
			$wpdb->prepare( "SELECT option_name, autoload FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);
		$copied = 0;
		foreach ( (array) $rows as $row ) {
			$new = 'incv_' . substr( $row->option_name, strlen( self::LEGACY_PREFIX ) + 1 );
			if ( false !== get_option( $new, false ) ) {
				continue;
			}
			$autoload = in_array( $row->autoload, array( 'yes', 'on', 'auto-on', 'auto' ), true );
			add_option( $new, get_option( $row->option_name ), '', $autoload ? 'yes' : 'no' );
			$copied++;
		}
		return $copied;
	}

	/**
	 * Copy legacy meta keys (_mmd_* and mmd_*) on posts, users, terms, order
	 * items and HPOS orders to the incv_ names, skipping keys that already
	 * exist for the same object.
	 *
	 * @return int Number of meta rows copied.
	 */
	private static function migrate_meta() {
		global $wpdb;

		$tables = array(
			$wpdb->postmeta => 'post_id',
			$wpdb->usermeta => 'user_id',
			$wpdb->termmeta => 'term_id',
		);
		$order_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$orders_meta    = $wpdb->prefix . 'wc_orders_meta';
		foreach ( array( $order_itemmeta => 'order_item_id', $orders_meta => 'order_id' ) as $table => $id_col ) {
			if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration; checks whether the WooCommerce table exists.
				$tables[ $table ] = $id_col;
			}
		}

		$copied = 0;
		foreach ( array( '_', '' ) as $lead ) {
			$old_prefix = $lead . self::LEGACY_PREFIX . '_';
			$new_prefix = $lead . 'incv_';
			$like       = $wpdb->esc_like( $old_prefix ) . '%';
			$start      = strlen( $old_prefix ) + 1;
			foreach ( $tables as $table => $id_col ) {
				$table  = esc_sql( $table );
				$id_col = esc_sql( $id_col );
				// One-time bulk copy inside the database; table and column names come from $wpdb / fixed strings.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$result = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$table} ({$id_col}, meta_key, meta_value)
						SELECT o.{$id_col}, CONCAT(%s, SUBSTRING(o.meta_key, %d)), o.meta_value
						FROM {$table} o
						WHERE o.meta_key LIKE %s
						AND NOT EXISTS (
							SELECT 1 FROM (SELECT {$id_col}, meta_key FROM {$table} WHERE meta_key LIKE %s) n
							WHERE n.{$id_col} = o.{$id_col} AND n.meta_key = CONCAT(%s, SUBSTRING(o.meta_key, %d))
						)",
						$new_prefix,
						$start,
						$like,
						$wpdb->esc_like( $new_prefix ) . '%',
						$new_prefix,
						$start
					)
				);
				// phpcs:enable
				if ( $result ) {
					$copied += (int) $result;
				}
			}
		}
		if ( $copied ) {
			// Meta caches may hold the pre-migration values; one flush after a one-time copy.
			wp_cache_flush();
		}
		return $copied;
	}
}
