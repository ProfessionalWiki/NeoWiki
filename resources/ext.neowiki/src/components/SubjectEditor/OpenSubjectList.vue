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
			<span
				v-if="nameOf( subject ) !== null"
				class="ext-neowiki-open-subject-list__name"
			>{{ nameOf( subject ) }}</span>
			<span
				v-else
				class="ext-neowiki-open-subject-list__name ext-neowiki-open-subject-list__name--unlabeled"
			>{{ $i18n( 'neowiki-subject-no-label' ).text() }}</span>
			<SchemaNameDisplay
				v-if="schemaNameToShow( subject ) !== null"
				class="ext-neowiki-open-subject-list__schema"
				:schema-name="schemaNameToShow( subject )!"
				link="none"
			/>
			<UnsavedDot
				v-if="isUnsaved( subject )"
				class="ext-neowiki-open-subject-list__unsaved"
			/>
		</li>
	</ul>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import UnsavedDot from '@/components/common/UnsavedDot.vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import { subjectDisplayName } from '@/presentation/subjectDisplayName.ts';
import { schemaNameToShow } from '@/presentation/schemaNameToShow.ts';
import { Subject } from '@/domain/Subject.ts';
import { SubjectId } from '@/domain/SubjectId.ts';

const props = defineProps<{
	subjects: readonly Subject[];
	activeId: string;
	unsavedIds: readonly string[];
}>();

const emit = defineEmits<{
	select: [ SubjectId ];
}>();

const list = ref<HTMLElement | null>( null );

function idOf( subject: Subject ): string {
	return subject.getId().text;
}

// Null for a Subject nobody named, which other surfaces show under its id. Here the row says so in
// words instead, as the label field beside it does.
function nameOf( subject: Subject ): string | null {
	return subject.hasGeneratedDisplayName() && subject.getHostSubject() === null ?
		null :
		subjectDisplayName( subject );
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
	margin: 0;
	padding: 0;
	list-style: none;

	/* The unsaved dot leads, in a column every row keeps: beside a name of any length, in line
		with the other dots, and moving nothing when it appears. */
	&__item {
		box-sizing: @box-sizing-base;
		display: grid;
		grid-template-columns: @size-50 minmax( 0, 1fr );
		align-items: center;
		gap: @spacing-12 @spacing-50;
		padding: @spacing-50 @spacing-75;
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

	&__name,
	&__schema {
		grid-column: 2;
	}

	/* A long name wraps rather than giving way at its end: the rows are few, so there is room.
		Past two lines it does give way. */
	&__name {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		overflow-wrap: anywhere;

		&--unlabeled {
			color: @color-subtle;
		}
	}

	&__schema {
		justify-self: start;
	}

	&__unsaved {
		grid-area: 1 / 1;
	}
}
</style>
