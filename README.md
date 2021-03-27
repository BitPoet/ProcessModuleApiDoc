# ProcessModuleApiDoc
On-the-fly class documentation viewer for the [ProcessWire CMS](https://processwire.com)

## What it does
Adds a "Module API Docs" menu entry in the ProcessWire backend that lets you view on-the-fly generated PHP doc for modules and other PHP files.

## Requirements

- ProcessWire >= 3.0.127
- TextformatterMarkdownExtra (shipped with ProcessWire)
- PHP-Parser (see Installation further down)

## Why

Running PHPDocumentor or other tools with the same purpose to view the inline documentation of a PHP class can more often than not become quite an effort.

This module parses and documents PHP files on the fly, so documentation doesn't have to be generated from scratch when the PHP file changes.

## Module Status

Alpha - try out at your own risk. Do not use on production systems!

## What it Displays

The documentation viewer displays the following information:

- Classes
- Public class properties with default value types
- Public class methods with return type and arguments (name, type, default value), and whether a method is static
- Hookable methods (with the same information detail as methods)
- Functions with return type and arguments (name, type, default value

For each of those, any summary and description found in PHP Doc style comments is also displayed. Descriptions containing markdown are formatted accordingly.

## Downsides

There aren't any links and dependencies between files and classes to see. That part is what makes the "grown up" tools complicated, slow and memory intensive. This module is intended as a lean solution to take a quick peek at properties, methods and hooks without having to dig through the source code.

## Installation

This module ships with a composer.json file for PHP-Parser, so installation should be straight forward. You do need to have shell access on the computer where you install this module, though.

- Extract the contents of the ZIP file into its own folder underneath site/modules
- Open a shell (bash, cmd, ...) and go into the module's folder
- Execute ```composer update``` to install [PHP-Parser](https://github.com/nikic/PHP-Parser)
- Go into the PW backend and select "Modules" -> "Refresh"
- Click "Install" for "TextformatterMarkdownExtra" from the Core modules if not installed yet
- Click "Install" for ProcessModuleApiDocs (Module Api Doc Viewer) from the Site modules
- Go to "Setup" -> "Module API Docs" and enjoy

## Overview

![Screenshot over over page](https://raw.githubusercontent.com/BitPoet/bitpoet.github.io/master/img/apidocs1.png)

## Documentation View

![Screenshot of documentation view](https://raw.githubusercontent.com/BitPoet/bitpoet.github.io/master/img/apidocs2-view.png)

## Technical Stuff

The module uses PHP-Parser behind the scenes to create an abstract syntax tree for the selected PHP file. That syntax tree is then walked and normalized to create a representation of namespaces, classes, methods and properties. Additionally, comments are parsed in PHP Doc style.

## Kudos

Big thanks go to [Nikita Popov](https://github.com/nikic) who built [PHP-Parser](https://github.com/nikic/PHP-Parser).
