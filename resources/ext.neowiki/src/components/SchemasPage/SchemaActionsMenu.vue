<template>
	<CdxMenuButton
		v-if="menuItems.length > 0"
		v-model:selected="menuSelection"
		:menu-items="menuItems"
		:aria-label="$i18n( 'neowiki-schemas-more-actions' ).text()"
		:title="$i18n( 'neowiki-schemas-more-actions' ).text()"
		@update:selected="dispatchMenuAction"
	>
		<CdxIcon :icon="cdxIconEllipsis" />
	</CdxMenuButton>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxIcon, CdxMenuButton } from '@wikimedia/codex';
import type { MenuButtonItemData } from '@wikimedia/codex';
import { cdxIconCopy, cdxIconEllipsis, cdxIconTrash } from '@wikimedia/codex-icons';

const props = defineProps<{
	canDuplicate: boolean;
	canDelete: boolean;
}>();

const emit = defineEmits<{
	duplicate: [];
	delete: [];
}>();

const menuItems = computed<MenuButtonItemData[]>( () => {
	const items: MenuButtonItemData[] = [];

	if ( props.canDuplicate ) {
		items.push( {
			value: 'duplicate',
			label: mw.msg( 'neowiki-schemas-duplicate' ),
			icon: cdxIconCopy
		} );
	}

	if ( props.canDelete ) {
		items.push( {
			value: 'delete',
			label: mw.msg( 'neowiki-schemas-delete' ),
			icon: cdxIconTrash,
			action: 'destructive'
		} );
	}

	return items;
} );

const menuSelection = ref<string | number | null>( null );

function dispatchMenuAction( value: string | number | null ): void {
	menuSelection.value = null;

	if ( value === 'duplicate' ) {
		emit( 'duplicate' );
	} else if ( value === 'delete' ) {
		emit( 'delete' );
	}
}
</script>
