( function () {
	'use strict';

	const Vue = require( 'vue' );
	const codex = require( './codex.js' );
	const nw = require( 'ext.neowiki' );
	const SubjectFinderPanel = require( './SubjectFinderPanel.vue' );

	function mount() {
		const mountPoint = document.getElementById( 'ext-redherb-subject-finder' );
		if ( mountPoint === null ) {
			return;
		}

		const pinia = nw.NeoWikiExtension.getInstance().getPinia();
		const app = Vue.createMwApp( SubjectFinderPanel )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( pinia );
		nw.NeoWikiServices.registerServices( app );
		app.mount( mountPoint );
	}

	queueMicrotask( mount );
}() );
