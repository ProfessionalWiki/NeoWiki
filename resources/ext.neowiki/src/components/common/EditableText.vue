<template>
	<span
		class="ext-neowiki-editable-text"
		:class="{
			'ext-neowiki-editable-text--clampable': clampable,
			'ext-neowiki-editable-text--clamped': clamped
		}"
	>
		<template v-if="editing">
			<CdxTextArea
				v-if="multiline"
				ref="inputRef"
				v-model="draft"
				class="ext-neowiki-editable-text__input"
				:autosize="true"
				:status="status ?? 'default'"
				:placeholder="placeholder"
				:aria-label="inputAriaLabel"
				:aria-invalid="status === 'error' ? 'true' : undefined"
				@keydown.enter="commitViaKeyboard"
				@keyup.esc.stop="cancel"
				@blur="commit"
			/>
			<CdxTextInput
				v-else
				ref="inputRef"
				v-model="draft"
				class="ext-neowiki-editable-text__input"
				:status="status ?? 'default'"
				:placeholder="placeholder"
				:aria-label="inputAriaLabel"
				:aria-invalid="status === 'error' ? 'true' : undefined"
				@keydown.enter="commitViaKeyboard"
				@keyup.esc.stop="cancel"
				@blur="commit"
			/>
		</template>

		<!-- Nothing to clamp, reveal or point a pencil at, so the whole control
			is the invitation to write one. -->
		<button
			v-else-if="showsAddButton"
			ref="editButtonRef"
			class="ext-neowiki-editable-text__add-button"
			type="button"
			@click="startEditing"
		>
			{{ addLabel }}
		</button>

		<!-- Clamped presentation: the controls sit inside the text box so they follow
			its last line, and are lifted onto that line over a fade once it overflows. -->
		<span
			v-else-if="clampable"
			ref="textRef"
			class="ext-neowiki-editable-text__text"
			:class="{ 'ext-neowiki-editable-text__text--placeholder': modelValue === '' }"
		>{{ displayText }}<span class="ext-neowiki-editable-text__controls">
			<button
				v-if="overflowing"
				class="ext-neowiki-editable-text__icon-button"
				type="button"
				:aria-label="revealLabel"
				:aria-expanded="expanded"
				:title="revealLabel"
				@click="toggleExpanded"
			>
				<CdxIcon :icon="expanded ? cdxIconCollapse : cdxIconEllipsis" size="small" />
			</button>
			<button
				ref="editButtonRef"
				class="ext-neowiki-editable-text__icon-button"
				type="button"
				:aria-label="editButtonLabel"
				:title="editButtonLabel"
				@click="startEditing"
			>
				<CdxIcon :icon="cdxIconEdit" size="small" />
			</button>
		</span></span>

		<template v-else>
			<span
				class="ext-neowiki-editable-text__text"
				:class="{ 'ext-neowiki-editable-text__text--placeholder': modelValue === '' }"
			>{{ displayText }}</span>
			<CdxButton
				ref="editButtonRef"
				class="ext-neowiki-editable-text__edit-button"
				weight="quiet"
				type="button"
				:aria-label="editButtonLabel"
				@click="startEditing"
			>
				<CdxIcon :icon="cdxIconEdit" size="small" />
			</CdxButton>
		</template>
	</span>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, toRef, watch } from 'vue';
import { CdxButton, CdxIcon, CdxTextArea, CdxTextInput } from '@wikimedia/codex';
import { cdxIconCollapse, cdxIconEdit, cdxIconEllipsis } from '@wikimedia/codex-icons';
import { useClampedText } from '@/composables/useClampedText.ts';

/**
 * Inline edit-in-place for text: renders the value as plain text (styled by its
 * context - it inherits font properties) with a quiet edit button that swaps in
 * an input. Enter and blur commit the draft, Escape discards it. Committing only
 * emits when the draft differs from the value; persistence is the host's concern,
 * including translating a cleared value — emitted as '' — into whatever the host
 * stores for "no value". The placeholder stands in for an empty value in both modes.
 * With `required`, a blank draft still emits (so the host's validation can flag
 * it) but the input stays open, keeping the error state anchored to a visible
 * field; Escape still reverts.
 *
 * `multiline` edits in an autosizing text area instead of a single-line input.
 * Enter then inserts a newline, so Ctrl or Cmd with Enter commits in its place.
 *
 * `addLabel` replaces the whole display with a text button of that name while
 * the value is empty, for a value whose absence is worth an invitation rather
 * than a placeholder with a pencil beside it.
 *
 * `clampLines` limits the displayed text to that many lines and switches to a
 * compact presentation: the edit button shrinks to 16px of ink over a full-size
 * target and moves inside the text box, so it follows the last line rather than
 * sitting at the far right of a wrapped block. Once the text overflows, a reveal
 * button joins it and the pair is lifted onto the last line over a fade, which
 * is what shows the reader that the text continues.
 */
const props = defineProps<{
	modelValue: string;
	editButtonLabel: string;
	inputAriaLabel: string;
	placeholder?: string;
	status?: 'default' | 'error';
	required?: boolean;
	multiline?: boolean;
	clampLines?: number;
	expandLabel?: string;
	collapseLabel?: string;
	addLabel?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [ value: string ] }>();

const editing = ref( false );
const draft = ref( '' );
const expanded = ref( false );
const inputRef = ref<InstanceType<typeof CdxTextInput> | InstanceType<typeof CdxTextArea> | null>( null );
const editButtonRef = ref<InstanceType<typeof CdxButton> | HTMLElement | null>( null );
const textRef = ref<HTMLElement | null>( null );

const displayText = computed( () => props.modelValue === '' ? ( props.placeholder ?? '' ) : props.modelValue );

const clampable = computed( () => props.clampLines !== undefined );

const showsAddButton = computed( () => props.addLabel !== undefined && props.modelValue === '' );

const { overflowing, measure: measureOverflow } = useClampedText(
	textRef,
	toRef( props, 'clampLines' ),
	computed( () => clampable.value && !expanded.value )
);

// Only the overflowing state lifts the controls out of the text flow.
const clamped = computed( () => overflowing.value && !expanded.value );

const revealLabel = computed( () => expanded.value ?
	( props.collapseLabel ?? '' ) :
	( props.expandLabel ?? '' )
);

async function startEditing(): Promise<void> {
	draft.value = props.modelValue;
	editing.value = true;
	await nextTick();
	focusField();
}

// CdxTextInput exposes focus(), CdxTextArea does not, so both are focused
// through the field they wrap.
function focusField(): void {
	const wrapper = inputRef.value?.$el as HTMLElement | undefined;
	wrapper?.querySelector<HTMLElement>( 'input, textarea' )?.focus();
}

async function commitViaKeyboard( event: KeyboardEvent ): Promise<void> {
	// The Enter that confirms an IME composition is not a commit.
	if ( event.isComposing ) {
		return;
	}

	// A text area needs Enter for newlines, so committing moves to Ctrl/Cmd+Enter.
	if ( props.multiline && !event.ctrlKey && !event.metaKey ) {
		return;
	}

	event.preventDefault();
	commit();

	if ( !editing.value ) {
		await focusEditButton();
	}
}

function commit(): void {
	// The blur that follows an Escape or an Enter must not commit again.
	if ( !editing.value ) {
		return;
	}

	if ( !props.required || draft.value.trim() !== '' ) {
		editing.value = false;
	}

	if ( draft.value !== props.modelValue ) {
		emit( 'update:modelValue', draft.value );
	}
}

async function cancel( event: KeyboardEvent ): Promise<void> {
	// The Escape that cancels an IME composition is not a cancel. This runs on
	// keyup because Codex dialogs close on the Escape KEYUP: a keydown-time
	// cancel would unmount the input and let the release close the host dialog.
	if ( event.isComposing ) {
		return;
	}

	editing.value = false;
	await focusEditButton();
}

function toggleExpanded(): void {
	expanded.value = !expanded.value;
}

// A value replaced by the host mid-edit invalidates the draft: abort rather
// than commit an old subject's draft against the new one. The comparison
// keeps the required-blank flow open: its own emit echoes back as a
// modelValue equal to the draft.
watch( () => props.modelValue, () => {
	if ( editing.value && props.modelValue !== draft.value ) {
		editing.value = false;
	}

	expanded.value = false;
	nextTick( measureOverflow );
} );

watch( editing, ( isEditing ) => {
	if ( !isEditing ) {
		nextTick( measureOverflow );
	}
} );

async function focusEditButton(): Promise<void> {
	await nextTick();
	editButtonElement()?.focus();
}

function editButtonElement(): HTMLElement | undefined {
	const button = editButtonRef.value;

	if ( button === null ) {
		return undefined;
	}

	return button instanceof HTMLElement ? button : ( button.$el as HTMLElement );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@wikimedia/codex/mixins/link.less';

.ext-neowiki-editable-text {
	display: inline-flex;
	align-items: center;
	gap: @spacing-25;
	min-width: 0;

	&__text {
		overflow-wrap: anywhere;
		min-width: 0;

		&--placeholder {
			color: @color-subtle;
		}
	}

	&__input {
		flex: 1;

		/* Match the display mode's height (set by the 32px edit button),
			so toggling modes does not shift the surrounding layout. */
		.cdx-text-input__input {
			font-size: inherit;
			font-weight: inherit;
			line-height: inherit;
			padding-top: 0;
			padding-bottom: 0;
			min-height: @min-size-interactive-pointer;
		}

		.cdx-text-area__textarea {
			font-size: inherit;
			font-weight: inherit;
			line-height: inherit;
		}
	}

	/* Clamped presentation. The text box does the clipping, so the controls it
		contains follow its last line instead of a wrapped block's right edge. */
	&--clampable {
		display: block;
	}

	&--clampable &__text {
		display: block;
		position: relative;
	}

	/* Only while text is actually cut off: clipping otherwise slices the focus
		ring and hover area of the buttons the text box holds. */
	&--clamped &__text {
		overflow: hidden;
	}

	&__controls {
		display: inline-flex;
		align-items: center;
		/* Wide enough that the full-size targets of two 16px icons cannot overlap. */
		gap: @spacing-100;
		margin-inline-start: @spacing-50;
		/* Kept out of the line box's height, so a description that fits its lines
			is not measured as overflowing just because the controls are taller. */
		height: 1em;
		line-height: 1;
		vertical-align: middle;
	}

	&--clamped &__controls {
		position: absolute;
		inset-inline-end: 0;
		bottom: 0;
		/* One whole line, so the text passing behind cannot show above the fade. */
		height: var( --ext-neowiki-clamped-text-line-height, 1.5em );
		margin-inline-start: 0;
		/* A runway for the fade the text disappears under, and room at the end so
			the clipped text box does not cut off the edit button's hover area.
			CSSJanus flips the gradient for right-to-left. */
		padding-inline: @spacing-250 @spacing-50;
		background: linear-gradient( to right, @background-color-transparent, @background-color-base @spacing-200 );
	}

	/* A link rather than a control with a box of its own, so it sits on the
		line it replaces instead of stretching it to a button's height. */
	&__add-button {
		.cdx-mixin-link-base();
		margin: 0;
		border: 0;
		padding: 0;
		background-color: transparent;
		font-family: inherit;
		font-size: inherit;
		line-height: inherit;
		cursor: pointer;
	}

	&__icon-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		position: relative;
		width: @size-100;
		height: @size-100;
		margin: 0;
		border: 0;
		padding: 0;
		background-color: transparent;
		color: @color-base;
		cursor: pointer;

		/* 16px of ink, but @spacing-50 on every side brings the pointer target
			up to the 32px minimum. */
		&::after {
			content: '';
			position: absolute;
			inset: -@spacing-50;
			border-radius: @border-radius-base;
		}

		&:hover::after {
			background-color: @background-color-button-quiet--hover;
		}

		&:focus-visible {
			outline: 0;
		}

		&:focus-visible::after {
			outline: @border-width-thick @border-style-base @color-progressive;
		}
	}
}
</style>
