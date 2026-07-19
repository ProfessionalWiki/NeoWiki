<template>
	<div class="ext-neowiki-schema-editor__property-editor">
		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-name' ).text() }}
			</template>
			<CdxTextInput
				ref="nameInput"
				:model-value="localProperty.name.toString()"
				input-type="text"
				@update:model-value="updatePropertyName"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-type' ).text() }}
			</template>
			<CdxSelect
				:selected="localProperty.type"
				:menu-items="typeOptions"
				@update:selected="changePropertyType"
			/>
		</CdxField>

		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-description' ).text() }}
			</template>
			<CdxTextArea
				v-model="localProperty.description"
			/>
		</CdxField>

		<CdxField :hide-label="true">
			<CdxCheckbox v-model="localProperty.required">
				{{ $i18n( 'neowiki-property-editor-required' ).text() }}
			</CdxCheckbox>
		</CdxField>

		<component
			:is="componentRegistry.getAttributesEditor( localProperty.type )"
			:property="localProperty"
			@update:property="updatePropertyAttributes"
		/>
		<component
			:is="componentRegistry.getValueEditingComponent( localProperty.type )"
			v-model="localProperty.default"
			class="property-definition-editor__default"
			:label="$i18n( 'neowiki-property-editor-initial-value' ).text()"
			:property="{ ...localProperty, description: '', required: false }"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { CdxCheckbox, CdxField, CdxSelect, CdxTextArea, CdxTextInput, type MenuItemData } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
	property: PropertyDefinition;
}>();

const emit = defineEmits<{
	'update:property-definition': [ PropertyDefinition ];
}>();

const localProperty = ref<PropertyDefinition>( { ...props.property } );

watch(
	localProperty,
	( newValue ) => {
		emit( 'update:property-definition', newValue as PropertyDefinition );
	},
	{ deep: true }
);

const nameInput = ref<InstanceType<typeof CdxTextInput> | null>( null );

onMounted( () => {
	nextTick( () => {
		if ( nameInput.value !== null ) {
			nameInput.value.focus();
		}
	} );
} );

function updatePropertyName( name: string ): void {
	if ( !PropertyName.isValid( name ) ) {
		console.log( 'TODO: show error' );
		return;
	}

	localProperty.value = {
		...localProperty.value,
		name: new PropertyName( name )
	};
}

function updatePropertyAttributes<T extends PropertyDefinition>( attributes: Partial<T> ): void {
	localProperty.value = {
		...localProperty.value,
		...attributes
	};
}

const propertyTypeRegistry = NeoWikiServices.getPropertyTypeRegistry();

// Rebuild the property when the type changes so its type-specific fields are
// initialized (e.g. a Select gets an empty options list). Otherwise the editors
// for the new type would receive a property missing the fields they expect.
function changePropertyType( type: string ): void {
	localProperty.value = propertyTypeRegistry.getType( type ).createPropertyDefinitionFromJson(
		{
			name: localProperty.value.name,
			type: type,
			description: localProperty.value.description,
			required: localProperty.value.required,
			default: undefined
		} as PropertyDefinition,
		{}
	);
}

const componentRegistry = NeoWikiServices.getComponentRegistry();

// An unregistered type (e.g. owned by a disabled or failed extension) is never
// offered for selection, but when it is the property's current type it is shown
// as a disabled entry so the select does not render empty.
const typeOptions = computed( (): MenuItemData[] => {
	const options: MenuItemData[] = componentRegistry.getLabelsAndIcons().map( ( { value, label, icon } ) => ( {
		value: value,
		label: mw.message( label ).text(),
		icon: icon
	} ) );

	const currentType = localProperty.value.type;
	if ( !options.some( ( option ) => option.value === currentType ) ) {
		options.unshift( {
			value: currentType,
			label: mw.message( componentRegistry.getLabel( currentType ) ).text(),
			icon: componentRegistry.getIcon( currentType ),
			disabled: true
		} );
	}

	return options;
} );
</script>
