<?php
/**
 * Elementor widgets under the names saved by earlier Incentiv builds.
 *
 * Elementor stores each widget by its name in the page data. Pages built with
 * an earlier build reference the previous names, so on sites where
 * INCV_Migration found legacy data these hidden subclasses keep those pages
 * rendering. They do not appear in the Elementor panel.
 *
 * @package Incentiv
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'INCV_Gift_Notice_Widget' ) ) {
	/** Legacy name for INCV_Gift_Notice_Widget. */
	class INCV_Legacy_Gift_Notice_Widget extends INCV_Gift_Notice_Widget {
		public function get_name() {
			return INCV_Migration::legacy_name( 'gift_notice' );
		}
		public function show_in_panel() {
			return false;
		}
	}
}

if ( class_exists( 'INCV_Gift_Selection_Widget' ) ) {
	/** Legacy name for INCV_Gift_Selection_Widget. */
	class INCV_Legacy_Gift_Selection_Widget extends INCV_Gift_Selection_Widget {
		public function get_name() {
			return INCV_Migration::legacy_name( 'gift_selection' );
		}
		public function show_in_panel() {
			return false;
		}
	}
}

if ( class_exists( 'INCV_Cart_Gift_Selection_Widget' ) ) {
	/** Legacy name for INCV_Cart_Gift_Selection_Widget. */
	class INCV_Legacy_Cart_Gift_Selection_Widget extends INCV_Cart_Gift_Selection_Widget {
		public function get_name() {
			return INCV_Migration::legacy_name( 'cart_gift_selection' );
		}
		public function show_in_panel() {
			return false;
		}
	}
}

if ( class_exists( 'INCV_Rule_Banner_Elementor_Widget' ) ) {
	/** Legacy name for INCV_Rule_Banner_Elementor_Widget. */
	class INCV_Legacy_Rule_Banner_Elementor_Widget extends INCV_Rule_Banner_Elementor_Widget {
		public function get_name() {
			return INCV_Migration::legacy_name( 'rule_banner' );
		}
		public function show_in_panel() {
			return false;
		}
	}
}
