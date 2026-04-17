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

function neowiki.getAll( propertyName, options )
	return php.getAll( propertyName, options )
end

function neowiki.getMainSubject( pageName )
	return php.getMainSubject( pageName )
end

function neowiki.getSubject( subjectId )
	return php.getSubject( subjectId )
end

function neowiki.getChildSubjects( pageName )
	return php.getChildSubjects( pageName )
end

function neowiki.query( cypher, params )
	return php.query( cypher, params )
end

return neowiki
