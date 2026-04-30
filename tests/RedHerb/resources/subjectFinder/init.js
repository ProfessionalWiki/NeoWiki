( function () {
	'use strict';

	var Vue = require( 'vue' );
	var codex = require( './codex.js' );
	var nw = require( 'ext.neowiki' );
	var SubjectFinderPanel = require( './SubjectFinderPanel.vue' );

	function mount() {
		var mountPoint = document.getElementById( 'ext-redherb-subject-finder' );
		if ( mountPoint === null ) {
			return;
		}

		var ext = nw.NeoWikiExtension.getInstance();
		mw.hook( 'neowiki.registration' ).fire(
			new nw.FrontendRegistrar(
				ext.getTypeSpecificComponentRegistry(),
				ext.getPropertyTypeRegistry()
			)
		);

		var pinia = ext.getPinia();
		var app = Vue.createMwApp( SubjectFinderPanel )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( pinia );
		nw.NeoWikiServices.registerServices( app );
		app.mount( mountPoint );
	}

	queueMicrotask( mount );
}() );
