This directory can be used to customise Roundcube plugins.

When Roundcube is installed or reinstalled using CustomBuild, the contents of
this directory will be copied over into the Roundcube installation `./plugins`
directory.

To add local customisations, create the following directory and add extra files
there:

```sh
mkdir -p /usr/local/directadmin/custombuild/custom/roundcube/plugins
```

**Note**: If the `./custom/roundcube/plugins` directory is created, it replaces
the `./configure/roundcube/plugins` directory entirely. Only the files from the
custom directory will be copied over.
