( function () {
	'use strict';

	var pinia = require( 'pinia' );

	module.exports = pinia.defineStore( 'redHerbEditMainSubject', {
		state: function () {
			return { open: false, subjectId: null };
		},
		actions: {
			openDialog: function ( subjectId ) {
				this.subjectId = subjectId;
				this.open = true;
			},
			closeDialog: function () {
				this.open = false;
				this.subjectId = null;
			}
		}
	} );
}() );
