# GhostCMS Migrator

The documentation for this migrator has been moved to the stand-alone GhostCMS plugin.

Please see the README: https://github.com/Automattic/newspack-ghostcms-migrator

The stand-alone plugin is just a thin wrapper to the NMT GhostCMS migrator, but it's important to keep the stand-alone plugin up to date with NMT changes. So instead of having documentation in both places, we only keep the documentation in the stand-alone plugin. This forces us to document changes there and do releases there too.  

Please make every effort to not allow the stand-alone plugin to become out of date with any NMT GhostCMS changes. Follow the "Development" section of the stand-alone plugin's README when ever changes are made in this plugin.

## Differences between NMT and Stand-alone

The differences between how the GhostCMS migrator works within NMT and the stand-alone GhostCMS Migrator plugin should be kept to a minimum.  

**Known differences:**

- "Step 2" of the README requires "downloading and installing `newspack-ghostcms-migrator.zip`", which is not needed since you can just run the CLI within NMT (or NCCM).
- "Development" section of the stand-alone documentation is only needed when NMT changes need to be incorprated into a new stand-alone release.  Please keep the stand-alone plugin up to date.



