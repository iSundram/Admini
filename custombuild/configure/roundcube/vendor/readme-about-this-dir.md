This directory can be used to customise Roundcube dependencies.

When Roundcube is installed or reinstalled using CustomBuild, the contents of
this directory will be copied over into the Roundcube installation `./vendor`
directory.

To add local customisations, create the following directory and add extra files
there:

```sh
mkdir -p /usr/local/directadmin/custombuild/custom/roundcube/vendor
```

**Note**: If the `./custom/roundcube/vendor` directory is created, it replaces
the `./configure/roundcube/vendor` directory entirely. Only the files from the
custom directory will be copied over.
