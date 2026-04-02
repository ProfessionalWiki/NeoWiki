local neowiki = {}
local php

function neowiki.setupInterface()
	neowiki.setupInterface = nil
	php = mw_interface
	mw_interface = nil

	mw = mw or {}
	mw.neowiki = neowiki

	package.loaded['mw.neowiki'] = neowiki
end

function neowiki.getValue( propertyName, options )
	return php.getValue( propertyName, options )
end

return neowiki
