<template>
	<div class="ext-neowiki-subject-statements">
		<ul
			v-if="hasStatements"
			class="ext-neowiki-subject-statements__list"
		>
			<li
				v-for="statement in statements"
				:key="statement.propertyName.toString()"
				class="ext-neowiki-subject-statements__item"
			>
				<span class="ext-neowiki-subject-statements__property">
					{{ statement.propertyName.toString() }}
				</span>
				<span class="ext-neowiki-subject-statements__value">
					{{ formatValue( statement ) }}
				</span>
			</li>
		</ul>
		<p
			v-else
			class="ext-neowiki-subject-statements__empty"
		>
			{{ $i18n( 'neowiki-managesubjects-no-statements' ).text() }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Subject } from '@/domain/Subject';
import { Statement } from '@/domain/Statement';
import { ValueType, type Value, type RelationValue } from '@/domain/Value';

const props = defineProps<{
	subject: Subject;
}>();

const statements = computed<Statement[]>( () => [ ...props.subject.getStatements() ] );
const hasStatements = computed( () => statements.value.length > 0 );

function formatValue( statement: Statement ): string {
	const value = statement.value;
	if ( value === undefined ) {
		return '';
	}
	return formatValueByType( value );
}

function formatValueByType( value: Value ): string {
	switch ( value.type ) {
		case ValueType.String:
			return value.parts.join( ', ' );
		case ValueType.Number:
			return String( value.number );
		case ValueType.Boolean:
			return value.boolean ? '✓' : '✗';
		case ValueType.Relation:
			return ( value as RelationValue ).relations.map( ( r ) => r.target.text ).join( ', ' );
		default:
			return '';
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-statements {
	&__list {
		list-style: none;
		display: grid;
		grid-template-columns: minmax( 8rem, max-content ) 1fr;
		gap: @spacing-25 @spacing-100;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: contents;
	}

	&__property {
		font-weight: @font-weight-bold;
		color: @color-subtle;
	}

	&__value {
		margin: 0;
	}

	&__empty {
		color: @color-subtle;
		font-style: italic;
		margin: 0;
	}
}
</style>
