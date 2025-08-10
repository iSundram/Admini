This directory can be used to customise Roundcube core files.

When Roundcube is installed or reinstalled using CustomBuild, the contents of
this directory will be copied over into the Roundcube installation `./program`
directory.

To add local customisations, create the following directory and add extra files
there:

```sh
mkdir -p /usr/local/directadmin/custombuild/custom/roundcube/program
```

**Note**: If the `./custom/roundcube/program` directory is created, it replaces
the `./configure/roundcube/program` directory entirely. Only the files from the
custom directory will be copied over.
