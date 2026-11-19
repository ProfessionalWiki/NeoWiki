<template>
	<CdxField
		ref="fieldRef"
		class="ext-neowiki-language-list-input"
		:is-fieldset="true"
		:disabled="props.disabled"
		tabindex="-1"
	>
		<template #label>
			{{ props.label }}
		</template>
		<template #description>
			<slot name="description" />
		</template>

		<ol
			ref="listRef"
			class="ext-neowiki-language-list-input__languages"
		>
			<li
				v-for="( language, index ) in languages"
				:key="language.tag"
				class="ext-neowiki-language-list-input__language"
			>
				<ReorderHandle
					v-if="!props.disabled"
					:position="index"
					:count="languages.length"
					:label="language.reorderLabel"
					@move="move( index, $event )"
				/>
				<span class="ext-neowiki-language-list-input__name">{{ language.name }}</span>
				<!-- Hidden from a screen reader beside a name, which says the same thing. -->
				<span
					v-if="language.name !== language.tag"
					class="ext-neowiki-language-list-input__tag"
					aria-hidden="true"
				>{{ shownLanguageTag( language.tag ) }}</span>
				<CdxButton
					v-if="!props.disabled"
					class="ext-neowiki-language-list-input__remove"
					weight="quiet"
					action="destructive"
					:disabled="languages.length === 1"
					:aria-label="language.removeLabel"
					:title="language.removeLabel"
					@click="remove( index )"
				>
					<CdxIcon :icon="cdxIconTrash" />
				</CdxButton>
			</li>
		</ol>

		<LanguagePicker
			v-if="!props.disabled"
			ref="pickerRef"
			:model-value="null"
			placement="bottom-start"
			:disabled-languages="tags"
			@update:model-value="add"
		>
			<template #default="{ expanded, controls, toggle }">
				<CdxButton
					class="ext-neowiki-language-list-input__add"
					aria-haspopup="listbox"
					:aria-expanded="expanded"
					:aria-controls="controls"
					@click="toggle"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ addLanguageText }}
				</CdxButton>
			</template>
		</LanguagePicker>

		<LiveAnnouncement ref="announcementRef" />
	</CdxField>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { CdxButton, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconTrash } from '@wikimedia/codex-icons';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import ReorderHandle from '@/components/common/ReorderHandle.vue';
import LiveAnnouncement, { type LiveAnnouncementExposes } from '@/components/common/LiveAnnouncement.vue';
import { movedAnnouncement, REORDER_HANDLE_SELECTOR, reorderHandleLabel, withItemMoved } from '@/components/common/reordering.ts';
import { useSortable } from '@/composables/useSortable.ts';
import { dataLanguageTagsOf, languageNameOrTag, shownLanguageTag } from '@/presentation/languages.ts';

interface ListedLanguage {
	tag: string;
	/** The language's name, or its tag when MediaWiki has none. */
	name: string;
	reorderLabel: string;
	removeLabel: string;
}

const props = defineProps<{
	/** BCP 47 tags, in order. */
	modelValue: readonly string[];
	label: string;
	disabled: boolean;
}>();

const emit = defineEmits<{
	'update:modelValue': [ tags: string[] ];
}>();

defineSlots<{
	description(): unknown;
}>();

const fieldRef = ref<InstanceType<typeof CdxField> | null>( null );
const listRef = ref<HTMLElement | null>( null );
const pickerRef = ref<InstanceType<typeof LanguagePicker> | null>( null );

const addLanguageText = mw.message( 'neowiki-config-editor-add-language' ).text();

const announcementRef = ref<LiveAnnouncementExposes | null>( null );

const tags = computed( (): string[] => dataLanguageTagsOf( props.modelValue ) );

const languages = computed( (): ListedLanguage[] => tags.value.map( ( tag ) => {
	const name = languageNameOrTag( tag );

	return {
		tag: tag,
		name: name,
		reorderLabel: reorderHandleLabel( name ),
		removeLabel: mw.message( 'neowiki-config-editor-remove-language', name ).text()
	};
} ) );

function add( tag: string ): void {
	emit( 'update:modelValue', [ ...tags.value, tag ] );
	announcementRef.value?.announce( mw.message( 'neowiki-config-editor-language-added', languageNameOrTag( tag ) ).text() );
}

function move( from: number, to: number ): void {
	const moved = withItemMoved( tags.value, from, to );

	emit( 'update:modelValue', moved );
	announcementRef.value?.announce( movedAnnouncement( languageNameOrTag( moved[ to ] ), to, moved.length ) );
}

useSortable( listRef, {
	handle: REORDER_HANDLE_SELECTOR,
	ghostClass: 'ext-neowiki-language-list-input__language--ghost',
	onReorder: move
} );

/**
 * The focus goes to the language that took the removed one's place, or else the one before it, or
 * else, when only one is left, which cannot be removed, to the button that adds one.
 */
async function remove( index: number ): Promise<void> {
	const tag = tags.value[ index ];
	const left = tags.value.filter( ( _, otherIndex ) => otherIndex !== index );

	emit( 'update:modelValue', left );
	announcementRef.value?.announce( mw.message( 'neowiki-config-editor-language-removed', languageNameOrTag( tag ) ).text() );

	await nextTick();

	if ( left.length === 1 ) {
		pickerRef.value?.focusTrigger();
	} else {
		focusRemoveButtonOf( Math.min( index, left.length - 1 ) );
	}
}

function focusRemoveButtonOf( index: number ): void {
	listRef.value?.children[ index ]
		?.querySelector<HTMLElement>( '.ext-neowiki-language-list-input__remove' )
		?.focus();
}

function focus(): void {
	( fieldRef.value?.$el as HTMLElement | undefined )?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-language-list-input {
	&__languages {
		max-width: @size-2400;
		margin: 0 0 @spacing-50;
		padding: 0;
		list-style: none;
		border: @border-width-base @border-style-base @border-color-subtle;
		border-radius: @border-radius-base;
		background-color: @background-color-base;
	}

	&__language {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		margin: 0;
		padding-inline-start: @spacing-75;

		& + & {
			border-top: @border-width-base @border-style-base @border-color-muted;
		}

		&:has( .ext-neowiki-reorder-handle ) {
			padding-inline-start: 0;
		}

		&--ghost {
			opacity: @opacity-low;
		}
	}

	&__name {
		flex-grow: 1;
		/* Room for the buttons: a row is as tall as one of them. */
		padding-block: @spacing-25;
	}

	&__tag {
		color: @color-subtle;
		font-size: @font-size-x-small;
	}

	/* Read only, the way a disabled checkbox beside it reads. */
	&.cdx-field--disabled &__name,
	&.cdx-field--disabled &__tag {
		color: @color-disabled;
	}
}
</style>
