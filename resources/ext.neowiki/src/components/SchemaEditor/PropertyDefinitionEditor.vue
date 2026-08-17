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

		<CdxField
			class="ext-neowiki-property-editor__required ext-neowiki-severity-row"
			:hide-label="true"
		>
			<CdxCheckbox
				:model-value="localProperty.required"
				@update:model-value="updateRequired"
			>
				{{ $i18n( 'neowiki-property-editor-required' ).text() }}
			</CdxCheckbox>
			<SeverityInput
				v-if="localProperty.required"
				:constraint="$i18n( 'neowiki-property-editor-required' ).text()"
				:model-value="localProperty.constraintSeverities?.required"
				@update:model-value="updateRequiredSeverity"
			/>
		</CdxField>

		<component
			:is="componentRegistry.getAttributesEditor( localProperty.type )"
			:property="localProperty"
			@update:property="updatePropertyAttributes"
		/>
		<component
			:is="componentRegistry.getValueEditingComponent( localProperty.type )"
			ref="defaultValueInput"
			v-model="localProperty.default"
			class="property-definition-editor__default"
			:label="$i18n( 'neowiki-property-editor-initial-value' ).text()"
			:property="{ ...localProperty, description: '', required: false }"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName, withConstraintSeverity, withoutSeveritiesOfClearedConstraints } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { CdxCheckbox, CdxField, CdxSelect, CdxTextArea, CdxTextInput, type MenuItemData } from '@wikimedia/codex';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';
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

function updateRequired( required: boolean ): void {
	updatePropertyAttributes( { required } );
}

function updateRequiredSeverity( severity: Severity ): void {
	updatePropertyAttributes( withConstraintSeverity( localProperty.value as PropertyDefinition, 'required', severity ) );
}

// Every Constraint change from this editor and the type-specific attributes editors funnels
// through here, so this is where a cleared Constraint also loses its severity.
function updatePropertyAttributes<T extends PropertyDefinition>( attributes: Partial<T> ): void {
	const updated = {
		...localProperty.value,
		...attributes
	} as PropertyDefinition;

	localProperty.value = {
		...updated,
		...withoutSeveritiesOfClearedConstraints( updated, attributes )
	};
}

const propertyTypeRegistry = NeoWikiServices.getPropertyTypeRegistry();

// Rebuild the property when the type changes so its type-specific fields are
// initialized (e.g. a Select gets an empty options list). Otherwise the editors
// for the new type would receive a property missing the fields they expect.
// The type-specific Constraints go, and their severities with them; required is
// shared by every type, so it keeps its severity along with its value.
function changePropertyType( type: string ): void {
	const requiredSeverity = localProperty.value.constraintSeverities?.required;

	localProperty.value = propertyTypeRegistry.getType( type ).createPropertyDefinitionFromJson(
		{
			name: localProperty.value.name,
			type: type,
			description: localProperty.value.description,
			required: localProperty.value.required,
			default: undefined,
			...( requiredSeverity === undefined ? {} : { constraintSeverities: { required: requiredSeverity } } )
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

const defaultValueInput = ref<ValueInputExposes | null>( null );

export interface PropertyDefinitionEditorExposes {
	unparseableInputMessage(): string | null;
}

/**
 * The message the initial-value field is showing because it holds text it cannot
 * turn into a Value, or null. The default is already dropped from the definition
 * at that point; callers hold the save rather than persist a removal the user
 * cannot see.
 */
function unparseableInputMessage(): string | null {
	return defaultValueInput.value?.unparseableInputMessage?.() ?? null;
}

defineExpose<PropertyDefinitionEditorExposes>( { unparseableInputMessage } );
</script>
