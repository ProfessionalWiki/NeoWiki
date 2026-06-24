<template>
	<div
		v-if="subject"
		class="ext-redherb-card"
	>
		<div class="ext-redherb-card__header">
			<span class="ext-redherb-card__caption">
				{{ $i18n( 'redherb-card-caption' ).text() }}
			</span>
			<span class="ext-redherb-card__actions">
				<cdx-button
					v-if="canEditSubject"
					weight="quiet"
					action="progressive"
					@click="openEditor"
				>
					{{ $i18n( 'redherb-card-edit-subject' ).text() }}
				</cdx-button>
				<a :href="schemaUrl">{{ $i18n( 'redherb-card-edit-schema' ).text() }}</a>
				<a
					v-if="layoutName"
					:href="layoutUrl"
				>{{ $i18n( 'redherb-card-edit-layout' ).text() }}</a>
			</span>
		</div>
		<div
			class="ext-redherb-card__label"
			role="heading"
			aria-level="2"
		>
			{{ subject.getLabel() }}
		</div>
		<dl
			v-if="layoutSections.wide.length > 0"
			class="ext-redherb-card__wide"
		>
			<div
				v-for="resolved in layoutSections.wide"
				:key="resolved.propertyDefinition.name.toString()"
				class="ext-redherb-card__wide-field"
			>
				<dt class="ext-redherb-card__term">
					{{ resolved.propertyDefinition.name.toString() }}
				</dt>
				<dd class="ext-redherb-card__value">
					<component
						:is="valueComponent( resolved.propertyDefinition.type )"
						:value="resolved.value"
						:property="resolved.propertyDefinition"
					></component>
				</dd>
			</div>
		</dl>
		<div class="ext-redherb-card__columns">
			<dl
				v-for="( column, index ) in layoutSections.columns"
				:key="index"
				class="ext-redherb-card__grid"
			>
				<div
					v-for="resolved in column"
					:key="resolved.propertyDefinition.name.toString()"
					class="ext-redherb-card__row"
				>
					<dt class="ext-redherb-card__term">
						{{ resolved.propertyDefinition.name.toString() }}
					</dt>
					<dd class="ext-redherb-card__value">
						<component
							:is="valueComponent( resolved.propertyDefinition.type )"
							:value="resolved.value"
							:property="resolved.propertyDefinition"
						></component>
					</dd>
				</div>
			</dl>
		</div>
		<subject-editor-dialog
			v-if="canEditSubject"
			:open="editorOpen"
			:subject="subject"
			:on-save="handleSaveSubject"
			:on-save-schema="handleSaveSchema"
			@update:open="editorOpen = $event"
		></subject-editor-dialog>
	</div>
</template>

<script>
'use strict';

var vue = require( 'vue' );
var codex = require( './codex.js' );
var nw = require( 'ext.neowiki' );

// Example View Type: renders a Subject as a two-column card, loosely modelled on
// the "document control" header BlueSpice shows on controlled documents. It
// demonstrates assembling NeoWiki's building blocks from a separate extension:
// the ViewTypeProps contract ( subjectId, canEditSubject, layoutName ); the
// subject / schema / layout stores; resolveDisplayProperties + the value-display
// component registry to render each value with its property type's component;
// and the shared SubjectEditorDialog for editing, shown only when the contract
// reports canEditSubject. It also reads the Layout's settings — a
// fullWidthProperties list — to decide which properties span the full width
// instead of sitting in a column. NeoWiki populates the stores before mounting.
module.exports = exports = {
	components: {
		CdxButton: codex.CdxButton,
		SubjectEditorDialog: nw.SubjectEditorDialog
	},
	props: {
		subjectId: { type: Object, required: true },
		canEditSubject: { type: Boolean, required: true },
		layoutName: { type: String, default: undefined }
	},
	setup: function ( props ) {
		var subjectStore = nw.useSubjectStore();
		var schemaStore = nw.useSchemaStore();
		var layoutStore = nw.useLayoutStore();
		var componentRegistry = nw.NeoWikiServices.getComponentRegistry();

		var editorOpen = vue.ref( false );

		var subject = vue.computed( function () {
			return subjectStore.getSubject( props.subjectId );
		} );

		var schema = vue.computed( function () {
			return schemaStore.getSchema( subject.value.getSchemaName() );
		} );

		var layout = vue.computed( function () {
			return props.layoutName ? layoutStore.getLayout( props.layoutName ) : undefined;
		} );

		var layoutUrl = vue.computed( function () {
			return props.layoutName ?
				mw.util.getUrl( 'Layout:' + props.layoutName, { action: 'edit' } ) :
				'';
		} );

		var schemaUrl = vue.computed( function () {
			return mw.util.getUrl( 'Schema:' + subject.value.getSchemaName(), { action: 'edit' } );
		} );

		var resolvedProperties = vue.computed( function () {
			if ( !schema.value ) {
				return [];
			}
			return nw.resolveDisplayProperties( schema.value, subject.value, layout.value );
		} );

		// The Layout's settings let the layout author customise this View Type.
		// fullWidthProperties names the properties that should span the full card
		// width; the rest are laid out in two columns.
		var fullWidthNames = vue.computed( function () {
			var names = layout.value ? layout.value.getSettings().fullWidthProperties : undefined;
			return Array.isArray( names ) ? names : [];
		} );

		function isFullWidth( resolved ) {
			return fullWidthNames.value.indexOf( resolved.propertyDefinition.name.toString() ) !== -1;
		}

		// Partition the properties into the full-width fields and two columns of
		// the rest, in a single pass.
		var layoutSections = vue.computed( function () {
			var wide = [];
			var compact = [];
			resolvedProperties.value.forEach( function ( resolved ) {
				if ( isFullWidth( resolved ) ) {
					wide.push( resolved );
				} else {
					compact.push( resolved );
				}
			} );
			var half = Math.ceil( compact.length / 2 );
			return {
				wide: wide,
				columns: [ compact.slice( 0, half ), compact.slice( half ) ]
			};
		} );

		function valueComponent( propertyType ) {
			return componentRegistry.getValueDisplayComponent( propertyType );
		}

		function openEditor() {
			Promise.all( [
				schemaStore.fetchSchema( subject.value.getSchemaName() ),
				subjectStore.fetchSubject( props.subjectId )
			] ).then( function () {
				editorOpen.value = true;
			} ).catch( function ( error ) {
				mw.notify(
					error instanceof Error ? error.message : String( error ),
					{ type: 'error' }
				);
			} );
		}

		function handleSaveSubject( updatedSubject, comment ) {
			return subjectStore.updateSubject( updatedSubject, comment );
		}

		function handleSaveSchema( updatedSchema, comment ) {
			return schemaStore.saveSchema( updatedSchema, comment );
		}

		return {
			subject: subject,
			layoutUrl: layoutUrl,
			schemaUrl: schemaUrl,
			layoutSections: layoutSections,
			editorOpen: editorOpen,
			valueComponent: valueComponent,
			openEditor: openEditor,
			handleSaveSubject: handleSaveSubject,
			handleSaveSchema: handleSaveSchema
		};
	}
};
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-redherb-card {
	margin-bottom: @spacing-100;
	padding: @spacing-100;
	border: @border-base;
	border-radius: @border-radius-base;
	color: @color-base;
	background-color: @background-color-base;
	line-height: @line-height-small;

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: @spacing-50;
	}

	&__caption {
		color: @color-subtle;
		font-size: @font-size-small;
		text-transform: uppercase;
	}

	&__label {
		font-size: @font-size-x-large;
		font-weight: @font-weight-bold;
		margin-bottom: @spacing-75;
	}

	// Full-width fields ( Layout setting fullWidthProperties ): the label sits
	// above the value so long text can use the whole card width.
	&__wide {
		margin: 0 0 @spacing-75;
	}

	&__wide-field {
		padding: @spacing-50 0;
		border-bottom: @border-subtle;
	}

	&__wide-field &__term {
		margin-block-end: @spacing-25;
	}

	&__columns {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-75 @spacing-150;
	}

	// Each column shares the row width and wraps to its own line on narrow
	// viewports. min-width: 0 lets the flex item shrink below its content's
	// intrinsic width instead of forcing the card to overflow horizontally.
	&__grid {
		flex: 1 1 16rem;
		min-width: 0;
		margin: 0;
	}

	// Key/value rows follow the infobox pattern: a fixed-fraction label and a
	// flexible value that wraps long content.
	&__row {
		display: flex;
		align-items: flex-start;
		column-gap: @spacing-100;
		padding: @spacing-50 0;
		border-bottom: @border-subtle;
	}

	&__term {
		flex: 0 0 40%;
		margin: 0;
		font-weight: @font-weight-bold;
		color: @color-emphasized;
	}

	&__value {
		flex: 0 1 60%;
		min-width: 0;
		margin: 0;
		overflow-wrap: anywhere;
		word-break: break-word;
	}
}
</style>
