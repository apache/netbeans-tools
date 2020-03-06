# tutorials-convert

This tool reads the NetBeans tutorials in HTML format and converts them to AsciiDoc format.

The NetBeans platform tutorials can be found in https://github.com/wadechandler/netbeans-static-site

## Getting started

1. Clone the repository:

Choose a directory of your liking and clone the repo:

    git clone https://github.com/wadechandler/netbeans-static-site.git

2. Run `mvn package exec:java X`, where "X" is the directory in the previous step.
3. Open the `tutorials-asciidoc` directory to see the results.
4. See the generated "external-links.txt" file to see referenced external links.

NOTE: This tool is expected to be run once, after that manual revision of generated files should be done.
