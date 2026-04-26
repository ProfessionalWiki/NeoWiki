<template>
	<cdx-dialog
		:open="store.open"
		:title="dialogTitle"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onSave"
		@default="onClose"
		@update:open="onOpenChange"
	>
		<cdx-field>
			<template #label>
				{{ labelLabel }}
			</template>
			<cdx-text-input v-model="label"></cdx-text-input>
		</cdx-field>

		<subject-editor
			v-if="schemaStatements && schemaProperties"
			ref="editorRef"
			:schema-statements="schemaStatements"
			:schema-properties="schemaProperties"
		></subject-editor>
	</cdx-dialog>
</template>

<script>
'use strict';

var vue = require( 'vue' );
var codex = require( './codex.js' );
var nw = require( 'ext.neowiki' );
var useEditMainSubjectStore = require( './store.js' );

module.exports = exports = {
	components: {
		CdxDialog: codex.CdxDialog,
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectEditor: nw.SubjectEditor
	},
	setup: function () {
		var store = useEditMainSubjectStore();
		var schemaStore = nw.useSchemaStore();
		var subjectStore = nw.useSubjectStore();

		var label = vue.ref( '' );
		var editorRef = vue.ref( null );
		var loadedSubject = vue.shallowRef( null );
		var loadedSchema = vue.shallowRef( null );

		function loadSubjectAndSchema( subjectIdText ) {
			var subjectId = new nw.SubjectId( subjectIdText );
			subjectStore.getOrFetchSubject( subjectId )
				.then( function ( subject ) {
					loadedSubject.value = subject;
					label.value = subject.getLabel();
					return schemaStore.getOrFetchSchema( subject.getSchemaName() );
				} )
				.then( function ( schema ) {
					loadedSchema.value = schema;
				} )
				.catch( function ( err ) {
					mw.log.error( err );
					mw.notify(
						err instanceof Error ? err.message : String( err ),
						{ type: 'error' }
					);
					store.closeDialog();
				} );
		}

		vue.watch( function () {
			return store.subjectId;
		}, function ( newId ) {
			if ( newId !== null ) {
				loadSubjectAndSchema( newId );
			} else {
				loadedSubject.value = null;
				loadedSchema.value = null;
				label.value = '';
			}
		} );

		var schemaProperties = vue.computed( function () {
			return loadedSchema.value === null
				? null
				: loadedSchema.value.getPropertyDefinitions();
		} );

		var schemaStatements = vue.computed( function () {
			if ( loadedSchema.value === null || loadedSubject.value === null ) {
				return null;
			}
			var existingStatements = loadedSubject.value.getStatements();
			var statements = [];
			var defs = loadedSchema.value.getPropertyDefinitions();
			for ( var def of defs ) {
				if ( existingStatements.has( def.name ) ) {
					statements.push( existingStatements.get( def.name ) );
				} else {
					statements.push( new nw.Statement( def.name, def.type, undefined ) );
				}
			}
			return new nw.StatementList( statements );
		} );

		function onClose() {
			store.closeDialog();
		}

		function onOpenChange( newOpen ) {
			if ( !newOpen ) {
				store.closeDialog();
			}
		}

		function onSave() {
			var trimmed = label.value.trim();
			if ( trimmed === '' || editorRef.value === null || loadedSubject.value === null ) {
				return;
			}
			var newStatements = editorRef.value.getSubjectData();
			var updatedSubject = loadedSubject.value
				.withLabel( trimmed )
				.withStatements( newStatements );

			subjectStore.updateSubject( updatedSubject )
				.then( function () {
					mw.notify( mw.message( 'redherb-edit-main-subject-success' ).text() );
					store.closeDialog();
				} )
				.catch( function ( err ) {
					mw.log.error( err );
					mw.notify(
						err instanceof Error ? err.message : String( err ),
						{ type: 'error' }
					);
				} );
		}

		return {
			store: store,
			label: label,
			editorRef: editorRef,
			schemaStatements: schemaStatements,
			schemaProperties: schemaProperties,
			onSave: onSave,
			onClose: onClose,
			onOpenChange: onOpenChange,
			dialogTitle: mw.message( 'redherb-edit-main-subject-dialog-title' ).text(),
			labelLabel: mw.message( 'redherb-edit-main-subject-dialog-label' ).text(),
			primaryAction: {
				label: mw.message( 'redherb-edit-main-subject-dialog-save' ).text(),
				actionType: 'progressive'
			},
			defaultAction: {
				label: mw.message( 'redherb-edit-main-subject-dialog-cancel' ).text()
			}
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-edit-main-subject-mount {
	display: contents;
}
</style>
