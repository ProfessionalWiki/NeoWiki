<!-- The Subjects the editor is holding, in the order they were opened. -->
<template>
	<ul
		ref="list"
		class="ext-neowiki-open-subject-list"
		role="listbox"
		:aria-label="$i18n( 'neowiki-subject-editor-open-subjects' ).text()"
		@keydown="onKeydown"
	>
		<li
			v-for="subject in subjects"
			:key="idOf( subject )"
			class="ext-neowiki-open-subject-list__item"
			:class="{ 'ext-neowiki-open-subject-list__item--selected': isActive( subject ) }"
			role="option"
			:aria-selected="isActive( subject )"
			:tabindex="isActive( subject ) ? 0 : -1"
			:data-mw-neowiki-subject-id="idOf( subject )"
			@click="select( subject )"
		>
			<span class="ext-neowiki-open-subject-list__name">{{ displayName( subject ) }}</span>
			<UnsavedDot v-if="isUnsaved( subject )" />
		</li>
	</ul>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import UnsavedDot from '@/components/common/UnsavedDot.vue';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';

const props = defineProps<{
	subjects: readonly Subject[];
	activeId: string;
	unsavedIds: readonly string[];
	// The name a Subject's pane shows for it, by Subject id, which follows the pane's form as it is
	// edited. A Subject it does not name, such as one whose pane has yet to register, is named as it
	// was read.
	names: ReadonlyMap<string, string>;
}>();

const emit = defineEmits<{
	select: [ SubjectId ];
}>();

const list = ref<HTMLElement | null>( null );

function idOf( subject: Subject ): string {
	return subject.getId().text;
}

function displayName( subject: Subject ): string {
	return props.names.get( idOf( subject ) ) ?? subjectDisplayName( subject );
}

function isActive( subject: Subject ): boolean {
	return idOf( subject ) === props.activeId;
}

function isUnsaved( subject: Subject ): boolean {
	return props.unsavedIds.includes( idOf( subject ) );
}

function select( subject: Subject ): void {
	emit( 'select', subject.getId() );
}

// The selection is the tab stop, so the focus goes with it and Shift+Tab comes back to the row
// the reader arrived at. Stopping at the ends rather than wrapping follows the schema editor's
// property list, the other list of its kind here.
function onKeydown( event: KeyboardEvent ): void {
	const offsets: Record<string, number> = { ArrowDown: 1, ArrowUp: -1 };
	const offset = offsets[ event.key ];

	if ( offset === undefined ) {
		return;
	}

	const index = props.subjects.findIndex( isActive ) + offset;
	const subject = props.subjects[ index ];

	// Covers both ends: a negative index reads as undefined too.
	if ( subject === undefined ) {
		return;
	}

	event.preventDefault();
	select( subject );
	optionAt( index )?.focus();
}

function optionAt( index: number ): HTMLElement | undefined {
	return list.value?.querySelectorAll<HTMLElement>( '[role="option"]' )[ index ];
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-open-subject-list {
	box-sizing: @box-sizing-base;
	/* A panel of dense rows rather than body text, so it sets its own base a step down. */
	font-size: @font-size-small;
	margin: 0;
	padding: 0;
	list-style: none;

	&__item {
		box-sizing: @box-sizing-base;
		display: flex;
		align-items: center;
		gap: @spacing-25;
		min-height: @size-200;
		/* The block padding is absorbed by min-height until a row's name takes two lines. */
		padding: @spacing-12 @spacing-35;
		border-radius: @border-radius-base;
		cursor: pointer;
		transition-property: @transition-property-base;
		transition-duration: @transition-duration-base;

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&:active {
			background-color: @background-color-button-quiet--active;
			color: @color-emphasized;
		}

		&:focus-visible {
			outline: @border-width-thick solid @outline-color-progressive--focus;
		}

		/* Codex's selected-menu-item state: background and colour, and no weight — a weight
			that changed with the state would re-measure the row under the pointer that
			selected it. */
		&--selected {
			background-color: @background-color-progressive-subtle;
			color: @color-progressive;
		}
	}

	/* Rows keep a common height, so a name gives way at its end. */
	&__name {
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
