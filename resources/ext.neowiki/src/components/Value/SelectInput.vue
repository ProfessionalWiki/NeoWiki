<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
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
			v-model:input-chips="chips"
			v-model:selected="selection"
			v-model:input-value="inputValue"
			:menu-items="menuItems"
			:placeholder="selectPlaceholder"
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
import { ref, watch, computed, nextTick } from 'vue';
import { CdxField, CdxIcon, CdxMultiselectLookup, CdxSelect } from '@wikimedia/codex';
import type { ChipInputItem, MenuItemData } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { SelectProperty, SelectType } from '@/domain/propertyTypes/Select.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<SelectProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const validationError = ref<string | null>( null );

const selectPlaceholder = computed( () =>
	mw.message( 'neowiki-select-placeholder' ).text()
);

const singleMenuItems = computed( (): MenuItemData[] =>
	props.property.options.map( ( option ) => ( {
		value: option,
		label: option
	} ) )
);

function partsFromValue( value: Value | undefined ): string[] {
	if ( value && value.type === ValueType.String ) {
		return ( value as StringValue ).parts.filter( ( p ) => p.trim() !== '' );
	}
	return [];
}

const initialParts = partsFromValue( props.modelValue );
const selection = ref<string[]>( [ ...initialParts ] );
const chips = ref<ChipInputItem[]>( initialParts.map( ( part ) => ( { value: part } ) ) );
const inputValue = ref<string | number>( '' );
const menuItems = ref<MenuItemData[]>( [] );

const singleSelectedValue = computed( () =>
	selection.value.length > 0 ? selection.value[ 0 ] : ''
);

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( SelectType.typeName );

function getFilteredOptions(): MenuItemData[] {
	const query = String( inputValue.value ).toLowerCase();
	return props.property.options
		.filter( ( option ) => !selection.value.includes( option ) )
		.filter( ( option ) => query === '' || option.toLowerCase().includes( query ) )
		.map( ( option ) => ( { value: option, label: option } ) );
}

function validate(): void {
	const value = selection.value.length > 0 ?
		newStringValue( selection.value ) :
		undefined;
	const errors = propertyType.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

function onSingleSelect( selected: string ): void {
	selection.value = selected ? [ selected ] : [];
	emit( 'update:modelValue', selection.value.length > 0 ?
		newStringValue( selection.value ) : undefined );
	validate();
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
		validate();
	} );
}, { deep: true } );

// External modelValue change
watch( () => props.modelValue, ( newValue ) => {
	const newParts = partsFromValue( newValue );
	if ( JSON.stringify( newParts ) !== JSON.stringify( selection.value ) ) {
		selection.value = [ ...newParts ];
		chips.value = newParts.map( ( part ) => ( { value: part } ) );
		validate();
	}
} );

watch( () => props.property, () => {
	validate();
} );

validate();

defineExpose<ValueInputExposes>( {
	getCurrentValue(): Value | undefined {
		return selection.value.length > 0 ?
			newStringValue( selection.value ) :
			undefined;
	}
} );
</script>
