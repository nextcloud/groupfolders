## 6.0.8

* [#1030](https://github.com/nextcloud/groupfolders/pull/1030) Properly cast file and groupfolder ids to strings when using them in paths
* [#1066](https://github.com/nextcloud/groupfolders/pull/1066) OCC command to empty the trashbin

## 6.0.7

* [#916](https://github.com/nextcloud/groupfolders/pull/916) Documented occ configuration @wiswedel
* [#917](https://github.com/nextcloud/groupfolders/pull/917) Make files client extension more robust to loading race conditions @juliushaertl
* [#920](https://github.com/nextcloud/groupfolders/pull/920) Changed wording in appinfo (shared WITH, not BY) @wiswedel
* [#942](https://github.com/nextcloud/groupfolders/pull/942) Make sure $path is a string @kesselb
* [#944](https://github.com/nextcloud/groupfolders/pull/944) Bump version requirement @nickvergessen
* [#946](https://github.com/nextcloud/groupfolders/pull/946) README read-ability @lrkwz
* [#980](https://github.com/nextcloud/groupfolders/pull/980) Provide a default scanner @rullzer
* [#983](https://github.com/nextcloud/groupfolders/pull/983) Do not fail if no applicable groups are setup @juliushaertl
* [#984](https://github.com/nextcloud/groupfolders/pull/984) Properly encode group names as url parameter @juliushaertl
* [#990](https://github.com/nextcloud/groupfolders/pull/990) Always delete expired versions regardless of the filecache permissions @juliushaertl

## 6.0.6

- ACL: Increase performance by selecting on indexed column @Deltachaos
- Use a lazy folder @rullzer

## 6.0.5

- Nextcloud 19 compatibility

## 6.0.4

- Check ACL before restoring files from the trashbin
- Do not allow restoring files at an existing target
- Return the mountpoint owner as a fallback
- Bump dependencies

## 6.0.3

- Do not ship unneeded files with the release

## 6.0.2

- Allow to detect the file path for shares inside of groupfolders, e.g. when they are matched in workflow rules
- Bump dependencies

## 6.0.1

- Fix sharing files from groupfolders trough ocs api
- Show the full path including groupfolder in trashbin

## 6.0.0

- Nextcloud 18 compatibility
- Search for users by display name
- Only check for admin permissions if needed
- Check for ACL list in trash backend
- Bump dependencies

## 5.0.4
- Fix etag propagation which caused the desktop client not syncing changes
- Check if the parent folder is updatable when moving

## 5.0.3
- Handle advanced permission rules for users/groups that no longer exist

## 5.0.2
- Allow longer path as groupfolder mount points

## 5.0.1
- Improved error handling when removing items from trash
- Fix groupfolders breaking updating calendar details    

## 5.0.0
- 17 compatiblity
- Use groupfolder storage for versioning

## 4.1.2
- Allow longer path as groupfolder mount points

## 4.1.1
- Improved error handling when removing items from trash
- Fix groupfolders breaking updating calendar details

## 4.1.0
- Allow groups to manage ACL permissions
- Bump dependencies
- Fix IE11 compatibility
- Check for naming conflicts before returning the user mouns

## 4.0.5
- Bump dependencies
- Update translations
- Proper values returned from Storage Wrapper fixing some etag bugs

## 4.0.4
- Fix issue with ACL cache returning empty result sets
- Bump dependencies

## 4.0.3
- Fix Collabora documents opening in read only in some cases
- Improve dark mode support

## 4.0.2
- Fix handling of public pages
- Fix advanced permissions not applying on the root of a groupfolder

## 4.0.1
- Fix not being able to delete advanced permission rules from the web interface
- Use display names in advanced permissions interface
- Fix not being able to open files in Collabora Online that don't have share permissions

## 4.0.0
- Access control list support for advanced permission management
- Improve performance of listing group folders with large filecache tables
- Block deleting of folders that have non-deletable items in them
- Improved admin page layout
- Fix groupfolder icons sometimes not being themed correctly.
- Fix moving shared groupfolder items to trashbin.

## 3.0.1

This release is aimed for Nextcloud 14/15 users who upgraded to 3.0.0 which was
falsely marked as compatible for those Nextcloud releases.

Additionally the following fixes are included

- Fix groupfolder icons sometimes not being themed correctly.
- Fix moving shared groupfolder items to trashbin. 

## 1.2.0

 - Allow changing the mount point of existing group folders
 - Add OCS api for managing folders
 - Fix folder icons in public shares
 - Merge permissions if a user has access to a folder trough multiple groups
