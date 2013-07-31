Batchtool
=========

The [eZ Publish](http://ez.no) extension [Batchtool](http://projects.ez.no/batchtool).

The version 2.0 of Batchtool have added support for class autoloading, making it more extensible.
The filter/operation classes no longer depend on being stored in the Batchtool extension.

I would appreciate feedback on wether the new version is working without problems for you,
and what operations you have used.

This version have been tested on eZ Publish 5.1, and seems to be working with the following command:

    php ezpublish/console ezpublish:legacy:script runcronjobs.php batchtool --legacy-help

This is a command line utility to do the same operations on lots of 
selected objects/nodes. For instance to move, copy or delete a bunch of
nodes. It is based on filters that fetches the objects to operate on, 
utilizing the fetch functions in eZ Publish for a flexible selection of 
objects.

This is intended as a command line tool, allthough it has been set up as a 
cronjob in case that could come in handy for some tasks.
