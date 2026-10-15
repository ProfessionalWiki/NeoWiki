<template>
	<CdxField
		:status="violationStatus( validationMessages )"
		:messages="validationMessages"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
		</template>
		<CdxMultiselectLookup
			v-if="props.property.multiple"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:input-chips="shownChips"
			:menu-items="menuItems"
			:placeholder="selectPlaceholder"
			@update:input-chips="chips = $event"
			@input="onInput"
		>
			<template #no-results>
				{{ $i18n( 'neowiki-select-no-results' ).text() }}
			</template>
		</CdxMultiselectLookup>
		<CdxSelect
			v-else
			:selected="singleSelectedValue"
			:menu-items="singleMenuItems"
			:default-label="selectPlaceholder"
			@update:selected="onSingleSelect"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, watch, computed, nextTick, toRef } from 'vue';
import { CdxField, CdxIcon, CdxMultiselectLookup, CdxSelect } from '@wikimedia/codex';
import type { ChipInputItem, MenuItemData } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { resolveSelectLabel, SelectOption, SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';

const props = withDefaults(
	defineProps<ValueInputProps<SelectProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

// For Select (single and multi) the field shows one aggregate message — the first
// relevant server violation, keyed by its severity — so an edit clears every held
// violation ('all').
const { firstMessages: validationMessages, emitClears } = useServerViolations(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

const selectPlaceholder = computed( () =>
	mw.message( 'neowiki-select-placeholder' ).text()
);

// An option still waiting for its label in the Schema editor is not offered: a menu shows the id of
// an item without a label.
const offeredOptions = computed( (): SelectOption[] =>
	props.property.options.filter( ( option ) => option.label.trim() !== '' )
);

const singleMenuItems = computed( (): MenuItemData[] =>
	offeredOptions.value.map( ( option ) => ( {
		value: option.id,
		label: option.label
	} ) )
);

function partsFromValue( value: Value | undefined ): string[] {
	if ( value && value.type === ValueType.String ) {
		return ( value as StringValue ).parts.filter( ( p ) => p.trim() !== '' );
	}
	return [];
}

function chipFromId( id: string ): ChipInputItem {
	const label = resolveSelectLabel( props.property, id );
	return {
		value: id,
		label: label ?? mw.message( 'neowiki-select-unknown-option' ).text()
	};
}

const initialParts = partsFromValue( props.modelValue );
const selection = ref<string[]>( [ ...initialParts ] );
const chips = ref<ChipInputItem[]>( initialParts.map( chipFromId ) );

// Labelled afresh, as an option keeps its id when the Schema editor renames it.
const shownChips = computed( (): ChipInputItem[] => chips.value.map( ( chip ) => chipFromId( String( chip.value ) ) ) );
const inputValue = ref<string | number>( '' );
const menuItems = ref<MenuItemData[]>( [] );

const singleSelectedValue = computed( () =>
	selection.value.length > 0 ? selection.value[ 0 ] : ''
);

function getFilteredOptions(): MenuItemData[] {
	const query = String( inputValue.value ).toLowerCase();
	return offeredOptions.value
		.filter( ( option ) => !selection.value.includes( option.id ) )
		.filter( ( option ) => query === '' || option.label.toLowerCase().includes( query ) )
		.map( ( option ) => ( { value: option.id, label: option.label } ) );
}

function onSingleSelect( selected: string ): void {
	selection.value = selected ? [ selected ] : [];
	emit( 'update:modelValue', selection.value.length > 0 ?
		newStringValue( selection.value ) : undefined );
	emitClears( 'all' );
}

function onInput(): void {
	menuItems.value = getFilteredOptions();
}

// The chips array is the authoritative source for multi-select state.
// v-model:input-chips handles both additions (from menu selection) and removals (from chip X).
// v-model:selected is kept in sync by the component internally.
// We only need to watch chips to emit changes to the parent.
let emitPending = false;

watch( chips, () => {
	if ( emitPending ) {
		return;
	}
	emitPending = true;
	nextTick( () => {
		emitPending = false;
		const parts = chips.value.map( ( chip ) => String( chip.value ) );
		if ( JSON.stringify( parts ) !== JSON.stringify( selection.value ) ) {
			selection.value = parts;
		}
		inputValue.value = '';
		menuItems.value = [];
		emit( 'update:modelValue', parts.length > 0 ? newStringValue( parts ) : undefined );
		emitClears( 'all' );
	} );
}, { deep: true } );

// External modelValue change
watch( () => props.modelValue, ( newValue ) => {
	const newParts = partsFromValue( newValue );
	if ( JSON.stringify( newParts ) !== JSON.stringify( selection.value ) ) {
		selection.value = [ ...newParts ];
		chips.value = newParts.map( chipFromId );
	}
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue(): Value | undefined {
		return selection.value.length > 0 ?
			newStringValue( selection.value ) :
			undefined;
	}
} );
</script>
