SilverStripe HasOneAutocompleteField
===================================

Overview
--------------

This module adds a field for using an autocomplete dropdown to assign a has_one relationship.  It's styled after the URLSegment field.


Maintainer Contacts
-------------------
*  Nathan Cox (<nathan@flyingmonkey.co.nz>)


Requirements
------------
* SilverStripe 4.0+

For SilverStripe 3.x see the version 1 branch on Github: https://github.com/nathancox/silverstripe-hasoneautocompletefield/tree/1


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
<?php

use SilverStripe\CMS\Model\SiteTree;
use NathanCox\HasOneAutocompleteField\Forms\HasOneAutocompleteField;

class Page extends SiteTree
{
    private static $db = [];

    private static $has_one = [
        'LinkedPage' => 'Page'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab('Root.Content', $pageField = HasOneAutocompleteField::create('LinkedPageID', 'Linked Page', 'Page', 'Title'));
        $pageField->setSearchFields(array('Title', 'Content'));

        return $fields;
    }
}

```


Known Issues
------------
[Issue Tracker](https://github.com/nathancox/silverstripe-hasoneautocompletefield/issues)
