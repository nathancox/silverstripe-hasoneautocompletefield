SilverStripe HasOneAutocompleteField
===================================

Overview
--------------

This module adds an field for using an autocomplete dropdown to assign as has_one relationship.  It's styled after the URLSegment field.


Maintainer Contacts
-------------------
*  Nathan Cox (<nathan@flyingmonkey.co.nz>)


Requirements
------------
* SilverStripe 4.0+


Installation Instructions
-------------------------

Via composer:

```
composer require nathancox/hasoneautocompletefield
```

Or manually download the module and place it in a folder called hasoneautocompletefield in your site root.

Visit yoursite.com/dev/build

Documentation
-------------


Example code:

```php
$fields->addFieldToTab('Root.Content', $pageField = HasOneAutocompleteField::create('LinkedPageID', 'Linked Page', 'Page', 'Title'));
$pageField->setSearchFields(array('Title', 'SomeOtherField'));
```



Known Issues
------------
[Issue Tracker](https://github.com/nathancox/silverstripe-hasoneautocompletefield/issues)
