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
				<SubjectPicker
					:selected="value"
					:target-schema="props.property.targetSchema"
					:start-icon="startIcon"
					:status="status"
					:aria-label="ariaLabel"
					:create-subject="createTarget"
					@update:selected="onUpdate"
					@blur="onBlur"
					@focusin="onFocus"
				>
					<template v-if="targetEditingEnabled" #suffix="{ selected }">
						<RelationTargetEditButton
							v-if="selected !== null"
							:target="selected"
							@edit="emit( 'edit-relation-target', $event )"
						/>
					</template>
				</SubjectPicker>
			</template>
		</NeoMultiLookupInput>
		<SubjectPicker
			v-else
			:selected="selectedId"
			:target-schema="props.property.targetSchema"
			:start-icon="startIcon"
			:status="fieldStatus"
			:create-subject="createTarget"
			@update:selected="onSingleSelectionChanged"
			@blur="onSingleBlur"
		>
			<template v-if="targetEditingEnabled" #suffix="{ selected }">
				<RelationTargetEditButton
					v-if="selected !== null"
					:target="selected"
					@edit="emit( 'edit-relation-target', $event )"
				/>
			</template>
		</SubjectPicker>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, computed, toRef, inject } from 'vue';
import { CdxField, CdxIcon, ValidationMessages } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import RelationTargetEditButton from '@/components/Value/RelationTargetEditButton.vue';
import NeoMultiLookupInput from '@/components/common/NeoMultiLookupInput.vue';
import SubjectPicker from '@/components/common/SubjectPicker.vue';
import { RelationTargetCreationKey, RelationTargetEditingKey, ValueInputEmits, ValueInputProps, ValueInputExposes } from '@/components/Value/ValueInputContract';
import type { Subject } from '@/domain/Subject.ts';
import { RelationProperty, RelationType } from '@/domain/propertyTypes/Relation.ts';
import { Value, ValueType, RelationValue, newRelation, relationValuesHaveSameTargets } from '@/domain/Value';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';

const props = withDefaults(
	defineProps<ValueInputProps<RelationProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( RelationType.typeName );

const emit = defineEmits<ValueInputEmits>();

const targetEditingEnabled = inject( RelationTargetEditingKey, false );

// Undefined where the host cannot create, which is what leaves the picker without a create
// option. Bound rather than passed through, so the picker never learns the target Schema's role.
const createRelationTarget = inject( RelationTargetCreationKey, undefined );

const createTarget = computed( (): ( ( label: string | null ) => Promise<Subject | null> ) | undefined => {
	if ( createRelationTarget === undefined ) {
		return undefined;
	}

	return ( label: string | null ): Promise<Subject | null> =>
		createRelationTarget( props.property.targetSchema, label );
} );

const internalValue = ref<RelationValue | undefined>( undefined );
const singleHasUnmatchedText = ref( false );

const { firstMessages, emitClears } = useServerViolations(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

// One aggregate message for the whole property (NeoMultiLookupInput has no per-index slot).
const displayedFieldMessages = computed( (): ValidationMessages => {
	if ( singleHasUnmatchedText.value ) {
		return {};
	}
	return firstMessages.value;
} );

const fieldStatus = computed( (): 'default' | 'error' | 'warning' => {
	if ( props.property.multiple || singleHasUnmatchedText.value ) {
		return 'default';
	}
	return violationStatus( displayedFieldMessages.value );
} );

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
	emitClears( 'all' );
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
	emitClears( 'all' );
}

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return internalValue.value;
	}
} );

</script>
