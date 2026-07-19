<template>
	<CdxField
		:is-fieldset="true"
		:messages="displayedFieldMessages"
		:status="fieldStatus"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
		</template>
		<NeoMultiLookupInput
			v-if="props.property.multiple"
			:model-value="selectedIds"
			:label="props.label"
			@update:model-value="onSelectionsChanged"
		>
			<template #input="{ value, onUpdate, onBlur, onFocus, status, ariaLabel }">
				<SubjectLookup
					:selected="value"
					:target-schema="props.property.targetSchema"
					:start-icon="startIcon"
					:status="status"
					:aria-label="ariaLabel"
					@update:selected="onUpdate"
					@blur="onBlur"
					@focusin="onFocus"
				/>
			</template>
		</NeoMultiLookupInput>
		<SubjectLookup
			v-else
			:selected="selectedId"
			:target-schema="props.property.targetSchema"
			:start-icon="startIcon"
			:status="fieldStatus"
			@update:selected="onSingleSelectionChanged"
			@blur="onSingleBlur"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { CdxField, CdxIcon, ValidationMessages } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import NeoMultiLookupInput from '@/components/common/NeoMultiLookupInput.vue';
import SubjectLookup from '@/components/common/SubjectLookup.vue';
import { ValueInputEmits, ValueInputProps, ValueInputExposes } from '@/components/Value/ValueInputContract';
import { RelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { Value, ValueType, RelationValue, newRelation, relationValuesHaveSameTargets } from '@/domain/Value';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';

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
const singleHasUnmatchedText = ref( false );

function relevantServerViolations(): readonly SubjectViolation[] {
	const name = props.property.name.toString();
	return ( props.serverViolations ?? [] ).filter( ( v ) => v.propertyName === name );
}

// Field-level server violation (NeoMultiLookupInput has no per-index slot).
function serverFieldMessages(): ValidationMessages {
	const hit = relevantServerViolations()[ 0 ];
	if ( hit ) {
		return {
			error: mw.message( `neowiki-field-${ hit.code }`, ...( hit.args as string[] ) ).text()
		};
	}
	return {};
}

const displayedFieldMessages = computed( (): ValidationMessages => {
	if ( singleHasUnmatchedText.value ) {
		return {};
	}
	return serverFieldMessages();
} );

const fieldStatus = computed( (): 'error' | 'default' => {
	if ( props.property.multiple || singleHasUnmatchedText.value ) {
		return 'default';
	}
	return displayedFieldMessages.value.error !== undefined ? 'error' : 'default';
} );

function emitClearIfServerViolationPresent(): void {
	if ( relevantServerViolations().length > 0 ) {
		emit( 'clear-server-violation', {
			propertyName: props.property.name.toString(),
			valuePartIndex: null
		} );
	}
}

function initializeInternalValue( value: Value | undefined ): void {
	if ( value && value.type === ValueType.Relation ) {
		const relValue = value as RelationValue;
		internalValue.value = relValue.relations.length > 0 ? relValue : undefined;
	} else {
		internalValue.value = undefined;
	}
}

initializeInternalValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInternalValue( newValue );
} );

const selectedId = computed( (): string | null => {
	if ( !internalValue.value || internalValue.value.relations.length === 0 ) {
		return null;
	}
	return internalValue.value.relations[ 0 ].target.text;
} );

const selectedIds = computed( (): ( string | null )[] => {
	if ( !internalValue.value ) {
		return [];
	}
	return internalValue.value.relations.map( ( r ) => r.target.text );
} );

function onSingleSelectionChanged( id: string | null ): void {
	singleHasUnmatchedText.value = false;

	let newRelationValue: RelationValue | undefined;
	if ( id !== null ) {
		newRelationValue = new RelationValue( [ newRelation( undefined, id ) ] );
	} else {
		newRelationValue = undefined;
	}

	if ( !relationValuesHaveSameTargets( internalValue.value, newRelationValue ) ) {
		internalValue.value = newRelationValue;
	}

	emit( 'update:modelValue', newRelationValue );
	emitClearIfServerViolationPresent();
}

function onSingleBlur( hasUnmatchedText: boolean ): void {
	singleHasUnmatchedText.value = hasUnmatchedText;
}

function onSelectionsChanged( ids: ( string | null )[] ): void {
	const nonNullIds = ids.filter( ( id ): id is string => id !== null );

	let newRelationValue: RelationValue | undefined;
	if ( nonNullIds.length > 0 ) {
		const relations = nonNullIds.map( ( id ) => newRelation( undefined, id ) );
		newRelationValue = new RelationValue( relations );
	} else {
		newRelationValue = undefined;
	}

	if ( !relationValuesHaveSameTargets( internalValue.value, newRelationValue ) ) {
		internalValue.value = newRelationValue;
	}

	emit( 'update:modelValue', newRelationValue );
	emitClearIfServerViolationPresent();
}

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return internalValue.value;
	}
} );

</script>
