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
		<CdxChipInput
			v-if="props.property.multiple"
			:input-chips="selectedChips"
			:separate-input="true"
			@update:input-chips="onChipsUpdate"
		>
			<template #default>
				<CdxSelect
					selected=""
					:menu-items="availableMenuItems"
					:default-label="selectPlaceholder"
					@update:selected="onMultiOptionSelected"
				/>
			</template>
		</CdxChipInput>
		<CdxSelect
			v-else
			:selected="singleSelectedValue"
			:menu-items="menuItems"
			:default-label="selectPlaceholder"
			@update:selected="onSingleSelect"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { CdxChipInput, CdxField, CdxIcon, CdxSelect } from '@wikimedia/codex';
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

const menuItems = computed( (): MenuItemData[] =>
	props.property.options.map( ( option ) => ( {
		value: option,
		label: option
	} ) )
);

const availableMenuItems = computed( (): MenuItemData[] => {
	const selected = selectedParts.value;
	return props.property.options
		.filter( ( option ) => !selected.includes( option ) )
		.map( ( option ) => ( {
			value: option,
			label: option
		} ) );
} );

const selectedParts = ref<string[]>( [] );

const initializeFromValue = ( value: Value | undefined ): void => {
	if ( value && value.type === ValueType.String ) {
		selectedParts.value = ( value as StringValue ).parts.filter( ( p ) => p.trim() !== '' );
	} else {
		selectedParts.value = [];
	}
};

initializeFromValue( props.modelValue );

const singleSelectedValue = computed( () =>
	selectedParts.value.length > 0 ? selectedParts.value[ 0 ] : ''
);

const selectedChips = computed( (): ChipInputItem[] =>
	selectedParts.value.map( ( part ) => ( { value: part } ) )
);

watch( () => props.modelValue, ( newValue ) => {
	initializeFromValue( newValue );
	validate();
} );

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( SelectType.typeName );

function emitValue(): void {
	const value = selectedParts.value.length > 0 ?
		newStringValue( selectedParts.value ) :
		undefined;
	emit( 'update:modelValue', value );
	validate();
}

function validate(): void {
	const value = selectedParts.value.length > 0 ?
		newStringValue( selectedParts.value ) :
		undefined;
	const errors = propertyType.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

function onSingleSelect( selected: string ): void {
	selectedParts.value = selected ? [ selected ] : [];
	emitValue();
}

function onMultiOptionSelected( selected: string ): void {
	if ( selected && !selectedParts.value.includes( selected ) ) {
		selectedParts.value = [ ...selectedParts.value, selected ];
		emitValue();
	}
}

function onChipsUpdate( chips: ChipInputItem[] ): void {
	selectedParts.value = chips.map( ( chip ) => String( chip.value ) );
	emitValue();
}

watch( () => props.property, () => {
	validate();
} );

validate();

defineExpose<ValueInputExposes>( {
	getCurrentValue(): Value | undefined {
		return selectedParts.value.length > 0 ?
			newStringValue( selectedParts.value ) :
			undefined;
	}
} );
</script>
