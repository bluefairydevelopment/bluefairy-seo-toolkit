/**
 * BlueFairy SEO Toolkit — Settings page
 *
 * Handles the conflict-notice feature override toggles, which save through
 * the plugin's REST endpoint.
 *
 * The organisation logo media picker lives in the Pro add-on, with the field
 * it drives.
 *
 * @package BlueFairy\SeoToolkit
 */
jQuery( function ( $ ) {
    'use strict';

    // --- Conflict Notice Feature Override Toggles ---

    $( '.bfst-feature-override-toggle' ).on( 'change', function () {
        var $checkbox = $( this );
        var feature   = $checkbox.data( 'feature' );
        var enabled   = $checkbox.is( ':checked' );
        var $status   = $checkbox.closest( 'tr' ).find( '.bfst-toggle-status' );

        $status.text( bfstSettings.savingText );

        fetch(
            bfstSettings.root + 'bluefairy-seo/v1/feature-toggle',
            {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   bfstSettings.nonce
                },
                body: JSON.stringify( { feature: feature, enabled: enabled } )
            }
        )
        .then( function ( r ) { return r.json(); } )
        .then( function ( data ) {
            $status.text( data.success ? bfstSettings.savedText : bfstSettings.errorText );
            setTimeout( function () { $status.text( '' ); }, 2000 );
        } )
        .catch( function () {
            $status.text( bfstSettings.errorText );
        } );
    } );
} );
