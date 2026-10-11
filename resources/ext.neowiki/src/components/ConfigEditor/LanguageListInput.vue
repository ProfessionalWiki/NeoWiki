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
				<span class="ext-neowiki-language-list-input__name">{{ language.name }}</span>
				<!-- Hidden from a screen reader beside a name, which says the same thing. -->
				<span
					v-if="language.name !== language.tag"
					class="ext-neowiki-language-list-input__tag"
					aria-hidden="true"
				>{{ shownLanguageTag( language.tag ) }}</span>
				<template v-if="!props.disabled">
					<CdxButton
						class="ext-neowiki-language-list-input__move-up"
						weight="quiet"
						:disabled="index === 0"
						:aria-label="language.moveUpLabel"
						:title="language.moveUpLabel"
						@click="move( index, index - 1 )"
					>
						<CdxIcon :icon="cdxIconUpTriangle" />
					</CdxButton>
					<CdxButton
						class="ext-neowiki-language-list-input__move-down"
						weight="quiet"
						:disabled="index === languages.length - 1"
						:aria-label="language.moveDownLabel"
						:title="language.moveDownLabel"
						@click="move( index, index + 1 )"
					>
						<CdxIcon :icon="cdxIconDownTriangle" />
					</CdxButton>
					<CdxButton
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
				</template>
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

		<span
			class="ext-neowiki-language-list-input__announcement"
			aria-live="polite"
		>{{ announcement }}</span>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { CdxButton, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconDownTriangle, cdxIconTrash, cdxIconUpTriangle } from '@wikimedia/codex-icons';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { dataLanguageTagsOf, languageNameOrTag, shownLanguageTag } from '@/presentation/languages.ts';

interface ListedLanguage {
	tag: string;
	/** The language's name, or its tag when MediaWiki has none. */
	name: string;
	moveUpLabel: string;
	moveDownLabel: string;
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

const announcement = ref( '' );

const tags = computed( (): string[] => dataLanguageTagsOf( props.modelValue ) );

const languages = computed( (): ListedLanguage[] => tags.value.map( ( tag ) => {
	const name = languageNameOrTag( tag );

	return {
		tag: tag,
		name: name,
		moveUpLabel: mw.message( 'neowiki-config-editor-move-language-up', name ).text(),
		moveDownLabel: mw.message( 'neowiki-config-editor-move-language-down', name ).text(),
		removeLabel: mw.message( 'neowiki-config-editor-remove-language', name ).text()
	};
} ) );

function add( tag: string ): void {
	emit( 'update:modelValue', [ ...tags.value, tag ] );
	announcement.value = mw.message( 'neowiki-config-editor-language-added', languageNameOrTag( tag ) ).text();
}

/**
 * The focus stays on the button that moved the language, unless that button cannot move it any
 * further, which hands it to the one that can.
 */
async function move( from: number, to: number ): Promise<void> {
	const moved = [ ...tags.value ];
	const [ tag ] = moved.splice( from, 1 );
	moved.splice( to, 0, tag );

	emit( 'update:modelValue', moved );
	announcement.value = mw.message( 'neowiki-config-editor-language-moved', languageNameOrTag( tag ), to + 1, moved.length ).text();

	await nextTick();

	const movedUp = to < from;
	const sameButton = movedUp ? 'move-up' : 'move-down';
	const otherButton = movedUp ? 'move-down' : 'move-up';
	const atTheEnd = movedUp ? to === 0 : to === moved.length - 1;

	focusButtonOf( to, atTheEnd ? otherButton : sameButton );
}

/**
 * The focus goes to the language that took the removed one's place, or else the one before it, or
 * else, when only one is left, which cannot be removed, to the button that adds one.
 */
async function remove( index: number ): Promise<void> {
	const tag = tags.value[ index ];
	const left = tags.value.filter( ( _, otherIndex ) => otherIndex !== index );

	emit( 'update:modelValue', left );
	announcement.value = mw.message( 'neowiki-config-editor-language-removed', languageNameOrTag( tag ) ).text();

	await nextTick();

	if ( left.length === 1 ) {
		pickerRef.value?.focusTrigger();
	} else {
		focusButtonOf( Math.min( index, left.length - 1 ), 'remove' );
	}
}

function focusButtonOf( index: number, button: 'move-up' | 'move-down' | 'remove' ): void {
	listRef.value?.children[ index ]
		?.querySelector<HTMLElement>( `.ext-neowiki-language-list-input__${ button }` )
		?.focus();
}

function focus(): void {
	( fieldRef.value?.$el as HTMLElement | undefined )?.focus();
}

defineExpose( { focus } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

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

	&__announcement {
		.ext-neowiki-visually-hidden();
	}
}
</style>
