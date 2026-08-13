<template>
	<span class="ext-neowiki-editable-text">
		<template v-if="!editing">
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
		<CdxTextInput
			v-else
			ref="inputRef"
			v-model="draft"
			class="ext-neowiki-editable-text__input"
			:status="status ?? 'default'"
			:aria-label="inputAriaLabel"
			:aria-invalid="status === 'error' ? 'true' : undefined"
			@keydown.enter="commitViaKeyboard"
			@keyup.esc.stop="cancel"
			@blur="commit"
		/>
	</span>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';

/**
 * Inline edit-in-place for a single line of text: renders the value as plain
 * text (styled by its context - it inherits font properties) with a quiet edit
 * button that swaps in a text input. Enter and blur commit the draft, Escape
 * discards it. Committing only emits when the draft differs from the value;
 * persistence is the host's concern. With `required`, a blank draft still
 * emits (so the host's validation can flag it) but the input stays open,
 * keeping the error state anchored to a visible field; Escape still reverts.
 */
const props = defineProps<{
	modelValue: string;
	editButtonLabel: string;
	inputAriaLabel: string;
	placeholder?: string;
	status?: 'default' | 'error';
	required?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [ value: string ] }>();

const editing = ref( false );
const draft = ref( '' );
const inputRef = ref<InstanceType<typeof CdxTextInput> | null>( null );
const editButtonRef = ref<InstanceType<typeof CdxButton> | null>( null );

const displayText = computed( () => props.modelValue === '' ? ( props.placeholder ?? '' ) : props.modelValue );

async function startEditing(): Promise<void> {
	draft.value = props.modelValue;
	editing.value = true;
	await nextTick();
	inputRef.value?.focus();
}

async function commitViaKeyboard( event: KeyboardEvent ): Promise<void> {
	// The Enter that confirms an IME composition is not a commit.
	if ( event.isComposing ) {
		return;
	}

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

// A value replaced by the host mid-edit invalidates the draft: abort rather
// than commit an old subject's draft against the new one. The comparison
// keeps the required-blank flow open: its own emit echoes back as a
// modelValue equal to the draft.
watch( () => props.modelValue, () => {
	if ( editing.value && props.modelValue !== draft.value ) {
		editing.value = false;
	}
} );

async function focusEditButton(): Promise<void> {
	await nextTick();
	( editButtonRef.value?.$el as HTMLElement | undefined )?.focus();
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
