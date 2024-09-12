<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\EntryPoints;

use MediaWiki\Output\OutputPage;
use Skin;

class NeoWikiHooks {

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		if ( !$out->isArticle() ) {
			return;
		}

		// TODO: just for testing
		if ( str_contains( $out->getHTML(), 'infobox' ) ) {
			$out->addHTML( '<div id="neowiki-infobox"></div>' );
			$out->addModules( 'ext.neowiki.infobox' );
		} else {
			$out->addHTML('
<span id="app-container"></span>

<div class="neowiki-add-button">Button 1</div>
<h2>Static Content Section</h2>
<p>This paragraph shouldn\'t change. It contains <strong>static</strong> or <em>external</em> content that must remain intact.</p>

<div class="neowiki-add-button">Button 2</div>
<table border="1">
    <caption>Table that shouldn\'t change</caption>
    <tr>
        <th>Header 1</th>
        <th>Header 2</th>
        <th>Header 3</th>
    </tr>
    <tr>
        <td>Row 1, Cell 1</td>
        <td>Row 1, Cell 2</td>
        <td>Row 1, Cell 3</td>
    </tr>
    <tr>
        <td>Row 2, Cell 1</td>
        <td>Row 2, Cell 2</td>
        <td>Row 2, Cell 3</td>
    </tr>
</table>

<div class="neowiki-add-button">Button 3</div>
<div class="neowiki-add-button">Button 4</div>
<ul>
    <li>Unordered list item 1 - shouldn\'t change</li>
    <li>Unordered list item 2 - static content</li>
    <li>Unordered list item 3 - external data</li>
</ul>

<div class="neowiki-add-button">Button 5</div>
<blockquote>
    This is a blockquote containing content that shouldn\'t change. It might represent a citation or an important quote from an external source.
</blockquote>

<div class="neowiki-add-button">Button 6</div>
<div class="neowiki-add-button">Button 7</div>
<pre>
function codeExample() {
    // This is a code block that shouldn\'t change
    console.log("Hello, static world!");
}
</pre>

<div class="neowiki-add-button">Button 8</div>
<div class="neowiki-add-button">Button 9</div>
<div class="neowiki-add-button">Button 10</div>
<ol>
    <li>First item in an ordered list - shouldn\'t change</li>
    <li>Second item - static content</li>
    <li>Third item - external data that must remain intact</li>
</ol>

<div class="neowiki-add-button">Button 11</div>
<div class="neowiki-add-button">Button 12</div>
<div class="image-container">
    <img src="https://seeklogo.com/images/M/mediawiki-2020-logo-695A097500-seeklogo.com.png" alt="Placeholder image that shouldn\'t change">
    <figcaption>Figure 1: A static image caption that shouldn\'t be modified</figcaption>
</div>

<div class="neowiki-add-button">Button 13</div>
<div class="neowiki-add-button">Button 14</div>
<div class="neowiki-add-button">Button 15</div>
<table border="1">
    <caption>Another table with static content</caption>
    <tr>
        <th>Column A</th>
        <th>Column B</th>
        <th>Column C</th>
    </tr>
    <tr>
        <td>Data 1A</td>
        <td>Data 1B</td>
        <td>Data 1C</td>
    </tr>
    <tr>
        <td>Data 2A</td>
        <td>Data 2B</td>
        <td>Data 2C</td>
    </tr>
</table>

<div class="neowiki-add-button">Button 16</div>
<div class="neowiki-add-button">Button 17</div>
<div class="neowiki-add-button">Button 18</div>
<dl>
    <dt>Definition Term 1</dt>
    <dd>Definition description 1 - This content shouldn\'t change as it\'s part of a static glossary.</dd>
    <dt>Definition Term 2</dt>
    <dd>Definition description 2 - Another example of unchangeable content in a description list.</dd>
</dl>

<div class="neowiki-add-button">Button 19</div>
<div class="neowiki-add-button">Button 20</div>

<p>Final content that shouldn\'t change. This paragraph contains important information that must remain static or unaltered by any dynamic processes.</p>
');
			$out->addModules( 'ext.neowiki.addButton' );
		}
	}
}
