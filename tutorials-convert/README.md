# tutorials-convert

This tool reads the NetBeans tutorials in HTML format and converts them to AsciiDoc format.

The NetBeans platform tutorials can be found in https://svn.netbeans.org/svn/platform~platform-content/trunk/tutorials/

The NetBeans platform tutorial images can be found in https://svn.netbeans.org/svn/platform~platform-content/trunk/images/


## Getting started

1. Check out the tutorials from SVN above in a directory "X".
2. Check out the images from SVN above in directory "Y".
2. Run `mvn package exec:java X Y`, where "X" is the directory in the previous step.
4. Open the `tutorials-asciidoc` directory to see the results.
5. See the generated "external-links.txt" file to see referenced external links.

NOTE: This tool is expected to be run once, after that manual revision of generated files should be done.
