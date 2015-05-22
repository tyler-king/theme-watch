# Theme Watcher

A simple tool I wrote for a specific use-case (Wordpress related) which allows for "watching" a folder for changes and syncing it to a remote location via FTP.

It handles file (creation, modify, and delete), directory (create, recursive create). It has commands for watching, checking, and uploading the entire directory or select files.

It utilizes Symgony's Console, touki/ftp (for FTP), jasonlewis/resource-watcher (for watching)

This project needs more testing, consider it a beta and work-in-progress as I add more features.

## Requirments

+ PHP 5.4 or higher
+ Coffee or beer

## Installation

### Part One

1. Clone this directory
2. Open your CLI and `cd` into the newly cloned repository
3. Run `composer install` to install the vendor dependencies

### Part Two

Please view `config.yml.sample` for an example configuration. Setup your own `config.yml` and place it into the directory you wish this project to handle.

### Part Three (optional)

Symlink the command to your local bin for easy access

```bash
ln -s /{path}/{to}/{this}/{repo}/bin/theme /usr/local/bin/theme-watcher
```

Now you can simply type `theme-watcher` and the command *should* work.

## Usage

`bin/theme theme:check`

Confirms your config (ftp and paths) are OK

`bin/theme theme:upload [files (optional)]`

Upload entire local theme to remote or list out the files (seperated by spaces)

`bin/theme theme:watch`

Watches the current directory for file modifications, creations, and deletions; pushes it to the remote location via FTP

## In-Action

http://gfycat.com/ImmenseOpenBorer


