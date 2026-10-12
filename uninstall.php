<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package SitePasswordGate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$spgate_offset     = 0;
	$spgate_site_count = 0;

	do {
		$spgate_site_ids   = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $spgate_offset,
			)
		);
		$spgate_site_count = count( $spgate_site_ids );

		foreach ( $spgate_site_ids as $spgate_site_id ) {
			switch_to_blog( $spgate_site_id );
			delete_option( 'spgate_options' );
			delete_option( 'spg_options' );
			restore_current_blog();
		}

		$spgate_offset += 100;
	} while ( 100 === $spgate_site_count );
} else {
	delete_option( 'spgate_options' );
	delete_option( 'spg_options' );
}

delete_site_option( 'spgate_options' );
delete_site_option( 'spg_options' );
