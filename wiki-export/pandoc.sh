#!/bin/sh

mkdir -p wiki-asciidoc
for A in wiki-wikimedia/*.mediawiki
do
    W=`basename $A .mediawiki`
    B="wiki-asciidoc/$W.asciidoc"
    echo "Converting $W"
    cat apache-license.asciidoc > $B
    cat << EOF >> $B
= $W
:jbake-type: page
:jbake-tags: devfaq, wiki
:jbake-status: published
:title: $W

EOF
    cat $A | sed 's/{|-/{|/g' | \
        sed 's/^| A specific lookup for this editor to query for possible values, instead of using the default lookup/| A specific lookup for this editor to query for possible values, instead of using the default lookup|}/g' | \
        /usr/bin/pandoc -f mediawiki -t asciidoc --normalize --base-header-level=1 --wrap=none --atx-headers --section-divs | \
        sed 's/link:\([^[]\+\)/link:\1.html/g' >> $B
done
    
