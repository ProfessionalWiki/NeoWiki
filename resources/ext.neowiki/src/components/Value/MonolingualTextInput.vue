<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:status="violationStatus( fieldMessages )"
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
		<div class="ext-neowiki-monolingual-text-input">
			<div
				v-for="( view, index ) in rowViews"
				:key="view.row.id"
				@focusout="onRowFocusOut( index, $event )"
			>
				<div class="ext-neowiki-monolingual-text-input__fields">
					<CdxTextInput
						class="ext-neowiki-monolingual-text-input__text"
						:model-value="view.row.text"
						:status="view.severity ?? 'default'"
						:aria-label="view.textLabel"
						@update:model-value="( text: string ) => onTextInput( index, text )"
					/>
					<LanguagePicker
						class="ext-neowiki-monolingual-text-input__language"
						:model-value="view.row.language"
						:aria-label="view.languageLabel"
						@update:model-value="( tag: string ) => commitLanguage( index, tag )"
					/>
				</div>
				<!-- Can't use CdxField because the message belongs under this row, not the field -->
				<CdxMessage
					v-if="view.severity !== undefined"
					:type="view.severity"
					inline
				>
					{{ view.message }}
				</CdxMessage>
			</div>
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import { CdxField, CdxIcon, CdxMessage, CdxTextInput } from '@wikimedia/codex';
import type { ValidationMessages } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import {
	type MonolingualText,
	type MonolingualTextValue,
	newMonolingualTextValue,
	type Value,
	ValueType
} from '@/domain/Value.ts';
import type { Severity } from '@/domain/Severity.ts';
import { MonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { preferErrorViolation, useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';
import { userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';

/**
 * One editable part.
 *
 * `id` identifies the row for as long as it is on screen, so that dropping a row from the middle
 * moves the ones below it rather than shifting their contents into DOM elements the user may be
 * typing in.
 */
interface Row {
	id: number;
	text: string;
	language: string;
}

interface RowView {
	row: Row;
	textLabel: string;
	languageLabel: string;
	message?: string;
	severity?: Severity;
}

let lastRowId = 0;

const props = withDefaults(
	defineProps<ValueInputProps<MonolingualTextProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const rows = ref<Row[]>( rowsOf( props.modelValue ) );

const { relevant, format, emitClears, firstMessages, fieldLevelMessages } = useServerViolations(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

function newRow(): Row {
	return rowOf( { text: '', language: userLanguageTag() } );
}

function rowOf( part: MonolingualText ): Row {
	return { id: ++lastRowId, text: part.text, language: part.language };
}

function holdsPart( row: Row ): boolean {
	return row.text.trim() !== '';
}

/**
 * The rows to edit a Value with: one per part it holds, plus the trailing empty row that is how a
 * part is added on a multi-valued property. A single-valued property gets no trailing row, so no
 * part can be added, but it still shows every part the Value carries: exceeding `multiple` is a
 * Violation rather than a refusal, so a stored Value can hold several, and a row the editor never
 * shows is a part the next save would silently drop.
 */
function rowsOf( value: Value | undefined ): Row[] {
	const parts = value !== undefined && value.type === ValueType.MonolingualText ? value.parts : [];
	const editable = parts.map( rowOf );

	if ( !props.property.multiple ) {
		return editable.length === 0 ? [ newRow() ] : editable;
	}

	return [ ...editable, newRow() ];
}

function currentValue(): MonolingualTextValue | undefined {
	const value = newMonolingualTextValue( rows.value );

	return value.parts.length === 0 ? undefined : value;
}

function emitValue(): void {
	emit( 'update:modelValue', currentValue() );
}

function onTextInput( index: number, text: string ): void {
	const heldPart = holdsPart( rows.value[ index ] );

	rows.value[ index ].text = text;

	const holdsPartNow = holdsPart( rows.value[ index ] );

	// The trailing empty row is what the next part is typed into, so a new one takes its place the
	// moment it holds a part. A row emptied again is dropped when the user leaves it.
	if ( props.property.multiple && holdsPartNow && index === rows.value.length - 1 ) {
		rows.value.push( newRow() );
	}

	// A part appearing or disappearing shifts every index after it, so the held violations no longer
	// say which part they are about and all of them are dropped.
	if ( holdsPartNow === heldPart ) {
		emitClears( clearScopeOf( index ) );
	} else {
		emitClears( 'all' );
	}

	emitValue();
}

/**
 * Drops a row left without text the moment focus leaves it, meaning both of its fields. A row
 * without text holds no part, so dropping it shifts no part index: there is nothing to emit and no
 * violation to clear.
 */
function onRowFocusOut( index: number, event: FocusEvent ): void {
	if ( focusStaysInRow( event.currentTarget as HTMLElement, event.relatedTarget ) ) {
		return;
	}

	if ( rowStays( index ) ) {
		return;
	}

	rows.value.splice( index, 1 );
}

/**
 * Whether focus moved to another field of the row. Focus landing on the language menu's list, which
 * Chrome makes a tab stop while the menu is open, does not count: Codex closes the menu right after,
 * focus falls to the document, and no further event reaches the row.
 */
function focusStaysInRow( rowElement: HTMLElement, target: EventTarget | null ): boolean {
	return target instanceof Element && rowElement.contains( target ) && target.closest( '.cdx-menu' ) === null;
}

/**
 * Which rows survive focus leaving them: the ones holding a part, the only row, since a field the
 * user cannot type in is no field, and the trailing row of a multi-valued property, which is where
 * the next part is typed.
 */
function rowStays( index: number ): boolean {
	return holdsPart( rows.value[ index ] ) ||
		rows.value.length === 1 ||
		( props.property.multiple && index === rows.value.length - 1 );
}

/**
 * A language the user picked stands as this row's language. A row holding no text is no part of
 * the Value, so changing its language changes nothing the parent could save.
 */
function commitLanguage( index: number, tag: string ): void {
	const row = rows.value[ index ];

	row.language = tag;

	if ( holdsPart( row ) ) {
		emitClears( clearScopeOf( index ) );
		emitValue();
	}
}

function clearScopeOf( rowIndex: number ): number[] {
	const partIndex = partIndexOf( rowIndex );

	return partIndex === null ? [] : [ partIndex ];
}

/**
 * Which part of the emitted Value a row holds, or null when it holds none. `valuePartIndex` counts
 * the parts the server was sent, so a row left blank shifts every row below it away from its own
 * violation unless the two are counted separately. A single-valued property shows its one
 * violation under the field rather than under the row, so no row of one claims a part.
 */
function partIndexOf( rowIndex: number ): number | null {
	if ( !props.property.multiple || !holdsPart( rows.value[ rowIndex ] ) ) {
		return null;
	}

	return rows.value.slice( 0, rowIndex ).filter( holdsPart ).length;
}

const rowViews = computed<RowView[]>( () => {
	const held = relevant();

	return rows.value.map( ( row, index ) => {
		const partIndex = partIndexOf( index );
		const hit = partIndex === null ?
			undefined :
			preferErrorViolation( held.filter( ( violation ) => violation.valuePartIndex === partIndex ) );

		return {
			row: row,
			textLabel: mw.message( 'neowiki-monolingual-text-text-label', props.label, index + 1 ).text(),
			languageLabel: mw.message( 'neowiki-monolingual-text-language-label', props.label, index + 1 ).text(),
			message: hit === undefined ? undefined : format( hit ),
			severity: hit?.severity
		};
	} );
} );

/**
 * A server violation with no part to attach to (`required`, `unique`) is shown under the whole
 * field. On a single-valued property the field is also where the one part's violation goes, there
 * being no row of its own to put it under.
 */
const fieldMessages = computed<ValidationMessages>(
	() => props.property.multiple ? fieldLevelMessages.value : firstMessages.value
);

// Re-seeds the rows when the Value changes elsewhere (a revert, a reload), leaving the ones being
// edited alone when it is this component's own emit coming back.
watch( () => props.modelValue, ( value ) => {
	const incoming = value !== undefined && value.type === ValueType.MonolingualText ? value.parts : [];

	if ( JSON.stringify( incoming ) !== JSON.stringify( newMonolingualTextValue( rows.value ).parts ) ) {
		rows.value = rowsOf( value );
	}
} );

watch( () => props.property.multiple, () => {
	rows.value = rowsOf( newMonolingualTextValue( rows.value ) );
} );

defineExpose<ValueInputExposes>( { getCurrentValue: currentValue } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-monolingual-text-input {
	display: flex;
	flex-direction: column;
	gap: @spacing-25;

	&__fields {
		display: flex;
		gap: @spacing-25;
	}

	// Codex gives every text input a 256px minimum, which two side by side overflow the dialog.
	&__text {
		flex: 1 1 auto;
		min-width: 0;
	}

	&__language {
		flex: 0 0 @size-800;

		.cdx-text-input {
			min-width: 0;
		}
	}
}
</style>
