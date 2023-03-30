local neo = {}
local php

function neo.getLabel( expression )
	return php.getLabel( expression )
end

function neo.setupInterface( options )
	neo.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	package.loaded['NeoWiki'] = neo
end

return neo
