![Logo Kumquat Dev](kumquat_dev.png)

* **[Kumquat Dev](#intro)**
* **[Installation](#installation)**
* **[Available tools](#tools)**
* **[Credits](#credits)**

# <a name="intro"></a>Kumquat Dev

Kumquat Dev is a module that includes some Developer experience (DX) 
improvements for [Kumquat](https://github.com/Happyculture/kumquat) based 
projects.

## <a name="installation"></a>Installation

- `composer require happyculture/kumquat_dev --dev`
- `drush en kumquat_dev`

## <a name="tools"></a>Available tools

### Drush commands

#### drush kumquat:update (kup)

Helper used to update dependencies. It runs `composer update` on the given
dependency then `drush updb`, then `drush cex`, then adds all files to the git
staging and finally offers to create the commit if you want.

Some options allow you to control how the composer update is made, to provide
the issue number to use in the commit message or even to make the commit
without asking if you are very confident. See `drush help kumquat:update`.

#### drush kumquat:export-module-config (kex)

Helper used to maintain functional submodules containing configuration objects
like node types or paragraph types. Any module can declare in its info.yml file
which configuration objects it needs and which one it doesn't want. Then, when
running this command, every listed configuration object and their dependencies
will be exported in the module's config/optional directory.

Example of info.yml syntax:
```yaml
kumquat_export:
  include:
    - paragraphs.paragraphs_type.rich_text
  exclude:
    - field.storage.paragraph.rich_text_style
```

#### drush generate pattern

Helper to generate a pattern according to the UI Patterns definition. It asks
you a few questions then creates the directory and needed files so you can
start theming quickly.

# <a name="credits"></a>Credits

The font used for the logo is [Smooth Butter from PutraCetol Studio](https://putracetol.com/product/smooth-butter/).
