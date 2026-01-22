#! /bin/bash

STATUS=0

. ./.env

function echoGood {
    echo -e "\e[32mSuccess: $1\e[0m"
}

function echoBad {
    echo -e "\e[31mFail: $1\e[0m"
}

function testContains {
    if echo "$1" | grep "$2" > /dev/null
    then
        echoGood "$3"
    else
        echoBad "$3"
        STATUS=1
    fi
}

mainPage=`curl -fsSL http://localhost:${MW_SERVER_PORT}/index.php/Main_Page`

testContains "$mainPage" "MediaWiki has been installed" "Main page requires login"

apiPhp=`curl -fsSL http://localhost:${MW_SERVER_PORT}/api.php`

testContains "$apiPhp" "/api.php?action=" "Can reach api.php"

exit $STATUS
