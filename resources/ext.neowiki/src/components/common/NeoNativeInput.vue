<template>
	<CdxTextInput
		ref="textInput"
		:input-type="inputType"
		:model-value="unparseable ? '' : shown"
		@update:model-value="onInput"
		@keyup="onKeyup"
	/>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxTextInput } from '@wikimedia/codex';

/**
 * A CdxTextInput for the browser's own number, date and date and time fields. Such a field can show
 * text the browser cannot read, such as "-" or a date with one part cleared, while reporting it as
 * empty: this input reports text only while the browser can read the field, and says when it cannot.
 */
const props = defineProps<{
	modelValue: string;
	inputType: 'number' | 'date' | 'datetime-local';
}>();

const emit = defineEmits<{
	'update:modelValue': [ text: string ];
	/** The message for the text the browser cannot read, or null once it can. */
	'unparseable-change': [ message: string | null ];
}>();

const unparseableMessages = {
	number: 'neowiki-field-invalid-number',
	date: 'neowiki-field-invalid-date',
	'datetime-local': 'neowiki-field-invalid-datetime'
} as const;

const textInput = ref<InstanceType<typeof CdxTextInput> | null>( null );

// What the input is bound to. A re-render writes it into the input unless it equals what the input
// holds, which a number field compares as a number: bound to the number it reported, 1 for "1.0", the
// field keeps "1.0", where bound to the text "1" it would be rewritten while it is typed.
const shown = ref<string | number>( props.modelValue );

// While set, the input is bound to the empty value the browser reports for its text, for the same reason.
const unparseable = ref( false );

// A new value replaces the text on screen. An empty one is what the input already holds.
watch( () => props.modelValue, ( text ) => {
	if ( text === String( shown.value ) ) {
		return;
	}

	shown.value = text;

	if ( text !== '' ) {
		setUnparseable( false );
	}
} );

function onInput( value: string | number ): void {
	setUnparseable( nativeInput().validity.badInput );

	if ( unparseable.value ) {
		return;
	}

	shown.value = value;
	emit( 'update:modelValue', String( value ) );
}

function nativeInput(): HTMLInputElement {
	return textInput.value!.$el.querySelector( 'input' );
}

function setUnparseable( value: boolean ): void {
	if ( value === unparseable.value ) {
		return;
	}

	unparseable.value = value;
	emit( 'unparseable-change', value ? mw.message( unparseableMessages[ props.inputType ] ).text() : null );
}

// A date field changes no value, and so fires no input event, when a first part is typed into it empty
// or its last part is cleared. It also shows nothing for a date it cannot display, such as one in the
// year 0, which only a deletion key may clear: Tab's release lands in the field it moves the focus to.
function onKeyup( event: KeyboardEvent ): void {
	const input = nativeInput();
	const emptied = isDeletion( event ) && input.value === '' && shown.value !== '';

	if ( input.validity.badInput !== unparseable.value || emptied ) {
		onInput( input.value );
	}
}

function isDeletion( event: KeyboardEvent ): boolean {
	return event.key === 'Backspace' || event.key === 'Delete';
}
</script>
