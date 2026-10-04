# _restructure_backup — the pre-restructure code

This directory is a complete copy of the application **as it was before** the
file/folder restructure. It is kept so the change can be reviewed or rolled back.

## What is in here

| | |
|---|---|
| `*.php` (54 files) | every root page, including the 15 report generators |
| `action/` | the old action endpoints (now `actions/` + `api/`) |
| `css/styles.css` | the old shared stylesheet (now `assets/css/styles.css`) |
| `js/` | the old shared scripts (now `assets/js/`) |
| `img/` | the old images (now `assets/images/`) |
| `includes/` | the old layout partials + `db_config.php` (now `config/db_config.php`) |

## How it was used

* the restructure was applied by a one-off codemod that read these files and
  wrote the new layout;
* every report generator in the live tree was diffed against its copy here.
  `*_pdf.php` is identical apart from the single connection line:

  ```diff
  - require_once 'includes/db_config.php';
  + require_once 'config/db_config.php';
  ```

* a runnable copy of this tree was served side by side with the restructured app
  and all 296 page × role combinations were compared (status codes, redirect
  targets, and any PHP failure). The two behaved identically.

## Rolling back

Copy the tree back over the project and delete the new layout:

```powershell
cd E:\laragon\www\OCP
Remove-Item -Recurse -Force actions, api, assets, config
Copy-Item -Force _restructure_backup\*.php .
foreach ($d in 'action','css','js','img','includes') {
  Remove-Item -Recurse -Force $d
  Copy-Item -Recurse -Force "_restructure_backup\$d" $d
}
```

Delete this directory once you are satisfied with the new layout.
