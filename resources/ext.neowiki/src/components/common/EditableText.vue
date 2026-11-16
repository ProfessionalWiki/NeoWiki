<template>
	<span class="ext-neowiki-editable-text">
		<template v-if="editing">
			<CdxTextInput
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

		<template v-else>
			<span
				class="ext-neowiki-editable-text__text"
				:class="{ 'ext-neowiki-editable-text__text--placeholder': modelValue === '' }"
			>{{ displayText }}</span>
			<!-- Native rather than CdxButton, which clicks on the Enter keyup: the Enter that
				commits the field is released on this button. -->
			<button
				ref="editButtonRef"
				class="cdx-button cdx-button--weight-quiet cdx-button--icon-only ext-neowiki-editable-text__edit-button"
				type="button"
				:aria-label="editButtonLabel"
				@keydown.enter="ignoreAutorepeat"
				@click="startEditing"
			>
				<CdxIcon :icon="cdxIconEdit" size="small" />
			</button>
		</template>
	</span>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';

/**
 * Inline edit-in-place for text: renders the value as plain text (styled by its
 * context - it inherits font properties) with a quiet edit button that swaps in
 * an input. Enter and blur commit the draft, Escape discards it. Committing only
 * emits when the draft differs from the value; persistence is the host's concern,
 * including translating a cleared value — emitted as '' — into whatever the host
 * stores for "no value". `dirty` reports whether the open draft differs from the
 * value, so a host can offer Save before the commit and withdraw it once the draft
 * is discarded or the field is taken down; a host saving while the field is open
 * calls the exposed `commit()` first. A blur caused by a pointer press elsewhere
 * closes the field only once the press ends, so the release lands where it was
 * aimed. The placeholder stands in for an empty value in both modes.
 * With `required`, a blank draft still emits (so the host's validation can flag
 * it) but the input stays open, keeping the error state anchored to a visible
 * field; Escape still reverts.
 */
const props = defineProps<{
	modelValue: string;
	editButtonLabel: string;
	inputAriaLabel: string;
	placeholder?: string;
	status?: 'default' | 'error';
	required?: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ value: string ];
	dirty: [ dirty: boolean ];
}>();

const editing = ref( false );
const draft = ref( '' );
const inputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );
const editButtonRef = ref<HTMLElement | null>( null );

const displayText = computed( () => props.modelValue === '' ? ( props.placeholder ?? '' ) : props.modelValue );

async function startEditing(): Promise<void> {
	draft.value = props.modelValue;
	editing.value = true;
	await nextTick();
	inputRef.value?.focus();
}

async function commitViaKeyboard( event: KeyboardEvent ): Promise<void> {
	// The Enter that confirms an IME composition is not a commit, nor is the autorepeat of one
	// held down on the edit button, which reaches the field that button opened.
	if ( event.isComposing || event.repeat ) {
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
		closeField();
	}

	if ( draft.value !== props.modelValue ) {
		emit( 'update:modelValue', draft.value );
	}
}

// Swapping the input for text mid-press re-lays out the dialog, and the release then lands
// away from what was pressed: on the backdrop instead of Save, which asks to discard the edit.
// The close waits one task past the release, so the click the press produces is dispatched first.
let pressed = false;
let closeOnRelease = false;

function closeField(): void {
	if ( pressed ) {
		closeOnRelease = true;
		return;
	}

	editing.value = false;
}

function onPointerDown(): void {
	pressed = true;
}

function onPointerUp(): void {
	pressed = false;

	if ( closeOnRelease ) {
		closeOnRelease = false;
		setTimeout( () => {
			editing.value = false;
		} );
	}
}

function watchPresses(): void {
	pressed = false;
	closeOnRelease = false;
	document.addEventListener( 'pointerdown', onPointerDown, true );
	document.addEventListener( 'pointerup', onPointerUp, true );
	document.addEventListener( 'pointercancel', onPointerUp, true );
}

function stopWatchingPresses(): void {
	document.removeEventListener( 'pointerdown', onPointerDown, true );
	document.removeEventListener( 'pointerup', onPointerUp, true );
	document.removeEventListener( 'pointercancel', onPointerUp, true );
}

const dirty = computed( () => editing.value && draft.value !== props.modelValue );

// Synchronous, so the blur that commits on the way out of a closing dialog withdraws the
// report before the field is taken down with it.
watch( dirty, ( value ) => emit( 'dirty', value ), { flush: 'sync' } );

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

// A value replaced by the host mid-edit invalidates the draft: abort rather
// than commit an old subject's draft against the new one. The comparison
// keeps the required-blank flow open: its own emit echoes back as a
// modelValue equal to the draft.
watch( () => props.modelValue, () => {
	if ( editing.value && props.modelValue !== draft.value ) {
		editing.value = false;
	}
} );

watch( editing, ( isEditing ) => {
	if ( isEditing ) {
		watchPresses();
	} else {
		stopWatchingPresses();
	}
} );

// A field taken down mid-edit, with the dialog that closes around it, can no longer be
// discarded by the user, so it withdraws its own report.
onBeforeUnmount( () => {
	stopWatchingPresses();

	if ( dirty.value ) {
		emit( 'dirty', false );
	}
} );

defineExpose( { commit } );

async function focusEditButton(): Promise<void> {
	await nextTick();
	editButtonRef.value?.focus();
}

// A native button clicks on every autorepeat of a held Enter, which would reopen the field
// that the Enter's first press committed.
function ignoreAutorepeat( event: KeyboardEvent ): void {
	if ( event.repeat ) {
		event.preventDefault();
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

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
	}
}
</style>
