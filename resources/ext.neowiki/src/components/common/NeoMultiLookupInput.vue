<template>
	<div class="ext-neowiki-multi-lookup-inputs">
		<div
			v-for="( value, index ) in internalValues"
			:key="slotKeys[ index ]"
		>
			<slot
				name="input"
				:value="value"
				:on-update="( newValue: string | null ) => onUpdate( index, newValue )"
				:on-blur="() => onBlur( index )"
				:on-focus="() => onFocus( index )"
				:status="getStatusForIndex( index )"
				:aria-label="`${props.label} item ${index + 1}`"
			/>
			<CdxMessage
				v-if="getMessageTextForIndex( index ) && touchedInputs.has( index )"
				:type="getMessageTypeForIndex( index )"
				inline
			>
				{{ getMessageTextForIndex( index ) }}
			</CdxMessage>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxMessage, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';

type ValidationMessageType = 'error' | 'warning' | 'success' | 'notice';

interface MultiLookupInputProps {
	modelValue?: ( string | null )[];
	label: string;
	messages?: ValidationMessages[];
}

const props = withDefaults(
	defineProps<MultiLookupInputProps>(),
	{
		modelValue: () => [ null ],
		label: '',
		messages: () => []
	}
);

type UpdateModelValueEmit = ( e: 'update:modelValue', value: ( string | null )[] ) => void;

const emit = defineEmits<UpdateModelValueEmit>();

let nextSlotKey = 0;

const internalValues = ref<( string | null )[]>(
	normalizeValues( [ ...props.modelValue ] )
);
const slotKeys = ref<number[]>(
	internalValues.value.map( () => nextSlotKey++ )
);
const touchedInputs = ref<Set<number>>( new Set() );
const focusedIndex = ref<number | null>( null );

watch( internalValues, ( newValues ) => {
	const valuesToEmit = newValues.filter( ( value, index ) =>
		!( index === newValues.length - 1 && value === null && newValues.length > 1 )
	);
	emit( 'update:modelValue', valuesToEmit );

	const normalized = normalizeWithKeys( newValues, slotKeys.value );

	if ( !areArraysEqual( internalValues.value, normalized.values ) ) {
		internalValues.value = normalized.values;
		slotKeys.value = normalized.keys;
	}
}, { deep: true } );

function onUpdate( index: number, newValue: string | null ): void {
	internalValues.value[ index ] = newValue;
}

function onFocus( index: number ): void {
	focusedIndex.value = index;
}

function onBlur( index: number ): void {
	touchedInputs.value.add( index );

	if ( focusedIndex.value === index ) {
		focusedIndex.value = null;

		const normalized = normalizeWithKeys( internalValues.value, slotKeys.value );
		if ( !areArraysEqual( internalValues.value, normalized.values ) ) {
			internalValues.value = normalized.values;
			slotKeys.value = normalized.keys;
		}
	}
}

function getMessageForIndex( index: number ): ValidationMessages | undefined {
	return props.messages?.[ index ];
}

function getStatusForIndex( index: number ): ValidationStatusType | 'default' {
	const messages = getMessageForIndex( index );
	if ( messages === undefined || Object.keys( messages ).length === 0 ) {
		return 'default';
	}
	return Object.keys( messages )[ 0 ] as keyof ValidationMessages;
}

function getMessageTypeForIndex( index: number ): ValidationMessageType | undefined {
	const messages = getMessageForIndex( index );
	if ( messages === undefined ) {
		return undefined;
	}
	return Object.keys( messages )[ 0 ] as ValidationMessageType;
}

function getMessageTextForIndex( index: number ): string | undefined {
	const messages = getMessageForIndex( index );
	if ( messages === undefined ) {
		return undefined;
	}
	const keys = Object.keys( messages ) as ( keyof ValidationMessages )[];
	return keys.length > 0 ? messages[ keys[ 0 ] ] : undefined;
}

function normalizeValues( values: ( string | null )[] ): ( string | null )[] {
	let normalized = [ ...values ];

	normalized = normalized.filter( ( value, index ) =>
		value !== null || index === normalized.length - 1
	);

	if ( normalized.length === 0 || normalized[ normalized.length - 1 ] !== null ) {
		normalized.push( null );
	}

	return normalized;
}

function normalizeWithKeys(
	values: ( string | null )[],
	keys: number[]
): { values: ( string | null )[]; keys: number[] } {
	const keepIndices: number[] = [];

	for ( let i = 0; i < values.length; i++ ) {
		if ( values[ i ] !== null || i === values.length - 1 || i === focusedIndex.value ) {
			keepIndices.push( i );
		}
	}

	const normalizedValues = keepIndices.map( ( i ) => values[ i ] );
	const normalizedKeys = keepIndices.map( ( i ) => keys[ i ] );

	if ( normalizedValues.length === 0 || normalizedValues[ normalizedValues.length - 1 ] !== null ) {
		normalizedValues.push( null );
		normalizedKeys.push( nextSlotKey++ );
	}

	return { values: normalizedValues, keys: normalizedKeys };
}

function areArraysEqual( arr1: ( string | null )[], arr2: ( string | null )[] ): boolean {
	if ( arr1.length !== arr2.length ) {
		return false;
	}
	for ( let i = 0; i < arr1.length; i++ ) {
		if ( arr1[ i ] !== arr2[ i ] ) {
			return false;
		}
	}
	return true;
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-multi-lookup-inputs {
	display: flex;
	flex-direction: column;
	gap: @spacing-25;
}
</style>
