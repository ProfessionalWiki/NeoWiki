( function () {
	'use strict';

	const Vue = require( 'vue' );
	const codex = require( './codex.js' );
	const nw = require( 'ext.neowiki' );
	const EditMainSubjectDialog = require( './EditMainSubjectDialog.vue' );
	const DIALOG_STATE_KEY = require( './constants.js' ).DIALOG_STATE_KEY;

	const TRIGGER_SELECTOR = '.ext-redherb-edit-main-subject-trigger';

	const dialogState = Vue.reactive( { open: false, subjectId: null } );
	let mounted = false;

	function ensureMounted() {
		if ( mounted ) {
			return;
		}
		const host = document.createElement( 'div' );
		host.className = 'ext-redherb-edit-main-subject-mount';
		document.body.appendChild( host );

		const app = Vue.createMwApp( EditMainSubjectDialog )
			.directive( 'tooltip', codex.CdxTooltip );
		app.use( nw.NeoWikiExtension.getInstance().getPinia() );
		nw.NeoWikiServices.registerServices( app );
		app.provide( DIALOG_STATE_KEY, dialogState );
		app.mount( host );
		mounted = true;
	}

	function resolveMainSubjectId() {
		const pageId = mw.config.get( 'wgArticleId' );
		if ( !pageId ) {
			return Promise.resolve( null );
		}
		return nw.NeoWikiExtension.getInstance().getSubjectRepository().getPageSubjects( pageId )
			.then( ( result ) => {
				const mainSubjectId = result.pageSubjects.getMainSubjectId();
				return mainSubjectId === null ? null : mainSubjectId.text;
			} );
	}

	function openDialog( subjectId ) {
		if ( subjectId === null ) {
			mw.notify(
				mw.message( 'redherb-edit-main-subject-no-main' ).text(),
				{ type: 'warn' }
			);
			return;
		}

		ensureMounted();
		dialogState.subjectId = subjectId;
		dialogState.open = true;
	}

	function handleClick( ev ) {
		const trigger = ev.target.closest( TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		ev.preventDefault();

		resolveMainSubjectId()
			.then( openDialog )
			.catch( ( err ) => {
				mw.log.error( err );
				mw.notify(
					err instanceof Error ? err.message : String( err ),
					{ type: 'error' }
				);
			} );
	}

	queueMicrotask( () => {
		document.body.addEventListener( 'click', handleClick );
	} );
}() );
