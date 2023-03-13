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
use SilverStripe\Core\Config\Config;

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

    protected $clearButtonEnabled = false;

    /**
     * Variable that sets the autocomplete delay
     *
     * @var integer
     */
    protected $autocompleteDelay = 300;

    /**
     * @param string $name         The field name
     * @param string $title        The label text
     * @param string $sourceObject Class name of the DataObject subclass
     * @param string $labelField   The object field used for display
     */
    public function __construct($name, $title, $sourceObject, $labelField = 'Title')
    {
        $this->sourceObject = $sourceObject;
        $this->labelField   = $labelField;
        $this->clearButtonEnabled = Config::inst()->get(HasOneAutocompleteField::class, 'clearButtonEnabled');

        $configAutocompleteDelay = intval(Config::inst()->get(HasOneAutocompleteField::class, 'autocompleteDelay'));
        if ($configAutocompleteDelay > 0) {
            $this->autocompleteDelay = $configAutocompleteDelay;
        }
      
        parent::__construct($name, $title);
    }

    /**
     * The action that handles AJAX search requests
     * @param  SS_HTTPRequest $request
     * @return json
     */
    public function search(HTTPRequest $request)
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
        $json = [];
        $count = 0;
        foreach($results as $result) {
            $name = $result->{$this->labelField};
            
            $json[$count++] = [
                'id' => $result->ID,
                'name' => $name,
                'currentString' => $this->getCurrentItemText($result)
            ];
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
        return $this;
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
        return $this;
    }


    public function getSearchCallback()
    {
        return $this->searchCallback;
    }

    public function setSearchCallback($callback)
    {
        $this->searchCallback = $callback;
        return $this;
    }

    public function getProcessCallback()
    {
        return $this->processCallback;
    }

    public function setProcessCallback($callback)
    {
        $this->processCallback = $callback;
        return $this;
    }


    public function getDefaultText()
    {
        return $this->defaultText;
    }

    public function setDefaultText($text)
    {
        $this->defaultText = $text;
        return $this;
    }

    public function getPlaceholderText()
    {
        return $this->placeholderText;
    }

    public function setPlaceholderText($text)
    {
        $this->placeholderText = $text;
        return $this;
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
        return $this;
    }

    public function enableClearButton()
    {
        $this->setClearButtonEnabled(true);
        return $this;
    }

    public function disableClearButton()
    {
        $this->setClearButtonEnabled(false);
        return $this;
    }

    private function getClearButtonEnabled()
    {
        return $this->clearButtonEnabled;
    }

    private function setClearButtonEnabled(bool $enabled = true)
    {
        $this->clearButtonEnabled = $enabled;
        return $this;
    }
      
    public function getAutocompleteDelay()
    {
        return $this->autocompleteDelay;
    }

    public function setAutocompleteDelay($delayInMilliseconds)
    {
        $this->autocompleteDelay = $delayInMilliseconds;
        return $this;
    }

    public function Field($properties = array())
    {
        Requirements::javascript('nathancox/hasoneautocompletefield: client/dist/js/hasoneautocompletefield.js');
        Requirements::css('nathancox/hasoneautocompletefield: client/dist/css/hasoneautocompletefield.css');

        $fields = FieldGroup::create($this->name);
        $fields->setName($this->name);

        $fields->push($labelField = LiteralField::create($this->name.'Label', '<span class="hasoneautocomplete-currenttext">' . $this->getCurrentItemText() . '</span>'));

        $fields->push($editField = FormAction::create($this->name.'Edit', ''));
        $editField->setUseButtonTag(true);
        $editField->setButtonContent('Edit');
        $editField->addExtraClass('edit hasoneautocomplete-editbutton btn-outline-secondary btn-sm');

        $fields->push($searchField = TextField::create($this->name.'Search', ''));
        $searchField->setAttribute('data-search-url', $this->Link('search'));
        $searchField->setAttribute('size', 40);
        $searchField->setAttribute('placeholder', $this->placeholderText);
        $searchField->addExtraClass('no-change-track hasoneautocomplete-search');

        $fields->push($idField = HiddenField::create($this->name, ''));
        $idField->addExtraClass('hasoneautocomplete-id');

        if ($this->value) {
            $idField->setValue($this->value);
        }

        $fields->push($cancelField = FormAction::create($this->name.'Cancel', ''));
        $cancelField->setUseButtonTag(true);
        $cancelField->setButtonContent('Cancel');
        $cancelField->addExtraClass('edit hasoneautocomplete-cancelbutton btn-outline-secondary');

        if ($this->getClearButtonEnabled() === true) {
            $fields->push($clearField = FormAction::create($this->name.'Clear', ''));
            $clearField->setUseButtonTag(true);
            $clearField->setButtonContent('Clear');
            $clearField->addExtraClass('clear hasoneautocomplete-clearbutton btn-outline-danger btn-hide-outline action--delete btn-sm');

            if (intval($this->value) === 0) {
                $clearField->setAttribute('style', 'display:none;');
            }
        }

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

            if (method_exists($item, "Link")) {
                $text = "<a href='{$item->Link()}' target='_blank'>".$text.'</a>';
            }
        }

        return $text;
    }



}
