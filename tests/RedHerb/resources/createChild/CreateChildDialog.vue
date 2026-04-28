<template>
	<cdx-dialog
		:open="open"
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
var DIALOG_OPEN_KEY = require( './constants.js' ).DIALOG_OPEN_KEY;

var SCHEMA_NAME = 'Company';

module.exports = exports = {
	components: {
		CdxDialog: codex.CdxDialog,
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectEditor: nw.SubjectEditor
	},
	setup: function () {
		var open = vue.inject( DIALOG_OPEN_KEY );
		var schemaStore = nw.useSchemaStore();
		var subjectStore = nw.useSubjectStore();

		var label = vue.ref( '' );
		var editorRef = vue.ref( null );
		var loadedSchema = vue.shallowRef( null );

		function loadSchema() {
			schemaStore.getOrFetchSchema( SCHEMA_NAME ).then( function ( schema ) {
				loadedSchema.value = schema;
			} ).catch( function ( err ) {
				loadedSchema.value = null;
				mw.log.error( err );
				mw.notify(
					err instanceof Error ? err.message : String( err ),
					{ type: 'error' }
				);
				open.value = false;
			} );
		}

		vue.watch( open, function ( isOpen ) {
			if ( isOpen && loadedSchema.value === null ) {
				loadSchema();
			}
			if ( !isOpen ) {
				label.value = '';
			}
		} );

		var schemaProperties = vue.computed( function () {
			return loadedSchema.value === null
				? null
				: loadedSchema.value.getPropertyDefinitions();
		} );

		var schemaStatements = vue.computed( function () {
			if ( loadedSchema.value === null ) {
				return null;
			}
			var statements = [];
			var defs = loadedSchema.value.getPropertyDefinitions();
			for ( var def of defs ) {
				statements.push(
					new nw.Statement( def.name, def.type, undefined )
				);
			}
			return new nw.StatementList( statements );
		} );

		function onClose() {
			open.value = false;
		}

		function onOpenChange( newOpen ) {
			if ( !newOpen ) {
				open.value = false;
			}
		}

		function onSave() {
			var trimmed = label.value.trim();
			if ( trimmed === '' || editorRef.value === null ) {
				return;
			}
			var pageId = mw.config.get( 'wgArticleId' );
			var statements = editorRef.value.getSubjectData();
			subjectStore.createChildSubject( pageId, trimmed, SCHEMA_NAME, statements )
				.then( function () {
					mw.notify( mw.message( 'redherb-create-child-success' ).text() );
					open.value = false;
				} )
				.catch( function ( err ) {
					mw.log.error( err );
					mw.notify(
						mw.message( 'redherb-create-child-error' ).text(),
						{ type: 'error' }
					);
				} );
		}

		return {
			open: open,
			label: label,
			editorRef: editorRef,
			schemaStatements: schemaStatements,
			schemaProperties: schemaProperties,
			onSave: onSave,
			onClose: onClose,
			onOpenChange: onOpenChange,
			dialogTitle: mw.message( 'redherb-create-child-dialog-title' ).text(),
			labelLabel: mw.message( 'redherb-create-child-dialog-label' ).text(),
			primaryAction: {
				label: mw.message( 'redherb-create-child-dialog-save' ).text(),
				actionType: 'progressive'
			},
			defaultAction: {
				label: mw.message( 'redherb-create-child-dialog-cancel' ).text()
			}
		};
	}
};
</script>

<style lang="less">
.ext-redherb-create-child-mount {
	display: contents;
}
</style>
