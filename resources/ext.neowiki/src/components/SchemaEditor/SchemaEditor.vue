<template>
	<div
		class="ext-neowiki-schema-editor"
		:class="{ 'ext-neowiki-schema-editor--has-selected-property': selectedProperty !== undefined }"
	>
		<div class="ext-neowiki-schema-editor__description">
			<CdxField
				:optional="true"
			>
				<template #label>
					{{ $i18n( 'neowiki-schema-editor-description' ).text() }}
				</template>
				<CdxTextArea
					:model-value="currentSchema.getDescription()"
					:placeholder="$i18n( 'neowiki-schema-editor-description-placeholder' ).text()"
					@update:model-value="onDescriptionChanged"
				/>
			</CdxField>
		</div>
		<PropertyList
			ref="propertyList"
			:properties="currentSchema.getPropertyDefinitions()"
			:selected-property-name="selectedPropertyName"
			@property-selected="onPropertySelected"
			@property-created="onPropertyCreated"
			@property-deleted="onPropertyDeleted"
			@property-reordered="onPropertyReordered"
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
import { ComponentPublicInstance, computed, onUpdated, ref, watch } from 'vue';
import { CdxField, CdxTextArea } from '@wikimedia/codex';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import PropertyDefinitionEditor from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { useOverflowDetection } from '@/composables/useOverflowDetection.ts';

const props = defineProps<{
	initialSchema: Schema;
}>();

const emit = defineEmits<{
	overflow: [ hasOverflow: boolean ];
	change: [];
}>();

const currentSchema = ref<Schema>( props.initialSchema );

const firstProperty = [ ...props.initialSchema.getPropertyDefinitions() ][ 0 ];
const selectedPropertyName = ref<string | undefined>( firstProperty?.name.toString() );

const propertyList = ref<ComponentPublicInstance | null>( null );
const propertyDefinitionEditor = ref<ComponentPublicInstance | null>( null );

const { hasOverflow, checkOverflow } = useOverflowDetection( [ propertyList, propertyDefinitionEditor ] );

watch( hasOverflow, ( value ) => {
	emit( 'overflow', value );
} );

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

function onDescriptionChanged( value: string ): void {
	currentSchema.value = currentSchema.value.withDescription( value );
	emit( 'change' );
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

onUpdated( () => {
	checkOverflow();
} );

export interface SchemaEditorExposes {
	getSchema: () => Schema;
}

defineExpose( {
	getSchema: function(): Schema {
		return currentSchema.value as Schema;
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-editor {
	display: grid;

	.ext-neowiki-schema-editor {
		&__description {
			padding: @spacing-100;
			border-block-end: @border-subtle;

			@media ( min-width: @min-width-breakpoint-desktop ) {
				padding: @spacing-150;
			}
		}

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
			grid-template-columns: minmax( 0, 20rem ) auto;
			grid-template-rows: auto minmax( 0, 1fr );

			.ext-neowiki-schema-editor {
				&__description {
					grid-column: 1 / -1;
				}

				&__property-list,
				&__property-editor {
					overflow-y: auto;
				}

				&__property-editor {
					border-inline-start: @border-subtle;
				}
			}
		}
	}
}
</style>
