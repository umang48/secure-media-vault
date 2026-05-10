/**
 * Umang Restricted Media Access – Admin JavaScript
 *
 * @package UmangRestrictedMediaAccess
 * @since   1.0.0
 */

/* global smvAdmin, jQuery */

( function ( $, smv ) {
	'use strict';

	/**
	 * Toggle conditional fields based on the selected protection type.
	 *
	 * @param {jQuery} $select The protection type <select> element.
	 */
	function toggleFields( $select ) {
		var val        = $select.val();
		var $container = $select.closest( '.urma-attachment-fields' );

		$container.find( '.urma-field-roles'    ).toggle( val === 'roles' );
		$container.find( '.urma-field-password' ).toggle( val === 'password' );
		$container.find( '.urma-field-posts'    ).toggle( val === 'posts' );
	}

	/**
	 * Generate a secure URL via AJAX and populate the URL input.
	 *
	 * @param {jQuery} $btn The clicked button.
	 */
	function generateSecureUrl( $btn ) {
		var attachmentId = $btn.data( 'attachment-id' );
		var targetId     = $btn.data( 'target' );
		var $input       = $( '#' + targetId );

		$btn.text( smv.i18n.generating ).prop( 'disabled', true );

		$.post( smv.ajaxUrl, {
			action        : 'urma_get_secure_url',
			nonce         : smv.nonce,
			attachment_id : attachmentId,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$input.val( response.data.url );
			} else {
				$input.val( '' );
				// eslint-disable-next-line no-alert
				alert( response.data.message || smv.i18n.error );
			}
		} )
		.fail( function () {
			// eslint-disable-next-line no-alert
			alert( smv.i18n.error );
		} )
		.always( function () {
			$btn.text( 'Generate' ).prop( 'disabled', false );
		} );
	}

	/**
	 * Copy text from an input to the clipboard.
	 *
	 * @param {jQuery} $btn The clicked button.
	 */
	function copyUrl( $btn ) {
		var targetId = $btn.data( 'target' );
		var $input   = $( '#' + targetId );
		var url      = $input.val();

		if ( ! url ) {
			return;
		}

		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( url ).then( function () {
				showCopied( $btn );
			} );
		} else {
			$input.select();
			document.execCommand( 'copy' );
			showCopied( $btn );
		}
	}

	/**
	 * Briefly show a "Copied!" label on the Copy button.
	 *
	 * @param {jQuery} $btn Button element.
	 */
	function showCopied( $btn ) {
		var original = $btn.text();
		$btn.text( smv.i18n.tokenCopied );
		setTimeout( function () {
			$btn.text( original );
		}, 2000 );
	}

	/**
	 * Revoke all tokens for an attachment via AJAX.
	 *
	 * @param {jQuery} $btn The clicked revoke button.
	 */
	function revokeTokens( $btn ) {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( smv.i18n.confirmRevoke ) ) {
			return;
		}

		var attachmentId = $btn.data( 'attachment-id' );

		$btn.prop( 'disabled', true );

		$.post( smv.ajaxUrl, {
			action        : 'urma_revoke_tokens',
			nonce         : smv.nonce,
			attachment_id : attachmentId,
		} )
		.done( function ( response ) {
			if ( response.success ) {
				// eslint-disable-next-line no-alert
				alert( response.data.message );
			} else {
				// eslint-disable-next-line no-alert
				alert( response.data.message || smv.i18n.error );
			}
		} )
		.fail( function () {
			// eslint-disable-next-line no-alert
			alert( smv.i18n.error );
		} )
		.always( function () {
			$btn.prop( 'disabled', false );
		} );
	}

	// ─────────────────────────────────────────────
	// Document ready
	// ─────────────────────────────────────────────

	$( function () {

		// Initial field visibility.
		$( '.urma-protection-select' ).each( function () {
			toggleFields( $( this ) );
		} );

		// Toggle on change.
		$( document ).on( 'change', '.urma-protection-select', function () {
			toggleFields( $( this ) );
		} );

		// Generate secure URL.
		$( document ).on( 'click', '.urma-generate-url', function () {
			generateSecureUrl( $( this ) );
		} );

		// Copy URL.
		$( document ).on( 'click', '.urma-copy-url', function () {
			copyUrl( $( this ) );
		} );

		// Revoke tokens.
		$( document ).on( 'click', '.urma-revoke-tokens', function () {
			revokeTokens( $( this ) );
		} );

	} );

}( jQuery, smvAdmin || {} ) );
