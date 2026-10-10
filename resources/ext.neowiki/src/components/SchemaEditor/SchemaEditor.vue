<template>
	<div
		ref="root"
		class="ext-neowiki-schema-editor"
		:style="{ '--ext-neowiki-pane-size': paneSize.cssSize.value }"
	>
		<PropertyList
			:id="propertyListId"
			:properties="currentSchema.getPropertyDefinitions()"
			:selected-property-name="selectedPropertyName"
			@subject-label-selected="onSubjectLabelSelected"
			@property-selected="onPropertySelected"
			@add-property="addProperty"
			@property-deleted="onPropertyDeleted"
			@property-reordered="onPropertyReordered"
		/>
		<PaneDivider
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
		<div class="ext-neowiki-schema-editor__detail">
			<PropertyDefinitionEditor
				v-if="selectedProperty !== undefined"
				ref="propertyDefinitionEditor"
				:key="propertyEditorKey"
				:property="selectedProperty as PropertyDefinition"
				:other-property-names="otherPropertyNames"
				:select-name="selectedPropertyName === createdPropertyName"
				@update:property-definition="onPropertyUpdated"
			/>
			<template v-else>
				<h3 class="ext-neowiki-schema-editor__subject-label-heading">
					{{ $i18n( 'neowiki-schema-editor-subject-label' ).text() }}
				</h3>
				<p class="ext-neowiki-schema-editor__subject-label-help">
					{{ $i18n( 'neowiki-schema-editor-subject-label-help' ).text() }}
				</p>
			</template>
			<CdxButton
				class="ext-neowiki-schema-editor__add-property"
				@click="addProperty"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-schema-editor-new-property' ).text() }}
			</CdxButton>
		</div>
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
import { CdxButton, CdxIcon, useGeneratedId } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import type { SaveBlocker } from '@/components/common/SaveBlocker.ts';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';

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
	// What one of the property editor's fields needs, plus its scrollbar. Its bounds row
	// wraps rather than overflowing, so the pane no longer has to hold the whole row.
	minOtherSize: 336,
	dividerSize: PANE_DIVIDER_SIZE,
	storageKey: 'neowiki-schema-editor-pane-size'
} );

const currentSchema = ref<Schema>( props.initialSchema );

// Undefined while the Label row is selected.
const selectedPropertyName = ref<string | undefined>();

// Gives each selected property an editor of its own. Not the name: renaming the property
// would then rebuild the editor on every keystroke, moving the caret to the end of the
// name and dropping what the editor's fields hold.
const propertyEditorKey = ref( 0 );

function selectProperty( name: string | undefined ): void {
	if ( name !== selectedPropertyName.value ) {
		selectedPropertyName.value = name;
		propertyEditorKey.value++;
	}
}

// The property added last, whose generated name the editor selects for replacement.
const createdPropertyName = ref<string | undefined>();

watch( () => props.initialSchema, ( schema ) => {
	currentSchema.value = schema;
	const firstProperty = [ ...schema.getPropertyDefinitions() ][ 0 ];
	selectProperty( firstProperty?.name.toString() );
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

const otherPropertyNames = computed( (): string[] =>
	Object.keys( currentSchema.value.getPropertyDefinitions().asRecord() )
		.filter( ( name ) => name !== selectedPropertyName.value )
);

function onSubjectLabelSelected(): void {
	selectProperty( undefined );
}

function onPropertySelected( name: PropertyName ): void {
	selectProperty( name.toString() );
}

function addProperty(): void {
	const newProperty = createNewProperty();
	currentSchema.value = currentSchema.value.withAddedPropertyDefinition( newProperty );
	createdPropertyName.value = newProperty.name.toString();
	selectProperty( newProperty.name.toString() );
	emit( 'change' );
}

function createNewProperty(): PropertyDefinition {
	return {
		name: generateUniquePropertyName(),
		type: 'text',
		description: '',
		required: false,
		default: undefined
	} as PropertyDefinition;
}

function generateUniquePropertyName(): PropertyName {
	let counter = 1;

	while ( propertyExists( `New Property ${ counter }` ) ) {
		counter++;
	}

	return new PropertyName( `New Property ${ counter }` );
}

function onPropertyDeleted( name: PropertyName ): void {
	currentSchema.value = currentSchema.value.withRemovedPropertyDefinition( name );

	if ( selectedPropertyName.value === name.toString() ) {
		const properties = [ ...currentSchema.value.getPropertyDefinitions() ];
		selectProperty( properties.length > 0 ?
			properties[ 0 ].name.toString() :
			undefined );
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
	saveBlocker: () => SaveBlocker | null;
}

/**
 * The first reason the save must not proceed, or null. Unparseable text comes first: the
 * user is looking at the field holding it.
 */
const saveBlocker = (): SaveBlocker | null => unparseableInput() ?? incompleteProperty();

/**
 * The property with a field showing text that getSchema() leaves out. Only the
 * selected property has an editor mounted.
 */
const unparseableInput = (): SaveBlocker | null => {
	const message = propertyDefinitionEditor.value?.unparseableInputMessage() ?? null;

	if ( message === null || selectedPropertyName.value === undefined ) {
		return null;
	}

	return { propertyName: selectedPropertyName.value, message };
};

/**
 * The first property definition the wiki would refuse to store, or null. Read from the Schema
 * rather than from the mounted editor: only the selected property has one, and a property the
 * user navigated away from is just as unsaveable. Only relation properties can be incomplete
 * today; letting every Property Type answer for its own definition is #1454.
 */
const incompleteProperty = (): SaveBlocker | null => {
	for ( const property of currentSchema.value.getPropertyDefinitions() ) {
		const message = missingRelationAttribute( property );

		if ( message !== null ) {
			return { propertyName: property.name.toString(), message: mw.message( message ).text() };
		}
	}

	return null;
};

defineExpose<SchemaEditorExposes>( {
	getSchema: function(): Schema {
		const schema = currentSchema.value as Schema;

		return props.description === undefined ? schema : schema.withDescription( props.description );
	},
	saveBlocker
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
		&__detail {
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

		&__subject-label-heading {
			margin: 0;
			padding-block: 0;
			font-size: @font-size-medium;
		}

		&__subject-label-help {
			margin: @spacing-50 0 0;
		}

		&__add-property {
			margin-block-start: @spacing-150;
		}
	}

	.cdx-select-vue {
		display: block; /* Make the select element take the full width of the parent element */
	}

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

			&__detail {
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
			&__detail {
				overflow-y: auto;
			}
		}
	}
}
</style>
