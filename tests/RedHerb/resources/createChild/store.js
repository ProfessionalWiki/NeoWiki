( function () {
	'use strict';

	var pinia = require( 'pinia' );

	module.exports = pinia.defineStore( 'redHerbCreateChild', {
		state: function () {
			return { open: false };
		},
		actions: {
			openDialog: function () {
				this.open = true;
			},
			closeDialog: function () {
				this.open = false;
			}
		}
	} );
}() );
