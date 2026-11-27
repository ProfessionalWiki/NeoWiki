<template>
	<div
		ref="root"
		class="ext-neowiki-date-text-input"
		@focusin="onFocusIn"
		@focusout="onFocusOut"
	>
		<div
			class="ext-neowiki-date-text-input__field"
			:class="{
				'ext-neowiki-date-text-input__field--with-pick': canPick,
				'ext-neowiki-date-text-input__field--with-footer': showsFooter
			}"
		>
			<CdxTextInput
				class="ext-neowiki-date-text-input__text"
				:model-value="text"
				:start-icon="cdxIconCalendar"
				@update:model-value="onText"
			/>
			<template v-if="canPick">
				<input
					ref="native"
					class="ext-neowiki-date-text-input__native"
					type="date"
					tabindex="-1"
					aria-hidden="true"
					:value="fullDate"
					:min="props.min"
					:max="props.max"
					@change="onPicked"
				>
				<CdxButton
					class="ext-neowiki-date-text-input__pick"
					weight="quiet"
					:aria-label="pickLabel"
					@click="openPicker"
				>
					<CdxIcon :icon="cdxIconCalendar" />
				</CdxButton>
			</template>
		</div>
		<div
			v-if="showsFooter"
			class="ext-neowiki-date-text-input__footer"
			tabindex="-1"
		>
			<span
				v-if="currentIso !== undefined"
				class="ext-neowiki-date-text-input__stored"
			>{{ storedText }}</span>
			<span v-else>{{ props.hint }}</span>
			<CdxButton
				v-if="alternativeLabel !== ''"
				class="ext-neowiki-date-text-input__alternative"
				weight="quiet"
				action="progressive"
				@mousedown.prevent
				@click="swap"
			>
				{{ alternativeLabel }}
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconCalendar } from '@wikimedia/codex-icons';
import { dateTextOf, DateTextReading, formatDateForDisplay, readDateText } from '@/domain/propertyTypes/dateText.ts';
import { userLanguageTag } from '@/presentation/languages.ts';
import { useAfterPress } from '@/composables/useAfterPress.ts';

/**
 * A text field that reads a date the way people write it and holds it as `YYYY`, `YYYY-MM` or
 * `YYYY-MM-DD`: `modelValue` is that form, undefined while the text is empty. Text that cannot
 * be read changes no value: `unreadableMessage` says why, and `lastReadIso` is the last date the
 * field could read. `errorMessage` is that message held back while the field is being edited,
 * as half-typed text is unreadable a few times on the way to a date. `hint` is what the property
 * asks for. With `picker`, a button opens the browser's calendar, which gives full dates only,
 * from `min` to `max`.
 */
const props = withDefaults(
	defineProps<{ modelValue: string | undefined; hint?: string; picker?: boolean; min?: string; max?: string }>(),
	{ hint: '', picker: false, min: undefined, max: undefined }
);

const emit = defineEmits<{
	'update:modelValue': [ string | undefined ];
}>();

const language = userLanguageTag();

const text = ref( '' );
const swapped = ref( false );
const editing = ref( false );
const root = ref<HTMLElement | null>( null );

const { afterPress, cancel: stayInField } = useAfterPress( editing );

function onFocusIn(): void {
	stayInField();
	editing.value = true;
}

// Focus moving to the footer's own button is still editing the field.
function onFocusOut( event: FocusEvent ): void {
	if ( event.relatedTarget instanceof Node && root.value?.contains( event.relatedTarget ) ) {
		return;
	}

	afterPress( leave );
}

function leave(): void {
	editing.value = false;

	if ( currentIso.value !== undefined ) {
		text.value = dateTextOf( currentIso.value, language );
	}
}

const reading = computed<DateTextReading>( () => readDateText( text.value, language ) );

const currentIso = computed<string | undefined>( () => {
	if ( reading.value.kind !== 'date' ) {
		return undefined;
	}

	return swapped.value && reading.value.alternative !== undefined ? reading.value.alternative : reading.value.iso;
} );

const otherIso = computed<string | undefined>( () => {
	if ( reading.value.kind !== 'date' || reading.value.alternative === undefined ) {
		return undefined;
	}

	return swapped.value ? reading.value.iso : reading.value.alternative;
} );

const lastReadIso = ref<string | undefined>( undefined );

// An incoming date the text already reads as leaves the text alone, so what the user typed
// is kept.
const initializeText = ( iso: string | undefined ): void => {
	lastReadIso.value = iso;

	if ( iso !== currentIso.value ) {
		text.value = iso === undefined ? '' : dateTextOf( iso, language );
		swapped.value = false;
	}
};

initializeText( props.modelValue );

watch( () => props.modelValue, initializeText );

function onText( value: string ): void {
	text.value = value;
	swapped.value = false;
	report();
}

function swap(): void {
	swapped.value = !swapped.value;
	report();
}

// Reporting text that does not read as a date as empty would erase the stored date.
function report(): void {
	if ( reading.value.kind === 'unreadable' ) {
		return;
	}

	lastReadIso.value = currentIso.value;
	emit( 'update:modelValue', currentIso.value );
}

const pickLabel = mw.message( 'neowiki-date-input-pick' ).text();

const storedText = computed<string>( () =>
	currentIso.value === undefined ? '' : mw.message( 'neowiki-date-input-stored-as', currentIso.value ).text()
);

const alternativeLabel = computed<string>( () =>
	otherIso.value === undefined ?
		'' :
		mw.message( 'neowiki-date-input-alternative', formatDateForDisplay( otherIso.value, language ) ).text()
);

const showsFooter = computed<boolean>( () =>
	editing.value && ( currentIso.value !== undefined || ( reading.value.kind === 'empty' && props.hint !== '' ) )
);

const unreadableMessage = computed<string | null>( () => {
	const r = reading.value;

	if ( r.kind !== 'unreadable' ) {
		return null;
	}

	switch ( r.problem ) {
		case 'invalid-day':
			return mw.message( 'neowiki-field-invalid-day', String( r.daysInMonth ) ).text();
		case 'invalid-month':
			return mw.message( 'neowiki-field-invalid-month' ).text();
		case 'year-zero':
			return mw.message( 'neowiki-field-year-zero' ).text();
		default:
			return mw.message( 'neowiki-field-unreadable-date', text.value.trim() ).text();
	}
} );

const canPick = computed<boolean>( () =>
	props.picker && 'showPicker' in HTMLInputElement.prototype
);
const native = ref<HTMLInputElement | null>( null );

const fullDate = computed<string>( () =>
	currentIso.value !== undefined && currentIso.value.length === 'YYYY-MM-DD'.length ? currentIso.value : ''
);

function openPicker(): void {
	try {
		native.value?.showPicker();
	} catch {
		// Refused outside a user gesture, or by a browser that has the method but not the picker.
	}
}

function onPicked( event: Event ): void {
	onText( ( event.target as HTMLInputElement ).value );
}

defineExpose( {
	lastReadIso: (): string | undefined => lastReadIso.value,
	unreadableMessage: (): string | null => unreadableMessage.value,
	errorMessage: (): string | null => editing.value ? null : unreadableMessage.value
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-date-text-input {
	/* One cell holding the text field and the calendar button over its end. */
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

	/* Room for the button, so a long text ends before it rather than running under it. */
	&__field--with-pick > &__text .cdx-text-input__input {
		padding-inline-end: calc( @min-size-interactive-pointer + @spacing-25 );
	}

	/* The footer continues the field's border, so the field's bottom corners square off. */
	&__field--with-footer > &__text .cdx-text-input__input {
		border-end-start-radius: 0;
		border-end-end-radius: 0;
	}

	/* Positioned so it paints over the field: Codex gives `.cdx-text-input` `position: relative`,
		and a positioned box paints above an unpositioned sibling whatever the source order. */
	&__pick {
		position: relative;
		justify-self: end;
	}

	/* Under the button, so that the calendar the browser opens hangs from the button rather
		than from the start of the field. */
	&__native {
		justify-self: end;
		width: 1px;
		height: 1px;
		opacity: 0;
		pointer-events: none;
	}

	/* Joined to the field the way Codex stacks a chip input's chips onto its input: the shared
		edge is overlapped by one border width, and the field, being positioned, paints over it. */
	&__footer {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-25;
		box-sizing: border-box;
		margin-top: -@border-width-base;
		border: @border-width-base @border-style-base @border-color-base;
		border-end-start-radius: @border-radius-base;
		border-end-end-radius: @border-radius-base;
		padding: @spacing-25 @spacing-50;
		background-color: @background-color-interactive-subtle;
		font-size: @font-size-small;
		color: @color-subtle;
	}

	&__footer:focus {
		outline: 0;
	}

	&__stored {
		font-family: @font-family-monospace;
		user-select: all;
	}
}
</style>
