<template>
	<div class="ext-redherb-subject-finder">
		<cdx-field>
			<template #label>
				{{ schemaLabel }}
			</template>
			<cdx-text-input
				v-model="schemaName"
				:placeholder="schemaPlaceholder"
			></cdx-text-input>
		</cdx-field>

		<cdx-field v-if="trimmedSchemaName">
			<template #label>
				{{ pickLabel }}
			</template>
			<subject-picker
				:selected="selectedSubjectId"
				:target-schema="trimmedSchemaName"
				@update:selected="onSelected"
			></subject-picker>
		</cdx-field>

		<div
			v-if="renderableSubjectId !== null"
			class="ext-redherb-subject-finder__rendered"
		>
			<infobox
				:subject-id="renderableSubjectId"
				:can-edit-subject="false"
			></infobox>
		</div>
	</div>
</template>

<script>
const vue = require( 'vue' );
const codex = require( './codex.js' );
const nw = require( 'ext.neowiki' );

// @vue/component
module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectPicker: nw.SubjectPicker,
		Infobox: nw.Infobox
	},
	setup: function () {
		const subjectStore = nw.useSubjectStore();
		const schemaName = vue.ref( '' );
		const selectedSubjectId = vue.ref( null );
		const loadedSubjectId = vue.ref( null );

		const trimmedSchemaName = vue.computed( () => schemaName.value.trim() );

		const renderableSubjectId = vue.computed( () => {
			if ( loadedSubjectId.value === null ) {
				return null;
			}
			return new nw.SubjectId( loadedSubjectId.value );
		} );

		function notifyError( err ) {
			mw.log.error( err );
			mw.notify(
				err instanceof Error ? err.message : String( err ),
				{ type: 'error' }
			);
		}

		function onSelected( id ) {
			selectedSubjectId.value = id;
			if ( id === null ) {
				loadedSubjectId.value = null;
				return;
			}
			nw.NeoWikiExtension.getInstance().getStoreStateLoader()
				.loadSubjectsAndSchemas( new Set( [ id ] ) )
				.then( () => {
					// The loader skips a Subject it cannot load — one the viewer may not read
					// among them — rather than rejecting, so the store is what says whether
					// there is anything to render.
					if ( subjectStore.findSubject( new nw.SubjectId( id ) ) === undefined ) {
						notifyError( new Error( mw.message( 'redherb-subject-finder-load-failed' ).text() ) );
						return;
					}
					loadedSubjectId.value = id;
				} )
				.catch( notifyError );
		}

		return {
			schemaName: schemaName,
			selectedSubjectId: selectedSubjectId,
			trimmedSchemaName: trimmedSchemaName,
			renderableSubjectId: renderableSubjectId,
			onSelected: onSelected,
			schemaLabel: mw.message( 'redherb-subject-finder-schema-label' ).text(),
			schemaPlaceholder: mw.message( 'redherb-subject-finder-schema-placeholder' ).text(),
			pickLabel: mw.message( 'redherb-subject-finder-pick-subject' ).text()
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-subject-finder {
	display: flex;
	flex-direction: column;
	gap: @spacing-100;
	padding: @spacing-100;

	&__rendered {
		margin-top: @spacing-100;
	}
}
</style>
