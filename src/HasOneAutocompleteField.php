<?php

namespace NathanCox\HasOneAutocompleteField\Forms;

use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataList;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\HiddenField;

class HasOneAutocompleteField extends FormField
{
    private static $allowed_actions = [
        'search'
    ];

    /**
     * The text to show when nothing is selected
     * @var string
     */
    protected $defaultText = '(none)';

    /**
     * @var String Text shown on the search field, instructing what to search for.
     */
    protected $placeholderText = "Search";

    /**
     * @var int Number of results to return
     */
    protected $resultsLimit = 40;

    /**
     * Can provide callback functions for fetching and formatting result data in case you have unusual needs
     * @var function
     */
    protected $searchCallback = false;

    protected $processCallback = false;

    /**
     * Which object fields to search on.
     *
     * The fields support "dot-notation" for relationships, e.g.
     * a entry called "Team.Name" will search through the names of
     * a "Team" relationship.
     *
     * @example
     *  array(
     *      'Name',
     *      'Email:StartsWith',
     *      'Team.Name'
     *  )
     *
     * @var Array
     */
    protected $searchFields = false;

    /**
     * @param string $name         The field name
     * @param string $title        The label text
     * @param string $sourceObject Class name of the DataObject subclass
     * @param string $labelField   The object field used for display
     */
    public function __construct($name, $title = null, $sourceObject, $labelField = 'Title')
    {
        $this->sourceObject = $sourceObject;
        $this->labelField   = $labelField;

        parent::__construct($name, $title);
    }

    /**
     * The action that handles AJAX search requests
     * @param  SS_HTTPRequest $request
     * @return json
     */
    public function search(SS_HTTPRequest $request)
    {
        // Check form field state
        if($this->isDisabled() || $this->isReadonly()) {
            return $this->httpError(403);
        }

        $query = $request->getVar('query');

        // use callbacks if they're set, otherwise used this class's methods for search and processing
        if ($this->getSearchCallback()) {
            $results = call_user_func($this->getSearchCallback(), $query, $this);
        } else {
            $results = $this->getResults($query);
        }

        if ($this->getProcessCallback()) {
            $json = call_user_func($this->getProcessCallback(), $results, $this);
        } else {
            $json = $this->processResults($results);
        }

        return Convert::array2json($json);
    }

    /**
     * Takes the search term and returns a DataList
     * @param  string $query
     * @return DataList
     */
    protected function getResults($query)
    {
         $searchFields = ($this->getSearchFields() ?: singleton($this->sourceObject)->stat('searchable_fields'));

        if(!$searchFields) {
            throw new Exception(
                sprintf('HasOneAutocompleteField: No searchable fields could be found for class "%s"',
                $this->sourceObject));
        }

        $params = [];
        $sort = [];
        
        foreach($searchFields as $searchField) {
            $name = (strpos($searchField, ':') !== FALSE) ? $searchField : "$searchField:PartialMatch:nocase";
            $params[$name] = $query;
            $sort[$searchField] = "ASC";
        }

        $results = DataList::create($this->sourceObject)
            ->filterAny($params)
            ->sort($sort)
            ->limit($this->getResultsLimit());

        return $results;
    }

    /**
     * Takes the DataList of search results and returns the json to be sent to the front end.
     * @param  DataList
     * @return json
     */
    protected function processResults($results)
    {
        $json = array();
        foreach($results as $result) {
            $name = $result->{$this->labelField};

            $json[$result->ID] = array(
                'name' => $name,
                'currentString' => $this->getCurrentItemText($result)
            );
        }

        return $json;
    }


    /**
     * Get the class name of the objects to be searched
     * @return string A DataObject subclass
     */
    public function getSourceObject()
    {
        return $this->sourceObject;
    }

    /**
     * Set the name of the class to search for
     * @param string $sourceObject a DataObject subclass
     */
    public function setSourceObject($sourceObject)
    {
        $this->sourceObject = $sourceObject;
    }


    public function getSearchFields()
    {
        return $this->searchFields;
    }

    public function setSearchFields($fields)
    {
        if (is_array($fields)) {
            $this->searchFields = $fields;
        } else {
            $this->searchFields = array($fields);
        }
    }


    public function getSearchCallback()
    {
        return $this->searchCallback;
    }

    public function setSearchCallback($callback)
    {
        $this->searchCallback = $callback;
    }

    public function getProcessCallback()
    {
        return $this->processCallback;
    }

    public function setProcessCallback($callback)
    {
        $this->processCallback = $callback;
    }


    public function getDefaultText()
    {
        return $this->defaultText;
    }

    public function setDefaultText($text)
    {
        $this->defaultText = $text;
    }

    public function getPlaceholderText()
    {
        return $this->placeholderText;
    }

    public function setPlaceholderText($text)
    {
        $this->placeholderText = $text;
    }

    /**
     * Gets the maximum number of autocomplete results to display.
     * @return int
     */
    public function getResultsLimit()
    {
        return $this->resultsLimit;
    }

    /**
     * Sets the maximum number of results to return;
     * @param int $limit
     */
    public function setResultsLimit($limit)
    {
        $this->resultsLimit = $limit;
    }


    public function Field($properties = array())
    {
        Requirements::javascript('nathancox/hasoneautocompletefield: client/dist/js/hasoneautocompletefield.js');
        Requirements::css('nathancox/hasoneautocompletefield: client/dist/css/hasoneautocompletefield.css');

        $fields = FieldGroup::create($this->name);
        $fields->setID("{$this->name}_Holder");

        $fields->push($labelField = LiteralField::create($this->name.'Label', '<span class="hasoneautocomplete-currenttext">' . $this->getCurrentItemText() . '</span>'));

        $fields->push($editField = FormAction::create($this->name.'Edit', ''));
        $editField->setUseButtonTag(true);
        $editField->setButtonContent('Edit');
        $editField->addExtraClass('edit hasoneautocomplete-editbutton ss-ui-button-small');

        $fields->push($searchField = TextField::create($this->name.'Search', ''));
        $searchField->setAttribute('data-search-url', $this->Link('search'));
        $searchField->setAttribute('size', 40);
        $searchField->setAttribute('placeholder', $this->placeholderText);
        $searchField->addExtraClass('no-change-track hasoneautocomplete-search');

        $fields->push($idField = HiddenField::create($this->name, ''));
        $idField->addExtraClass('hasoneautocomplete-id');

        $fields->push($cancelField = FormAction::create($this->name.'Cancel', ''));
        $cancelField->setUseButtonTag(true);
        $cancelField->setButtonContent('Cancel');
        $cancelField->addExtraClass('edit hasoneautocomplete-cancelbutton ss-ui-button-small ss-ui-action-minor');

        return $fields;
    }


    /**
     * Get the currently selected object
     * @return DataObject
     */
    function getItem()
    {
        $sourceObject = $this->sourceObject;
        if ($this->value !== null) {
            $item = $sourceObject::get()->byID($this->value);
        } else {
            $item = $sourceObject::create();
        }
        return $item;
    }



    /**
     * Return the text to be dislayed next to the "Edit" button indicating the currently selected item.
     * By default is displays $labelField and wraps it in a link if the object has the Link() method.
     * @param  DataObjext $item
     * @return string
     */
    function getCurrentItemText($item = null)
    {
        $text = $this->getDefaultText();

        if (is_null($item)) {
            $item = $this->getItem();
        }


        if ($item && $item->ID > 0) {
            $labelField = $this->labelField;
            if (isset($item->$labelField)) {
                $text = $item->$labelField;
            } else {
                user_error("PageSearchField can't find field called ".$labelField."on ".$item->ClassName, E_USER_ERROR);
            }

            if ($item->Link()) {
                $text = "<a href='{$item->Link()}' target='_blank'>".$text.'</a>';
            }
        }

        return $text;
    }



}
