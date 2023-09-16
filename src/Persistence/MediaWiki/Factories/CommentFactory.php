<?php

namespace ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Factories;

use CommentStoreComment;

class CommentFactory {
	public function create( string $text ): CommentStoreComment {
		return new CommentStoreComment( null, $text );
	}
}
