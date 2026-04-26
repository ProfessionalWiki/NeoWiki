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
			<subject-lookup
				:selected="selectedSubjectId"
				:target-schema="trimmedSchemaName"
				@update:selected="onSelected"
			></subject-lookup>
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
'use strict';

var vue = require( 'vue' );
var codex = require( './codex.js' );
var nw = require( 'ext.neowiki' );

module.exports = exports = {
	components: {
		CdxField: codex.CdxField,
		CdxTextInput: codex.CdxTextInput,
		SubjectLookup: nw.SubjectLookup,
		Infobox: nw.Infobox
	},
	setup: function () {
		var schemaName = vue.ref( '' );
		var selectedSubjectId = vue.ref( null );
		var loadedSubjectId = vue.ref( null );

		var trimmedSchemaName = vue.computed( function () {
			return schemaName.value.trim();
		} );

		var renderableSubjectId = vue.computed( function () {
			if ( loadedSubjectId.value === null ) {
				return null;
			}
			return new nw.SubjectId( loadedSubjectId.value );
		} );

		function onSelected( id ) {
			selectedSubjectId.value = id;
			if ( id === null ) {
				loadedSubjectId.value = null;
				return;
			}
			nw.NeoWikiExtension.getInstance().getStoreStateLoader()
				.loadSubjectsAndSchemas( new Set( [ id ] ) )
				.then( function () {
					loadedSubjectId.value = id;
				} );
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
