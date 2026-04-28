( function () {
	'use strict';

	var Vue = require( 'vue' );
	var codex = require( './codex.js' );
	var nw = require( 'ext.neowiki' );
	var CreateChildDialog = require( './CreateChildDialog.vue' );
	var DIALOG_OPEN_KEY = require( './constants.js' ).DIALOG_OPEN_KEY;

	var TRIGGER_SELECTOR = '.ext-redherb-create-child-company-trigger';

	var open = Vue.ref( false );
	var mounted = false;

	function ensureMounted() {
		if ( mounted ) {
			return;
		}
		var host = document.createElement( 'div' );
		host.className = 'ext-redherb-create-child-mount';
		document.body.appendChild( host );

		var app = Vue.createMwApp( CreateChildDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( nw.NeoWikiExtension.getInstance().getPinia() );
		nw.NeoWikiServices.registerServices( app );
		app.provide( DIALOG_OPEN_KEY, open );
		app.mount( host );
		mounted = true;
	}

	function handleClick( ev ) {
		var trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		ensureMounted();
		open.value = true;
	}

	queueMicrotask( function () {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
