# iqomp/watcher

A composer plugin to run cli script when it detect some file changes on working
directory. This plugin really help on developing some application with self
server like [swoole](https://www.swoole.co.uk/) where you need to restart the
server on every file update.

## Installation

```
composer require iqomp/watcher --dev
```

## Usage

This module add new composer command named `watch` that will watch for file changes
on current directory recursively, and run the provided argument as a script.

```bash
composer watch "php index.php start" --ignore="runtime" --ignore="cache"
```

Above script will run `php index.php start` on the first run, and watch for file
changes in current directory while ignoring directory `runtime` and `cache`
relative to current directory. When file changes found, the previous script
process get killed and new process is executed.
