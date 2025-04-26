<template>
	<CdxField
		:required="props.property.required"
		:status="validationError === null ? 'default' : 'error'"
		:is-fieldset="true"
	>
		<template #label>
			{{ props.label }}
		</template>
		<NeoMultiTextInput
			:model-value="displayValues"
			:label="props.label"
			:required="props.property.required"
			:invalid-values="invalidTargetTexts"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, defineExpose, computed } from 'vue';
import { CdxField } from '@wikimedia/codex';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { ValueInputEmits, ValueInputProps, ValueInputExposes } from '@/components/Value/ValueInputContract';
import { RelationProperty, RelationType } from '@neo/domain/propertyTypes/Relation.ts';
import { Value, ValueType, RelationValue, Relation, newRelation } from '@neo/domain/Value';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<RelationProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const internalValue = ref<RelationValue | undefined>( undefined );
const validationError = ref<string | null>( null );
const invalidTargetTexts = ref<Set<string>>( new Set() );

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
	validate( internalValue.value );
} );

// Computed property to feed NeoMultiTextInput
const displayValues = computed( (): string[] => {
	if ( !internalValue.value ) {
		return [];
	}
	return internalValue.value.relations.map( ( r ) => r.target.text );
} );

function onInput( newTargetStrings: string[] ): void {
	const targets = newTargetStrings.filter( ( s ) => s.trim() !== '' );
	const creationInvalidTargets = new Set<string>();
	const validRelations: Relation[] = [];

	for ( const target of targets ) {
		try {
			const relation = newRelation( undefined, target );
			validRelations.push( relation );
		} catch ( _error ) {
			creationInvalidTargets.add( target );
		}
	}

	let newRelationValue: RelationValue | undefined;
	if ( validRelations.length > 0 ) {
		newRelationValue = new RelationValue( validRelations );
	} else {
		newRelationValue = undefined;
	}

	internalValue.value = newRelationValue;
	emit( 'update:modelValue', newRelationValue );
	validate( newRelationValue, creationInvalidTargets );
}

// Get the RelationType instance from the registry
const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( RelationType.typeName );

function validate(
	value: RelationValue | undefined,
	creationInvalidTargets: Set<string> = new Set()
): void {
	const domainErrors = propertyType.validate( value, props.property );

	let firstDomainErrorMessage: string | null = null;
	const domainInvalidTargets = new Set<string>();

	for ( let i = 0; i < domainErrors.length; i++ ) {
		const error = domainErrors[ i ];
		if ( i === 0 ) {
			// Store message from the first domain error for CdxField status
			firstDomainErrorMessage = mw.message(
				`neowiki-field-${ error.code }`, ...( error.args ?? [] )
			).text();
		}
		if ( error.code === 'invalid-subject-id' && error.args && error.args.length > 0 ) {
			domainInvalidTargets.add( String( error.args[ 0 ] ) );
		}
	}

	// Merge creation and domain invalid targets
	const allInvalidTargets = new Set( [
		...creationInvalidTargets,
		...domainInvalidTargets
	] );
	invalidTargetTexts.value = allInvalidTargets;

	// Set overall field status based ONLY on domain errors
	validationError.value = firstDomainErrorMessage;
}

watch( () => props.property, () => {
	validate( internalValue.value );
}, { deep: true } );

// Initial validation
validate( internalValue.value );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return internalValue.value;
	}
} );

</script>
