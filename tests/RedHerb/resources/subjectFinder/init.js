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

		var pinia = nw.NeoWikiExtension.getInstance().getPinia();
		var app = Vue.createMwApp( SubjectFinderPanel )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( pinia );
		nw.NeoWikiServices.registerServices( app );
		app.mount( mountPoint );
	}

	queueMicrotask( mount );
}() );
