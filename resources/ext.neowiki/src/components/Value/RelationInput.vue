<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
		</template>
		<NeoMultiTextInput
			:model-value="displayValues"
			:label="props.label"
			:messages="inputMessages"
			:start-icon="startIcon"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, defineExpose, computed } from 'vue';
import { CdxField, ValidationMessages } from '@wikimedia/codex';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { ValueInputEmits, ValueInputProps, ValueInputExposes } from '@/components/Value/ValueInputContract';
import { RelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { Value, ValueType, RelationValue, Relation, newRelation } from '@/domain/Value';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<RelationProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( RelationType.typeName );

const emit = defineEmits<ValueInputEmits>();

const internalValue = ref<RelationValue | undefined>( undefined );
const fieldMessages = ref<ValidationMessages>( {} );
const inputMessages = ref<ValidationMessages[]>( [] );

function initializeInternalValue( value: Value | undefined ): void {
	if ( value && value.type === ValueType.Relation ) {
		const relValue = value as RelationValue;
		internalValue.value = relValue.relations.length > 0 ? relValue : undefined;
	} else {
		if ( value !== undefined ) {
			// console.error( 'RelationInput received non-Relation value:', value );
		}
		internalValue.value = undefined;
	}
}

initializeInternalValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInternalValue( newValue );
	// Use validate after model updates
	const { errors, overallErrorMessage } = validate( displayValues.value );
	inputMessages.value = errors;
	fieldMessages.value = overallErrorMessage ? { error: overallErrorMessage } : {};
} );

// Computed property to feed NeoMultiTextInput
const displayValues = computed( (): string[] => {
	if ( !internalValue.value ) {
		return [];
	}
	return internalValue.value.relations.map( ( r ) => r.target.text );
} );

// Get the RelationType instance from the registry
const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( RelationType.typeName );

function validate( targetStrings: string[] ): {
	errors: ValidationMessages[];
	validRelations: Relation[];
	overallErrorMessage: string | null;
} {
	const perInputErrors: ValidationMessages[] = Array( targetStrings.length ).fill( {} );
	const validRelations: Relation[] = [];

	targetStrings.forEach( ( target, index ) => {
		if ( target.trim() === '' ) {
			return;
		}

		let relation: Relation | null = null;
		try {
			relation = newRelation( undefined, target );
		} catch ( error: unknown ) {
			const message = ( error as Error ).message || mw.message( 'neowiki-error-relation-creation' ).text();
			perInputErrors[ index ] = { error: message };
		}

		if ( relation ) {
			validRelations.push( relation );
		}
	} );

	// Perform ONE final validation on the *complete* set of potentially valid relations
	const finalDomainErrors = propertyType.validate(
		validRelations.length > 0 ? new RelationValue( validRelations ) : undefined,
		props.property
	);

	// Determine overall error message if the final validation fails
	let overallErrorMessage: string | null = null;
	const firstDomainError = finalDomainErrors[ 0 ];
	if ( firstDomainError ) {
		overallErrorMessage = mw.message(
			`neowiki-field-${ firstDomainError.code }`, ...( firstDomainError.args ?? [] )
		).text();
	}

	return {
		errors: perInputErrors,
		validRelations: validRelations,
		overallErrorMessage: overallErrorMessage
	};
}

function onInput( newTargetStrings: string[] ): void {
	const { errors, validRelations, overallErrorMessage } = validate( newTargetStrings );

	inputMessages.value = errors;
	fieldMessages.value = overallErrorMessage ? { error: overallErrorMessage } : {};

	let newRelationValue: RelationValue | undefined;
	if ( validRelations.length > 0 ) {
		newRelationValue = new RelationValue( validRelations );
	} else {
		newRelationValue = undefined;
	}

	// Update internal state WITHOUT triggering the props.modelValue watcher again
	// This assumes initializeInternalValue correctly handles undefined/'empty' RelationValues
	if ( JSON.stringify( internalValue.value ) !== JSON.stringify( newRelationValue ) ) {
		internalValue.value = newRelationValue;
	}

	emit( 'update:modelValue', newRelationValue );
}

watch( () => props.property, () => {
	// Re-validate using the current display values when property changes
	const { errors, overallErrorMessage } = validate( displayValues.value );
	inputMessages.value = errors;
	fieldMessages.value = overallErrorMessage ? { error: overallErrorMessage } : {};
}, { deep: true } );

// Initial validation based on the starting modelValue
// Use validate for initial validation
const initialValidationResult = validate( displayValues.value );
inputMessages.value = initialValidationResult.errors;
fieldMessages.value = initialValidationResult.overallErrorMessage ?
	{ error: initialValidationResult.overallErrorMessage } :
	{};

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return internalValue.value;
	}
} );

</script>
