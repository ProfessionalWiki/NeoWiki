<template>
	<div
		ref="root"
		class="ext-neowiki-schema-editor"
		:class="{ 'ext-neowiki-schema-editor--has-selected-property': selectedProperty !== undefined }"
		:style="{ '--ext-neowiki-pane-size': paneSize.cssSize.value }"
	>
		<PropertyList
			:id="propertyListId"
			:properties="currentSchema.getPropertyDefinitions()"
			:selected-property-name="selectedPropertyName"
			@property-selected="onPropertySelected"
			@property-created="onPropertyCreated"
			@property-deleted="onPropertyDeleted"
			@property-reordered="onPropertyReordered"
		/>
		<!-- Rendered on the same condition as the editor beside it: the three-track list
			below appears only with a property selected, and a divider outside that condition
			would leave a track for a column that is not there. -->
		<PaneDivider
			v-if="selectedProperty !== undefined"
			class="ext-neowiki-schema-editor__divider"
			:label="$i18n( 'neowiki-schema-editor-resize-property-list' ).text()"
			:controls="propertyListId"
			:size="paneSize.size.value"
			:min="paneSize.minSize.value"
			:max="paneSize.maxSize.value"
			:disabled="!paneSize.resizable.value"
			@resize="paneSize.resizeTo"
			@commit="paneSize.persist"
		/>
		<PropertyDefinitionEditor
			v-if="selectedProperty !== undefined"
			ref="propertyDefinitionEditor"
			:key="selectedPropertyName"
			:property="selectedProperty as PropertyDefinition"
			@update:property-definition="onPropertyUpdated"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { Schema } from '@/domain/Schema.ts';
import { ComponentPublicInstance, computed, ref, watch } from 'vue';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import PropertyDefinitionEditor, { type PropertyDefinitionEditorExposes } from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import PaneDivider, { PANE_DIVIDER_SIZE } from '@/components/common/PaneDivider.vue';
import { usePaneSize } from '@/composables/usePaneSize.ts';
import { useGeneratedId } from '@wikimedia/codex';
import type { UnparseableInput } from '@/components/common/UnparseableInput.ts';

const props = defineProps<{
	initialSchema: Schema;
	/**
	 * The Schema's description, owned by the host: the editor covers the property
	 * definitions, while creating and editing present the description
	 * differently. Optional, because a caller that does not present it at all
	 * should keep the one the Schema arrived with rather than clear it.
	 */
	description?: string;
}>();

const emit = defineEmits<{
	change: [];
}>();

const root = ref<HTMLElement | null>( null );

// Named for the divider to point at. Generated rather than fixed: a schema editor opens
// inside the subject editor's dialog, so two of these can be on the page at once.
const propertyListId = useGeneratedId( 'ext-neowiki-property-list' );

// One preference across all five dialogs this editor appears in: same two columns, and
// every one of those dialogs is the same width. Its own key, not the subject editor's,
// whose dialog is wider — sharing one would let a nudge here overwrite a choice there.
const paneSize = usePaneSize( root, {
	defaultSize: 320,
	minSize: 192,
	// What the property editor's own fields need: its bounds row lays out side by side and
	// measures 552px, and its scrollbar takes another 15. Below this the labels clip and the
	// pane scrolls sideways, which the old `auto` track could not do.
	minOtherSize: 576,
	dividerSize: PANE_DIVIDER_SIZE,
	storageKey: 'neowiki-schema-editor-pane-size'
} );

const currentSchema = ref<Schema>( props.initialSchema );
const selectedPropertyName = ref<string | undefined>();

watch( () => props.initialSchema, ( schema ) => {
	currentSchema.value = schema;
	const firstProperty = [ ...schema.getPropertyDefinitions() ][ 0 ];
	selectedPropertyName.value = firstProperty?.name.toString();
}, { immediate: true } );

const propertyDefinitionEditor = ref<( ComponentPublicInstance & PropertyDefinitionEditorExposes ) | null>( null );

const selectedProperty = computed( () => {
	if ( selectedPropertyName.value === undefined ) {
		return undefined;
	}

	return currentSchema.value.getPropertyDefinitions().get(
		new PropertyName( selectedPropertyName.value )
	);
} );

function onPropertySelected( name: PropertyName ): void {
	selectedPropertyName.value = name.toString();
}

function onPropertyCreated( newProperty: PropertyDefinition ): void {
	currentSchema.value = currentSchema.value.withAddedPropertyDefinition( newProperty );
	emit( 'change' );
}

function onPropertyDeleted( name: PropertyName ): void {
	currentSchema.value = currentSchema.value.withRemovedPropertyDefinition( name );

	if ( selectedPropertyName.value === name.toString() ) {
		const properties = [ ...currentSchema.value.getPropertyDefinitions() ];
		selectedPropertyName.value = properties.length > 0 ?
			properties[ 0 ].name.toString() :
			undefined;
	}

	emit( 'change' );
}

function onPropertyReordered( names: PropertyName[] ): void {
	currentSchema.value = currentSchema.value.withReorderedPropertyDefinitions( names );
	emit( 'change' );
}

function onPropertyUpdated( updatedProperty: PropertyDefinition ): void {
	currentSchema.value = buildUpdatedSchema( updatedProperty );

	selectedPropertyName.value = updatedProperty.name.toString();
	emit( 'change' );
}

function propertyExists( name: string | undefined ): boolean {
	return name !== undefined &&
		currentSchema.value.getPropertyDefinitions().has( new PropertyName( name ) );
}

function buildUpdatedSchema( updatedProperty: PropertyDefinition ): Schema {
	if ( !propertyExists( selectedPropertyName.value ) ) {
		return currentSchema.value.withAddedPropertyDefinition( updatedProperty );
	}

	return new Schema(
		currentSchema.value.getName(),
		currentSchema.value.getDescription(),
		replacePropertyDefinition( updatedProperty )
	);
}

function replacePropertyDefinition( updatedProperty: PropertyDefinition ): PropertyDefinitionList {
	return new PropertyDefinitionList(
		Array.from( currentSchema.value.getPropertyDefinitions() ).map(
			function( property: PropertyDefinition ) {
				return property.name.toString() === selectedPropertyName.value ? updatedProperty : property;
			}
		)
	);
}

export interface SchemaEditorExposes {
	getSchema: () => Schema;
	unparseableInput: () => UnparseableInput | null;
}

/**
 * The property whose initial-value field is showing text it cannot turn into a
 * Value, so getSchema() would return that property with its default dropped.
 * Only the selected property has an editor mounted.
 */
const unparseableInput = (): UnparseableInput | null => {
	const message = propertyDefinitionEditor.value?.unparseableInputMessage() ?? null;

	if ( message === null || selectedPropertyName.value === undefined ) {
		return null;
	}

	return { propertyName: selectedPropertyName.value, message };
};

defineExpose<SchemaEditorExposes>( {
	getSchema: function(): Schema {
		const schema = currentSchema.value as Schema;

		return props.description === undefined ? schema : schema.withDescription( props.description );
	},
	unparseableInput
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-editor {
	display: grid;

	/* Shown only where the columns are side by side. Safe as a display toggle only
		because the three-track list is behind the same query: hiding a grid item inside
		an explicit track list leaves its track behind and lands the next item in it. */
	.ext-neowiki-schema-editor__divider {
		display: none;
	}

	.ext-neowiki-schema-editor {
		&__property-editor {
			padding: @spacing-100;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				padding: @spacing-150;
			}
		}

		&__property-list {
			@media ( min-width: @min-width-breakpoint-desktop ) {
				padding-block: ( @spacing-150 - @spacing-50 );
				padding-inline: ( @spacing-150 - @spacing-75 ) 0;

				.ext-neowiki-property-list {
					.ext-neowiki-property-list__item {
						border-top-right-radius: 0;
						border-bottom-right-radius: 0;
					}
				}
			}
		}
	}

	.cdx-select-vue {
		display: block; /* Make the select element take the full width of the parent element */
	}

	&--has-selected-property {
		/*
			TODO: Temporary solution for responsive layout.
			Property list and editor should be in multiple steps for mobile.
		*/
		@media ( max-width: @max-width-breakpoint-tablet ) {
			.ext-neowiki-schema-editor {
				&__property-list {
					overflow-x: auto;
					padding: 0;
					display: flex;
				}

				&__property-list .ext-neowiki-property-list {
					display: flex;
					white-space: nowrap;

					.ext-neowiki-property-list__item {
						border-radius: 0;
					}

					.ext-neowiki-property-list__add-item {
						margin-block-start: 0;
					}
				}

				&__property-editor {
					border-block-start: @border-subtle;
				}
			}
		}

		@media ( min-width: @min-width-breakpoint-desktop ) {
			min-height: 0;
			/* The reader's width, bounded in script rather than here: the observed width
				this is divided against is a border box, which `100%` in a track is not.
				`minmax( 0, 1fr )` rather than `auto`, or the editor's min-content would
				claim space back out of a width the reader set. This grid takes no inline
				padding or border of its own, for the same reason. */
			grid-template-columns: var( --ext-neowiki-pane-size, 20rem ) @spacing-75 minmax( 0, 1fr );
			grid-template-rows: minmax( 0, 1fr );

			.ext-neowiki-schema-editor {
				&__divider {
					display: flex;
				}

				&__property-list,
				&__property-editor {
					overflow-y: auto;
				}
			}
		}
	}
}
</style>
