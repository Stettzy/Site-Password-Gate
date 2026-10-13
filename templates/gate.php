<?php
/**
 * Password gate template.
 *
 * Available variables are prepared and escaped at output by SPGATE_Plugin::render_gate().
 *
 * @package KnockFirstPasswordProtection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<title><?php echo esc_html( $heading . ' - ' . $site_name ); ?></title>
	<?php wp_print_styles( 'knock-first-password-protection' ); ?>
	<?php if ( $site_icon ) : ?>
		<link rel="icon" href="<?php echo esc_url( $site_icon ); ?>" sizes="64x64">
	<?php endif; ?>
</head>
<body class="spg-gate">
	<main class="spg-card">
		<div class="spg-mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" role="img" focusable="false">
				<path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 1 1 4 0v2h-4V7Zm3 9.73V18h-2v-1.27a2 2 0 1 1 2 0Z"/>
			</svg>
		</div>

		<p class="spg-site-name"><?php echo esc_html( $site_name ); ?></p>
		<h1><?php echo esc_html( $heading ); ?></h1>
		<p class="spg-message"><?php echo esc_html( $message ); ?></p>

		<?php if ( $error ) : ?>
			<div class="spg-error" role="alert"><?php echo esc_html( $error ); ?></div>
		<?php endif; ?>

		<form action="<?php echo esc_url( $form_action ); ?>" method="post">
			<input type="hidden" name="spgate_action" value="unlock">
			<input type="hidden" name="spgate_redirect" value="<?php echo esc_url( $redirect_url ); ?>">
			<?php wp_nonce_field( 'spgate_unlock_site', 'spgate_nonce' ); ?>

			<label for="spg-password"><?php esc_html_e( 'Password', 'knock-first-password-protection' ); ?></label>
			<input id="spg-password" name="spgate_password" type="password" required autocomplete="current-password" autofocus>
			<button type="submit"><?php esc_html_e( 'View site', 'knock-first-password-protection' ); ?></button>
		</form>
	</main>
</body>
</html>
