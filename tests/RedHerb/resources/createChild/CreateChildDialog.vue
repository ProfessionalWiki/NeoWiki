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
			v-if="statements"
			ref="editorRef"
			:statements="statements"
			:schema="loadedSchema"
		></subject-editor>
	</cdx-dialog>
</template>

<script>
const vue = require( 'vue' );
const codex = require( './codex.js' );
const nw = require( 'ext.neowiki' );
const DIALOG_OPEN_KEY = require( './constants.js' ).DIALOG_OPEN_KEY;

const SCHEMA_NAME = 'Company';

// @vue/component
module.exports = exports = {
	components: {
		CdxDialog: codex.CdxDialog,
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectEditor: nw.SubjectEditor
	},
	setup: function () {
		const open = vue.inject( DIALOG_OPEN_KEY );
		const schemaStore = nw.useSchemaStore();
		const subjectStore = nw.useSubjectStore();

		const label = vue.ref( '' );
		const editorRef = vue.ref( null );
		const loadedSchema = vue.shallowRef( null );

		function loadSchema() {
			schemaStore.getOrFetchSchema( SCHEMA_NAME ).then( ( schema ) => {
				loadedSchema.value = schema;
			} ).catch( ( err ) => {
				loadedSchema.value = null;
				mw.log.error( err );
				mw.notify(
					err instanceof Error ? err.message : String( err ),
					{ type: 'error' }
				);
				open.value = false;
			} );
		}

		vue.watch( open, ( isOpen ) => {
			if ( isOpen && loadedSchema.value === null ) {
				loadSchema();
			}
			if ( !isOpen ) {
				label.value = '';
			}
		} );

		const statements = vue.computed( () => {
			if ( loadedSchema.value === null ) {
				return null;
			}
			return loadedSchema.value.blankStatements();
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
			const trimmed = label.value.trim();
			if ( trimmed === '' || editorRef.value === null ) {
				return;
			}
			const pageId = mw.config.get( 'wgArticleId' );
			const subjectStatements = editorRef.value.getSubjectData();
			subjectStore.createChildSubject( pageId, trimmed, SCHEMA_NAME, subjectStatements )
				.then( () => {
					mw.notify( mw.message( 'redherb-create-child-success' ).text() );
					open.value = false;
				} )
				.catch( ( err ) => {
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
			statements: statements,
			loadedSchema: loadedSchema,
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
