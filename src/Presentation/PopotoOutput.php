<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Presentation;

class PopotoOutput {

	public function buildHtml(): string {
		return <<<HTML
<section class="ppt-section-main">
    <div class="ppt-section-header">
        <span class="ppt-header-span">TODO title</span>
    </div>

    <div class="ppt-container-graph" style="height: 350px">

        <div id="popoto-taxonomy" class="ppt-taxo-nav">

        </div>

        <div id="popoto-graph" class="ppt-div-graph">
            <!-- Graph is generated here -->
        </div>
    </div>

    <div id="popoto-cypher" class="ppt-container-query">
        <!-- Query viewer is generated here -->
    </div>

    <!-- Add a header with total number of results count -->
    <div class="ppt-section-header">
        RESULTS <span id="rescount" class="ppt-count"></span>
    </div>

    <div id="popoto-results" class="ppt-container-results">
        <!-- Results are generated here -->
    </div>

</section>
HTML;
	}

}
