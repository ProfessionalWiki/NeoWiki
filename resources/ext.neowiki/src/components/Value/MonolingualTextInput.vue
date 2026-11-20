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
				class="ext-neowiki-monolingual-text-input__row"
				@focusout="onRowFocusOut( index, $event )"
			>
				<div class="ext-neowiki-monolingual-text-input__field">
					<CdxTextInput
						class="ext-neowiki-monolingual-text-input__text"
						:model-value="view.row.text"
						:status="view.severity ?? 'default'"
						:aria-label="view.textLabel"
						:style="{ '--ext-neowiki-language-tag-length': tagLength( view.language ) }"
						@update:model-value="( text: string ) => onTextInput( index, text )"
					/>
					<LanguageInput
						class="ext-neowiki-monolingual-text-input__language"
						:model-value="view.language"
						:label="view.languageLabel"
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
import type { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { MonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import LanguageInput from '@/components/common/LanguageInput.vue';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { preferErrorViolation, useServerViolations, violationStatus } from '@/composables/useServerViolations.ts';
import { newPartLanguageTag, unusedLanguageTag } from '@/presentation/languages.ts';

/**
 * One editable part.
 *
 * `id` identifies the row for as long as it is on screen, so that dropping a row from the middle
 * moves the ones below it rather than shifting their contents into DOM elements the user may be
 * typing in.
 *
 * `language` is null on a new row, which follows the value until it holds text or the user picks
 * its language, showing the language the next part would start in. Where each language takes one
 * part, the value can already have every language a part could start in: the row then shows none,
 * and text typed into it waits for the user to pick one.
 */
interface Row {
	id: number;
	text: string;
	language: string | null;
}

interface RowView {
	row: Row;
	language: string | null;
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
	return { id: ++lastRowId, text: '', language: null };
}

function rowOf( part: MonolingualText ): Row {
	return { id: ++lastRowId, text: part.text, language: part.language };
}

function holdsText( row: Row ): boolean {
	return row.text.trim() !== '';
}

function holdsPart( row: Row ): row is Row & { language: string } {
	return holdsText( row ) && row.language !== null;
}

function lacksLanguage( row: Row ): boolean {
	return holdsText( row ) && row.language === null;
}

function languageOf( row: Row ): string | null {
	if ( row.language !== null || holdsText( row ) ) {
		return row.language;
	}

	return newPartLanguage();
}

function newPartLanguage(): string | null {
	const partLanguages = rows.value.filter( holdsPart ).map( ( row ) => row.language );

	if ( props.property.uniqueLanguages ) {
		return unusedLanguageTag( partLanguages ) ?? null;
	}

	return newPartLanguageTag( partLanguages );
}

function rowsOf( value: Value | undefined ): Row[] {
	return withNewRow( partsOf( value ).map( rowOf ) );
}

/**
 * The rows to edit with: the ones holding text, plus the trailing empty row that is how a part is
 * added on a multi-valued property. A single-valued property gets no trailing row, so no part can be
 * added, but it still shows every part the Value carries: exceeding `multiple` is a Violation rather
 * than a refusal, so a stored Value can hold several, and a row the editor never shows is a part the
 * next save would silently drop.
 */
function withNewRow( editable: Row[] ): Row[] {
	if ( !props.property.multiple ) {
		return editable.length === 0 ? [ newRow() ] : editable;
	}

	return [ ...editable, newRow() ];
}

function partsOf( value: Value | undefined ): MonolingualText[] {
	return value !== undefined && value.type === ValueType.MonolingualText ? value.parts : [];
}

function currentValue(): MonolingualTextValue | undefined {
	const value = newMonolingualTextValue(
		rows.value.filter( holdsPart ).map( ( row ) => ( { text: row.text, language: row.language } ) )
	);

	return value.parts.length === 0 ? undefined : value;
}

function emitValue(): void {
	emit( 'update:modelValue', currentValue() );
}

function unparseableInputMessage(): string | null {
	return rows.value.some( lacksLanguage ) ? languageMissingText() : null;
}

function languageMissingText(): string {
	return mw.message( 'neowiki-monolingual-text-language-missing' ).text();
}

function onTextInput( index: number, text: string ): void {
	const row = rows.value[ index ];
	const heldText = holdsText( row );
	const heldPart = holdsPart( row );

	row.text = text;

	const holdsTextNow = holdsText( row );

	// A part's language is its own: following the value it is now part of would move it, and the
	// next new row would show the same language.
	if ( holdsTextNow && !heldText && row.language === null ) {
		row.language = newPartLanguage();
	}

	// The trailing empty row is what the next part is typed into, so a new one takes its place the
	// moment it holds text. A row emptied again is dropped when the user leaves it.
	if ( props.property.multiple && holdsTextNow && index === rows.value.length - 1 ) {
		rows.value.push( newRow() );
	}

	// A part appearing or disappearing shifts every index after it, so the held violations no longer
	// say which part they are about and all of them are dropped.
	if ( holdsPart( row ) === heldPart ) {
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

	if ( userIsAway( event ) ) {
		return;
	}

	if ( rowStays( index ) ) {
		return;
	}

	rows.value.splice( index, 1 );
}

/**
 * Whether focus moved to another part of the row: its text field, its language list or link, or the
 * language search they open.
 */
function focusStaysInRow( rowElement: HTMLElement, target: EventTarget | null ): boolean {
	return target instanceof Node && rowElement.contains( target );
}

/**
 * Whether focus left for another tab or application rather than for somewhere on the page. The row
 * is kept for the user's return: they may be away copying the text it is waiting for, and dropping it
 * would slide the next row, with its own language, into its place.
 */
function userIsAway( event: FocusEvent ): boolean {
	return event.relatedTarget === null && !document.hasFocus();
}

/**
 * Which rows survive focus leaving them: the ones holding text, the only row, since a field the
 * user cannot type in is no field, and the trailing row of a multi-valued property, which is where
 * the next part is typed.
 */
function rowStays( index: number ): boolean {
	return holdsText( rows.value[ index ] ) ||
		rows.value.length === 1 ||
		( props.property.multiple && index === rows.value.length - 1 );
}

/**
 * A language the user picked stands as this row's language. A row holding no text is no part of
 * the Value, so changing its language changes nothing the parent could save. Where each language
 * takes one value, the change can settle or raise a violation on another row, so all are dropped.
 */
function commitLanguage( index: number, tag: string ): void {
	const row = rows.value[ index ];

	row.language = tag;

	if ( holdsText( row ) ) {
		emitClears( props.property.uniqueLanguages ? 'all' : clearScopeOf( index ) );
		emitValue();
	}
}

function clearScopeOf( rowIndex: number ): number[] {
	const partIndex = partIndexOf( rowIndex );

	return partIndex === null ? [] : [ partIndex ];
}

/**
 * Which part of the emitted Value a row holds, or null when it holds none. `valuePartIndex` counts
 * the parts the server was sent, so a row left blank or without a language shifts every row below
 * it away from its own violation unless the two are counted separately. A single-valued property
 * shows its one violation under the field rather than under the row, so no row of one claims a part.
 */
function partIndexOf( rowIndex: number ): number | null {
	if ( !props.property.multiple || !holdsPart( rows.value[ rowIndex ] ) ) {
		return null;
	}

	return rows.value.slice( 0, rowIndex ).filter( holdsPart ).length;
}

const rowViews = computed<RowView[]>( () => {
	const held = relevant();

	return rows.value.map( ( row, index ) => ( {
		row: row,
		language: languageOf( row ),
		textLabel: mw.message( 'neowiki-monolingual-text-text-label', props.label, index + 1 ).text(),
		languageLabel: mw.message( 'neowiki-monolingual-text-language-label', props.label, index + 1 ).text(),
		...rowMessage( row, index, held )
	} ) );
} );

function rowMessage( row: Row, index: number, held: readonly SubjectViolation[] ): Pick<RowView, 'message' | 'severity'> {
	if ( lacksLanguage( row ) ) {
		return { message: languageMissingText(), severity: 'error' };
	}

	const partIndex = partIndexOf( index );
	const hit = partIndex === null ?
		undefined :
		preferErrorViolation( held.filter( ( violation ) => violation.valuePartIndex === partIndex ) );

	return { message: hit === undefined ? undefined : format( hit ), severity: hit?.severity };
}

/**
 * How many characters of room the language list takes at the end of the text field. The icon it
 * shows for no language takes about as much as a two-letter tag.
 */
function tagLength( language: string | null ): number {
	return language?.length ?? 2;
}

/**
 * A server violation with no part to attach to, such as `required`, is shown under the whole
 * field. On a single-valued property the field is also where the one part's violation goes, there
 * being no row of its own to put it under.
 */
const fieldMessages = computed<ValidationMessages>(
	() => props.property.multiple ? fieldLevelMessages.value : firstMessages.value
);

// Re-seeds the rows when the Value changes elsewhere (a revert, a reload), leaving the ones being
// edited alone when it is this component's own emit coming back.
watch( () => props.modelValue, ( value ) => {
	if ( JSON.stringify( partsOf( value ) ) !== JSON.stringify( partsOf( currentValue() ) ) ) {
		rows.value = rowsOf( value );
	}
} );

watch( () => props.property.multiple, () => {
	rows.value = withNewRow( rows.value.filter( holdsText ) );
} );

defineExpose<ValueInputExposes>( { getCurrentValue: currentValue, unparseableInputMessage } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-monolingual-text-input {
	display: flex;
	flex-direction: column;
	gap: @spacing-25;

	/* One cell holding the text field and the language list over its end, so the language reads
		as belonging to the text. Overlapped by grid placement rather than by positioning: a
		`position: relative` here would recapture the containing block of the language panel and
		of Codex's menus inside a dialog, and silently clip them. */
	&__field {
		display: grid;
		grid-template-columns: minmax( 0, 1fr );
		align-items: center;

		> * {
			grid-area: 1 / 1;
		}
	}

	/* Codex gives every text input a 256px minimum, which a narrow pane cannot hold. Nested to
		outrank Codex's own rule, which loads later at the same specificity. */
	&__field > &__text {
		min-width: 0;
	}

	/* Room for the language list, so a long text ends before it rather than running under it.
		The list shows the tag, whose small capitals come out about as wide as the field's `ch`, plus
		its chevron and padding; the tag's length is set on the field, since tags run from `en` to
		`zh-hant-tw`. */
	&__field > &__text .cdx-text-input__input {
		padding-inline-end: calc( var( --ext-neowiki-language-tag-length ) * 1ch + @size-250 );
	}

	&__language {
		justify-self: end;
	}
}
</style>
