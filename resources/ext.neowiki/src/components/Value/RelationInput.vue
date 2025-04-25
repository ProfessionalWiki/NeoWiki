<template>
	<CdxField
		:required="props.property.required"
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
	>
		<template #label>
			{{ props.label }}
		</template>
		<CdxTextInput
			:model-value="displayValue"
			:aria-label="props.label"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, defineExpose, computed } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { ValueInputEmits, ValueInputProps, ValueInputExposes } from '@/components/Value/ValueInputContract';
import { RelationProperty } from '@neo/domain/propertyTypes/Relation.ts';
import { Value, ValueType, RelationValue, Relation, newRelation } from '@neo/domain/Value';

const props = withDefaults(
	defineProps<ValueInputProps<RelationProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const internalValue = ref<Value | undefined>( props.modelValue );

const validationError = ref<string | null>( null );

watch( () => props.modelValue, ( newValue ) => {
	// Ensure the new value conforms to RelationValue if not undefined
	if ( newValue && newValue.type !== ValueType.Relation ) {
		console.error( 'RelationInput received a non-Relation value:', newValue );
		internalValue.value = undefined; // Or handle appropriately
	} else {
		internalValue.value = newValue;
	}
	validate( internalValue.value as RelationValue | undefined, props.property );
} );

const displayValue = computed( () => {
	if ( !internalValue.value || internalValue.value.type !== ValueType.Relation ) {
		return '';
	}
	// Simple display for now, might need adjustment when editing is implemented
	return ( internalValue.value as RelationValue ).relations.map( ( r ) => r.target.text ).join( ', ' );
} );

const emit = defineEmits<ValueInputEmits>();

function onInput( newValue: string ): void {
	// Basic parsing: comma-separated page titles
	const targets = newValue.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( ( s ) => s !== '' );

	let newRelationValue: RelationValue | undefined;
	if ( targets.length === 0 ) {
		newRelationValue = undefined;
	} else {
		const relations: Relation[] = targets.map( ( target ) => newRelation( undefined, target ) );
		newRelationValue = new RelationValue( relations );
	}

	internalValue.value = newRelationValue;
	emit( 'update:modelValue', newRelationValue );
	validate( newRelationValue, props.property );
}

function validate( _value: RelationValue | undefined, _property: RelationProperty ): void {
	// TODO: Implement actual validation based on property constraints
	validationError.value = null;
}

watch( () => props.property, () => {
	validate( internalValue.value as RelationValue | undefined, props.property );
} );

// Initial validation
validate( internalValue.value as RelationValue | undefined, props.property );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		const current = internalValue.value;
		if ( !current || ( current.type === ValueType.Relation && ( current as RelationValue ).relations.length === 0 ) ) {
			return undefined;
		}
		if ( current.type !== ValueType.Relation ) {
			console.error( 'Internal value is not a Relation value:', current );
			return undefined;
		}
		return current;
	}
} );

</script>
