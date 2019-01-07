# html-convert

This tool reads tutorials in HTML format from the 3rd Oracle donation and converts these to the AsciiDoc format.

## Getting started

1. Download and extract the third Oracle donation zip file. This will generate a "netbeans-docs.zip" file.
2. Extract the "netbeans-docs.zip" zip file somewhere in a directory "X". This will generate the "X/docs" folder.
3. Run `mvn package exec:java X`, where "X" is the directory where you extracted the docs zip file.
4. Open the `tutorials-asciidoc` directory to see the results.
5. See the generated "external-links.txt" file to see referenced external links.

NOTE: This tool is expected to be run once, after that manual revision of generated files should be done.
