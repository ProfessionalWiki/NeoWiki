<template>
	<cdx-dialog
		:open="state.open"
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
const DIALOG_STATE_KEY = require( './constants.js' ).DIALOG_STATE_KEY;

// @vue/component
module.exports = exports = {
	components: {
		CdxDialog: codex.CdxDialog,
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectEditor: nw.SubjectEditor
	},
	setup: function () {
		const state = vue.inject( DIALOG_STATE_KEY );
		const schemaStore = nw.useSchemaStore();
		const subjectStore = nw.useSubjectStore();

		const label = vue.ref( '' );
		const editorRef = vue.ref( null );
		const loadedSubject = vue.shallowRef( null );
		const loadedSchema = vue.shallowRef( null );

		function reset() {
			loadedSubject.value = null;
			loadedSchema.value = null;
			label.value = '';
		}

		function close() {
			state.open = false;
			state.subjectId = null;
		}

		function loadSubjectAndSchema( subjectIdText ) {
			const subjectId = new nw.SubjectId( subjectIdText );
			subjectStore.getOrFetchSubject( subjectId )
				.then( ( subject ) => {
					loadedSubject.value = subject;
					label.value = subject.getLabel();
					return schemaStore.getOrFetchSchema( subject.getSchemaName() );
				} )
				.then( ( schema ) => {
					loadedSchema.value = schema;
				} )
				.catch( ( err ) => {
					mw.log.error( err );
					mw.notify(
						err instanceof Error ? err.message : String( err ),
						{ type: 'error' }
					);
					close();
				} );
		}

		vue.watch( () => state.subjectId, ( newId ) => {
			if ( newId !== null ) {
				loadSubjectAndSchema( newId );
			} else {
				reset();
			}
		} );

		const statements = vue.computed( () => {
			if ( loadedSchema.value === null || loadedSubject.value === null ) {
				return null;
			}
			return loadedSchema.value.statementsFrom( loadedSubject.value.getStatements() );
		} );

		function onClose() {
			close();
		}

		function onOpenChange( newOpen ) {
			if ( !newOpen ) {
				close();
			}
		}

		function onSave() {
			const trimmed = label.value.trim();
			if ( trimmed === '' || editorRef.value === null || loadedSubject.value === null ) {
				return;
			}
			const newStatements = editorRef.value.getSubjectData();
			const updatedSubject = loadedSubject.value
				.withLabel( trimmed )
				.withStatements( newStatements );

			subjectStore.updateSubject( updatedSubject )
				.then( () => {
					mw.notify( mw.message( 'redherb-edit-main-subject-success' ).text() );
					close();
				} )
				.catch( ( err ) => {
					mw.log.error( err );
					mw.notify(
						err instanceof Error ? err.message : String( err ),
						{ type: 'error' }
					);
				} );
		}

		return {
			state: state,
			label: label,
			editorRef: editorRef,
			statements: statements,
			loadedSchema: loadedSchema,
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
.ext-redherb-edit-main-subject-mount {
	display: contents;
}
</style>
