<template>
	<div
		class="ext-neowiki-dependent-subject-table"
		:class="{
			'ext-neowiki-dependent-subject-table--more-before': moreBefore,
			'ext-neowiki-dependent-subject-table--more-after': moreAfter
		}"
	>
		<!-- A region and a tab stop only while the table is too wide for its place, which the keyboard then has to
			scroll. The property it is named after is named just above it already, so a table of one Schema has no
			caption to say it again. -->
		<div
			ref="scroller"
			class="ext-neowiki-dependent-subject-table__scroller"
			:role="scrolls ? 'region' : undefined"
			:tabindex="scrolls ? 0 : undefined"
			:aria-labelledby="scrolls && props.showsSchemaName ? captionId : undefined"
			:aria-label="scrolls && !props.showsSchemaName ? props.propertyName : undefined"
			@scroll="measure"
		>
			<table
				ref="table"
				class="ext-neowiki-dependent-subject-table__table"
			>
				<caption
					v-if="props.showsSchemaName"
					:id="captionId"
					class="ext-neowiki-dependent-subject-table__caption"
				>
					<SchemaNameDisplay :schema-name="props.schema.getName()" />
				</caption>
				<thead>
					<tr>
						<th
							v-for="column in columns"
							:key="column.name.toString()"
							class="ext-neowiki-dependent-subject-table__header"
							:class="{ 'ext-neowiki-dependent-subject-table__header--number': column.type === NumberType.typeName }"
							scope="col"
						>
							{{ column.name.toString() }}
						</th>
					</tr>
				</thead>
				<tbody>
					<DependentSubjectRowDisplay
						v-for="subject in props.subjects"
						:key="subject.getId().text"
						:subject="subject"
						:columns="columns"
					/>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useGeneratedId, useResizeObserver } from '@wikimedia/codex';
import type { Schema } from '@/domain/Schema';
import type { Subject } from '@/domain/Subject';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import DependentSubjectRowDisplay from '@/components/Value/DependentSubjectRowDisplay.vue';
import { NumberType } from '@/domain/propertyTypes/Number';

const props = defineProps<{
	schema: Schema;
	/** Subjects of the Schema above, one row each. */
	subjects: readonly Subject[];
	propertyName: string;
	showsSchemaName: boolean;
}>();

const captionId = useGeneratedId( 'ext-neowiki-dependent-subject-table' );

// A property none of the rows has a value for would make a column of empty cells.
const columns = computed( (): PropertyDefinition[] => {
	const shownNames = new Set<string>();

	for ( const subject of props.subjects ) {
		subject.getNamesOfNonEmptyProperties().forEach( ( name ) => shownNames.add( name.toString() ) );
	}

	return [ ...props.schema.getPropertyDefinitions() ].filter( ( property ) => shownNames.has( property.name.toString() ) );
} );

const scroller = ref<HTMLElement | null>( null );
const table = ref<HTMLElement | null>( null );

const scrolls = ref( false );
const moreBefore = ref( false );
const moreAfter = ref( false );

// The start is a scroll position of 0 in both directions of writing: it grows negative towards the end where
// text runs right to left.
function measure(): void {
	const element = scroller.value;

	if ( element === null ) {
		return;
	}

	const scrolled = Math.abs( element.scrollLeft );

	scrolls.value = element.scrollWidth > element.clientWidth;
	moreBefore.value = scrolls.value && scrolled > 1;
	moreAfter.value = scrolls.value && scrolled + element.clientWidth < element.scrollWidth - 1;
}

watch( [
	useResizeObserver( computed( () => scroller.value ?? undefined ) ),
	useResizeObserver( computed( () => table.value ?? undefined ) )
], measure );

onMounted( measure );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-dependent-subject-table {
	position: relative;
	max-width: @size-full;

	& + & {
		margin-top: @spacing-75;
	}

	/* Shades the edge past which more of the table lies. ResourceLoader turns the gradients round where text runs
		right to left. */
	&--more-before::before,
	&--more-after::after {
		content: '';
		position: absolute;
		top: 0;
		bottom: 0;
		width: @spacing-75;
		opacity: @opacity-low;
		pointer-events: none;
	}

	&--more-before::before {
		inset-inline-start: 0;
		background-image: linear-gradient( to right, @box-shadow-color-base, @box-shadow-color-transparent );
	}

	&--more-after::after {
		inset-inline-end: 0;
		background-image: linear-gradient( to left, @box-shadow-color-base, @box-shadow-color-transparent );
	}

	&__scroller {
		overflow-x: auto;

		&:focus-visible {
			outline: @border-width-thick @border-style-base @outline-color-progressive--focus;
		}
	}

	&__table {
		border-collapse: collapse;
		/* Set apart from the card or row it sits in, whose background its header would otherwise share. */
		background-color: @background-color-base;
		/* Words stay whole: a table too wide for its container scrolls rather than breaking them. */
		overflow-wrap: normal;
		word-break: normal;
	}

	&__caption {
		padding-bottom: @spacing-25;
		text-align: start;
	}

	&__header,
	&__cell {
		padding: @spacing-25 @spacing-50;
		border: @border-width-base @border-style-base @border-color-subtle;
		text-align: start;
		vertical-align: top;
	}

	&__header--number,
	&__cell--number {
		text-align: end;
	}

	&__header {
		background-color: @background-color-neutral-subtle;
		color: @color-emphasized;
		font-weight: @font-weight-bold;
	}
}
</style>
